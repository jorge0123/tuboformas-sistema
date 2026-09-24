<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Support\Permisos;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolController extends Controller
{
    public function index()
    {
        $this->authorize('roles.gestionar');
        $roles = Role::withCount(['users', 'permissions'])->orderBy('id')->get();

        return view('roles.index', ['roles' => $roles, 'total' => count(Permisos::todos())]);
    }

    public function create(Request $request)
    {
        $this->authorize('roles.gestionar');
        $base = $request->filled('copiar') ? Role::findByName($request->copiar) : null;

        return view('roles.form', ['rol' => new Role, 'marcados' => $base?->permissions->pluck('name')->all() ?? []]);
    }

    public function store(Request $request)
    {
        $this->authorize('roles.gestionar');
        $d = $this->validar($request);
        $clave = Str::slug($d['nombre'], '_');
        abort_if(Role::where('name', $clave)->exists(), 422, 'Ya existe un rol con ese nombre.');
        $rol = Role::create(['name' => $clave, 'guard_name' => 'web']);
        $rol->forceFill(['nombre' => $d['nombre'], 'descripcion' => $d['descripcion'], 'es_sistema' => false])->save();
        $rol->syncPermissions($d['permisos'] ?? []);
        Auditoria::registrar('crear', $rol, $d['nombre']);

        return redirect()->route('roles.index')->with('ok', "Rol {$d['nombre']} creado.");
    }

    public function edit(Role $rol)
    {
        $this->authorize('roles.gestionar');
        abort_if($rol->name === Permisos::SUPER_ADMIN, 403, 'El super administrador siempre tiene todos los permisos.');

        return view('roles.form', ['rol' => $rol, 'marcados' => $rol->permissions->pluck('name')->all()]);
    }

    public function update(Request $request, Role $rol)
    {
        $this->authorize('roles.gestionar');
        abort_if($rol->name === Permisos::SUPER_ADMIN, 403);
        $d = $this->validar($request);
        $antes = $rol->permissions->pluck('name')->all();
        $rol->forceFill(['nombre' => $d['nombre'], 'descripcion' => $d['descripcion']])->save();
        $rol->syncPermissions($d['permisos'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Auditoria::registrar('editar', $rol, $d['nombre'], [
            'agregados' => array_values(array_diff($d['permisos'] ?? [], $antes)),
            'quitados' => array_values(array_diff($antes, $d['permisos'] ?? [])),
        ]);

        return redirect()->route('roles.index')->with('ok', 'Permisos del rol actualizados. Aplican desde el siguiente clic de cada usuario.');
    }

    public function destroy(Role $rol)
    {
        $this->authorize('roles.gestionar');
        abort_if($rol->es_sistema, 403, 'Los roles del sistema no se eliminan.');
        if ($rol->users()->exists()) {
            return back()->with('error', 'El rol tiene usuarios asignados. Cámbialos de rol primero.');
        }
        Auditoria::registrar('eliminar', $rol, $rol->nombre);
        $rol->delete();

        return redirect()->route('roles.index')->with('ok', 'Rol eliminado.');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'permisos' => ['nullable', 'array'],
            'permisos.*' => [Rule::in(array_keys(Permisos::todos()))],
        ]);
    }
}
