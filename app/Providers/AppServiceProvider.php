<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\App;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Los links de paginación usan las clases de Bootstrap 5
        Paginator::useBootstrapFive();

        // La interfaz está en español: mensajes de validación (lang/es) y nombres de meses
        App::setLocale('es');
        Carbon::setLocale('es');
    }
}
