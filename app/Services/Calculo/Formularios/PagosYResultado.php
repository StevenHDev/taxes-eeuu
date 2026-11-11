<?php

namespace App\Services\Calculo\Formularios;

use App\Enums\Expediente\EstadoCivilIrs;
use App\Services\Calculo\Nucleo\Apoyo;
use App\Services\Calculo\Nucleo\Calculadora;
use App\Services\Calculo\Nucleo\Declaracion;
use App\Services\Calculo\Nucleo\Dinero;
use App\Services\Calculo\Nucleo\EntradasDeclaracion;
use App\Services\Calculo\Nucleo\Linea;
use App\Services\Calculo\Nucleo\OrigenTraza;
use App\Services\Calculo\Nucleo\Traza;
use App\Support\ParametrosFiscales;
use RuntimeException;

/**
 * Pagos y resultado del Form 1040 de 2025 (p. 2): líneas 25a a 34, 35a, 36, 37 y 38 (instrucciones i1040gi,
 * p. 39 y 63 a 65). La 27a la escribe `Eitc`.
 *
 * - 25a: casilla 2 de los W-2. 25b: casilla 4 de los 1099 y 6 del SSA-1099 (las del catálogo).
 * - 25c: W-2G casilla 4 y el Medicare adicional retenido del Form 8959, parte V (líneas 19 a 24); con
 *   K-1 no se soporta (la retención del K-1 no se lee).
 * - 26: comprobantes de pago estimado aplicados al año. Con MFS los pagos conjuntos se reparten: lo
 *   decide el preparador.
 * - 28 copia la 27 del 8812; 29 (AOTC, Form 8863) no se soporta si hay 1098-T; 30 (adopción, Form 8839)
 *   no se soporta si F12 = sí; 31 copia la 15 del Schedule 3.
 * - 35a: todo el sobrepago, salvo que el cliente pida aplicarlo al año siguiente (G3): eso lo decide el
 *   preparador. Las líneas 35b a 35d (cuenta bancaria) no se leen ni se escriben.
 * - 38 (multa por pago estimado insuficiente, Form 2210): no soportada si podría aplicar (se debe saldo, o
 *   hubo pagos estimados); no hay parámetros del umbral de la multa.
 *
 * Corre al final: necesita la 24 (`ImpuestoYSchedule2`), la 27a (`Eitc`), la 27 del 8812 y la 15 del Schedule 3.
 */
final class PagosYResultado implements Calculadora
{
    private const F = '1040';

    /** 25b (i1040gi p. 39): casilla 4 de los Form 1099 y casilla 6 del SSA-1099, como en el catálogo de la línea. */
    private const RETENCION_1099 = [
        '1099-nec:4' => 'Form 1099-NEC, casilla 4',
        '1099-k:4' => 'Form 1099-K, casilla 4',
        '1099-misc:4' => 'Form 1099-MISC, casilla 4',
        '1099-r:4' => 'Form 1099-R, casilla 4',
        '1099-g:4' => 'Form 1099-G, casilla 4',
        '1099-int:4' => 'Form 1099-INT, casilla 4',
        '1099-div:4' => 'Form 1099-DIV, casilla 4',
        'ssa-1099:6' => 'SSA-1099, casilla 6',
    ];

    /** Form 8959 (2025), línea 21: "Multiply line 20 by 1.45% (0.0145)". No está en parámetros todavía. */
    private const PARAMETRO_MEDICARE = ['additional_medicare_tax', 'tasa_medicare_regular'];

    public function formulario(): string
    {
        return self::F;
    }

