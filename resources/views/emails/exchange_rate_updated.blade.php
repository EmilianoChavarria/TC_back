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

    {{-- Comparativo de tres fechas: da referencia de hacia dónde se movió el
         valor sin tener que entrar al portal. --}}
    @php
        // Los clientes de correo ignoran las hojas de estilo, así que el estilo
        // de cada celda se arma aquí y no se repite fila por fila.
        $th = 'padding:10px 14px; background-color:#f9fafb; border-bottom:1px solid #e5e7eb; color:#6b7280; font-size:12px; text-transform:uppercase; letter-spacing:0.4px;';
        $td = fn (bool $last, bool $highlight) => 'padding:12px 14px;'
            .($last ? '' : ' border-bottom:1px solid #f3f4f6;')
            .($highlight ? ' background-color:#eff6ff;' : '');
    @endphp

    <p style="margin:0 0 12px; color:#111827; font-size:15px; font-weight:600;">
        {{ __('emails.exchange_rate_comparison_title') }}
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 28px; border:1px solid #e5e7eb; border-radius:8px; border-collapse:separate; border-spacing:0;">
        <tr>
            <td style="{{ $th }}">{{ __('emails.exchange_rate_comparison_col_period') }}</td>
            <td style="{{ $th }}">{{ __('emails.exchange_rate_comparison_col_date') }}</td>
            <td align="right" style="{{ $th }}">{{ __('emails.exchange_rate_comparison_col_rate') }}</td>
            <td align="right" style="{{ $th }}">{{ __('emails.exchange_rate_comparison_col_factor') }}</td>
        </tr>

        @foreach ($comparison as $row)
            @php $cell = $td($loop->last, $row['highlight']); @endphp
            <tr>
                <td style="{{ $cell }} color:#111827; font-size:14px; font-weight:{{ $row['highlight'] ? '600' : '400' }};">
                    {{ $row['label'] }}
                    @if ($row['note'])
                        <br><span style="color:#9ca3af; font-size:12px; font-weight:400;">{{ $row['note'] }}</span>
                    @endif
                </td>
                <td style="{{ $cell }} color:#6b7280; font-size:14px;">{{ $row['date'] }}</td>
                <td align="right" style="{{ $cell }} color:{{ $row['rate'] ? '#111827' : '#9ca3af' }}; font-size:15px; font-weight:{{ $row['highlight'] ? '700' : '600' }};">
                    {{ $row['rate'] ?? __('emails.exchange_rate_row_empty') }}
                </td>
                <td align="right" style="{{ $cell }} color:#6b7280; font-size:14px;">
                    @if ($row['factorValue'])
                        {{ $row['factorValue'] }}
                        @if ($row['factorCode'] !== null)
                            <br><span style="color:#9ca3af; font-size:12px;">{{ __('emails.exchange_rate_factor_code', ['code' => $row['factorCode']]) }}</span>
                        @endif
                    @else
                        <span style="color:#9ca3af;">&mdash;</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </table>

    {{-- Catálogo completo de factores: el correo se usa como referencia de
         escritorio y así no hay que entrar al portal sólo a consultar un rango. --}}
    <p style="margin:0 0 12px; color:#111827; font-size:15px; font-weight:600;">
        {{ __('emails.exchange_rate_factors_title') }}
    </p>

    @if (empty($factors))
        <p style="margin:0 0 28px; color:#9ca3af; font-size:14px;">
            {{ __('emails.exchange_rate_factors_empty') }}
        </p>
    @else
        <table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 8px; border:1px solid #e5e7eb; border-radius:8px; border-collapse:separate; border-spacing:0;">
            <tr>
                <td style="{{ $th }}">{{ __('emails.exchange_rate_factors_col_code') }}</td>
                <td style="{{ $th }}">{{ __('emails.exchange_rate_factors_col_range') }}</td>
                <td align="right" style="{{ $th }}">{{ __('emails.exchange_rate_factors_col_factor') }}</td>
            </tr>

            @foreach ($factors as $factor)
                @php $cell = $td($loop->last, $factor['highlight']); @endphp
                <tr>
                    <td style="{{ $cell }} color:#6b7280; font-size:14px;">{{ $factor['code'] }}</td>
                    <td style="{{ $cell }} color:#111827; font-size:14px; font-weight:{{ $factor['highlight'] ? '600' : '400' }};">
                        {{ __('emails.exchange_rate_factors_range', ['from' => $factor['rangeFrom'], 'to' => $factor['rangeTo']]) }}
                    </td>
                    <td align="right" style="{{ $cell }} color:#111827; font-size:15px; font-weight:600;">{{ $factor['factor'] }}</td>
                </tr>
            @endforeach
        </table>

        <p style="margin:0 0 28px; color:#9ca3af; font-size:12px; line-height:1.5;">
            {{ __('emails.exchange_rate_factors_notice') }}
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
