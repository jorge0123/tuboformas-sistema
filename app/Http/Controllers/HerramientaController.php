<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Herramienta;
use App\Models\User;
use App\Notifications\Aviso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class HerramientaController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('herramientas.ver');
        $herramientas = Herramienta::with('asignadaA')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('nombre', 'like', '%'.$request->q.'%')
                ->orWhere('codigo', 'like', '%'.$request->q.'%')->orWhere('marca', 'like', '%'.$request->q.'%')))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('tecnico'), fn ($q) => $q->where('asignada_a', $request->tecnico))
            ->when($request->filled('categoria'), fn ($q) => $q->where('categoria', $request->categoria))
            ->orderBy('codigo')->paginate(30)->withQueryString();

        $resumen = Herramienta::selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado');
        $tecnicos = User::activos()->permission('ot.ejecutar')->orderBy('name')->get();
        $categorias = Herramienta::whereNotNull('categoria')->distinct()->orderBy('categoria')->pluck('categoria');

        return view('herramientas.index', compact('herramientas', 'resumen', 'tecnicos', 'categorias'));
    }

    /** La caja de herramientas de cada técnico. */
    public function mias(Request $request)
    {
        $this->authorize('herramientas.ver');
        $herramientas = $request->user()->herramientas()->with(['asignaciones' => fn ($q) => $q->whereNull('devuelto_at')])->orderBy('codigo')->get();

        return view('herramientas.mias', compact('herramientas'));
    }

    public function show(Herramienta $herramienta)
    {
        $this->authorize('herramientas.ver');
        $herramienta->load(['asignadaA', 'asignaciones.user', 'asignaciones.entregadoPor', 'asignaciones.recibidoPor']);
        $tecnicos = User::activos()->permission('ot.ejecutar')->orderBy('name')->get();

        return view('herramientas.show', compact('herramienta', 'tecnicos'));
    }

    public function create()
    {
        $this->authorize('herramientas.gestionar');

        return view('herramientas.form', ['h' => new Herramienta(['estado' => 'disponible']), 'categorias' => $this->categorias()]);
    }

    public function store(Request $request)
    {
        $this->authorize('herramientas.gestionar');
        $h = Herramienta::create($this->validar($request));
        Auditoria::registrar('crear', $h, "{$h->codigo} · {$h->nombre}");

        return redirect()->route('herramientas.show', $h)->with('ok', 'Herramienta registrada.');
    }

    public function edit(Herramienta $herramienta)
    {
        $this->authorize('herramientas.gestionar');

        return view('herramientas.form', ['h' => $herramienta, 'categorias' => $this->categorias()]);
    }

    public function update(Request $request, Herramienta $herramienta)
    {
        $this->authorize('herramientas.gestionar');
        $herramienta->update($this->validar($request, $herramienta));
        Auditoria::registrar('editar', $herramienta, "{$herramienta->codigo} · {$herramienta->nombre}");

        return redirect()->route('herramientas.show', $herramienta)->with('ok', 'Cambios guardados.');
    }

    public function destroy(Herramienta $herramienta)
    {
        $this->authorize('herramientas.gestionar');
        if ($herramienta->asignaciones()->exists()) {
            return back()->with('error', 'Tiene historial de asignaciones. Márcala como "De baja" en lugar de eliminarla.');
        }
        Auditoria::registrar('eliminar', $herramienta, "{$herramienta->codigo} · {$herramienta->nombre}");
        $herramienta->delete();

        return redirect()->route('herramientas.index')->with('ok', 'Herramienta eliminada.');
    }

    public function asignar(Request $request, Herramienta $herramienta)
    {
        $this->authorize('herramientas.asignar');
        $d = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'estado_entrega' => ['required', Rule::in(array_keys(Herramienta::CONDICIONES))],
            'notas' => ['nullable', 'string', 'max:255'],
        ]);
        if ($herramienta->estado !== 'disponible') {
            return back()->with('error', 'Solo se asignan herramientas disponibles. Primero recíbela.');
        }
        DB::transaction(function () use ($herramienta, $d, $request) {
            $herramienta->asignaciones()->create($d + ['entregado_por' => $request->user()->id, 'entregado_at' => now()]);
            $herramienta->update(['estado' => 'asignada', 'asignada_a' => $d['user_id']]);
        });
        $tecnico = User::find($d['user_id']);
        Auditoria::registrar('asignar', $herramienta, "Entregada a {$tecnico->name}");
        $tecnico->notify(new Aviso('Herramienta asignada', "{$herramienta->codigo} · {$herramienta->nombre} quedó a tu cargo.", route('herramientas.mias')));

        return back()->with('ok', "Herramienta entregada a {$tecnico->name}.");
    }

    public function devolver(Request $request, Herramienta $herramienta)
    {
        $this->authorize('herramientas.asignar');
        $d = $request->validate([
            'estado_devolucion' => ['required', Rule::in(array_keys(Herramienta::CONDICIONES))],
            'notas' => ['nullable', 'string', 'max:255'],
        ]);
        $asig = $herramienta->asignaciones()->whereNull('devuelto_at')->first();
        abort_unless($asig, 422, 'La herramienta no está asignada.');
        DB::transaction(function () use ($herramienta, $asig, $d, $request) {
            $asig->update([
                'devuelto_at' => now(), 'recibido_por' => $request->user()->id,
                'estado_devolucion' => $d['estado_devolucion'],
                'notas' => trim(($asig->notas ? $asig->notas.' · ' : '').($d['notas'] ?? '')) ?: null,
            ]);
            $herramienta->update([
                'asignada_a' => null,
                'estado' => ['bueno' => 'disponible', 'danado' => 'en_reparacion', 'perdido' => 'perdida'][$d['estado_devolucion']],
            ]);
        });
        Auditoria::registrar('recibir', $herramienta, 'Devuelta: '.Herramienta::CONDICIONES[$d['estado_devolucion']]);
        if ($asig->user_id !== $request->user()->id) {
            $asig->user->notify(new Aviso('Herramienta recibida', "{$herramienta->codigo} · {$herramienta->nombre} ya no está a tu cargo (devuelta: "
                .mb_strtolower(Herramienta::CONDICIONES[$d['estado_devolucion']]).').', route('herramientas.mias')));
        }

        return back()->with('ok', 'Herramienta recibida.');
    }

    private function categorias()
    {
        return Herramienta::whereNotNull('categoria')->distinct()->orderBy('categoria')->pluck('categoria');
    }

    private function validar(Request $request, ?Herramienta $h = null): array
    {
        $d = $request->validate([
            'codigo' => ['required', 'string', 'max:30', Rule::unique('herramientas')->ignore($h)],
            'nombre' => ['required', 'string', 'max:150'],
            'categoria' => ['nullable', 'string', 'max:60'],
            'marca' => ['nullable', 'string', 'max:60'],
            'modelo' => ['nullable', 'string', 'max:60'],
            'serie' => ['nullable', 'string', 'max:60'],
            'costo' => ['nullable', 'numeric', 'min:0'],
            'fecha_compra' => ['nullable', 'date'],
            'notas' => ['nullable', 'string', 'max:1000'],
            'estado' => ['nullable', Rule::in(['disponible', 'en_reparacion', 'perdida', 'baja'])],
            'foto' => ['nullable', 'image', 'max:8192'],
        ], [], ['codigo' => 'código']);
        // "asignada" solo cambia con Asignar/Recibir.
        if ($h?->estado === 'asignada' || empty($d['estado'])) {
            unset($d['estado']);
        }
        if ($request->hasFile('foto')) {
            if ($h?->foto) {
                Storage::disk('public')->delete($h->foto);
            }
            $d['foto'] = $request->file('foto')->store('herramientas', 'public');
        } else {
            unset($d['foto']);
        }

        return $d;
    }
}
