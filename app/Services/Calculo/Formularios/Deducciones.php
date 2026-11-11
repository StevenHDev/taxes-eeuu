<?php

namespace App\Services\Calculo\Formularios;

use App\Enums\Expediente\EstadoCivilIrs;
use App\Services\Calculo\Formularios\Deducciones\Mayores;
use App\Services\Calculo\Nucleo\Apoyo;
use App\Services\Calculo\Nucleo\Calculadora;
use App\Services\Calculo\Nucleo\Declaracion;
use App\Services\Calculo\Nucleo\Dinero;
use App\Services\Calculo\Nucleo\EntradasDeclaracion;
use App\Services\Calculo\Nucleo\Linea;
use App\Services\Calculo\Nucleo\OrigenTraza;
use App\Services\Calculo\Nucleo\PersonaEntrada;
use App\Services\Calculo\Nucleo\RolPersona;
use App\Services\Calculo\Nucleo\Traza;
use InvalidArgumentException;
use RuntimeException;

/**
 * Deducciones del Form 1040 de 2025 (p. 2): líneas 12d, 12e, 13a, 13b, 14 y 15. Fuentes: el
 * formulario (`f1040`) y sus instrucciones (`i1040gi`, p. 33 a 35: líneas 12d, 12e, 13a y 13b,
 * "Standard Deduction Worksheet for Dependents" y "Standard Deduction Chart").
 *
 * Orden (para el orquestador de 6.4i): esta clase corre el bloque completo y llama a las otras
 * dos en el orden que exigen sus dependencias, así que el orquestador solo la llama a ella,
 * DESPUÉS de `Ingresos1040` (11a y 11b; y de `Schedule1` y `ScheduleC`) y ANTES de
 * `ImpuestoYSchedule2` (que lee la 15):
 *   1. `Schedule1A` (lee la 11b) → sch-1a:38, y 13b (= sch-1a:38).
 *   2. 12d y 12e.
 *   3. `Form8995` (lee 11a − 12e − 13b) → f8995:15, y 13a (= f8995:15).
 *   4. 14 y 15.
 * Correr `Schedule1A` o `Form8995` otra vez después no cambia nada: escriben las mismas líneas.
 *
 * - 12d: en el 1040 son casillas; aquí la línea lleva el monto adicional (casillas marcadas ×
 *   `deduccion_estandar.adicional_*`) y la traza dice qué casilla cuenta. La ceguera sale de la
 *   pregunta A9; sin respuesta, solo cuenta la edad y la traza lo dice.
 * - 12e: deducción estándar del estado civil + 12d, o la hoja del dependiente si alguien puede
 *   declarar al contribuyente (o al cónyuge de una conjunta). Las deducciones detalladas
 *   (Schedule A) no se calculan en F6: si hay documentos de Schedule A, la 12e no se soporta.
 */
final class Deducciones implements Calculadora
{
    private const FORMULARIO = '1040';

    /** Documentos que activan el Schedule A (preguntas E1 a E4 y F7): el preparador compara. */
    private const DOCUMENTOS_SCHEDULE_A = ['1098', 'recibo-predial', 'recibos-medicos', 'recibos-donaciones', 'registros-desastre'];

    /** Pregunta "¿Eras legalmente ciego?" (usted / cónyuge / no). */
    private const PREGUNTA_CEGUERA = 'A9';

    public function __construct(
        private readonly Schedule1A $schedule1A = new Schedule1A,
        private readonly Form8995 $form8995 = new Form8995,
    ) {}

    public function formulario(): string
    {
        return self::FORMULARIO;
    }

    public function calcular(EntradasDeclaracion $e, Declaracion $d): void
    {
        $this->schedule1A->calcular($e, $d);
        Apoyo::copiar($d, self::FORMULARIO, '13b', 'sch-1a', '38');

        $adicional = $this->linea12d($e, $d);
        $this->linea12e($e, $d, $adicional);

        $this->form8995->calcular($e, $d);
        Apoyo::copiar($d, self::FORMULARIO, '13a', 'f8995', '15');

        $this->linea14($d);
        $this->linea15($d);
    }

