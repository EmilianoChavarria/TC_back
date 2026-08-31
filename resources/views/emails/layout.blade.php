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

<body
    style="margin:0; padding:0; background-color:#f4f4f7; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f7; padding:40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0"
                    style="background-color:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.05);">
                    <!-- Header -->
                    <tr>
                        <td style="padding: 40px 30px; text-align: center;">
                            <img src="{{ $message->embed(public_path('images/LTM.png')) }}" alt="logo_timken"
                                style="max-width: 220px; height: auto; display: block; margin: 0 auto;">
                        </td>
                    </tr>

                    <tr>
                        <td align="center"
                            style="background-color: #ff8200; border-top: 2px solid #ff8200; padding: 0 30px; text-align: center;">
                            <p
                                style="margin:0; padding:0; color:#FFFFFF; font-size:20px; font-weight:700; line-height:1.6; text-align:center;">
                                Portal de Tipo de Cambio
                            </p>
                        </td>
                    </tr>

                    <!-- <tr>
                        <p style="margin:0 0 4px; color:#111827; font-size:20px; font-weight:700; padding:11px 12px;">
                            Portal de Tipo de Cambio
                        </p>
                    </tr> -->

                    <tr>
                        <td style="padding:40px 30px;">
                            @yield('content')
                        </td>
                    </tr>

                    <tr>
                        <td
                            style="background-color: #ff8200; padding: 30px; text-align: center; border-top: 1px solid #e2e8f0;">
                            @if (!empty($supportEmail))
                                <p style="margin:0; color: #FFFFFF; font-size:13px; line-height:1.5;">
                                    {{ __('emails.footer_support') }}
                                    <a href="mailto:{{ $supportEmail }}" style="color: #1255ca;">{{ $supportEmail }}</a>
                                </p>
                            @endif

                            @if (!empty($isOverride) && !empty($originalRecipient))
                                <p
                                    style="margin:16px 0 0; color: #FFFFFF; font-size:13px; line-height:1.5; border-top:1px solid #e5e7eb; padding-top:12px;">
                                    {{ __('emails.override_notice', ['recipient' => $originalRecipient]) }}
                                </p>
                            @endif

                            <p style="margin:16px 0 0; color: #FFFFFF; font-size:13px;">
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
