<?php

namespace App\Services\Calculo\Formularios;

use App\Enums\Expediente\ClasificacionPersona;
use App\Enums\Expediente\EstadoCivilIrs;
use App\Services\Calculo\Formularios\Creditos\Corte;
use App\Services\Calculo\Nucleo\Apoyo;
use App\Services\Calculo\Nucleo\Calculadora;
use App\Services\Calculo\Nucleo\Declaracion;
use App\Services\Calculo\Nucleo\Dinero;
use App\Services\Calculo\Nucleo\EntradasDeclaracion;
use App\Services\Calculo\Nucleo\EstadoLinea;
use App\Services\Calculo\Nucleo\Linea;
use App\Services\Calculo\Nucleo\OrigenTraza;
use App\Services\Calculo\Nucleo\PersonaEntrada;
use App\Services\Calculo\Nucleo\RolPersona;
use App\Services\Calculo\Nucleo\Traza;
use App\Support\ParametrosFiscales;
use RuntimeException;

/**
 * Schedule 8812 de 2025 (crédito por hijos y por otros dependientes, y crédito adicional por hijos) y la línea 19
 * del Form 1040. La línea 27 (ACTC) la copia PagosYResultado a la línea 28 del 1040.
 *
 * Fuentes: Schedule 8812 de 2025 (pp. 1 y 2) e instrucciones de 2025 (i1040s8): SSN de quien declara y del hijo
 * (p. 1), edad "under age 17 at the end of 2025" (p. 2), otros dependientes (p. 2), hoja A del límite por el
 * impuesto (p. 4), hoja B (p. 5, solo con créditos que el piloto no tiene), ingreso del trabajo de la línea 18a
 * (Earned Income Worksheet, p. 8) y Parte II-B (p. 3).
 *
 * Corre DESPUÉS de: Ingresos1040 (1z y 11a), la primera pasada de ImpuestoYSchedule2 (línea 18), Eitc (27a),
 * Schedule3 (líneas 2 y 11), ScheduleC (31), Schedule1 (15) y Schedule 2 línea 11. ImpuestoYSchedule2 tiene que
 * volver a correr después para la línea 21 del 1040 (19 + 20).
 *
 * No se pregunta hoy y queda en la traza: que el dependiente sea ciudadano, nacional o residente de EE. UU. y tenga
 * un SSN, ITIN o ATIN (otros dependientes), el pago de combate no gravable (18b) y los ingresos de Puerto Rico y del
 * Form 2555 o 4563 (líneas 2a a 2c).
 */
final class Schedule8812 implements Calculadora
{
    private const F = 'sch-8812';

    private const F1040 = '1040';

    /**
     * "Number of qualifying children under age 17" (Schedule 8812 de 2025, línea 4; i1040s8 de 2025, p. 2: "under age
     * 17 at the end of 2025"). No hay parámetro del año para esta edad: la fija la ley (IRC §24(c)(1)).
     */
    private const EDAD_MENOR_DE = 17;

    /** Parte II-B: "Certain Filers Who Have Three or More Qualifying Children" (Schedule 8812 de 2025, p. 2). */
    private const HIJOS_PARTE_II_B = 3;

    public function formulario(): string
    {
        return self::F;
    }

    public function calcular(EntradasDeclaracion $e, Declaracion $d): void
    {
        try {
            $parteI = $this->parteI($e, $d);
        } catch (Corte $corte) {
            $d->poner($corte->linea(self::F, '14'));
            $d->poner($corte->linea(self::F, '27'));
            Apoyo::copiar($d, self::F1040, '19', self::F, '14');

            return;
        }

        Apoyo::copiar($d, self::F1040, '19', self::F, '14');

        try {
            $this->parteII($e, $d, $parteI);
        } catch (Corte $corte) {
            $d->poner($corte->linea(self::F, '27'));
        }
    }

    // ── Parte I ──

