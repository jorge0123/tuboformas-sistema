<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Auditoria;
use App\Models\Maquina;
use App\Models\OrdenTrabajo;
use App\Models\Producto;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MaquinaController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('maquinas.ver');

        $maquinas = $this->filtrar($request)
            ->with('area')
            ->withCount(['ordenes as ot_abiertas' => fn ($q) => $q->whereIn('estado', OrdenTrabajo::ABIERTOS)])
            ->orderByRaw('numero is null, numero')->orderBy('nombre')
            ->paginate(25)->withQueryString();

        $areas = Area::where('activo', true)->orderBy('nombre')->get();

        return view('maquinas.index', compact('maquinas', 'areas'));
    }

    public function exportar(Request $request)
    {
        $this->authorize('maquinas.ver');
        $filas = $this->filtrar($request)->with('area')->orderBy('numero')->get()->map(fn ($m) => [
            $m->numero, $m->codigo, $m->nombre, $m->area?->nombre, $m->marca, $m->modelo, $m->serie,
            Maquina::ESTADOS[$m->estado], $m->criticidad, $m->observaciones,
        ]);

        return Csv::descargar('maquinas', ['No.', 'Código', 'Descripción', 'Área', 'Marca', 'Modelo', 'Serie', 'Estado', 'Criticidad', 'Observación'], $filas);
    }

    public function show(Maquina $maquina)
    {
        $this->authorize('maquinas.ver');
        $maquina->load(['area', 'componentes', 'partes.producto', 'archivos.user', 'planes.responsable']);

        $ordenes = $maquina->ordenes()->with('responsable')->latest()->limit(50)->get();
        $bitacora = $maquina->bitacoras()->with(['responsable', 'proveedor', 'orden'])->limit(100)->get();
        $stats = [
            'ot_abiertas' => $ordenes->whereIn('estado', OrdenTrabajo::ABIERTOS)->count(),
            'correctivos_anio' => $maquina->bitacoras()->where('tipo', 'correctivo')->whereYear('fecha', now()->year)->count(),
            'horas_anio' => (float) $maquina->bitacoras()->whereYear('fecha', now()->year)->sum('horas'),
            'ultimo' => $bitacora->first()?->fecha,
        ];

        return view('maquinas.show', compact('maquina', 'ordenes', 'bitacora', 'stats'));
    }

    public function create()
    {
        $this->authorize('maquinas.crear');

        return view('maquinas.form', $this->datosFormulario(new Maquina(['estado' => 'operativa', 'criticidad' => 'B'])));
    }

    public function store(Request $request)
    {
        $this->authorize('maquinas.crear');
        $maquina = DB::transaction(function () use ($request) {
            $maquina = Maquina::create($this->validar($request));
            $this->guardarFicha($maquina, $request);

            return $maquina;
        });
        Auditoria::registrar('crear', $maquina, $maquina->etiqueta());

        return redirect()->route('maquinas.show', $maquina)->with('ok', 'Máquina registrada.');
    }

    public function edit(Maquina $maquina)
    {
        $this->authorize('maquinas.editar');
        $maquina->load(['componentes', 'partes']);

        return view('maquinas.form', $this->datosFormulario($maquina));
    }

    public function update(Request $request, Maquina $maquina)
    {
        $this->authorize('maquinas.editar');
        DB::transaction(function () use ($request, $maquina) {
            $maquina->update($this->validar($request, $maquina));
            $this->guardarFicha($maquina, $request);
        });
        Auditoria::registrar('editar', $maquina, $maquina->etiqueta());

        return redirect()->route('maquinas.show', $maquina)->with('ok', 'Cambios guardados.');
    }

    public function destroy(Maquina $maquina)
    {
        $this->authorize('maquinas.eliminar');
        if ($maquina->bitacoras()->exists() || $maquina->ordenes()->exists()) {
            return back()->with('error', 'La máquina tiene historial. Márcala como "De baja" en lugar de eliminarla.');
        }
        Auditoria::registrar('eliminar', $maquina, $maquina->etiqueta());
        if ($maquina->foto) {
            Storage::disk('public')->delete($maquina->foto);
        }
        $maquina->delete();

        return redirect()->route('maquinas.index')->with('ok', 'Máquina eliminada.');
    }

    private function filtrar(Request $request)
    {
        return Maquina::query()
            ->buscar($request->input('q'))
            ->when($request->filled('area'), fn ($q) => $q->where('area_id', $request->area))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado),
                fn ($q) => $q->when(! $request->boolean('con_baja'), fn ($w) => $w->where('estado', '!=', 'baja')))
            ->when($request->filled('criticidad'), fn ($q) => $q->where('criticidad', $request->criticidad));
    }

    private function datosFormulario(Maquina $maquina): array
    {
        return [
            'maquina' => $maquina,
            'areas' => Area::where('activo', true)->orderBy('nombre')->get(),
            'repuestos' => Producto::whereIn('tipo', ['repuesto', 'insumo'])->where('activo', true)->orderBy('nombre')->get(['id', 'codigo', 'nombre']),
        ];
    }

    private function validar(Request $request, ?Maquina $maquina = null): array
    {
        $datos = $request->validate([
            'numero' => ['nullable', 'integer', 'min:0'],
            'codigo' => ['nullable', 'string', 'max:30', Rule::unique('maquinas')->ignore($maquina)],
            'nombre' => ['required', 'string', 'max:150'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'marca' => ['nullable', 'string', 'max:100'],
            'modelo' => ['nullable', 'string', 'max:100'],
            'serie' => ['nullable', 'string', 'max:100'],
            'anio' => ['nullable', 'integer', 'between:1900,'.(now()->year + 1)],
            'ubicacion' => ['nullable', 'string', 'max:100'],
            'criticidad' => ['required', Rule::in(array_keys(Maquina::CRITICIDADES))],
            'estado' => ['required', Rule::in(array_keys(Maquina::ESTADOS))],
            'horometro' => ['nullable', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'foto' => ['nullable', 'image', 'max:8192'],
            'componentes' => ['array'],
            'componentes.*.nombre' => ['nullable', 'string', 'max:100'],
            'componentes.*.specs' => ['array'],
            'partes' => ['array'],
            'partes.*.especificacion' => ['nullable', 'string', 'max:200'],
        ], [], ['codigo' => 'código', 'nombre' => 'descripción', 'anio' => 'año']);

        if ($request->hasFile('foto')) {
            if ($maquina?->foto) {
                Storage::disk('public')->delete($maquina->foto);
            }
            $datos['foto'] = $request->file('foto')->store('maquinas', 'public');
        } else {
            unset($datos['foto']);
        }
        unset($datos['componentes'], $datos['partes']);

        return $datos;
    }

    /** Reemplaza componentes y partes con lo que viene del formulario (filas vacías se ignoran). */
    private function guardarFicha(Maquina $maquina, Request $request): void
    {
        $maquina->componentes()->delete();
        foreach (array_values($request->input('componentes', [])) as $i => $c) {
            if (blank($c['nombre'] ?? null)) {
                continue;
            }
            $specs = collect($c['specs'] ?? [])
                ->filter(fn ($s) => filled($s['clave'] ?? null) || filled($s['valor'] ?? null))
                ->map(fn ($s) => ['clave' => trim($s['clave'] ?? ''), 'valor' => trim($s['valor'] ?? '')])->values()->all();
            $maquina->componentes()->create(['nombre' => $c['nombre'], 'especificaciones' => $specs, 'orden' => $i]);
        }

        $maquina->partes()->delete();
        foreach ($request->input('partes', []) as $p) {
            if (blank($p['especificacion'] ?? null)) {
                continue;
            }
            $maquina->partes()->create([
                'grupo' => $p['grupo'] ?? null,
                'especificacion' => $p['especificacion'],
                'dimensiones' => $p['dimensiones'] ?? null,
                'cantidad' => is_numeric($p['cantidad'] ?? null) ? $p['cantidad'] : 1,
                'producto_id' => ($p['producto_id'] ?? null) ?: null,
            ]);
        }
    }
}
