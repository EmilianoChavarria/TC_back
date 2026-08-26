<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Banxico (SIE API)
    |--------------------------------------------------------------------------
    | SF43718 es la serie del tipo de cambio FIX (pesos por dólar) que Banxico
    | publica cada día hábil y que aplica al día hábil siguiente.
    */
    'banxico' => [
        'token' => env('BANXICO_TOKEN'),
        'series' => env('BANXICO_FIX_SERIES', 'SF43718'),
        'base_url' => env('BANXICO_BASE_URL', 'https://www.banxico.org.mx/SieAPIRest/service/v1'),
        'timeout' => (int) env('BANXICO_TIMEOUT', 15),
        // Días hacia atrás que consulta la sincronización diaria. Con más de uno
        // se recupera sola de un día caído sin intervención manual.
        'lookback_days' => (int) env('BANXICO_LOOKBACK_DAYS', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Precisión
    |--------------------------------------------------------------------------
    | Decimales con los que se redondea el tipo de cambio.
    */
    'scale' => (int) env('EXCHANGE_RATE_SCALE', 4),

    /*
    |--------------------------------------------------------------------------
    | Decimales del factor
    |--------------------------------------------------------------------------
    | Los factores se capturan y se muestran con esta precisión, independiente
    | de la del tipo de cambio.
    */
    'factor_scale' => (int) env('EXCHANGE_FACTOR_SCALE', 3),

    /*
    |--------------------------------------------------------------------------
    | Días inhábiles fijos
    |--------------------------------------------------------------------------
    | Además de sábados y domingos. Formato YYYY-MM-DD, separados por coma.
    | Cuando exista el módulo de feriados, este arreglo se sustituye por su
    | consulta dentro de BusinessDayService.
    */
    'holidays' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('EXCHANGE_RATE_HOLIDAYS', ''))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Ventana de edición manual
    |--------------------------------------------------------------------------
    | Sólo se puede capturar o corregir el tipo de cambio del día en curso y el
    | del día hábil siguiente.
    */
    'manual_edit' => [
        'today' => true,
        'next_business_day' => true,
    ],
];
