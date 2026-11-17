-- Verificación a mano de la lógica en el servidor (MariaDB 10.4, phpMyAdmin, pestaña SQL).
-- Se corre DESPUÉS de "php artisan migrate". Ejecutar de a un bloque por vez y mirar el resultado.

-- 1) Los tres objetos existen -------------------------------------------------------------
SHOW FULL TABLES WHERE Table_type = 'VIEW';            -- debe aparecer vista_ventas_cerradas
SHOW TRIGGERS WHERE `Table` = 'leads';                 -- debe aparecer trg_leads_estado_no_retrocede (BEFORE UPDATE)
SHOW PROCEDURE STATUS WHERE Db = DATABASE();           -- debe aparecer sp_ventas_por_mes

-- 2) Vista: una fila por venta cerrada ----------------------------------------------------
SELECT * FROM vista_ventas_cerradas ORDER BY fecha_cierre DESC;
-- Control: la cantidad de filas tiene que ser igual a la de leads cerrados
SELECT (SELECT COUNT(*) FROM vista_ventas_cerradas) AS en_la_vista,
       (SELECT COUNT(*) FROM leads WHERE estado = 'cerrado') AS leads_cerrados;

-- 3) Procedimiento: ventas y monto por mes (solo aparecen los meses que tuvieron ventas) --
CALL sp_ventas_por_mes(6);
CALL sp_ventas_por_mes(12);

-- 4) Trigger: la base no deja que una consulta vuelva a un estado anterior ------------------
-- 4a) Elegir una consulta que NO esté en "nuevo" y anotar su id y su estado
SELECT id, estado FROM leads WHERE estado <> 'nuevo' ORDER BY id LIMIT 1;
-- 4b) Intentar retrocederla a "nuevo" (cambiar 1 por el id anotado). DEBE DAR ERROR:
--     #1644 - Una consulta no puede volver a un estado anterior.
UPDATE leads SET estado = 'nuevo' WHERE id = 1;
-- 4c) Comprobar que sigue igual que en 4a (el cambio fue rechazado)
SELECT id, estado FROM leads WHERE id = 1;
-- 4d) Un cambio hacia adelante o sin cambio de estado NO da error (no se ejecuta acá para no alterar datos):
--     UPDATE leads SET estado = estado WHERE id = 1;
