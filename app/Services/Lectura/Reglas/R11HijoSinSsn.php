<?php

namespace App\Services\Lectura\Reglas;

use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaRespuesta;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * R-11 (solo aviso en F4): una persona de C con parentesco de hijo (hijo,
 * hijastro o hijo de crianza), menor de 17 al 31 de diciembre del año (C2) y
 * con un número (C9) que no es un SSN válido para trabajar no da el crédito
 * por hijos. La fecha y el tipo de número son respuestas sensibles: se leen
 * aquí y no salen en el detalle, que solo lleva el id de la persona.
 */
class R11HijoSinSsn implements Regla
{
    private const PARENTESCOS = ['hijo', 'hijastro', 'hijo_crianza'];

    public function codigo(): string
    {
        return 'R-11';
    }

    public function evaluar(Entrevista $entrevista, Collection $datosPorItem): array
    {
        $respuestas = EntrevistaRespuesta::query()
            ->where('entrevista_id', $entrevista->id)
            ->whereIn('pregunta_codigo', ['C2', 'C3', 'C9'])
            ->whereIn('persona_id', $entrevista->personas()->select('id'))
            ->get()
            ->groupBy('persona_id');

        // Nace el 1 de enero de (año - 16) o después: tiene 16 o menos al 31 de diciembre.
        $desde = Carbon::create($entrevista->tax_year - 16, 1, 1)->startOfDay();
        $hallazgos = [];

        foreach ($respuestas as $personaId => $filas) {
            $valor = fn (string $codigo) => $filas->firstWhere('pregunta_codigo', $codigo)?->valor;

            if (! in_array($valor('C3'), self::PARENTESCOS, true) || ! is_string($valor('C9')) || $valor('C9') === 'ssn_valido') {
                continue;
            }

            try {
                $nacio = Carbon::parse((string) $valor('C2'))->startOfDay();
            } catch (\Throwable) {
                continue;
            }

            if ($nacio->greaterThanOrEqualTo($desde)) {
                $hallazgos[] = new Hallazgo("R-11#persona:{$personaId}", detalle: ['persona_id' => $personaId, 'problema' => 'Hijo menor de 17 sin SSN válido para trabajar: no da el crédito por hijos.']);
            }
        }

        return $hallazgos;
    }
}
