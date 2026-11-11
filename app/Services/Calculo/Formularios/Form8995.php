<?php

namespace App\Services\Calculo\Formularios;

use App\Enums\Expediente\EstadoCivilIrs;
use App\Services\Calculo\Nucleo\Apoyo;
use App\Services\Calculo\Nucleo\Calculadora;
use App\Services\Calculo\Nucleo\Declaracion;
use App\Services\Calculo\Nucleo\Dinero;
use App\Services\Calculo\Nucleo\EntradasDeclaracion;
use App\Services\Calculo\Nucleo\EstadoLinea;
use App\Services\Calculo\Nucleo\Linea;
use App\Services\Calculo\Nucleo\OrigenTraza;
use App\Services\Calculo\Nucleo\Traza;
use App\Support\ParametrosFiscales;
use RuntimeException;

/**
 * Form 8995 de 2025, "Qualified Business Income Deduction Simplified Computation" (decisión h):
 * líneas 2, 3, 4, 5, 10, 11, 12, 13, 14, 15 y 16. Fuentes: el formulario (`f8995`, p. 1) y sus
 * instrucciones (`i8995`, p. 2 a 5).
 *
 * - QBI del piloto = un solo negocio, el del Schedule C: línea 31 menos la parte deducible del
 *   impuesto de autoempleo (Schedule 1, línea 15). Las instrucciones (p. 2, "Determining Your
 *   Qualified Business Income") también restan el seguro médico del autoempleo y los aportes a
 *   planes de retiro (Schedule 1, líneas 16 y 17), que el piloto no calcula.
 * - Línea 3 (pérdida arrastrada de años anteriores) y líneas 6 a 9 (dividendos de REIT y PTP):
 *   el piloto no las pregunta; cuentan cero. Con 1099-DIV o K-1 el formulario sale no soportado.
 * - Línea 11 = 1040 línea 11a − 12e − 13b (i8995, p. 4, "Line 11").
 * - Línea 12 = dividendos calificados + ganancia de capital neta: cero sin documentos de
 *   inversión; con ellos (1099-DIV, 1099-B, 1099-DA, K-1), no soportada.
 * - Si la línea 11 pasa de `qbi.umbral_*`, se usa el Form 8995-A (F7): todo no soportado.
 * - Con pérdida (línea 2 + 3 negativa) la deducción es cero y la línea 16 (pérdida que se
 *   arrastra al año siguiente) sale no soportada: la registra el preparador.
 *
 * Debe correr DESPUÉS de `ScheduleC`, `Schedule1` (línea 15), `Ingresos1040` (11a) y de las
 * líneas 12e (`Deducciones`) y 38 del Schedule 1-A; `Deducciones` lo llama en su orden.
 */
final class Form8995 implements Calculadora
{
    private const FORMULARIO = 'f8995';

    /** Documentos con dividendos (REIT, calificados), ganancias de capital o QBI de sociedades. */
    private const DOCUMENTOS_FUERA = ['1099-div', '1099-b', '1099-da', 'k-1'];

    /** Todas las líneas que escribe, para marcarlas juntas cuando no aplica o no se soporta. */
    private const LINEAS = ['2', '3', '4', '5', '10', '11', '12', '13', '14', '15', '16'];

    public function formulario(): string
    {
        return self::FORMULARIO;
    }

    public function calcular(EntradasDeclaracion $e, Declaracion $d): void
    {
        $negocio = $d->linea('sch-c', '31');

        if ($negocio === null) {
            $this->todas($d, EstadoLinea::FaltaDato, __('Falta la línea 31 del Schedule C.'));

            return;
        }

        if ($negocio->estado === EstadoLinea::NoAplica) {
            $this->todas($d, EstadoLinea::NoAplica, __('No hay negocio en el Schedule C: no hay ingreso calificado del negocio.'));

            return;
        }

        if ($negocio->estado !== EstadoLinea::Calculada) {
            $this->todas($d, $negocio->estado, $negocio->motivo ?? __('No se puede calcular: depende de la línea 31 del Schedule C.'));

            return;
        }

        $fuera = Apoyo::documentosPresentes($e, self::DOCUMENTOS_FUERA);

        if ($fuera !== []) {
            $this->todas($d, EstadoLinea::NoSoportada, __('Hay documentos de dividendos, ganancias de capital o sociedades (:documentos): cambian el ingreso calificado y el límite del Form 8995 y el piloto no los calcula.', ['documentos' => implode(', ', $fuera)]));

            return;
        }

        if ($e->estadoCivil === null) {
            $this->todas($d, EstadoLinea::FaltaDato, Apoyo::motivoSinEstadoCivil());

            return;
        }

        $linea11 = $this->linea11($d);

        if ($linea11 instanceof Linea) {
            $this->todas($d, $linea11->estado, $linea11->motivo ?? '');

            return;
        }

        try {
            // El umbral de "todos los demás" incluye MFS en 2025 (i8995, p. 2, Exception 1). En 2026 MFS
            // tiene USD 25 más y no hay parámetro propio: usar el de soltero manda al 8995-A unos casos
            // que no lo necesitan, nunca al revés.
            $claveUmbral = $e->estadoCivil === EstadoCivilIrs::Mfj ? 'umbral_mfj' : 'umbral_soltero';
            $umbral = Apoyo::parametroEnDolares($e->taxYear, 'qbi', $claveUmbral);
            $tasa = (string) ParametrosFiscales::valorRequerido($e->taxYear, 'qbi', 'tasa');
        } catch (RuntimeException $excepcion) {
            $this->todas($d, EstadoLinea::FaltaDato, Apoyo::motivoSinParametro($e->taxYear, $excepcion));

            return;
        }

        [$ingresoAntes, $trazaIngreso, $sinValidarIngreso] = $linea11;

        if ($ingresoAntes->centavos() > $umbral->centavos()) {
            $this->todas($d, EstadoLinea::NoSoportada, __('El ingreso gravable antes de esta deducción pasa del umbral del Form 8995 (:umbral): se usa el Form 8995-A, que llega en F7.', ['umbral' => $umbral->texto()]));

            return;
        }

        $this->escribir($d, $negocio->valor, $negocio->datosSinValidar, $ingresoAntes, $trazaIngreso, $sinValidarIngreso, $umbral, 'qbi.'.$claveUmbral, $tasa);
    }

