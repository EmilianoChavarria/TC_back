# TCProject — Backend

API Laravel 13 con el sistema de autenticación y seguridad: login por correo y
contraseña, JWT en cookie httpOnly, tres roles fijos, bloqueo de usuarios e IPs
por intentos fallidos, configuración de contraseñas/sesión y envío de correo por
SendGrid con modo override.

## Puesta en marcha

```bash
composer install
cp .env.example .env
php artisan key:generate
# crear la base de datos indicada en DB_DATABASE
php artisan migrate --seed
php artisan serve
```

El seeder crea los tres roles, la configuración inicial del sistema y la cuenta
de superadministrador definida en `.env`
(`SUPERADMIN_EMAIL` / `SUPERADMIN_PASSWORD`), marcada para cambiar contraseña en
el primer acceso.

## Identificadores públicos

La API **no expone llaves primarias**. Las tablas que se referencian desde el
cliente (`users`, `userblockedhistory`, `ipblockedhistory`) llevan una columna
`uuid` (UUIDv7, único) además de su PK `BIGINT` interna, y sólo el `uuid` sale
en las respuestas, en las URLs y en el `sub` del JWT. Los roles se identifican
por `roleName` y las IPs por su dirección. Las tablas singleton no exponen
identificador alguno. El detalle de la convención está en `CLAUDE.md`.

## Roles

Los roles **no son dinámicos**: `SUPERADMIN`, `ADMIN` y `USER` (constantes en
`App\Models\Role`).

| | SUPERADMIN | ADMIN | USER |
|---|---|---|---|
| Administra seguridad y configuración | Sí | Sí | No |
| Alta de usuarios | Sí | Sí | No |
| Puede crear la cuenta ADMIN | Sí | No | No |
| Cuentas activas permitidas | 1 | 1 | Sin límite |
| Su cuenta se bloquea por intentos fallidos | **No** | **No** | Sí |
| Su IP se bloquea por intentos fallidos | Sí | Sí | Sí |

## Autenticación

- `POST /api/auth/login` valida credenciales y responde con `Set-Cookie:
  access_token=<JWT>; HttpOnly`. El token nunca se devuelve en el cuerpo de la
  respuesta; el middleware acepta además `Authorization: Bearer <token>` para
  clientes que no usan cookies.
- El TTL del token es el **tiempo máximo de inactividad** configurado en
  `loginattemptsettings.sessionTimeoutMinutes`.
- Sesión única por usuario: el token vigente se guarda en
  `usersecurity.sessionToken`; un login nuevo invalida el anterior.
- `GET /api/auth/verify` revalida la sesión y **renueva** el token (sliding
  session). Devuelve 401 y limpia la cookie cuando ya no es válida.
- Inactividad: si `lastActivityAt` supera `sessionTimeoutMinutes`, la sesión se
  cierra automáticamente (401 «Sesión expirada por inactividad»).
- Mientras `users.mustChangePassword` sea verdadero, el middleware sólo permite
  `auth/verify`, `auth/logout`, `auth/change-password` y `password-requirements`.

Configuración de la cookie en `config/security.php` (`AUTH_COOKIE_*`). Para un
frontend en otro dominio: `AUTH_COOKIE_SAME_SITE=None` + `AUTH_COOKIE_SECURE=true`
(exige HTTPS) y el origen declarado en `CORS_ALLOWED_ORIGINS` — con cookies no se
admite `*`.

## Bloqueos

Los fallos se cuentan en una **ventana deslizante de 24 h**
(`SECURITY_ATTEMPT_WINDOW_HOURS`); un fallo posterior a la ventana reinicia el
contador. Un login correcto lo pone a cero.

- Al alcanzar `maxUserAttempts` el **usuario** queda bloqueado. El bloqueo no
  caduca solo: lo libera un SUPERADMIN o ADMIN.
- Al alcanzar `maxIpAttempts` la **dirección IP** queda bloqueada, para
  cualquier rol.
- Excepción operativa: una sesión ya activa de SUPERADMIN/ADMIN sigue
  funcionando desde una IP bloqueada, para que puedan liberarla desde el panel.
  El *login* desde esa IP se rechaza para todos, sin excepción.
- Salida de emergencia por consola:
  `php artisan security:unlock-ip 1.2.3.4` y
  `php artisan security:unlock-user correo@dominio.com`.

Cada bloqueo y desbloqueo queda registrado en `userblockedhistory` e
`ipblockedhistory` (motivo, fallos, IP, administrador que liberó).

