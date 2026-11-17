<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mini descripción del vehículo para el catálogo y el detalle (hasta 500 caracteres, lo controla
     * la validación del formulario). Es opcional (nullable): los vehículos que ya existen quedan vacíos
     * y la vista muestra un texto armado con sus datos (Vehiculo::resumen).
     */
    public function up(): void
    {
        Schema::table('vehiculos', function (Blueprint $table) {
            $table->text('descripcion')->nullable()->after('combustible');
        });
    }

    public function down(): void
    {
        Schema::table('vehiculos', function (Blueprint $table) {
            $table->dropColumn('descripcion');
        });
    }
};
