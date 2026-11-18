<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Campanita de avisos. La usan los tres roles, cada uno con sus propios avisos:
 *   Empleado y Admin: llega una consulta nueva.
 *   Cliente:          su consulta avanza de estado o el equipo se comunica.
 * Siempre se trabaja sobre $request->user()->notifications(): nadie puede ver ni marcar los avisos de otro.
 */
class NotificacionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('rol:cliente,empleado,admin')];
    }

    /** Lista completa de avisos del usuario, los más nuevos primero. */
    public function index(Request $request)
    {
        return view('notificaciones.index', [
            'notificaciones' => $request->user()->notifications()->paginate(15),
            'noLeidas' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /** Marca un aviso como leído y lleva a la consulta a la que se refiere. */
    public function leer(Request $request, string $id)
    {
        // Se busca solo entre los avisos del usuario: el de otra persona da 404
        $aviso = $request->user()->notifications()->findOrFail($id);
        $aviso->markAsRead();

        $lead = Lead::find($aviso->data['lead_id'] ?? null);
        if (! $lead) {
            return redirect()->route('notificaciones.index');
        }

        // El equipo va al detalle de la consulta; el cliente a la lista de sus consultas
        return $request->user()->puedeGestionar()
            ? redirect()->route('consultas.show', $lead)
            : redirect()->route('consultas.mias');
    }

    /** Marca como leídos todos los avisos pendientes del usuario. */
    public function leerTodas(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return redirect()->route('notificaciones.index')
            ->with('success', 'Marcaste todos los avisos como leídos.');
    }
}
