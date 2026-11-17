<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices para que las consultas sigan rápidas cuando las tablas crezcan (Hito 3 de Base de Datos).
 *
 * Criterio: se indexa solo lo que las pantallas de la aplicación realmente consultan
 * (filtros y ordenamientos del catálogo, bandeja de consultas, reporte de ventas).
 * Cada índice acelera lecturas pero hace un poco más lento insertar y actualizar, por eso
 * no se indexan columnas de poca variedad por sí solas (por ejemplo "destacado", que solo
 * tiene dos valores) ni todas las columnas "por las dudas".
 *
 * Es una migración solo aditiva: no cambia columnas ni datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehiculos', function (Blueprint $table) {
            // Filtro por marca y búsqueda por marca + modelo; también el cruce con precios_referencia
            $table->index(['marca', 'modelo'], 'idx_vehiculos_marca_modelo');
            // Catálogo: filtro por estado (disponible/reservado/vendido) y orden por precio
            $table->index(['estado', 'precio'], 'idx_vehiculos_estado_precio');
            // Filtro por rango de años
            $table->index('anio', 'idx_vehiculos_anio');
            // Filtro por kilometraje (0 km / usado) y orden "menos km"
            $table->index('kilometraje', 'idx_vehiculos_kilometraje');
        });

        Schema::table('vehiculo_fotos', function (Blueprint $table) {
            // Las fotos de un vehículo se piden siempre ordenadas
            $table->index(['vehiculo_id', 'orden'], 'idx_fotos_vehiculo_orden');
        });

        Schema::table('leads', function (Blueprint $table) {
            // Bandeja de consultas, contador de "nuevas" y reporte de ventas (estado = cerrado en un rango de fechas)
            $table->index(['estado', 'updated_at'], 'idx_leads_estado_actualizado');
            // Regla "una sola consulta abierta por cliente y vehículo"
            $table->index(['usuario_id', 'vehiculo_id', 'estado'], 'idx_leads_cliente_vehiculo_estado');
        });

        Schema::table('interacciones', function (Blueprint $table) {
            // Historial de contactos de una consulta, ordenado por fecha
            $table->index(['lead_id', 'fecha'], 'idx_interacciones_lead_fecha');
        });

        // Tablas de módulos que todavía no tienen pantalla: se dejan listas para sus consultas previstas
        Schema::table('turnos', function (Blueprint $table) {
            // Agenda: turnos pendientes/confirmados ordenados por fecha
            $table->index(['estado', 'fecha_hora'], 'idx_turnos_estado_fecha');
        });

        Schema::table('resenas', function (Blueprint $table) {
            // Mostrar las reseñas aprobadas de un vehículo
            $table->index(['vehiculo_id', 'estado_moderacion'], 'idx_resenas_vehiculo_moderacion');
        });
    }

    public function down(): void
    {
        // MariaDB exige un índice sobre cada clave foránea. Al crear los índices compuestos de arriba,
        // pudo haber descartado el índice simple que usaba la clave foránea. Antes de borrar los
        // compuestos se deja uno simple, para que la reversión no falle.
        foreach ([
            'vehiculo_fotos' => 'vehiculo_id',
            'leads' => 'usuario_id',
            'interacciones' => 'lead_id',
            'resenas' => 'vehiculo_id',
        ] as $tabla => $columna) {
            Schema::table($tabla, fn (Blueprint $table) => $table->index($columna, "{$tabla}_{$columna}_index"));
        }

        Schema::table('resenas', fn (Blueprint $table) => $table->dropIndex('idx_resenas_vehiculo_moderacion'));
        Schema::table('turnos', fn (Blueprint $table) => $table->dropIndex('idx_turnos_estado_fecha'));
        Schema::table('interacciones', fn (Blueprint $table) => $table->dropIndex('idx_interacciones_lead_fecha'));
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('idx_leads_cliente_vehiculo_estado');
            $table->dropIndex('idx_leads_estado_actualizado');
        });
        Schema::table('vehiculo_fotos', fn (Blueprint $table) => $table->dropIndex('idx_fotos_vehiculo_orden'));
        Schema::table('vehiculos', function (Blueprint $table) {
            $table->dropIndex('idx_vehiculos_kilometraje');
            $table->dropIndex('idx_vehiculos_anio');
            $table->dropIndex('idx_vehiculos_estado_precio');
            $table->dropIndex('idx_vehiculos_marca_modelo');
        });
    }
};
