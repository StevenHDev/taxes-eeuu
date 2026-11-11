<?php

namespace App\Services\Calculo\Formularios;

use App\Enums\Expediente\EstadoCivilIrs;
use App\Services\Calculo\Nucleo\Apoyo;
use App\Services\Calculo\Nucleo\Calculadora;
use App\Services\Calculo\Nucleo\Declaracion;
use App\Services\Calculo\Nucleo\Dinero;
use App\Services\Calculo\Nucleo\EntradasDeclaracion;
use App\Services\Calculo\Nucleo\Linea;
use App\Services\Calculo\Nucleo\Traza;
use InvalidArgumentException;
use RuntimeException;

/**
 * Schedule 1 (Form 1040) de 2025: líneas 3, 8z, 9, 10, 11, 15 y 26.
 *
 * Solo escribe las líneas que el piloto calcula; las demás (1, 2a, 4 a 7, 8a a
 * 8y, 12 a 14, 16 a 24z) no se escriben. Si hay un documento que las llenaría
 * (K-1, 1099-G, 1098-E), la línea total (10 o 26) sale no soportada en vez de
 * salir corta.
 *
 * Debe correr DESPUÉS de las calculadoras del Schedule C y del Schedule SE
 * (líneas 3 y 15) y ANTES de `Ingresos1040` (que lee la 10 y la 26).
 */
final class Schedule1 implements Calculadora
{
    private const FORMULARIO = 'sch-1';

    /** Líneas 1, 5 y 7 de la Parte I: reembolsos de impuestos y desempleo (1099-G) y K-1. */
    private const DOCUMENTOS_DE_INGRESO = ['1099-g', 'k-1'];

    /** Línea 21 de la Parte II: interés de préstamos estudiantiles (1098-E). */
    private const DOCUMENTOS_DE_AJUSTES = ['1098-e'];

    public function formulario(): string
    {
        return self::FORMULARIO;
    }

    public function calcular(EntradasDeclaracion $e, Declaracion $d): void
    {
        Apoyo::copiar($d, self::FORMULARIO, '3', 'sch-c', '31');
        $this->linea8z($e, $d);
        $this->linea9($d);
        $this->linea10($e, $d);
        $this->linea11($e, $d);
        Apoyo::copiar($d, self::FORMULARIO, '15', 'sch-se', '13');
        $this->linea26($e, $d);
    }

    /** Línea 8z: 1099-MISC casilla 3 (otros ingresos) más casilla 8 (pagos sustitutos). */
    private function linea8z(EntradasDeclaracion $e, Declaracion $d): void
    {
        $etiquetas = [
            '1099-misc:3' => __('1099-MISC, casilla 3 (otros ingresos)'),
            '1099-misc:8' => __('1099-MISC, casilla 8 (pagos sustitutos en lugar de dividendos o intereses)'),
        ];

        $resultado = Apoyo::leerDatos($e, self::FORMULARIO, '8z', $etiquetas);

        if ($resultado === null) {
            $d->poner(Linea::noAplica(self::FORMULARIO, '8z', __('No hay 1099-MISC con otros ingresos.')));

            return;
        }

        if ($resultado instanceof Linea) {
            $d->poner($resultado);

            return;
        }

        [$total, $traza, $sinValidar] = $resultado;

        $d->poner(Linea::calculada(self::FORMULARIO, '8z', $total, $traza->conResultado(__('Línea 8z del Schedule 1'), $total), $sinValidar));
    }

    /** Línea 9: suma de las líneas 8a a 8z; el piloto solo escribe la 8z. */
    private function linea9(Declaracion $d): void
    {
        $resultado = Apoyo::leerLineas($d, self::FORMULARIO, '9', [[self::FORMULARIO, '8z', false]]);

        if ($resultado instanceof Linea) {
            $d->poner($resultado);

            return;
        }

        [$total, $traza, $sinValidar] = $resultado;

        $d->poner(Linea::calculada(self::FORMULARIO, '9', $total, $traza->conResultado(__('Línea 9 del Schedule 1'), $total), $sinValidar));
    }

