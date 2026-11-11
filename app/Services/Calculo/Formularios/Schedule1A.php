<?php

namespace App\Services\Calculo\Formularios;

use App\Enums\Expediente\EstadoCivilIrs;
use App\Services\Calculo\Formularios\Deducciones\Mayores;
use App\Services\Calculo\Nucleo\Apoyo;
use App\Services\Calculo\Nucleo\Calculadora;
use App\Services\Calculo\Nucleo\Declaracion;
use App\Services\Calculo\Nucleo\Dinero;
use App\Services\Calculo\Nucleo\DocumentoEntrada;
use App\Services\Calculo\Nucleo\EntradasDeclaracion;
use App\Services\Calculo\Nucleo\Linea;
use App\Services\Calculo\Nucleo\OrigenTraza;
use App\Services\Calculo\Nucleo\PersonaEntrada;
use App\Services\Calculo\Nucleo\RolPersona;
use App\Services\Calculo\Nucleo\Traza;
use App\Support\ParametrosFiscales;
use InvalidArgumentException;
use RuntimeException;

/**
 * Schedule 1-A (Form 1040) de 2025, "Additional Deductions": Partes I a VI.
 * Fuentes: el formulario (`f1040s1a`, p. 1 y 2) y sus instrucciones, que en 2025
 * vienen dentro de las del 1040 (`i1040gi`, p. 101 a 110).
 *
 * - Parte I (líneas 1 y 3): MAGI = línea 11b del 1040. Las líneas 2a a 2e (Puerto Rico,
 *   Forms 2555 y 4563) no son del piloto.
 * - Parte II, propinas (4c, 6, 7 a 13): la 4c sale de la constancia de propinas, por
 *   persona. La línea 5 (propinas del negocio, de los 1099) no se calcula: en 2025 los 1099
 *   no separan las propinas (p. 103) y hay que limitarlas a la ganancia neta (p. 104).
 * - Parte III, horas extra (14c, 15 a 21): la 14c sale de la constancia (el recargo).
 * - Parte IV, intereses del vehículo (23, 24, 30): exige el ensamblaje final en EE. UU.
 *   (p. 109), que hoy no se lee; con un vehículo nuevo, la parte sale `falta_dato`.
 * - Parte V, mayores (31 a 37), por persona.
 * - Parte VI (38) = 13 + 21 + 30 + 37 → 1040 línea 13b (la copia `Deducciones`).
 *
 * Propinas, horas extra y mayores exigen declarar en conjunto si se está casado
 * (cuadros "Caution" de las Partes II, III y V): con MFS esas partes no aplican.
 *
 * Debe correr DESPUÉS de `Ingresos1040` (lee la 11b). `Deducciones` la llama en su orden.
 * Escribe la numeración de 2025: en 2026 el Schedule 1-A cambia de número de línea
 * (pendiente 4 de la ola 2).
 */
final class Schedule1A implements Calculadora
{
    private const FORMULARIO = 'sch-1a';

    private const CONSTANCIA = 'constancia-horas-propinas';

    private const PRESTAMO = 'estado-prestamo-vehiculo';

    /** Las líneas 10/11, 18/19 y 27/28 dividen el exceso de MAGI "by $1,000" (Schedule 1-A de 2025). */
    private const CENTAVOS_POR_1000 = 100000;

    public function formulario(): string
    {
        return self::FORMULARIO;
    }

    public function calcular(EntradasDeclaracion $e, Declaracion $d): void
    {
        // La Parte IV (línea 30) siempre termina en `vehiculo()`: no necesita el MAGI todavía.
        $d->poner($this->vehiculo($e));

        // [línea final, lo que entra a la parte (o la línea final ya resuelta), cómo se termina con el MAGI]
        $partes = [
            ['13', $this->propinasOHorasExtra($e, 'propinas'), fn (array $parte, array $magi) => $this->reduccionPropinasOHorasExtra($e, $d, 'propinas', $parte, $magi)],
            ['21', $this->propinasOHorasExtra($e, 'horas-extra'), fn (array $parte, array $magi) => $this->reduccionPropinasOHorasExtra($e, $d, 'horas-extra', $parte, $magi)],
            ['37', $this->mayores($e), fn (array $parte, array $magi) => $this->reduccionMayores($e, $d, $parte, $magi)],
        ];

        $magi = null;

        foreach ($partes as [$linea, $parte, $terminar]) {
            if ($parte instanceof Linea) {
                $d->poner($parte);

                continue;
            }

            $magi ??= $this->magi($d);

            if ($magi instanceof Linea) {
                $d->poner(Linea::faltaDato(self::FORMULARIO, $linea, $magi->motivo ?? __('Falta la línea 3 del Schedule 1-A.')));

                continue;
            }

            $terminar($parte, $magi);
        }

        $this->linea38($d);
    }

