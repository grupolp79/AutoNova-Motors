<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Correo con el enlace para crear una contraseña nueva ("¿Olvidaste tu contraseña?").
 * Reemplaza al correo en inglés que trae Laravel (User::sendPasswordResetNotification).
 * Es SOLO TEXTO (sin HTML): con MAIL_MAILER=log se escribe en storage/logs/laravel.log
 * y el enlace queda solo en su propia línea, fácil de copiar.
 * El código ($token) solo viaja en el enlace: la base guarda su hash en password_reset_tokens.
 */
class RestablecerContrasena extends Notification
{
    public function __construct(private string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // El enlace lleva el código y el correo; vence a los minutos que dice config/auth.php (60)
        $enlace = route('password.reset', ['token' => $this->token, 'email' => $notifiable->email]);

        // text() usa solo la vista de texto (resources/views/emails/recuperacion.blade.php): no hay parte HTML
        return (new MailMessage)
            ->subject('Crear una contraseña nueva - AutoNova Motors')
            ->text('emails.recuperacion', [
                'nombre' => $notifiable->name,
                'enlace' => $enlace,
                'minutos' => config('auth.passwords.users.expire'),
            ]);
    }
}