    /** @return array{hijos: int, linea12: Dinero, linea14: Dinero, sinValidar: list<string>} */
    private function parteI(EntradasDeclaracion $e, Declaracion $d): array
    {
        if ($e->estadoCivil === null) {
            throw Corte::falta(Apoyo::motivoSinEstadoCivil());
        }

        $dependientes = array_values(array_filter(
            $e->personas,
            fn (PersonaEntrada $p): bool => $p->rol === RolPersona::Dependiente && $p->clasificacion->esDependiente(),
        ));

        if ($dependientes === []) {
            throw Corte::noAplica(__('Sin dependientes: no hay crédito por hijos ni por otros dependientes.'));
        }

        [$hijos, $otros, $trazaPersonas] = $this->clasificar($e, $dependientes);

        $cero = Dinero::deCentavos(0);
        $conjunta = $e->estadoCivil === EstadoCivilIrs::Mfj;

        // Líneas 1 y 3: ingreso ajustado (1040, línea 11a); las líneas 2a a 2c (Puerto Rico, 2555, 4563) no son del piloto.
        $linea1 = $this->leer($d, self::F1040, '11a', '1');
        [$agi, $sinValidar] = $linea1;
        $this->poner($d, '1', $agi, Traza::vacia()->conEntrada(Apoyo::nombreLinea(self::F1040, '11a'), $agi, OrigenTraza::Linea, self::F1040.':11a'), $sinValidar);
        $this->poner($d, '3', $agi, Traza::vacia()
            ->conEntrada(self::nombreLinea('1'), $agi, OrigenTraza::Linea, self::F.':1')
            ->conOperacion(__('Líneas 1 más 2d; los ingresos excluidos de Puerto Rico y de los Forms 2555 y 4563 (líneas 2a a 2c) no son del piloto.')), $sinValidar);

        // Líneas 4 a 8.
        $montoHijo = $this->parametro($e, 'credito_ctc', 'monto_por_dependiente');
        $montoOtro = $this->parametro($e, 'credito_odc', 'monto_por_dependiente');
        $d->poner(Linea::calculada(self::F, '4', (string) count($hijos), $trazaPersonas->conResultado(self::nombreLinea('4'), (string) count($hijos))));
        $linea5 = Dinero::deCentavos($montoHijo->centavos() * count($hijos));
        $this->poner($d, '5', $linea5, Traza::vacia()->conParametro(__('Crédito por hijo calificado'), $montoHijo, 'credito_ctc.monto_por_dependiente')->conOperacion(__('Línea 4 por el monto por hijo.')));
        $d->poner(Linea::calculada(self::F, '6', (string) count($otros), Traza::vacia()->conResultado(self::nombreLinea('6'), (string) count($otros))));
        $linea7 = Dinero::deCentavos($montoOtro->centavos() * count($otros));
        $this->poner($d, '7', $linea7, Traza::vacia()->conParametro(__('Crédito por otro dependiente'), $montoOtro, 'credito_odc.monto_por_dependiente')->conOperacion(__('Línea 6 por el monto por dependiente.')));
        $linea8 = $linea5->mas($linea7);
        $this->poner($d, '8', $linea8, Traza::vacia()->conOperacion(__('Líneas 5 más 7.')));

        // Líneas 9 a 11: reducción por ingreso (el crédito por otros dependientes usa los mismos umbrales).
        $claveUmbral = $conjunta ? 'phase_out_umbral_mfj' : 'phase_out_umbral_soltero';
        $linea9 = $this->parametro($e, 'credito_ctc', $claveUmbral);
        $this->poner($d, '9', $linea9, Traza::vacia()->conParametro(__('Umbral del ingreso según el estado civil'), $linea9, 'credito_ctc.'.$claveUmbral));

        $exceso = $agi->menos($linea9)->max($cero);
        $milesDeExceso = intdiv($exceso->centavos() + 99999, 100000);
        $linea10 = Dinero::deCentavos($milesDeExceso * 100000);
        $this->poner($d, '10', $linea10, Traza::vacia()->conOperacion(__('Línea 3 menos línea 9; si es cero o menos, cero; si no es múltiplo de USD 1.000, el múltiplo siguiente.')), $sinValidar);

        $reduccion = $this->parametro($e, 'credito_ctc', 'reduccion_por_1000_exceso');
        $linea11 = Dinero::deCentavos($reduccion->centavos() * $milesDeExceso);
        $this->poner($d, '11', $linea11, Traza::vacia()
            ->conParametro(__('Reducción por cada USD 1.000 de exceso (5 %)'), $reduccion, 'credito_ctc.reduccion_por_1000_exceso')
            ->conOperacion(__('Línea 10 por 5 %.')), $sinValidar);

        $linea12 = $linea8->menos($linea11)->max($cero);
        $this->poner($d, '12', $linea12, Traza::vacia()->conOperacion(__('Si la línea 8 es mayor que la 11, la diferencia; si no, no hay crédito.')), $sinValidar);

        if ($linea12->esCero()) {
            $this->poner($d, '14', $cero, Traza::vacia()->conOperacion(__('La línea 8 no es mayor que la 11: no hay crédito por hijos, por otros dependientes ni crédito adicional.')), $sinValidar);

            return ['hijos' => count($hijos), 'linea12' => $linea12, 'linea14' => $cero, 'sinValidar' => $sinValidar];
        }

        // Línea 13: hoja A del límite por el impuesto (i1040s8, p. 4).
        [$linea13, $traza13, $sinValidar13] = $this->hojaA($d);
        $this->poner($d, '13', $linea13, $traza13, $sinValidar13);

        $linea14 = $linea12->min($linea13);
        $sinValidar = array_values(array_unique([...$sinValidar, ...$sinValidar13]));
        $this->poner($d, '14', $linea14, Traza::vacia()
            ->conEntrada(self::nombreLinea('12'), $linea12, OrigenTraza::Linea, self::F.':12')
            ->conEntrada(self::nombreLinea('13'), $linea13, OrigenTraza::Linea, self::F.':13')
            ->conOperacion(__('La menor entre las líneas 12 y 13. Va a la línea 19 del Form 1040.')), $sinValidar);

        return ['hijos' => count($hijos), 'linea12' => $linea12, 'linea14' => $linea14, 'sinValidar' => $sinValidar];
    }