    /**
     * Parte I: líneas 1 y 3. Solo se escriben si alguna parte las necesita.
     *
     * @return array{0: Dinero, 1: list<string>}|Linea
     */
    private function magi(Declaracion $d): array|Linea
    {
        $lectura = Apoyo::leerUna($d, '1040', '11b', self::FORMULARIO, '1');

        if ($lectura instanceof Linea) {
            $d->poner($lectura);
            $d->poner(Linea::faltaDato(self::FORMULARIO, '3', $lectura->motivo ?? __('Falta la línea 1 del Schedule 1-A.')));

            return $lectura;
        }

        [$valor, $sinValidar] = $lectura;
        $traza = Traza::vacia()->conEntrada(__('Línea 11b del Form 1040'), $valor, OrigenTraza::Linea, '1040:11b');
        $d->poner(Linea::calculada(self::FORMULARIO, '1', $valor, $traza->conResultado(__('Línea 1 del Schedule 1-A'), $valor), $sinValidar));

        $traza3 = Traza::vacia()
            ->conEntrada(__('Línea 1 del Schedule 1-A'), $valor, OrigenTraza::Linea, self::FORMULARIO.':1')
            ->conOperacion(__('Las líneas 2a a 2e (ingreso excluido de Puerto Rico, Forms 2555 y 4563) no son del piloto: cuentan cero.'));
        $d->poner(Linea::calculada(self::FORMULARIO, '3', $valor, $traza3->conResultado(__('Línea 3 del Schedule 1-A (MAGI)'), $valor), $sinValidar));

        return [$valor, $sinValidar];
    }

    /**
     * Partes II y III: lo que entra a la línea 4c o 14c, por persona. Devuelve el total con
     * su traza, o la línea final de la parte (no aplica / falta dato).
     *
     * @param  'propinas'|'horas-extra'  $tipo
     * @return array{0: Dinero, 1: Traza, 2: list<string>}|Linea
     */
    private function propinasOHorasExtra(EntradasDeclaracion $e, string $tipo): array|Linea
    {
        [$casilla, $final, $nombre] = $tipo === 'propinas'
            ? ['propinas-calificadas', '13', __('propinas calificadas')]
            : ['horas-extra-calificadas', '21', __('horas extra calificadas')];

        $documentos = array_values(array_filter(
            $e->documentos(self::CONSTANCIA),
            fn (DocumentoEntrada $doc): bool => $doc->casilla($casilla) !== null,
        ));

        if ($documentos === []) {
            if (isset($e->datos[self::CONSTANCIA.':'.$casilla])) {
                return Linea::faltaDato(self::FORMULARIO, $final, __('Falta saber de quién es cada constancia de :tipo: hace falta el detalle por persona.', ['tipo' => $nombre]));
            }

            return Linea::noAplica(self::FORMULARIO, $final, __('No hay constancia de :tipo.', ['tipo' => $nombre]));
        }

        if ($e->estadoCivil === null) {
            return Linea::faltaDato(self::FORMULARIO, $final, Apoyo::motivoSinEstadoCivil());
        }

        if ($e->estadoCivil === EstadoCivilIrs::Mfs) {
            return Linea::noAplica(self::FORMULARIO, $final, __('Con declaración separada (MFS) no se puede tomar esta deducción: el Schedule 1-A exige declarar en conjunto si se está casado.'));
        }

        $total = Dinero::deCentavos(0);
        $traza = Traza::vacia();
        $sinValidar = [];

        foreach ($documentos as $doc) {
            $dato = $doc->casilla($casilla);

            if ($dato === null || ! $dato->valor instanceof Dinero) {
                return Linea::faltaDato(self::FORMULARIO, $final, __('Falta el monto de :tipo en la constancia :documento.', ['tipo' => $nombre, 'documento' => $doc->referencia()]));
            }

            if ($doc->personaId === null) {
                return Linea::faltaDato(self::FORMULARIO, $final, __('Falta saber de quién es la constancia :documento.', ['documento' => $doc->referencia()]));
            }

            $persona = Apoyo::personaPorId($e, $doc->personaId);
            $referencia = $dato->referencia ?? $doc->referencia().':'.$casilla;

            if (! $this->esDeLaDeclaracion($e, $persona)) {
                $traza = $traza->conOperacion(__('La constancia :documento no es del contribuyente ni del cónyuge de la declaración conjunta: no cuenta.', ['documento' => $doc->referencia()]));

                continue;
            }

            if (! self::ssnValidoParaTrabajar($persona)) {
                $traza = $traza->conOperacion(__('La constancia :documento es de una persona sin SSN válido para trabajar: no cuenta (instrucciones del 1040, p. 104 y 108).', ['documento' => $doc->referencia()]));

                continue;
            }

            $total = $total->mas($dato->valor);
            $traza = $traza->conEntrada(__('Constancia de :tipo', ['tipo' => $nombre]), $dato->valor, $dato->origen, $referencia);

            if ($dato->usaSinValidar()) {
                $sinValidar[] = $referencia;
            }
        }

        return [$total, $traza, array_values(array_unique($sinValidar))];
    }