    /**
     * Línea 12d: casillas de 65 años o más y de ceguera (i1040gi, p. 33, "Line 12d"). El cónyuge
     * solo cuenta en la conjunta; con HOH no se marcan las del cónyuge; con MFS solo si el cónyuge
     * no tuvo ingresos, no presenta y nadie lo puede declarar (nota de la tabla, p. 35), y eso
     * no se pregunta. Monto por casilla (hoja del dependiente, línea 4b, p. 35): USD 1.600
     * casado o QSS, USD 2.000 soltero o HOH.
     *
     * @return array{0: Dinero, 1: int, 2: Traza}|null monto, casillas marcadas y traza; null si la 12d no salió calculada
     */
    private function linea12d(EntradasDeclaracion $e, Declaracion $d): ?array
    {
        if ($e->estadoCivil === null) {
            $d->poner(Linea::faltaDato(self::FORMULARIO, '12d', Apoyo::motivoSinEstadoCivil()));

            return null;
        }

        $contribuyente = Apoyo::persona($e, RolPersona::Contribuyente);

        if ($contribuyente === null) {
            $d->poner(Linea::faltaDato(self::FORMULARIO, '12d', __('Faltan los datos del contribuyente.')));

            return null;
        }

        $ceguera = $this->ceguera($e);

        if ($ceguera === false) {
            $d->poner(Linea::faltaDato(self::FORMULARIO, '12d', __('No se entiende la respuesta de la pregunta A9 (ceguera): debe ser "no", "usted", "conyuge" o "usted,conyuge".')));

            return null;
        }

        $traza = Traza::vacia();

        if ($ceguera === null) {
            $traza = $traza->conOperacion(__('La pregunta A9 (ceguera) no está respondida: solo se cuentan las casillas de edad. Si alguien es ciego, el preparador marca la casilla.'));
        }

        $casillas = 0;
        $conyuge = Apoyo::persona($e, RolPersona::Conyuge);
        $personas = [[$contribuyente, 'usted']];

        if ($conyuge !== null && in_array($e->estadoCivil, [EstadoCivilIrs::Mfj, EstadoCivilIrs::Mfs], true)) {
            $personas[] = [$conyuge, 'conyuge'];
        }

        foreach ($personas as [$persona, $clave]) {
            $mayor = Mayores::esMayor($persona);
            $conyugeMfs = $persona->rol === RolPersona::Conyuge && $e->estadoCivil === EstadoCivilIrs::Mfs;

            // Con MFS las casillas del cónyuge son la excepción (nota de la tabla, p. 35): sin su edad
            // no se frena la declaración; se dice en la traza.
            if ($mayor === null && $conyugeMfs) {
                $traza = $traza->conOperacion(__('Con MFS no se sabe la edad del cónyuge: su casilla de edad no se cuenta. Solo se marcaría si el cónyuge no tuvo ingresos, no presenta y nadie lo declara; lo revisa el preparador.'));
                $mayor = false;
            }

            if ($mayor === null) {
                $d->poner(Linea::faltaDato(self::FORMULARIO, '12d', Mayores::motivo($persona), $traza));

                return null;
            }

            $ciego = $ceguera !== null && in_array($clave, $ceguera, true);
            $marcadas = ($mayor ? 1 : 0) + ($ciego ? 1 : 0);

            if ($marcadas > 0 && $conyugeMfs) {
                $d->poner(Linea::faltaDato(
                    self::FORMULARIO,
                    '12d',
                    __('Con declaración separada (MFS), las casillas del cónyuge solo se marcan si el cónyuge no tuvo ingresos, no presenta declaración y nadie lo puede declarar como dependiente (instrucciones del 1040, p. 35). Lo revisa el preparador.'),
                    $traza,
                ));

                return null;
            }

            $traza = $traza
                ->conEntrada(Mayores::etiqueta($persona), $mayor, OrigenTraza::Respuesta, Mayores::referencia($persona))
                ->conEntrada($clave === 'usted' ? __('El contribuyente es ciego') : __('El cónyuge es ciego'), $ciego, OrigenTraza::Respuesta, self::PREGUNTA_CEGUERA);
            $casillas += $marcadas;
        }

        if ($casillas === 0) {
            $d->poner(Linea::noAplica(self::FORMULARIO, '12d', __('Nadie de la declaración tiene 65 años o más ni es ciego.'), $traza));

            return [Dinero::deCentavos(0), 0, $traza];
        }

        try {
            $clave = in_array($e->estadoCivil, [EstadoCivilIrs::Single, EstadoCivilIrs::Hoh], true) ? 'adicional_65_ciego_soltero_hoh' : 'adicional_65_ciego_casado';
            $porCasilla = Apoyo::parametroEnDolares($e->taxYear, 'deduccion_estandar', $clave);
        } catch (RuntimeException|InvalidArgumentException $excepcion) {
            $d->poner(Linea::faltaDato(self::FORMULARIO, '12d', $excepcion instanceof RuntimeException ? Apoyo::motivoSinParametro($e->taxYear, $excepcion) : __('Un parámetro de la deducción estándar no es un monto.')));

            return null;
        }

        $monto = Dinero::deCentavos($porCasilla->centavos() * $casillas);
        $traza = $traza
            ->conOperacion(__('Casillas marcadas: :n.', ['n' => (string) $casillas]), (string) $casillas)
            ->conParametro(__('Deducción adicional por casilla'), $porCasilla, 'deduccion_estandar.'.$clave)
            ->conOperacion(__('Casillas por monto por casilla.'));

        // La 12d del 1040 de 2025 son solo casillas (p. 2): se guarda cuántas se marcaron. El monto adicional
        // queda en la traza y entra en la 12e (Standard Deduction Chart, i1040gi p. 35).
        $traza = $traza->conResultado(__('Deducción adicional por las casillas (va en la línea 12e)'), $monto);
        $d->poner(Linea::calculada(self::FORMULARIO, '12d', (string) $casillas, $traza->conResultado(__('Casillas marcadas en la línea 12d del Form 1040'), (string) $casillas)));

        return [$monto, $casillas, $traza];
    }

