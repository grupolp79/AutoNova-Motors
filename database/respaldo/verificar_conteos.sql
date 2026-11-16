-- Verificación de la base de datos del proyecto (sirve para MariaDB/MySQL y para SQLite).
-- Se corre ANTES de hacer un respaldo y DESPUÉS de restaurarlo: los resultados tienen que ser iguales.

-- 1) Cantidad de filas por tabla
SELECT 'users' AS tabla, COUNT(*) AS filas FROM users
UNION ALL SELECT 'vehiculos', COUNT(*) FROM vehiculos
UNION ALL SELECT 'vehiculo_fotos', COUNT(*) FROM vehiculo_fotos
UNION ALL SELECT 'leads', COUNT(*) FROM leads
UNION ALL SELECT 'interacciones', COUNT(*) FROM interacciones
UNION ALL SELECT 'turnos', COUNT(*) FROM turnos
UNION ALL SELECT 'resenas', COUNT(*) FROM resenas
UNION ALL SELECT 'favoritos', COUNT(*) FROM favoritos
UNION ALL SELECT 'precios_referencia', COUNT(*) FROM precios_referencia;

-- 2) Control de contenido: totales que cambian si se pierde o se altera algún dato
SELECT 'suma de precios de vehiculos' AS control, ROUND(SUM(precio), 2) AS valor FROM vehiculos
UNION ALL SELECT 'leads cerrados', COUNT(*) FROM leads WHERE estado = 'cerrado'
UNION ALL SELECT 'vehiculos vendidos', COUNT(*) FROM vehiculos WHERE estado = 'vendido'
UNION ALL SELECT 'usuarios admin', COUNT(*) FROM users WHERE rol = 'admin';

-- 3) Integridad: cada fila tiene que valer 0 (registros que apuntan a algo que no existe)
SELECT 'leads sin vehiculo' AS control, COUNT(*) AS problemas
  FROM leads l LEFT JOIN vehiculos v ON v.id = l.vehiculo_id WHERE v.id IS NULL
UNION ALL SELECT 'leads sin usuario', COUNT(*)
  FROM leads l LEFT JOIN users u ON u.id = l.usuario_id WHERE u.id IS NULL
UNION ALL SELECT 'interacciones sin lead', COUNT(*)
  FROM interacciones i LEFT JOIN leads l ON l.id = i.lead_id WHERE l.id IS NULL
UNION ALL SELECT 'fotos sin vehiculo', COUNT(*)
  FROM vehiculo_fotos f LEFT JOIN vehiculos v ON v.id = f.vehiculo_id WHERE v.id IS NULL
UNION ALL SELECT 'turnos sin vehiculo o usuario', COUNT(*)
  FROM turnos t LEFT JOIN vehiculos v ON v.id = t.vehiculo_id LEFT JOIN users u ON u.id = t.usuario_id
  WHERE v.id IS NULL OR u.id IS NULL
UNION ALL SELECT 'resenas sin vehiculo o usuario', COUNT(*)
  FROM resenas r LEFT JOIN vehiculos v ON v.id = r.vehiculo_id LEFT JOIN users u ON u.id = r.usuario_id
  WHERE v.id IS NULL OR u.id IS NULL
UNION ALL SELECT 'favoritos sin vehiculo o usuario', COUNT(*)
  FROM favoritos x LEFT JOIN vehiculos v ON v.id = x.vehiculo_id LEFT JOIN users u ON u.id = x.usuario_id
  WHERE v.id IS NULL OR u.id IS NULL;
