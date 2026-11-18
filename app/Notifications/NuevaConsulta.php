<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Notifications\Notification;

/**
 * Aviso para el equipo (Empleado y Admin): un cliente envió una consulta nueva.
 * Se guarda solo en la base de datos (campanita); no se manda por correo.
 * No lleva ShouldQueue: se guarda en el momento, sin necesitar un proceso de colas en marcha.
 */
class NuevaConsulta extends Notification
{
    public function __construct(private Lead $consulta)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** Contenido guardado en notifications.data (no incluye el mensaje que escribió el cliente). */
    public function toArray(object $notifiable): array
    {
        $vehiculo = $this->consulta->vehiculo;

        return [
            'tipo' => 'nueva_consulta',
            'lead_id' => $this->consulta->id,
            'titulo' => 'Nueva consulta',
            'texto' => $this->consulta->cliente->name . ' consultó por ' . $vehiculo->marca . ' ' . $vehiculo->modelo . ' ' . $vehiculo->anio . '.',
        ];
    }
}
