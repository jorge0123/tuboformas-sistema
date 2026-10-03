<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Turno;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TurnoController extends Controller
{
    public function index()
    {
        $this->authorize('asistencia.gestionar');

        return view('asistencia.turnos', [
            'turnos' => Turno::withCount(['usuarios' => fn ($q) => $q->where('activo', true)])->orderByDesc('activo')->orderBy('hora_entrada')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('asistencia.gestionar');
        $t = Turno::create($this->validar($request) + ['activo' => true]);
        Auditoria::registrar('crear', $t, $t->nombre);

        return back()->with('ok', 'Turno creado. Asígnalo a cada persona en Usuarios.');
    }

    public function update(Request $request, Turno $turno)
    {
        $this->authorize('asistencia.gestionar');
        $turno->update($this->validar($request, $turno) + ['activo' => $request->boolean('activo')]);
        Auditoria::registrar('editar', $turno, $turno->nombre);

        return back()->with('ok', 'Turno guardado.');
    }

    private function validar(Request $request, ?Turno $t = null): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:60', Rule::unique('turnos')->ignore($t)],
            'hora_entrada' => ['required', 'date_format:H:i'],
            'hora_salida' => ['required', 'date_format:H:i', 'different:hora_entrada'],
            'dias' => ['required', 'array', 'min:1'],
            'dias.*' => ['integer', 'between:1,7'],
            'tolerancia' => ['required', 'integer', 'between:0,120'],
        ], [], ['hora_entrada' => 'entrada', 'hora_salida' => 'salida', 'dias' => 'días']);
    }
}
