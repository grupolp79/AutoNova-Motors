# Historia del proyecto — AutoNova Motors (Servicio Automotriz)
Grupo LP: Lautaro Da Silva y Thiago Olmos, liderazgo compartido. Docente de Gestión de Proyecto y Registro Documental: Carin Molina.
Reconstrucción a partir de los documentos entregados, el tablero del 16/11 y los informes de Programación y BD del 17/11. Solo consta lo que figura en esas fuentes.

## Hito 0 — Punto de partida (inicios de septiembre)
- Semana 1 (01-07/09): se definió y priorizó el alcance ampliado: 8 funcionalidades sobre el caso AutoNova Motors, una automotora ficticia de Montevideo.
- Se entregó el Hito 0 de Programación y BD (v1.0) con stack Laravel + Blade.
- Reuniones de equipo documentadas del 02/09 al 12/10.

## Hito 1 — Gestión y planificación (entregado 25/09; v2 el 07/10)
- Ventana prevista 14-18/09; se entregó el 25/09 (+7 días).
- Se resolvió el conflicto con el scaffold institucional (React + JWT + Docker + Nginx): el 25/09 la docente confirmó el stack Laravel + Blade. Riesgo R-05 cerrado.
- El documento incluyó stakeholders, 11 riesgos, factibilidad, ESRE con 14 requerimientos, cronograma por sprints y gestión documental.
- El 07/10 se reenvió la versión 2 con las correcciones de la docente.

## Hito 2 — Diseño y trazabilidad (entregado 20/10)
- Cierre previsto del Sprint 2: 16/10; entrega real 20/10 (+4 días).
- Se agregó el seguimiento, la matriz de riesgos actualizada, las métricas, los diagramas UML, el plan de pruebas (PU-01 a PU-13) y la selección de características ISO 25010.
- El tablero del 19/10 mostraba las tareas del Sprint 1 y 2 (ADR, endpoints y UML) todavía abiertas, y el Sprint 3 sin avance.
- **Decisión del 16/11:** el Hito 2 no se reenvía; sus correcciones se incorporan al Hito 3. La defensa oral del Hito 2 queda pendiente y se coordina con la del Hito 3.

## Hito 3 — Entrega final (Gestión: 17/11; plan completo: 11/12)
- Entre el 22/10 y el 16/11 el código no tuvo cambios y el tablero solo cambió en la tarjeta #7. La reunión semanal con acta acordada el 19/10 no se sostuvo.
- Al 16/11 el sistema estaba en el nivel del Hito 2: 3 de 14 requerimientos funcionaban. Desvío promedio de las tareas abiertas: 27,0 días (ADR +37, endpoints +33, UML +31, Hito 1 +7).
- Del 16 al 17/11 se construyó el resto del núcleo: consultas y su gestión, cierre de venta, historial de contactos, precios de referencia, favoritos, registro e inicio de sesión con roles, reporte de ventas, listado de usuarios y descripción en cada vehículo.
- Estado final al 17/11: **11 de 14 requerimientos funcionan**; no se construyeron RF-08 (comparador), RF-13 (turnos) y RF-14 (reseñas con moderación). 391 pruebas automáticas aprobadas en la PC del equipo, 25 rutas y 15 migraciones.
- Base de datos (MariaDB 10.4.32): 10 índices, una vista, un disparador y un procedimiento almacenado verificados; respaldo restaurado con conteos idénticos.
- Se aplicó el formato de carátula y la estructura de manuales de la docente. Los manuales de Usuario (19 págs.), Técnico de Programación (11) y Técnico de BD (30) los elaboró el equipo de Programación y BD.

## Lecciones aprendidas
1. Sin reuniones ni registro aparecieron desvíos de 4 a 37 días; la medida correctiva no se sostuvo.
2. El desarrollo se concentró al final: conviene repartirlo por sprints y probar cada módulo al cerrarlo.
3. Las pruebas y capturas deben hacerse en un único entorno de referencia (la PC del equipo).
4. Las observaciones de la docente se aplican completas antes de cada entrega.
5. El stack se confirmó casi cuatro semanas después del inicio; conviene confirmarlo en la primera semana.
