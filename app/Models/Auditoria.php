<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Registro de auditoría: quién hizo qué, sobre qué y cuándo (tabla 'auditoria').
 * Se escribe SOLO con Auditoria::registrar(...), que se llama desde el controlador donde pasa la acción.
 * Las filas no se modifican ni se borran desde la aplicación.
 * Nunca se guardan contraseñas, códigos ni tokens: solo un texto que describe lo que pasó.
 */
class Auditoria extends Model
{
    // El nombre en español no sigue la pluralización de Laravel
    protected $table = 'auditoria';

    // Solo tiene created_at (una fila de auditoría no se actualiza)
    const UPDATED_AT = null;

    // Acciones que se registran y su texto para la pantalla y el filtro
    const ACCIONES = [
        'vehiculo.crear' => 'Creó un vehículo',
        'vehiculo.editar' => 'Editó un vehículo',
        'vehiculo.eliminar' => 'Eliminó un vehículo',
        'vehiculo.descripcion' => 'Editó la descripción de un vehículo',
        'consulta.avanzar' => 'Avanzó una consulta',
        'consulta.contacto' => 'Registró un contacto',
        'turno.estado' => 'Cambió el estado de un turno',
        'resena.moderar' => 'Moderó una reseña',
        'usuario.crear' => 'Creó un usuario',
        'contrasena.recuperar' => 'Cambió su contraseña por recuperación',
    ];

    // Nombre de cada tipo de referencia para la pantalla
    const TIPOS = ['vehiculo' => 'Vehículo', 'consulta' => 'Consulta', 'turno' => 'Turno', 'resena' => 'Reseña', 'usuario' => 'Usuario'];

    // Clase del modelo => tipo que se guarda en referencia_tipo
    private const TIPO_DE_MODELO = [
        Vehiculo::class => 'vehiculo',
        Lead::class => 'consulta',
        Turno::class => 'turno',
        Resena::class => 'resena',
        User::class => 'usuario',
    ];

    protected $fillable = ['usuario_id', 'accion', 'detalle', 'referencia_tipo', 'referencia_id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /**
     * Único punto de entrada para registrar algo. Ejemplo:
     *   Auditoria::registrar('vehiculo.editar', 'Precio: U$S 15.000 → U$S 14.000.', $vehiculo);
     * $usuario: quién lo hizo. Si no se pasa, es el usuario con la sesión iniciada.
     */
    public static function registrar(string $accion, string $detalle, ?Model $sobre = null, ?User $usuario = null): self
    {
        return self::create([
            'usuario_id' => $usuario?->id ?? auth()->id(),
            'accion' => $accion,
            'detalle' => $detalle,
            'referencia_tipo' => $sobre ? self::TIPO_DE_MODELO[$sobre::class] : null,
            'referencia_id' => $sobre?->getKey(),
        ]);
    }

    /**
     * La auditoría solo se agrega: si alguien intenta editar o borrar una fila desde el código,
     * se corta con un error en lugar de cambiar el historial.
     */
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('El registro de auditoría no se puede modificar.'));
        static::deleting(fn () => throw new LogicException('El registro de auditoría no se puede borrar.'));
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
