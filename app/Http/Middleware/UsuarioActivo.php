<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Si desactivan al usuario mientras tiene sesión abierta, lo saca en su siguiente clic. */
class UsuarioActivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();
        if ($u && ! $u->activo) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['username' => 'Tu usuario está desactivado. Consulta con el administrador.']);
        }

        return $next($request);
    }
}
