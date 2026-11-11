<?php

namespace App\Services\Calculo\Formularios;

use App\Enums\Expediente\ClasificacionPersona;
use App\Enums\Expediente\EstadoCivilIrs;
use App\Services\Calculo\Formularios\Creditos\Corte;
use App\Services\Calculo\Formularios\Form2441\Credito2441;
use App\Services\Calculo\Nucleo\Apoyo;
use App\Services\Calculo\Nucleo\Calculadora;
use App\Services\Calculo\Nucleo\Declaracion;
use App\Services\Calculo\Nucleo\Dinero;
use App\Services\Calculo\Nucleo\DocumentoEntrada;
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
 * Form 2441 de 2025 (crédito por cuidado de hijos y dependientes) y la línea 1e del Form 1040.
 *
 * Dos pasos, porque el 1040 los necesita en momentos distintos:
 * - `calcularBeneficios()`: Parte III (beneficios de cuidado del W-2, casilla 10) → línea 26 → 1040 línea 1e. Corre
 *   ANTES de `Ingresos1040`, que suma la 1e en la 1z. Necesita el Schedule SE (línea 3) y el Schedule 1 (línea 15)
 *   si hay negocio propio.
 * - `calcular()`: hace la Parte III otra vez (es idempotente) y la Parte II (crédito) → línea 11 → Schedule 3
 *   línea 2. Corre DESPUÉS de que existan la línea 11a (ingreso ajustado) y la 18 (impuesto) del 1040.
 *
 * Fuentes: Form 2441 de 2025 (pp. 1 y 2) e instrucciones del Form 2441 de 2025 (i2441): persona calificada (p. 1),
 * casado por separado (p. 2), tope de USD 6.000 con dos personas aunque los gastos sean de una (p. 4), ingreso del
 * trabajo de cada cónyuge (líneas 4 y 5, p. 4), cónyuge estudiante o incapacitado (p. 4), hoja del límite por el
 * impuesto (línea 10, p. 5) y Parte III (líneas 12 a 26, pp. 5 y 6).
 *
 * Lo que no se pregunta hoy y queda en la traza o en manos del preparador: beneficios trasladados o perdidos
 * (líneas 13 y 14), tope del plan del empleador menor que el legal (línea 21), proveedor que no puede serlo (cónyuge,
 * padre del niño o dependiente), gastos de 2024 pagados en 2025 (línea 9b, hoja A) y la regla de padres separados.
 * Nunca escribe en la traza el nombre, la dirección ni el SSN o EIN del proveedor: solo la referencia del recibo.
 */
final class Form2441 implements Calculadora
{
    private const F = 'f2441';

    private const F1040 = '1040';

    private const RECIBO = 'recibo-cuidado';

    public function formulario(): string
    {
        return self::F;
    }

    public function calcular(EntradasDeclaracion $e, Declaracion $d): void
    {
        $this->calcularBeneficios($e, $d);
        $this->calcularCredito($e, $d);
    }

    /** Parte III (líneas 12 a 26) y la línea 1e del 1040. */
    public function calcularBeneficios(EntradasDeclaracion $e, Declaracion $d): void
    {
        try {
            $this->parteIII($e, $d);
        } catch (Corte $corte) {
            $d->poner($corte->linea(self::F, '26'));
        }

        Apoyo::copiar($d, self::F1040, '1e', self::F, '26');
    }

    /** Partes I y II (líneas 3 a 11). */
    private function calcularCredito(EntradasDeclaracion $e, Declaracion $d): void
    {
        try {
            $this->parteII($e, $d);
        } catch (Corte $corte) {
            $d->poner($corte->linea(self::F, '11'));
        }
    }

    // ── Parte III: beneficios de cuidado del empleador ──

