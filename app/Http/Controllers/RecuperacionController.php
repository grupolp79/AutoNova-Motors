<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * "¿Olvidaste tu contraseña?" (RF-05). Usa el sistema que trae Laravel (Password broker):
 *   1. pedir / enviar:      la persona escribe su correo y, si existe, le llega un enlace con un código.
 *   2. formulario / guardar: desde el enlace, elige la contraseña nueva.
 * El código se guarda con hash en password_reset_tokens, vence a los 60 minutos (config/auth.php)
 * y se borra al usarlo, así que sirve una sola vez. Las rutas son solo para quien no inició sesión (guest).
 */
class RecuperacionController extends Controller
{
    // Mismo mensaje exista o no el correo: así nadie puede averiguar qué correos están registrados
    const MENSAJE_ENVIADO = 'Si el correo está registrado, te enviamos un enlace para crear una contraseña nueva. Revisá tu casilla: el enlace vence en 60 minutos.';

    const MENSAJE_ENLACE_INVALIDO = 'El enlace para crear la contraseña no es válido o ya venció. Pedí uno nuevo.';

    /** Pantalla 1: pide el correo. */
    public function pedir()
    {
        return view('auth.olvide');
    }

    public function enviar(Request $request)
    {
        // Mismo tratamiento del correo que en el registro: minúsculas y sin espacios
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ], [
            'email.required' => 'Escribí el correo con el que te registraste.',
            'email.email' => 'Ese correo no tiene un formato válido. Revisalo.',
        ]);

        // Si el correo existe, Laravel crea el código y llama a User::sendPasswordResetNotification.
        // El resultado (enviado, no existe, pedido hace menos de un minuto) no se muestra: la respuesta es siempre la misma.
        Password::sendResetLink($request->only('email'));

        return back()->with('success', self::MENSAJE_ENVIADO);
    }

    /** Pantalla 2: la que abre el enlace del correo. Pide la contraseña nueva. */
    public function formulario(Request $request, string $token)
    {
        return view('auth.restablecer', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function guardar(Request $request)
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            // Las mismas reglas que el registro (están en el modelo User)
            'password' => User::REGLAS_CONTRASENA,
        ], [
            'token.required' => self::MENSAJE_ENLACE_INVALIDO,
            'email.required' => self::MENSAJE_ENLACE_INVALIDO,
            'email.email' => self::MENSAJE_ENLACE_INVALIDO,
        ]);

        // reset() revisa que el código corresponda a ese correo y no haya vencido; si está bien, llama a la función
        $estado = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $usuario, string $nueva) {
                // El cast 'hashed' del modelo guarda la contraseña con hash
                $usuario->password = $nueva;
                // Nuevo remember_token: cualquier "Mantener la sesión iniciada" que hubiera deja de servir
                $usuario->setRememberToken(Str::random(60));

                // Cambio y auditoría en una transacción. Solo queda QUIÉN la cambió y cuándo:
                // nada de la contraseña ni del código. No hay sesión iniciada, así que se indica el usuario.
                DB::transaction(function () use ($usuario) {
                    $usuario->save();
                    Auditoria::registrar('contrasena.recuperar',
                        "{$usuario->name} ({$usuario->email}) eligió una contraseña nueva desde «¿Olvidaste tu contraseña?».",
                        $usuario, $usuario);
                });

                event(new PasswordReset($usuario));
            }
        );

        if ($estado !== Password::PASSWORD_RESET) {
            // Código inválido, vencido, ya usado o correo que no coincide: el mismo mensaje para todos
            return back()->withErrors(['email' => self::MENSAJE_ENLACE_INVALIDO]);
        }

        // No se inicia sesión sola: la persona entra con su contraseña nueva
        return redirect()->route('login')
            ->with('success', 'Listo, cambiaste tu contraseña. Ya podés iniciar sesión con la nueva.');
    }
}
