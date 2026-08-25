<?php

namespace App\Support;

final class DisplayNumber
{
    public static function quantity(null|string|int $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        [$whole, $fraction] = self::parts((string) $value);

        return self::group($whole).($fraction === '' ? '' : '.'.rtrim($fraction, '0'));
    }

    public static function money(null|string|int $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        [$whole, $fraction] = self::parts((string) $value);

        return self::group($whole).'.'.str_pad(substr($fraction, 0, 3), 3, '0').' د.ل';
    }

    /** @return array{string, string} */
    private static function parts(string $value): array
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return [$whole === '' || $whole === '-' ? $whole.'0' : $whole, $fraction];
    }

    private static function group(string $whole): string
    {
        $negative = str_starts_with($whole, '-');
        $digits = ltrim($whole, '-');

        return ($negative ? '-' : '').preg_replace('/(?<!^)(?=(\d{3})+$)/', ',', $digits);
    }
}
