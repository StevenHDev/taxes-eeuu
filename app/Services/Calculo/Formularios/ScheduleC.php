<?php

namespace App\Services\Calculo\Formularios;

use App\Services\Calculo\Formularios\ScheduleC\DuenoNegocio;
use App\Services\Calculo\Nucleo\Apoyo;
use App\Services\Calculo\Nucleo\Calculadora;
use App\Services\Calculo\Nucleo\Declaracion;
use App\Services\Calculo\Nucleo\Dinero;
use App\Services\Calculo\Nucleo\EntradasDeclaracion;
use App\Services\Calculo\Nucleo\Linea;
use App\Services\Calculo\Nucleo\OrigenTraza;
use App\Services\Calculo\Nucleo\Traza;
use App\Support\ParametrosFiscales;
use App\Support\RespuestaSiNo;
use InvalidArgumentException;
use RuntimeException;

/**
 * Schedule C (Form 1040) de 2025: líneas 1 a 31 (Partes I y II), de un solo negocio.
 *
 * - 1 = 1099-NEC c. 1 (en 2026, c. 1a) + 1099-K c. 1a (decisión A de la ola 3) + ingresos sin
 *   formulario de los registros. Con 1099-MISC c. 5 o 6 sale no soportada: decidir si van a la
 *   línea 1 es del preparador (la decisión A no los incluye).
 * - 2 de los registros; 3 = 1 − 2; 4 no se calcula (Parte III, inventario); 5 = 3 − 4; 6 de los
 *   registros si los hay; 7 = 5 + 6.
 * - 8 a 27b = sumas de los registros por línea, salvo 9 (millaje estándar), 13 (depreciación,
 *   Form 4562: no soportada), 24b (comidas al porcentaje deducible) y 27a (Form 7205: no
 *   soportada).
 * - 28 = 8 a 27b; 29 = 7 − 28; 30 = método simplificado de oficina en casa; 31 = 29 − 30.
 *   Con pérdida, la línea 32 (riesgo de la inversión) la marca el preparador.
 *
 * Fuentes: Schedule C de 2025 (f1040sc, p. 1) e instrucciones (i1040sc de 2025): línea 1 p. 5;
 * líneas 2, 6 y 9 p. 6; línea 24b p. 9 y 10; línea 30 p. 10; hoja del método simplificado p. 11 y
 * 12; línea 31 p. 14.
 *
 * Debe correr ANTES de `ScheduleSe` (línea 2), `Schedule1` (línea 3), `Eitc` y del Form 8995.
 */
final class ScheduleC implements Calculadora
{
    private const FORMULARIO = 'sch-c';

    /** Las líneas que escribe, en el orden del formulario. */
    public const LINEAS = ['1', '2', '3', '4', '5', '6', '7', ...self::GASTOS, '28', '29', '30', '31'];

    /** Parte II, líneas 8 a 27b (las que suman en la 28). */
    public const GASTOS = ['8', '9', '10', '11', '12', '13', '14', '15', '16a', '16b', '17', '18', '19', '20a', '20b', '21', '22', '23', '24a', '24b', '25', '26', '27a', '27b'];

    /** Claves de los registros que indicarían inventario (línea 4 y Parte III, líneas 35 a 42). */
    private const CLAVES_INVENTARIO = ['4', '35', '36', '37', '38', '39', '40', '41', '42'];

    public function formulario(): string
    {
        return self::FORMULARIO;
    }

    public function calcular(EntradasDeclaracion $e, Declaracion $d): void
    {
        $negocio = DuenoNegocio::de($e);

        if (! $negocio->hayNegocio) {
            $this->todas($d, fn (string $l): Linea => Linea::noAplica(self::FORMULARIO, $l, DuenoNegocio::motivoSinNegocio()));

            return;
        }

        if ($negocio->dosDuenos) {
            $this->todas($d, fn (string $l): Linea => Linea::noSoportada(self::FORMULARIO, $l, DuenoNegocio::motivoDosDuenos()));

            return;
        }

        $this->linea1($e, $d);
        $this->deRegistros($e, $d, '2', __('Devoluciones y descuentos (registros del negocio)'));
        $this->resta($d, '3', '1', '2', __('Línea 1 menos línea 2.'));
        $this->linea4($e, $d);
        $this->resta($d, '5', '3', '4', __('Línea 3 menos línea 4.'));
        $this->deRegistros($e, $d, '6', __('Otros ingresos del negocio (registros)'));
        Apoyo::sumar($d, self::FORMULARIO, '7', [[self::FORMULARIO, '5'], [self::FORMULARIO, '6']], Apoyo::nombreLinea(self::FORMULARIO, '7'));

        foreach (self::GASTOS as $linea) {
            match ($linea) {
                '9' => $this->linea9($e, $d),
                '13' => $this->noSoportadaSiHay($e, $d, '13', __('Depreciación y sección 179: se calcula en el Form 4562, que el piloto no hace. La calcula el preparador (F7).')),
                '24b' => $this->linea24b($e, $d),
                '27a' => $this->noSoportadaSiHay($e, $d, '27a', __('Deducción por edificios comerciales eficientes en energía: se calcula en el Form 7205, que el piloto no hace. La revisa el preparador.')),
                default => $this->deRegistros($e, $d, $linea, $this->etiquetaGasto($linea)),
            };
        }

        Apoyo::sumar($d, self::FORMULARIO, '28', array_map(fn (string $l): array => [self::FORMULARIO, $l], self::GASTOS), Apoyo::nombreLinea(self::FORMULARIO, '28'));
        $this->resta($d, '29', '7', '28', __('Línea 7 menos línea 28.'));
        $this->linea30($e, $d);
        $this->linea31($d);
    }