    private function parteIII(EntradasDeclaracion $e, Declaracion $d): void
    {
        $beneficios = $this->beneficiosDelW2($e);

        if ($beneficios['total']->esCero()) {
            throw Corte::noAplica(__('Sin beneficios de cuidado de dependientes en la casilla 10 de los W-2.'));
        }

        $this->verificarEstadoCivil($e);
        $recibos = $this->recibos($e);

        if ($recibos === []) {
            throw Corte::falta(__('Hay beneficios de cuidado en la casilla 10 del W-2, pero falta el recibo del cuidado (gastos calificados, línea 16 del Form 2441).'));
        }

        $cero = Dinero::deCentavos(0);

        // Línea 12 (i2441, p. 5): total de la casilla 10 de los W-2.
        $this->poner($d, '12', $beneficios['total'], $beneficios['traza'], $beneficios['sinValidar']);

        // Líneas 13 y 14 (i2441, p. 5): lo trasladado de 2024 y lo perdido o trasladado a 2026 no se pregunta hoy.
        $this->poner($d, '13', $cero, Traza::vacia()->conOperacion(__('Lo trasladado de 2024 y usado en el período de gracia no se pregunta hoy: cero; el preparador lo confirma.')));
        $this->poner($d, '14', $cero, Traza::vacia()->conOperacion(__('Lo perdido o trasladado al año siguiente no se pregunta hoy: cero; el preparador lo confirma.')));

        $linea15 = $beneficios['total'];
        $this->poner($d, '15', $linea15, Traza::vacia()
            ->conEntrada(self::nombreLinea('12'), $beneficios['total'], OrigenTraza::Linea, self::F.':12')
            ->conOperacion(__('Líneas 12 más 13 menos 14.')), $beneficios['sinValidar']);

        // Línea 16 (i2441, p. 5): gastos calificados del año para las personas calificadas.
        [$gastos, $trazaGastos, $sinValidarGastos] = $this->gastosCalificados($e, $recibos);
        $this->poner($d, '16', $gastos, $trazaGastos, $sinValidarGastos);

        $linea17 = $linea15->min($gastos);
        $this->poner($d, '17', $linea17, Traza::vacia()->conOperacion(__('La menor entre las líneas 15 y 16.')), [...$beneficios['sinValidar'], ...$sinValidarGastos]);

        // Líneas 18 y 19 (i2441, p. 5): ingreso del trabajo de cada uno, sin los beneficios de la línea 12.
        [$linea18, $linea19, $trazaIngreso, $sinValidarIngreso] = $this->ingresosDelTrabajo($e, $d, '18', '19', null);
        $this->poner($d, '18', $linea18, $trazaIngreso['contribuyente'], $sinValidarIngreso);
        $this->poner($d, '19', $linea19, $trazaIngreso['otro'], $sinValidarIngreso);

        $linea20 = $linea17->min($linea18)->min($linea19)->max($cero);
        $this->poner($d, '20', $linea20, Traza::vacia()->conOperacion(__('La menor entre las líneas 17, 18 y 19; si es cero o menos, cero.')), $sinValidarIngreso);

        // Línea 21 (i2441, p. 5): máximo que se puede excluir en el año. Con MFS no se llega aquí.
        try {
            $linea21 = Apoyo::parametroEnDolares($e->taxYear, 'credito_cuidado_dependientes', 'tope_beneficios_excluibles');
        } catch (RuntimeException $excepcion) {
            throw Corte::falta(Apoyo::motivoSinParametro($e->taxYear, $excepcion));
        }

        $this->poner($d, '21', $linea21, Traza::vacia()
            ->conParametro(__('Máximo de beneficios de cuidado que se excluyen en el año'), $linea21, 'credito_cuidado_dependientes.tope_beneficios_excluibles')
            ->conOperacion(__('El tope del plan del empleador no se pregunta hoy; si es menor, el preparador lo cambia.')));

        // Línea 22 (p. 2 del Form 2441): beneficios de un negocio propio. Los de la casilla 10 del W-2 son de un empleador.
        $this->poner($d, '22', $cero, Traza::vacia()->conOperacion(__('Los beneficios de la casilla 10 del W-2 vienen de un empleador, no de un negocio propio: cero.')));

        $linea23 = $linea15->menos($cero);
        $this->poner($d, '23', $linea23, Traza::vacia()->conOperacion(__('Línea 15 menos línea 22.')), $beneficios['sinValidar']);

        // Línea 24: la menor entre 20, 21 y 22 (con 22 en cero, cero). Línea 25: con "No" en la 22, la menor entre 20 y 21.
        $linea24 = $linea20->min($linea21)->min($cero);
        $this->poner($d, '24', $linea24, Traza::vacia()->conOperacion(__('La menor entre las líneas 20, 21 y 22.')));

        $linea25 = $linea20->min($linea21)->max($cero);
        $this->poner($d, '25', $linea25, Traza::vacia()->conOperacion(__('Sin beneficios de un negocio propio (línea 22 en cero): la menor entre las líneas 20 y 21.')), $sinValidarIngreso);

        $linea26 = $linea23->menos($linea25)->max($cero);
        $this->poner($d, '26', $linea26, Traza::vacia()
            ->conEntrada(self::nombreLinea('23'), $linea23, OrigenTraza::Linea, self::F.':23')
            ->conEntrada(self::nombreLinea('25'), $linea25, OrigenTraza::Linea, self::F.':25')
            ->conOperacion(__('Línea 23 menos línea 25; si es cero o menos, cero. Va a la línea 1e del Form 1040.')), [...$beneficios['sinValidar'], ...$sinValidarGastos, ...$sinValidarIngreso]);
    }

    // ── Partes I y II: proveedores y crédito ──