## Configuración del Sistema

`passwordrequirements`: longitud mínima, vigencia en días (`expirationDays`,
0 = sin vencimiento), mayúscula, minúscula, número y carácter especial con lista
de caracteres permitidos.

Al caducar la contraseña el usuario puede iniciar sesión, pero queda obligado a
cambiarla antes de usar el resto de la API.

`loginattemptsettings`: `maxUserAttempts`, `maxIpAttempts` y
`sessionTimeoutMinutes`. Ambas tablas guardan `updatedByUserId` para mostrar
«última actualización aplicada por…».

## Tipo de cambio

Banxico publica el FIX (serie `SF43718`) cada dia habil y esa publicacion aplica
al **dia habil siguiente**. El proceso diario `exchange-rate:sync` la consulta y
la guarda tal cual como tipo de cambio de la fecha aplicable. Ademas busca el
factor vigente cuyo rango la contiene -`[desde, hasta)`, inferior inclusivo y
superior exclusivo- y lo guarda junto al registro **solo como dato informativo**:
al tipo de cambio no se le aplica ninguna operacion.

La publicacion nunca se sobrescribe. La correccion manual se hace sobre el tipo
de cambio de Banxico, se guarda aparte, exige motivo y prevalece como valor
vigente, incluso si la sincronizacion automatica llega despues. Solo se puede
capturar el dia en curso y el dia habil siguiente. `exchange-rate:reset-manual`
limpia las capturas manuales y devuelve el vigente a la publicacion de Banxico.

Requiere `BANXICO_TOKEN` y un cron que ejecute `php artisan schedule:run` cada
minuto; la programacion esta en `routes/console.php`.

## Días feriados

Determinan el día hábil siguiente con el que se calcula el tipo de cambio. Se
administra el año en curso y el siguiente; la fecha es única entre los vigentes
y la baja es lógica.

A partir del 15 de noviembre (`HOLIDAYS_REMINDER_START`), `holidays:remind`
envía un correo diario a todos los usuarios activos mientras el año siguiente no
tenga ni un día capturado, y deja de enviarse solo en cuanto se captura el
primero. Cada envío queda en la auditoría como `holidayReminder.sent`.

## Auditoría

Toda solicitud que modifica estado queda registrada, en dos niveles enlazados:
`requestlogs` (una fila por solicitud: método, ruta, estado, duración, autor, IP
y cuerpo saneado) y `auditlogs` (una fila por registro afectado: tabla, uuid del
registro, evento, columnas cambiadas con valores antes/después y las marcas de
tiempo del registro).

Los eventos son `created`, `updated`, `softDeleted`, `restored` y `deleted`, más
eventos propios del dominio como `email.sent`. Cuando el cambio no lo origina una
persona, el autor queda como «Proceso automático».

No hay que hacer nada por endpoint: el middleware cubre el grupo `api` completo y
el trait `Auditable` cubre los modelos. Contraseñas, hashes y tokens se guardan
como `[REDACTADO]`; los ids internos no se guardan. Ver `config/audit.php` y
`CLAUDE.md`.

## Correo

`MAIL_MAILER=sendgrid` + `SENDGRID_API_KEY` (transporte registrado en
`AppServiceProvider` sobre `symfony/sendgrid-mailer`). Con `MAIL_MAILER=log` el
correo se escribe en `storage/logs`.

Todo el correo del portal va en español; no hay preferencia de idioma por
usuario.

Todo el correo sale por `EmailSenderService`, que respeta el modo guardado en
`emailconfig`:

| Modo | Comportamiento |
|---|---|
| `normal` | Se envía al destinatario real |
| `override` | Todo se redirige a `overrideEmail`; la plantilla indica a quién se habría enviado |
| `disabled` | No se envía nada |

## Endpoints

