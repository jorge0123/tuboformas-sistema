<?php

namespace Database\Seeders;

use App\Support\Permisos;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crea o actualiza permisos y roles desde App\Support\Permisos.
 * Se puede volver a correr sin perder cambios: a los roles que ya existen solo
 * se les AGREGAN permisos nuevos (no se quitan los que un admin haya marcado).
 * Con --fresh (RESETEAR_ROLES=1) se reescriben exactamente como en el catálogo.
 * Los permisos y roles de sistema que ya no están en el catálogo se eliminan; sus
 * usuarios pasan al rol Consulta para que un administrador les asigne el que toca.
 */
class RolesPermisosSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (array_keys(Permisos::todos()) as $p) {
            Permission::findOrCreate($p, 'web');
        }

        Permission::whereNotIn('name', array_keys(Permisos::todos()))->delete();
        foreach (Role::where('es_sistema', true)->whereNotIn('name', array_keys(Permisos::roles()))->get() as $viejo) {
            foreach ($viejo->users as $u) {
                $u->syncRoles(['consulta']);
            }
            $viejo->delete();
        }

        $resetear = (bool) env('RESETEAR_ROLES', false);
        foreach (Permisos::roles() as $clave => [$nombre, $descripcion]) {
            $rol = Role::where('name', $clave)->first();
            $nuevo = ! $rol;
            $rol ??= Role::create(['name' => $clave, 'guard_name' => 'web']);
            $rol->forceFill(['nombre' => $nombre, 'descripcion' => $descripcion, 'es_sistema' => true])->save();

            $permisos = Permisos::permisosDeRol($clave);
            if ($nuevo || $resetear || in_array($clave, [Permisos::SUPER_ADMIN, 'administrador'])) {
                $rol->syncPermissions($permisos);
            } else {
                $rol->givePermissionTo(array_diff($permisos, $rol->permissions->pluck('name')->all()));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