    public function calcular(EntradasDeclaracion $e, Declaracion $d): void
    {
        $this->retencion($e, $d, '25a', ['w2:2' => __('W-2, casilla 2 (impuesto federal retenido)')], __('Sin W-2 con impuesto federal retenido.'));
        $this->retencion($e, $d, '25b', array_map(fn (string $t): string => __($t), self::RETENCION_1099), __('Sin 1099 con impuesto federal retenido.'));
        $this->linea25c($e, $d);
        Apoyo::sumar($d, self::F, '25d', [[self::F, '25a'], [self::F, '25b'], [self::F, '25c']], Apoyo::nombreLinea(self::F, '25d'));
        $this->linea26($e, $d);

        Apoyo::copiar($d, self::F, '28', 'sch-8812', '27');
        $this->linea29($e, $d);
        $this->linea30($e, $d);
        Apoyo::copiar($d, self::F, '31', 'sch-3', '15');
        Apoyo::sumar($d, self::F, '32', [[self::F, '27a'], [self::F, '28'], [self::F, '29'], [self::F, '30'], [self::F, '31']], Apoyo::nombreLinea(self::F, '32'));
        Apoyo::sumar($d, self::F, '33', [[self::F, '25d'], [self::F, '26'], [self::F, '32']], Apoyo::nombreLinea(self::F, '33'));

        $this->resultado($e, $d);
    }

    /**
     * 25a y 25b: suma de las casillas de retención; "no aplica" si ningún documento la trae.
     *
     * @param  array<string, string>  $etiquetas
     */
    private function retencion(EntradasDeclaracion $e, Declaracion $d, string $linea, array $etiquetas, string $sinDatos): void
    {
        $lectura = Apoyo::leerDatos($e, self::F, $linea, $etiquetas);

        if ($lectura instanceof Linea) {
            $d->poner($lectura);

            return;
        }

        if ($lectura === null) {
            $d->poner(Linea::noAplica(self::F, $linea, $sinDatos));

            return;
        }

        [$total, $traza, $sinValidar] = $lectura;
        $d->poner(Linea::calculada(self::F, $linea, $total, $traza->conResultado(Apoyo::nombreLinea(self::F, $linea), $total), $sinValidar));
    }

    /**
     * 25c (i1040gi p. 38 y 39): W-2G casilla 4 + Form 8959 línea 24. Parte V del 8959: 19 = W-2 casilla 6;
     * 20 = W-2 casilla 5 (línea 1); 21 = 20 × 1,45 %; 22 = 19 − 21 (mínimo cero); 23 (RRTA) no es del piloto.
     */
    private function linea25c(EntradasDeclaracion $e, Declaracion $d): void
    {
        if (Apoyo::hayDatos($e, 'k-1')) {
            $d->poner(Linea::noSoportada(self::F, '25c', __('Hay un K-1: su retención federal va a la línea 25c y no se lee en el piloto.')));

            return;
        }

        $w2g = Apoyo::leerDatos($e, self::F, '25c', ['w2g:4' => __('W-2G, casilla 4 (impuesto federal retenido)')]);

        if ($w2g instanceof Linea) {
            $d->poner($w2g);

            return;
        }

        $cero = Dinero::deCentavos(0);
        [$total, $traza, $sinValidar] = $w2g ?? [$cero, Traza::vacia(), []];
        $medicare = $e->datos['w2:6'] ?? null;

        if ($medicare === null) {
            if ($w2g === null) {
                $d->poner(Linea::noAplica(self::F, '25c', __('Sin W-2G con retención ni Medicare retenido en los W-2 (casilla 6).')));

                return;
            }

            $traza = $traza->conOperacion(__('Sin Medicare retenido en los W-2 (casilla 6): el Form 8959, parte V, da cero.'));
            $d->poner(Linea::calculada(self::F, '25c', $total, $traza->conResultado(Apoyo::nombreLinea(self::F, '25c'), $total), $sinValidar));

            return;
        }

        $salarios = $e->datos['w2:5'] ?? null;

        if (! $medicare->valor instanceof Dinero || ! $salarios?->valor instanceof Dinero) {
            $d->poner(Linea::faltaDato(self::F, '25c', __('Para el Form 8959, parte V, faltan los montos de las casillas 5 y 6 de los W-2.')));

            return;
        }

        try {
            $tasa = (string) ParametrosFiscales::valorRequerido($e->taxYear, ...self::PARAMETRO_MEDICARE);
        } catch (RuntimeException $excepcion) {
            $d->poner(Linea::faltaDato(self::F, '25c', Apoyo::motivoSinParametro($e->taxYear, $excepcion)));

            return;
        }

        $regular = $salarios->valor->por($tasa);
        $adicional = $medicare->valor->menos($regular)->max($cero);
        $total = $total->mas($adicional);
        $clave = implode('.', self::PARAMETRO_MEDICARE);

        foreach (['w2:6' => $medicare, 'w2:5' => $salarios] as $referencia => $dato) {
            if ($dato->usaSinValidar()) {
                $sinValidar[] = $dato->referencia ?? $referencia;
            }
        }

        $traza = $traza
            ->conEntrada(__('Form 8959, línea 19: W-2, casilla 6 (Medicare retenido)'), $medicare->valor, $medicare->origen, $medicare->referencia ?? 'w2:6')
            ->conEntrada(__('Form 8959, línea 20: W-2, casilla 5 (salarios de Medicare)'), $salarios->valor, $salarios->origen, $salarios->referencia ?? 'w2:5')
            ->conParametro(__('Tasa regular de Medicare del empleado (Form 8959, línea 21)'), $tasa, $clave)
            ->conOperacion(__('Form 8959, línea 22: línea 19 menos la línea 20 por la tasa; si es cero o menos, cero. La línea 23 (RRTA) no es del piloto.'), $adicional);

        $d->poner(Linea::calculada(self::F, '25c', $total, $traza->conResultado(Apoyo::nombreLinea(self::F, '25c'), $total), array_values(array_unique($sinValidar))));
    }