| Método | Ruta | Acceso |
|---|---|---|
| GET | `/api/health` | Público |
| POST | `/api/auth/login` | Público (20 req/min) |
| GET | `/api/auth/verify` | Sesión |
| GET | `/api/auth/me` | Sesión |
| POST | `/api/auth/logout` | Sesión |
| POST | `/api/auth/change-password` | Sesión |
| POST | `/api/auth/register` | SUPERADMIN, ADMIN |
| GET | `/api/users` | SUPERADMIN, ADMIN |
| GET | `/api/users/roles` | SUPERADMIN, ADMIN |
| GET | `/api/users/{uuid}` | SUPERADMIN, ADMIN |
| PUT | `/api/users/{uuid}` | SUPERADMIN, ADMIN |
| DELETE | `/api/users/{uuid}` | SUPERADMIN, ADMIN |
| POST | `/api/users/{uuid}/restore` | SUPERADMIN, ADMIN |
| POST | `/api/users/{uuid}/reset-password` | SUPERADMIN, ADMIN |
| POST | `/api/password-requirements/validate` | Público (60 req/min) |
| GET | `/api/password-requirements` | Sesión |
| PUT | `/api/password-requirements` | SUPERADMIN, ADMIN |
| GET/PUT | `/api/security/login-attempt-settings` | SUPERADMIN, ADMIN |
| GET | `/api/security/summary` | SUPERADMIN, ADMIN |
| GET | `/api/security/users/blocked` | SUPERADMIN, ADMIN |
| GET | `/api/security/ips/blocked` | SUPERADMIN, ADMIN |
| POST | `/api/security/users/{uuid}/unlock` | SUPERADMIN, ADMIN |
| POST | `/api/security/ips/unlock` | SUPERADMIN, ADMIN |
| GET/PUT | `/api/email-config` | SUPERADMIN, ADMIN |
| POST | `/api/email-config/test` | SUPERADMIN, ADMIN |
| GET | `/api/dashboard/exchange-rate` | Sesión |
| GET | `/api/dashboard/exchange-rate/fluctuation` | Sesión |
| GET | `/api/exchange-rates` | Sesión |
| GET | `/api/exchange-rates/current` | Sesión |
| GET | `/api/exchange-rates/editable-dates` | Sesión |
| GET | `/api/exchange-rates/factors` | Sesión |
| POST | `/api/exchange-rates` | SUPERADMIN, ADMIN |
| PUT | `/api/exchange-rates/{uuid}` | SUPERADMIN, ADMIN |
| DELETE | `/api/exchange-rates/{uuid}` | SUPERADMIN, ADMIN |
| POST | `/api/exchange-rates/sync` | SUPERADMIN, ADMIN |
| POST | `/api/exchange-rates/factors` | SUPERADMIN, ADMIN |
| POST | `/api/exchange-rates/factors/bulk` | SUPERADMIN, ADMIN |
| PUT | `/api/exchange-rates/factors/{uuid}` | SUPERADMIN, ADMIN |
| DELETE | `/api/exchange-rates/factors/{uuid}` | SUPERADMIN, ADMIN |
| POST | `/api/exchange-rates/factors/{uuid}/restore` | SUPERADMIN, ADMIN |
| GET | `/api/holidays` | Sesión |
| GET | `/api/holidays/status` | Sesión |
| POST | `/api/holidays` | SUPERADMIN, ADMIN |
| POST | `/api/holidays/bulk` | SUPERADMIN, ADMIN |
| PUT | `/api/holidays/{uuid}` | SUPERADMIN, ADMIN |
| DELETE | `/api/holidays/{uuid}` | SUPERADMIN, ADMIN |
| POST | `/api/holidays/{uuid}/restore` | SUPERADMIN, ADMIN |
| GET | `/api/audit/logs` | SUPERADMIN, ADMIN |
| GET | `/api/audit/records/{table}/{uuid}` | SUPERADMIN, ADMIN |
| GET | `/api/audit/requests` | SUPERADMIN, ADMIN |

Los listados de bloqueos son paginados (`perPage`, máx. 100) e incluyen el
historial completo; con `?onlyActive=1` sólo los que siguen bloqueados.

Todas las respuestas comparten la forma de `App\Support\ApiResponse`:

```json
{ "codeStatus": 200, "success": true, "message": "...", "data": {}, "errors": null, "timestamp": "..." }
```

## Notas para el frontend

- Las peticiones deben ir con `credentials: 'include'`.
- `POST /api/auth/register` recibe el rol por `roleName` (`SUPERADMIN`, `ADMIN`,
  `USER`), no por id, y lo asume como `USER` cuando se omite. `password` es
  opcional: si no se envía, el backend genera una contraseña temporal válida y la
  manda por correo. Sólo un SUPERADMIN puede crear cuentas privilegiadas, y de
  `SUPERADMIN` y `ADMIN` sólo puede existir una cuenta activa de cada uno.
- El campo `sessionTimeoutMinutes` de login/verify sirve para programar el aviso
  de cierre por inactividad en la interfaz.