    private function parteII(EntradasDeclaracion $e, Declaracion $d): void
    {
        $recibos = $this->recibos($e);
        $beneficios = $this->beneficiosDelW2($e);
        $conBeneficios = ! $beneficios['total']->esCero();

        if ($recibos === [] && ! $conBeneficios) {
            throw Corte::noAplica(__('Sin recibos de cuidado de hijos o dependientes.'));
        }

        $this->verificarEstadoCivil($e);

        if ($recibos === []) {
            throw Corte::falta(__('Hay beneficios de cuidado en la casilla 10 del W-2, pero falta el recibo del cuidado (gastos calificados, línea 16 del Form 2441).'));
        }

        $this->parteI($d, $recibos);
        [$gastos, $trazaGastos, $sinValidarGastos] = $this->gastosCalificados($e, $recibos);
        $this->poner($d, 'parte-ii', $gastos, $trazaGastos, $sinValidarGastos);

        $tope = $this->topeDeGastos($e);
        $cero = Dinero::deCentavos(0);
        $sinValidar = $sinValidarGastos;

        if ($conBeneficios) {
            // Líneas 27 a 31 (Form 2441, p. 2): con beneficios, el tope se reduce por lo excluido o deducido.
            $linea26 = $d->linea(self::F, '26');

            if ($linea26 !== null && $linea26->estado !== EstadoLinea::Calculada) {
                throw Corte::deLinea($linea26);
            }

            $lectura = Apoyo::leerVarias($d, [[self::F, '24'], [self::F, '25']], self::F, '28');

            if ($lectura instanceof Linea) {
                throw Corte::deLinea($lectura);
            }

            [[$linea24, $linea25], $trazaBeneficios, $sinValidarBeneficios] = $lectura;
            $sinValidar = [...$sinValidar, ...$sinValidarBeneficios];

            $this->poner($d, '27', $tope['monto'], $tope['traza']);
            $linea28 = $linea24->mas($linea25);
            $this->poner($d, '28', $linea28, $trazaBeneficios->conOperacion(__('Líneas 24 más 25.')), $sinValidarBeneficios);
            $linea29 = $tope['monto']->menos($linea28)->max($cero);
            $this->poner($d, '29', $linea29, Traza::vacia()->conOperacion(__('Línea 27 menos línea 28; si es cero o menos, no hay crédito.')), $sinValidarBeneficios);
            $linea30 = $gastos->menos($linea28)->max($cero);
            $this->poner($d, '30', $linea30, $trazaGastos->conOperacion(__('Gastos de la línea 2 sin los beneficios de la línea 28.')), $sinValidar);
            $linea3 = $linea29->min($linea30);
            $this->poner($d, '31', $linea3, Traza::vacia()->conOperacion(__('La menor entre las líneas 29 y 30; va a la línea 3.')), $sinValidar);
            $this->poner($d, '3', $linea3, Traza::vacia()
                ->conEntrada(self::nombreLinea('31'), $linea3, OrigenTraza::Linea, self::F.':31')
                ->conOperacion(__('Con la Parte III, la línea 3 es la línea 31.')), $sinValidar);
        } else {
            $linea3 = $gastos->min($tope['monto']);
            $this->poner($d, '3', $linea3, $tope['traza']
                ->conEntrada(__('Gastos calificados de la línea 2'), $gastos, OrigenTraza::Linea, self::F.':parte-ii')
                ->conOperacion(__('Gastos de la línea 2, sin pasar del tope.')), $sinValidar);
        }

        // Líneas 4 y 5 (i2441, p. 4): ingreso del trabajo de cada uno; incluye la línea 1e (está en la 1z).
        [$linea4, $linea5, $trazaIngreso, $sinValidarIngreso] = $this->ingresosDelTrabajo($e, $d, '4', '5', $beneficios);
        $this->poner($d, '4', $linea4, $trazaIngreso['contribuyente'], $sinValidarIngreso);
        $this->poner($d, '5', $linea5, $trazaIngreso['otro'], $sinValidarIngreso);
        $sinValidar = [...$sinValidar, ...$sinValidarIngreso];

        $linea6 = $linea3->min($linea4)->min($linea5)->max($cero);
        $this->poner($d, '6', $linea6, Traza::vacia()->conOperacion(__('La menor entre las líneas 3, 4 y 5; si es cero o menos, cero.')), $sinValidar);

        // Línea 7: ingreso ajustado (1040, línea 11a). Línea 8: decimal de la tabla.
        $lectura = Apoyo::leerUna($d, self::F1040, '11a', self::F, '7');

        if ($lectura instanceof Linea) {
            throw Corte::deLinea($lectura);
        }

        [$linea7, $sinValidar7] = $lectura;
        $sinValidar = [...$sinValidar, ...$sinValidar7];
        $this->poner($d, '7', $linea7, Traza::vacia()->conEntrada(Apoyo::nombreLinea(self::F1040, '11a'), $linea7, OrigenTraza::Linea, self::F1040.':11a'), $sinValidar7);

        try {
            $decimal = Credito2441::decimalParaLinea7($linea7, $e->taxYear, $e->estadoCivil === EstadoCivilIrs::Mfj);
        } catch (RuntimeException $excepcion) {
            throw Corte::falta(Apoyo::motivoSinParametro($e->taxYear, $excepcion));
        }

        $d->poner(Linea::calculada(self::F, '8', $decimal, Traza::vacia()
            ->conEntrada(self::nombreLinea('7'), $linea7, OrigenTraza::Linea, self::F.':7')
            ->conParametro(__('Tabla de la línea 8 del Form 2441 (decimal según el ingreso ajustado)'), $decimal, 'credito_cuidado_dependientes.tabla')
            ->conResultado(self::nombreLinea('8'), $decimal), $sinValidar7));

        $linea9a = $linea6->por($decimal);
        $this->poner($d, '9a', $linea9a, Traza::vacia()->conOperacion(__('Línea 6 por el decimal de la línea 8.')), $sinValidar);
        $d->poner(Linea::noAplica(self::F, '9b', __('Sin gastos de 2024 pagados en 2025 (los recibos son del año).')));
        $this->poner($d, '9c', $linea9a, Traza::vacia()->conOperacion(__('Líneas 9a más 9b.')), $sinValidar);

        // Línea 10 (hoja del límite por el impuesto, i2441, p. 5): 1040 línea 18 menos Schedule 3 líneas 1 y 6l.
        $lectura = Apoyo::leerUna($d, self::F1040, '18', self::F, '10');

        if ($lectura instanceof Linea) {
            throw Corte::deLinea($lectura);
        }

        [$linea18, $sinValidar18] = $lectura;
        $linea10 = $linea18->max($cero);
        $this->poner($d, '10', $linea10, Traza::vacia()
            ->conEntrada(Apoyo::nombreLinea(self::F1040, '18'), $linea18, OrigenTraza::Linea, self::F1040.':18')
            ->conOperacion(__('Hoja del límite por el impuesto: línea 18 del 1040 menos las líneas 1 (crédito por impuestos extranjeros) y 6l del Schedule 3, que el piloto no tiene; si es cero o menos, no hay crédito.')), $sinValidar18);

        $linea11 = $linea9a->min($linea10);
        $this->poner($d, '11', $linea11, Traza::vacia()
            ->conEntrada(self::nombreLinea('9c'), $linea9a, OrigenTraza::Linea, self::F.':9c')
            ->conEntrada(self::nombreLinea('10'), $linea10, OrigenTraza::Linea, self::F.':10')
            ->conOperacion(__('La menor entre las líneas 9c y 10. Va a la línea 2 del Schedule 3.')), [...$sinValidar, ...$sinValidar18]);
    }

