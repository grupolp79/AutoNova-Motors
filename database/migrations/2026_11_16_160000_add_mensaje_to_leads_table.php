<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RF-03: el cliente puede escribir un mensaje al consultar por un vehículo.
     * Es opcional (nullable): los leads que ya existen quedan con el mensaje vacío.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->text('mensaje')->nullable()->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('mensaje');
        });
    }
};
