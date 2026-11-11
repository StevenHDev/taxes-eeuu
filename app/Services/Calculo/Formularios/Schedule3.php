<?php

namespace App\Services\Calculo\Formularios;

use App\Services\Calculo\Formularios\Creditos\Corte;
use App\Services\Calculo\Nucleo\Apoyo;
use App\Services\Calculo\Nucleo\Calculadora;
use App\Services\Calculo\Nucleo\Declaracion;
use App\Services\Calculo\Nucleo\Dinero;
use App\Services\Calculo\Nucleo\DocumentoEntrada;
use App\Services\Calculo\Nucleo\EntradasDeclaracion;
use App\Services\Calculo\Nucleo\Linea;
use App\Services\Calculo\Nucleo\OrigenTraza;
use App\Services\Calculo\Nucleo\Traza;
use RuntimeException;

/**
 * Schedule 3 de 2025 (créditos y pagos adicionales) y la línea 20 del Form 1040.
 *
 * - Parte I: línea 2 (Form 2441, línea 11); línea 3 `no_soportada` si hay 1098-T (créditos de educación, Form 8863);
 *   línea 8 = suma de la Parte I del piloto → 1040 línea 20. Las líneas 1, 4, 5a, 5b y 6 no son del piloto.
 * - Parte II: línea 9 (Form 8962, línea 26), línea 10 (pago con la extensión), línea 11 (exceso de Seguro Social
 *   retenido) y línea 15 = 9 + 10 + 11 (la copia a la línea 31 del 1040 la hace PagosYResultado). Las líneas 12 y 13
 *   no son del piloto.
 *
 * Corre DESPUÉS de `Form2441::calcular()` y del Form 8962; el Schedule 8812 la necesita antes (línea 2 en la hoja del
 * límite y línea 11 en la Parte II-B). Fuentes: Schedule 3 de 2025 (p. 1) e instrucciones del 1040 de 2025 (i1040gi,
 * Schedule 3, líneas 9 a 11, p. 117).
 */
final class Schedule3 implements Calculadora
{
    private const S3 = 'sch-3';

    private const F1040 = '1040';

    public function formulario(): string
    {
        return self::S3;
    }

    public function calcular(EntradasDeclaracion $e, Declaracion $d): void
    {
        Apoyo::copiar($d, self::S3, '2', 'f2441', '11');
        $this->linea3($e, $d);
        $this->totalDe($d, '8', ['2', '3'], __('Suma de la Parte I del piloto (líneas 2 y 3); las líneas 1, 4, 5a, 5b y 6 no se calculan en el piloto.'));
        Apoyo::copiar($d, self::F1040, '20', self::S3, '8');

        $this->linea9($e, $d);
        $this->escribir($d, '10', fn (): Linea => $this->linea10($e));
        $this->escribir($d, '11', fn (): Linea => $this->linea11($e));
        $this->totalDe($d, '15', ['9', '10', '11'], __('Suma de la Parte II del piloto (líneas 9, 10 y 11); las líneas 12 y 13 no se calculan en el piloto.'));
    }

    /** Línea 3 (Form 8863): con un 1098-T puede haber crédito de educación, que el piloto no calcula. */
    private function linea3(EntradasDeclaracion $e, Declaracion $d): void
    {
        if (Apoyo::hayDatos($e, '1098-t')) {
            $d->poner(Linea::noSoportada(self::S3, '3', __('Hay un 1098-T: los créditos de educación (Form 8863) los calcula el preparador.')));
        }
    }

    /** Línea 9: crédito neto de la prima (Form 8962, línea 26). Sin 1095-A no hay 8962. */
    private function linea9(EntradasDeclaracion $e, Declaracion $d): void
    {
        if ($d->linea('f8962', '26') === null && ! Apoyo::hayDatos($e, '1095-a')) {
            $d->poner(Linea::noAplica(self::S3, '9', __('Sin 1095-A no hay crédito de la prima del Marketplace.')));

            return;
        }

        Apoyo::copiar($d, self::S3, '9', 'f8962', '26');
    }