    /**
     * Parte I: un proveedor por nombre (comparado sin mayúsculas ni espacios de más, nunca escrito). La traza lleva
     * solo la referencia de cada recibo y si le falta el SSN o EIN (lo cubre la regla R-12).
     *
     * @param  list<array{doc: DocumentoEntrada, monto: Dinero, persona: ?PersonaEntrada}>  $recibos
     */
    private function parteI(Declaracion $d, array $recibos): void
    {
        $proveedores = [];
        $total = Dinero::deCentavos(0);
        $sinValidar = [];

        foreach ($recibos as $recibo) {
            $nombre = $recibo['doc']->casilla('proveedor-nombre')?->valor;
            $clave = is_string($nombre) && trim($nombre) !== ''
                ? mb_strtolower(preg_replace('/\s+/', ' ', trim($nombre)) ?? '')
                : 'sin-nombre#'.$recibo['doc']->itemId;
            $proveedores[$clave][] = $recibo;
            $total = $total->mas($recibo['monto']);
            $sinValidar = [...$sinValidar, ...$this->sinValidarDe($recibo['doc'], ['monto-pagado'])];
        }

        $traza = Traza::vacia();
        $numero = 0;

        foreach ($proveedores as $grupo) {
            $numero++;
            $monto = Dinero::deCentavos(0);
            $sinTin = false;

            foreach ($grupo as $recibo) {
                $monto = $monto->mas($recibo['monto']);
                $tin = $recibo['doc']->casilla('proveedor-tin')?->valor;
                $sinTin = $sinTin || ! is_string($tin) || trim($tin) === '';
                $traza = $traza->conEntrada(__('Proveedor :n, recibo', ['n' => (string) $numero]), $recibo['monto'], OrigenTraza::Leido, $recibo['doc']->referencia());
            }

            if ($sinTin) {
                $traza = $traza->conOperacion(__('Al proveedor :n le falta el SSN o EIN: el crédito puede negarse si no se muestra la diligencia debida (i2441, p. 3; regla R-12).', ['n' => (string) $numero]));
            }
        }

        if ($numero > 3) {
            $traza = $traza->conOperacion(__('Más de tres proveedores: se marca la casilla de arriba de la línea 1 y se adjunta una hoja con los demás (i2441, p. 2).'));
        }

        $this->poner($d, 'parte-i', $total, $traza->conOperacion(__('Columna (e): total pagado a los proveedores.')), $sinValidar);
    }

    // ── Datos de entrada ──

