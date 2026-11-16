<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehiculo extends Model
{
    use HasFactory;

    // Valores permitidos (clave) y texto que se muestra (valor).
    // Las claves tienen que coincidir EXACTAMENTE con los enum de la migración.
    const TRANSMISIONES = ['manual' => 'Manual', 'automatica' => 'Automática'];
    const COMBUSTIBLES = ['nafta' => 'Nafta', 'diesel' => 'Diésel', 'hibrido' => 'Híbrido', 'electrico' => 'Eléctrico'];
    const ESTADOS = ['disponible' => 'Disponible', 'reservado' => 'Reservado', 'vendido' => 'Vendido'];

    protected $fillable = [
        'marca', 'modelo', 'anio', 'precio', 'kilometraje',
        'transmision', 'combustible', 'estado', 'destacado',
    ];

    // destacado es tinyint(1) en la base: con el cast llega como true/false y no como 1/0
    protected $casts = ['destacado' => 'boolean'];

    // Opciones del select "Ordenar por". La clave '' es el orden por defecto (destacados primero).
    const ORDENES = [
        '' => 'Destacados primero',
        'recientes' => 'Más recientes',
        'precio_asc' => 'Precio: menor a mayor',
        'precio_desc' => 'Precio: mayor a menor',
        'km_asc' => 'Menos km',
        'anio_desc' => 'Año más nuevo',
    ];

    public function fotos()
    {
        return $this->hasMany(VehiculoFoto::class)->orderBy('orden');
    }

    // Consultas de clientes sobre este vehículo (FK vehiculo_id)
    public function leads()
    {
        return $this->hasMany(Lead::class);
    }

    public function turnos()
    {
        return $this->hasMany(Turno::class);
    }

    public function resenas()
    {
        return $this->hasMany(Resena::class);
    }

    /**
     * Scope de filtros del catálogo: se usa como Vehiculo::filtrar($filtros).
     * Cada when() agrega la condición solo si ese filtro vino.
     * Todos los valores van como bindings (?), nunca pegados al SQL.
     */
    public function scopeFiltrar($query, array $f)
    {
        return $query
            // Búsqueda: cada palabra tiene que aparecer en la marca, el modelo o el año.
            // LIKE no distingue mayúsculas porque la columna usa una collation "_ci" (case insensitive).
            ->when($f['q'] ?? null, function ($query, $texto) {
                foreach (preg_split('/\s+/', trim($texto)) as $palabra) {
                    // Se escapan % y _ para que se busquen como texto y no como comodines
                    $patron = '%' . addcslashes($palabra, '%_\\') . '%';
                    $query->where(function ($sub) use ($patron) {
                        $sub->where('marca', 'like', $patron)
                            ->orWhere('modelo', 'like', $patron)
                            ->orWhere('anio', 'like', $patron);
                    });
                }
            })
            // isset() en lugar de when($valor) porque 0 es un valor válido (ej. km_max = 0)
            ->when(isset($f['precio_min']), fn ($q) => $q->where('precio', '>=', $f['precio_min']))
            ->when(isset($f['precio_max']), fn ($q) => $q->where('precio', '<=', $f['precio_max']))
            ->when(isset($f['km_min']), fn ($q) => $q->where('kilometraje', '>=', $f['km_min']))
            ->when(isset($f['km_max']), fn ($q) => $q->where('kilometraje', '<=', $f['km_max']))
            // Condición: no hay columna, se deduce del kilometraje
            ->when(($f['condicion'] ?? null) === '0km', fn ($q) => $q->where('kilometraje', 0))
            ->when(($f['condicion'] ?? null) === 'usado', fn ($q) => $q->where('kilometraje', '>', 0))
            ->when(isset($f['anio_desde']), fn ($q) => $q->where('anio', '>=', $f['anio_desde']))
            ->when(isset($f['anio_hasta']), fn ($q) => $q->where('anio', '<=', $f['anio_hasta']))
            // Grupos de checkboxes: si no se marcó ninguno, el array está vacío y no filtra
            ->when($f['estado'] ?? [], fn ($q, $valores) => $q->whereIn('estado', $valores))
            ->when($f['marca'] ?? [], fn ($q, $valores) => $q->whereIn('marca', $valores))
            ->when($f['transmision'] ?? [], fn ($q, $valores) => $q->whereIn('transmision', $valores))
            ->when($f['combustible'] ?? [], fn ($q, $valores) => $q->whereIn('combustible', $valores))
            ->when($f['destacados'] ?? null, fn ($q) => $q->where('destacado', true));
    }

    /**
     * Scope de orden del catálogo. Al final se ordena por id para que la paginación sea estable
     * (si dos autos tienen el mismo precio, siempre salen en el mismo orden).
     */
    public function scopeOrdenar($query, ?string $orden)
    {
        match ($orden) {
            'recientes' => $query->orderByDesc('created_at'),
            'precio_asc' => $query->orderBy('precio'),
            'precio_desc' => $query->orderByDesc('precio'),
            'km_asc' => $query->orderBy('kilometraje'),
            'anio_desc' => $query->orderByDesc('anio'),
            // Sin orden elegido: destacados primero y después los más nuevos
            default => $query->orderByDesc('destacado'),
        };

        return $query->orderByDesc('id');
    }
}