    /**
     * Partes II (líneas 4c a 13) y III (14c a 21): tope y reducción por MAGI. La división
     * de las líneas 11 y 19 se redondea hacia ABAJO ("decrease the result to the next lower
     * whole number", Schedule 1-A de 2025, p. 1).
     *
     * @param  'propinas'|'horas-extra'  $tipo
     * @param  array{0: Dinero, 1: Traza, 2: list<string>}  $parte
     * @param  array{0: Dinero, 1: list<string>}  $magi
     */
    private function reduccionPropinasOHorasExtra(EntradasDeclaracion $e, Declaracion $d, string $tipo, array $parte, array $magi): void
    {
        $propinas = $tipo === 'propinas';
        [$entrada, $suma, $tope, $magiLinea, $umbral, $exceso, $cuenta, $reduccion, $final] = $propinas
            ? ['4c', '6', '7', '8', '9', '10', '11', '12', '13']
            : ['14c', '15', '15', '16', '17', '18', '19', '20', '21'];
        $conjunta = $e->estadoCivil === EstadoCivilIrs::Mfj;

        try {
            $claveTope = $propinas ? 'propinas_tope' : ($conjunta ? 'horas_extra_tope_mfj' : 'horas_extra_tope_soltero');
            $claveUmbral = ($propinas ? 'propinas_umbral_magi_' : 'horas_extra_umbral_magi_').($conjunta ? 'mfj' : 'soltero');
            $claveReduccion = $propinas ? 'propinas_reduccion_por_1000' : 'horas_extra_reduccion_por_1000';
            $valorTope = Apoyo::parametroEnDolares($e->taxYear, 'schedule_1a', $claveTope);
            $valorUmbral = Apoyo::parametroEnDolares($e->taxYear, 'schedule_1a', $claveUmbral);
            $valorReduccion = Apoyo::parametroEnDolares($e->taxYear, 'schedule_1a', $claveReduccion);
        } catch (RuntimeException|InvalidArgumentException $excepcion) {
            $d->poner(Linea::faltaDato(self::FORMULARIO, $final, $excepcion instanceof RuntimeException ? Apoyo::motivoSinParametro($e->taxYear, $excepcion) : __('Un parámetro del Schedule 1-A no es un monto.')));

            return;
        }

        [$total, $trazaEntrada, $sinValidarEntrada] = $parte;
        [$valorMagi, $sinValidarMagi] = $magi;
        $sinValidar = array_values(array_unique([...$sinValidarEntrada, ...$sinValidarMagi]));

        $d->poner(Linea::calculada(self::FORMULARIO, $entrada, $total, $trazaEntrada->conResultado(Apoyo::nombreLinea(self::FORMULARIO, $entrada), $total), $sinValidarEntrada));

        if ($propinas) {
            $traza6 = Traza::vacia()
                ->conEntrada(Apoyo::nombreLinea(self::FORMULARIO, '4c'), $total, OrigenTraza::Linea, self::FORMULARIO.':4c')
                ->conOperacion(__('La línea 5 (propinas recibidas en un negocio, incluidas en los 1099) no se calcula en el piloto: en 2025 los 1099 no las separan. Si las hay, el preparador las agrega.'));
            $d->poner(Linea::calculada(self::FORMULARIO, $suma, $total, $traza6->conResultado(Apoyo::nombreLinea(self::FORMULARIO, $suma), $total), $sinValidarEntrada));
        }

        $limitado = $total->min($valorTope);
        $trazaTope = Traza::vacia()
            ->conEntrada(Apoyo::nombreLinea(self::FORMULARIO, $propinas ? '6' : '14c'), $total, OrigenTraza::Linea, self::FORMULARIO.':'.($propinas ? '6' : '14c'))
            ->conParametro(__('Tope de la deducción'), $valorTope, 'schedule_1a.'.$claveTope)
            ->conOperacion(__('El menor entre los dos.'), $limitado);

        if (! $propinas) {
            $trazaTope = $trazaTope->conOperacion(__('La línea 14b (horas extra incluidas en los 1099) no se calcula en el piloto: si las hay, el preparador las agrega.'));
        }

        $d->poner(Linea::calculada(self::FORMULARIO, $tope, $limitado, $trazaTope->conResultado(Apoyo::nombreLinea(self::FORMULARIO, $tope), $limitado), $sinValidarEntrada));

        $this->reduccionPorMagi($d, $valorMagi, $sinValidar, $limitado, $tope, [$magiLinea, $umbral, $exceso, $cuenta, $reduccion, $final], $valorUmbral, 'schedule_1a.'.$claveUmbral, $valorReduccion, 'schedule_1a.'.$claveReduccion);
    }