    /**
     * Beneficios de la casilla 10 de cada W-2.
     *
     * @return array{total: Dinero, porPersona: array<int, Dinero>, traza: Traza, sinValidar: list<string>}
     */
    private function beneficiosDelW2(EntradasDeclaracion $e): array
    {
        $this->exigirDetalleDeW2($e);
        $total = Dinero::deCentavos(0);
        $porPersona = [];
        $traza = Traza::vacia();
        $sinValidar = [];

        foreach ($e->documentos('w2') as $doc) {
            $dato = $doc->casilla('10');

            if ($dato === null) {
                continue;
            }

            if (! $dato->valor instanceof Dinero) {
                throw Corte::falta(__('Falta el monto de la casilla 10 del W-2 #:item.', ['item' => (string) $doc->itemId]));
            }

            if ($dato->valor->esCero()) {
                continue;
            }

            $total = $total->mas($dato->valor);
            $clave = $doc->personaId ?? 0;
            $porPersona[$clave] = ($porPersona[$clave] ?? Dinero::deCentavos(0))->mas($dato->valor);
            $traza = $traza->conEntrada(__('W-2, casilla 10 (beneficios de cuidado de dependientes)'), $dato->valor, $dato->origen, $doc->referencia().':10');
            $sinValidar = [...$sinValidar, ...$this->sinValidarDe($doc, ['10'])];
        }

        return ['total' => $total, 'porPersona' => $porPersona, 'traza' => $traza, 'sinValidar' => $sinValidar];
    }

    /**
     * Los recibos del cuidado, cada uno con su monto y la persona cuidada (`personaId` del recibo).
     *
     * @return list<array{doc: DocumentoEntrada, monto: Dinero, persona: ?PersonaEntrada}>
     */
    private function recibos(EntradasDeclaracion $e): array
    {
        $documentos = $e->documentos(self::RECIBO);

        if ($documentos === [] && Apoyo::hayDatos($e, self::RECIBO)) {
            throw Corte::falta(__('Falta el detalle de cada recibo del cuidado (proveedor y persona cuidada).'));
        }

        $recibos = [];

        foreach ($documentos as $doc) {
            $monto = $doc->monto('monto-pagado');

            if ($monto === null) {
                throw Corte::falta(__('Falta el monto pagado del recibo del cuidado #:item.', ['item' => (string) $doc->itemId]));
            }

            foreach (['periodo-desde', 'periodo-hasta'] as $casilla) {
                $fecha = $doc->casilla($casilla)?->valor;

                if (is_string($fecha) && preg_match('/^(\d{4})-/', $fecha, $m) === 1 && (int) $m[1] !== $e->taxYear) {
                    throw Corte::noSoportada(__('El recibo del cuidado #:item cubre un período fuera de :anio: los gastos de otro año (hoja A de la línea 9b o crédito del año siguiente) los revisa el preparador.', ['item' => (string) $doc->itemId, 'anio' => (string) $e->taxYear]));
                }
            }

            $recibos[] = ['doc' => $doc, 'monto' => $monto, 'persona' => Apoyo::personaPorId($e, $doc->personaId)];
        }

        return $recibos;
    }

    /**
     * Gastos calificados de las personas calificadas (línea 2, columna d; línea 16). Un recibo de alguien que no es
     * persona calificada no cuenta; si no se sabe, falta el dato.
     *
     * @param  list<array{doc: DocumentoEntrada, monto: Dinero, persona: ?PersonaEntrada}>  $recibos
     * @return array{0: Dinero, 1: Traza, 2: list<string>}
     */
    private function gastosCalificados(EntradasDeclaracion $e, array $recibos): array
    {
        $limite = $this->edadLimite($e);
        $total = Dinero::deCentavos(0);
        $traza = Traza::vacia()->conParametro(__('Edad límite del hijo calificado (menor de)'), (string) $limite, 'credito_cuidado_dependientes.edad_limite_dependiente');
        $sinValidar = [];

        foreach ($recibos as $recibo) {
            $doc = $recibo['doc'];
            $persona = $recibo['persona'];

            if ($doc->personaId === null || $persona === null) {
                throw Corte::falta(__('Falta saber a quién cuidó el recibo del cuidado #:item (persona del bloque C).', ['item' => (string) $doc->itemId]));
            }

            if (! $this->cuenta($persona, $limite, $doc)) {
                $traza = $traza->conOperacion(__('El recibo :recibo es de una persona que no es calificada: no cuenta.', ['recibo' => $doc->referencia()]));

                continue;
            }

            $total = $total->mas($recibo['monto']);
            $traza = $traza->conEntrada(__('Gasto del recibo para la persona :persona', ['persona' => 'persona#'.$persona->id]), $recibo['monto'], OrigenTraza::Leido, $doc->referencia());
            $sinValidar = [...$sinValidar, ...$this->sinValidarDe($doc, ['monto-pagado'])];
        }

        return [$total, $traza->conResultado(__('Gastos calificados de las personas calificadas'), $total), $sinValidar];
    }

