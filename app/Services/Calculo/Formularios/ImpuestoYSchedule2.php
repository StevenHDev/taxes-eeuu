<?php

namespace App\Services\Calculo\Formularios;

use App\Enums\Expediente\EstadoCivilIrs;
use App\Services\Calculo\Formularios\Impuesto\HojaImpuesto;
use App\Services\Calculo\Formularios\Impuesto\TablaImpuesto;
use App\Services\Calculo\Formularios\Impuesto\Tramos;
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
 * Impuesto del Form 1040 (línea 16), Schedule 2 (líneas 3, 4, 11 y 21) y las líneas 17, 18,
 * 21, 22, 23 y 24 del 1040, todo de 2025.
 *
 * - Línea 16: Tax Table si el ingreso gravable es menor de USD 100.000; si no, la Tax
 *   Computation Worksheet (Pub. 1040, p. 2 a 15). Con documentos de dividendos o de ganancias
 *   de capital (que exigen la hoja de dividendos calificados y ganancias de capital) sale
 *   no soportada.
 * - Schedule 2: 3 = línea 29 del 8962 (la línea 2, AMT, no se soporta); 4 = línea 12 del
 *   Schedule SE; 11 = Form 8959 partes I y II (impuesto, sin la conciliación de retenciones,
 *   que es del 25c); 21 = 4 + 11. Las demás líneas del Schedule 2 no se escriben. Si hay
 *   documentos que dispararían el impuesto sobre la inversión (Form 8960), la línea 12 sale
 *   no soportada y arrastra la 21, la 23 y la 24.
 *
 * Debe correr DESPUÉS de las calculadoras del ingreso gravable (línea 15), del Schedule SE,
 * del 8962, del 8812 y del Schedule 3 (líneas 19 y 20); lo que no encuentre sale `falta_dato`.
 */
final class ImpuestoYSchedule2 implements Calculadora
{
    private const F1040 = '1040';

    private const SCH2 = 'sch-2';

    /** Documentos con dividendos calificados o ganancias de capital: exigen la hoja QDCG (Pub. 1040, p. 14, nota). */
    private const DOCUMENTOS_QDCG = ['1099-div', '1099-b', '1099-da', 'k-1'];

    /** Intereses, dividendos y alquileres: podrían causar el impuesto sobre la inversión (Form 8960). */
    private const DOCUMENTOS_NIIT = ['1099-int', '1099-div', '1099-b', '1099-da', 'k-1', 'registros-alquiler'];

    public function formulario(): string
    {
        return self::F1040;
    }

    public function calcular(EntradasDeclaracion $e, Declaracion $d): void
    {
        $this->linea16($e, $d);

        $this->schedule2Linea3($e, $d);
        $this->schedule2Linea4($d);
        $this->schedule2Linea11($e, $d);
        $this->schedule2Linea12($e, $d);
        $this->schedule2Linea21($d);

        Apoyo::sumar($d, self::F1040, '17', [[self::SCH2, '3']], __('Línea 17 del Form 1040'), copiar: true);
        Apoyo::sumar($d, self::F1040, '18', [[self::F1040, '16'], [self::F1040, '17']], __('Línea 18 del Form 1040'));
        Apoyo::sumar($d, self::F1040, '21', [[self::F1040, '19'], [self::F1040, '20']], __('Línea 21 del Form 1040'));
        $this->linea22($d);
        Apoyo::sumar($d, self::F1040, '23', [[self::SCH2, '21']], __('Línea 23 del Form 1040'), copiar: true);
        Apoyo::sumar($d, self::F1040, '24', [[self::F1040, '22'], [self::F1040, '23']], __('Línea 24 del Form 1040'));
    }

