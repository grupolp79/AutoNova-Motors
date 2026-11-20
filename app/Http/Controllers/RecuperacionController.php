<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\User;
use App\Notifications\RestablecerContrasena;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * "¿Olvidaste tu contraseña?" (RF-05) con un CÓDIGO DE 6 DÍGITOS que llega por correo:
 *   1. pedir / enviar:       la persona escribe su correo y, si existe, le llega el código.
 *   2. formulario / guardar: escribe su correo, el código y la contraseña nueva.
 * Un código funciona en cualquier dirección del sitio (un enlace dependía de que APP_URL estuviera bien).
 *
 * El código se guarda con hash en password_reset_tokens (una fila por correo: email, token, created_at),
 * vence a los 15 minutos, se borra al usarlo (sirve una sola vez) y se bloquea a los 5 intentos fallidos.
 * Las rutas son solo para quien no inició sesión (guest).
 */
class RecuperacionController extends Controller
{
    const MINUTOS_VALIDEZ = 15;

    // Entre un pedido y el siguiente para el mismo correo: así nadie le llena la casilla a otra persona
    const SEGUNDOS_ENTRE_PEDIDOS = 60;

    // Intentos fallidos permitidos por código; al llegar a este número el código se borra
    const MAX_INTENTOS = 5;

    // Mismo mensaje exista o no el correo: así nadie puede averiguar qué correos están registrados
    const MENSAJE_ENVIADO = 'Si el correo está registrado, te enviamos un código de 6 dígitos. Vence en 15 minutos.';

    // Un solo mensaje para todos los fallos: correo que no existe, código incorrecto, vencido, ya usado o bloqueado
    const MENSAJE_CODIGO_INVALIDO = 'El código no es válido o ya venció. Pedí uno nuevo.';

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

        $email = $request->input('email');
        $usuario = User::where('email', $email)->first();

        if ($usuario && ! $this->pidioHacePoco($email)) {
            $this->crearYEnviarCodigo($usuario);
        }