    /** @param callable(string): Linea $linea */
    private function todas(Declaracion $d, callable $linea): void
    {
        foreach (self::LINEAS as $l) {
            $d->poner($linea($l));
        }
    }

    /**
     * Línea 1: "Enter gross receipts from your trade or business" y "be sure line 1 includes
     * amounts properly shown on your Forms 1099-NEC" (i1040sc de 2025, p. 5).
     */
    private function linea1(EntradasDeclaracion $e, Declaracion $d): void
    {
        $misc = array_values(array_intersect(DuenoNegocio::CASILLAS_MISC_NEGOCIO, array_keys($e->datos)));

        if ($misc !== []) {
            $d->poner(Linea::noSoportada(self::FORMULARIO, '1', __('Hay un 1099-MISC con ingresos de barcos de pesca (casilla 5) o pagos médicos (casilla 6): el preparador decide si van a la línea 1 del Schedule C.')));

            return;
        }

        $lectura = Apoyo::leerDatos($e, self::FORMULARIO, '1', [
            // En 2026 la compensación de no empleado del 1099-NEC pasa a la casilla 1a (catálogo de 2026).
            '1099-nec:1' => __('1099-NEC, casilla 1 (compensación de no empleado)'),
            '1099-nec:1a' => __('1099-NEC, casilla 1a (compensación de no empleado)'),
            '1099-k:1a' => __('1099-K, casilla 1a (monto bruto de pagos con tarjeta o de terceros)'),
        ]);

        if ($lectura instanceof Linea) {
            $d->poner($lectura);

            return;
        }

        [$total, $traza, $sinValidar] = $lectura ?? [Dinero::deCentavos(0), Traza::vacia(), []];
        $sinFormulario = $e->sumasRegistros['1-sin-formulario'] ?? null;

        if ($lectura === null && $sinFormulario === null) {
            $d->poner(Linea::faltaDato(self::FORMULARIO, '1', __('Faltan los ingresos del negocio: no hay 1099-NEC, 1099-K ni ingresos sin formulario en los registros.')));

            return;
        }

        if ($sinFormulario !== null) {
            $total = $total->mas($sinFormulario);
            $traza = $traza->conEntrada(__('Ingresos sin formulario (registros del negocio)'), $sinFormulario, OrigenTraza::Leido, 'registros-ingresos-gastos:1-sin-formulario');
        }

        $d->poner(Linea::calculada(self::FORMULARIO, '1', $total, $traza->conResultado(Apoyo::nombreLinea(self::FORMULARIO, '1'), $total), $sinValidar));
    }

    /**
     * Línea 4 (costo de la mercancía vendida, Parte III): no es del piloto. No soportada si hay
     * señales de inventario: registros de la Parte III o un 1099 que dice que el pagador le vendió
     * productos para reventa (1099-NEC c. 2, 1099-MISC c. 7).
     */
    private function linea4(EntradasDeclaracion $e, Declaracion $d): void
    {
        $hayInventario = array_intersect(self::CLAVES_INVENTARIO, array_keys($e->sumasRegistros)) !== []
            || RespuestaSiNo::esAfirmativo($e->datos['1099-nec:2']->valor ?? false)
            || RespuestaSiNo::esAfirmativo($e->datos['1099-misc:7']->valor ?? false);

        $d->poner($hayInventario
            ? Linea::noSoportada(self::FORMULARIO, '4', __('Hay datos de inventario o de ventas para reventa: el costo de la mercancía vendida (Parte III) lo calcula el preparador.'))
            : Linea::noAplica(self::FORMULARIO, '4', __('Sin inventario: no hay costo de la mercancía vendida.')));
    }

