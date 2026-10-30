<?php

namespace App\Services\Entrevista;

use Closure;

/**
 * Evalúa la `condicion` de una pregunta del catálogo (ver
 * App\Rules\CondicionCatalogoValida para la forma):
 *
 *   {"pregunta": "B1", "es": "casado"}      la respuesta calza (lista = alguna de)
 *   {"pregunta": "C4", "hasta": 6}          número menor o igual
 *   {"todas": [...]} / {"alguna": [...]}
 *
 * No sabe de personas ni de visibilidad: recibe la respuesta de cada
 * pregunta ya resuelta por MotorEntrevista (que mira la misma persona en el
 * bloque C y trata como sin responder lo que no se ve).
 */
class EvaluadorCondicion
{
    /**
     * @param  array<string, mixed>|null  $condicion
     * @param  Closure(string): mixed  $respuestaDe  código de pregunta → respuesta (null si no hay)
     */
    public function cumple(?array $condicion, Closure $respuestaDe): bool
    {
        if ($condicion === null) {
            return true;
        }

        if (isset($condicion['todas'])) {
            return collect($condicion['todas'])->every(fn (array $sub) => $this->cumple($sub, $respuestaDe));
        }

        if (isset($condicion['alguna'])) {
            return collect($condicion['alguna'])->contains(fn (array $sub) => $this->cumple($sub, $respuestaDe));
        }

        $valor = $respuestaDe($condicion['pregunta']);

        if (array_key_exists('hasta', $condicion)) {
            return is_numeric($valor) && $valor <= $condicion['hasta'];
        }

        return ValorRespuesta::coincide($valor, $condicion['es']);
    }
}