    /**
     * Línea 10: "Combine lines 1 through 7 and 9" (Schedule 1, p. 1). Del
     * piloto entran la 3 (negocio, puede ser pérdida) y la 9. Puede ser negativa.
     */
    private function linea10(EntradasDeclaracion $e, Declaracion $d): void
    {
        $resultado = Apoyo::leerLineas($d, self::FORMULARIO, '10', [[self::FORMULARIO, '3', true], [self::FORMULARIO, '9', true]]);

        if ($resultado instanceof Linea) {
            $d->poner($resultado);

            return;
        }

        $fuera = Apoyo::documentosPresentes($e, self::DOCUMENTOS_DE_INGRESO);

        if ($fuera !== []) {
            $d->poner(Linea::noSoportada(
                self::FORMULARIO,
                '10',
                __('El piloto no calcula estos documentos: :documentos (líneas 1, 5 y 7 del Schedule 1). Sin ellos el ingreso adicional queda incompleto y lo revisa el preparador.', ['documentos' => implode(', ', $fuera)]),
            ));

            return;
        }

        [$total, $traza, $sinValidar] = $resultado;

        $d->poner(Linea::calculada(self::FORMULARIO, '10', $total, $traza->conResultado(__('Línea 10 del Schedule 1'), $total), $sinValidar));
    }

    /**
     * Línea 11: gastos de educador. Hasta el tope por persona de las
     * instrucciones del 1040 de 2025 (p. 93, "Educator Expenses": USD 300, o
     * USD 600 si los dos cónyuges de una declaración conjunta son educadores
     * elegibles, sin que ninguno pase de USD 300).
     *
     * Primera versión: el tope se aplica UNA vez (el de una persona). La
     * calculadora no sabe si el cónyuge también es educador; con MFJ y un total
     * por encima del tope lo deja anotado en la traza para el preparador.
     */
    private function linea11(EntradasDeclaracion $e, Declaracion $d): void
    {
        $dato = $e->datos['recibos-utiles-educador:total'] ?? null;

        if ($dato === null) {
            $d->poner(Linea::noAplica(self::FORMULARIO, '11', __('No hay recibos de útiles de educador.')));

            return;
        }

        if (! $dato->valor instanceof Dinero) {
            $d->poner(Linea::faltaDato(self::FORMULARIO, '11', __('Falta el total de los recibos de útiles de educador.')));

            return;
        }

        try {
            $tope = Apoyo::parametroEnDolares($e->taxYear, 'schedule_1', 'tope_gastos_educador');
        } catch (RuntimeException|InvalidArgumentException) {
            $d->poner(Linea::faltaDato(
                self::FORMULARIO,
                '11',
                __('No se puede calcular: falta el parámetro schedule_1.tope_gastos_educador de :anio (Catálogo → Parámetros).', ['anio' => $e->taxYear]),
            ));

            return;
        }

        $total = $dato->valor;
        $deducible = $total->min($tope)->max(Dinero::deCentavos(0));

        $traza = Traza::vacia()
            ->conEntrada(__('Total de los recibos de útiles de educador'), $total, $dato->origen, $dato->referencia ?? 'recibos-utiles-educador:total')
            ->conParametro(__('Tope de gastos de educador por persona'), $tope, 'schedule_1.tope_gastos_educador')
            ->conOperacion(__('El menor entre el total y el tope'), $deducible);

        if ($e->estadoCivil === EstadoCivilIrs::Mfj && $total->centavos() > $tope->centavos()) {
            $traza = $traza->conOperacion(__('Con declaración conjunta, si los dos cónyuges son educadores el tope es doble: lo revisa el preparador.'));
        }

        $d->poner(Linea::calculada(
            self::FORMULARIO,
            '11',
            $deducible,
            $traza->conResultado(__('Línea 11 del Schedule 1'), $deducible),
            Apoyo::sinValidar(['recibos-utiles-educador:total' => $dato]),
        ));
    }

    /**
     * Línea 26: "Add lines 11 through 23 and 25" (Schedule 1, p. 2). Del piloto
     * entran la 11 (educador) y la 15 (mitad del impuesto de autoempleo).
     */
    private function linea26(EntradasDeclaracion $e, Declaracion $d): void
    {
        $resultado = Apoyo::leerLineas($d, self::FORMULARIO, '26', [[self::FORMULARIO, '11', true], [self::FORMULARIO, '15', true]]);

        if ($resultado instanceof Linea) {
            $d->poner($resultado);

            return;
        }

        $fuera = Apoyo::documentosPresentes($e, self::DOCUMENTOS_DE_AJUSTES);

        if ($fuera !== []) {
            $d->poner(Linea::noSoportada(
                self::FORMULARIO,
                '26',
                __('El piloto no calcula estos documentos: :documentos (línea 21 del Schedule 1). Sin ellos los ajustes quedan incompletos y los revisa el preparador.', ['documentos' => implode(', ', $fuera)]),
            ));

            return;
        }

        [$total, $traza, $sinValidar] = $resultado;

        $d->poner(Linea::calculada(self::FORMULARIO, '26', $total, $traza->conResultado(__('Línea 26 del Schedule 1'), $total), $sinValidar));
    }
}
