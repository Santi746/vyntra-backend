<?php

namespace App\Models\Concerns;

/**
 * Valida el formato UUID antes de resolver el route model binding.
 *
 * En PostgreSQL, pasar un valor no-UUID a una columna `uuid` dispara
 * "invalid input syntax for type uuid" (SQLSTATE 22P02) y la app responde
 * 500. Al rechazar el formato inválido ANTES de consultar, Eloquent no
 * encuentra el modelo y Laravel responde 404 (correcto).
 *
 * Se overridea resolveRouteBinding (no resolveRouteBindingQuery) para no
 * colisionar con HasUuids/HasUniqueStringIds, que ya definen
 * resolveRouteBindingQuery.
 *
 * Cubre TODOS los modelos UUID sin importar el nombre del parámetro de
 * ruta, a diferencia de `->whereUuid()` que es por-ruta.
 */
trait ValidatesUuidRouteBinding
{
    public function resolveRouteBinding($value, $field = null)
    {
        if (($field === null || $field === $this->getRouteKeyName()) && ! $this->isValidUuid($value)) {
            // UUID inválido: fuerza no-coincidencia -> 404, no 500.
            return null;
        }

        return parent::resolveRouteBinding($value, $field);
    }

    protected function isValidUuid($value): bool
    {
        return is_string($value)
            && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD', $value) === 1;
    }
}
