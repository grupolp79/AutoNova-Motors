<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Inicio y cierre de sesión con la autenticación nativa de Laravel (sesiones).
 * Los clientes se registran solos (RegistroController); las cuentas de empleado y admin las crea UsuarioSeeder.
 */
class AuthController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $credenciales = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // attempt() busca el usuario por correo y compara la contraseña contra el hash guardado
        if (! Auth::attempt($credenciales, $request->boolean('recordarme'))) {
            // Mismo mensaje para "correo inexistente" y "contraseña incorrecta":
            // así no se revela qué correos están registrados.
            return back()
                ->withErrors(['email' => 'El correo o la contraseña no son correctos.'])
                ->onlyInput('email');
        }

        // Se renueva el id de sesión al iniciar (evita la fijación de sesión)
        $request->session()->regenerate();

        // intended(): vuelve a la página que el usuario quería ver antes de que lo mandaran al login
        return redirect()->intended(route('vehiculos.index'))
            ->with('success', 'Sesión iniciada. ¡Bienvenido, ' . Auth::user()->name . '!');
    }

    public function destroy(Request $request)
    {
        Auth::logout();

        // Se invalida la sesión y se cambia el token CSRF
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('vehiculos.index')->with('success', 'Sesión cerrada.');
    }
}
