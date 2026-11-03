<?php

namespace App\Services\Lectura\Reglas;

use App\Enums\Lectura\Legibilidad;
use App\Enums\Lectura\MotivoConfianza;
use App\Enums\Lectura\OrigenDato;
use App\Models\Entrevista\Entrevista;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use App\Support\ParametrosFiscales;
use Illuminate\Support\Collection;

/**
 * R-03: un dato de una casilla clave con confianza menor que el umbral va a
 * revisión del preparador. El umbral es el parámetro `lectura.umbral_confianza`
 * del año (0,90); cómo se calcula la confianza está en la regla R-03 del
 * catálogo y en `CalculadoraConfianza`. Lo que el preparador ya corrigió no
 * cuenta, ni una casilla vacía que el lector vio con claridad. El detalle lleva el motivo de la confianza, nunca el valor.
 */
class R03Confianza implements Regla
{
    public function __construct(private readonly CatalogoFiscal $catalogo) {}

    public function codigo(): string
    {
        return 'R-03';
    }

    public function evaluar(Entrevista $entrevista, Collection $datosPorItem): array
    {
        $umbral = ParametrosFiscales::valor($entrevista->tax_year, 'lectura', 'umbral_confianza');

        if (! is_numeric($umbral)) {
            return [];
        }

        $documentos = $this->catalogo->documentos($entrevista->tax_year)->keyBy('codigo');
        $hallazgos = [];

        foreach ($entrevista->documentos()->vigentes()->get() as $item) {
            $claves = collect($documentos->get($item->documento_codigo)['casillas'] ?? [])
                ->filter(fn (array $c) => $c['clave'] ?? false)
                ->pluck('casilla')
                ->map(fn ($c) => (string) $c)
                ->flip();

            foreach ($datosPorItem->get($item->id, collect()) as $dato) {
                // Una casilla que el lector vio con claridad y está vacía (la 7 o la 10 de casi todo W-2) no es
                // una duda: sin valor y `claro`. Una que no pudo ver, o que ni mencionó, sí va a revisión.
                $vacia = $dato->motivo_confianza === MotivoConfianza::SinValor && $dato->legibilidad === Legibilidad::Claro;

                if ($dato->origen === OrigenDato::Preparador || $dato->confianza === null || $vacia || ! $claves->has($dato->casilla) || $dato->confianza >= (float) $umbral) {
                    continue;
                }

                $hallazgos[] = new Hallazgo(
                    clave: "R-03#item:{$item->id}#casilla:{$dato->casilla}",
                    item: $item,
                    dato: $dato,
                    detalle: ['casilla' => $dato->casilla, 'motivo_confianza' => $dato->motivo_confianza?->value, 'confianza' => $dato->confianza, 'umbral' => (float) $umbral],
                );
            }
        }

        return $hallazgos;
    }
}
