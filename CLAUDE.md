# TCProject — Backend

API Laravel 13 (PHP 8.3, MySQL). Autenticación por correo y contraseña, JWT en
cookie httpOnly, tres roles fijos, bloqueo de usuarios e IPs por intentos
fallidos, configuración del sistema y envío de correo por SendGrid.

## Regla número uno: no se exponen identificadores internos

**Ninguna respuesta de la API, ningún claim del JWT y ninguna URL puede contener
una llave primaria de base de datos.** Esto no es preferencia de estilo: los
enteros secuenciales permiten enumerar recursos y filtran volumen de negocio
(`id=2` dice cuántos usuarios hay). Es una capa de defensa adicional, **nunca un
reemplazo de la autorización**: cada endpoint sigue obligado a verificar rol y
pertenencia por su cuenta.

Estrategia adoptada (opción B):

- La **PK sigue siendo `BIGINT` autoincremental**. Los joins y las FK internas
  usan enteros de 8 bytes.
- Las tablas que se referencian desde afuera llevan además una columna
  **`uuid` (UUIDv7, `CHAR(36)`, único)**. UUIDv7 es ordenado por tiempo, así que
  el índice único no se fragmenta como lo haría un v4 aleatorio.
- Hacia afuera **sólo viaja el `uuid`**.

No se migró la PK a UUID a propósito: en InnoDB la PK es el índice clusterizado
y los 16 bytes se copian a cada índice secundario y cada FK, encareciendo cada
escritura de forma permanente sin ganar nada que no dé ya la columna paralela.

### Qué tabla lleva `uuid` y por qué

| Tabla | `uuid` | Motivo |
|---|---|---|
| `users` | Sí | Se referencia en respuestas, URLs y en el `sub` del JWT |
| `userblockedhistory` | Sí | Cada fila del historial necesita clave estable para el front |
| `ipblockedhistory` | Sí | Igual que la anterior |
| `roles` | No | Catálogo cerrado de 3 filas: se expone `roleName`, no un id |
| `blockedips` | No | Su PK es la dirección IP, que es el dato de negocio y debe mostrarse |
| `usersecurity` | No | Nunca se expone; su PK es la FK a `users` |
| `loginattemptsettings` | No | Singleton: una fila, sin nada que referenciar |
| `passwordrequirements` | No | Singleton |
| `emailconfig` | No | Singleton |
| `exchangeratefactors` | Sí | Se edita y elimina desde el cliente |
| `exchangerates` | Sí | Se corrige desde el cliente y tiene historial propio |
| `holidays` | Sí | Se edita y elimina desde el cliente |

De los singleton se **omite el campo `id` en el resource**; no se sustituye por
nada, porque el cliente no necesita referenciarlos.

### Cómo se aplica en código

- Los modelos con `uuid` usan `HasUuids` con `uniqueIds()` sobrescrito a
  `['uuid']`, para que el trait rellene esa columna y **no** toque la PK.
- `getRouteKeyName()` devuelve `'uuid'`: el route model binding resuelve por
  uuid de forma transparente.
- Las rutas usan `{uuid}`, nunca `{id}`.
- El JWT lleva `sub` = uuid del usuario y `roleName`. **No lleva ids.** El
  payload va en base64 dentro de la cookie y cualquiera puede decodificarlo.
- Las reglas de validación no referencian ids: se valida contra `roleName` o
  contra `uuid`, nunca `exists:tabla,id`.

### Al agregar una tabla o un endpoint

1. ¿Se va a referenciar desde el cliente? Entonces agrega `uuid` único, con la
   misma forma que las existentes, y backfill en la propia migración.
2. ¿Es singleton o catálogo cerrado? No agregues `uuid`; expón un campo natural
   (`roleName`, la IP, etc.) o ningún identificador.
3. El `JsonResource` es el **único** lugar donde se arma la salida. Ningún
   resource emite `$this->id` ni un `*Id`. Los controladores no devuelven
   modelos crudos ni arrays armados a mano.
4. Antes de dar por terminado, revisa que la respuesta no traiga ids: los
   `whenLoaded` de relaciones son el descuido típico.

## Otras reglas de no filtración

- **Nunca** se registran en log ni se devuelven contraseñas, hashes, el JWT
  completo ni el `sessionToken`. `EmailSenderService` loguea destinatarios y
  asunto, jamás el cuerpo.
- Los mensajes de login son genéricos (`Credenciales inválidas`) para no revelar
  si un correo existe. El caso de cuenta bloqueada sí se distingue, porque el
  usuario necesita saber que debe pedir el desbloqueo.
