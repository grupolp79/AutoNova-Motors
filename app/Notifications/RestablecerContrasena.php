<?php

namespace App\Notifications;

use App\Http\Controllers\RecuperacionController;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Correo con el código de 6 dígitos para crear una contraseña nueva ("¿Olvidaste tu contraseña?").
 * Es SOLO TEXTO (sin HTML): con MAIL_MAILER=log se escribe en storage/logs/laravel.log
 * y el código queda solo en su propia línea, fácil de copiar.
 * El código solo viaja en el correo: la base guarda su hash en password_reset_tokens.
 */
class RestablecerContrasena extends Notification
{
    public function __construct(private string $codigo)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // text() usa solo la vista de texto (resources/views/emails/recuperacion.blade.php): no hay parte HTML
        return (new MailMessage)
            ->subject('Tu código para crear una contraseña nueva - AutoNova Motors')
            ->text('emails.recuperacion', [
                'nombre' => $notifiable->name,
                'codigo' => $this->codigo,
                'minutos' => RecuperacionController::MINUTOS_VALIDEZ,
            ]);
    }
}
