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
| Lenguaje | PHP 8.2 o superior |
| Framework | Laravel 12 con plantillas Blade |
| Base de datos | MariaDB 10.4 (la de XAMPP), compatible con MySQL |
| Interfaz | Bootstrap 5.3 |
| Pruebas | PHPUnit 11 (sobre SQLite en memoria) |
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

Requisitos: XAMPP (PHP 8.2 o superior y MariaDB/MySQL) y Composer.

```
composer install
copy .env.example .env
php artisan key:generate
```

1. Crear una base vacía en phpMyAdmin (por ejemplo `autonova`, cotejamiento `utf8mb4_unicode_ci`).
2. Abrir `.env` y reemplazar la configuración de base de datos: `.env.example` trae SQLite por defecto, así que hay que indicar MySQL.

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=autonova
DB_USERNAME=root
DB_PASSWORD=
```

3. Crear las tablas y cargar los datos de ejemplo (borra todo lo que haya en esa base) y levantar la aplicación:

```
php artisan migrate:fresh --seed
php artisan serve
```

La aplicación queda en http://127.0.0.1:8000.

## Acceso y roles

El catálogo es público. Para gestionar vehículos hay que iniciar sesión (`/login`). Los permisos dependen del rol:

| Rol | Qué puede hacer |
|---|---|
| Visitante (sin sesión) y Cliente | Ver el catálogo, usar los filtros y ver el detalle de cada vehículo |
| Empleado | Lo anterior, más dar de alta y modificar vehículos |
| Admin | Lo anterior, más eliminar vehículos sin historial, marcar vehículos como destacados y ver el reporte de ventas (`/reportes/ventas`) |

Cuentas de ejemplo (las crea `database/seeders/UsuarioSeeder.php`, que también indica la contraseña de prueba): `admin@autonova.test`, `empleado1@autonova.test` y `cliente1@correo.test`.

## Pruebas

```
php artisan test
```

Las pruebas usan una base SQLite en memoria, separada de la base de desarrollo, y el proyecto impide que corran contra otra base. Requieren la extensión `pdo_sqlite` de PHP (se comprueba con `php -m`; si falta, se activa en `php.ini`).

## Respaldo de la base de datos

El procedimiento de respaldo y recuperación está en el Manual Técnico (parte de Base de Datos). El archivo `database/respaldo/verificar_conteos.sql` sirve para comprobar que una base restaurada tiene los mismos datos que la original.

## Documentación y seguimiento

- Los entregables de cada hito se guardan en [`docs/hitos/`](docs/hitos/), una carpeta por materia.
- El seguimiento de tareas y sprints se lleva en la pestaña **Projects** de este repositorio.
- Cronograma: 14 semanas, del 01/09/2026 al 11/12/2026, en 6 sprints de dos semanas.
