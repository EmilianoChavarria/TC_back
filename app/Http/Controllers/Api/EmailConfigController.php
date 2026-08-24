<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Security\UpdateEmailConfigRequest;
use App\Http\Resources\EmailConfigResource;
use App\Mail\TestEmail;
use App\Models\EmailConfig;
use App\Services\EmailSenderService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

/**
 * Configuración de correo: destinatario de soporte y modo de envío
 * (normal / override / disabled).
 */
class EmailConfigController extends Controller
{
    public function __construct(private readonly EmailSenderService $emailSender)
    {
    }

    /** GET /api/email-config */
    public function show()
    {
        $config = EmailConfig::query()->orderBy('id')->first()
            ?? new EmailConfig(['emailMode' => EmailConfig::MODE_NORMAL]);

        return response()->json(ApiResponse::success('Configuración de correo obtenida', EmailConfigResource::make($config)));
    }

    /** PUT /api/email-config */
    public function update(UpdateEmailConfigRequest $request)
    {
        $data = $request->validated();
        $now = Carbon::now();

        $config = EmailConfig::query()->orderBy('id')->first();

        if ($config) {
            $config->fill($data + ['updatedAt' => $now])->save();
        } else {
            $config = EmailConfig::create($data + ['createdAt' => $now, 'updatedAt' => $now]);
        }

        return response()->json(ApiResponse::success('Configuración de correo actualizada', EmailConfigResource::make($config)));
    }

    /** POST /api/email-config/test — envía un correo de prueba respetando el modo configurado */
    public function sendTest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Datos inválidos', $validator->errors(), 422), 422);
        }

        $recipient = (string) $request->input('email');
        $sent = $this->emailSender->send(new TestEmail(), $recipient);

        if (!$sent) {
            return response()->json(ApiResponse::error(
                'El correo no se envió. Revise el modo configurado y los logs.',
                ['emailMode' => $this->emailSender->mode()],
                422
            ), 422);
        }

        return response()->json(ApiResponse::success('Correo de prueba enviado', [
            'emailMode' => $this->emailSender->mode(),
            'requestedRecipient' => $recipient,
        ]));
    }
}
