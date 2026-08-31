<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Paleta de la plantilla de correo
    |--------------------------------------------------------------------------
    | Los clientes de correo ignoran las hojas de estilo, así que cada color va
    | en línea en cada etiqueta. Se centralizan aquí para no perseguir el mismo
    | hexadecimal por cinco vistas cuando cambie la marca.
    |
    | El naranja es el institucional de Timken; los tonos derivados se usan para
    | fondos suaves, bordes y texto sobre blanco (el naranja puro no alcanza
    | contraste suficiente para leerse como texto).
    */
    'brand' => [
        'primary' => env('MAIL_BRAND_PRIMARY', '#FF8200'),
        'primary_dark' => env('MAIL_BRAND_PRIMARY_DARK', '#B35A00'),
        'primary_soft' => env('MAIL_BRAND_PRIMARY_SOFT', '#FFF4E8'),
        'primary_border' => env('MAIL_BRAND_PRIMARY_BORDER', '#FFD9B3'),
    ],
];
