<?php

namespace App\Services\Lectura\Reglas;

use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaRespuesta;
use App\Models\Lectura\DocumentoDato;
use Illuminate\Support\Collection;

/**
 * R-01: el SSN (o ITIN) de un documento tiene que ser el de alguna persona de
 * la declaración. Se comparan solo los últimos 4 dígitos, porque las copias
 * del cliente suelen traer el número truncado. Los números de las personas
 * salen de la tarjeta del Seguro Social leída (un ítem por persona) y de B4.3
 * (el cónyuge); no se guardan en ningún lado: el hallazgo dice solo la casilla.
 *
 * Es conservadora: si no hay ningún número conocido, o falta el del cónyuge
 * estando casado (no se sabría de quién es el documento), no abre nada. Un
 * TIN con forma de EIN (XX-XXXXXXX) no se compara: es de una empresa. De la
 * persona cubierta de un 1095-A (filas 16 a 20) se ocupa R-08, porque puede
 * ser alguien que legítimamente no va en la declaración.
 */
class R01Ssn implements Regla
{
    /** Casillas con el SSN del documento; los 1099 se resuelven por prefijo. */
    private const CASILLAS = [
        'w2' => ['a'],
        '1095-a' => ['5', '8'],
        'ssa-1099' => ['receptor-tin'],
    ];

    public function codigo(): string
    {
        return 'R-01';
    }

    public function evaluar(Entrevista $entrevista, Collection $datosPorItem): array
    {
        $conocidos = self::conocidos($entrevista, $datosPorItem);

        if ($conocidos['numeros'] === [] || $conocidos['conyuge_pendiente']) {
            return [];
        }

        $hallazgos = [];

        foreach ($entrevista->documentos()->vigentes()->get() as $item) {
            $casillas = self::CASILLAS[$item->documento_codigo] ?? (str_starts_with($item->documento_codigo, '1099-') ? ['receptor-tin'] : []);

            foreach ($datosPorItem->get($item->id, collect())->whereIn('casilla', $casillas) as $dato) {
                $ultimos = self::ultimos4($dato->valor);

                if ($ultimos === null || preg_match('/^\s*\d{2}-\d{7}\s*$/', (string) $dato->valor) || in_array($ultimos, $conocidos['numeros'], true)) {
                    continue;
                }

                $hallazgos[] = new Hallazgo("R-01#item:{$item->id}#casilla:{$dato->casilla}", $item, $dato, ['casilla' => $dato->casilla, 'problema' => 'Los últimos 4 dígitos no coinciden con los de ninguna persona de la declaración.']);
            }
        }

        return $hallazgos;
    }

    /**
     * Los últimos 4 dígitos de un número, o null si trae menos de 4.
     */
    public static function ultimos4(?string $valor): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $valor);

        return strlen($digitos) >= 4 ? substr($digitos, -4) : null;
    }

    /**
     * Los últimos 4 dígitos de los números que la entrevista conoce.
     * `conyuge_pendiente`: está casado y falta su número (B4.3) y la tarjeta
     * del segundo. `tarjetas_pendientes`: hay una tarjeta en la lista que
     * todavía no se leyó, así que faltan números de personas.
     *
     * @param  Collection<int, Collection<int, DocumentoDato>>  $datosPorItem
     * @return array{numeros: list<string>, conyuge_pendiente: bool, tarjetas_pendientes: bool}
     */
    public static function conocidos(Entrevista $entrevista, Collection $datosPorItem): array
    {
        $numeros = [];
        $tarjetas = 0;
        $pendientes = false;

        $tarjetaConyugeLeida = false;

        foreach ($entrevista->documentos()->vigentes()->whereIn('documento_codigo', ['tarjeta-ssn', 'tarjeta-ssn-conyuge'])->get() as $tarjeta) {
            $ultimos = self::ultimos4($datosPorItem->get($tarjeta->id, collect())->firstWhere('casilla', 'ssn')?->valor);

            if ($ultimos === null) {
                $pendientes = true;

                continue;
            }

            $numeros[] = $ultimos;
            $tarjetas++;
            $tarjetaConyugeLeida = $tarjetaConyugeLeida || $tarjeta->documento_codigo === 'tarjeta-ssn-conyuge';
        }

        $respuesta = fn (string $codigo) => EntrevistaRespuesta::query()
            ->where(['entrevista_id' => $entrevista->id, 'pregunta_codigo' => $codigo])->whereNull('persona_id')->first()?->valor;

        $numeroConyuge = $respuesta('B4.3');
        $conyuge = self::ultimos4(is_scalar($numeroConyuge) ? (string) $numeroConyuge : null);

        if ($conyuge !== null) {
            $numeros[] = $conyuge;
        }

        return [
            'numeros' => array_values(array_unique($numeros)),
            'conyuge_pendiente' => $respuesta('B1') === 'casado' && $conyuge === null && ! $tarjetaConyugeLeida && $tarjetas < 2,
            'tarjetas_pendientes' => $pendientes,
        ];
    }
}