    /**
     * Línea 12e: deducción estándar (recuadro "Standard deduction for" del 1040 de 2025, p. 2,
     * y "Standard Deduction Chart", i1040gi p. 35) o la hoja del dependiente (p. 35).
     *
     * @param  array{0: Dinero, 1: int, 2: Traza}|null  $adicional
     */
    private function linea12e(EntradasDeclaracion $e, Declaracion $d, ?array $adicional): void
    {
        if ($e->estadoCivil === null) {
            $d->poner(Linea::faltaDato(self::FORMULARIO, '12e', Apoyo::motivoSinEstadoCivil()));

            return;
        }

        if ($adicional === null) {
            $d->poner(Linea::faltaDato(self::FORMULARIO, '12e', __('No se puede calcular: depende de la línea 12d del Form 1040, que tiene datos pendientes.')));

            return;
        }

        $schA = Apoyo::documentosPresentes($e, self::DOCUMENTOS_SCHEDULE_A);

        if ($schA !== []) {
            $d->poner(Linea::noSoportada(self::FORMULARIO, '12e', __('Hay documentos de deducciones detalladas (:documentos): el preparador compara la deducción estándar con el Schedule A, que llega en F7.', ['documentos' => implode(', ', $schA)])));

            return;
        }

        $claveEstandar = match ($e->estadoCivil) {
            EstadoCivilIrs::Single => 'monto_soltero',
            EstadoCivilIrs::Mfj, EstadoCivilIrs::Qss => 'monto_mfj',
            EstadoCivilIrs::Mfs => 'monto_mfs',
            EstadoCivilIrs::Hoh => 'monto_hoh',
        };

        try {
            $estandar = Apoyo::parametroEnDolares($e->taxYear, 'deduccion_estandar', $claveEstandar);
        } catch (RuntimeException|InvalidArgumentException $excepcion) {
            $d->poner(Linea::faltaDato(self::FORMULARIO, '12e', $excepcion instanceof RuntimeException ? Apoyo::motivoSinParametro($e->taxYear, $excepcion) : __('Un parámetro de la deducción estándar no es un monto.')));

            return;
        }

        [$montoAdicional, $casillas] = $adicional;
        $traza = Traza::vacia()
            ->conEntrada($e->estadoCivil->label(), $e->estadoCivil->value, OrigenTraza::Decision, 'estado_civil_irs')
            ->conParametro(__('Deducción estándar del estado civil'), $estandar, 'deduccion_estandar.'.$claveEstandar);

        if ($e->estadoCivil === EstadoCivilIrs::Mfs) {
            $traza = $traza->conOperacion(__('Con MFS, si el cónyuge detalla deducciones la estándar es cero (línea 12b del 1040, instrucciones p. 34): lo confirma el preparador.'));
        }

        $dependiente = $this->puedeSerDependiente($e);

        if ($dependiente === null) {
            $traza = $traza->conOperacion(__('No se sabe si otra persona puede declarar al contribuyente como dependiente: se usa la deducción estándar normal. Si alguien puede, el preparador usa la hoja del dependiente.'));
        }

        if ($dependiente === true) {
            $this->hojaDependiente($e, $d, $estandar, $montoAdicional, $casillas, $traza);

            return;
        }

        $valor = $estandar->mas($montoAdicional);
        $traza = $traza
            ->conEntrada(__('Deducción adicional (línea 12d)'), $montoAdicional, OrigenTraza::Linea, '1040:12d')
            ->conOperacion(__('Deducción estándar más la adicional de la línea 12d (Standard Deduction Chart, instrucciones del 1040, p. 35).'));

        $d->poner(Linea::calculada(self::FORMULARIO, '12e', $valor, $traza->conResultado(__('Línea 12e del Form 1040'), $valor)));
    }