    /**
     * Hijo calificado del crédito por hijos (línea 4): dependiente "hijo calificado", menor de 17 al cierre del año y
     * con SSN válido para trabajar; además quien declara (o uno de los cónyuges en conjunta) necesita un SSN válido
     * (i1040s8, p. 1). Los demás dependientes van a la línea 6 (otros dependientes, i1040s8, p. 2).
     *
     * @param  list<PersonaEntrada>  $dependientes
     * @return array{0: list<int>, 1: list<int>, 2: Traza}
     */
    private function clasificar(EntradasDeclaracion $e, array $dependientes): array
    {
        $contribuyente = Apoyo::persona($e, RolPersona::Contribuyente);

        if ($contribuyente === null) {
            throw Corte::falta(__('Falta el contribuyente entre las personas del expediente.'));
        }

        $declarantes = [$contribuyente];

        if ($e->estadoCivil === EstadoCivilIrs::Mfj) {
            $conyuge = Apoyo::persona($e, RolPersona::Conyuge);

            if ($conyuge === null) {
                throw Corte::falta(__('Falta el cónyuge entre las personas del expediente.'));
            }

            $declarantes[] = $conyuge;
        }

        $declaranteConSsn = array_filter($declarantes, fn (PersonaEntrada $p): bool => self::ssnParaTrabajar($p)) !== [];
        $traza = Traza::vacia();

        if (! $declaranteConSsn) {
            $traza = $traza->conOperacion(__('Quien declara (o ninguno de los cónyuges en conjunta) no tiene un SSN válido para trabajar: no hay crédito por hijos ni crédito adicional; los hijos cuentan como otros dependientes (i1040s8, p. 1 y 2).'));
        }

        $hijos = [];
        $otros = [];

        foreach ($dependientes as $p) {
            if ($p->clasificacion !== ClasificacionPersona::HijoCalificado) {
                $otros[] = $p->id;
                $traza = $traza->conOperacion(__('persona#:id: pariente calificado, otro dependiente.', ['id' => (string) $p->id]));

                continue;
            }

            if ($p->edadAlFinDeAnio === null) {
                throw Corte::falta(__('Falta la edad del dependiente persona#:id para saber si es menor de 17.', ['id' => (string) $p->id]));
            }

            if ($p->edadAlFinDeAnio < self::EDAD_MENOR_DE && self::ssnParaTrabajar($p) && $declaranteConSsn) {
                $hijos[] = $p->id;
                $traza = $traza->conOperacion(__('persona#:id: hijo calificado menor de 17 con SSN válido para trabajar.', ['id' => (string) $p->id]));

                continue;
            }

            $otros[] = $p->id;
            $traza = $traza->conOperacion($p->edadAlFinDeAnio >= self::EDAD_MENOR_DE
                ? __('persona#:id: hijo calificado de 17 años o más, otro dependiente.', ['id' => (string) $p->id])
                : __('persona#:id: hijo calificado sin SSN válido para trabajar (o quien declara no lo tiene), otro dependiente.', ['id' => (string) $p->id]));
        }

        if ($otros !== []) {
            $traza = $traza->conOperacion(__('Otros dependientes: que sean ciudadanos, nacionales o residentes de EE. UU. y tengan SSN, ITIN o ATIN no se pregunta hoy; el preparador lo confirma.'));
        }

        return [$hijos, $otros, $traza];
    }

