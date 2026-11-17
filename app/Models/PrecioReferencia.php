<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrecioReferencia extends Model
{
    // El nombre de la tabla no sigue la convención de Laravel (sería "precio_referencias")
    protected $table = 'precios_referencia';

    protected $fillable = ['marca', 'modelo', 'anio_desde', 'anio_hasta', 'precio_referencia'];

    /**
     * Filas de referencia que corresponden a un vehículo: misma marca y modelo, y el año del
     * vehículo dentro del rango [anio_desde, anio_hasta] (los dos extremos incluidos).
     * Si hubiera rangos superpuestos, primero va el que empieza más tarde (el más específico).
     */
    public function scopeParaVehiculo($query, Vehiculo $vehiculo)
    {
        return $query
            ->where('marca', $vehiculo->marca)
            ->where('modelo', $vehiculo->modelo)
            ->where('anio_desde', '<=', $vehiculo->anio)
            ->where('anio_hasta', '>=', $vehiculo->anio)
            ->orderByDesc('anio_desde')
            ->orderBy('id');
    }

    /** Búsqueda por texto: cada palabra tiene que estar en la marca o en el modelo. */
    public function scopeBuscar($query, ?string $texto)
    {
        foreach (preg_split('/\s+/', trim((string) $texto), -1, PREG_SPLIT_NO_EMPTY) as $palabra) {
            // Se escapan % y _ para que se busquen como texto y no como comodines
            $patron = '%' . addcslashes($palabra, '%_\\') . '%';
            $query->where(fn ($sub) => $sub->where('marca', 'like', $patron)->orWhere('modelo', 'like', $patron));
        }

        return $query;
    }
}
