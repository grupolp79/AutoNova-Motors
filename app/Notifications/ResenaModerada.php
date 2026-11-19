<?php

namespace App\Notifications;

use App\Models\Resena;
use Illuminate\Notifications\Notification;

/**
 * Aviso para el cliente: el Admin aprobó o rechazó su reseña.
 * Solo dice el resultado y el vehículo.
 */
class ResenaModerada extends Notification
{
    public function __construct(private Resena $resena)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $vehiculo = $this->resena->vehiculo;
        $auto = $vehiculo->marca . ' ' . $vehiculo->modelo;
        $aprobada = $this->resena->estado_moderacion === 'aprobada';

        return [
            'tipo' => 'resena_moderada',
            'resena_id' => $this->resena->id,
            'estado' => $this->resena->estado_moderacion,
            'titulo' => $aprobada ? 'Tu reseña fue aprobada' : 'Tu reseña no fue aprobada',
            'texto' => $aprobada
                ? 'Tu reseña sobre ' . $auto . ' ya se ve en la página del vehículo. ¡Gracias!'
                : 'Tu reseña sobre ' . $auto . ' no se va a publicar.',
        ];
    }
}