    /**
     * "Standard Deduction Worksheet for Dependents—Line 12e" (i1040gi, p. 35):
     * 2 = ingreso del trabajo + USD 450 o USD 1.350, lo mayor (las dos ramas de la línea 2 son
     * exactamente eso); 3 = estándar del estado civil; 4a = la menor; 4b = casillas × adicional;
     * 4c = 4a + 4b. Ingreso del trabajo (nota de la hoja) = 1040 línea 1z + Schedule 1 líneas
     * 3, 6, 8r, 8t y 8u − Schedule 1 línea 15; del piloto entran 1z, 3 y 15.
     */
    private function hojaDependiente(EntradasDeclaracion $e, Declaracion $d, Dinero $estandar, Dinero $montoAdicional, int $casillas, Traza $traza): void
    {
        $lectura = Apoyo::leerVarias($d, [['1040', '1z'], ['sch-1', '3'], ['sch-1', '15']], self::FORMULARIO, '12e');

        if ($lectura instanceof Linea) {
            $d->poner($lectura);

            return;
        }

        try {
            $minimo = Apoyo::parametroEnDolares($e->taxYear, 'deduccion_estandar', 'dependiente_minimo');
            $adicionalTrabajo = Apoyo::parametroEnDolares($e->taxYear, 'deduccion_estandar', 'dependiente_adicional_ingreso_trabajo');
        } catch (RuntimeException $excepcion) {
            $d->poner(Linea::faltaDato(self::FORMULARIO, '12e', Apoyo::motivoSinParametro($e->taxYear, $excepcion)));

            return;
        }

        [[$salarios, $negocio, $mitadSe], $trazaIngreso, $sinValidar] = $lectura;
        $ingresoTrabajo = $salarios->mas($negocio)->menos($mitadSe);
        $linea2 = $ingresoTrabajo->mas($adicionalTrabajo)->max($minimo);
        $linea4a = $linea2->min($estandar);
        $valor = $linea4a->mas($montoAdicional);

        $traza = $traza
            ->conOperacion(__('Alguien puede declarar al contribuyente (o al cónyuge de la conjunta) como dependiente: se usa la hoja del dependiente (instrucciones del 1040, p. 35).'));

        foreach ($trazaIngreso->pasos as $paso) {
            $traza = $traza->conEntrada($paso->texto, $paso->valor, $paso->origen ?? OrigenTraza::Linea, $paso->referencia);
        }

        $traza = $traza
            ->conOperacion(__('Ingreso del trabajo: línea 1z más la línea 3 del Schedule 1 menos su línea 15 (las líneas 6, 8r, 8t y 8u no son del piloto).'), $ingresoTrabajo)
            ->conParametro(__('Suma al ingreso del trabajo'), $adicionalTrabajo, 'deduccion_estandar.dependiente_adicional_ingreso_trabajo')
            ->conParametro(__('Mínimo de la hoja del dependiente'), $minimo, 'deduccion_estandar.dependiente_minimo')
            ->conOperacion(__('Línea 2: el ingreso del trabajo más la suma, o el mínimo, lo que sea mayor.'), $linea2)
            ->conOperacion(__('Línea 4a: la menor entre la línea 2 y la deducción estándar del estado civil.'), $linea4a)
            ->conEntrada(__('Línea 4b: deducción adicional (línea 12d), :n casillas', ['n' => (string) $casillas]), $montoAdicional, OrigenTraza::Linea, '1040:12d')
            ->conOperacion(__('Línea 4c: 4a más 4b.'));

        $d->poner(Linea::calculada(self::FORMULARIO, '12e', $valor, $traza->conResultado(__('Línea 12e del Form 1040'), $valor), $sinValidar));
    }