    /**
     * @param  list<string>  $sinValidarNegocio
     * @param  list<string>  $sinValidarIngreso
     */
    private function escribir(Declaracion $d, mixed $ganancia, array $sinValidarNegocio, Dinero $ingresoAntes, Traza $trazaIngreso, array $sinValidarIngreso, Dinero $umbral, string $claveUmbral, string $tasa): void
    {
        if (! $ganancia instanceof Dinero) {
            $this->todas($d, EstadoLinea::FaltaDato, __('La línea 31 del Schedule C no tiene un monto.'));

            return;
        }

        $mitadSe = Apoyo::leerUna($d, 'sch-1', '15', self::FORMULARIO, '2');

        if ($mitadSe instanceof Linea) {
            $this->todas($d, $mitadSe->estado, $mitadSe->motivo ?? '');

            return;
        }

        [$deducibleSe, $sinValidarSe] = $mitadSe;
        $sinValidarQbi = array_values(array_unique([...$sinValidarNegocio, ...$sinValidarSe]));
        $cero = Dinero::deCentavos(0);

        // Línea 2: QBI del único negocio del piloto.
        $qbi = $ganancia->menos($deducibleSe);
        $d->poner(Linea::calculada(self::FORMULARIO, '2', $qbi, Traza::vacia()
            ->conEntrada(__('Ganancia neta del Schedule C (línea 31)'), $ganancia, OrigenTraza::Linea, 'sch-c:31')
            ->conEntrada(__('Parte deducible del impuesto de autoempleo (Schedule 1, línea 15)'), $deducibleSe, OrigenTraza::Linea, 'sch-1:15')
            ->conOperacion(__('Ganancia menos la parte deducible del impuesto de autoempleo (instrucciones del 8995, p. 2). El seguro médico del autoempleo y los aportes a planes de retiro (Schedule 1, líneas 16 y 17) no son del piloto.'))
            ->conResultado(Apoyo::nombreLinea(self::FORMULARIO, '2'), $qbi), $sinValidarQbi));

        $d->poner(Linea::noAplica(self::FORMULARIO, '3', __('El piloto no pregunta por pérdidas del negocio arrastradas de años anteriores: si las hay, el preparador las agrega.')));

        // Línea 4: líneas 2 y 3; si es cero o menos, cero.
        $total = $qbi->max($cero);
        $d->poner(Linea::calculada(self::FORMULARIO, '4', $total, Traza::vacia()
            ->conEntrada(Apoyo::nombreLinea(self::FORMULARIO, '2'), $qbi, OrigenTraza::Linea, self::FORMULARIO.':2')
            ->conOperacion(__('Líneas 2 y 3 combinadas; si es cero o menos, cero.'))
            ->conResultado(Apoyo::nombreLinea(self::FORMULARIO, '4'), $total), $sinValidarQbi));

        // Línea 5: línea 4 × 20 %.
        $componente = $total->por($tasa);
        $d->poner(Linea::calculada(self::FORMULARIO, '5', $componente, Traza::vacia()
            ->conEntrada(Apoyo::nombreLinea(self::FORMULARIO, '4'), $total, OrigenTraza::Linea, self::FORMULARIO.':4')
            ->conParametro(__('Porcentaje de la deducción'), $tasa, 'qbi.tasa')
            ->conOperacion(__('Multiplicación.'))
            ->conResultado(Apoyo::nombreLinea(self::FORMULARIO, '5'), $componente), $sinValidarQbi));

        // Línea 10: 5 + 9; la 9 (REIT y PTP) es cero en el piloto.
        $d->poner(Linea::calculada(self::FORMULARIO, '10', $componente, Traza::vacia()
            ->conEntrada(Apoyo::nombreLinea(self::FORMULARIO, '5'), $componente, OrigenTraza::Linea, self::FORMULARIO.':5')
            ->conOperacion(__('Más la línea 9 (dividendos de REIT y PTP), que es cero sin 1099-DIV ni K-1.'))
            ->conResultado(Apoyo::nombreLinea(self::FORMULARIO, '10'), $componente), $sinValidarQbi));

        // Línea 11: ingreso gravable antes de la deducción.
        $d->poner(Linea::calculada(self::FORMULARIO, '11', $ingresoAntes, $trazaIngreso
            ->conParametro(__('Umbral del Form 8995 (por encima se usa el 8995-A)'), $umbral, $claveUmbral)
            ->conResultado(Apoyo::nombreLinea(self::FORMULARIO, '11'), $ingresoAntes), $sinValidarIngreso));

        // Línea 12: ganancia de capital neta + dividendos calificados: cero sin documentos de inversión.
        $d->poner(Linea::calculada(self::FORMULARIO, '12', $cero, Traza::vacia()
            ->conOperacion(__('Sin 1099-DIV, 1099-B, 1099-DA ni K-1 no hay dividendos calificados ni ganancia de capital neta.'))
            ->conResultado(Apoyo::nombreLinea(self::FORMULARIO, '12'), $cero)));

        // Línea 13: 11 − 12; si es cero o menos, cero.
        $base = $ingresoAntes->max($cero);
        $d->poner(Linea::calculada(self::FORMULARIO, '13', $base, Traza::vacia()
            ->conEntrada(Apoyo::nombreLinea(self::FORMULARIO, '11'), $ingresoAntes, OrigenTraza::Linea, self::FORMULARIO.':11')
            ->conOperacion(__('Línea 11 menos línea 12; si es cero o menos, cero.'))
            ->conResultado(Apoyo::nombreLinea(self::FORMULARIO, '13'), $base), $sinValidarIngreso));

        // Línea 14: límite por ingreso.
        $limite = $base->por($tasa);
        $d->poner(Linea::calculada(self::FORMULARIO, '14', $limite, Traza::vacia()
            ->conEntrada(Apoyo::nombreLinea(self::FORMULARIO, '13'), $base, OrigenTraza::Linea, self::FORMULARIO.':13')
            ->conParametro(__('Porcentaje de la deducción'), $tasa, 'qbi.tasa')
            ->conOperacion(__('Multiplicación.'))
            ->conResultado(Apoyo::nombreLinea(self::FORMULARIO, '14'), $limite), $sinValidarIngreso));

        // Línea 15: la menor entre 10 y 14.
        $deduccion = $componente->min($limite);
        $sinValidar = array_values(array_unique([...$sinValidarQbi, ...$sinValidarIngreso]));
        $d->poner(Linea::calculada(self::FORMULARIO, '15', $deduccion, Traza::vacia()
            ->conEntrada(Apoyo::nombreLinea(self::FORMULARIO, '10'), $componente, OrigenTraza::Linea, self::FORMULARIO.':10')
            ->conEntrada(Apoyo::nombreLinea(self::FORMULARIO, '14'), $limite, OrigenTraza::Linea, self::FORMULARIO.':14')
            ->conOperacion(__('La menor entre las líneas 10 y 14.'))
            ->conResultado(Apoyo::nombreLinea(self::FORMULARIO, '15'), $deduccion), $sinValidar));

        // Línea 16: pérdida que se arrastra al año siguiente.
        if ($qbi->esNegativo()) {
            $d->poner(Linea::noSoportada(self::FORMULARIO, '16', __('El negocio tuvo pérdida: la deducción es cero y la pérdida se arrastra al año siguiente (línea 16). El piloto no guarda arrastres: lo registra el preparador.')));
        } else {
            $d->poner(Linea::noAplica(self::FORMULARIO, '16', __('No hay pérdida del negocio que arrastrar.')));
        }
    }