- El detalle de excepciones sólo viaja con `APP_DEBUG=true`. En producción se
  responde un mensaje genérico (ver `bootstrap/app.php`).
- Este archivo, el README y la colección de Postman **no contienen credenciales
  ni identificadores reales**. Las credenciales viven sólo en `.env`.

## Auditoría: obligatoria en todo endpoint

**Toda solicitud que modifica estado queda registrada, sin excepción.** No es
algo que se agregue endpoint por endpoint: el middleware `AuditRequests` envuelve
el grupo `api` completo y el trait `Auditable` engancha los eventos de Eloquent.
Un endpoint nuevo queda auditado sin escribir una línea extra.

Dos tablas complementarias:

| Tabla | Granularidad | Origen |
|---|---|---|
| `requestlogs` | Una fila por solicitud: método, ruta, estado, duración, autor, IP, agente y cuerpo saneado | Middleware `AuditRequests` |
| `auditlogs` | Una fila por registro afectado: tabla, uuid del registro, evento, diff, marcas de tiempo del registro y autor | Trait `Auditable` sobre eventos de Eloquent |

`auditlogs.requestLogId` enlaza ambas: desde una solicitud se ven todos los
registros que tocó, y desde un cambio se ve la solicitud que lo originó. Es nulo
cuando el cambio nace de consola o de un proceso automático.

### Cómo funciona

- Durante una solicitud los cambios se acumulan en memoria (`AuditContext`) y se
  escriben al final, cuando ya existe el `requestlogs` al que enlazarlos. Fuera
  de una solicitud se escriben de inmediato, con autor `Proceso automático`.
- El autor se toma del usuario resuelto por `jwt`; se guarda la FK **y** una
  copia de nombre y rol, para que el historial siga siendo legible aunque la
  cuenta se elimine después.
- Los eventos son `created` (Alta), `updated` (Actualización), `softDeleted`
  (Eliminación lógica), `restored` (Restauración) y `deleted` (Eliminación). Una
  baja lógica se detecta por el cambio de `deletedAt`, no por el método HTTP.
- En un `updated` se guarda **sólo lo que cambió**: `changedColumns`,
  `oldValues` y `newValues` con esas columnas. En un `created`, el registro
  completo. También se copian `createdAt` y `updatedAt` del registro afectado.
- Un fallo de auditoría **nunca** tumba la operación: se registra en el log de
  la aplicación y la respuesta sigue su curso.

### Qué nunca se almacena

`config/audit.php` manda. Las claves de `redacted` (contraseñas, hashes,
tokens, `authorization`…) se sustituyen por `[REDACTADO]` tanto en el cuerpo de
la solicitud como en los diffs: queda constancia de *que* la contraseña cambió,
nunca de su valor. Las llaves primarias (`id`, `roleId`, `userId`, …) se
descartan del payload y de los diffs, igual que en el resto de la API. Las
columnas de `ignored_columns` (`lastActivityAt`, `lastKnownIp`, …) no generan
ruido de sesión. Los payloads enormes se sustituyen por un resumen.

### Al agregar un modelo o un evento

1. Modelo que deba auditarse: agrega el trait `Auditable`. Opcionalmente define
   `auditLabel()` (el texto que se ve en la línea de tiempo) y `$auditExcluded`
   (columnas fuera del diff).
2. **No** audites tablas de alta frecuencia que ya tienen su propia bitácora:
   `usersecurity` cambia en cada petición y `blockedips` en cada fallo; sus
   hechos relevantes viven en `userblockedhistory` e `ipblockedhistory`.
3. Acción sin modelo detrás (un correo enviado, un proceso programado):
   `AuditRecorder::event('email.sent', 'emails', $uuid, $etiqueta, $detalles)`.
   El segundo argumento es un nombre lógico, no necesariamente una tabla real.
4. Cargas iniciales y migraciones de datos van dentro de
   `AuditContext::withoutAuditing(...)`, como hacen los seeders.

### Consulta

`GET /api/audit/logs` (línea de tiempo, con filtros `table`, `recordUuid`,
`event`, `actorUuid`, `from`, `to`), `GET /api/audit/records/{table}/{uuid}`
(historial de un registro, que es lo que alimenta el «Historial de la pantalla»)
y `GET /api/audit/requests` (bitácora de solicitudes con sus cambios). Todo
restringido a SUPERADMIN y ADMIN.

## Tipo de cambio y factores

