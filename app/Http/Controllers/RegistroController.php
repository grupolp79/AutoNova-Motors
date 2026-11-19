<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Registro de clientes (RF-05). Cualquier visitante puede crear una cuenta, y esa cuenta
 * es SIEMPRE de rol cliente. Las cuentas de empleado y admin no se crean desde acá:
 * un rol con más permisos no puede ser algo que el propio usuario elige.
 */
class RegistroController extends Controller
{
    public function create()
    {
        return view('auth.registro');
    }

    public function store(Request $request)
    {
        // El correo se guarda siempre en minúsculas y sin espacios, así "Ana@Correo.com" y "ana@correo.com" son el mismo
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $datos = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            // Mínimo 8, máximo 72 y con confirmación (las reglas están en el modelo User)
            'password' => User::REGLAS_CONTRASENA,
        ]);

        // El rol no sale del formulario: se fija acá. La contraseña se guarda con hash por el cast 'hashed' del modelo.
        $usuario = User::create([
            'name' => trim($datos['name']),
            'email' => $datos['email'],
            'password' => $datos['password'],
            'rol' => 'cliente',
        ]);

        Auth::login($usuario);
        $request->session()->regenerate();

        return redirect()->intended(route('vehiculos.index'))
            ->with('success', 'Cuenta creada. ¡Bienvenido, ' . $usuario->name . '!');
    }
}
