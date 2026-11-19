<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Usuarios del sistema, solo para el Admin (las rutas tienen rol:admin en routes/web.php):
 *   index:          listado de quién existe y con qué rol.
 *   create / store: alta de cuentas de empleado y de admin (los clientes se registran solos).
 * Nunca se muestran ni se guardan contraseñas en texto plano. No hay edición ni baja de usuarios.
 */
class UsuarioController extends Controller
{
    // Roles que existen, en el orden en que se muestran
    private const ROLES = ['admin', 'empleado', 'cliente'];

    // Roles que el Admin puede dar al crear una cuenta. 'cliente' no está: los clientes se registran solos.
    const ROLES_CREABLES = ['empleado' => 'Empleado', 'admin' => 'Administrador'];

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

    /** Formulario "Crear usuario". */
    public function create()
    {
        return view('usuarios.create', ['roles' => self::ROLES_CREABLES]);
    }

    public function store(Request $request)
    {
        // El correo se guarda siempre en minúsculas y sin espacios, igual que en el registro
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $datos = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            // El rol se valida contra la lista: aunque se arme el pedido a mano, no entra 'cliente' ni otro valor
            'rol' => ['required', Rule::in(array_keys(self::ROLES_CREABLES))],
            // Contraseña provisoria con las mismas reglas que el registro (están en el modelo User)
            'password' => User::REGLAS_CONTRASENA,
        ], [
            'rol.required' => 'Elegí el rol de la cuenta: empleado o administrador.',
            'rol.in' => 'El rol tiene que ser empleado o administrador. Los clientes crean su cuenta desde «Registrarse».',
        ]);

        // Alta y auditoría en una transacción. La contraseña se guarda con hash por el cast 'hashed' del modelo User;
        // en la auditoría no se escribe nada de ella.
        $usuario = DB::transaction(function () use ($datos) {
            $usuario = User::create([
                'name' => trim($datos['name']),
                'email' => $datos['email'],
                'rol' => $datos['rol'],
                'password' => $datos['password'],
            ]);

            Auditoria::registrar('usuario.crear',
                "Cuenta de {$usuario->name} ({$usuario->email}) con rol " . self::ROLES_CREABLES[$usuario->rol] . '.',
                $usuario);

            return $usuario;
        });

        return redirect()->route('usuarios.index')
            ->with('success', 'Creaste la cuenta de ' . $usuario->name . ' (' . self::ROLES_CREABLES[$usuario->rol] . '). '
                . 'La persona puede cambiar la contraseña con «¿Olvidaste tu contraseña?».');
    }
}