    /** Línea 16: Tax Table (menos de USD 100.000) o Tax Computation Worksheet. */
    private function linea16(EntradasDeclaracion $e, Declaracion $d): void
    {
        $lectura = Apoyo::leerUna($d, self::F1040, '15', self::F1040, '16');

        if ($lectura instanceof Linea) {
            $d->poner($lectura);

            return;
        }

        [$ingreso, $sinValidar] = $lectura;

        if ($e->estadoCivil === null) {
            $d->poner(Linea::faltaDato(self::F1040, '16', Apoyo::motivoSinEstadoCivil()));

            return;
        }

        $fuera = Apoyo::documentosPresentes($e, self::DOCUMENTOS_QDCG);

        if ($fuera !== []) {
            $d->poner(Linea::noSoportada(self::F1040, '16', __('Hay documentos de dividendos o ganancias de capital (:documentos): falta la hoja de dividendos calificados y ganancias de capital.', ['documentos' => implode(', ', $fuera)])));

            return;
        }

        $estado = $e->estadoCivil->value;
        $traza = Traza::vacia()
            ->conEntrada(__('Ingreso gravable (línea 15 del Form 1040)'), $ingreso, OrigenTraza::Linea, self::F1040.':15')
            ->conEntrada($e->estadoCivil->label(), $estado, OrigenTraza::Decision, 'estado_civil_irs');

        try {
            $claveTramos = 'tax_brackets.'.Tramos::claveParametro($estado);

            if (TablaImpuesto::aplica($ingreso)) {
                $franja = TablaImpuesto::franja($ingreso);
                $impuesto = TablaImpuesto::paraFila($estado, $ingreso, $e->taxYear);
                $traza = $traza
                    ->conParametro(__('Tramos del impuesto del año'), $claveTramos, $claveTramos)
                    ->conOperacion(__('Tax Table (Pub. 1040, p. 2 a 13): franja desde :desde hasta menos de :hasta; se usa el impuesto del punto medio de la franja, redondeado a dólares.', ['desde' => $franja['desde']->texto(), 'hasta' => $franja['hasta']->texto()]));
            } else {
                $hoja = HojaImpuesto::calcular($estado, $ingreso, $e->taxYear);
                $impuesto = $hoja['impuesto'];
                $traza = $traza
                    ->conParametro(__('Tramos del impuesto del año'), $claveTramos, $claveTramos)
                    ->conOperacion(__('Tax Computation Worksheet (Pub. 1040, p. 14 y 15): línea 15 × :tasa menos :resta.', ['tasa' => $hoja['tasa'], 'resta' => $hoja['resta']->texto()]));
            }
        } catch (RuntimeException $excepcion) {
            $d->poner(Linea::faltaDato(self::F1040, '16', Apoyo::motivoSinParametro($e->taxYear, $excepcion)));

            return;
        }

        $d->poner(Linea::calculada(self::F1040, '16', $impuesto, $traza->conResultado(__('Línea 16 del Form 1040'), $impuesto), $sinValidar));
    }

    /**
     * Schedule 2, línea 3: "Add lines 1z and 2". En el piloto, la 1z es solo la 1a (línea 29
     * del Form 8962) y la línea 2 (impuesto mínimo alternativo, Form 6251) no se calcula.
     */
    private function schedule2Linea3(EntradasDeclaracion $e, Declaracion $d): void
    {
        $f8962 = $d->linea('f8962', '29');
        $hay1095 = Apoyo::documentosPresentes($e, ['1095-a']) !== [];

        if ($f8962 === null && $hay1095) {
            $d->poner(Linea::faltaDato(self::SCH2, '3', __('Falta la línea 29 del Form 8962: el cliente tiene un 1095-A.')));

            return;
        }

        if ($f8962 === null) {
            $traza = Traza::vacia()
                ->conOperacion(__('Sin 1095-A no hay crédito de la prima que devolver (línea 1a).'))
                ->conOperacion(__('El impuesto mínimo alternativo (línea 2, Form 6251) no se calcula en el piloto.'));
            $cero = Dinero::deCentavos(0);
            $d->poner(Linea::calculada(self::SCH2, '3', $cero, $traza->conResultado(__('Línea 3 del Schedule 2'), $cero)));

            return;
        }

        $lectura = Apoyo::leerUna($d, 'f8962', '29', self::SCH2, '3');

        if ($lectura instanceof Linea) {
            $d->poner($lectura);

            return;
        }

        [$valor, $sinValidar] = $lectura;
        $traza = Traza::vacia()
            ->conEntrada(__('Línea 29 del Form 8962 (línea 1a del Schedule 2)'), $valor, OrigenTraza::Linea, 'f8962:29')
            ->conOperacion(__('El impuesto mínimo alternativo (línea 2, Form 6251) no se calcula en el piloto.'));

        $d->poner(Linea::calculada(self::SCH2, '3', $valor, $traza->conResultado(__('Línea 3 del Schedule 2'), $valor), $sinValidar));
    }