    /**
     * Línea 11 = 1040 línea 11a − 12e − 13b (i8995, p. 4).
     *
     * @return array{0: Dinero, 1: Traza, 2: list<string>}|Linea
     */
    private function linea11(Declaracion $d): array|Linea
    {
        $lectura = Apoyo::leerVarias($d, [['1040', '11a'], ['1040', '12e'], ['1040', '13b']], self::FORMULARIO, '11');

        if ($lectura instanceof Linea) {
            return $lectura;
        }

        [[$ajustado, $estandar, $adicionales], $traza, $sinValidar] = $lectura;
        $valor = $ajustado->menos($estandar)->menos($adicionales);

        return [$valor, $traza->conOperacion(__('Línea 11a menos las líneas 12e y 13b del Form 1040 (instrucciones del 8995, p. 4).')), $sinValidar];
    }

    /** Escribe todas las líneas del formulario con el mismo estado y motivo. */
    private function todas(Declaracion $d, EstadoLinea $estado, string $motivo): void
    {
        foreach (self::LINEAS as $linea) {
            $d->poner(match ($estado) {
                EstadoLinea::FaltaDato => Linea::faltaDato(self::FORMULARIO, $linea, $motivo),
                EstadoLinea::NoSoportada => Linea::noSoportada(self::FORMULARIO, $linea, $motivo),
                default => Linea::noAplica(self::FORMULARIO, $linea, $motivo),
            });
        }
    }
}