    /**
     * Hoja A del límite por el impuesto (i1040s8, p. 4): 1040 línea 18 menos las líneas 1, 2, 3, 4, 5b, 6d, 6f, 6l y
     * 6m del Schedule 3 (del piloto, la 2 y la 3); la hoja B (línea 4) solo aplica con créditos que el piloto no tiene.
     *
     * @return array{0: Dinero, 1: Traza, 2: list<string>}
     */
    private function hojaA(Declaracion $d): array
    {
        [$impuesto, $sinValidar] = $this->leer($d, self::F1040, '18', '13');

        $creditos = Apoyo::leerLineas($d, self::F, '13', [['sch-3', '2', true], ['sch-3', '3', false]]);

        if ($creditos instanceof Linea) {
            throw Corte::deLinea($creditos);
        }

        [$restar, $traza, $sinValidarCreditos] = $creditos;
        $valor = $impuesto->menos($restar)->max(Dinero::deCentavos(0));

        return [$valor, Traza::vacia()
            ->conEntrada(Apoyo::nombreLinea(self::F1040, '18'), $impuesto, OrigenTraza::Linea, self::F1040.':18')
            ->conEntrada(__('Créditos del Schedule 3 que se restan (líneas 2 y 3)'), $restar, OrigenTraza::Linea, 'sch-3:2')
            ->conOperacion(__('Hoja A: línea 18 del 1040 menos los créditos del Schedule 3; la hoja B no aplica (sin créditos de hipoteca, adopción, energía residencial ni comprador en D. C.); si es cero o menos, cero.')), [...$sinValidar, ...$sinValidarCreditos]];
    }

    // ── Parte II: crédito adicional por hijos ──

