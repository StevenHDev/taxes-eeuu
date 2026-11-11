<?php

namespace App\Services\Calculo\Formularios;

use App\Services\Calculo\Nucleo\Apoyo;
use App\Services\Calculo\Nucleo\Calculadora;
use App\Services\Calculo\Nucleo\Declaracion;
use App\Services\Calculo\Nucleo\Dinero;
use App\Services\Calculo\Nucleo\EntradasDeclaracion;
use App\Services\Calculo\Nucleo\Linea;
use App\Services\Calculo\Nucleo\Traza;

/**
 * Ingresos de la página 1 del Form 1040 de 2025: 1a, 1c, 1z, 8, 9, 10, 11a y
 * 11b (Form 1040 de 2025, p. 1 y p. 2).
 *
 * No escribe la 1e (la escribe la calculadora del Form 2441) ni las demás
 * líneas de ingresos del trabajo (1b, 1d, 1f, 1g, 1h: el piloto no las
 * soporta); la 1z solo suma las que otra calculadora ya haya puesto en la
 * declaración, por eso esta clase debe correr DESPUÉS de la del 2441 y de
 * `Schedule1`.
 *
 * Los montos no se redondean a dólares: el redondeo es opcional para el
 * contribuyente y, si se usa, lo aplica la presentación a todos los montos
 * (instrucciones del 1040 de 2025, p. 23, "Rounding Off to Whole Dollars").
 */
final class Ingresos1040 implements Calculadora
{
    private const FORMULARIO = '1040';

    /**
     * Documentos que el piloto no calcula y que cambian el ingreso total: van a
     * las líneas 2b a 7a del 1040 o a las líneas 1, 5 y 7 del Schedule 1
     * (1099-INT, 1099-DIV, 1099-R, SSA-1099, 1099-B, 1099-DA, 1099-G, K-1).
     * El 1098-E no está aquí: es un ajuste (Schedule 1, línea 21), no ingreso.
     */
    private const DOCUMENTOS_FUERA_DEL_PILOTO = ['1099-int', '1099-div', '1099-r', 'ssa-1099', '1099-b', '1099-da', '1099-g', 'k-1'];

    /** Líneas que suma la 1z: "Add lines 1a through 1h" (la 1i, combate no gravable, no entra). */
    private const LINEAS_DEL_TRABAJO = ['1a', '1b', '1c', '1d', '1e', '1f', '1g', '1h'];

    public function formulario(): string
    {
        return self::FORMULARIO;
    }

    public function calcular(EntradasDeclaracion $e, Declaracion $d): void
    {
        $this->linea1a($e, $d);
        $this->linea1c($e, $d);
        $this->linea1z($d);
        Apoyo::copiar($d, self::FORMULARIO, '8', 'sch-1', '10');
        $this->linea9($e, $d);
        Apoyo::copiar($d, self::FORMULARIO, '10', 'sch-1', '26');
        $this->linea11a($d);
        Apoyo::copiar($d, self::FORMULARIO, '11b', self::FORMULARIO, '11a');
    }

    /** Línea 1a: suma de la casilla 1 de todos los W-2 (1040, p. 1). */
    private function linea1a(EntradasDeclaracion $e, Declaracion $d): void
    {
        $dato = $e->datos['w2:1'] ?? null;

        if ($dato === null) {
            $d->poner(Linea::noAplica(self::FORMULARIO, '1a', __('No hay ningún W-2 en esta declaración.')));

            return;
        }

        if (! $dato->valor instanceof Dinero) {
            $d->poner(Linea::faltaDato(self::FORMULARIO, '1a', __('Falta el monto de la casilla 1 de los W-2.')));

            return;
        }

        $traza = Traza::vacia()
            ->conEntrada(__('W-2, casilla 1 (suma de todos los W-2)'), $dato->valor, $dato->origen, $dato->referencia ?? 'w2:1')
            ->conResultado(__('Línea 1a del Form 1040'), $dato->valor);

        $d->poner(Linea::calculada(self::FORMULARIO, '1a', $dato->valor, $traza, Apoyo::sinValidar(['w2:1' => $dato])));
    }

