<!doctype html>
<html lang="{{ app()->getLocale() }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
</head>

@php
    // Paleta de marca. Va aquí y no en cada vista porque los clientes de correo
    // sólo respetan el estilo en línea y el hexadecimal se repetiría por todos lados.
    $brand = config('emails.brand');
@endphp

<body style="margin:0; padding:0; background-color:#f4f4f7; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f7; padding:40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.05);">
                    <tr>
                        <td style="padding:28px 30px; text-align:center; background-color:{{ $brand['primary'] }};">
                            <span style="color:#ffffff; font-size:20px; font-weight:600; letter-spacing:0.5px;">
                                {{ config('app.name') }}
                            </span>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:40px 30px;">
                            @yield('content')
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color:#f9fafb; padding:24px 30px; text-align:center; border-top:1px solid #e5e7eb;">
                            @if (!empty($supportEmail))
                                <p style="margin:0; color:#6b7280; font-size:13px; line-height:1.5;">
                                    {{ __('emails.footer_support') }}
                                    <a href="mailto:{{ $supportEmail }}" style="color:{{ $brand['primary_dark'] }};">{{ $supportEmail }}</a>
                                </p>
                            @endif

                            @if (!empty($isOverride) && !empty($originalRecipient))
                                <p style="margin:16px 0 0; color:#b45309; font-size:12px; line-height:1.5; border-top:1px solid #e5e7eb; padding-top:12px;">
                                    {{ __('emails.override_notice', ['recipient' => $originalRecipient]) }}
                                </p>
                            @endif

                            <p style="margin:16px 0 0; color:#9ca3af; font-size:12px;">
                                &copy; {{ now()->year }} ITTEC. Tecnología Inteligente. Todos los derechos reservados.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
