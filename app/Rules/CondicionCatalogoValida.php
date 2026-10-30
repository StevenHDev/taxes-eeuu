<?php

namespace App\Rules;

use App\Models\CatalogoFiscal\CatalogoPregunta;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida la `condicion` de una pregunta del catálogo fiscal (cuándo se
 * muestra). Es un JSON pequeño y cerrado:
 *
 *   {"pregunta": "B1", "es": "casado"}
 *   {"pregunta": "C4", "hasta": 6}      (número menor o igual; F2.2)
 *   {"todas": [condición, …]}
 *   {"alguna": [condición, …]}
 *
 * Revisa la forma y que cada código de pregunta exista en el mismo año. En
 * la fase 1 solo se guarda; la evalúa App\Services\Entrevista\EvaluadorCondicion (fase 2).
 */
class CondicionCatalogoValida implements ValidationRule
{
    private const PROFUNDIDAD_MAXIMA = 4;

    public function __construct(private readonly int $taxYear) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        $codigos = [];

        if (! $this->esCondicion($value, 1, $codigos)) {
            $fail('La condición no tiene una forma válida.');

            return;
        }

        $existentes = CatalogoPregunta::query()
            ->delAnio($this->taxYear)
            ->whereIn('codigo', array_unique($codigos))
            ->pluck('codigo')
            ->all();

        $faltantes = array_values(array_diff(array_unique($codigos), $existentes));

        if ($faltantes !== []) {
            $fail('La condición usa preguntas que no existen en '.$this->taxYear.': '.implode(', ', $faltantes).'.');
        }
    }

    /**
     * @param  array<int, string>  $codigos
     */
    private function esCondicion(mixed $valor, int $profundidad, array &$codigos): bool
    {
        if (! is_array($valor) || $profundidad > self::PROFUNDIDAD_MAXIMA) {
            return false;
        }

        $claves = array_keys($valor);
        sort($claves);

        if ($claves === ['es', 'pregunta']) {
            if (! is_string($valor['pregunta']) || $valor['pregunta'] === '') {
                return false;
            }

            $codigos[] = $valor['pregunta'];

            return is_scalar($valor['es']) || is_array($valor['es']);
        }

        if ($claves === ['hasta', 'pregunta']) {
            if (! is_string($valor['pregunta']) || $valor['pregunta'] === '' || ! is_numeric($valor['hasta'])) {
                return false;
            }

            $codigos[] = $valor['pregunta'];

            return true;
        }

        if ($claves === ['todas'] || $claves === ['alguna']) {
            $lista = $valor[$claves[0]];

            if (! is_array($lista) || $lista === [] || ! array_is_list($lista)) {
                return false;
            }

            foreach ($lista as $sub) {
                if (! $this->esCondicion($sub, $profundidad + 1, $codigos)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }
}