    /**
     * Persona calificada (i2441, p. 1): hijo calificado dependiente menor de 13; o una persona que no puede cuidarse
     * sola (el cónyuge o un dependiente). "No puede cuidarse sola" no se pregunta: si puede serlo, falta el dato.
     */
    private function cuenta(PersonaEntrada $persona, int $limite, DocumentoEntrada $doc): bool
    {
        $esHijo = $persona->rol === RolPersona::Dependiente && $persona->clasificacion === ClasificacionPersona::HijoCalificado;

        if ($esHijo && $persona->edadAlFinDeAnio === null) {
            throw Corte::falta(__('Falta la edad de la persona cuidada en el recibo #:item.', ['item' => (string) $doc->itemId]));
        }

        if ($esHijo && $persona->edadAlFinDeAnio < $limite) {
            return true;
        }

        if ($esHijo && $persona->edadAlFinDeAnio === $limite) {
            throw Corte::falta(__('La persona cuidada en el recibo #:item cumplió :edad años en el año: solo cuentan los gastos de antes del cumpleaños (i2441, p. 1); el preparador los separa.', ['item' => (string) $doc->itemId, 'edad' => (string) $limite]));
        }

        $puedeSerIncapacitado = match ($persona->rol) {
            RolPersona::Conyuge => true,
            RolPersona::Dependiente => $persona->clasificacion->esDependiente() && $persona->incapacitado !== false,
            RolPersona::Contribuyente => false,
        };

        if ($puedeSerIncapacitado) {
            throw Corte::falta(__('El recibo #:item es de una persona que solo cuenta si no puede cuidarse sola (i2441, p. 1); el preparador lo confirma.', ['item' => (string) $doc->itemId]));
        }

        return false;
    }

    /**
     * Línea 3 y 27: USD 3.000 con una persona calificada y USD 6.000 con dos o más, aunque los gastos sean de una
     * sola (i2441, p. 4). Se cuentan los hijos calificados menores de 13 al menos parte del año.
     *
     * @return array{monto: Dinero, traza: Traza}
     */
    private function topeDeGastos(EntradasDeclaracion $e): array
    {
        $limite = $this->edadLimite($e);
        $calificadas = 0;

        foreach ($e->personas as $p) {
            if ($p->rol !== RolPersona::Dependiente || $p->clasificacion !== ClasificacionPersona::HijoCalificado) {
                continue;
            }

            if ($p->edadAlFinDeAnio === null) {
                throw Corte::falta(__('Falta la edad de un hijo calificado para saber cuántas personas calificadas hay (tope de la línea 3 del Form 2441).'));
            }

            if ($p->edadAlFinDeAnio <= $limite) {
                $calificadas++;
            }
        }

        // Los recibos ya probaron que hay al menos una persona calificada.
        $clave = $calificadas >= 2 ? 'tope_gastos_2_mas_personas' : 'tope_gastos_1_persona';

        try {
            $monto = Apoyo::parametroEnDolares($e->taxYear, 'credito_cuidado_dependientes', $clave);
        } catch (RuntimeException $excepcion) {
            throw Corte::falta(Apoyo::motivoSinParametro($e->taxYear, $excepcion));
        }

        $traza = Traza::vacia()
            ->conOperacion(__('Hijos calificados menores de :edad al menos parte del año: :n.', ['edad' => (string) $limite, 'n' => (string) max(1, $calificadas)]))
            ->conParametro(__('Tope de gastos del Form 2441 (línea 3)'), $monto, 'credito_cuidado_dependientes.'.$clave);

        return ['monto' => $monto, 'traza' => $traza];
    }

    private function edadLimite(EntradasDeclaracion $e): int
    {
        try {
            return (int) ParametrosFiscales::valorRequerido($e->taxYear, 'credito_cuidado_dependientes', 'edad_limite_dependiente');
        } catch (RuntimeException $excepcion) {
            throw Corte::falta(Apoyo::motivoSinParametro($e->taxYear, $excepcion));
        }
    }