        // Exista o no el correo, la respuesta es la misma: va a la pantalla 2 con el correo ya escrito
        return redirect()->route('password.reset')
            ->with('success', self::MENSAJE_ENVIADO)
            ->with('email', $email);
    }

    /** Pantalla 2: correo, código y contraseña nueva. */
    public function formulario()
    {
        return view('auth.restablecer');
    }

    public function guardar(Request $request)
    {
        // El correo como en el registro y el código sin espacios (por si se copió "004 217" o con un espacio al final)
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
            'codigo' => preg_replace('/\s+/', '', (string) $request->input('codigo')),
        ]);

        // 1) Primero el formulario. Si algo está mal escrito (por ejemplo, la contraseña es corta),
        //    se muestra el error y NO se gasta el código ni se cuenta un intento.
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'codigo' => ['required', 'regex:/^[0-9]{6}$/'],
            // Las mismas reglas que el registro (están en el modelo User)
            'password' => User::REGLAS_CONTRASENA,
        ], [
            'email.required' => 'Escribí el correo con el que te registraste.',
            'email.email' => 'Ese correo no tiene un formato válido. Revisalo.',
            'codigo.required' => 'Escribí el código de 6 dígitos que te llegó por correo.',
            'codigo.regex' => 'El código tiene 6 números. Revisalo en el correo.',
        ]);

        $email = $request->input('email');

        // 2) Después el código
        if (! $this->codigoEsValido($email, $request->input('codigo'))) {
            return back()
                ->withErrors(['codigo' => self::MENSAJE_CODIGO_INVALIDO])
                ->withInput($request->only('email'));
        }

        // 3) Todo bien: se guarda la contraseña nueva. Si el código es válido, el usuario existe.
        $usuario = User::where('email', $email)->firstOrFail();
        // El cast 'hashed' del modelo guarda la contraseña con hash
        $usuario->password = $request->input('password');
        // Nuevo remember_token: cualquier "Mantener la sesión iniciada" que hubiera deja de servir
        $usuario->setRememberToken(Str::random(60));

        // Cambio, borrado del código y auditoría en una transacción. Solo queda QUIÉN la cambió y cuándo:
        // nada de la contraseña ni del código. No hay sesión iniciada, así que se indica el usuario.
        DB::transaction(function () use ($usuario) {
            $usuario->save();
            DB::table('password_reset_tokens')->where('email', $usuario->email)->delete();
            Auditoria::registrar('contrasena.recuperar',
                "{$usuario->name} ({$usuario->email}) eligió una contraseña nueva desde «¿Olvidaste tu contraseña?».",
                $usuario, $usuario);
        });

        RateLimiter::clear($this->claveIntentos($email));
        event(new PasswordReset($usuario));

        // No se inicia sesión sola: la persona entra con su contraseña nueva
        return redirect()->route('login')
            ->with('success', 'Listo, cambiaste tu contraseña. Ya podés iniciar sesión con la nueva.');
    }

    /** Un número entre 0 y 999999 como texto de 6 dígitos, con ceros a la izquierda (4217 -> "004217"). */
    public static function formatearCodigo(int $numero): string
    {
        return str_pad((string) $numero, 6, '0', STR_PAD_LEFT);
    }

    /** ¿Ya se pidió un código para este correo hace menos de 60 segundos? */
    private function pidioHacePoco(string $email): bool
    {
        $pedido = DB::table('password_reset_tokens')->where('email', $email)->value('created_at');

        return $pedido !== null
            && Carbon::parse($pedido)->addSeconds(self::SEGUNDOS_ENTRE_PEDIDOS)->isFuture();
    }

    /** Crea el código, guarda solo su hash (reemplaza al anterior) y manda el correo. */
    private function crearYEnviarCodigo(User $usuario): void
    {
        // random_int es aleatorio de verdad (sirve para secretos); rand() no
        $codigo = self::formatearCodigo(random_int(0, 999999));

        // Una sola fila por correo: un pedido nuevo pisa al anterior y el código viejo deja de servir
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $usuario->email],
            ['token' => Hash::make($codigo), 'created_at' => now()],
        );

        // Código nuevo, intentos nuevos
        RateLimiter::clear($this->claveIntentos($usuario->email));

        try {
            $usuario->notify(new RestablecerContrasena($codigo));
        } catch (\Throwable $error) {
            // Si el servidor de correo no responde, la persona ve la respuesta de siempre (no un error 500)
            // y el error queda en storage/logs/laravel.log. Se borra el código porque nunca llegó.
            report($error);
            DB::table('password_reset_tokens')->where('email', $usuario->email)->delete();
        }
    }

    /**
     * Revisa el código de ese correo: que exista, que no haya vencido, que no esté bloqueado y que coincida.
     * Cada fallo cuenta un intento; al 5.º se borra el código y hay que pedir uno nuevo.
     */
    private function codigoEsValido(string $email, string $codigo): bool
    {
        $clave = $this->claveIntentos($email);

        if (RateLimiter::tooManyAttempts($clave, self::MAX_INTENTOS)) {
            return false;
        }

        $fila = DB::table('password_reset_tokens')->where('email', $email)->first();

        $vigente = $fila !== null
            && Carbon::parse($fila->created_at)->addMinutes(self::MINUTOS_VALIDEZ)->isFuture();

        if ($vigente && Hash::check($codigo, $fila->token)) {
            return true;
        }

        // Fallo: se cuenta el intento (el contador dura lo mismo que el código)
        RateLimiter::hit($clave, self::MINUTOS_VALIDEZ * 60);
        if (RateLimiter::attempts($clave) >= self::MAX_INTENTOS) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
        }

        return false;
    }

    /** Clave del contador de intentos fallidos de un correo (se guarda en la caché, no hace falta tabla). */
    private function claveIntentos(string $email): string
    {
        return 'recuperar-codigo:' . $email;
    }
}