    /** @param array{hijos: int, linea12: Dinero, linea14: Dinero, sinValidar: list<string>} $parteI */
    private function parteII(EntradasDeclaracion $e, Declaracion $d, array $parteI): void
    {
        $cero = Dinero::deCentavos(0);
        $sinValidar = $parteI['sinValidar'];

        if ($parteI['linea12']->esCero()) {
            throw Corte::noAplica(__('Sin crédito en la línea 12: no hay crédito adicional por hijos.'));
        }

        $linea16a = $parteI['linea12']->menos($parteI['linea14']);

        if ($linea16a->esCero() || $linea16a->esNegativo()) {
            // El formulario pide anotar la 16a y parar ahí ("If zero, stop here"; Schedule 8812 de 2025, p. 2).
            $this->poner($d, '16a', $cero, Traza::vacia()->conOperacion(__('Línea 12 menos línea 14.')), $sinValidar);

            throw Corte::noAplica(__('La línea 16a es cero: todo el crédito ya se usó en la línea 14; no hay crédito adicional por hijos.'));
        }

        $this->poner($d, '16a', $linea16a, Traza::vacia()->conOperacion(__('Línea 12 menos línea 14.')), $sinValidar);

        $tope = $this->parametro($e, 'credito_actc', 'tope_reembolsable_por_hijo');
        $linea16b = Dinero::deCentavos($tope->centavos() * $parteI['hijos']);

        if ($linea16b->esCero()) {
            throw Corte::noAplica(__('Sin hijos menores de 17 con SSN válido (línea 16b en cero): no hay crédito adicional por hijos.'));
        }

        $this->poner($d, '16b', $linea16b, Traza::vacia()
            ->conParametro(__('Tope reembolsable por hijo'), $tope, 'credito_actc.tope_reembolsable_por_hijo')
            ->conOperacion(__('Hijos de la línea 4 por el tope por hijo.')));

        $linea17 = $linea16a->min($linea16b);
        $this->poner($d, '17', $linea17, Traza::vacia()->conOperacion(__('La menor entre las líneas 16a y 16b.')), $sinValidar);

        // Línea 18a: Earned Income Worksheet (i1040s8, p. 8): 1040 línea 1z + Schedule C línea 31 − Schedule 1 línea 15.
        [$linea18a, $traza18a, $sinValidar18a] = $this->ingresoDelTrabajo($e, $d);
        $sinValidar = array_values(array_unique([...$sinValidar, ...$sinValidar18a]));
        $this->poner($d, '18a', $linea18a, $traza18a, $sinValidar18a);

        $umbral = $this->parametro($e, 'credito_actc', 'umbral_ingreso_trabajo');
        $tasa = $this->tasa($e);

        if ($linea18a->centavos() > $umbral->centavos()) {
            $linea19 = $linea18a->menos($umbral);
            $this->poner($d, '19', $linea19, Traza::vacia()
                ->conParametro(__('Ingreso del trabajo desde el que cuenta'), $umbral, 'credito_actc.umbral_ingreso_trabajo')
                ->conOperacion(__('Línea 18a menos el umbral.')), $sinValidar18a);
            $linea20 = $linea19->por($tasa);
        } else {
            $d->poner(Linea::noAplica(self::F, '19', __('El ingreso del trabajo no pasa del umbral: la línea 19 queda en blanco y la 20 en cero.')));
            $linea20 = $cero;
        }

        $this->poner($d, '20', $linea20, Traza::vacia()
            ->conParametro(__('Porcentaje del ingreso del trabajo sobre el umbral'), $tasa, 'credito_actc.tasa_ingreso_trabajo')
            ->conOperacion(__('Línea 19 por el porcentaje.')), $sinValidar18a);

        if ($parteI['hijos'] < self::HIJOS_PARTE_II_B) {
            $this->linea27($d, $linea17->min($linea20), __('Menos de tres hijos: la menor entre las líneas 17 y 20.'), $sinValidar);

            return;
        }

        if ($linea20->centavos() >= $linea17->centavos()) {
            $this->linea27($d, $linea17, __('Tres hijos o más y la línea 20 alcanza la 17: la línea 17.'), $sinValidar);

            return;
        }

        $this->parteIIB($e, $d, $linea17, $linea20, $sinValidar);
    }

