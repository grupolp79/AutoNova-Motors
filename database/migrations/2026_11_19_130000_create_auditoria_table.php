<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de auditoría: quién hizo qué, sobre qué y cuándo.
 * Solo se agregan filas (Auditoria::registrar); la aplicación no las modifica ni las borra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditoria', function (Blueprint $table) {
            $table->id();
            // Quién hizo la acción. nullOnDelete: si algún día se borra el usuario, la fila de auditoría
            // se conserva (con usuario_id en NULL) y el borrado del usuario no se bloquea.
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            // Código corto de la acción, por ejemplo 'vehiculo.editar' (ver Auditoria::ACCIONES)
            $table->string('accion', 40);
            // Texto legible con lo que pasó. Nunca lleva contraseñas, códigos ni tokens.
            $table->text('detalle');
            // Sobre qué se hizo: tipo ('vehiculo', 'consulta', 'turno', 'resena', 'usuario') e id.
            // Sin clave foránea a propósito: si se elimina un vehículo, su historial de auditoría queda.
            $table->string('referencia_tipo', 30)->nullable();
            $table->unsignedBigInteger('referencia_id')->nullable();
            // Solo fecha de creación: una fila de auditoría no se modifica, no lleva updated_at
            $table->timestamp('created_at')->useCurrent();

            // La pantalla lista de más nueva a más vieja y filtra por usuario y por acción
            $table->index('created_at');
            $table->index('accion');
            $table->index(['referencia_tipo', 'referencia_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria');
    }
};
