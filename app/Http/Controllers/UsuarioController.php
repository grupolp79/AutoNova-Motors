<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Listado de usuarios (solo lectura) para el Admin: quién existe y con qué rol.
 * Nunca se muestran contraseñas. La ruta está protegida con rol:admin (routes/web.php).
 */
class UsuarioController extends Controller
{
    // Roles que existen, en el orden en que se muestran
    private const ROLES = ['admin', 'empleado', 'cliente'];

    public function index(Request $request)
    {
        // Filtro opcional ?rol=admin|empleado|cliente. Un valor desconocido se ignora y se muestran todos.
        $rol = $request->query('rol');
        if (! in_array($rol, self::ROLES, true)) {
            $rol = null;
        }

        $usuarios = User::query()
            ->when($rol, fn ($q) => $q->where('rol', $rol))
            // Admin primero, después empleados, después clientes; dentro de cada rol, por nombre
            ->orderByRaw("CASE rol WHEN 'admin' THEN 1 WHEN 'empleado' THEN 2 ELSE 3 END")
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        // Cantidad de usuarios por rol (siempre sobre el total, aunque haya filtro)
        $totales = User::query()
            ->select('rol', DB::raw('count(*) as total'))
            ->groupBy('rol')
            ->pluck('total', 'rol');

        return view('usuarios.index', [
            'usuarios' => $usuarios,
            'roles' => self::ROLES,
            'rolActual' => $rol,
            'totales' => $totales,
        ]);
    }
}
