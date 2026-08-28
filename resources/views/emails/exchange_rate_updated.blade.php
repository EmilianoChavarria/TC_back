@extends('emails.layout')

@section('title', __('emails.exchange_rate_updated_title'))

@section('content')
    @if ($isCorrection)
        {{-- Va arriba del todo: quien ya apuntó el valor anterior tiene que
             ver la corrección antes que la cifra. --}}
        <p style="margin:0 0 20px; padding:12px 16px; background-color:#fef3c7; border-radius:6px; color:#92400e; font-size:14px; line-height:1.6;">
            {{ __('emails.exchange_rate_correction_notice') }}
        </p>
    @endif

    <p style="margin:0 0 8px; color:#6b7280; font-size:14px; line-height:1.6;">
        {{ __('emails.exchange_rate_updated_heading') }}
    </p>

    <p style="margin:0 0 24px; color:#111827; font-size:16px; line-height:1.6;">
        {{ $applicableDate }}
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px; border:1px solid #e5e7eb; border-radius:8px;">
        <tr>
            <td style="padding:24px 24px 8px; text-align:center;">
                <span style="color:#6b7280; font-size:13px;">{{ __('emails.exchange_rate_label') }}</span>
            </td>
        </tr>
        <tr>
            <td style="padding:0 24px 24px; text-align:center;">
                <span style="color:#111827; font-size:34px; font-weight:700; letter-spacing:-0.5px;">{{ $effectiveRate }}</span>
            </td>
        </tr>

        @if ($factorValue)
            <tr>
                <td style="padding:16px 24px; text-align:center; border-top:1px solid #e5e7eb;">
                    <span style="color:#6b7280; font-size:13px;">{{ __('emails.exchange_rate_factor_label') }}</span>
                    <span style="color:#111827; font-size:15px; font-weight:600;"> {{ $factorValue }}</span>
                    @if ($factorCode !== null)
                        <span style="color:#9ca3af; font-size:13px;"> · {{ __('emails.exchange_rate_factor_code', ['code' => $factorCode]) }}</span>
                    @endif
                    {{-- El factor es informativo y no modifica el tipo de cambio.
                         Sin decirlo, quien recibe el correo puede intentar
                         multiplicar uno por otro. --}}
                    <br>
                    <span style="color:#9ca3af; font-size:12px;">{{ __('emails.exchange_rate_factor_notice') }}</span>
                </td>
            </tr>
        @endif
    </table>

    @if ($isManual)
        <p style="margin:0 0 24px; color:#b45309; font-size:14px; line-height:1.6;">
            {{ __('emails.exchange_rate_manual_notice') }}
            @if ($manualReason)
                <br><span style="color:#6b7280;">{{ $manualReason }}</span>
            @endif
        </p>
    @endif

    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center">
                <a href="{{ $portalUrl }}" style="display:inline-block; background-color:#2563eb; color:#ffffff; text-decoration:none; font-size:15px; font-weight:600; padding:14px 40px; border-radius:6px;">
                    {{ __('emails.exchange_rate_updated_button') }}
                </a>
            </td>
        </tr>
    </table>
@endsection
