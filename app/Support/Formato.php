<?php

namespace App\Support;

use Carbon\CarbonInterface;

class Formato
{
    /** 1234.500 → "1,234.5"; sin ceros de sobra. */
    public static function numero($n, int $decimales = 3): string
    {
        if ($n === null || $n === '') {
            return '—';
        }
        $s = number_format((float) $n, $decimales, '.', ',');

        return str_contains($s, '.') ? rtrim(rtrim($s, '0'), '.') : $s;
    }

    public static function dinero($n): string
    {
        return $n === null || $n === '' ? '—' : 'Q '.number_format((float) $n, 2, '.', ',');
    }

    public static function fecha($f, bool $hora = false): string
    {
        if (! $f) {
            return '—';
        }
        $f = $f instanceof CarbonInterface ? $f : \Carbon\Carbon::parse($f);

        return $f->format($hora ? 'd/m/Y H:i' : 'd/m/Y');
    }

    public static function bytes(int $b): string
    {
        return $b >= 1048576 ? round($b / 1048576, 1).' MB' : max(1, round($b / 1024)).' KB';
    }
}
