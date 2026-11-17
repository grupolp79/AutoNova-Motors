-- Prueba de rendimiento de los índices con 100.000 filas (MariaDB 10.4, phpMyAdmin, pestaña SQL).
-- Se trabaja sobre una COPIA de la tabla (vehiculos_volumen): los datos reales no se tocan.
-- Cada bloque se ejecuta por separado. Al final hay un bloque para borrar todo lo creado.

-- 1) Copia de la estructura (CREATE TABLE ... LIKE copia también los índices) -----------
DROP TABLE IF EXISTS vehiculos_volumen;
CREATE TABLE vehiculos_volumen LIKE vehiculos;
SHOW INDEX FROM vehiculos_volumen;                      -- tienen que verse los idx_vehiculos_*

-- 2) Tabla auxiliar con los dígitos 0 a 9 (10 x 10 x 10 x 10 x 10 = 100.000 combinaciones) --
DROP TABLE IF EXISTS digitos_aux;
CREATE TABLE digitos_aux (n TINYINT NOT NULL);
INSERT INTO digitos_aux (n) VALUES (0),(1),(2),(3),(4),(5),(6),(7),(8),(9);

-- 3) Cargar 100.000 vehículos de prueba (datos al azar) ----------------------------------
INSERT INTO vehiculos_volumen
    (marca, modelo, anio, precio, kilometraje, transmision, combustible, estado, destacado, created_at, updated_at)
SELECT ELT(1 + FLOOR(RAND() * 8), 'Toyota', 'Ford', 'Chevrolet', 'Volkswagen', 'Honda', 'Nissan', 'Renault', 'Peugeot'),
       CONCAT('Modelo ', 1 + FLOOR(RAND() * 40)),
       2005 + FLOOR(RAND() * 22),
       ROUND(5000 + RAND() * 45000, 2),
       FLOOR(RAND() * 250000),
       ELT(1 + FLOOR(RAND() * 2), 'manual', 'automatica'),
       ELT(1 + FLOOR(RAND() * 4), 'nafta', 'diesel', 'hibrido', 'electrico'),
       IF(RAND() < 0.7, 'vendido', IF(RAND() < 0.7, 'disponible', 'reservado')),
       IF(RAND() < 0.1, 1, 0),
       NOW(), NOW()
FROM digitos_aux a, digitos_aux b, digitos_aux c, digitos_aux d, digitos_aux e;

SELECT COUNT(*) AS filas FROM vehiculos_volumen;        -- debe dar 100000
ANALYZE TABLE vehiculos_volumen;                        -- actualiza las estadísticas que usa el optimizador

-- 4) Consulta del catálogo: disponibles entre 10.000 y 20.000 ordenados por precio -------
-- 4a) CON índices. Anotar: type, key, rows (EXPLAIN) y r_total_time_ms (ANALYZE)
EXPLAIN SELECT id, marca, modelo, precio FROM vehiculos_volumen
 WHERE estado = 'disponible' AND precio BETWEEN 10000 AND 20000 ORDER BY precio;
ANALYZE SELECT id, marca, modelo, precio FROM vehiculos_volumen
 WHERE estado = 'disponible' AND precio BETWEEN 10000 AND 20000 ORDER BY precio;

-- 4b) SIN índices (se los ignora a propósito). Anotar lo mismo y comparar con 4a
EXPLAIN SELECT id, marca, modelo, precio
  FROM vehiculos_volumen IGNORE INDEX (idx_vehiculos_marca_modelo, idx_vehiculos_estado_precio, idx_vehiculos_anio, idx_vehiculos_kilometraje)
 WHERE estado = 'disponible' AND precio BETWEEN 10000 AND 20000 ORDER BY precio;
ANALYZE SELECT id, marca, modelo, precio
  FROM vehiculos_volumen IGNORE INDEX (idx_vehiculos_marca_modelo, idx_vehiculos_estado_precio, idx_vehiculos_anio, idx_vehiculos_kilometraje)
 WHERE estado = 'disponible' AND precio BETWEEN 10000 AND 20000 ORDER BY precio;

-- 5) Búsqueda por marca y modelo ---------------------------------------------------------
-- 5a) CON índices
EXPLAIN SELECT id, anio, precio FROM vehiculos_volumen WHERE marca = 'Toyota' AND modelo = 'Modelo 7';
ANALYZE SELECT id, anio, precio FROM vehiculos_volumen WHERE marca = 'Toyota' AND modelo = 'Modelo 7';
-- 5b) SIN índices
EXPLAIN SELECT id, anio, precio
  FROM vehiculos_volumen IGNORE INDEX (idx_vehiculos_marca_modelo, idx_vehiculos_estado_precio, idx_vehiculos_anio, idx_vehiculos_kilometraje)
 WHERE marca = 'Toyota' AND modelo = 'Modelo 7';
ANALYZE SELECT id, anio, precio
  FROM vehiculos_volumen IGNORE INDEX (idx_vehiculos_marca_modelo, idx_vehiculos_estado_precio, idx_vehiculos_anio, idx_vehiculos_kilometraje)
 WHERE marca = 'Toyota' AND modelo = 'Modelo 7';

-- 6) Limpieza: borra la copia y la tabla auxiliar (los datos reales no se tocaron) --------
DROP TABLE IF EXISTS vehiculos_volumen;
DROP TABLE IF EXISTS digitos_aux;