    /** Una línea que sale tal cual de la suma de los registros; sin registros, no aplica. */
    private function deRegistros(EntradasDeclaracion $e, Declaracion $d, string $linea, string $etiqueta): void
    {
        $valor = $e->sumasRegistros[$linea] ?? null;

        if ($valor === null) {
            $d->poner(Linea::noAplica(self::FORMULARIO, $linea, __('Sin datos de esta línea en los registros del negocio.')));

            return;
        }

        $traza = Traza::vacia()
            ->conEntrada($etiqueta, $valor, OrigenTraza::Leido, 'registros-ingresos-gastos:'.$linea)
            ->conResultado(Apoyo::nombreLinea(self::FORMULARIO, $linea), $valor);

        $d->poner(Linea::calculada(self::FORMULARIO, $linea, $valor, $traza));
    }

    private function noSoportadaSiHay(EntradasDeclaracion $e, Declaracion $d, string $linea, string $motivo): void
    {
        $d->poner(isset($e->sumasRegistros[$linea])
            ? Linea::noSoportada(self::FORMULARIO, $linea, $motivo)
            : Linea::noAplica(self::FORMULARIO, $linea, __('Sin datos de esta línea en los registros del negocio.')));
    }

    /**
     * Línea 9: con la tarifa estándar, "Multiply the number of business miles driven by 0.70"
     * (i1040sc de 2025, p. 6; la tarifa sale de `schedule_c.millaje_estandar`). Quien usa la
     * tarifa estándar no deduce además los gastos reales del vehículo ("Do not deduct
     * depreciation, rent or lease payments, or your actual operating expenses"); si los registros
     * traen las dos cosas, elige el preparador.
     */
    private function linea9(EntradasDeclaracion $e, Declaracion $d): void
    {
        $millas = $e->sumasRegistros['millas'] ?? null;

        if ($millas === null) {
            $this->deRegistros($e, $d, '9', __('Gastos de auto y camioneta (registros del negocio)'));

            return;
        }

        if (isset($e->sumasRegistros['9'])) {
            $d->poner(Linea::faltaDato(self::FORMULARIO, '9', __('Hay millas del negocio y también gastos reales del vehículo: el preparador elige la tarifa estándar o los gastos reales.')));

            return;
        }

        try {
            $tarifa = (string) ParametrosFiscales::valorRequerido($e->taxYear, 'schedule_c', 'millaje_estandar');
            // Las millas llegan como cantidad dentro de un Dinero (9.800 millas = 9.800,00): por la tarifa en dólares da dólares.
            $monto = $millas->por($tarifa);
        } catch (RuntimeException|InvalidArgumentException $excepcion) {
            $d->poner(Linea::faltaDato(self::FORMULARIO, '9', $this->motivoParametro($e->taxYear, 'schedule_c.millaje_estandar', $excepcion)));

            return;
        }

        $traza = Traza::vacia()
            ->conEntrada(__('Millas del negocio (registros del negocio, cantidad)'), $this->cantidad($millas), OrigenTraza::Leido, 'registros-ingresos-gastos:millas')
            ->conParametro(__('Tarifa estándar de millaje del negocio (USD por milla)'), $tarifa, 'schedule_c.millaje_estandar')
            ->conOperacion(__('Millas por la tarifa estándar (instrucciones del Schedule C, línea 9).'), $monto)
            ->conOperacion(__('Estacionamiento y peajes del negocio se suman a esta línea: si los hay, los agrega el preparador.'))
            ->conResultado(Apoyo::nombreLinea(self::FORMULARIO, '9'), $monto);

        $d->poner(Linea::calculada(self::FORMULARIO, '9', $monto, $traza));
    }