    /** Línea 14: "Add lines 12e, 13a, and 13b". */
    private function linea14(Declaracion $d): void
    {
        $resultado = Apoyo::leerLineas($d, self::FORMULARIO, '14', [
            [self::FORMULARIO, '12e', true],
            [self::FORMULARIO, '13a', true],
            [self::FORMULARIO, '13b', true],
        ]);

        if ($resultado instanceof Linea) {
            $d->poner($resultado);

            return;
        }

        [$total, $traza, $sinValidar] = $resultado;

        $d->poner(Linea::calculada(self::FORMULARIO, '14', $total, $traza->conOperacion(__('Suma de las líneas 12e, 13a y 13b.'))->conResultado(__('Línea 14 del Form 1040'), $total), $sinValidar));
    }

    /** Línea 15: "Subtract line 14 from line 11b. If zero or less, enter -0-. This is your taxable income". */
    private function linea15(Declaracion $d): void
    {
        $lectura = Apoyo::leerVarias($d, [[self::FORMULARIO, '11b'], [self::FORMULARIO, '14']], self::FORMULARIO, '15');

        if ($lectura instanceof Linea) {
            $d->poner($lectura);

            return;
        }

        [[$ajustado, $deducciones], $traza, $sinValidar] = $lectura;
        $gravable = $ajustado->menos($deducciones)->max(Dinero::deCentavos(0));

        $d->poner(Linea::calculada(self::FORMULARIO, '15', $gravable, $traza
            ->conOperacion(__('Línea 11b menos línea 14; si es cero o menos, cero.'))
            ->conResultado(__('Línea 15 del Form 1040 (ingreso gravable)'), $gravable), $sinValidar));
    }

    /**
     * Respuesta de A9 como lista de quién es ciego (["usted"], ["conyuge"], los dos o []).
     * null si no está respondida; false si no se entiende. La respuesta se guarda como
     * ["usted"] | ["conyuge"] | ["usted","conyuge"] | "no" (`ValorRespuesta`); como
     * `RespuestaEntrada` no lleva listas, se espera "no", "usted", "conyuge" o "usted,conyuge".
     *
     * @return list<string>|false|null
     */
    private function ceguera(EntradasDeclaracion $e): array|false|null
    {
        $respuesta = $e->respuestas[self::PREGUNTA_CEGUERA] ?? null;

        if ($respuesta === null || $respuesta->valor === null) {
            return null;
        }

        if ($respuesta->valor === false || $respuesta->valor === 'no') {
            return [];
        }

        if (! is_string($respuesta->valor)) {
            return false;
        }

        $quienes = array_map('trim', explode(',', $respuesta->valor));

        foreach ($quienes as $quien) {
            if (! in_array($quien, ['usted', 'conyuge'], true)) {
                return false;
            }
        }

        return $quienes;
    }

    /**
     * true si alguien puede declarar al contribuyente (o al cónyuge, en conjunta) como dependiente
     * (casilla 12a del 1040); null si no se sabe; false si no.
     */
    private function puedeSerDependiente(EntradasDeclaracion $e): ?bool
    {
        $personas = [Apoyo::persona($e, RolPersona::Contribuyente)];

        if ($e->estadoCivil === EstadoCivilIrs::Mfj) {
            $personas[] = Apoyo::persona($e, RolPersona::Conyuge);
        }

        $valores = array_map(fn (?PersonaEntrada $p): ?bool => $p?->otroPuedeReclamar, $personas);

        if (in_array(true, $valores, true)) {
            return true;
        }

        return in_array(null, $valores, true) ? null : false;
    }
}
