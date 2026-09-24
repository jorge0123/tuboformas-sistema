<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificacionController extends Controller
{
    public function index(Request $request)
    {
        $notificaciones = $request->user()->notifications()->paginate(25);

        return view('notificaciones.index', compact('notificaciones'));
    }

    /** Para la campana: últimas 12 y cuántas sin leer. */
    public function recientes(Request $request)
    {
        $u = $request->user();

        return response()->json([
            'sin_leer' => $u->unreadNotifications()->count(),
            'items' => $u->notifications()->limit(12)->get()->map(fn ($n) => [
                'id' => $n->id,
                'titulo' => $n->data['titulo'] ?? '',
                'mensaje' => $n->data['mensaje'] ?? '',
                'url' => route('notificaciones.abrir', $n->id),
                'leida' => (bool) $n->read_at,
                'hace' => $n->created_at->diffForHumans(),
            ]),
        ]);
    }

    /** Marca como leída y lleva al enlace de la notificación. */
    public function abrir(Request $request, string $id)
    {
        $n = $request->user()->notifications()->findOrFail($id);
        $n->markAsRead();

        return redirect($n->data['enlace'] ?? route('dashboard'));
    }

    public function leerTodas(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('ok', 'Notificaciones marcadas como leídas.');
    }
}