    /**
     * Línea 24b: "In most cases, you can deduct only 50% of your business meal expenses"
     * (i1040sc de 2025, p. 10, "Amount of deduction"; porcentaje de
     * `schedule_c.comidas_porcentaje_deducible`). El 80 % de quien está sujeto a los límites de
     * horas del DOT no se modela.
     */
    private function linea24b(EntradasDeclaracion $e, Declaracion $d): void
    {
        $comidas = $e->sumasRegistros['24b'] ?? null;

        if ($comidas === null) {
            $d->poner(Linea::noAplica(self::FORMULARIO, '24b', __('Sin datos de esta línea en los registros del negocio.')));

            return;
        }

        try {
            $porcentaje = (string) ParametrosFiscales::valorRequerido($e->taxYear, 'schedule_c', 'comidas_porcentaje_deducible');
            $monto = $comidas->por($porcentaje);
        } catch (RuntimeException|InvalidArgumentException $excepcion) {
            $d->poner(Linea::faltaDato(self::FORMULARIO, '24b', $this->motivoParametro($e->taxYear, 'schedule_c.comidas_porcentaje_deducible', $excepcion)));

            return;
        }

        $traza = Traza::vacia()
            ->conEntrada(__('Comidas del negocio (registros del negocio)'), $comidas, OrigenTraza::Leido, 'registros-ingresos-gastos:24b')
            ->conParametro(__('Porcentaje deducible de las comidas del negocio'), $porcentaje, 'schedule_c.comidas_porcentaje_deducible')
            ->conOperacion(__('Comidas por el porcentaje deducible (instrucciones del Schedule C, línea 24b).'), $monto)
            ->conResultado(Apoyo::nombreLinea(self::FORMULARIO, '24b'), $monto);

        $d->poner(Linea::calculada(self::FORMULARIO, '24b', $monto, $traza));
    }

    /**
     * Línea 30 con la hoja del método simplificado (i1040sc de 2025, p. 11 y 12):
     * 1 = límite por el ingreso bruto (si todo el ingreso viene del uso de la casa: la línea 29;
     * las ganancias y pérdidas de los Form 8949 y 4797 no son del piloto); 2 = pies cuadrados, sin
     * pasar de 300; 3a = USD 5; 3b = 1,0 (guardería no exclusiva: no es del piloto);
     * 4 = 2 × 3c; 5 = la menor entre 1 y 4, y si es cero o menos, cero.
     */
    private function linea30(EntradasDeclaracion $e, Declaracion $d): void
    {
        $pies = $e->sumasRegistros['pies-oficina'] ?? null;

        if ($pies === null) {
            $d->poner(Linea::noAplica(self::FORMULARIO, '30', __('Sin pies cuadrados de oficina en casa en los registros.')));

            return;
        }

        $lectura = Apoyo::leerVarias($d, [[self::FORMULARIO, '29']], self::FORMULARIO, '30');

        if ($lectura instanceof Linea) {
            $d->poner($lectura);

            return;
        }

        [[$linea29], $traza, $sinValidar] = $lectura;

        try {
            $tarifa = Apoyo::parametroEnDolares($e->taxYear, 'schedule_c', 'oficina_casa_tarifa_pie_cuadrado');
        } catch (RuntimeException|InvalidArgumentException $excepcion) {
            $d->poner(Linea::faltaDato(self::FORMULARIO, '30', $this->motivoParametro($e->taxYear, 'schedule_c.oficina_casa_tarifa_pie_cuadrado', $excepcion)));

            return;
        }

        try {
            $tope = Apoyo::parametroEnDolares($e->taxYear, 'schedule_c', 'oficina_casa_tope_pies_cuadrados');
        } catch (RuntimeException|InvalidArgumentException $excepcion) {
            $d->poner(Linea::faltaDato(self::FORMULARIO, '30', $this->motivoParametro($e->taxYear, 'schedule_c.oficina_casa_tope_pies_cuadrados', $excepcion)));

            return;
        }

        $cero = Dinero::deCentavos(0);
        // Pies y tope son cantidades guardadas como Dinero (300 pies = 300,00); por la tarifa en dólares da dólares.
        $piesPermitidos = $pies->min($tope)->max($cero);
        $montoHoja4 = $piesPermitidos->por($this->dolaresComoFactor($tarifa));
        $monto = $linea29->min($montoHoja4)->max($cero);

        $traza = $traza
            ->conEntrada(__('Pies cuadrados de la oficina en casa (registros del negocio, cantidad)'), $this->cantidad($pies), OrigenTraza::Leido, 'registros-ingresos-gastos:pies-oficina')
            ->conParametro(__('Tope de pies cuadrados del método simplificado'), $this->cantidad($tope), 'schedule_c.oficina_casa_tope_pies_cuadrados')
            ->conParametro(__('Tarifa por pie cuadrado del método simplificado'), $tarifa, 'schedule_c.oficina_casa_tarifa_pie_cuadrado')
            ->conOperacion(__('Hoja del método simplificado, línea 1: límite por el ingreso bruto = línea 29 (se supone que todo el ingreso viene del negocio en la casa; si no, lo ajusta el preparador).'), $linea29)
            ->conOperacion(__('Hoja del método simplificado, línea 2: pies cuadrados, sin pasar del tope.'), $this->cantidad($piesPermitidos))
            ->conOperacion(__('Hoja del método simplificado, línea 4: pies por la tarifa.'), $montoHoja4)
            ->conOperacion(__('Hoja del método simplificado, línea 5: la menor entre las líneas 1 y 4; si es cero o menos, cero.'), $monto);

        if (! isset($e->sumasRegistros['pies-casa'])) {
            $traza = $traza->conOperacion(__('Faltan los pies cuadrados de toda la casa, que el formulario pide junto a la línea 30.'));
        }

        $d->poner(Linea::calculada(self::FORMULARIO, '30', $monto, $traza->conResultado(Apoyo::nombreLinea(self::FORMULARIO, '30'), $monto), $sinValidar));
    }

