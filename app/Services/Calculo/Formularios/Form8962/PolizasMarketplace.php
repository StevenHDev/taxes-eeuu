<?php

namespace App\Services\Calculo\Formularios\Form8962;

use App\Services\Calculo\Nucleo\DatoEntrada;
use App\Services\Calculo\Nucleo\Dinero;
use App\Services\Calculo\Nucleo\DocumentoEntrada;
use App\Services\Calculo\Nucleo\Linea;
use App\Services\Calculo\Nucleo\OrigenTraza;
use App\Services\Calculo\Nucleo\Traza;

/**
 * Los 1095-A de la declaración, juntados por mes para la Parte II del Form 8962 (i8962 2025, p. 14 a 17).
 *
 * - Cada 1095-A trae, por mes, la prima de inscripción (columna A), la del segundo plan plata de menor
 *   costo, SLCSP (B), y el adelanto del crédito, APTC (C), en las líneas 21 a 32 (enero a diciembre);
 *   los totales del año en la 33. Nunca se lee el número de póliza ni los datos de las personas cubiertas.
 * - Columna (a) y (f): con varios 1095-A en un mes, se suman. Columna (b): con varios 1095-A del mismo
 *   estado, el Marketplace pone el mismo SLCSP en cada uno y se toma uno solo ("do not add the amounts");
 *   si los SLCSP no coinciden (pólizas en estados distintos, asignación con otra familia, matrimonio en el
 *   año) no se soporta: lo resuelve el preparador.
 * - Línea 10 (p. 14): "Sí" (línea 11, anual) solo si cada póliza cubrió los 12 meses con la misma prima y
 *   el mismo SLCSP todos los meses; si no, meses (líneas 12 a 23).
 * - Un mes con SLCSP en blanco o en cero (p. 14, "Missing or incorrect SLCSP premium") o con prima de
 *   inscripción en cero (p. 16, primas sin pagar) sale `falta_dato`: el preparador busca el SLCSP o confirma
 *   el pago.
 *
 * Con `soloAdelantos` (MFS sin la excepción, p. 15 y 16) solo cuenta la columna C.
 */
