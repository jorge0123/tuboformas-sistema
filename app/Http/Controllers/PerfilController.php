<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class PerfilController extends Controller
{
    public function edit(Request $request)
    {
        return view('perfil', ['u' => $request->user()->load(['roles', 'especialidad'])]);
    }

    public function update(Request $request)
    {
        $u = $request->user();
        $d = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users')->ignore($u)],
            'telefono' => ['nullable', 'string', 'max:30'],
        ]);
        $u->update($d + ['notif_email' => $request->boolean('notif_email')]);

        return back()->with('ok', 'Perfil actualizado.');
    }

    public function password(Request $request)
    {
        $request->validate([
            'actual' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers(), 'different:actual'],
        ], [], ['actual' => 'contraseña actual', 'password' => 'nueva contraseña']);
        $request->user()->update(['password' => Hash::make($request->password)]);
        Auditoria::registrar('cambiar_password', $request->user(), 'Cambió su contraseña');

        return back()->with('ok', 'Contraseña actualizada.');
    }
}