    /**
     * Ingreso del trabajo de cada uno (i2441, líneas 4 y 5, p. 4; líneas 18 y 19, p. 5): W-2 casilla 1 de esa persona
     * más la ganancia neta de autoempleo si es la dueña del negocio (Schedule SE línea 3 menos Schedule 1 línea 15).
     * En conjunta la segunda es la del cónyuge; en los demás, la misma del contribuyente. Si alguno no tuvo ingreso
     * del trabajo, falta saber si fue estudiante o incapacitado (ingreso supuesto, i2441, p. 4), que no se pregunta.
     *
     * Con `$beneficios` (líneas 4 y 5) suma la línea 26 (1040 línea 1e, parte de la 1z) a quien recibió los
     * beneficios; en las líneas 18 y 19 no entra (i2441, p. 5).
     *
     * @param  array{total: Dinero, porPersona: array<int, Dinero>, traza: Traza, sinValidar: list<string>}|null  $beneficios
     * @return array{0: Dinero, 1: Dinero, 2: array{contribuyente: Traza, otro: Traza}, 3: list<string>}
     */
    private function ingresosDelTrabajo(EntradasDeclaracion $e, Declaracion $d, string $lineaPropia, string $lineaOtro, ?array $beneficios): array
    {
        $contribuyente = Apoyo::persona($e, RolPersona::Contribuyente);

        if ($contribuyente === null) {
            throw Corte::falta(__('Falta el contribuyente entre las personas del expediente.'));
        }

        $conjunta = $e->estadoCivil === EstadoCivilIrs::Mfj;
        $conyuge = $conjunta ? Apoyo::persona($e, RolPersona::Conyuge) : null;

        if ($conjunta && $conyuge === null) {
            throw Corte::falta(__('Falta el cónyuge entre las personas del expediente.'));
        }

        $autoempleo = $this->autoempleo($e, $d, $contribuyente);
        $gravables = $beneficios !== null ? $this->beneficiosGravablesPorPersona($d, $beneficios) : [];

        [$propio, $trazaPropia, $sinValidar] = $this->ingresoDe($e, $contribuyente, $autoempleo, $gravables);
        $this->exigirIngreso($propio, $contribuyente, __('El contribuyente'));

        if ($conyuge !== null) {
            [$otro, $trazaOtro, $sinValidarOtro] = $this->ingresoDe($e, $conyuge, $autoempleo, $gravables);
            $this->exigirIngreso($otro, $conyuge, __('El cónyuge'));
            $sinValidar = [...$sinValidar, ...$sinValidarOtro];
        } else {
            $otro = $propio;
            $trazaOtro = Traza::vacia()
                ->conEntrada(self::nombreLinea($lineaPropia), $propio, OrigenTraza::Linea, self::F.':'.$lineaPropia)
                ->conOperacion(__('No es declaración conjunta: el mismo monto de la línea :linea.', ['linea' => $lineaPropia]));
        }

        return [$propio, $otro, ['contribuyente' => $trazaPropia, 'otro' => $trazaOtro], array_values(array_unique($sinValidar))];
    }

    /**
     * Sin ingreso del trabajo, el IRS le supone USD 250 o 500 al mes solo a quien fue estudiante a tiempo completo
     * o estuvo incapacitado (i2441, p. 4). Si se sabe que no fue ni lo uno ni lo otro, cuenta su ingreso real
     * (cero o pérdida) y el crédito sale en cero; si no se sabe, o si fue, lo confirma el preparador.
     */
    private function exigirIngreso(Dinero $ingreso, PersonaEntrada $persona, string $quien): void
    {
        if (! $ingreso->esNegativo() && ! $ingreso->esCero()) {
            return;
        }

        if ($persona->esEstudiante === false && $persona->incapacitado === false) {
            return;
        }

        throw Corte::falta(__(':quien no tuvo ingreso del trabajo: el preparador confirma si fue estudiante a tiempo completo o estuvo incapacitado (ingreso supuesto de USD 250 o 500 al mes, i2441, p. 4).', ['quien' => $quien]));
    }

    /**
     * @param  array<int, array{0: Dinero, 1: list<string>}>  $autoempleo
     * @param  array<int, Dinero>  $gravables
     * @return array{0: Dinero, 1: Traza, 2: list<string>}
     */
    private function ingresoDe(EntradasDeclaracion $e, PersonaEntrada $persona, array $autoempleo, array $gravables): array
    {
        $total = Dinero::deCentavos(0);
        $traza = Traza::vacia();
        $sinValidar = [];

        foreach ($e->documentos('w2') as $doc) {
            if ($doc->personaId === null) {
                throw Corte::falta(__('Falta saber de quién es el W-2 #:item.', ['item' => (string) $doc->itemId]));
            }

            if ($doc->personaId !== $persona->id) {
                continue;
            }

            $salario = $doc->monto('1');

            if ($salario === null) {
                throw Corte::falta(__('Falta la casilla 1 del W-2 #:item.', ['item' => (string) $doc->itemId]));
            }

            $total = $total->mas($salario);
            $traza = $traza->conEntrada(__('W-2, casilla 1 (salarios)'), $salario, $doc->casilla('1')?->origen ?? OrigenTraza::Leido, $doc->referencia().':1');
            $sinValidar = [...$sinValidar, ...$this->sinValidarDe($doc, ['1'])];
        }

        if (isset($autoempleo[$persona->id])) {
            [$neto, $sinValidarNeto] = $autoempleo[$persona->id];
            $total = $total->mas($neto);
            $traza = $traza->conEntrada(__('Ganancia neta de autoempleo (línea 3 del Schedule SE menos línea 15 del Schedule 1)'), $neto, OrigenTraza::Linea, 'sch-se:3');
            $sinValidar = [...$sinValidar, ...$sinValidarNeto];
        }

        if (isset($gravables[$persona->id])) {
            $total = $total->mas($gravables[$persona->id]);
            $traza = $traza->conEntrada(__('Beneficios de cuidado gravables (línea 26, 1040 línea 1e)'), $gravables[$persona->id], OrigenTraza::Linea, self::F.':26');
        }

        return [$total, $traza->conResultado(__('Ingreso del trabajo de la persona :persona', ['persona' => 'persona#'.$persona->id]), $total), $sinValidar];
    }

