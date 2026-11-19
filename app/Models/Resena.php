<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Reseña de un cliente sobre un vehículo que compró (RF-14).
 * Nace 'pendiente' y el Admin la aprueba o la rechaza. En el detalle del vehículo solo se ven las aprobadas.
 */
class Resena extends Model
{
    const MODERACION = ['pendiente' => 'Pendiente', 'aprobada' => 'Aprobada', 'rechazada' => 'Rechazada'];

    // Largo permitido del texto (se valida en ResenaController)
    const TEXTO_MIN = 10;
    const TEXTO_MAX = 500;

    protected $fillable = ['usuario_id', 'vehiculo_id', 'texto', 'estado_moderacion'];

    public function cliente()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function vehiculo()
    {
        return $this->belongsTo(Vehiculo::class);
    }

    /** Solo las que se pueden mostrar al público: Resena::aprobadas()->... */
    public function scopeAprobadas($query)
    {
        return $query->where('estado_moderacion', 'aprobada');
    }

    /** Una reseña se modera una sola vez: después de aprobada o rechazada no cambia más. */
    public function estaPendiente(): bool
    {
        return $this->estado_moderacion === 'pendiente';
    }

    /** Primer nombre del cliente, lo único que se muestra en público (por privacidad). */
    public function primerNombre(): string
    {
        return Str::before(trim($this->cliente->name) . ' ', ' ');
    }
}