    /**
     * 26 (i1040gi p. 39): pagos de impuesto estimado de 2025 (comprobantes con tipo "estimado" y año aplicado
     * = año de la declaración) más el sobrepago de 2024 aplicado a 2025, que no se lee (va en la misma G1).
     */
    private function linea26(EntradasDeclaracion $e, Declaracion $d): void
    {
        $comprobantes = $e->documentos('comprobantes-pagos');
        $g1 = ($e->respuestas['G1'] ?? null)?->valor;
        $dijoQueSi = $g1 === true || $g1 === 'si';

        if ($comprobantes === [] && Apoyo::hayDatos($e, 'comprobantes-pagos')) {
            $d->poner(Linea::faltaDato(self::F, '26', __('Faltan los comprobantes de pago por separado (tipo y año de cada uno): con el total sumado no se sabe cuáles son pagos estimados.')));

            return;
        }

        $total = Dinero::deCentavos(0);
        $traza = Traza::vacia();
        $sinValidar = [];
        $contados = 0;

        foreach ($comprobantes as $comprobante) {
            $referencia = $comprobante->referencia();
            $tipo = $comprobante->casilla('tipo-pago')?->valor;

            if (! is_string($tipo) || $tipo === '') {
                $d->poner(Linea::faltaDato(self::F, '26', __('Falta el tipo de pago del comprobante :comprobante (estimado, extensión u otro).', ['comprobante' => $referencia])));

                return;
            }

            if ($tipo !== 'estimado') {
                $traza = $traza->conOperacion(__('El comprobante :comprobante no es un pago estimado (:tipo): no cuenta en la línea 26.', ['comprobante' => $referencia, 'tipo' => $tipo]));

                continue;
            }

            $anio = $comprobante->casilla('anio-aplicado')?->valor;

            if (! is_string($anio) || preg_match('/^\d{4}$/', trim($anio)) !== 1) {
                $d->poner(Linea::faltaDato(self::F, '26', __('Falta el año al que se aplica el pago estimado del comprobante :comprobante.', ['comprobante' => $referencia])));

                return;
            }

            if ((int) trim($anio) !== $e->taxYear) {
                $traza = $traza->conOperacion(__('El pago estimado del comprobante :comprobante es de otro año: no cuenta en la línea 26.', ['comprobante' => $referencia]));

                continue;
            }

            $monto = $comprobante->casilla('monto');

            if ($monto === null || ! $monto->valor instanceof Dinero) {
                $d->poner(Linea::faltaDato(self::F, '26', __('Falta el monto del comprobante :comprobante.', ['comprobante' => $referencia])));

                return;
            }

            $contados++;
            $total = $total->mas($monto->valor);
            $traza = $traza->conEntrada(__('Pago estimado del comprobante :comprobante', ['comprobante' => $referencia]), $monto->valor, $monto->origen, $monto->referencia ?? $referencia.':monto');

            if ($monto->usaSinValidar()) {
                $sinValidar[] = $monto->referencia ?? $referencia.':monto';
            }
        }

        if ($contados === 0) {
            if ($dijoQueSi) {
                $d->poner(Linea::faltaDato(self::F, '26', __('En G1 dijiste que hiciste pagos estimados o aplicaste el reembolso del año pasado, pero no hay comprobantes de pagos estimados del año.')));

                return;
            }

            $d->poner(Linea::noAplica(self::F, '26', __('Sin pagos estimados del año.'), $traza));

            return;
        }

        if ($e->estadoCivil === EstadoCivilIrs::Mfs) {
            $d->poner(Linea::faltaDato(self::F, '26', __('Con MFS, si los pagos estimados fueron conjuntos se reparten entre los cónyuges (instrucciones del 1040, línea 26): lo decide el preparador.'), $traza));

            return;
        }

        $traza = $traza->conOperacion(__('El sobrepago de la declaración del año pasado aplicado a este año no se lee de ningún documento: si lo hubo, lo suma el preparador.'));

        $d->poner(Linea::calculada(self::F, '26', $total, $traza->conResultado(Apoyo::nombreLinea(self::F, '26'), $total), $sinValidar));
    }