    /** Schedule 2, línea 4: impuesto de trabajo por cuenta propia, línea 12 del Schedule SE. */
    private function schedule2Linea4(Declaracion $d): void
    {
        Apoyo::sumar($d, self::SCH2, '4', [['sch-se', '12']], __('Línea 4 del Schedule 2'), copiar: true);
    }

    /**
     * Schedule 2, línea 11: impuesto adicional de Medicare, Form 8959 (2025, p. 1) partes I y II.
     * Las líneas 2 y 3 del 8959 (propinas no reportadas, Form 8919) y la parte III (RRTA)
     * no están en el piloto. La conciliación de retenciones (partes V) es del 25c.
     */
    private function schedule2Linea11(EntradasDeclaracion $e, Declaracion $d): void
    {
        if ($e->estadoCivil === null) {
            $d->poner(Linea::faltaDato(self::SCH2, '11', Apoyo::motivoSinEstadoCivil()));

            return;
        }

        $dato = $e->datos['w2:5'] ?? null;

        if ($dato !== null && ! $dato->valor instanceof Dinero) {
            $d->poner(Linea::faltaDato(self::SCH2, '11', __('Falta un monto válido en la casilla 5 del W-2.')));

            return;
        }

        $lectura = Apoyo::leerUna($d, 'sch-se', '6', self::SCH2, '11');

        if ($lectura instanceof Linea) {
            $d->poner($lectura);

            return;
        }

        [$autoempleo, $sinValidar] = $lectura;

        try {
            $umbral = $this->umbralMedicare($e->taxYear, $e->estadoCivil);
            $tasa = (string) ParametrosFiscales::valorRequerido($e->taxYear, 'additional_medicare_tax', 'tasa');
        } catch (RuntimeException $excepcion) {
            $d->poner(Linea::faltaDato(self::SCH2, '11', Apoyo::motivoSinParametro($e->taxYear, $excepcion)));

            return;
        }

        $cero = Dinero::deCentavos(0);
        $salarios = $dato?->valor ?? $cero;
        $traza = Traza::vacia();

        if ($dato !== null) {
            $traza = $traza->conEntrada(__('W-2, casilla 5 (salarios sujetos a Medicare)'), $salarios, $dato->origen, $dato->referencia ?? 'w2:5');

            if ($dato->usaSinValidar()) {
                $sinValidar[] = $dato->referencia ?? 'w2:5';
            }
        } else {
            $traza = $traza->conOperacion(__('Sin W-2 con casilla 5: salarios de Medicare en cero.'));
        }

        $traza = $traza
            ->conEntrada(__('Autoempleo (línea 6 del Schedule SE)'), $autoempleo, OrigenTraza::Linea, 'sch-se:6')
            ->conParametro(__('Umbral del Form 8959 para el estado civil (líneas 5 y 9)'), $umbral, 'additional_medicare_tax.umbral')
            ->conParametro(__('Tasa del impuesto adicional de Medicare'), $tasa, 'additional_medicare_tax.tasa');

        // Parte I (líneas 4 a 7) y parte II (líneas 8 a 13).
        $linea6 = $salarios->menos($umbral)->max($cero);
        $linea7 = $linea6->por($tasa);
        $linea11 = $umbral->menos($salarios)->max($cero);
        $linea12 = $autoempleo->max($cero)->menos($linea11)->max($cero);
        $linea13 = $linea12->por($tasa);
        $total = $linea7->mas($linea13);

        $traza = $traza
            ->conOperacion(__('Form 8959, parte I (líneas 6 y 7): salarios menos el umbral, por la tasa.'), $linea7)
            ->conOperacion(__('Form 8959, parte II (líneas 8 a 13): autoempleo menos lo que falta del umbral después de los salarios, por la tasa.'), $linea13);

        $d->poner(Linea::calculada(self::SCH2, '11', $total, $traza->conResultado(__('Línea 11 del Schedule 2 (Form 8959, línea 18)'), $total), array_values(array_unique($sinValidar))));
    }