    /**
     * Línea 1c: propinas no reportadas (Form 4137). No se calcula; solo se avisa
     * cuando hay señal de que existen: la respuesta "no" a D3.1 (no le reportó
     * todas las propinas al empleador) o propinas asignadas en la casilla 8 del
     * W-2, que el IRS manda a la 1c (instrucciones del 1040 de 2025, p. 22,
     * "Line 1c").
     */
    private function linea1c(EntradasDeclaracion $e, Declaracion $d): void
    {
        $respuesta = ($e->respuestas['D3.1'] ?? null)?->valor;
        $noReportoTodas = $respuesta === false || $respuesta === 'no';
        $asignadas = $e->datos['w2:8']->valor ?? null;
        $hayAsignadas = $asignadas instanceof Dinero && ! $asignadas->esCero();

        if ($noReportoTodas || $hayAsignadas) {
            $d->poner(Linea::noSoportada(
                self::FORMULARIO,
                '1c',
                __('Hay propinas no reportadas al empleador (Form 4137): el piloto no las calcula y las revisa el preparador.'),
            ));
        }
    }

    /**
     * Línea 1z: suma de las líneas 1a a 1h. Solo cuentan las que alguna
     * calculadora ya escribió; las demás valen cero porque el piloto no las
     * soporta (1040, p. 1, "Add lines 1a through 1h").
     */
    private function linea1z(Declaracion $d): void
    {
        $fuentes = array_map(fn (string $l): array => [self::FORMULARIO, $l, false], self::LINEAS_DEL_TRABAJO);

        $resultado = Apoyo::leerLineas($d, self::FORMULARIO, '1z', $fuentes);

        if ($resultado instanceof Linea) {
            $d->poner($resultado);

            return;
        }

        [$total, $traza, $sinValidar] = $resultado;

        $d->poner(Linea::calculada(self::FORMULARIO, '1z', $total, $traza->conResultado(__('Línea 1z del Form 1040'), $total), $sinValidar));
    }

    /**
     * Línea 9: ingresos totales = 1z + 2b + 3b + 4b + 5b + 6b + 7a + 8 (1040,
     * p. 1). Las líneas 2b a 7a las llenan documentos que el piloto no
     * soporta: si hay alguno, la línea es no soportada en vez de salir corta.
     * Puede ser cero o negativa (el formulario no la limita).
     */
    private function linea9(EntradasDeclaracion $e, Declaracion $d): void
    {
        $resultado = Apoyo::leerLineas($d, self::FORMULARIO, '9', [[self::FORMULARIO, '1z', true], [self::FORMULARIO, '8', true]]);

        if ($resultado instanceof Linea) {
            $d->poner($resultado);

            return;
        }

        $fuera = Apoyo::documentosPresentes($e, self::DOCUMENTOS_FUERA_DEL_PILOTO);

        if ($fuera !== []) {
            $d->poner(Linea::noSoportada(
                self::FORMULARIO,
                '9',
                __('El piloto no calcula estos documentos: :documentos. Sin ellos el ingreso total queda incompleto y lo revisa el preparador.', ['documentos' => implode(', ', $fuera)]),
            ));

            return;
        }

        [$total, $traza, $sinValidar] = $resultado;

        $traza = $traza
            ->conOperacion(__('Líneas 2b, 3b, 4b, 5b, 6b y 7a: valen cero porque el piloto no tiene documentos que las llenen'))
            ->conResultado(__('Línea 9 del Form 1040'), $total);

        $d->poner(Linea::calculada(self::FORMULARIO, '9', $total, $traza, $sinValidar));
    }

    /** Línea 11a: línea 9 menos línea 10 (1040, p. 1). Puede ser negativa. */
    private function linea11a(Declaracion $d): void
    {
        $nueve = $d->linea(self::FORMULARIO, '9');
        $diez = $d->linea(self::FORMULARIO, '10');

        $resultado = Apoyo::leerLineas($d, self::FORMULARIO, '11a', [[self::FORMULARIO, '9', true], [self::FORMULARIO, '10', true]]);

        if ($resultado instanceof Linea) {
            $d->poner($resultado);

            return;
        }

        // leerLineas suma; aquí se resta, así que se recalcula con los valores de las dos líneas.
        $valorNueve = $nueve?->valor instanceof Dinero ? $nueve->valor : Dinero::deCentavos(0);
        $valorDiez = $diez?->valor instanceof Dinero ? $diez->valor : Dinero::deCentavos(0);
        $ingresoAjustado = $valorNueve->menos($valorDiez);

        [, $traza, $sinValidar] = $resultado;

        $traza = $traza
            ->conOperacion(__('Línea 9 menos línea 10'))
            ->conResultado(__('Línea 11a del Form 1040 (ingreso ajustado)'), $ingresoAjustado);

        $d->poner(Linea::calculada(self::FORMULARIO, '11a', $ingresoAjustado, $traza, $sinValidar));
    }
}
