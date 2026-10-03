<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Cliente;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $this->autorizarVer($request);
        $clientes = Cliente::withCount(['pedidos', 'pedidos as activos' => fn ($q) => $q->activos()])
            ->withMax('pedidos', 'created_at')
            ->buscar($request->input('q'))
            ->when($request->input('estado', 'activos') === 'activos', fn ($q) => $q->where('activo', true))
            ->orderBy('nombre')->paginate(30)->withQueryString();

        return view('clientes.index', compact('clientes'));
    }

    public function show(Request $request, Cliente $cliente)
    {
        $this->autorizarVer($request);
        $pedidos = $cliente->pedidos()->visiblesPara($request->user())->with('vendedor')->withCount('lineas')->latest()->limit(30)->get();

        return view('clientes.show', compact('cliente', 'pedidos'));
    }

    public function create()
    {
        $this->authorize('clientes.gestionar');

        return view('clientes.form', ['c' => new Cliente(['activo' => true])]);
    }

    public function store(Request $request)
    {
        $this->authorize('clientes.gestionar');
        $c = Cliente::create($this->validar($request));
        Auditoria::registrar('crear', $c, $c->nombre);

        // Alta rápida desde el formulario de pedido.
        if ($request->wantsJson()) {
            return response()->json($c->only(['id', 'nombre', 'nit', 'contacto', 'telefono', 'direccion', 'municipio']));
        }

        return redirect()->route('clientes.show', $c)->with('ok', 'Cliente registrado.');
    }

    public function edit(Cliente $cliente)
    {
        $this->authorize('clientes.gestionar');

        return view('clientes.form', ['c' => $cliente]);
    }

    public function update(Request $request, Cliente $cliente)
    {
        $this->authorize('clientes.gestionar');
        $cliente->update($this->validar($request));
        Auditoria::registrar('editar', $cliente, $cliente->nombre);

        return redirect()->route('clientes.show', $cliente)->with('ok', 'Cambios guardados.');
    }

    private function autorizarVer(Request $request): void
    {
        abort_unless($request->user()->canAny(['clientes.gestionar', 'pedidos.crear', 'pedidos.ver_todos']), 403);
    }

    private function validar(Request $request): array
    {
        $d = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'nit' => ['nullable', 'string', 'max:30'],
            'contacto' => ['nullable', 'string', 'max:100'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'municipio' => ['nullable', 'string', 'max:100'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ]);
        $d['activo'] = $request->has('activo') ? $request->boolean('activo') : true;

        return $d;
    }
}
