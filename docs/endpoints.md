# Endpoints de AutoNova Motors

**Total de rutas del proyecto: 49** (salida de `php artisan route:list --except-vendor --json`, 2026-11-19).

Todas las rutas tienen el middleware `web` (sesión, cookies y protección CSRF). Cada ruta está en el grupo que corresponde a su middleware real:

| Grupo | Middleware que lo define | Rutas |
|---|---|---|
| Público | solo `web` | 7 |
| Sin sesión | `guest` | 8 |
| Con sesión | `auth`, o `rol:cliente,empleado,admin` (los tres roles) | 4 |
| Cliente | `rol:cliente` | 10 |
| Empleado y Admin | `rol:empleado,admin` | 12 |
| Solo Admin | `rol:admin` | 8 |
| **Total** | | **49** |

`rol:` es el middleware propio del proyecto (`app/Http/Middleware/EnsureRole.php`). `throttle:N,1` limita a N pedidos por minuto. Ninguna ruta quedó en «Otros».

## Público

| Método | URL | Nombre | Controlador@acción | Middleware |
|---|---|---|---|---|
| GET, HEAD, POST, PUT, PATCH, DELETE, OPTIONS | `/` | — | `Illuminate\Routing\RedirectController` (redirige a `/vehiculos`) | web |
| GET, HEAD | `/vehiculos` | vehiculos.index | VehiculoController@index | web |
| GET, HEAD | `/vehiculos/{vehiculo}` | vehiculos.show | VehiculoController@show | web |
| GET, HEAD | `/comparar` | comparador.index | ComparadorController@index | web |
| POST | `/comparar/{vehiculo}` | comparador.agregar | ComparadorController@agregar | web |
| POST | `/comparar/{id}/quitar` | comparador.quitar | ComparadorController@quitar | web |
| POST | `/comparar/vaciar` | comparador.vaciar | ComparadorController@vaciar | web |

## Sin sesión

| Método | URL | Nombre | Controlador@acción | Middleware |
|---|---|---|---|---|
| GET, HEAD | `/login` | login | AuthController@create | web, guest |
| POST | `/login` | login.store | AuthController@store | web, guest, throttle:5,1 |
| GET, HEAD | `/registro` | registro | RegistroController@create | web, guest |
| POST | `/registro` | registro.store | RegistroController@store | web, guest, throttle:6,1 |
| GET, HEAD | `/olvide-contrasena` | password.request | RecuperacionController@pedir | web, guest |
| POST | `/olvide-contrasena` | password.email | RecuperacionController@enviar | web, guest, throttle:5,1 |
| GET, HEAD | `/restablecer-contrasena` | password.reset | RecuperacionController@formulario | web, guest |
| POST | `/restablecer-contrasena` | password.update | RecuperacionController@guardar | web, guest, throttle:5,1 |

## Con sesión

| Método | URL | Nombre | Controlador@acción | Middleware |
|---|---|---|---|---|
| POST | `/logout` | logout | AuthController@destroy | web, auth |
| GET, HEAD | `/notificaciones` | notificaciones.index | NotificacionController@index | web, rol:cliente,empleado,admin |
| POST | `/notificaciones/leer-todas` | notificaciones.leer-todas | NotificacionController@leerTodas | web, rol:cliente,empleado,admin |
| POST | `/notificaciones/{id}/leer` | notificaciones.leer | NotificacionController@leer | web, rol:cliente,empleado,admin |

## Cliente

| Método | URL | Nombre | Controlador@acción | Middleware |
|---|---|---|---|---|
| POST | `/vehiculos/{vehiculo}/consultar` | consultas.store | ConsultaController@store | web, rol:cliente |
| GET, HEAD | `/mis-consultas` | consultas.mias | ConsultaController@mias | web, rol:cliente |
| GET, HEAD | `/mis-consultas/{consulta}/resena` | resenas.create | ResenaController@create | web, rol:cliente |
| POST | `/mis-consultas/{consulta}/resena` | resenas.store | ResenaController@store | web, rol:cliente |
| GET, HEAD | `/favoritos` | favoritos.index | FavoritoController@index | web, rol:cliente |
| POST | `/vehiculos/{vehiculo}/favorito` | favoritos.store | FavoritoController@store | web, rol:cliente |
| DELETE | `/vehiculos/{vehiculo}/favorito` | favoritos.destroy | FavoritoController@destroy | web, rol:cliente |
| POST | `/vehiculos/{vehiculo}/turno` | turnos.store | TurnoController@store | web, rol:cliente |
| GET, HEAD | `/mis-turnos` | turnos.mios | TurnoController@mios | web, rol:cliente |
| PATCH | `/mis-turnos/{turno}/cancelar` | turnos.cancelar | TurnoController@cancelar | web, rol:cliente |

## Empleado y Admin

| Método | URL | Nombre | Controlador@acción | Middleware |
|---|---|---|---|---|
| GET, HEAD | `/vehiculos/create` | vehiculos.create | VehiculoController@create | web, rol:empleado,admin |
| POST | `/vehiculos` | vehiculos.store | VehiculoController@store | web, rol:empleado,admin |
| GET, HEAD | `/vehiculos/{vehiculo}/edit` | vehiculos.edit | VehiculoController@edit | web, rol:empleado,admin |
| PUT, PATCH | `/vehiculos/{vehiculo}` | vehiculos.update | VehiculoController@update | web, rol:empleado,admin |
| PATCH | `/vehiculos/{vehiculo}/descripcion` | vehiculos.descripcion | VehiculoController@actualizarDescripcion | web, rol:empleado,admin |
| GET, HEAD | `/consultas` | consultas.index | ConsultaController@index | web, rol:empleado,admin |
| GET, HEAD | `/consultas/{consulta}` | consultas.show | ConsultaController@show | web, rol:empleado,admin |
| PATCH | `/consultas/{consulta}/estado` | consultas.avanzar | ConsultaController@avanzar | web, rol:empleado,admin |
| POST | `/consultas/{consulta}/interacciones` | consultas.interacciones.store | InteraccionController@store | web, rol:empleado,admin |
| GET, HEAD | `/precios-referencia` | precios.index | PrecioReferenciaController@index | web, rol:empleado,admin |
| GET, HEAD | `/turnos` | turnos.index | TurnoController@index | web, rol:empleado,admin |
| PATCH | `/turnos/{turno}/estado` | turnos.estado | TurnoController@cambiarEstado | web, rol:empleado,admin |

## Solo Admin

| Método | URL | Nombre | Controlador@acción | Middleware |
|---|---|---|---|---|
| DELETE | `/vehiculos/{vehiculo}` | vehiculos.destroy | VehiculoController@destroy | web, rol:admin |
| GET, HEAD | `/resenas` | resenas.index | ResenaController@index | web, rol:admin |
| PATCH | `/resenas/{resena}/moderar` | resenas.moderar | ResenaController@moderar | web, rol:admin |
| GET, HEAD | `/usuarios` | usuarios.index | UsuarioController@index | web, rol:admin |
| GET, HEAD | `/usuarios/crear` | usuarios.create | UsuarioController@create | web, rol:admin |
| POST | `/usuarios` | usuarios.store | UsuarioController@store | web, rol:admin |
| GET, HEAD | `/reportes/ventas` | reportes.ventas | ReporteController@ventas | web, rol:admin |
| GET, HEAD | `/auditoria` | auditoria.index | AuditoriaController@index | web, rol:admin |

Todos los controladores están en el espacio de nombres `App\Http\Controllers`, salvo el de `/`, que es de Laravel.
