<?php

use App\Http\Controllers\VehiculoController;
use Illuminate\Support\Facades\Route;

// La página de inicio lleva directo al catálogo
Route::redirect('/', '/vehiculos');

// Crea las 7 rutas del CRUD: index, create, store, show, edit, update, destroy
Route::resource('vehiculos', VehiculoController::class);
