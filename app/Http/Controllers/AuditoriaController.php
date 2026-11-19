<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Pantalla "Auditoría" (solo Admin): quién hizo qué, sobre qué y cuándo.
 * Es solo lectura: no hay rutas para editar ni borrar filas de la auditoría.
 */
class AuditoriaController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('rol:admin')];
    }

    public function index(Request $request)
    {
        // Filtros opcionales: ?usuario=ID y ?accion=vehiculo.editar. Un valor desconocido se ignora.
        $accion = $request->query('accion');
        if (! array_key_exists($accion ?? '', Auditoria::ACCIONES)) {
            $accion = null;
        }

        $usuarioId = ctype_digit((string) $request->query('usuario')) ? (int) $request->query('usuario') : null;

        $registros = Auditoria::query()
            ->with('usuario:id,name,rol')
            ->when($usuarioId, fn ($q) => $q->where('usuario_id', $usuarioId))
            ->when($accion, fn ($q) => $q->where('accion', $accion))
            // De la más nueva a la más vieja; el id desempata las del mismo segundo
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        // Para el filtro: solo las personas que tienen algo en la auditoría
        $usuarios = User::whereIn('id', Auditoria::query()->select('usuario_id')->whereNotNull('usuario_id'))
            ->orderBy('name')
            ->get(['id', 'name', 'rol']);

        return view('auditoria.index', [
            'registros' => $registros,
            'usuarios' => $usuarios,
            'usuarioActual' => $usuarioId,
            'accionActual' => $accion,
        ]);
    }
}