    /** 29: crédito de oportunidad americana (Form 8863): no es del piloto. */
    private function linea29(EntradasDeclaracion $e, Declaracion $d): void
    {
        if (Apoyo::hayDatos($e, '1098-t')) {
            $d->poner(Linea::noSoportada(self::F, '29', __('Hay un 1098-T: el crédito de oportunidad americana (Form 8863) no es del piloto.')));

            return;
        }

        $d->poner(Linea::noAplica(self::F, '29', __('Sin 1098-T.')));
    }

    /** 30: crédito reembolsable por adopción (Form 8839, línea 13): no es del piloto. */
    private function linea30(EntradasDeclaracion $e, Declaracion $d): void
    {
        $f12 = ($e->respuestas['F12'] ?? null)?->valor;

        if ($f12 === true || $f12 === 'si') {
            $d->poner(Linea::noSoportada(self::F, '30', __('Adoptaste o pagaste gastos de adopción (F12): el crédito por adopción (Form 8839) no es del piloto.')));

            return;
        }

        $d->poner(Linea::noAplica(self::F, '30', __('Sin adopción.')));
    }

    /** 34, 35a, 36, 37 y 38 (i1040gi p. 63 a 65). */
    private function resultado(EntradasDeclaracion $e, Declaracion $d): void
    {
        $lectura = Apoyo::leerVarias($d, [[self::F, '33'], [self::F, '24']], self::F, '34');

        if ($lectura instanceof Linea) {
            foreach (['34', '35a', '36', '37', '38'] as $linea) {
                $d->poner(new Linea(self::F, $linea, null, $lectura->estado, $lectura->motivo, Traza::vacia()));
            }

            return;
        }

        [[$pagos, $impuesto], $traza, $sinValidar] = $lectura;
        $hayEstimados = ($d->linea(self::F, '26')?->valor instanceof Dinero) && ! $d->linea(self::F, '26')->valor->esCero();

        if ($pagos->centavos() > $impuesto->centavos()) {
            $sobrepago = $pagos->menos($impuesto);
            $d->poner(Linea::calculada(self::F, '34', $sobrepago, $traza
                ->conOperacion(__('Línea 33 menos línea 24.'))
                ->conResultado(Apoyo::nombreLinea(self::F, '34'), $sobrepago), $sinValidar));
            $d->poner(Linea::noAplica(self::F, '37', __('Hay pago de más: no se debe nada.')));
            $this->reembolso($e, $d, $sobrepago, $sinValidar);
            $this->linea38($d, $hayEstimados, false);

            return;
        }

        $saldo = $impuesto->menos($pagos);
        $motivo = __('No hay pago de más.');

        foreach (['34', '35a', '36'] as $linea) {
            $d->poner(Linea::noAplica(self::F, $linea, $motivo));
        }

        $d->poner(Linea::calculada(self::F, '37', $saldo, $traza
            ->conOperacion(__('Línea 24 menos línea 33. Si el preparador calcula multa (línea 38), se suma aquí.'))
            ->conResultado(Apoyo::nombreLinea(self::F, '37'), $saldo), $sinValidar));
        $this->linea38($d, $hayEstimados, ! $saldo->esCero());
    }

