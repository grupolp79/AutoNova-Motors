<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * Foto de un vehículo. La columna 'ruta' tiene una de dos cosas:
 *   - una URL externa (https://...), cargada en el textarea del formulario o por las semillas;
 *   - una ruta relativa de una foto subida como archivo: /uploads/vehiculos/{id_vehiculo}/{nombre}
 *     (disco 'vehiculos' de config/filesystems.php, dentro de public/).
 */
class VehiculoFoto extends Model
{
    // Así empiezan las rutas de las fotos subidas (coincide con la 'url' del disco 'vehiculos')
    const PREFIJO_SUBIDAS = '/uploads/vehiculos/';

    protected $fillable = [
        'vehiculo_id', 'ruta', 'orden',
    ];

    public function vehiculo()
    {
        return $this->belongsTo(Vehiculo::class);
    }

    /** ¿Es una foto subida como archivo (y no una URL externa)? */
    public function esSubida(): bool
    {
        return str_starts_with($this->ruta, self::PREFIJO_SUBIDAS);
    }

    /** Ruta dentro del disco 'vehiculos' ("5/abc123.jpg"), o null si es una URL externa. */
    public function rutaEnDisco(): ?string
    {
        return $this->esSubida() ? substr($this->ruta, strlen(self::PREFIJO_SUBIDAS)) : null;
    }

    /**
     * Dirección para usar en <img src>: $foto->url.
     * Las subidas pasan por asset() para que anden aunque el sitio esté publicado en una subcarpeta;
     * las URLs externas quedan igual.
     */
    protected function url(): Attribute
    {
        return Attribute::get(fn () => $this->esSubida() ? asset(ltrim($this->ruta, '/')) : $this->ruta);
    }
}
