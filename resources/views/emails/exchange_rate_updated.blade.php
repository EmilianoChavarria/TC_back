@extends('emails.layout')

@section('title', __('emails.exchange_rate_updated_title'))

@section('content')
    @php
        // Los clientes de correo ignoran las hojas de estilo, así que el estilo
        // de cada celda se arma aquí y no se repite fila por fila.
        $brand = config('emails.brand');

        $th = 'padding:10px 12px; background-color:#f9fafb; border-bottom:1px solid #e5e7eb; color:#6b7280; font-size:12px; text-transform:uppercase; letter-spacing:0.4px;';
        $td = fn (bool $last, bool $highlight) => 'padding:11px 12px;'
            .($last ? '' : ' border-bottom:1px solid #f3f4f6;')
            .($highlight ? ' background-color:'.$brand['primary_soft'].';' : '');

        // La variación se pinta con color y con símbolo: el color solo no se
        // ve en los clientes que bloquean estilos ni sirve a quien no lo distingue.
        $deltaStyle = [
            'up' => ['color' => '#b91c1c', 'symbol' => '▲'],
            'down' => ['color' => '#15803d', 'symbol' => '▼'],
            'flat' => ['color' => '#6b7280', 'symbol' => '='],
        ];
    @endphp

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

    <table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px; border:1px solid {{ $brand['primary_border'] }}; border-radius:8px; background-color:{{ $brand['primary_soft'] }};">
        {{-- Tipo de cambio y factor a la par y del mismo tamaño: son dos datos
             del día, no uno principal y una nota al pie. Van en celdas de una
             misma fila y no en columnas CSS, que Outlook no reparte. --}}
        <tr>
            <td style="padding:24px;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="{{ $factorValue ? '50%' : '100%' }}" align="center" style="text-align:center;">
                            <span style="color:#6b7280; font-size:13px;">{{ __('emails.exchange_rate_label') }}</span>
                            <br>
                            <span style="color:{{ $brand['primary_dark'] }}; font-size:34px; font-weight:700; letter-spacing:-0.5px; line-height:1.3;">{{ $effectiveRate }}</span>
                        </td>

                        @if ($factorValue)
                            <td width="50%" align="center" style="text-align:center; border-left:1px solid {{ $brand['primary_border'] }};">
                                <span style="color:#6b7280; font-size:13px;">{{ __('emails.exchange_rate_factor_label') }}</span>
                                <br>
                                <span style="color:{{ $brand['primary_dark'] }}; font-size:34px; font-weight:700; letter-spacing:-0.5px; line-height:1.3;">{{ $factorValue }}</span>
                            </td>
                        @endif
                    </tr>
                </table>
            </td>
        </tr>

        @if ($factorValue)
            {{-- El factor es informativo y no modifica el tipo de cambio. Sin
                 decirlo, quien recibe el correo puede intentar multiplicar uno
                 por otro, y ahora que están a la par se nota más. --}}
            <tr>
                <td style="padding:0 24px 20px; text-align:center;">
                    <span style="color:#9ca3af; font-size:12px; line-height:1.5;">{{ __('emails.exchange_rate_factor_notice') }}</span>
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

    {{-- Historial del último mes: el correo se usa como referencia de escritorio
         y así no hay que entrar al portal a reconstruir la serie. --}}
    <p style="margin:0 0 4px; color:#111827; font-size:15px; font-weight:600;">
        {{ __('emails.exchange_rate_history_title', ['days' => $summary['days']]) }}
    </p>

    @if ($summary['records'] > 0)
        <p style="margin:0 0 12px; color:#6b7280; font-size:13px; line-height:1.5;">
            {{ __('emails.exchange_rate_history_summary', [
                'records' => $summary['records'],
                'max' => $summary['max'],
                'min' => $summary['min'],
            ]) }}
        </p>
    @endif

    @if (empty($history))
        <p style="margin:0 0 28px; color:#9ca3af; font-size:14px;">
            {{ __('emails.exchange_rate_history_empty') }}
        </p>
    @else
        <table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 8px; border:1px solid #e5e7eb; border-radius:8px; border-collapse:separate; border-spacing:0;">
            <tr>
                <td style="{{ $th }}">{{ __('emails.exchange_rate_history_col_date') }}</td>
                <td align="right" style="{{ $th }}">{{ __('emails.exchange_rate_history_col_rate') }}</td>
                <td align="right" style="{{ $th }}">{{ __('emails.exchange_rate_history_col_change') }}</td>
                <td align="right" style="{{ $th }}">{{ __('emails.exchange_rate_history_col_factor') }}</td>
            </tr>

            @foreach ($history as $row)
                @php $cell = $td($loop->last, $row['highlight']); @endphp
                <tr>
                    <td style="{{ $cell }} color:#111827; font-size:14px; font-weight:{{ $row['highlight'] ? '600' : '400' }};">
                        {{ $row['date'] }}
                        <span style="color:#9ca3af; font-size:12px; font-weight:400;">{{ $row['weekday'] }}</span>
                        @if ($row['tag'])
                            <br><span style="color:{{ $brand['primary_dark'] }}; font-size:12px; font-weight:600;">{{ $row['tag'] }}</span>
                        @endif
                        @if ($row['note'])
                            <br><span style="color:#9ca3af; font-size:12px; font-weight:400;">{{ $row['note'] }}</span>
                        @endif
                    </td>
                    <td align="right" style="{{ $cell }} color:{{ $row['rate'] ? '#111827' : '#9ca3af' }}; font-size:15px; font-weight:{{ $row['highlight'] ? '700' : '600' }};">
                        {{ $row['rate'] ?? __('emails.exchange_rate_row_empty') }}
                    </td>
                    <td align="right" style="{{ $cell }} font-size:13px;">
                        @if ($row['deltaSign'])
                            @php $delta = $deltaStyle[$row['deltaSign']]; @endphp
                            <span style="color:{{ $delta['color'] }};">{{ $delta['symbol'] }} {{ $row['delta'] }}</span>
                        @else
                            <span style="color:#9ca3af;">&mdash;</span>
                        @endif
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

        <p style="margin:0 0 28px; color:#9ca3af; font-size:12px; line-height:1.5;">
            {{ __('emails.exchange_rate_history_notice') }}
        </p>
    @endif

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
                <a href="{{ $portalUrl }}" style="display:inline-block; background-color:{{ $brand['primary'] }}; color:#ffffff; text-decoration:none; font-size:15px; font-weight:600; padding:14px 40px; border-radius:6px;">
                    {{ __('emails.exchange_rate_updated_button') }}
                </a>
            </td>
        </tr>
    </table>
@endsection
