# Diagrama de clases UML — AutoNova Motors

> Fuente: código de `app/` en la rama `main` (commit 326cc50, 17/11/2026).
> Los atributos son los de `$fillable` de cada modelo; `id` y `timestamps` se omiten.
> GitHub renderiza este bloque Mermaid directamente.
> Trazabilidad: ISO/IEC/IEEE 29148 (RF ↔ clase), ISO/IEC 25010 (mantenibilidad: modularidad).

## 1. Modelo de dominio (Eloquent)

```mermaid
classDiagram
    direction LR

    class User {
        +String name
        +String email
        +String password
        +String rol
        +esAdmin() bool
        +esEmpleado() bool
        +puedeGestionar() bool
        +favoritos() BelongsToMany
        +consultas() HasMany
    }

    class Vehiculo {
        +String marca
        +String modelo
        +int anio
        +decimal precio
        +int kilometraje
        +String transmision
        +String combustible
        +String estado
        +bool destacado
        +String descripcion
        +TRANSMISIONES
        +COMBUSTIBLES
        +ESTADOS
        +DESCRIPCION_MAX = 500
        +fotos() HasMany
        +leads() HasMany
        +turnos() HasMany
        +resenas() HasMany
        +precioReferencia() PrecioReferencia
        +comparacionPrecio() array
        +scopeFiltrar(query, f)
        +scopeOrdenar(query, orden)
        +totalesPorEstado()$ array
    }

    class VehiculoFoto {
        +int vehiculo_id
        +String ruta
        +int orden
        +vehiculo() BelongsTo
    }

    class Lead {
        +int usuario_id
        +int vehiculo_id
        +String estado
        +String mensaje
        +ESTADOS
        +SIGUIENTE
        +cliente() BelongsTo
        +vehiculo() BelongsTo
        +interacciones() HasMany
        +estaCerrado() bool
        +siguienteEstado() String
        +avanzar() String
        +ventasPorMes(meses, hasta)$ Collection
    }

    class Interaccion {
        +int lead_id
        +String tipo
        +datetime fecha
        +String comentario
        +TIPOS
        +lead() BelongsTo
    }

    class Turno {
        +int usuario_id
        +int vehiculo_id
        +datetime fecha_hora
        +String estado
        +ESTADOS
        +cliente() BelongsTo
        +vehiculo() BelongsTo
    }

    class Resena {
        +int usuario_id
        +int vehiculo_id
        +String texto
        +String estado_moderacion
        +MODERACION
        +cliente() BelongsTo
        +vehiculo() BelongsTo
    }

    class PrecioReferencia {
        +String marca
        +String modelo
        +int anio_desde
        +int anio_hasta
        +decimal precio_referencia
        +scopeParaVehiculo(query, vehiculo)
        +scopeBuscar(query, texto)
    }

    User "1" --> "0..*" Lead : consultas (usuario_id)
    User "0..*" -- "0..*" Vehiculo : favoritos (tabla pivot)
    User "1" --> "0..*" Turno : usuario_id
    User "1" --> "0..*" Resena : usuario_id
    Vehiculo "1" *-- "0..*" VehiculoFoto : fotos
    Vehiculo "1" --> "0..*" Lead : leads
    Vehiculo "1" --> "0..*" Turno : turnos
    Vehiculo "1" --> "0..*" Resena : resenas
    Lead "1" *-- "0..*" Interaccion : interacciones
    Vehiculo ..> PrecioReferencia : compara precio
```

Notas de lectura:

- `Turno` y `Resena` existen como modelo y tabla, pero **RF-13 (turnos) y RF-14 (reseñas) no tienen controlador ni rutas**: están marcados como "no implementado".
- `PrecioReferencia` no define relaciones Eloquent: se vincula con `Vehiculo` por marca, modelo y rango de años (`scopeParaVehiculo`).
- `favoritos` es una relación muchos a muchos con la tabla `favoritos` (claves `usuario_id` y `vehiculo_id`).
- Los valores de `rol` (`cliente`, `empleado`, `admin`) se usan como cadenas; no hay constantes de rol en `User`.

## 2. Capa de aplicación (controladores y middleware)

```mermaid
classDiagram
    direction TB

    class Controller {
        <<abstract>>
    }
    class HasMiddleware {
        <<interface>>
        +middleware()$ array
    }
    class EnsureRole {
        <<middleware, alias rol>>
        +handle(request, next, roles) Response
    }

    class AuthController {
        +create()
        +store(request)
        +destroy(request)
    }
    class RegistroController {
        +create()
        +store(request)
    }
    class VehiculoController {
        +index(request)
        +create()
        +store(request)
        +show(vehiculo)
        +edit(vehiculo)
        +update(request, vehiculo)
        +destroy(vehiculo)
    }
    class ConsultaController {
        +store(request, vehiculo)
        +mias(request)
        +index(request)
        +show(consulta)
        +avanzar(consulta)
    }
    class InteraccionController {
        +store(consulta)
    }
    class FavoritoController {
        +index(request)
        +store(request, vehiculo)
        +destroy(request, vehiculo)
    }
    class PrecioReferenciaController {
        +index(request)
    }
    class ReporteController {
        -PERIODOS = 3, 6, 12
        +ventas(request)
    }
    class UsuarioController {
        +index()
    }

    Controller <|-- AuthController
    Controller <|-- RegistroController
    Controller <|-- VehiculoController
    Controller <|-- ConsultaController
    Controller <|-- InteraccionController
    Controller <|-- FavoritoController
    Controller <|-- PrecioReferenciaController
    Controller <|-- ReporteController
    Controller <|-- UsuarioController
    HasMiddleware <|.. VehiculoController
    HasMiddleware <|.. ConsultaController
    HasMiddleware <|.. FavoritoController
    EnsureRole <.. VehiculoController : rol:empleado,admin / rol:admin
    EnsureRole <.. ConsultaController : rol:cliente / rol:empleado,admin
    EnsureRole <.. FavoritoController : rol:cliente
    EnsureRole <.. ReporteController : rol:admin (ruta)
    EnsureRole <.. UsuarioController : rol:admin (ruta)

    AuthController ..> User
    RegistroController ..> User
    VehiculoController ..> Vehiculo
    VehiculoController ..> VehiculoFoto
    ConsultaController ..> Lead
    ConsultaController ..> Vehiculo
    InteraccionController ..> Interaccion
    FavoritoController ..> User
    PrecioReferenciaController ..> PrecioReferencia
    ReporteController ..> Lead
    UsuarioController ..> User
```

Verificado en el código: `VehiculoController`, `ConsultaController` y `FavoritoController` implementan `HasMiddleware`. Los métodos de `RegistroController`, `InteraccionController`, `PrecioReferenciaController` y `UsuarioController` salen de `routes/web.php` y las dependencias con modelos de esos cuatro controladores son **inferidas por nombre: revisar contra el código antes de entregar**.