    /**
     * Parte II-B (Schedule 8812, p. 2; i1040s8, p. 3): con tres hijos o más, el Seguro Social y Medicare retenidos
     * pueden dar más crédito. Con impuesto adicional de Medicare o propinas no reportadas al empleador hace falta una
     * hoja que el piloto no tiene.
     *
     * @param  list<string>  $sinValidar
     */
    private function parteIIB(EntradasDeclaracion $e, Declaracion $d, Dinero $linea17, Dinero $linea20, array $sinValidar): void
    {
        $cero = Dinero::deCentavos(0);
        $medicare = $d->linea('sch-2', '11');
        $propinas = $d->linea(self::F1040, '1c');

        if (($medicare?->valor instanceof Dinero && ! $medicare->valor->esCero()) || $propinas?->estado === EstadoLinea::NoSoportada) {
            throw Corte::noSoportada(__('Parte II-B con impuesto adicional de Medicare o propinas no reportadas: la hoja del Schedule 8812 la hace el preparador.'));
        }

        // Línea 21: W-2 casillas 4 y 6 (en conjunta, las de los dos).
        $retenido = Apoyo::leerDatos($e, self::F, '21', [
            'w2:4' => __('W-2, casilla 4 (Seguro Social retenido)'),
            'w2:6' => __('W-2, casilla 6 (Medicare retenido)'),
        ]);

        if ($retenido instanceof Linea) {
            throw Corte::deLinea($retenido);
        }

        [$linea21, $traza21, $sinValidar21] = $retenido ?? [$cero, Traza::vacia()->conOperacion(__('Sin W-2: nada retenido.')), []];
        $this->poner($d, '21', $linea21, $traza21, $sinValidar21);

        // Línea 22: Schedule 1 línea 15 (si hay negocio) y Schedule 2 líneas 5, 6 y 13 (no son del piloto).
        $linea22 = $cero;
        $traza22 = Traza::vacia()->conOperacion(__('Las líneas 5, 6 y 13 del Schedule 2 no son del piloto.'));
        $sinValidar22 = [];

        if ($this->hayNegocio($e)) {
            [$linea22, $sinValidar22] = $this->leer($d, 'sch-1', '15', '22');
            $traza22 = $traza22->conEntrada(Apoyo::nombreLinea('sch-1', '15'), $linea22, OrigenTraza::Linea, 'sch-1:15');
        }

        $this->poner($d, '22', $linea22, $traza22, $sinValidar22);
        $linea23 = $linea21->mas($linea22);
        $this->poner($d, '23', $linea23, Traza::vacia()->conOperacion(__('Líneas 21 más 22.')), [...$sinValidar21, ...$sinValidar22]);

        $lectura = Apoyo::leerVarias($d, [[self::F1040, '27a'], ['sch-3', '11']], self::F, '24');

        if ($lectura instanceof Linea) {
            throw Corte::deLinea($lectura);
        }

        [[$eic, $exceso], $traza24, $sinValidar24] = $lectura;
        $linea24 = $eic->mas($exceso);
        $this->poner($d, '24', $linea24, $traza24->conOperacion(__('Línea 27a del 1040 más línea 11 del Schedule 3.')), $sinValidar24);

        $linea25 = $linea23->menos($linea24)->max($cero);
        $this->poner($d, '25', $linea25, Traza::vacia()->conOperacion(__('Línea 23 menos línea 24; si es cero o menos, cero.')));
        $linea26 = $linea20->max($linea25);
        $this->poner($d, '26', $linea26, Traza::vacia()->conOperacion(__('La mayor entre las líneas 20 y 25.')));

        $this->linea27($d, $linea17->min($linea26), __('La menor entre las líneas 17 y 26.'), [...$sinValidar, ...$sinValidar21, ...$sinValidar22, ...$sinValidar24]);
    }