    /**
     * Línea 10 (i1040gi, p. 117): lo pagado con la extensión (Form 4868 o el pago que la pidió). Cuentan los
     * comprobantes de tipo `extension` del año de la declaración.
     */
    private function linea10(EntradasDeclaracion $e): Linea
    {
        $comprobantes = $e->documentos('comprobantes-pagos');

        if ($comprobantes === [] && Apoyo::hayDatos($e, 'comprobantes-pagos')) {
            throw Corte::falta(__('Falta el detalle de cada comprobante de pago (tipo y año).'));
        }

        $total = Dinero::deCentavos(0);
        $traza = Traza::vacia();
        $sinValidar = [];
        $cuantos = 0;

        foreach ($comprobantes as $doc) {
            $tipo = $doc->casilla('tipo-pago')?->valor;

            if (! is_string($tipo) || trim($tipo) === '') {
                throw Corte::falta(__('Falta saber qué pago es el comprobante #:item (estimado, extensión u otro).', ['item' => (string) $doc->itemId]));
            }

            if (mb_strtolower(trim($tipo)) !== 'extension') {
                continue;
            }

            $anio = $doc->casilla('anio-aplicado')?->valor;

            if (! is_string($anio) || preg_match('/^\d{4}$/', trim($anio)) !== 1) {
                throw Corte::falta(__('Falta el año al que aplica el pago con la extensión del comprobante #:item.', ['item' => (string) $doc->itemId]));
            }

            if ((int) $anio !== $e->taxYear) {
                $traza = $traza->conOperacion(__('El comprobante :comprobante es de la extensión de otro año: no cuenta.', ['comprobante' => $doc->referencia()]));

                continue;
            }

            $monto = $doc->monto('monto');

            if ($monto === null) {
                throw Corte::falta(__('Falta el monto del comprobante de pago #:item.', ['item' => (string) $doc->itemId]));
            }

            $cuantos++;
            $total = $total->mas($monto);
            $traza = $traza->conEntrada(__('Pago con la extensión'), $monto, $doc->casilla('monto')?->origen ?? OrigenTraza::Leido, $doc->referencia().':monto');
            $sinValidar = [...$sinValidar, ...self::sinValidarDe($doc, ['monto', 'tipo-pago', 'anio-aplicado'])];
        }

        if ($cuantos === 0) {
            return Linea::noAplica(self::S3, '10', __('Sin pagos con la extensión del año.'));
        }

        return Linea::calculada(self::S3, '10', $total, $traza->conResultado(Apoyo::nombreLinea(self::S3, '10'), $total), array_values(array_unique($sinValidar)));
    }

