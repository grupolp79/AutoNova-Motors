<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Campanita de avisos. La usan los tres roles, cada uno con sus propios avisos:
 *   Empleado y Admin: llega una consulta nueva o un cliente pide un turno.
 *   Admin:            además, llega una reseña para moderar.
 *   Cliente:          su consulta avanza de estado, el equipo se comunica, le cambian el estado de un turno
 *                     o le aprueban o rechazan una reseña.
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

    /** Marca un aviso como leído y lleva a la consulta (o al turno) al que se refiere. */
    public function leer(Request $request, string $id)
    {
        // Se busca solo entre los avisos del usuario: el de otra persona da 404
        $aviso = $request->user()->notifications()->findOrFail($id);
        $aviso->markAsRead();

        // Avisos de turnos (RF-13): el equipo va a la agenda; el cliente a "Mis turnos"
        if (isset($aviso->data['turno_id'])) {
            return $request->user()->puedeGestionar()
                ? redirect()->route('turnos.index')
                : redirect()->route('turnos.mios');
        }

        // Avisos de reseñas (RF-14): el Admin va a la moderación; el cliente a "Mis consultas"
        if (isset($aviso->data['resena_id'])) {
            return $request->user()->esAdmin()
                ? redirect()->route('resenas.index')
                : redirect()->route('consultas.mias');
        }

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
