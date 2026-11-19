<?php

namespace App\Notifications;

use App\Models\Resena;
use Illuminate\Notifications\Notification;

/**
 * Aviso para los Admin: un cliente dejó una reseña y espera moderación.
 * Igual que NuevaConsulta: se guarda solo en la base de datos (campanita). No copia el texto de la reseña.
 */
class NuevaResena extends Notification
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

        return [
            'tipo' => 'nueva_resena',
            'resena_id' => $this->resena->id,
            'titulo' => 'Reseña para moderar',
            'texto' => $this->resena->cliente->name . ' dejó una reseña sobre ' . $vehiculo->marca . ' ' . $vehiculo->modelo . '.',
        ];
    }
}