    /** Schedule 2, línea 12 (Form 8960): no soportada si hay datos que podrían dispararla. */
    private function schedule2Linea12(EntradasDeclaracion $e, Declaracion $d): void
    {
        $fuera = Apoyo::documentosPresentes($e, self::DOCUMENTOS_NIIT);

        if ($fuera === []) {
            return;
        }

        // El Form 8960 solo se llena si el ingreso ajustado modificado pasa del umbral del estado civil
        // (instrucciones del 1040 de 2025, Schedule 2, línea 12; Form 8960, línea 14). En el piloto ese
        // ingreso es la línea 11b del 1040 (no hay ingreso extranjero excluido).
        if ($e->estadoCivil === null) {
            $d->poner(Linea::faltaDato(self::SCH2, '12', Apoyo::motivoSinEstadoCivil()));

            return;
        }

        $agi = Apoyo::leerUna($d, '1040', '11b', self::SCH2, '12');

        if ($agi instanceof Linea) {
            $d->poner($agi);

            return;
        }

        try {
            $umbral = Apoyo::parametroEnDolares($e->taxYear, 'niit', match ($e->estadoCivil) {
                EstadoCivilIrs::Mfj, EstadoCivilIrs::Qss => 'umbral_mfj',
                EstadoCivilIrs::Mfs => 'umbral_mfs',
                EstadoCivilIrs::Single, EstadoCivilIrs::Hoh => 'umbral_soltero',
            });
        } catch (RuntimeException $excepcion) {
            $d->poner(Linea::faltaDato(self::SCH2, '12', Apoyo::motivoSinParametro($e->taxYear, $excepcion)));

            return;
        }

        if ($agi[0]->centavos() > $umbral->centavos()) {
            $d->poner(Linea::noSoportada(self::SCH2, '12', __('Hay documentos de intereses, dividendos o alquileres (:documentos) y el ingreso pasa del umbral: falta el Form 8960 (impuesto sobre la inversión).', ['documentos' => implode(', ', $fuera)])));

            return;
        }

        $d->poner(Linea::noAplica(self::SCH2, '12', __('El ingreso no pasa del umbral del impuesto sobre la inversión (Form 8960): no se debe.'), Traza::vacia()
            ->conEntrada(Apoyo::nombreLinea('1040', '11b'), $agi[0], OrigenTraza::Linea, '1040:11b')
            ->conParametro(__('Umbral del impuesto sobre la inversión'), $umbral, 'niit')));
    }

    /** Schedule 2, línea 21: "Add lines 4, 7 through 16, 18, and 19"; del piloto, la 4 y la 11 (y la 12 si quedó no soportada). */
    private function schedule2Linea21(Declaracion $d): void
    {
        $partes = [[self::SCH2, '4'], [self::SCH2, '11']];

        if ($d->linea(self::SCH2, '12') !== null) {
            $partes[] = [self::SCH2, '12'];
        }

        Apoyo::sumar($d, self::SCH2, '21', $partes, __('Línea 21 del Schedule 2'));
    }

    /** 1040, línea 22: línea 18 menos línea 21; si es cero o menos, cero. */
    private function linea22(Declaracion $d): void
    {
        $lectura = Apoyo::leerVarias($d, [[self::F1040, '18'], [self::F1040, '21']], self::F1040, '22');

        if ($lectura instanceof Linea) {
            $d->poner($lectura);

            return;
        }

        [$valores, $traza, $sinValidar] = $lectura;
        $valor = $valores[0]->menos($valores[1])->max(Dinero::deCentavos(0));

        $d->poner(Linea::calculada(self::F1040, '22', $valor, $traza
            ->conOperacion(__('Línea 18 menos línea 21; si es cero o menos, cero.'))
            ->conResultado(__('Línea 22 del Form 1040'), $valor), $sinValidar));
    }

    /** Umbral del Form 8959 (líneas 5 y 9) según el estado civil. */
    private function umbralMedicare(int $taxYear, EstadoCivilIrs $estadoCivil): Dinero
    {
        $clave = match ($estadoCivil) {
            EstadoCivilIrs::Mfj => 'umbral_mfj',
            EstadoCivilIrs::Mfs => 'umbral_mfs',
            EstadoCivilIrs::Single, EstadoCivilIrs::Hoh, EstadoCivilIrs::Qss => 'umbral_soltero',
        };

        return Dinero::deDolares((int) ParametrosFiscales::valorRequerido($taxYear, 'additional_medicare_tax', $clave));
    }
}
