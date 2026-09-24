<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Bitacora;
use App\Models\Maquina;
use App\Models\OrdenTrabajo;
use App\Models\Proveedor;
use App\Models\User;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BitacoraController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('bitacora.ver');
        $registros = $this->filtrar($request)->with(['maquina', 'responsable', 'proveedor', 'orden'])
            ->orderByDesc('fecha')->orderByDesc('id')->paginate(30)->withQueryString();
        $maquinas = Maquina::orderBy('nombre')->get(['id', 'codigo', 'nombre']);

        return view('bitacora.index', compact('registros', 'maquinas'));
    }

    public function exportar(Request $request)
    {
        $this->authorize('bitacora.ver');
        $verCostos = $request->user()->can('inventario.ver_costos');
        $filas = $this->filtrar($request)->with(['maquina', 'responsable', 'proveedor', 'orden'])->orderBy('fecha')->get()
            ->map(fn ($b) => [
                $b->fecha->format('d/m/Y'), $b->maquina->codigo, $b->maquina->nombre, OrdenTrabajo::TIPOS[$b->tipo] ?? $b->tipo,
                $b->componente, $b->trabajo_realizado, $b->horas, $b->nombreResponsable(), $b->proveedor?->nombre,
                $b->garantia, $verCostos ? $b->costo : '', $b->horometro, $b->orden?->folio, $b->comentarios,
            ]);

        return Csv::descargar('bitacora-mantenimiento', ['Fecha', 'Código', 'Máquina', 'Tipo', 'Componente', 'Mantenimiento ejecutado',
            'Horas', 'Responsable', 'Proveedor', 'Garantía', 'Costo', 'Horómetro', 'OT', 'Comentarios'], $filas);
    }

    public function create(Request $request)
    {
        $this->authorize('bitacora.crear');
        $b = new Bitacora(['maquina_id' => $request->integer('maquina') ?: null, 'fecha' => today(), 'tipo' => 'correctivo', 'responsable_id' => $request->user()->id]);

        return view('bitacora.form', $this->datos($b));
    }

    public function store(Request $request)
    {
        $this->authorize('bitacora.crear');
        $b = Bitacora::create($this->validar($request) + ['user_id' => $request->user()->id]);
        Auditoria::registrar('crear', $b, $b->trabajo_realizado);

        return redirect()->route('maquinas.show', ['maquina' => $b->maquina_id, 'tab' => 'bitacora'])->with('ok', 'Registrado en la bitácora.');
    }

    public function edit(Bitacora $bitacora)
    {
        $this->authorize('bitacora.editar');

        return view('bitacora.form', $this->datos($bitacora));
    }

    public function update(Request $request, Bitacora $bitacora)
    {
        $this->authorize('bitacora.editar');
        $bitacora->update($this->validar($request));
        Auditoria::registrar('editar', $bitacora, $bitacora->trabajo_realizado);

        return redirect()->route('maquinas.show', ['maquina' => $bitacora->maquina_id, 'tab' => 'bitacora'])->with('ok', 'Registro actualizado.');
    }

    public function destroy(Bitacora $bitacora)
    {
        $this->authorize('bitacora.eliminar');
        Auditoria::registrar('eliminar', $bitacora, $bitacora->trabajo_realizado, $bitacora->toArray());
        $maquina = $bitacora->maquina_id;
        $bitacora->delete();

        return redirect()->route('maquinas.show', ['maquina' => $maquina, 'tab' => 'bitacora'])->with('ok', 'Registro eliminado.');
    }

    private function filtrar(Request $request)
    {
        return Bitacora::query()
            ->when($request->filled('maquina'), fn ($q) => $q->where('maquina_id', $request->maquina))
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->tipo))
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha', '>=', $request->desde))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha', '<=', $request->hasta))
            ->when($request->boolean('garantia'), fn ($q) => $q->where('garantia', true))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('trabajo_realizado', 'like', '%'.$request->q.'%')
                ->orWhere('componente', 'like', '%'.$request->q.'%')->orWhere('comentarios', 'like', '%'.$request->q.'%')));
    }

    private function datos(Bitacora $b): array
    {
        return [
            'b' => $b,
            'maquinas' => Maquina::orderBy('nombre')->get(['id', 'codigo', 'nombre']),
            'usuarios' => User::activos()->orderBy('name')->get(['id', 'name']),
            'proveedores' => Proveedor::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
        ];
    }

    private function validar(Request $request): array
    {
        $d = $request->validate([
            'maquina_id' => ['required', 'exists:maquinas,id'],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'tipo' => ['required', Rule::in(array_keys(OrdenTrabajo::TIPOS))],
            'componente' => ['nullable', 'string', 'max:100'],
            'trabajo_realizado' => ['required', 'string', 'max:5000'],
            'horas' => ['nullable', 'numeric', 'min:0'],
            'responsable_id' => ['nullable', 'exists:users,id'],
            'responsable_nombre' => ['nullable', 'string', 'max:100'],
            'proveedor_id' => ['nullable', 'exists:proveedores,id'],
            'garantia' => ['nullable', 'boolean'],
            'costo' => ['nullable', 'numeric', 'min:0'],
            'horometro' => ['nullable', 'numeric', 'min:0'],
            'comentarios' => ['nullable', 'string', 'max:3000'],
        ], [], ['maquina_id' => 'máquina', 'trabajo_realizado' => 'mantenimiento ejecutado']);
        $d['garantia'] = $request->boolean('garantia');
        if (! empty($d['responsable_id'])) {
            $d['responsable_nombre'] = null;
        }

        return $d;
    }
}
