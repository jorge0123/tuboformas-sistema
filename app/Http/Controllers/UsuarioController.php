<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Especialidad;
use App\Models\User;
use App\Support\Permisos;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UsuarioController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('usuarios.ver');
        $usuarios = User::with(['roles', 'especialidad', 'permissions'])
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->q.'%')
                ->orWhere('username', 'like', '%'.$request->q.'%')->orWhere('email', 'like', '%'.$request->q.'%')))
            ->when($request->filled('rol'), fn ($q) => $q->role($request->rol))
            ->when($request->input('estado', 'activos') === 'activos', fn ($q) => $q->where('activo', true))
            ->when($request->input('estado') === 'inactivos', fn ($q) => $q->where('activo', false))
            ->orderBy('name')->paginate(30)->withQueryString();
        $roles = Role::orderBy('id')->get();

        return view('usuarios.index', compact('usuarios', 'roles'));
    }

    public function create()
    {
        $this->authorize('usuarios.gestionar');

        return view('usuarios.form', $this->datos(new User(['activo' => true, 'notif_email' => true])));
    }

    public function store(Request $request)
    {
        $this->authorize('usuarios.gestionar');
        $d = $this->validar($request);
        $u = User::create(collect($d)->except(['rol', 'permisos_extra'])->all());
        $u->syncRoles([$d['rol']]);
        $u->syncPermissions($d['permisos_extra'] ?? []);
        Auditoria::registrar('crear', $u, "{$u->username} · rol {$d['rol']}");

        return redirect()->route('usuarios.index')->with('ok', "Usuario {$u->username} creado.");
    }

    public function edit(User $usuario)
    {
        $this->authorize('usuarios.gestionar');
        $this->protegerSuperAdmin($usuario);

        return view('usuarios.form', $this->datos($usuario->load(['roles', 'permissions'])));
    }

    public function update(Request $request, User $usuario)
    {
        $this->authorize('usuarios.gestionar');
        $this->protegerSuperAdmin($usuario);
        $d = $this->validar($request, $usuario);
        if ($usuario->id === $request->user()->id && (! $d['activo'] || $d['rol'] !== $usuario->rolPrincipal()?->name)) {
            return back()->withInput()->with('error', 'No puedes desactivarte ni cambiar tu propio rol.');
        }
        $datos = collect($d)->except(['rol', 'permisos_extra'])->all();
        if (empty($datos['password'])) {
            unset($datos['password']);
        }
        $usuario->update($datos);
        $usuario->syncRoles([$d['rol']]);
        $usuario->syncPermissions($d['permisos_extra'] ?? []);
        Auditoria::registrar('editar', $usuario, "{$usuario->username} · rol {$d['rol']}", ['permisos_extra' => $d['permisos_extra'] ?? []]);

        return redirect()->route('usuarios.index')->with('ok', 'Usuario actualizado.');
    }

    public function auditoria(Request $request)
    {
        $this->authorize('auditoria.ver');
        $registros = Auditoria::with('user')
            ->when($request->filled('usuario'), fn ($q) => $q->where('user_id', $request->usuario))
            ->when($request->filled('accion'), fn ($q) => $q->where('accion', $request->accion))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('descripcion', 'like', '%'.$request->q.'%')->orWhere('entidad', 'like', '%'.$request->q.'%')))
            ->latest('id')->paginate(50)->withQueryString();
        $usuarios = User::orderBy('name')->get(['id', 'name']);
        $acciones = Auditoria::distinct()->orderBy('accion')->pluck('accion');

        return view('usuarios.auditoria', compact('registros', 'usuarios', 'acciones'));
    }

    private function datos(User $u): array
    {
        $roles = Role::orderBy('id')->get()
            ->reject(fn ($r) => $r->name === Permisos::SUPER_ADMIN && ! auth()->user()->esSuperAdmin());

        return [
            'u' => $u,
            'roles' => $roles,
            'especialidades' => Especialidad::where('activo', true)->orderBy('nombre')->get(),
            'permisosRol' => $roles->mapWithKeys(fn ($r) => [$r->name => $r->permissions->pluck('name')]),
        ];
    }

    private function validar(Request $request, ?User $u = null): array
    {
        $d = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users')->ignore($u)],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users')->ignore($u)],
            'puesto' => ['nullable', 'string', 'max:100'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'especialidad_id' => ['nullable', 'exists:especialidades,id'],
            'password' => [$u ? 'nullable' : 'required', 'confirmed', Password::min(8)->letters()->numbers()],
            'rol' => ['required', 'exists:roles,name'],
            'permisos_extra' => ['nullable', 'array'],
            'permisos_extra.*' => [Rule::in(array_keys(Permisos::todos()))],
        ], [], ['name' => 'nombre', 'username' => 'usuario', 'password' => 'contraseña']);
        abort_if($d['rol'] === Permisos::SUPER_ADMIN && ! $request->user()->esSuperAdmin(), 403, 'Solo un super administrador asigna ese rol.');
        $d['activo'] = $request->boolean('activo');
        $d['notif_email'] = $request->boolean('notif_email');
        // Los permisos extra que ya trae el rol no se guardan aparte.
        $delRol = Role::findByName($d['rol'])->permissions->pluck('name')->all();
        $d['permisos_extra'] = array_values(array_diff($d['permisos_extra'] ?? [], $delRol));

        return $d;
    }

    private function protegerSuperAdmin(User $usuario): void
    {
        abort_if($usuario->esSuperAdmin() && ! auth()->user()->esSuperAdmin(), 403, 'Solo un super administrador puede modificar a otro super administrador.');
    }
}
