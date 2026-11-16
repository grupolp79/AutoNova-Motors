<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deja pasar solo a los usuarios con alguno de los roles indicados.
 * Se usa en las rutas así: ->middleware('rol:empleado,admin')
 *
 * - Sin sesión iniciada: va al login y, después de entrar, vuelve a la página que quería ver.
 * - Con sesión pero sin el rol pedido: error 403 (acceso denegado).
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $usuario = $request->user();

        if (! $usuario) {
            // guest() guarda la URL pedida para que el login pueda volver a ella (intended)
            return redirect()->guest(route('login'))
                ->with('error', 'Iniciá sesión para continuar.');
        }

        if (! in_array($usuario->rol, $roles, true)) {
            abort(403, 'Tu rol no tiene permiso para realizar esta acción.');
        }

        return $next($request);
    }
}