    /**
     * Línea 11 (i1040gi, p. 117): con más de un empleador en el año, el Seguro Social retenido de más. Se calcula
     * por separado para cada persona; lo que un solo empleador retuvo de más no se reclama aquí (el empleador lo
     * corrige). Dos W-2 con el mismo EIN son un solo empleador. El RRTA (ferrocarriles) no es del piloto.
     */
    private function linea11(EntradasDeclaracion $e): Linea
    {
        $w2 = $e->documentos('w2');

        if ($w2 === [] && Apoyo::hayDatos($e, 'w2')) {
            throw Corte::falta(__('Falta el detalle de cada W-2 (de quién es cada uno).'));
        }

        $porPersona = [];

        foreach ($w2 as $doc) {
            if ($doc->personaId === null) {
                throw Corte::falta(__('Falta saber de quién es el W-2 #:item.', ['item' => (string) $doc->itemId]));
            }

            $porPersona[$doc->personaId][] = $doc;
        }

        $conVarios = array_filter($porPersona, fn (array $docs): bool => count($docs) >= 2);

        if ($conVarios === []) {
            return Linea::noAplica(self::S3, '11', __('Nadie tuvo más de un empleador: no hay exceso de Seguro Social.'));
        }

        try {
            $maximo = Apoyo::parametroEnDolares($e->taxYear, 'self_employment_tax', 'maximo_seguro_social_retenido_por_persona');
        } catch (RuntimeException $excepcion) {
            throw Corte::falta(Apoyo::motivoSinParametro($e->taxYear, $excepcion));
        }

        $cero = Dinero::deCentavos(0);
        $total = $cero;
        $traza = Traza::vacia()->conParametro(__('Máximo de Seguro Social retenido por persona en el año'), $maximo, 'self_employment_tax.maximo_seguro_social_retenido_por_persona');
        $sinValidar = [];

        foreach ($conVarios as $personaId => $docs) {
            $porEmpleador = [];

            foreach ($docs as $indice => $doc) {
                $retenido = $doc->monto('4');

                if ($retenido === null) {
                    throw Corte::falta(__('Falta la casilla 4 del W-2 #:item.', ['item' => (string) $doc->itemId]));
                }

                // El EIN solo se compara, nunca se escribe.
                $ein = $doc->casilla('b')?->valor;
                $clave = is_string($ein) && preg_replace('/\D/', '', $ein) !== '' ? 'ein:'.preg_replace('/\D/', '', $ein) : 'doc:'.$indice;
                $porEmpleador[$clave] = ($porEmpleador[$clave] ?? $cero)->mas($retenido);
                $traza = $traza->conEntrada(__('W-2, casilla 4 (Seguro Social retenido) de la persona :persona', ['persona' => 'persona#'.$personaId]), $retenido, $doc->casilla('4')?->origen ?? OrigenTraza::Leido, $doc->referencia().':4');
                $sinValidar = [...$sinValidar, ...self::sinValidarDe($doc, ['4'])];
            }

            if (count($porEmpleador) < 2) {
                $traza = $traza->conOperacion(__('Los W-2 de la persona :persona son del mismo empleador: lo retenido de más lo corrige el empleador.', ['persona' => 'persona#'.$personaId]));

                continue;
            }

            $reclamable = $cero;

            foreach ($porEmpleador as $retenido) {
                $reclamable = $reclamable->mas($retenido->min($maximo));
            }

            $exceso = $reclamable->menos($maximo)->max($cero);
            $total = $total->mas($exceso);
            $traza = $traza->conOperacion(__('Persona :persona: lo retenido por cada empleador (sin pasar del máximo) menos el máximo; si es cero o menos, cero.', ['persona' => 'persona#'.$personaId]), $exceso);
        }

        return Linea::calculada(self::S3, '11', $total, $traza->conResultado(Apoyo::nombreLinea(self::S3, '11'), $total), array_values(array_unique($sinValidar)));
    }

    /** @param callable(): Linea $calculo */
    private function escribir(Declaracion $d, string $linea, callable $calculo): void
    {
        try {
            $d->poner($calculo());
        } catch (Corte $corte) {
            $d->poner($corte->linea(self::S3, $linea));
        }
    }

    /**
     * Suma líneas del Schedule 3 ya escritas. Si ninguna se escribió o todas son "no aplica", la suma es "no aplica";
     * una línea con datos pendientes o no soportada se hereda.
     *
     * @param  list<string>  $lineas
     */
    private function totalDe(Declaracion $d, string $destino, array $lineas, string $nota): void
    {
        $resultado = Apoyo::leerLineas($d, self::S3, $destino, array_map(fn (string $l): array => [self::S3, $l, false], $lineas));

        if ($resultado instanceof Linea) {
            $d->poner($resultado);

            return;
        }

        [$total, $traza, $sinValidar, $aplicadas] = $resultado;

        if ($aplicadas === 0) {
            $d->poner(Linea::noAplica(self::S3, $destino, __('Ninguna línea del piloto aplica.')));

            return;
        }

        $d->poner(Linea::calculada(self::S3, $destino, $total, $traza->conOperacion($nota)->conResultado(Apoyo::nombreLinea(self::S3, $destino), $total), $sinValidar));
    }

    /**
     * @param  list<string>  $casillas
     * @return list<string>
     */
    private static function sinValidarDe(DocumentoEntrada $doc, array $casillas): array
    {
        $referencias = [];

        foreach ($casillas as $casilla) {
            $dato = $doc->casilla($casilla);

            if ($dato !== null && $dato->usaSinValidar()) {
                $referencias[] = $dato->referencia ?? $doc->referencia().':'.$casilla;
            }
        }

        return $referencias;
    }
}
