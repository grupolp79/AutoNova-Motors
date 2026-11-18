# Diagrama de componentes UML — AutoNova Motors

> Fuente: estructura de `app/`, `routes/`, `database/migrations/` y `resources/` en `main` (commit 326cc50), más los datos de red oficiales del proyecto.
> Mermaid no tiene un tipo "componentes" propio; se representa con un diagrama de bloques con subsistemas, que es la forma habitual en GitHub.

## 1. Componentes de la aplicación

```mermaid
flowchart LR
    subgraph Cliente["Navegador (cliente / empleado / admin)"]
        UI["Vistas Blade + Bootstrap 5.3.3"]
    end

    subgraph App["Aplicación Laravel 12.70.1 (PHP 8.2)"]
        direction TB
        RT["Rutas<br/>routes/web.php"]
        MW["Middleware<br/>guest, auth, throttle, rol (EnsureRole)"]
        CT["Controladores<br/>Auth, Registro, Vehiculo, Consulta,<br/>Interaccion, Favorito, PrecioReferencia,<br/>Reporte, Usuario"]
        MD["Modelos Eloquent<br/>User, Vehiculo, VehiculoFoto, Lead,<br/>Interaccion, Turno, Resena, PrecioReferencia"]
        VW["Vistas<br/>resources/views"]
        RT --> MW --> CT --> MD
        CT --> VW
    end

    subgraph BD["MariaDB 10.4.32"]
        direction TB
        TB1["Tablas de negocio (9)"]
        VT["Vista vista_ventas_cerradas"]
        TG["Trigger trg_leads_estado_no_retrocede"]
        SP["Procedimiento sp_ventas_por_mes<br/>(definido; el reporte actual no lo llama)"]
    end

    UI -- "HTTP / HTTPS" --> RT
    VW -- "HTML" --> UI
    MD -- "SQL (PDO)" --> TB1
    TB1 --- TG
    VT --- TB1
    SP --- VT

    subgraph Pruebas["Pruebas automáticas"]
        PU["PHPUnit 11.5.57<br/>391 tests (17/11/2026)"]
    end
    PU -. "ejecuta contra" .-> App
```

## 2. Despliegue previsto (plan, no implementado)

```mermaid
flowchart TB
    NAV["Navegador del usuario"]
    subgraph DMZ["DMZ - 172.16.10.10"]
        direction TB
        SW["serweb01 (Ubuntu 24.04)<br/>Servidor web + PHP + Laravel"]
    end
    DBX["Base de datos MariaDB<br/>(ubicación según el diseño de red de Conectividad)"]
    NAV -- "HTTPS" --> SW
    SW -- "SQL" --> DBX
```

Aclaración: serweb01 es el destino del plan de despliegue; **la aplicación no está desplegada ahí**. Hoy corre en XAMPP en la PC del equipo. Revisar con Redes (Pajares) dónde queda la base de datos antes de entregar este diagrama.

## 3. Dependencias entre componentes

| Componente | Depende de | Observación |
|---|---|---|
| Rutas | Middleware, controladores | Los roles se aplican en la ruta (reportes, usuarios) o dentro del controlador (`HasMiddleware`) |
| Controladores | Modelos, vistas | Sin capa de servicios: la lógica de negocio crítica está en `Lead::avanzar()` |
| Modelos | MariaDB | Relaciones Eloquent; el pivote `favoritos` une `User` y `Vehiculo` |
| Base de datos | Migraciones (15) | Incluye vista, trigger y procedimiento en `create_logica_en_servidor` |