    /** Línea 31 = 29 − 30. Con pérdida, la línea 32 (32a o 32b, riesgo de la inversión) la marca el preparador (i1040sc de 2025, p. 14). */
    private function linea31(Declaracion $d): void
    {
        $this->resta($d, '31', '29', '30', __('Línea 29 menos línea 30.'));
        $linea = $d->linea(self::FORMULARIO, '31');

        if ($linea?->valor instanceof Dinero && $linea->valor->esNegativo()) {
            $d->poner(Linea::calculada(
                self::FORMULARIO,
                '31',
                $linea->valor,
                $linea->traza->conOperacion(__('Con pérdida, el preparador marca la línea 32a o 32b (riesgo de la inversión) y revisa los límites de pérdidas (Form 6198, 8582 y 461).')),
                $linea->datosSinValidar,
            ));
        }
    }

    /** `$destino` = `$a` − `$b`, ambas líneas del Schedule C; propaga lo que falte. */
    private function resta(Declaracion $d, string $destino, string $a, string $b, string $operacion): void
    {
        $lectura = Apoyo::leerVarias($d, [[self::FORMULARIO, $a], [self::FORMULARIO, $b]], self::FORMULARIO, $destino);

        if ($lectura instanceof Linea) {
            $d->poner($lectura);

            return;
        }

        [[$minuendo, $sustraendo], $traza, $sinValidar] = $lectura;
        $total = $minuendo->menos($sustraendo);

        $d->poner(Linea::calculada(self::FORMULARIO, $destino, $total, $traza->conOperacion($operacion)->conResultado(Apoyo::nombreLinea(self::FORMULARIO, $destino), $total), $sinValidar));
    }

    private function etiquetaGasto(string $linea): string
    {
        $nombre = match ($linea) {
            '8' => __('Publicidad'),
            '10' => __('Comisiones y cuotas'),
            '11' => __('Trabajo contratado'),
            '12' => __('Agotamiento'),
            '14' => __('Programas de beneficios para empleados'),
            '15' => __('Seguros (que no sean de salud)'),
            '16a' => __('Intereses de hipoteca pagados a bancos'),
            '16b' => __('Otros intereses'),
            '17' => __('Servicios legales y profesionales'),
            '18' => __('Gastos de oficina'),
            '19' => __('Planes de pensión y de participación en ganancias'),
            '20a' => __('Alquiler de vehículos, maquinaria y equipo'),
            '20b' => __('Alquiler de otros bienes del negocio'),
            '21' => __('Reparaciones y mantenimiento'),
            '22' => __('Materiales y suministros'),
            '23' => __('Impuestos y licencias'),
            '24a' => __('Viajes'),
            '25' => __('Servicios públicos'),
            '26' => __('Sueldos'),
            '27b' => __('Otros gastos'),
            default => Apoyo::nombreLinea(self::FORMULARIO, $linea),
        };

        return __(':gasto (registros del negocio)', ['gasto' => $nombre]);
    }

    /** Una cantidad (millas, pies) guardada como Dinero, como texto para la traza: "9800". */
    private function cantidad(Dinero $cantidad): string
    {
        return (string) intdiv($cantidad->centavos(), 100);
    }

    /** Un monto en dólares como factor de texto para `Dinero::por()` ("5.00"). */
    private function dolaresComoFactor(Dinero $dolares): string
    {
        $centavos = $dolares->centavos();

        return intdiv($centavos, 100).'.'.str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT);
    }

    private function motivoParametro(int $taxYear, string $clave, RuntimeException|InvalidArgumentException $excepcion): string
    {
        return $excepcion instanceof RuntimeException
            ? Apoyo::motivoSinParametro($taxYear, $excepcion)
            : __('No se puede calcular: el parámetro :parametro de :anio no es un número válido (Catálogo → Parámetros).', ['parametro' => $clave, 'anio' => (string) $taxYear]);
    }
}
