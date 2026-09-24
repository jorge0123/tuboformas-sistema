<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/** Exportación a CSV que Excel abre directo (UTF-8 con BOM, separador coma). */
class Csv
{
    public static function descargar(string $nombre, array $encabezados, iterable $filas): StreamedResponse
    {
        abort_unless(auth()->user()?->can('reportes.exportar'), 403, 'No tienes permiso para exportar.');

        return response()->streamDownload(function () use ($encabezados, $filas) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $encabezados);
            foreach ($filas as $fila) {
                fputcsv($out, array_map(fn ($v) => is_bool($v) ? ($v ? 'Sí' : 'No') : $v, $fila));
            }
            fclose($out);
        }, $nombre.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
