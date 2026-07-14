<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    /**
     * Autentica un usuario para los tests usando Sanctum con ability completa ('*').
     *
     * Por qué: las rutas protegidas exigen el middleware 'ability:*' y
     * Sanctum::actingAs($user) usa abilities VACÍOS por defecto
     * (vendor/laravel/sanctum/src/Sanctum.php:70), lo que produce 403.
     * Centralizar aquí evita editar 60+ tests y es la única fuente de verdad.
     */
    public function actingAs($user, $guard = null): static
    {
        Sanctum::actingAs($user, ['*'], $guard ?? 'sanctum');

        return $this;
    }
}
