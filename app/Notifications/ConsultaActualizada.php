<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Notifications\Notification;

/**
 * Aviso para el cliente dueño de una consulta: el equipo la hizo avanzar de estado
 * o registró un contacto (llamada, correo o visita).
 * El aviso nunca incluye el comentario del contacto: son notas internas del equipo.
 */
class ConsultaActualizada extends Notification
{
    // Cómo se nombra cada tipo de contacto en el texto del aviso (con su artículo)
    private const CONTACTOS = ['llamada' => 'una llamada', 'correo' => 'un correo', 'visita' => 'una visita'];

    private function __construct(
        private Lead $consulta,
        private string $tipo,
        private string $detalle,
    ) {
    }

    /** La consulta pasó a otro estado. */
    public static function porEstado(Lead $consulta): self
    {
        return new self($consulta, 'estado', $consulta->estado);
    }

    /** El equipo registró un contacto ($tipoContacto es una clave de Interaccion::TIPOS). */
    public static function porContacto(Lead $consulta, string $tipoContacto): self
    {
        return new self($consulta, 'contacto', $tipoContacto);
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $vehiculo = $this->consulta->vehiculo;
        $auto = $vehiculo->marca . ' ' . $vehiculo->modelo;

        if ($this->tipo === 'estado') {
            $estado = Lead::ESTADOS[$this->detalle];

            return [
                'tipo' => 'estado_consulta',
                'lead_id' => $this->consulta->id,
                'estado' => $this->detalle,
                'titulo' => 'Tu consulta pasó a ' . $estado,
                'texto' => 'Tu consulta por ' . $auto . ' ahora está en estado ' . $estado . '.',
            ];
        }

        return [
            'tipo' => 'contacto_consulta',
            'lead_id' => $this->consulta->id,
            'contacto' => $this->detalle,
            'titulo' => 'El equipo se comunicó con vos',
            'texto' => 'Registramos ' . self::CONTACTOS[$this->detalle] . ' por tu consulta sobre ' . $auto . '.',
        ];
    }
}
