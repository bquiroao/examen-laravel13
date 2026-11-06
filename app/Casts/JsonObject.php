<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** jsonb con CHECK (jsonb_typeof(x) = 'object'): un arreglo vacío se guarda como {} y no como []. */
class JsonObject implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        return $value === null ? [] : (array) json_decode($value, true);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        return json_encode($value === [] || $value === null ? new \stdClass : $value, JSON_UNESCAPED_UNICODE);
    }
}