El sistema opera sobre el **tipo de cambio FIX** que Banxico publica cada día
hábil (serie `SF43718`). La publicación de un día hábil **aplica al día hábil
siguiente**: el tipo de cambio de hoy proviene de la publicación de ayer.

### Proceso diario

1. `exchange-rate:sync` consulta la SIE API de Banxico (ventana de varios días,
   así se recupera sola de un día caído).
2. Para cada publicación busca el **factor vigente cuyo rango la contiene**:
   `[rangeFrom, rangeTo)`, límite inferior inclusivo y superior exclusivo.
3. El tipo de cambio **es la publicación tal cual**: `calculatedRate = publicación`,
   redondeada a `config('exchange.scale')`. Sobre ella **no se aplica ninguna
   operación**; el factor sólo se guarda y se muestra al lado.
4. Se guarda en `exchangerates` con la fecha aplicable = día hábil siguiente al
   de la publicación.
5. Los **días feriados** del rango no tienen publicación aplicable: se les
   arrastra el tipo de cambio del día hábil anterior (`source = carried`,
   `carriedFromDate` = fecha de origen). Ver «Días hábiles y feriados».

Ejemplo: publicación 18.7690 con factor 1.0042 (clave 5, rango 18.5–19.5) queda
como tipo de cambio 18.7690 y factor 1.0042.

Del factor se guarda una **copia** en el registro (`factorCode`, `factorValue`):
si el factor cambia después, el histórico conserva el que aplicaba ese día.

Si ninguna clave cubre la publicación se marca `factorApplied: false` y queda el
aviso en el log para que el administrador cubra el rango; el tipo de cambio no
cambia por ello.

### Captura manual

`calculatedRate` es la publicación del proceso y **nunca se sobrescribe**. La
corrección del usuario se hace **sobre el tipo de cambio de Banxico**, vive en
`manualRate` con motivo obligatorio y prevalece como `effectiveRate` incluso si
la sincronización automática llega después. La ventana de
edición es sólo el día en curso y el día hábil siguiente
(`config('exchange.manual_edit')`).

Todo movimiento queda en la auditoría: el cambio de modelo más un evento
`exchangeRate.manualOverride` con valor anterior, nuevo, calculado y motivo. Es
lo que alimenta el historial por registro de la pantalla.

### Tablero

`GET /dashboard/exchange-rate` devuelve en una sola llamada todo lo que pinta la
pantalla de entrada: valor vigente de hoy con su variación contra el registro
anterior, día hábil siguiente con la marca de cuándo se generó el cálculo,
registros manuales del periodo, estado del proceso automático y la serie de
fluctuación. `GET /dashboard/exchange-rate/fluctuation` devuelve sólo la serie,
para recargar la gráfica con otra ventana sin volver a pedir las tarjetas.

El estado del proceso automático sale de la propia auditoría: `sync()` registra
`exchangeRate.sync` al terminar y el comando registra `exchangeRate.syncFailed`
si el servicio externo falla. No hay una tabla de estado aparte que mantener
sincronizada.

### Días hábiles y feriados

`BusinessDayService` descarta fines de semana y los días feriados capturados en
el módulo correspondiente (`HolidayService::dates()`), con los días fijos de
`config('exchange.holidays')` como respaldo. Ambos servicios son `scoped`: el
calendario se consulta una sola vez por petición, porque los listados evalúan
día hábil fila por fila.

**Arrastre del tipo de cambio en feriados**: en un día feriado nadie opera, pero
el portal se sigue consultando. `sync()` cierra la corrida llamando a
`carryOverHolidays()`, que deja en cada feriado —hasta el siguiente día hábil,
así queda listo antes de que llegue— el vigente del día hábil anterior, con
`source = carried` y `carriedFromDate` apuntando a su origen. Dos feriados
seguidos se encadenan al mismo día hábil.

Lo que **no** hace, a propósito: no pisa una captura manual del feriado, no
reescribe fechas pasadas (el valor con el que se operó ese día es un hecho), no
notifica por correo (es el mismo valor de ayer) y no inventa un registro cuando
no hay nada anterior que arrastrar. Un feriado futuro ya arrastrado sí se
reevalúa en cada corrida: si por la tarde llega la publicación del día previo,
el feriado se queda con ésa. Se desactiva con
`EXCHANGE_HOLIDAY_CARRY_OVER=false`.

El módulo administra el año en curso y los siguientes según
`config('holidays.years_ahead')`; capturar fuera de ese rango se rechaza. La
fecha es única entre los vigentes y la baja es lógica, así que una fecha
eliminada puede volver a capturarse.

