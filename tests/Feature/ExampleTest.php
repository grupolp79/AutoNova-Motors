<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * La raíz del sitio no muestra una página propia: redirige (302) al catálogo.
     * (El test de ejemplo de Laravel esperaba 200 y fallaba por esta redirección.)
     */
    public function test_la_raiz_redirige_al_catalogo(): void
    {
        $this->get('/')->assertRedirect('/vehiculos');
    }
}