    /**
     * Parte IV: intereses del préstamo de un vehículo nuevo. Las instrucciones (p. 109) exigen,
     * entre otras cosas, que el vehículo haya tenido su ensamblaje final en EE. UU.; ese dato
     * no se lee hoy, así que con un vehículo nuevo la parte queda `falta_dato`. Cuando se lea,
     * las líneas 23 a 30 siguen el patrón de las Partes II y III con la línea 28 redondeada
     * hacia ARRIBA ("increase the result to the next higher whole number").
     */
    private function vehiculo(EntradasDeclaracion $e): Linea
    {
        $documentos = $e->documentos(self::PRESTAMO);

        if ($documentos === []) {
            if (Apoyo::hayDatos($e, self::PRESTAMO)) {
                return Linea::faltaDato(self::FORMULARIO, '30', __('Falta el detalle de cada estado del préstamo del vehículo.'));
            }

            return Linea::noAplica(self::FORMULARIO, '30', __('No hay estado del préstamo de un vehículo.'));
        }

        $traza = Traza::vacia();
        $nuevos = [];

        foreach ($documentos as $doc) {
            $nuevo = $doc->casilla('vehiculo-nuevo')?->valor;

            if ($nuevo === 'no') {
                $traza = $traza->conOperacion(__('El vehículo del préstamo :documento es usado: no cuenta (instrucciones del 1040, p. 109: "a used vehicle does not qualify").', ['documento' => $doc->referencia()]));

                continue;
            }

            if ($nuevo !== 'si') {
                return Linea::faltaDato(self::FORMULARIO, '30', __('Falta confirmar si el vehículo del préstamo :documento es nuevo.', ['documento' => $doc->referencia()]), $traza);
            }

            if ($doc->monto('intereses-pagados') === null) {
                return Linea::faltaDato(self::FORMULARIO, '30', __('Falta el monto de los intereses pagados en el préstamo :documento.', ['documento' => $doc->referencia()]), $traza);
            }

            $nuevos[] = $doc->referencia();
        }

        if ($nuevos === []) {
            return Linea::noAplica(self::FORMULARIO, '30', __('Los vehículos del préstamo son usados: no dan la deducción.'), $traza);
        }

        $traza = $traza->conOperacion(__('Además, el préstamo debe haberse originado después del 31 de diciembre de 2024, el vehículo debe ser de uso personal y el préstamo con primer gravamen sobre él (p. 109): lo confirma el preparador.'));

        return Linea::faltaDato(
            self::FORMULARIO,
            '30',
            __('Falta confirmar que el vehículo (:documentos) tuvo su ensamblaje final en EE. UU.: lo exige el Schedule 1-A (instrucciones del 1040, p. 109) y hoy no se lee.', ['documentos' => implode(', ', $nuevos)]),
            $traza,
        );
    }