**Recordatorio de captura**: a partir del día configurado en
`config('holidays.reminder.start')` (15 de noviembre por omisión) el comando
`holidays:remind` envía un correo diario a todos los usuarios activos mientras
el año siguiente no tenga ni un día capturado. Deja de enviarse solo en cuanto
se captura el primero. El comando decide por sí mismo si toca enviar —ventana,
pendiente y una vez al día, verificándolo contra la propia auditoría— así que la
programación puede invocarlo todos los días sin condiciones. Cada envío queda
como evento `holidayReminder.sent` en la línea de tiempo, con autor
«Proceso automático».

### Consulta pública

`GET /api/public/exchange-rate?days=30` es la **única ruta sin autenticación**
del sistema (aparte de `health`). La sirve `PublicExchangeRateService`, que es
donde vive la regla de qué se publica: fecha, valor vigente, factor informativo,
variación contra el día hábil anterior, máximo y mínimo del periodo, la serie y
el historial. Nada más.

**Lo que jamás sale**: `uuid`, `source`, `manualRate`, `manualReason`, autor,
`publishedRate`/`publishedDate`, `factorCode`, `carriedFromDate`, marcas de
notificación ni el estado del proceso. La vista pública no debe delatar que
existe un portal detrás, así que un campo nuevo aquí no se juzga por si sirve,
sino por lo que le cuenta a quien no debería saber nada de nosotros.
`PublicExchangeRateTest` bloquea la lista completa de campos prohibidos.

Va con `throttle:60,1` por ser la única puerta abierta a internet. Al no ser una
escritura, no entra a `requestlogs` (la auditoría sólo registra POST/PUT/PATCH/
DELETE). En fin de semana o feriado responde el último valor disponible: una
consulta pública no puede quedarse muda.

### Factores

Los rangos vigentes **no pueden traslaparse**; se valida en
`ExchangeRateFactorService` tanto al crear como al editar y al restaurar. La
baja es lógica (`deletedAt`), así el histórico conserva el factor que aplicaba.
La clave visible (`code`) es un consecutivo propio, independiente de la PK.

`POST /exchange-rates/factors/bulk` guarda la tabla completa de una vez: los
elementos con `uuid` se actualizan y el resto se dan de alta. Es todo o nada
(una transacción) y el traslape se valida sobre el **conjunto resultante**, no
elemento por elemento, para que un reacomodo de varios rangos a la vez no se
rechace por estados intermedios.

## Roles

No son dinámicos: `SUPERADMIN`, `ADMIN`, `USER`, como constantes en
`App\Models\Role`. El middleware `role:SUPERADMIN,ADMIN` protege las rutas
administrativas.

La organización tiene **una sola cuenta activa de SUPERADMIN, una de ADMIN y
tantos USER como haga falta** (`Role::SINGLE_ACCOUNT`). La regla se aplica tanto
en el alta como al cambiar el rol de una cuenta existente y al reactivar una
dada de baja (`UserService`). El alta rechaza una
segunda cuenta privilegiada mientras la anterior siga vigente: para reemplazarla
hay que dar de baja la existente. `auth/register` asume rol `USER` cuando no se
envía `roleName`.

| | SUPERADMIN | ADMIN | USER |
|---|---|---|---|
| Administra seguridad y configuración | Sí | Sí | No |
| Da de alta usuarios | Sí | Sí | No |
| Puede crear SUPERADMIN o ADMIN | Sí | No | No |
| Su cuenta se bloquea por intentos fallidos | No | No | Sí |
| Su IP se bloquea por intentos fallidos | Sí | Sí | Sí |

## Administración de cuentas

`UserService` concentra las reglas de la gestión de usuarios:

- Nadie cambia su propio rol ni se da de baja a sí mismo; el backend lo rechaza
  aunque la interfaz lo permitiera.
- Asignar `SUPERADMIN` o `ADMIN` exige ser SUPERADMIN y que no exista otra
  cuenta activa con ese rol. Reactivar una cuenta privilegiada revalida lo mismo.
- Sólo un SUPERADMIN puede dar de baja la cuenta de superadministrador.
- La baja es lógica (`isActive` + `deletedAt`) y **corta la sesión abierta** de
  esa cuenta borrando su `sessionToken`.
- `users/{uuid}/reset-password` genera una contraseña temporal nueva, marca
  `mustChangePassword`, invalida la sesión y la envía por correo. **La contraseña
  nunca viaja en la respuesta.** Si el correo no sale, responde 502: sin correo
  esa cuenta quedaría inaccesible.

