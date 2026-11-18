# Diagramas de secuencia UML — AutoNova Motors

> Fuente: `routes/web.php`, `AuthController`, `VehiculoController`, `ConsultaController`, `FavoritoController`, `ReporteController`, `EnsureRole` y `Lead` en `main` (commit 326cc50).
> Cada diagrama sigue el código tal como está, incluidos los casos de error.

## S1. Inicio de sesión (RF de autenticación)

```mermaid
sequenceDiagram
    actor U as Usuario
    participant R as Rutas (guest, throttle 5/min)
    participant A as AuthController
    participant Au as Auth (Laravel)
    participant S as Sesión

    U->>R: POST /login (email, password, recordarme)
    R->>A: store(request)
    A->>A: validar email requerido y válido, password requerido
    A->>Au: attempt(credenciales, recordarme)
    alt credenciales correctas
        Au-->>A: true
        A->>S: regenerate() (evita fijación de sesión)
        A-->>U: redirect intended o vehiculos.index + mensaje de bienvenida
    else credenciales incorrectas
        Au-->>A: false
        A-->>U: volver con error en email, conserva el correo
    end
```

Nota: el mismo mensaje cubre correo inexistente y contraseña errónea. El redirect no depende del rol.

## S2. Alta de vehículo (empleado o admin)

```mermaid
sequenceDiagram
    actor E as Empleado / Admin
    participant R as Rutas
    participant M as EnsureRole (rol)
    participant C as VehiculoController
    participant DB as Base de datos
    participant V as Vehiculo
    participant F as VehiculoFoto

    E->>R: POST /vehiculos
    R->>M: handle(rol: empleado, admin)
    alt sin sesión
        M-->>E: redirect a /login con mensaje de error
    else rol no permitido
        M-->>E: 403
    else rol permitido
        M->>C: store(request)
        C->>C: validar(request): URLs de fotos desde textarea, reglas de campos
        alt es admin
            C->>C: destacado desde el checkbox
        end
        C->>DB: DB::transaction (inicio)
        C->>V: create(datos)
        loop cada URL en orden
            C->>F: create(ruta = URL, orden = índice)
        end
        C->>DB: commit
        C-->>E: redirect a vehiculos.show + mensaje de éxito
    end
```

Nota: las fotos son URLs `http(s)`, no hay subida de archivos. Si la validación falla, Laravel devuelve al formulario con los errores.

## S3. Consulta de un cliente y avance de estado (RF-05 y RF-06)

```mermaid
sequenceDiagram
    actor Cl as Cliente
    actor Em as Empleado / Admin
    participant C as ConsultaController
    participant L as Lead
    participant V as Vehiculo
    participant DB as Base de datos

    Cl->>C: POST /vehiculos/{id}/consultar (mensaje)
    C->>V: ¿estado = vendido?
    alt vendido
        C-->>Cl: redirect a vehiculos.show con error
    else disponible o reservado
        C->>L: ¿existe lead del cliente para este vehículo no cerrado?
        alt ya existe
            C-->>Cl: redirect a consultas.mias con error
        else no existe
            C->>C: validar mensaje (opcional, máx. 500)
            C->>L: create(usuario_id, vehiculo_id, estado = nuevo, mensaje)
            C-->>Cl: redirect a consultas.mias con éxito
        end
    end

    Em->>C: PATCH /consultas/{id}/estado
    C->>L: avanzar()
    alt lead ya cerrado
        L-->>C: "La consulta ya está cerrada."
    else abierto
        L->>DB: transacción
        L->>V: lockForUpdate().findOrFail(vehiculo_id)
        alt vehículo vendido
            L-->>C: "El vehículo ya figura como vendido…"
        else
            L->>L: estado = siguiente (nuevo → contactado → negociando → cerrado)
            opt siguiente = negociando y vehículo disponible
                L->>V: estado = reservado
            end
            opt siguiente = cerrado
                L->>V: estado = vendido
            end
            L->>DB: commit
            L-->>C: null
        end
    end
    C-->>Em: redirect a consultas.show (éxito o error)
```

Nota: el trigger `trg_leads_estado_no_retrocede` de la base rechaza cualquier actualización que haga retroceder el estado, como segunda barrera a nivel de datos.

## S4. Reporte de ventas por mes (solo admin)

```mermaid
sequenceDiagram
    actor Ad as Admin
    participant M as EnsureRole (rol:admin)
    participant C as ReporteController
    participant L as Lead
    participant DB as Base de datos

    Ad->>M: GET /reportes/ventas?meses=N
    M->>C: ventas(request)
    C->>C: meses = N si está en 3, 6 o 12; si no, 6
    C->>L: ventasPorMes(meses)
    L->>DB: leads cerrados entre desde y fin del último mes (con vehiculo.id, precio)
    DB-->>L: filas
    L->>L: agrupar por mes, sumar montos, calcular variaciones
    L-->>C: colección de filas
    C->>C: invertir (mes más nuevo primero), actual = fila 0, anterior = fila 1
    C-->>Ad: vista reportes.ventas
```

Hallazgos para revisar con Programación y Base de Datos:

- `ventasPorMes()` calcula en PHP con una consulta Eloquent. **No llama al procedimiento `sp_ventas_por_mes` ni a la vista `vista_ventas_cerradas`**, que existen en la migración de lógica en servidor. Si la documentación dice que el reporte usa el procedimiento, hay que corregir una de las dos cosas.
- Usa `updated_at` como fecha de venta: si se edita un lead cerrado, la venta puede cambiar de mes.
- El monto usa el precio actual del vehículo, no el precio al momento de la venta.