    /**
     * Las líneas de la reducción por MAGI de las Partes II y III (8 a 13 y 16 a 21):
     * MAGI (= línea 3), umbral, exceso, exceso ÷ 1.000 (entero), × monto de reducción, y
     * la deducción = línea limitada − reducción (mínimo cero). Si el exceso es cero o menos,
     * la deducción es la línea limitada y no se escriben la cuenta ni la reducción. La Parte IV
     * (líneas 25 a 30) es igual pero redondea la línea 28 hacia ARRIBA y no se calcula todavía
     * (ver `vehiculo()`).
     *
     * @param  list<string>  $sinValidar
     * @param  array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string}  $lineas
     */
    private function reduccionPorMagi(Declaracion $d, Dinero $magi, array $sinValidar, Dinero $limitado, string $lineaLimitada, array $lineas, Dinero $umbral, string $claveUmbral, Dinero $porCadaMil, string $claveReduccion): void
    {
        [$lMagi, $lUmbral, $lExceso, $lCuenta, $lReduccion, $lFinal] = $lineas;

        $d->poner(Linea::calculada(self::FORMULARIO, $lMagi, $magi, Traza::vacia()
            ->conEntrada(__('Línea 3 del Schedule 1-A (MAGI)'), $magi, OrigenTraza::Linea, self::FORMULARIO.':3')
            ->conResultado(Apoyo::nombreLinea(self::FORMULARIO, $lMagi), $magi), $sinValidar));
        $d->poner(Linea::calculada(self::FORMULARIO, $lUmbral, $umbral, Traza::vacia()
            ->conParametro(__('MAGI desde el que se reduce la deducción'), $umbral, $claveUmbral)
            ->conResultado(Apoyo::nombreLinea(self::FORMULARIO, $lUmbral), $umbral)));

        $exceso = $magi->menos($umbral);
        $d->poner(Linea::calculada(self::FORMULARIO, $lExceso, $exceso, Traza::vacia()
            ->conEntrada(Apoyo::nombreLinea(self::FORMULARIO, $lMagi), $magi, OrigenTraza::Linea, self::FORMULARIO.':'.$lMagi)
            ->conEntrada(Apoyo::nombreLinea(self::FORMULARIO, $lUmbral), $umbral, OrigenTraza::Linea, self::FORMULARIO.':'.$lUmbral)
            ->conOperacion(__('Línea :a menos línea :b.', ['a' => $lMagi, 'b' => $lUmbral]))
            ->conResultado(Apoyo::nombreLinea(self::FORMULARIO, $lExceso), $exceso), $sinValidar));

        $trazaFinal = Traza::vacia()->conEntrada(Apoyo::nombreLinea(self::FORMULARIO, $lineaLimitada), $limitado, OrigenTraza::Linea, self::FORMULARIO.':'.$lineaLimitada);

        if ($exceso->centavos() <= 0) {
            $trazaFinal = $trazaFinal->conOperacion(__('La línea :linea es cero o menos: la deducción no se reduce (se saltan las dos líneas siguientes).', ['linea' => $lExceso]));
            $d->poner(Linea::calculada(self::FORMULARIO, $lFinal, $limitado, $trazaFinal->conResultado(Apoyo::nombreLinea(self::FORMULARIO, $lFinal), $limitado), $sinValidar));

            return;
        }

        $miles = intdiv($exceso->centavos(), self::CENTAVOS_POR_1000);

        $d->poner(Linea::calculada(self::FORMULARIO, $lCuenta, (string) $miles, Traza::vacia()
            ->conEntrada(Apoyo::nombreLinea(self::FORMULARIO, $lExceso), $exceso, OrigenTraza::Linea, self::FORMULARIO.':'.$lExceso)
            ->conOperacion(__('Dividido entre 1.000 y redondeado hacia ABAJO al entero (Schedule 1-A, línea :linea).', ['linea' => $lCuenta]))
            ->conResultado(Apoyo::nombreLinea(self::FORMULARIO, $lCuenta), (string) $miles), $sinValidar));

        $reduccion = Dinero::deCentavos($porCadaMil->centavos() * $miles);
        $d->poner(Linea::calculada(self::FORMULARIO, $lReduccion, $reduccion, Traza::vacia()
            ->conEntrada(Apoyo::nombreLinea(self::FORMULARIO, $lCuenta), (string) $miles, OrigenTraza::Linea, self::FORMULARIO.':'.$lCuenta)
            ->conParametro(__('Reducción por cada USD 1.000 de exceso'), $porCadaMil, $claveReduccion)
            ->conOperacion(__('Multiplicación.'))
            ->conResultado(Apoyo::nombreLinea(self::FORMULARIO, $lReduccion), $reduccion), $sinValidar));

        $deduccion = $limitado->menos($reduccion)->max(Dinero::deCentavos(0));
        $trazaFinal = $trazaFinal
            ->conEntrada(Apoyo::nombreLinea(self::FORMULARIO, $lReduccion), $reduccion, OrigenTraza::Linea, self::FORMULARIO.':'.$lReduccion)
            ->conOperacion(__('Línea :a menos línea :b; si es cero o menos, cero.', ['a' => $lineaLimitada, 'b' => $lReduccion]));
        $d->poner(Linea::calculada(self::FORMULARIO, $lFinal, $deduccion, $trazaFinal->conResultado(Apoyo::nombreLinea(self::FORMULARIO, $lFinal), $deduccion), $sinValidar));
    }