    /**
     * La línea 26 (beneficios gravables) es parte de la 1z y del ingreso del trabajo de quien los recibió. Si los
     * recibieron las dos personas, no se sabe cómo repartirla.
     *
     * @param  array{total: Dinero, porPersona: array<int, Dinero>, traza: Traza, sinValidar: list<string>}  $beneficios
     * @return array<int, Dinero>
     */
    private function beneficiosGravablesPorPersona(Declaracion $d, array $beneficios): array
    {
        if ($beneficios['total']->esCero()) {
            return [];
        }

        $lectura = Apoyo::leerUna($d, self::F, '26', self::F, '4');

        if ($lectura instanceof Linea) {
            throw Corte::deLinea($lectura);
        }

        [$gravable] = $lectura;

        if ($gravable->esCero()) {
            return [];
        }

        if (count($beneficios['porPersona']) !== 1 || array_key_first($beneficios['porPersona']) === 0) {
            throw Corte::falta(__('Hay beneficios de cuidado gravables (1040 línea 1e) de más de una persona o de un W-2 sin dueño: el preparador los reparte entre las líneas 4 y 5.'));
        }

        return [array_key_first($beneficios['porPersona']) => $gravable];
    }

    /**
     * Ganancia neta de autoempleo (i2441, p. 4, punto 2): Schedule SE línea 3 menos Schedule 1 línea 15, de la dueña
     * del negocio (decisión B de la ola 3: la persona de los 1099-NEC y 1099-K). Sin negocio, nada.
     *
     * @return array<int, array{0: Dinero, 1: list<string>}>
     */
    private function autoempleo(EntradasDeclaracion $e, Declaracion $d, PersonaEntrada $contribuyente): array
    {
        $hayNegocio = Apoyo::hayDatos($e, '1099-nec') || Apoyo::hayDatos($e, '1099-k') || $e->sumasRegistros !== [];

        if (! $hayNegocio) {
            return [];
        }

        $duenos = [];

        foreach ([...$e->documentos('1099-nec'), ...$e->documentos('1099-k')] as $doc) {
            if ($doc->personaId === null) {
                throw Corte::falta(__('Falta saber de quién es el :documento #:item.', ['documento' => strtoupper($doc->codigo), 'item' => (string) $doc->itemId]));
            }

            $duenos[$doc->personaId] = true;
        }

        if (count($duenos) > 1) {
            throw Corte::noSoportada(__('Hay trabajo independiente de las dos personas (dos negocios): lo calcula el preparador; llega en F7.'));
        }

        if ($duenos === [] && $e->estadoCivil === EstadoCivilIrs::Mfj) {
            throw Corte::falta(__('Falta saber de quién es el negocio de los registros (contribuyente o cónyuge).'));
        }

        $dueno = $duenos === [] ? $contribuyente->id : (int) array_key_first($duenos);

        $lectura = Apoyo::leerVarias($d, [['sch-se', '3'], ['sch-1', '15']], self::F, '4');

        if ($lectura instanceof Linea) {
            throw Corte::deLinea($lectura);
        }

        [[$se3, $mitad], , $sinValidar] = $lectura;

        return [$dueno => [$se3->menos($mitad), $sinValidar]];
    }

    private function verificarEstadoCivil(EntradasDeclaracion $e): void
    {
        if ($e->estadoCivil === null) {
            throw Corte::falta(Apoyo::motivoSinEstadoCivil());
        }

        // i2441, p. 2, "Married Persons Filing Separately": solo si vivió separado del cónyuge los últimos 6 meses,
        // su casa fue la de la persona calificada más de medio año y pagó más de la mitad de mantenerla. No se pregunta.
        if ($e->estadoCivil === EstadoCivilIrs::Mfs) {
            throw Corte::falta(__('Con MFS, este crédito lo revisa el preparador.'));
        }
    }

    /** Con datos de W-2 sin el detalle por documento no se puede saber de quién es cada uno. */
    private function exigirDetalleDeW2(EntradasDeclaracion $e): void
    {
        if ($e->documentos('w2') === [] && Apoyo::hayDatos($e, 'w2')) {
            throw Corte::falta(__('Falta el detalle de cada W-2 (de quién es cada uno).'));
        }
    }

    /**
     * @param  list<string>  $casillas
     * @return list<string>
     */
    private function sinValidarDe(DocumentoEntrada $doc, array $casillas): array
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
