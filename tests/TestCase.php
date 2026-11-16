<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Seguro contra accidentes: los tests usan RefreshDatabase, que BORRA todas las tablas de la base
     * con la que corren. Por eso se frena antes de arrancar si la conexión no es la de pruebas
     * (SQLite en memoria, definida en phpunit.xml, o una base cuyo nombre termine en "_test").
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $conexion = $app['config']->get('database.default');
        $base = (string) $app['config']->get("database.connections.$conexion.database");
        $esDePruebas = $conexion === 'sqlite' && $base === ':memory:'
            || str_ends_with($base, '_test');

        if (! $esDePruebas) {
            throw new RuntimeException(
                "Los tests se negaron a correr contra la base '$base' (conexión '$conexion'): no es una base de pruebas. "
                . 'Revisá DB_CONNECTION y DB_DATABASE en phpunit.xml.'
            );
        }

        return $app;
    }
}
