<?php

namespace App\Support;

use Carbon\Carbon;
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
        $f = $f instanceof CarbonInterface ? $f : Carbon::parse($f);

        return $f->format($hora ? 'd/m/Y H:i' : 'd/m/Y');
    }

    /** 8.0833 h → "8 h 05 min"; 0.5 → "30 min". */
    public static function duracion($horas): string
    {
        $min = (int) round((float) $horas * 60);
        $h = intdiv($min, 60);

        return $h ? $h.' h '.str_pad((string) ($min % 60), 2, '0', STR_PAD_LEFT).' min' : ($min % 60).' min';
    }

    public static function bytes(int $b): string
    {
        return $b >= 1048576 ? round($b / 1048576, 1).' MB' : max(1, round($b / 1024)).' KB';
    }
}
