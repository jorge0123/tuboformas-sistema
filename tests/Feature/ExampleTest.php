<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Humo: el sistema arranca, el login carga y lo privado exige sesión. */
class ExampleTest extends TestCase
{
    public function test_el_sistema_responde_y_pide_iniciar_sesion(): void
    {
        $this->get('/up')->assertOk();
        $this->get('/login')->assertOk()->assertSee('Iniciar sesión');
        $this->get('/')->assertRedirect('/login');
    }
}
