# AutoNova Motors — Servicio Automotriz

Sistema web de gestión para una automotora (caso de uso ficticio: **AutoNova Motors**, Montevideo).
Proyecto de Egreso de la Tecnicatura en Redes y Software — CETP-UTU, UTU Buceo.

**Grupo LP:** Lautaro Da Silva y Thiago Olmos.

## ¿Qué hace?

Centraliza en un solo sistema la operación comercial de una automotora de porte mediano:

- **Catálogo de vehículos** con estado (Disponible / Reservado / Vendido).
- **Gestión de leads**: seguimiento de clientes potenciales (Nuevo → Contactado → Negociando → Cerrado) con historial cronológico de interacciones.
- **Sugerencia de precios** de referencia a partir de una tabla manual.
- **Funcionalidades de ampliación**: filtros de búsqueda avanzados, comparador de vehículos, favoritos, destacados, turnos para probar un vehículo y reseñas con moderación.

Actores del sistema: **Cliente**, **Empleado** y **Admin**.

## Tecnologías

| Componente | Tecnología |
|---|---|
| Lenguaje | PHP 8.3 |
| Framework | Laravel 11 con plantillas Blade |
| Base de datos | MySQL 8.0 |
| Interfaz | Bootstrap 5.3 |
| Pruebas | PHPUnit |
| Control de versiones | Git / GitHub |
| Gestión del proyecto | GitHub Projects (Scrum/Kanban por sprints) |
| Servidor de producción | Ubuntu Server 24.04 LTS (DMZ) |
| Dominio interno | Windows Server 2022 (`autonova.local`) |

## Estructura del repositorio

```
.
├── README.md
├── docs/
│   ├── hitos/
│   │   ├── gestion-proyecto/        Gestión de Proyecto y Registro Documental
│   │   ├── programacion-bd/         Programación y Base de Datos
│   │   ├── redes-microsoft/         Tutoría Redes Microsoft
│   │   ├── posix/                   Tutoría POSIX
│   │   └── conectividad-seguridad/  Conectividad y Seguridad de Redes
│   ├── actas/                       Actas de reunión
│   ├── diagramas/                   UML, modelo entidad-relación, red
│   ├── adr/                         Decisiones técnicas (ADR)
│   └── tablero/                     Capturas del tablero de seguimiento
└── infra/
    ├── posix/                       Scripts del servidor POSIX
    └── microsoft/                   Scripts del servidor Windows
```

El código de la aplicación Laravel está en la raíz del repositorio (`app/`, `routes/`, `resources/views/`, `database/`, etc.).

## Cómo ejecutar el proyecto

Requisitos: XAMPP (PHP 8.2 o superior y MySQL 8) y Composer.

```
composer install
copy .env.example .env
php artisan key:generate
```

En `.env`, completar los datos de la base (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`) y después:

```
php artisan migrate:fresh --seed
php artisan serve
```

La aplicación queda en http://127.0.0.1:8000. Las cuentas de ejemplo las crea `database/seeders/UsuarioSeeder.php`.

## Documentación y seguimiento

- Los entregables de cada hito se guardan en [`docs/hitos/`](docs/hitos/), una carpeta por materia.
- El seguimiento de tareas y sprints se lleva en la pestaña **Projects** de este repositorio.
- Cronograma: 14 semanas, del 01/09/2026 al 11/12/2026, en 6 sprints de dos semanas.