final readonly class PolizasMarketplace
{
    public const MESES = [1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio',
        7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'];

    /**
     * @param  array<int, array{a: Dinero, b: Dinero, f: Dinero, traza: Traza}>  $meses  solo los meses con cobertura
     * @param  array{a: Dinero, b: Dinero, f: Dinero, traza: Traza}  $anual
     * @param  list<string>  $sinValidar
     */
    private function __construct(
        public bool $todoElAnio,
        public array $meses,
        public array $anual,
        public array $sinValidar,
    ) {}

    /**
     * @param  list<DocumentoEntrada>  $polizas
     * @return self|Linea la lectura, o la línea 24 del 8962 con el problema (falta_dato o no_soportada)
     */
    public static function leer(array $polizas, bool $soloAdelantos): self|Linea
    {
        $meses = [];
        $sinValidar = [];
        $todoElAnio = true;

        foreach ($polizas as $poliza) {
            $primas = [];
            $slcsp = [];

            foreach (self::MESES as $mes => $mesEs) {
                $nombre = __($mesEs);
                $fila = (string) (20 + $mes);
                $valores = [];

                foreach (['A', 'B', 'C'] as $columna) {
                    $lectura = self::monto($poliza, "{$fila}-{$columna}");

                    if ($lectura instanceof Linea) {
                        return $lectura;
                    }

                    $valores[$columna] = $lectura;
                }

                $cubierto = array_filter($valores, fn (?Dinero $v): bool => $v !== null && ! $v->esCero()) !== [];

                if (! $cubierto) {
                    $todoElAnio = false;

                    continue;
                }

                $cero = Dinero::deCentavos(0);
                $a = $valores['A'] ?? $cero;
                $b = $valores['B'] ?? $cero;
                $f = $valores['C'] ?? $cero;

                if (! $soloAdelantos && $a->esCero()) {
                    return Linea::faltaDato('f8962', '24', __('El 1095-A #:item trae la prima de :mes en cero: el preparador confirma si se pagó a tiempo (instrucciones del 8962, columna (a) de las líneas 12 a 23).', ['item' => $poliza->itemId, 'mes' => $nombre]));
                }

                if (! $soloAdelantos && $b->esCero()) {
                    return Linea::faltaDato('f8962', '24', __('El 1095-A #:item no trae el SLCSP de :mes: el preparador lo busca en HealthCare.gov/Tax-Tool o con la Pub. 974 (instrucciones del 8962, línea 10).', ['item' => $poliza->itemId, 'mes' => $nombre]));
                }

                $primas[] = $a->centavos();
                $slcsp[] = $b->centavos();

                foreach (['A', 'B', 'C'] as $columna) {
                    $dato = $poliza->casilla("{$fila}-{$columna}");

                    if ($dato !== null && $dato->usaSinValidar()) {
                        $sinValidar[] = self::referencia($poliza, "{$fila}-{$columna}", $dato);
                    }
                }

                $etiqueta = __('1095-A #:item, :mes', ['item' => $poliza->itemId, 'mes' => $nombre]);

                if (! isset($meses[$mes])) {
                    $meses[$mes] = ['a' => $cero, 'b' => $b, 'f' => $cero, 'traza' => Traza::vacia()];
                } elseif (! $soloAdelantos && $meses[$mes]['b']->centavos() !== $b->centavos()) {
                    return Linea::noSoportada('f8962', '24', __('Los 1095-A traen distinto SLCSP para :mes (pólizas en estados distintos, asignación con otra familia o matrimonio en el año): lo calcula el preparador.', ['mes' => $nombre]));
                }

                $meses[$mes]['a'] = $meses[$mes]['a']->mas($a);
                $meses[$mes]['f'] = $meses[$mes]['f']->mas($f);
                $meses[$mes]['traza'] = $meses[$mes]['traza']
                    ->conEntrada(__(':poliza: prima (columna A)', ['poliza' => $etiqueta]), $a, $poliza->casilla("{$fila}-A")?->origen ?? OrigenTraza::Leido, self::referencia($poliza, "{$fila}-A"))
                    ->conEntrada(__(':poliza: SLCSP (columna B)', ['poliza' => $etiqueta]), $b, $poliza->casilla("{$fila}-B")?->origen ?? OrigenTraza::Leido, self::referencia($poliza, "{$fila}-B"))
                    ->conEntrada(__(':poliza: adelanto (columna C)', ['poliza' => $etiqueta]), $f, $poliza->casilla("{$fila}-C")?->origen ?? OrigenTraza::Leido, self::referencia($poliza, "{$fila}-C"));
            }

            // Línea 10: la misma prima y el mismo SLCSP los 12 meses, en cada póliza.
            if (count($primas) !== 12 || count(array_unique($primas)) !== 1 || count(array_unique($slcsp)) !== 1) {
                $todoElAnio = false;
            }
        }

        if ($meses === []) {
            return Linea::faltaDato('f8962', '24', __('Los 1095-A no traen montos de ningún mes.'));
        }

        ksort($meses);

        $anual = $todoElAnio ? self::anual($polizas, $soloAdelantos, $sinValidar) : null;

        if ($anual instanceof Linea) {
            return $anual;
        }

        $cero = Dinero::deCentavos(0);

        return new self($todoElAnio, $meses, $anual ?? ['a' => $cero, 'b' => $cero, 'f' => $cero, 'traza' => Traza::vacia()], array_values(array_unique($sinValidar)));
    }

    /**
     * Línea 11 (p. 15): columna (a) = suma de las líneas 33A; (b) = la 33B de un solo 1095-A; (f) = suma de
     * las 33C. Si un 1095-A no trae la línea 33, se usa la suma de sus meses.
     *
     * @param  list<DocumentoEntrada>  $polizas
     * @param  list<string>  $sinValidar  se agregan las referencias de las líneas 33 sin validar
     * @return array{a: Dinero, b: Dinero, f: Dinero, traza: Traza}|Linea
     */
    private static function anual(array $polizas, bool $soloAdelantos, array &$sinValidar): array|Linea
    {
        $cero = Dinero::deCentavos(0);
        $a = $cero;
        $f = $cero;
        $b = null;
        $traza = Traza::vacia();

        foreach ($polizas as $poliza) {
            $totales = [];

            foreach (['A', 'B', 'C'] as $columna) {
                $lectura = self::monto($poliza, "33-{$columna}");

                if ($lectura instanceof Linea) {
                    return $lectura;
                }

                if ($lectura === null) {
                    // Sin la línea 33: la suma de los 12 meses de esta póliza.
                    $lectura = $cero;

                    foreach (array_keys(self::MESES) as $mes) {
                        $lectura = $lectura->mas(self::monto($poliza, (20 + $mes)."-{$columna}") ?? $cero);
                    }

                    $traza = $traza->conOperacion(__('El 1095-A #:item no trae la línea 33, columna :columna: se suman sus 12 meses.', ['item' => $poliza->itemId, 'columna' => $columna]), $lectura);
                } else {
                    $dato = $poliza->casilla("33-{$columna}");

                    if ($dato !== null && $dato->usaSinValidar()) {
                        $sinValidar[] = self::referencia($poliza, "33-{$columna}", $dato);
                    }

                    $traza = $traza->conEntrada(__('1095-A #:item, línea 33, columna :columna', ['item' => $poliza->itemId, 'columna' => $columna]), $lectura, $poliza->casilla("33-{$columna}")?->origen ?? OrigenTraza::Leido, self::referencia($poliza, "33-{$columna}"));
                }

                $totales[$columna] = $lectura;
            }

            if (! $soloAdelantos && $b !== null && $b->centavos() !== $totales['B']->centavos()) {
                return Linea::noSoportada('f8962', '24', __('Los 1095-A traen distinto SLCSP anual (pólizas en estados distintos, asignación con otra familia o matrimonio en el año): lo calcula el preparador.'));
            }

            $a = $a->mas($totales['A']);
            $b ??= $totales['B'];
            $f = $f->mas($totales['C']);
        }

        if (count($polizas) > 1 && ! $soloAdelantos) {
            $traza = $traza->conOperacion(__('Varios 1095-A con el mismo SLCSP: la columna (b) toma el de uno solo (instrucciones del 8962, línea 11, columna (b)).'));
        }

        return ['a' => $a, 'b' => $b ?? $cero, 'f' => $f, 'traza' => $traza];
    }

    /** El monto de una casilla: null si no está o vino vacía; la línea 24 con falta_dato si no es un monto. */
    private static function monto(DocumentoEntrada $poliza, string $casilla): Dinero|Linea|null
    {
        $dato = $poliza->casilla($casilla);

        if ($dato === null || $dato->valor === null || $dato->valor === '') {
            return null;
        }

        if (! $dato->valor instanceof Dinero) {
            return Linea::faltaDato('f8962', '24', __('El 1095-A #:item trae un valor que no es un monto en la línea :casilla.', ['item' => $poliza->itemId, 'casilla' => $casilla]));
        }

        return $dato->valor;
    }

    private static function referencia(DocumentoEntrada $poliza, string $casilla, ?DatoEntrada $dato = null): string
    {
        return $dato?->referencia ?? $poliza->referencia().':'.$casilla;
    }
}