    /** @param list<string> $sinValidar */
    private function linea27(Declaracion $d, Dinero $valor, string $operacion, array $sinValidar): void
    {
        $this->poner($d, '27', $valor, Traza::vacia()->conOperacion($operacion.' '.__('Va a la línea 28 del Form 1040.')), $sinValidar);
    }

    /**
     * Earned Income Worksheet (i1040s8, p. 8): 1040 línea 1z, más la ganancia o pérdida del Schedule C (línea 31),
     * menos la mitad del impuesto de autoempleo (Schedule 1, línea 15); si es cero o menos, cero. Se usa aunque se
     * tome el EIC (la tabla de la p. 7 manda a la hoja B del EIC, que llega a lo mismo sin pago de combate ni clero).
     *
     * @return array{0: Dinero, 1: Traza, 2: list<string>}
     */
    private function ingresoDelTrabajo(EntradasDeclaracion $e, Declaracion $d): array
    {
        $partes = [[self::F1040, '1z']];

        if ($this->hayNegocio($e)) {
            $partes[] = ['sch-c', '31'];
            $partes[] = ['sch-1', '15'];
        }

        $lectura = Apoyo::leerVarias($d, $partes, self::F, '18a');

        if ($lectura instanceof Linea) {
            throw Corte::deLinea($lectura);
        }

        [$valores, $traza, $sinValidar] = $lectura;
        $total = $valores[0];

        if (count($valores) === 3) {
            $total = $total->mas($valores[1])->menos($valores[2]);
        }

        $total = $total->max(Dinero::deCentavos(0));

        return [$total, $traza->conOperacion(__('Línea 1z más la ganancia del Schedule C menos la línea 15 del Schedule 1; si es cero o menos, cero. Sin pago de combate no gravable (18b), que no se pregunta.')), $sinValidar];
    }

    private function hayNegocio(EntradasDeclaracion $e): bool
    {
        return Apoyo::hayDatos($e, '1099-nec') || Apoyo::hayDatos($e, '1099-k') || $e->sumasRegistros !== [];
    }

    private static function ssnParaTrabajar(PersonaEntrada $p): bool
    {
        return $p->ssnValido && ! $p->noValidoParaTrabajar;
    }

    /** @return array{0: Dinero, 1: list<string>} */
    private function leer(Declaracion $d, string $formulario, string $linea, string $destino): array
    {
        $lectura = Apoyo::leerUna($d, $formulario, $linea, self::F, $destino);

        if ($lectura instanceof Linea) {
            throw Corte::deLinea($lectura);
        }

        return $lectura;
    }

    private function parametro(EntradasDeclaracion $e, string $categoria, string $clave): Dinero
    {
        try {
            return Apoyo::parametroEnDolares($e->taxYear, $categoria, $clave);
        } catch (RuntimeException $excepcion) {
            throw Corte::falta(Apoyo::motivoSinParametro($e->taxYear, $excepcion));
        }
    }

    private function tasa(EntradasDeclaracion $e): string
    {
        try {
            return (string) ParametrosFiscales::valorRequerido($e->taxYear, 'credito_actc', 'tasa_ingreso_trabajo');
        } catch (RuntimeException $excepcion) {
            throw Corte::falta(Apoyo::motivoSinParametro($e->taxYear, $excepcion));
        }
    }

    /** @param list<string> $sinValidar */
    private function poner(Declaracion $d, string $linea, Dinero $valor, Traza $traza, array $sinValidar = []): void
    {
        $d->poner(Linea::calculada(self::F, $linea, $valor, $traza->conResultado(self::nombreLinea($linea), $valor), array_values(array_unique($sinValidar))));
    }

    private static function nombreLinea(string $linea): string
    {
        return Apoyo::nombreLinea(self::F, $linea);
    }
}
