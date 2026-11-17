<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lógica que vive en el servidor de base de datos (Hito 3 de Base de Datos).
 *
 *  1. Vista  vista_ventas_cerradas         -> una fila por venta cerrada, con cliente, vehículo y monto.
 *  2. Trigger trg_leads_estado_no_retrocede -> la base rechaza que una consulta vuelva a un estado anterior
 *                                              (red de seguridad: la aplicación ya avanza de a un paso).
 *  3. Procedimiento sp_ventas_por_mes(n)    -> ventas y monto por mes, calculado dentro de MariaDB.
 *
 * La vista y el trigger existen en MariaDB/MySQL y en SQLite (con la sintaxis de cada motor), así las
 * pruebas automáticas (SQLite en memoria) verifican la misma regla. El procedimiento usa funciones de
 * fecha de MariaDB, por eso solo se crea ahí; se prueba a mano con database/sql/verificar_servidor.sql.
 *
 * La aplicación sigue calculando el reporte con Eloquent (Lead::ventasPorMes); estos objetos son una
 * segunda vía de consulta y una protección de integridad, no reemplazan al código de la aplicación.
 */
return new class extends Migration
{
    public function up(): void
    {
        $motor = DB::getDriverName();

        // --- 1. Vista (misma sintaxis en ambos motores) ---
        DB::statement('DROP VIEW IF EXISTS vista_ventas_cerradas');
        DB::statement(<<<'SQL'
            CREATE VIEW vista_ventas_cerradas AS
            SELECT l.id         AS lead_id,
                   l.updated_at AS fecha_cierre,
                   u.id         AS cliente_id,
                   u.name       AS cliente,
                   v.id         AS vehiculo_id,
                   v.marca      AS marca,
                   v.modelo     AS modelo,
                   v.anio       AS anio,
                   v.precio     AS monto
            FROM leads l
            INNER JOIN users u ON u.id = l.usuario_id
            INNER JOIN vehiculos v ON v.id = l.vehiculo_id
            WHERE l.estado = 'cerrado'
            SQL);

        // --- 2. Trigger ---
        DB::statement('DROP TRIGGER IF EXISTS trg_leads_estado_no_retrocede');

        if (in_array($motor, ['mysql', 'mariadb'], true)) {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER trg_leads_estado_no_retrocede
                BEFORE UPDATE ON leads
                FOR EACH ROW
                BEGIN
                    IF (CASE NEW.estado WHEN 'nuevo' THEN 1 WHEN 'contactado' THEN 2 WHEN 'negociando' THEN 3 ELSE 4 END)
                     < (CASE OLD.estado WHEN 'nuevo' THEN 1 WHEN 'contactado' THEN 2 WHEN 'negociando' THEN 3 ELSE 4 END)
                    THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Una consulta no puede volver a un estado anterior.';
                    END IF;
                END
                SQL);
        } elseif ($motor === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER trg_leads_estado_no_retrocede
                BEFORE UPDATE OF estado ON leads
                FOR EACH ROW
                WHEN (CASE NEW.estado WHEN 'nuevo' THEN 1 WHEN 'contactado' THEN 2 WHEN 'negociando' THEN 3 ELSE 4 END)
                   < (CASE OLD.estado WHEN 'nuevo' THEN 1 WHEN 'contactado' THEN 2 WHEN 'negociando' THEN 3 ELSE 4 END)
                BEGIN
                    SELECT RAISE(ABORT, 'Una consulta no puede volver a un estado anterior.');
                END
                SQL);
        }

        // --- 3. Procedimiento almacenado (solo MariaDB/MySQL) ---
        if (in_array($motor, ['mysql', 'mariadb'], true)) {
            DB::unprepared('DROP PROCEDURE IF EXISTS sp_ventas_por_mes');
            DB::unprepared(<<<'SQL'
                CREATE PROCEDURE sp_ventas_por_mes(IN p_meses INT)
                BEGIN
                    SELECT DATE_FORMAT(fecha_cierre, '%Y-%m') AS mes,
                           COUNT(*)                           AS ventas,
                           SUM(monto)                         AS monto
                    FROM vista_ventas_cerradas
                    WHERE fecha_cierre >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL p_meses MONTH)
                    GROUP BY DATE_FORMAT(fecha_cierre, '%Y-%m')
                    ORDER BY mes DESC;
                END
                SQL);
        }
    }

    public function down(): void
    {
        $motor = DB::getDriverName();

        if (in_array($motor, ['mysql', 'mariadb'], true)) {
            DB::unprepared('DROP PROCEDURE IF EXISTS sp_ventas_por_mes');
        }

        DB::statement('DROP TRIGGER IF EXISTS trg_leads_estado_no_retrocede');
        DB::statement('DROP VIEW IF EXISTS vista_ventas_cerradas');
    }
};
