<?php

namespace Tests\Concerns;

use App\Models\Lead;
use App\Models\Resena;
use App\Models\Turno;
use App\Models\User;
use App\Models\Vehiculo;
use Carbon\Carbon;

/**
 * Funciones para armar datos de prueba con valores conocidos (sin azar),
 * así cada test puede afirmar resultados exactos.
 */
trait ArmaDatos
{
    protected function vehiculo(array $datos = []): Vehiculo
    {
        return Vehiculo::factory()->create($datos + [
            'marca' => 'Toyota', 'modelo' => 'Corolla', 'anio' => 2020, 'precio' => 15000,
            'kilometraje' => 50000, 'transmision' => 'manual', 'combustible' => 'nafta',
            'estado' => 'disponible', 'destacado' => false,
        ]);
    }

    protected function admin(): User
    {
        return User::factory()->admin()->create();
    }

    protected function empleado(): User
    {
        return User::factory()->empleado()->create();
    }

    protected function cliente(): User
    {
        return User::factory()->cliente()->create();
    }

    protected function lead(Vehiculo $vehiculo, string $estado = 'nuevo', ?Carbon $fecha = null, ?User $cliente = null): Lead
    {
        $lead = new Lead([
            'usuario_id' => ($cliente ?? $this->cliente())->id,
            'vehiculo_id' => $vehiculo->id,
            'estado' => $estado,
        ]);

        if ($fecha) {
            // Si created_at/updated_at ya vienen cargados, Eloquent no los pisa al guardar
            $lead->forceFill(['created_at' => $fecha, 'updated_at' => $fecha]);
        }

        $lead->save();

        return $lead;
    }

    protected function turno(Vehiculo $vehiculo): Turno
    {
        return Turno::create([
            'usuario_id' => $this->cliente()->id,
            'vehiculo_id' => $vehiculo->id,
            'fecha_hora' => now()->addDays(3),
            'estado' => 'pendiente',
        ]);
    }

    protected function resena(Vehiculo $vehiculo): Resena
    {
        return Resena::create([
            'usuario_id' => $this->cliente()->id,
            'vehiculo_id' => $vehiculo->id,
            'texto' => 'Muy buena atención.',
            'estado_moderacion' => 'aprobada',
        ]);
    }

    /** Datos válidos del formulario de alta/edición; $cambios pisa los que se quieran probar. */
    protected function datosFormulario(array $cambios = []): array
    {
        return $cambios + [
            'marca' => 'Ford',
            'modelo' => 'Fiesta',
            'anio' => 2019,
            'precio' => 11500,
            'kilometraje' => 60000,
            'transmision' => 'manual',
            'combustible' => 'nafta',
            'estado' => 'disponible',
            'fotos' => "https://ejemplo.com/a.jpg\nhttps://ejemplo.com/b.jpg",
        ];
    }
}
