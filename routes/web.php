<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConsultaController;
use App\Http\Controllers\FavoritoController;
use App\Http\Controllers\InteraccionController;
use App\Http\Controllers\PrecioReferenciaController;
use App\Http\Controllers\RegistroController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\VehiculoController;
use Illuminate\Support\Facades\Route;

// La página de inicio lleva directo al catálogo
Route::redirect('/', '/vehiculos');

// Crea las 7 rutas del CRUD: index, create, store, show, edit, update, destroy.
// Los permisos por rol están en VehiculoController::middleware():
//   index y show: públicos · create, store, edit, update: empleado y admin · destroy: solo admin
Route::resource('vehiculos', VehiculoController::class);

// Sesión: el login y el registro solo los ve quien no inició sesión; el login se limita a 5 intentos por minuto
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
    // Registro de clientes: la cuenta creada es siempre de rol cliente (ver RegistroController)
    Route::get('/registro', [RegistroController::class, 'create'])->name('registro');
    Route::post('/registro', [RegistroController::class, 'store'])->middleware('throttle:6,1')->name('registro.store');
});
Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

// Reporte gerencial: solo Admin
Route::get('/reportes/ventas', [ReporteController::class, 'ventas'])
    ->middleware('rol:admin')
    ->name('reportes.ventas');

// Listado de usuarios y sus roles (solo lectura): solo Admin
Route::get('/usuarios', [UsuarioController::class, 'index'])
    ->middleware('rol:admin')
    ->name('usuarios.index');

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
