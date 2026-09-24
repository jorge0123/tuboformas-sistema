<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
        $request->user()->forceFill(['ultimo_acceso_at' => now()])->save();
        Auditoria::registrar('login', $request->user(), 'Inicio de sesión');

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