    /**
     * 35a y 36: todo el sobrepago se reembolsa, salvo que G3 pida aplicarlo al año siguiente. G3 es sensible:
     * la traza solo dice si hay respuesta, nunca cuál.
     *
     * @param  list<string>  $sinValidar
     */
    private function reembolso(EntradasDeclaracion $e, Declaracion $d, Dinero $sobrepago, array $sinValidar): void
    {
        $g3 = ($e->respuestas['G3'] ?? null)?->valor;

        if ($g3 === 'aplicar_anio_siguiente') {
            $motivo = __('Pediste aplicar el reembolso al año siguiente (G3): el preparador decide cuánto va a la línea 36 y cuánto a la 35a.');
            $d->poner(Linea::noSoportada(self::F, '35a', $motivo));
            $d->poner(Linea::noSoportada(self::F, '36', $motivo));

            return;
        }

        $d->poner(Linea::noAplica(self::F, '36', __('No se aplica nada al impuesto estimado del año siguiente.')));

        $traza = Traza::vacia()
            ->conEntrada(Apoyo::nombreLinea(self::F, '34'), $sobrepago, OrigenTraza::Linea, self::F.':34')
            ->conEntrada($g3 === null ? __('Sin respuesta sobre cómo recibir el reembolso (G3)') : __('Hay respuesta sobre cómo recibir el reembolso (G3)'), null, OrigenTraza::Respuesta, 'G3')
            ->conOperacion(__('Todo el pago de más se reembolsa. Los datos bancarios (35b a 35d) no se calculan aquí.'));

        $d->poner(Linea::calculada(self::F, '35a', $sobrepago, $traza->conResultado(Apoyo::nombreLinea(self::F, '35a'), $sobrepago), $sinValidar));
    }

    /**
     * 38 (i1040gi p. 65): puede haber multa si la 37 es de al menos USD 1.000 y más del 10 % del impuesto, o si
     * no se pagó suficiente estimado a tiempo (aun con reembolso). Sin esos umbrales en parámetros, se marca no
     * soportada cuando hay saldo o pagos estimados; con reembolso y sin pagos estimados, no aplica.
     */
    private function linea38(Declaracion $d, bool $hayEstimados, bool $debe): void
    {
        if ($debe) {
            $d->poner(Linea::noSoportada(self::F, '38', __('Hay saldo a pagar: el preparador revisa si aplica la multa por pago estimado insuficiente (Form 2210).')));

            return;
        }

        if ($hayEstimados) {
            $d->poner(Linea::noSoportada(self::F, '38', __('Hubo pagos estimados: el preparador revisa si se pagaron a tiempo (multa del Form 2210).')));

            return;
        }

        $d->poner(Linea::noAplica(self::F, '38', __('Sin saldo a pagar ni pagos estimados: no hay multa por pago estimado insuficiente.')));
    }
}
