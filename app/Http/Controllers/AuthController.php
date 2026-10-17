<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\User;
use App\Support\Permisos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function form()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $datos = $request->validate([
            'username' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string'],
        ]);

        // Se puede entrar con usuario o con correo.
        $campo = str_contains($datos['username'], '@') ? 'email' : 'username';
        $credenciales = [$campo => $datos['username'], 'password' => $datos['password'], 'activo' => true];

        if (! Auth::attempt($credenciales, $request->boolean('recordar'))) {
            return back()->withInput($request->only('username'))
                ->withErrors(['username' => 'Usuario o contraseña incorrectos, o el usuario está desactivado.']);
        }

        $request->session()->regenerate();
        if ($request->user()->hasAnyRole(Permisos::ROLES_SESION_UNICA)) {
            $this->cerrarOtrasSesiones($request, $request->user());
        }
        $request->user()->forceFill(['ultimo_acceso_at' => now()])->save();
        Auditoria::registrar('login', $request->user(), 'Inicio de sesión');

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Super administrador y administrador tienen una sola sesión activa: al entrar se cierran las
     * que tenían abiertas en otro navegador o equipo. Gana el último que entra (nadie queda fuera
     * por olvidar cerrar sesión en otra PC).
     */
    private function cerrarOtrasSesiones(Request $request, User $usuario): void
    {
        // Token nuevo: el "Recordarme" de los otros equipos deja de servir.
        $usuario->setRememberToken(Str::random(60));
        $usuario->save();
        if ($request->boolean('recordar')) {
            // Esta sesión recibe la cookie con el token nuevo.
            Auth::login($usuario, true);
        }
        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))->table(config('session.table'))
                ->where('user_id', $usuario->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
