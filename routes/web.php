<?php

use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ComparadorController;
use App\Http\Controllers\ConsultaController;
use App\Http\Controllers\FavoritoController;
use App\Http\Controllers\InteraccionController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\PrecioReferenciaController;
use App\Http\Controllers\RecuperacionController;
use App\Http\Controllers\RegistroController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\ResenaController;
use App\Http\Controllers\TurnoController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\VehiculoController;
use Illuminate\Support\Facades\Route;

// La página de inicio lleva directo al catálogo
Route::redirect('/', '/vehiculos');

// Crea las 7 rutas del CRUD: index, create, store, show, edit, update, destroy.
// Los permisos por rol están en VehiculoController::middleware():
//   index y show: públicos · create, store, edit, update: empleado y admin · destroy: solo admin
Route::resource('vehiculos', VehiculoController::class);

// Edición rápida de la descripción desde la ficha: mismo permiso que "Editar" (empleado y admin, en el controlador)
Route::patch('/vehiculos/{vehiculo}/descripcion', [VehiculoController::class, 'actualizarDescripcion'])->name('vehiculos.descripcion');

// Sesión: el login y el registro solo los ve quien no inició sesión; el login se limita a 5 intentos por minuto
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
    // Registro de clientes: la cuenta creada es siempre de rol cliente (ver RegistroController)
    Route::get('/registro', [RegistroController::class, 'create'])->name('registro');
    Route::post('/registro', [RegistroController::class, 'store'])->middleware('throttle:6,1')->name('registro.store');

    // "¿Olvidaste tu contraseña?" (RF-05): pedir el código por correo (5 intentos por minuto, como el login) y elegir la nueva
    Route::get('/olvide-contrasena', [RecuperacionController::class, 'pedir'])->name('password.request');
    Route::post('/olvide-contrasena', [RecuperacionController::class, 'enviar'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/restablecer-contrasena', [RecuperacionController::class, 'formulario'])->name('password.reset');
    Route::post('/restablecer-contrasena', [RecuperacionController::class, 'guardar'])->middleware('throttle:5,1')->name('password.update');
});
Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

// Reporte gerencial: solo Admin
Route::get('/reportes/ventas', [ReporteController::class, 'ventas'])
    ->middleware('rol:admin')
    ->name('reportes.ventas');

// Usuarios: listado y alta de cuentas de empleado y admin. Solo Admin. No hay edición ni baja.
Route::middleware('rol:admin')->group(function () {
    Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
    Route::get('/usuarios/crear', [UsuarioController::class, 'create'])->name('usuarios.create');
    Route::post('/usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
});

// Auditoría (quién hizo qué y cuándo): solo Admin, solo lectura. El permiso está en AuditoriaController::middleware().
Route::get('/auditoria', [AuditoriaController::class, 'index'])->name('auditoria.index');

// Consultas (leads): el cliente consulta desde el detalle del vehículo y ve las suyas;
// Empleado y Admin manejan la bandeja, el avance de estado y el historial de contactos.
// Los permisos por rol están en ConsultaController::middleware() e InteraccionController::middleware().
Route::post('/vehiculos/{vehiculo}/consultar', [ConsultaController::class, 'store'])->name('consultas.store');
Route::get('/mis-consultas', [ConsultaController::class, 'mias'])->name('consultas.mias');
Route::get('/consultas', [ConsultaController::class, 'index'])->name('consultas.index');
Route::get('/consultas/{consulta}', [ConsultaController::class, 'show'])->name('consultas.show');
Route::patch('/consultas/{consulta}/estado', [ConsultaController::class, 'avanzar'])->name('consultas.avanzar');
Route::post('/consultas/{consulta}/interacciones', [InteraccionController::class, 'store'])->name('consultas.interacciones.store');

// Precios de referencia (RF-04): tabla de consulta, solo lectura, para Empleado y Admin.
// El permiso por rol está en PrecioReferenciaController::middleware().
Route::get('/precios-referencia', [PrecioReferenciaController::class, 'index'])->name('precios.index');

// Favoritos (RF-11): solo el Cliente. El permiso por rol está en FavoritoController::middleware().
Route::get('/favoritos', [FavoritoController::class, 'index'])->name('favoritos.index');
Route::post('/vehiculos/{vehiculo}/favorito', [FavoritoController::class, 'store'])->name('favoritos.store');
Route::delete('/vehiculos/{vehiculo}/favorito', [FavoritoController::class, 'destroy'])->name('favoritos.destroy');

// Campanita de avisos: la usan los tres roles, cada uno con los suyos.
// El permiso está en NotificacionController::middleware().
Route::get('/notificaciones', [NotificacionController::class, 'index'])->name('notificaciones.index');
Route::post('/notificaciones/leer-todas', [NotificacionController::class, 'leerTodas'])->name('notificaciones.leer-todas');
Route::post('/notificaciones/{id}/leer', [NotificacionController::class, 'leer'])->name('notificaciones.leer');

// Comparador (RF-08): público, como el catálogo. La selección (hasta 3 vehículos) se guarda en la sesión.
// whereNumber evita que "vaciar" se confunda con el id de un vehículo.
Route::get('/comparar', [ComparadorController::class, 'index'])->name('comparador.index');
Route::post('/comparar/vaciar', [ComparadorController::class, 'vaciar'])->name('comparador.vaciar');
Route::post('/comparar/{vehiculo}', [ComparadorController::class, 'agregar'])->whereNumber('vehiculo')->name('comparador.agregar');
Route::post('/comparar/{id}/quitar', [ComparadorController::class, 'quitar'])->whereNumber('id')->name('comparador.quitar');

// Turnos de prueba (RF-13): el cliente pide, ve y cancela los suyos; Empleado y Admin manejan la agenda.
// Los permisos por rol están en TurnoController::middleware().
Route::post('/vehiculos/{vehiculo}/turno', [TurnoController::class, 'store'])->name('turnos.store');
Route::get('/mis-turnos', [TurnoController::class, 'mios'])->name('turnos.mios');
Route::patch('/mis-turnos/{turno}/cancelar', [TurnoController::class, 'cancelar'])->name('turnos.cancelar');
Route::get('/turnos', [TurnoController::class, 'index'])->name('turnos.index');
Route::patch('/turnos/{turno}/estado', [TurnoController::class, 'cambiarEstado'])->name('turnos.estado');

// Reseñas con moderación (RF-14): el cliente reseña una compra (consulta cerrada suya) y el Admin modera.
// Los permisos por rol están en ResenaController::middleware(); la consulta de otro cliente da 403.
Route::get('/mis-consultas/{consulta}/resena', [ResenaController::class, 'create'])->name('resenas.create');
Route::post('/mis-consultas/{consulta}/resena', [ResenaController::class, 'store'])->name('resenas.store');
Route::get('/resenas', [ResenaController::class, 'index'])->name('resenas.index');
Route::patch('/resenas/{resena}/moderar', [ResenaController::class, 'moderar'])->name('resenas.moderar');
