<?php

namespace App\Http\Controllers;

use App\Models\Asistencia;
use App\Models\User;
use App\Services\AsistenciaService;
use App\Support\Csv;
use App\Support\Formato;
use App\Support\Ocupacion;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AsistenciaController extends Controller
{
    public function __construct(private AsistenciaService $servicio) {}

    /** Mi asistencia; quien tiene asistencia.ver además ve al personal de hoy y los registros de todos. */
    public function index(Request $request)
    {
        $u = $request->user();
        $this->servicio->cerrarOlvidadas();
        $todos = $u->can('asistencia.ver');

        $registros = $this->filtrar($request, $todos)->with(['user', 'turno', 'corrector'])
            ->orderByDesc('entrada_at')->paginate(30)->withQueryString();

        $hoy = $todos ? Ocupacion::hoy() : collect();
        $personal = $todos ? User::activos()->whereNotNull('turno_id')->orderBy('name')->get(['id', 'name']) : collect();

        return view('asistencia.index', [
            'registros' => $registros, 'hoy' => $hoy, 'personal' => $personal, 'todos' => $todos,
            'abierta' => $u->asistenciaAbierta(), 'tramo' => $this->servicio->tramoAbierto($u),
        ]);
    }

    public function exportar(Request $request)
    {
        $todos = $request->user()->can('asistencia.ver');
        $filas = $this->filtrar($request, $todos)->with(['user', 'turno'])->orderBy('entrada_at')->get()->map(fn ($a) => [
            $a->user->name, $a->fecha->format('d/m/Y'), $a->turno?->nombre, $a->entrada_at->format('H:i'), $a->salida_at?->format('d/m/Y H:i'),
            $a->horas(), $a->minutosTarde(), $a->horasExtra(), $a->salida_automatica ? 'Sí' : 'No', $a->notas,
        ]);

        return Csv::descargar('asistencia', ['Persona', 'Fecha', 'Turno', 'Entrada', 'Salida', 'Horas', 'Minutos tarde',
            'Horas extra', 'Salida sin marcar', 'Notas'], $filas);
    }

    public function entrada(Request $request)
    {
        $a = $this->servicio->marcarEntrada($request->user());
        $tramo = $this->servicio->tramoAbierto($request->user());

        return back()->with('ok', 'Entrada marcada a las '.$a->entrada_at->format('H:i').'.'
            .($tramo ? " Sigues con {$tramo->orden->folio}." : ' Buen turno.'));
    }

    public function salida(Request $request)
    {
        $a = $this->servicio->marcarSalida($request->user());

        return back()->with('ok', 'Salida marcada a las '.$a->salida_at->format('H:i').'. Trabajaste '
            .Formato::duracion($a->horas()).'.');
    }

    public function update(Request $request, Asistencia $asistencia)
    {
        $this->authorize('asistencia.gestionar');
        $d = $request->validate([
            'entrada_at' => ['required', 'date'],
            'salida_at' => ['nullable', 'date'],
            'notas' => ['required', 'string', 'max:255'],
        ], [], ['entrada_at' => 'entrada', 'salida_at' => 'salida', 'notas' => 'motivo']);
        $this->servicio->corregir($asistencia, Carbon::parse($d['entrada_at']), isset($d['salida_at']) ? Carbon::parse($d['salida_at']) : null,
            $d['notas'], $request->user());

        return back()->with('ok', 'Asistencia corregida.');
    }

    private function filtrar(Request $request, bool $todos)
    {
        return Asistencia::query()
            ->when(! $todos, fn ($q) => $q->where('user_id', $request->user()->id))
            ->when($todos && $request->filled('persona'), fn ($q) => $q->where('user_id', $request->persona))
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha', '>=', $request->desde))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha', '<=', $request->hasta))
            ->when($request->input('ver') === 'tarde', fn ($q) => $q->whereIn('id', $this->ids(fn ($a) => $a->minutosTarde() > 0)))
            ->when($request->input('ver') === 'antes', fn ($q) => $q->whereIn('id', $this->ids(fn ($a) => $a->minutosAntes() > 0)))
            ->when($request->input('ver') === 'sin_salida', fn ($q) => $q->where('salida_automatica', true));
    }

    /** Ids de asistencias que cumplen una condición contra su turno (llegada tarde, salida antes). */
    private function ids(callable $condicion): array
    {
        return Asistencia::with('turno')->whereNotNull('turno_id')->whereDate('fecha', '>=', today()->subDays(120))->get()
            ->filter($condicion)->pluck('id')->all();
    }
}
