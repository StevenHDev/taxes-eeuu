<?php

namespace App\Models\Entrevista;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Valor de una respuesta de la entrevista, siempre cifrado. Se guarda
 * envuelto (`{"v": …}`) para que un texto, un número o una lista se cifren
 * y se lean igual; `encrypted:array` solo acepta arreglos.
 *
 * @implements CastsAttributes<string|int|float|list<string>|null, string|int|float|list<string>|null>
 */
class ValorCifrado implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value === null ? null : json_decode(Crypt::decryptString($value), true)['v'];
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : Crypt::encryptString(json_encode(['v' => $value], JSON_THROW_ON_ERROR));
    }
}
