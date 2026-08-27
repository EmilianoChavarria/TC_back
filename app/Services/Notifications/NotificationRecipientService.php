<?php

namespace App\Services\Notifications;

use App\Models\NotificationRecipient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Alta, baja y consulta de los buzones que reciben el aviso de tipo de cambio.
 */
class NotificationRecipientService
{
    /** @return Collection<int, NotificationRecipient> */
    public function list(bool $includeDeleted = false): Collection
    {
        return $this->query($includeDeleted)->get();
    }

    /**
     * Misma consulta que `list`, por páginas.
     *
     * La lista crece sola —cada correo que alguien pide agregar se queda—, y
     * traerla completa en cada carga deja de ser razonable pasadas unas
     * decenas de direcciones.
     *
     * @return LengthAwarePaginator<int, NotificationRecipient>
     */
    public function paginate(bool $includeDeleted = false, int $perPage = 20, ?string $search = null): LengthAwarePaginator
    {
        return $this->query($includeDeleted)
            ->when($search, function (Builder $query, string $term) {
                $term = mb_strtolower(trim($term));

                $query->where(function (Builder $inner) use ($term) {
                    $inner->whereRaw('LOWER(email) LIKE ?', ["%{$term}%"])
                        ->orWhereRaw("LOWER(COALESCE(name, '')) LIKE ?", ["%{$term}%"]);
                });
            })
            ->paginate(min(100, max(1, $perPage)));
    }

    /**
     * Cuántos recibirán el próximo aviso.
     *
     * Va aparte del paginador a propósito: es un total de toda la lista, y
     * contarlo sobre la página en pantalla daría una cifra distinta según
     * dónde esté parado el usuario.
     */
    public function notifiableCount(): int
    {
        return NotificationRecipient::query()->notifiable()->count();
    }

    /** @return Builder<NotificationRecipient> */
    private function query(bool $includeDeleted): Builder
    {
        return NotificationRecipient::query()
            ->when(!$includeDeleted, fn ($query) => $query->active())
            ->orderByRaw('isActive DESC')
            ->orderBy('email');
    }

    /** @return Collection<int, NotificationRecipient> */
    public function notifiable(): Collection
    {
        return NotificationRecipient::query()->notifiable()->orderBy('email')->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): NotificationRecipient
    {
        $email = $this->normalize($data['email']);

        $this->assertEmailAvailable($email);

        return NotificationRecipient::create([
            'email' => $email,
            'name' => $data['name'] ?? null,
            'isActive' => $data['isActive'] ?? true,
            'createdAt' => Carbon::now(),
            'updatedAt' => Carbon::now(),
        ]);
    }

    /**
     * Alta de varios correos de una vez.
     *
     * ⚠️ NO es todo o nada. Pegar una lista real trae direcciones repetidas
     * —porque ya estaban— y alguna mal escrita; rechazar el lote entero
     * obligaría a depurarla a mano antes de pegarla, que es justo el trabajo
     * que esta pantalla evita.
     *
     * Se devuelve el desglose para poder decirle al usuario qué pasó con cada
     * grupo en vez de un «guardado» que esconde la mitad.
     *
     * @param  array<int, string>  $emails
     * @return array{created: array<int, NotificationRecipient>, duplicates: array<int, string>, invalid: array<int, string>}
     */
    public function createMany(array $emails): array
    {
        $created = [];
        $duplicates = [];
        $invalid = [];

        // Se normaliza antes de nada para que dos formas del mismo correo
        // dentro del MISMO lote no se cuenten como dos altas.
        $seen = [];

        foreach ($emails as $raw) {
            $email = $this->normalize((string) $raw);

            if ($email === '') {
                continue;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $invalid[] = $email;

                continue;
            }

            if (isset($seen[$email])) {
                continue;
            }

            $seen[$email] = true;

            if ($this->takenByActive($email)) {
                $duplicates[] = $email;

                continue;
            }

            $created[] = NotificationRecipient::create([
                'email' => $email,
                'isActive' => true,
                'createdAt' => Carbon::now(),
                'updatedAt' => Carbon::now(),
            ]);
        }

        return ['created' => $created, 'duplicates' => $duplicates, 'invalid' => $invalid];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(NotificationRecipient $recipient, array $data): NotificationRecipient
    {
        if (array_key_exists('email', $data)) {
            $email = $this->normalize($data['email']);
            $this->assertEmailAvailable($email, $recipient->id);
            $data['email'] = $email;
        }

        $recipient->fill($data + ['updatedAt' => Carbon::now()])->save();

        return $recipient->refresh();
    }

    /** Baja lógica: la dirección puede volver a darse de alta después. */
    public function delete(NotificationRecipient $recipient): NotificationRecipient
    {
        $recipient->fill(['deletedAt' => Carbon::now(), 'updatedAt' => Carbon::now()])->save();

        return $recipient->refresh();
    }

    public function restore(NotificationRecipient $recipient): NotificationRecipient
    {
        // Al reactivar hay que volver a comprobar el duplicado: la dirección
        // pudo darse de alta otra vez mientras esta estaba de baja.
        $this->assertEmailAvailable($recipient->email, $recipient->id);

        $recipient->fill(['deletedAt' => null, 'updatedAt' => Carbon::now()])->save();

        return $recipient->refresh();
    }

    /**
     * ⚠️ La unicidad se valida aquí y no con un índice en la tabla.
     *
     * La baja es lógica, así que un UNIQUE sobre `email` impediría volver a
     * dar de alta una dirección que se eliminó — mismo criterio que holidays.
     */
    private function assertEmailAvailable(string $email, ?int $ignoreId = null): void
    {
        if ($this->takenByActive($email, $ignoreId)) {
            throw ValidationException::withMessages([
                'email' => 'Ese correo ya está en la lista de notificaciones.',
            ]);
        }
    }

    /** ¿Ya hay un registro vigente con esa dirección? */
    private function takenByActive(string $email, ?int $ignoreId = null): bool
    {
        return NotificationRecipient::query()
            ->active()
            ->where('email', $email)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }

    /**
     * Minúsculas y sin espacios.
     *
     * Sin normalizar, «Pagos@Empresa.mx» y «pagos@empresa.mx» pasan como dos
     * destinatarios distintos y esa persona recibe el aviso por duplicado.
     */
    private function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