    /**
     * Parte V: quién tiene derecho a la deducción para mayores (líneas 36a y 36b): nacido antes
     * del 2 de enero de 1961 (65 años al cierre de 2025) y con SSN válido para trabajar; el
     * cónyuge solo en declaración conjunta.
     *
     * @return array{0: bool, 1: bool, 2: Traza}|Linea [contribuyente, cónyuge, traza]
     */
    private function mayores(EntradasDeclaracion $e): array|Linea
    {
        if ($e->estadoCivil === null) {
            return Linea::faltaDato(self::FORMULARIO, '37', Apoyo::motivoSinEstadoCivil());
        }

        if ($e->estadoCivil === EstadoCivilIrs::Mfs) {
            return Linea::noAplica(self::FORMULARIO, '37', __('Con declaración separada (MFS) no se puede tomar esta deducción: el Schedule 1-A exige declarar en conjunto si se está casado.'));
        }

        $contribuyente = Apoyo::persona($e, RolPersona::Contribuyente);

        if ($contribuyente === null) {
            return Linea::faltaDato(self::FORMULARIO, '37', __('Faltan los datos del contribuyente.'));
        }

        $conyuge = $e->estadoCivil === EstadoCivilIrs::Mfj ? Apoyo::persona($e, RolPersona::Conyuge) : null;

        if ($e->estadoCivil === EstadoCivilIrs::Mfj && $conyuge === null) {
            return Linea::faltaDato(self::FORMULARIO, '37', __('Faltan los datos del cónyuge de la declaración conjunta.'));
        }

        $traza = Traza::vacia();
        $derechos = [];
        $hayMayores = false;

        foreach ([$contribuyente, $conyuge] as $persona) {
            if ($persona === null) {
                $derechos[] = false;

                continue;
            }

            $mayor = Mayores::esMayor($persona);

            if ($mayor === null) {
                return Linea::faltaDato(self::FORMULARIO, '37', Mayores::motivo($persona));
            }

            $traza = $traza->conEntrada(Mayores::etiqueta($persona), $mayor, OrigenTraza::Respuesta, Mayores::referencia($persona));
            $hayMayores = $hayMayores || $mayor;
            $derechos[] = $mayor && self::ssnValidoParaTrabajar($persona);

            if ($mayor && ! self::ssnValidoParaTrabajar($persona)) {
                $traza = $traza->conOperacion(__('Tiene 65 años o más pero no tiene SSN válido para trabajar: no cuenta (instrucciones del 1040, p. 110).'));
            }
        }

        if (! $hayMayores) {
            return Linea::noAplica(self::FORMULARIO, '37', __('Nadie de la declaración tiene 65 años o más.'), $traza);
        }

        return [$derechos[0], $derechos[1], $traza];
    }