## Autenticación

- El JWT viaja en cookie **httpOnly** (`App\Support\AuthCookie`); nunca en el
  cuerpo de la respuesta. El middleware acepta además `Authorization: Bearer`
  para clientes sin cookies.
- El TTL del token es el tiempo máximo de inactividad configurado en
  `loginattemptsettings.sessionTimeoutMinutes`.
- Sesión única: el token vigente se guarda en `usersecurity.sessionToken`; un
  login nuevo invalida el anterior.
- `auth/verify` revalida y renueva el token (sliding session).
- Con `users.mustChangePassword` activo, `JwtAuth` sólo deja pasar `auth/verify`,
  `auth/logout`, `auth/change-password` y `password-requirements`.

## Bloqueos

Los fallos se cuentan en ventana deslizante de 24 h
(`SECURITY_ATTEMPT_WINDOW_HOURS`); un fallo fuera de la ventana reinicia el
contador y un login correcto lo pone a cero.

- Al llegar a `maxUserAttempts` se bloquea el **usuario**. El bloqueo no caduca
  solo: lo libera SUPERADMIN o ADMIN.
- Al llegar a `maxIpAttempts` se bloquea la **IP**, para cualquier rol.
- Excepción operativa: una sesión ya activa de SUPERADMIN/ADMIN sigue
  funcionando desde una IP bloqueada, para poder liberarla desde el panel. El
  *login* desde esa IP se rechaza para todos.
- Salida de emergencia: `php artisan security:unlock-ip` y
  `security:unlock-user`.

Todo bloqueo y desbloqueo queda en `userblockedhistory` / `ipblockedhistory` con
motivo, fallos y quién liberó.

## Estructura

```
app/
  Actions/Auth/        Casos de uso de login y alta de usuarios
  Console/Commands/    Sincronización del tipo de cambio y desbloqueos de emergencia
  Http/
    Controllers/Api/   Controladores delgados: validan, delegan, responden
    Middleware/        AuditRequests (interceptor), JwtAuth (sesión), EnsureRole (rol)
    Requests/          FormRequest por operación
    Resources/         Única fuente de la forma de las respuestas
  Mail/                Mailables; Concerns\HasOverrideNotice para modo override
  Models/
  Services/            JwtService, AuthAttemptService, PasswordValidationService,
                       LoginAttemptSettingsService, EmailSenderService, UserService
  Services/Audit/      AuditContext, AuditRecorder, AuditSanitizer
  Services/Exchange/   BanxicoFixService, BusinessDayService, HolidayService,
                       ExchangeRateService, ExchangeRateFactorService
  Support/             ApiResponse, AuthCookie
routes/api/            auth.php, users.php, security.php, emailConfig.php,
                       exchangeRates.php, holidays.php, audit.php, dashboard.php,
                       publicExchangeRate.php (sin autenticación)
routes/console.php     Programación diaria (requiere cron con schedule:run)
docs/                  Colección y environment de Postman
```

Toda respuesta usa la envoltura de `App\Support\ApiResponse`:
`codeStatus`, `success`, `message`, `data`, `errors`, `timestamp`.

## Convenciones

- Nombres de tabla en minúscula sin separadores (`usersecurity`,
  `userblockedhistory`); columnas en `camelCase`; timestamps propios
  (`createdAt`, `updatedAt`, `deletedAt`), no los de Laravel.
- Mensajes de API en español.
- La configuración operativa vive en base de datos (umbrales, requisitos de
  contraseña, modo de correo). `config/security.php` sólo aporta los valores de
  respaldo mientras no exista registro.
- Todo el correo sale por `EmailSenderService`, que respeta el modo
  `normal | override | disabled` de `emailconfig`. No se llama a `Mail::` directo.
- El portal envía **siempre en español**: hay una sola carpeta de traducciones
  (`lang/es`) y los mailables no reciben locale.

## Comandos

```bash
php artisan migrate --seed     # esquema + roles + configuración inicial + superadmin
php artisan serve
php artisan exchange-rate:sync          # proceso diario del tipo de cambio
php artisan holidays:remind             # recordatorio de captura de feriados
php artisan exchange-rate:sync --date=YYYY-MM-DD --days=15
php artisan security:unlock-user <correo>
php artisan security:unlock-ip <ip>
```

La cuenta inicial de superadministrador se define en `.env`
(`SUPERADMIN_EMAIL` / `SUPERADMIN_PASSWORD`) y nace obligada a cambiar la
contraseña en el primer acceso.
