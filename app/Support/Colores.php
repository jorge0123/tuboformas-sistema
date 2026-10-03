<?php

namespace App\Support;

/**
 * Colores de las gráficas. Los tipos de OT usan la paleta categórica validada (orden fijo, nunca
 * se recicla); el color sigue al tipo, no a su posición. Estados y ocupación usan colores de estado,
 * siempre con su texto al lado.
 */
class Colores
{
    public const TIPOS_OT = [
        'preventivo' => '#2a78d6',
        'correctivo' => '#eb6834',
        'predictivo' => '#1baf7a',
        'mejora' => '#eda100',
        'ampliacion' => '#e87ba4',
        'proyecto' => '#008300',
    ];

    public const ESTADOS_OT = [
        'pendiente' => '#a39c94',
        'en_progreso' => '#2a78d6',
        'en_espera' => '#eda100',
        'completada' => '#1baf7a',
        'cancelada' => '#e2ded9',
    ];

    /** [color, etiqueta, clase de insignia] según el % de ocupación. */
    public static function ocupacion(?int $pct): array
    {
        return match (true) {
            $pct === null => ['#e2ded9', 'Sin marcas', 'insignia-gris'],
            $pct >= 75 => ['#1baf7a', 'Buena', 'insignia-verde'],
            $pct >= 50 => ['#eda100', 'Media', 'insignia-ambar'],
            default => ['#e34948', 'Baja', 'insignia-roja'],
        };
    }
}
