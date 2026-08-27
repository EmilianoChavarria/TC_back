<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\BulkStoreNotificationRecipientsRequest;
use App\Http\Requests\Notifications\StoreNotificationRecipientRequest;
use App\Http\Requests\Notifications\UpdateNotificationRecipientRequest;
use App\Http\Resources\NotificationRecipientResource;
use App\Models\NotificationRecipient;
use App\Services\Notifications\NotificationRecipientService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

/**
 * Buzones que reciben el aviso del tipo de cambio.
 *
 * No son usuarios del portal: son direcciones de terceros que necesitan el
 * dato del día sin tener cuenta. Toda la sección es de administración.
 */
class NotificationRecipientController extends Controller
{
    public function __construct(private readonly NotificationRecipientService $recipients)
    {
    }

    /** GET /api/notification-recipients?includeDeleted=&search=&page=&perPage= */
    public function index(Request $request)
    {
        return response()->json(ApiResponse::success(
            'Destinatarios de notificaciones',
            $this->page(
                $request->boolean('includeDeleted'),
                (int) $request->query('perPage', 10),
                $request->query('search')
            )
        ));
    }

    /** POST /api/notification-recipients */
    public function store(StoreNotificationRecipientRequest $request)
    {
        return response()->json(ApiResponse::success(
            'Destinatario agregado',
            NotificationRecipientResource::make($this->recipients->create($request->validated())),
            201
        ), 201);
    }

    /**
     * POST /api/notification-recipients/bulk
     *
     * Alta de una lista pegada de golpe. Devuelve el desglose porque el
     * resultado normal no es «todo bien»: una lista real trae direcciones que
     * ya estaban y alguna mal escrita, y el usuario necesita ver qué pasó con
     * cada grupo.
     */
    public function bulkStore(BulkStoreNotificationRecipientsRequest $request)
    {
        $result = $this->recipients->createMany($request->emails());

        return response()->json(ApiResponse::success(
            $this->bulkMessage($result),
            [
                'created' => NotificationRecipientResource::collection($result['created']),
                'duplicates' => $result['duplicates'],
                'invalid' => $result['invalid'],
                // La primera página ya con el alta dentro: así la pantalla se
                // refresca sin una segunda petición después de pegar la lista.
                // Se respeta el tamaño de página que el cliente tiene en pantalla.
                'recipients' => $this->page(false, (int) $request->input('perPage', 10)),
            ],
            201
        ), 201);
    }

    /** PUT /api/notification-recipients/{uuid} */
    public function update(UpdateNotificationRecipientRequest $request, string $uuid)
    {
        $recipient = $this->findOrFail($uuid);

        return response()->json(ApiResponse::success(
            'Destinatario actualizado',
            NotificationRecipientResource::make($this->recipients->update($recipient, $request->validated()))
        ));
    }

    /** DELETE /api/notification-recipients/{uuid} */
    public function destroy(string $uuid)
    {
        $recipient = $this->findOrFail($uuid);

        return response()->json(ApiResponse::success(
            'Destinatario eliminado',
            NotificationRecipientResource::make($this->recipients->delete($recipient))
        ));
    }

    /** POST /api/notification-recipients/{uuid}/restore */
    public function restore(string $uuid)
    {
        $recipient = $this->findOrFail($uuid);

        return response()->json(ApiResponse::success(
            'Destinatario restaurado',
            NotificationRecipientResource::make($this->recipients->restore($recipient))
        ));
    }

    /**
     * Página de destinatarios con el total de los que sí reciben el aviso.
     *
     * El contador va aparte del paginador porque es de toda la lista: sacarlo
     * de la página en pantalla daría una cifra distinta en cada página.
     *
     * @return array<string, mixed>
     */
    private function page(bool $includeDeleted, int $perPage, ?string $search = null): array
    {
        $paginator = $this->recipients->paginate($includeDeleted, $perPage, $search);

        $paginator->setCollection(
            NotificationRecipientResource::collection($paginator->getCollection())->collection
        );

        return $paginator->toArray() + ['notifiable' => $this->recipients->notifiableCount()];
    }

    /**
     * @param  array{created: array<int, mixed>, duplicates: array<int, string>, invalid: array<int, string>}  $result
     */
    private function bulkMessage(array $result): string
    {
        $created = count($result['created']);
        $duplicates = count($result['duplicates']);
        $invalid = count($result['invalid']);

        if ($created === 0 && $duplicates === 0 && $invalid === 0) {
            return 'No se agregó ningún correo.';
        }

        $parts = [];

        $parts[] = $created === 1 ? '1 correo agregado' : "{$created} correos agregados";

        if ($duplicates > 0) {
            $parts[] = $duplicates === 1 ? '1 ya estaba en la lista' : "{$duplicates} ya estaban en la lista";
        }

        if ($invalid > 0) {
            $parts[] = $invalid === 1 ? '1 no es un correo válido' : "{$invalid} no son correos válidos";
        }

        return implode(', ', $parts).'.';
    }

    /**
     * Se busca incluyendo los eliminados: `restore` necesita encontrarlos, y
     * un `update` sobre uno dado de baja debe responder 422 por su regla, no
     * un 404 que parece que el registro no existió nunca.
     */
    private function findOrFail(string $uuid): NotificationRecipient
    {
        return NotificationRecipient::query()->whereUuid($uuid)->firstOrFail();
    }
}
