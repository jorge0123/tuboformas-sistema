<?php

namespace App\Http\Controllers;

use App\Models\Archivo;
use App\Models\Auditoria;
use App\Models\Bitacora;
use App\Models\Maquina;
use App\Models\Movimiento;
use App\Models\OrdenTrabajo;
use App\Models\Pedido;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ArchivoController extends Controller
{
    /** tipo => [modelo, permiso para subir/borrar] */
    private const TIPOS = [
        'maquina' => [Maquina::class, 'maquinas.editar'],
        'ot' => [OrdenTrabajo::class, null], // se valida contra la OT (quien la ejecuta o puede editarla)
        'bitacora' => [Bitacora::class, 'bitacora.crear'],
        'producto' => [Producto::class, 'inventario.gestionar'],
        'movimiento' => [Movimiento::class, 'movimientos.crear'],
        'pedido' => [Pedido::class, 'pedidos.preparar'],
    ];

    public function store(Request $request)
    {
        $datos = $request->validate([
            'tipo' => ['required', Rule::in(array_keys(self::TIPOS))],
            'id' => ['required', 'integer'],
            'categoria' => ['required', Rule::in(array_keys(Archivo::CATEGORIAS))],
            'archivo' => ['required', 'file', 'max:20480', 'mimes:jpg,jpeg,png,webp,gif,heic,pdf,doc,docx,xls,xlsx,dwg'],
        ], [], ['archivo' => 'archivo']);

        $modelo = $this->modelo($datos['tipo'], $datos['id']);
        $this->autorizar($request, $datos['tipo'], $modelo);

        $file = $request->file('archivo');
        $ruta = $file->storeAs(
            $datos['tipo'].'/'.now()->format('Y/m'),
            Str::uuid().'.'.strtolower($file->getClientOriginalExtension() ?: $file->extension()),
            'public'
        );
        $archivo = $modelo->archivos()->create([
            'categoria' => $datos['categoria'],
            'nombre' => Str::limit($file->getClientOriginalName(), 250, ''),
            'ruta' => $ruta,
            'mime' => $file->getMimeType(),
            'tamano' => $file->getSize(),
            'user_id' => $request->user()->id,
        ]);
        Auditoria::registrar('adjuntar', $modelo, $archivo->nombre);

        return response()->json(['ok' => true, 'id' => $archivo->id, 'url' => $archivo->url()]);
    }

    public function destroy(Request $request, Archivo $archivo)
    {
        $tipo = array_search($archivo->adjuntable_type, array_map(fn ($t) => $t[0], self::TIPOS), true);
        $this->autorizar($request, $tipo, $archivo->adjuntable);
        Storage::disk('public')->delete($archivo->ruta);
        Auditoria::registrar('eliminar_adjunto', $archivo->adjuntable, $archivo->nombre);
        $archivo->delete();

        return back()->with('ok', 'Archivo eliminado.');
    }

    private function modelo(string $tipo, int $id)
    {
        return self::TIPOS[$tipo][0]::findOrFail($id);
    }

    private function autorizar(Request $request, string|false $tipo, $modelo): void
    {
        abort_if(! $tipo || ! $modelo, 404);
        $u = $request->user();
        if ($tipo === 'pedido' && $modelo->piloto_id === $u->id) {
            return; // el piloto sube la foto de la entrega
        }
        if ($tipo === 'ot') {
            abort_unless($u->can('ot.editar') || ($u->can('ot.ejecutar') && $modelo->laEjecuta($u)) || $modelo->solicitante_id === $u->id, 403);

            return;
        }
        abort_unless($u->can(self::TIPOS[$tipo][1]), 403);
    }
}
