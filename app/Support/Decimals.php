<?php

namespace App\Support;

/**
 * Formato numérico del módulo de tipo de cambio.
 *
 * El tipo de cambio y los límites de los rangos se muestran con la precisión de
 * `exchange.scale`; el factor con la de `exchange.factor_scale`. Se devuelven
 * como cadena para que el cliente no pierda los ceros a la derecha.
 */
class Decimals
{
    public static function rate(mixed $value): ?string
    {
        return self::format($value, (int) config('exchange.scale', 4));
    }

    public static function factor(mixed $value): ?string
    {
        return self::format($value, (int) config('exchange.factor_scale', 3));
    }

    /** Redondea al alcance indicado; útil antes de guardar. */
    public static function roundRate(mixed $value): string
    {
        return self::format($value, (int) config('exchange.scale', 4)) ?? '0';
    }

    public static function roundFactor(mixed $value): string
    {
        return self::format($value, (int) config('exchange.factor_scale', 3)) ?? '0';
    }

    private static function format(mixed $value, int $scale): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, $scale, '.', '');
    }
}
