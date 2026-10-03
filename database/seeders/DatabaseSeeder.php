<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Permisos;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RolesPermisosSeeder::class, CatalogosSeeder::class]);

        // Primer acceso. Cambiar la contraseña al entrar (Mi perfil).
        $admin = User::firstOrCreate(['username' => 'admin'], [
            'name' => 'Administrador del sistema',
            'email' => env('ADMIN_EMAIL'),
            'password' => env('ADMIN_PASSWORD', 'Tuboformas2026!'),
            'puesto' => 'TI',
        ]);
        $admin->syncRoles([Permisos::SUPER_ADMIN]);

        // Datos de ejemplo solo en local (o si se pide explícitamente).
        if (app()->environment('local') || env('SEED_DEMO')) {
            $this->call([DemoSeeder::class, DemoAmpliadoSeeder::class]);
        }
    }
}