    /**
     * Parte V, líneas 31 a 37.
     *
     * @param  array{0: bool, 1: bool, 2: Traza}  $parte
     * @param  array{0: Dinero, 1: list<string>}  $magi
     */
    private function reduccionMayores(EntradasDeclaracion $e, Declaracion $d, array $parte, array $magi): void
    {
        [$contribuyente, $conyuge, $trazaPersonas] = $parte;
        $conjunta = $e->estadoCivil === EstadoCivilIrs::Mfj;

        try {
            $claveUmbral = 'mayores_umbral_magi_'.($conjunta ? 'mfj' : 'soltero');
            $tope = Apoyo::parametroEnDolares($e->taxYear, 'schedule_1a', 'mayores_tope');
            $umbral = Apoyo::parametroEnDolares($e->taxYear, 'schedule_1a', $claveUmbral);
            $tasa = (string) ParametrosFiscales::valorRequerido($e->taxYear, 'schedule_1a', 'mayores_tasa_reduccion');
        } catch (RuntimeException $excepcion) {
            $d->poner(Linea::faltaDato(self::FORMULARIO, '37', Apoyo::motivoSinParametro($e->taxYear, $excepcion)));

            return;
        }

        [$valorMagi, $sinValidar] = $magi;

        $d->poner(Linea::calculada(self::FORMULARIO, '31', $valorMagi, Traza::vacia()
            ->conEntrada(__('Línea 3 del Schedule 1-A (MAGI)'), $valorMagi, OrigenTraza::Linea, self::FORMULARIO.':3')
            ->conResultado(Apoyo::nombreLinea(self::FORMULARIO, '31'), $valorMagi), $sinValidar));
        $d->poner(Linea::calculada(self::FORMULARIO, '32', $umbral, Traza::vacia()
            ->conParametro(__('MAGI desde el que se reduce la deducción'), $umbral, 'schedule_1a.'.$claveUmbral)
            ->conResultado(Apoyo::nombreLinea(self::FORMULARIO, '32'), $umbral)));

        $exceso = $valorMagi->menos($umbral);
        $d->poner(Linea::calculada(self::FORMULARIO, '33', $exceso, Traza::vacia()
            ->conOperacion(__('Línea :a menos línea :b.', ['a' => '31', 'b' => '32']))
            ->conResultado(Apoyo::nombreLinea(self::FORMULARIO, '33'), $exceso), $sinValidar));

        $traza35 = Traza::vacia()->conParametro(__('Deducción para mayores por persona'), $tope, 'schedule_1a.mayores_tope');

        if ($exceso->centavos() <= 0) {
            $por = $tope;
            $traza35 = $traza35->conOperacion(__('La línea 33 es cero o menos: la línea 35 es el monto completo.'));
        } else {
            $reduccion = $exceso->por($tasa);
            $d->poner(Linea::calculada(self::FORMULARIO, '34', $reduccion, Traza::vacia()
                ->conEntrada(Apoyo::nombreLinea(self::FORMULARIO, '33'), $exceso, OrigenTraza::Linea, self::FORMULARIO.':33')
                ->conParametro(__('Tasa de reducción'), $tasa, 'schedule_1a.mayores_tasa_reduccion')
                ->conOperacion(__('Multiplicación.'))
                ->conResultado(Apoyo::nombreLinea(self::FORMULARIO, '34'), $reduccion), $sinValidar));
            $por = $tope->menos($reduccion)->max(Dinero::deCentavos(0));
            $traza35 = $traza35
                ->conEntrada(Apoyo::nombreLinea(self::FORMULARIO, '34'), $reduccion, OrigenTraza::Linea, self::FORMULARIO.':34')
                ->conOperacion(__('El monto por persona menos la línea 34; si es cero o menos, cero.'));
        }

        $d->poner(Linea::calculada(self::FORMULARIO, '35', $por, $traza35->conResultado(Apoyo::nombreLinea(self::FORMULARIO, '35'), $por), $sinValidar));

        $total = Dinero::deCentavos(0);
        $traza37 = $trazaPersonas;

        foreach (['36a' => $contribuyente, '36b' => $conyuge] as $linea => $derecho) {
            if (! $derecho) {
                $d->poner(Linea::noAplica(self::FORMULARIO, $linea, $linea === '36a'
                    ? __('El contribuyente no tiene 65 años o más con SSN válido.')
                    : __('El cónyuge no tiene 65 años o más con SSN válido, o no es una declaración conjunta.')));

                continue;
            }

            $d->poner(Linea::calculada(self::FORMULARIO, $linea, $por, Traza::vacia()
                ->conEntrada(Apoyo::nombreLinea(self::FORMULARIO, '35'), $por, OrigenTraza::Linea, self::FORMULARIO.':35')
                ->conResultado(Apoyo::nombreLinea(self::FORMULARIO, $linea), $por), $sinValidar));
            $total = $total->mas($por);
            $traza37 = $traza37->conEntrada(Apoyo::nombreLinea(self::FORMULARIO, $linea), $por, OrigenTraza::Linea, self::FORMULARIO.':'.$linea);
        }

        $d->poner(Linea::calculada(self::FORMULARIO, '37', $total, $traza37->conOperacion(__('Suma de las líneas 36a y 36b.'))->conResultado(Apoyo::nombreLinea(self::FORMULARIO, '37'), $total), $sinValidar));
    }

    /** Parte VI, línea 38: "Add lines 13, 21, 30, and 37" (Schedule 1-A de 2025, p. 2). */
    private function linea38(Declaracion $d): void
    {
        $resultado = Apoyo::leerLineas($d, self::FORMULARIO, '38', [
            [self::FORMULARIO, '13', true],
            [self::FORMULARIO, '21', true],
            [self::FORMULARIO, '30', true],
            [self::FORMULARIO, '37', true],
        ]);

        if ($resultado instanceof Linea) {
            $d->poner($resultado);

            return;
        }

        [$total, $traza, $sinValidar, $aplicadas] = $resultado;

        if ($aplicadas === 0) {
            $d->poner(Linea::noAplica(self::FORMULARIO, '38', __('No hay propinas, horas extra, intereses de vehículo ni mayores de 65 años que den deducción.')));

            return;
        }

        $d->poner(Linea::calculada(self::FORMULARIO, '38', $total, $traza->conOperacion(__('Suma de las líneas 13, 21, 30 y 37.'))->conResultado(Apoyo::nombreLinea(self::FORMULARIO, '38'), $total), $sinValidar));
    }

    /** El contribuyente, o el cónyuge si la declaración es conjunta. */
    private function esDeLaDeclaracion(EntradasDeclaracion $e, ?PersonaEntrada $persona): bool
    {
        return $persona !== null && (
            $persona->rol === RolPersona::Contribuyente
            || ($persona->rol === RolPersona::Conyuge && $e->estadoCivil === EstadoCivilIrs::Mfj)
        );
    }

    /**
     * "A valid SSN ... is one that is valid for employment and that is issued by the SSA before
     * the due date of your 2025 return" (instrucciones del 1040 de 2025, p. 104).
     */
    public static function ssnValidoParaTrabajar(?PersonaEntrada $persona): bool
    {
        return $persona !== null && $persona->ssnValido && ! $persona->noValidoParaTrabajar;
    }
}
