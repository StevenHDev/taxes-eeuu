<?php

namespace App\Services\Calculo\Formularios;

use App\Enums\Expediente\ClasificacionPersona;
use App\Enums\Expediente\EstadoCivilIrs;
use App\Services\Calculo\Formularios\Eitc\CreditoEic;
use App\Services\Calculo\Formularios\Eitc\EvaluacionHijos;
use App\Services\Calculo\Formularios\Eitc\ResultadoEic;
use App\Services\Calculo\Formularios\Eitc\SinCalculo;
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
use App\Support\TablaFiscal;
use RuntimeException;

/**
 * Crédito por ingreso del trabajo (EIC): línea 27a del Form 1040 y el crédito del Schedule EIC.
 *
 * Sigue el orden de las instrucciones del 1040 de 2025 (i1040gi, pp. 40 a 48): Paso 1 (todos), Paso 2 (ingreso de
 * inversión), Paso 3 (hijo calificado), Paso 4 (sin hijo calificado), Paso 5 (ingreso del trabajo) y la Hoja de
 * trabajo A (sin autoempleo, p. 46) o B (con Schedule SE o Schedule C, pp. 47 y 48), con la EIC Table de la Pub. 1040
 * (CreditoEic) como método. Sin crédito, la línea queda `no_aplica` (la 27a se deja en blanco y se marca la 27c);
 * si la hoja da 0 por la tabla, también `no_aplica`; un crédito calculado puede ser 0 solo si el IRS así lo da.
 *
 * Lo que no calcula, no lo inventa:
 *  - Documentos fuera del piloto que meten ingreso de inversión o Schedule E (1099-INT, 1099-DIV, 1099-B, 1099-DA,
 *    K-1): `no_soportada` (Paso 2).
 *  - Sin dato opcional que la regla necesita: `falta_dato` diciendo cuál.
 *  - No verifica (no hay dato de entrada): vivienda principal en EE. UU. más de medio año, Form 8862 por un crédito
 *    negado antes, clero o empleado de iglesia, pagos de exención de Medicaid (Schedule 1, línea 8s), pago de
 *    combate no gravable (1040, línea 1i), empleado por ley (Schedule C) ni el Form 2555. El preparador los revisa.
 */
final class Eitc implements Calculadora
{
    /** Documentos fuera del piloto que ponen ingreso en las líneas 2a, 2b, 3b o 7a del 1040 o en el Schedule E (Paso 2, p. 40). */
    private const DOCUMENTOS_FUERA_DEL_PILOTO = ['1099-int', '1099-div', '1099-b', '1099-da', 'k-1'];

    /** Parentescos del hijo calificado: hijo, hijastro, de crianza, hermano (también medio o hermanastro) y descendientes de ellos (p. 41, Paso 3). Valores de la pregunta C3. */
    private const PARENTESCOS_QUE_CALIFICAN = ['hijo', 'hijastro', 'hijo_crianza', 'hermano', 'nieto'];

    /** "Under age 19 at the end of 2025" (instrucciones del 1040, p. 41, Paso 3). */
    private const EDAD_MENOR_DE = 19;

    /** "Under age 24 at the end of 2025, a full-time student" (p. 41, Paso 3). */
    private const EDAD_ESTUDIANTE_MENOR_DE = 24;

    /** "Lived with you in the United States for more than half of 2025": 7 meses o más (p. 41; la pregunta C4 usa "desde 7"). */
    private const MESES_MAS_DE_LA_MITAD = 7;

    /** Sin hijo calificado: "at least age 25 but under age 65" al cierre de 2025 (p. 41, Paso 4, pregunta 2). */
    private const EDAD_MINIMA_SIN_HIJOS = 25;

    private const EDAD_MAXIMA_SIN_HIJOS = 64;

    public function formulario(): string
    {
        return 'sch-eic';
    }

    public function calcular(EntradasDeclaracion $e, Declaracion $d): void
    {
        try {
            [$resultado, $hijos] = $this->evaluar($e, $d);
        } catch (SinCalculo $corte) {
            $resultado = $corte->resultado;
            $hijos = null;
        }

        $this->escribirLinea($d, '1040', '27a', $resultado);
        $this->escribirLinea($d, 'sch-eic', 'credito', $resultado);
        $this->escribirHijos($d, $resultado, $hijos);
    }

    /** @return array{ResultadoEic, EvaluacionHijos} */
    private function evaluar(EntradasDeclaracion $e, Declaracion $d): array
    {
        if ($e->estadoCivil === null) {
            throw new SinCalculo(ResultadoEic::faltaDato(__('Falta decidir el estado civil en Revisión.')));
        }

        $estado = $e->estadoCivil;
        $conjunta = $estado === EstadoCivilIrs::Mfj;
        $tabla = $this->parametroTabla($e->taxYear);

        $contribuyente = $this->persona($e, RolPersona::Contribuyente);
        $conyuge = $this->persona($e, RolPersona::Conyuge);

        if ($contribuyente === null) {
            throw new SinCalculo(ResultadoEic::faltaDato(__('Falta el contribuyente entre las personas del expediente.')));
        }

        if ($conjunta && $conyuge === null) {
            throw new SinCalculo(ResultadoEic::faltaDato(__('Falta el cónyuge entre las personas del expediente.')));
        }

        // Paso 2 (p. 40): el ingreso de inversión y el Schedule E tienen reglas que el piloto no calcula.
        $fuera = $this->documentoFueraDelPiloto($e);

        if ($fuera !== null) {
            $tope = $this->topeIngresoInversion($e->taxYear);

            throw new SinCalculo(ResultadoEic::noSoportada(__('No se puede calcular el crédito por ingreso del trabajo: el expediente trae un documento (:doc) que cuenta como ingreso de inversión o Schedule E y el piloto no lo calcula; el tope del ingreso de inversión es USD :tope (instrucciones del 1040, p. 40, Paso 2).', ['doc' => strtoupper($fuera), 'tope' => $tope->texto()])));
        }

        $traza = Traza::vacia()
            ->conParametro(__('Cifras del EIC por número de hijos (Rev. Proc. 2024-40, sec. 2.06)'), null, 'credito_eic.por_hijos');

        // Paso 1, pregunta 2 (p. 40): SSN válido para trabajar de quien declara y de su cónyuge si es conjunta.
        foreach (array_filter([$contribuyente, $conjunta ? $conyuge : null]) as $declarante) {
            if (! $this->ssnValidoParaEic($declarante)) {
                return [ResultadoEic::noAplica(__('Sin crédito por ingreso del trabajo: quien declara (o su cónyuge en conjunta) no tiene un SSN válido para trabajar (instrucciones del 1040, p. 40, Paso 1, pregunta 2, y p. 44).'), $traza), new EvaluacionHijos];
            }
        }

        $hijos = $this->evaluarHijos($e, $contribuyente, $conjunta ? $conyuge : null);

        if ($hijos->faltantes !== []) {
            throw new SinCalculo(ResultadoEic::faltaDato(__('Para el crédito por ingreso del trabajo falta: :lista.', ['lista' => implode('; ', $hijos->faltantes)])));
        }

        $noCumple = $this->requisitosPersonales($e, $estado, $contribuyente, $conjunta ? $conyuge : null, $hijos);

        if ($noCumple !== null) {
            return [ResultadoEic::noAplica($noCumple, $traza), $hijos];
        }

        $columna = ($conjunta ? 'mfj_' : 'solo_').$hijos->hijosParaLaTabla();
        $traza = $traza->conOperacion(__('Columna de la EIC Table: :columna (estado civil y número de hijos con SSN válido)', ['columna' => $columna]), $columna);

        // Línea 1z (ingreso del trabajo de la hoja, Paso 5) y línea 11b (ingreso ajustado).
        [$ganado1z, $linea1z] = $this->leer($d, '1040', '1z', 'Form 1040');
        [$agi, $linea11b] = $this->leer($d, '1040', '11b', 'Form 1040');
        $sinValidar = [...$linea1z->datosSinValidar, ...$linea11b->datosSinValidar];
        $traza = $traza
            ->conEntrada(__('Form 1040, línea 1z (ingreso del trabajo, Paso 5)'), $ganado1z, OrigenTraza::Linea, '1040:1z')
            ->conEntrada(__('Form 1040, línea 11b (ingreso ajustado)'), $agi, OrigenTraza::Linea, '1040:11b');

        $fin = CreditoEic::finReduccion($tabla, $columna);
        $inicio = CreditoEic::inicioReduccion($tabla, $columna);

        // Paso 1, pregunta 1 (p. 40): la línea 11b tiene que ser menor que el fin de la tabla.
        if ($agi->centavos() >= $fin->centavos()) {
            return [ResultadoEic::noAplica(__('Sin crédito por ingreso del trabajo: la línea 11b (USD :agi) no es menor que USD :fin (instrucciones del 1040, p. 40, Paso 1, pregunta 1).', ['agi' => $agi->texto(), 'fin' => $fin->texto()]), $traza), $hijos];
        }

        // Hoja A (sin autoempleo) o B (con Schedule C): Paso 5, pregunta 2 (p. 42).
        $c31 = $this->lineaSchedule31($d);

        if ($c31 === null) {
            $ingreso = $ganado1z;
            $traza = $traza->conOperacion(__('Hoja de trabajo A (sin autoempleo): línea 1 = ingreso del trabajo'), $ingreso);
        } else {
            [$ingreso, $traza, $sinValidarB] = $this->ingresoHojaB($e, $d, $c31, $ganado1z, $traza);
            $sinValidar = [...$sinValidar, ...$sinValidarB];
        }

        $sinValidar = array_values(array_unique($sinValidar));

        if ($ingreso->centavos() >= $fin->centavos()) {
            return [ResultadoEic::noAplica(__('Sin crédito por ingreso del trabajo: el ingreso del trabajo (USD :ingreso) no es menor que USD :fin (instrucciones del 1040, p. 42, Paso 5, pregunta 3; hoja B, línea 5, p. 47).', ['ingreso' => $ingreso->texto(), 'fin' => $fin->texto()]), $traza), $hijos];
        }

        // Hoja A líneas 2 a 6 / hoja B líneas 6 a 11 (pp. 46 y 48).
        $credito = CreditoEic::deLaTabla($tabla, $columna, $ingreso);
        $traza = $traza->conOperacion(__('EIC Table con el ingreso del trabajo (hoja A, línea 2; hoja B, línea 7)'), $credito);

        if ($credito->esCero()) {
            return [ResultadoEic::noAplica(__('Sin crédito por ingreso del trabajo: la EIC Table da 0 para el ingreso del trabajo (hoja A, línea 2, p. 46; hoja B, línea 7, p. 48).'), $traza), $hijos];
        }

        if ($agi->centavos() !== $ingreso->centavos() && $agi->centavos() >= $inicio->centavos()) {
            $conIngresoAjustado = CreditoEic::deLaTabla($tabla, $columna, $agi);
            $traza = $traza->conOperacion(__('EIC Table con el ingreso ajustado (hoja A, línea 5; hoja B, línea 10)'), $conIngresoAjustado);
            $credito = $credito->min($conIngresoAjustado);
        } else {
            $traza = $traza->conOperacion(__('El ingreso ajustado es igual al ingreso del trabajo o menor que el inicio de la reducción (USD :inicio): el crédito es el de la línea 2', ['inicio' => $inicio->texto()]));
        }

        $traza = $traza->conResultado(__('Crédito por ingreso del trabajo (1040, línea 27a)'), $credito);

        return [ResultadoEic::credito($credito, $traza, $sinValidar), $hijos];
    }

    /**
     * Pasos 3 y 4 (pp. 41 y 42) y la regla de las personas separadas (p. 44): lo que impide el crédito, o null si cumple.
     * Lanza falta_dato cuando una respuesta necesaria no está.
     */
    private function requisitosPersonales(EntradasDeclaracion $e, EstadoCivilIrs $estado, PersonaEntrada $contribuyente, ?PersonaEntrada $conyuge, EvaluacionHijos $hijos): ?string
    {
        $conjunta = $estado === EstadoCivilIrs::Mfj;
        $reclamado = $e->respuestas['A5']->valor ?? null;

        if (! $conjunta && $reclamado === null) {
            throw new SinCalculo(ResultadoEic::faltaDato(__('Falta saber si otra persona puede reclamar al contribuyente como dependiente (pregunta A5).')));
        }

        if ($hijos->hayHijoCalificado()) {
            // Paso 3, preguntas 3 a 5 (p. 41) y "Special rule for separated spouses" (p. 44).
            if ($estado === EstadoCivilIrs::Mfs) {
                $this->exigirSeparadoParaMfs($e);
            }

            if (! $conjunta && $reclamado === true) {
                throw new SinCalculo(ResultadoEic::faltaDato(__('Otra persona puede reclamar al contribuyente (A5): el preparador revisa si es hijo calificado de otra persona (instrucciones del 1040, p. 41, Paso 3, pregunta 6).')));
            }

            return null;
        }

        // Paso 4 (sin hijo calificado). Pregunta 1: casado con MFS o HOH, sin crédito.
        $casado = ($e->respuestas['B1']->valor ?? null) === 'casado';

        if ($estado === EstadoCivilIrs::Mfs || ($estado === EstadoCivilIrs::Hoh && $casado)) {
            return __('Sin crédito por ingreso del trabajo: sin hijo calificado, quien declara casado por separado o como cabeza de familia estando casado no puede tomarlo (instrucciones del 1040, p. 41, Paso 4, pregunta 1).');
        }

        // Pregunta 2: 25 a 64 años al cierre del año (el contribuyente o, en conjunta, su cónyuge).
        $edades = array_filter([$contribuyente->edadAlFinDeAnio, $conyuge?->edadAlFinDeAnio], fn (?int $edad): bool => $edad !== null);
        $faltaEdad = $contribuyente->edadAlFinDeAnio === null || ($conjunta && $conyuge?->edadAlFinDeAnio === null);

        if ($edades === []) {
            throw new SinCalculo(ResultadoEic::faltaDato(__('Falta la fecha de nacimiento del contribuyente (pregunta C2) para la regla de edad del crédito sin hijos.')));
        }

        $enRango = array_filter($edades, fn (int $edad): bool => $edad >= self::EDAD_MINIMA_SIN_HIJOS && $edad <= self::EDAD_MAXIMA_SIN_HIJOS);

        if ($enRango === []) {
            if (in_array(self::EDAD_MINIMA_SIN_HIJOS - 1, $edades, true) || $faltaEdad) {
                // Nacido el 1 de enero de 2001 cuenta como 25 ("born after December 31, 1960, and before January 2, 2001", p. 41).
                throw new SinCalculo(ResultadoEic::faltaDato(__('Con 24 años al cierre del año o sin la edad del cónyuge, el preparador confirma la fecha de nacimiento: nacer el 1 de enero de 2001 cuenta como 25 años (instrucciones del 1040, p. 41, Paso 4, pregunta 2).')));
            }

            return __('Sin crédito por ingreso del trabajo: sin hijo calificado hay que tener de 25 a 64 años al cierre del año (instrucciones del 1040, p. 41, Paso 4, pregunta 2).');
        }

        if (! $conjunta && $reclamado === true) {
            return __('Sin crédito por ingreso del trabajo: otra persona puede reclamar al contribuyente como dependiente (instrucciones del 1040, p. 42, Paso 4, preguntas 5 y 6).');
        }

        return null;
    }

    /**
     * Un casado por separado (MFS) con hijo calificado solo toma el crédito si vivió aparte de su cónyuge los
     * últimos 6 meses del año o está legalmente separado (instrucciones del 1040 de 2025, p. 41, Paso 3, preguntas
     * 3 a 5, y p. 44, "Special rule for separated spouses"). Verificado en el PDF de 2025.
     */
    private function exigirSeparadoParaMfs(EntradasDeclaracion $e): void
    {
        $aparte = $e->respuestas['B3']->valor ?? null;

        if ($aparte === true) {
            return;
        }

        $revisa = __('Con MFS, este crédito lo revisa el preparador');

        if ($aparte === null) {
            throw new SinCalculo(ResultadoEic::faltaDato($revisa.': '.__('falta saber si vivió separado de su cónyuge los últimos 6 meses del año (pregunta B3).')));
        }

        if (($e->respuestas['B1']->valor ?? null) === 'separado') {
            throw new SinCalculo(ResultadoEic::faltaDato($revisa.': '.__('está separado legalmente y hay que confirmar que no vivían en el mismo hogar al cierre del año (instrucciones del 1040, p. 44).')));
        }

        throw new SinCalculo(ResultadoEic::noAplica(__('Sin crédito por ingreso del trabajo: casado por separado, con hijo calificado, solo lo toma quien vivió aparte de su cónyuge los últimos 6 meses del año o está legalmente separado (instrucciones del 1040, p. 41 y p. 44).')));
    }

    /**
     * Paso 3 (p. 41): hijo calificado del EIC. Parte de los dependientes que el preparador clasificó como hijo
     * calificado y exige parentesco, más de medio año en casa y la edad (menor de 19 y más joven que quien declara;
     * menor de 24 y estudiante; o incapacitado de cualquier edad). La regla de convivencia por nacimiento
     * o muerte en el año y las de los padres que no declaran juntos las revisa el preparador.
     */
    private function evaluarHijos(EntradasDeclaracion $e, PersonaEntrada $contribuyente, ?PersonaEntrada $conyuge): EvaluacionHijos
    {
        $calificados = [];
        $conSsn = [];
        $faltantes = [];
        $edades = array_filter([$contribuyente->edadAlFinDeAnio, $conyuge?->edadAlFinDeAnio], fn (?int $edad): bool => $edad !== null);

        foreach ($e->personas as $p) {
            if ($p->rol !== RolPersona::Dependiente || $p->clasificacion !== ClasificacionPersona::HijoCalificado) {
                continue;
            }

            $faltaEn = function (string $dato) use (&$faltantes, $p): void {
                $faltantes[] = __(':dato del dependiente #:id', ['dato' => $dato, 'id' => $p->id]);
            };

            if ($p->parentesco === null) {
                $faltaEn(__('el parentesco (pregunta C3)'));

                continue;
            }

            if (! in_array($p->parentesco, self::PARENTESCOS_QUE_CALIFICAN, true)) {
                continue;
            }

            if ($p->mesesConviviendo === null) {
                $faltaEn(__('los meses que vivió con el contribuyente (pregunta C4)'));
            }

            if ($p->edadAlFinDeAnio === null) {
                $faltaEn(__('la fecha de nacimiento (pregunta C2)'));
            }

            if ($p->mesesConviviendo === null || $p->edadAlFinDeAnio === null) {
                continue;
            }

            if ($p->mesesConviviendo < self::MESES_MAS_DE_LA_MITAD) {
                if ($p->edadAlFinDeAnio === 0) {
                    $faltantes[] = __('confirmar el tiempo de convivencia del dependiente #:id, que nació en el año (instrucciones del 1040, p. 43, "Exception to time lived with you")', ['id' => $p->id]);
                }

                continue;
            }

            $edad = $p->edadAlFinDeAnio;
            $cumpleEdad = $p->incapacitado === true;

            if (! $cumpleEdad) {
                $menorDeEdad = $edad < self::EDAD_MENOR_DE;
                $estudiante = $edad < self::EDAD_ESTUDIANTE_MENOR_DE && $p->esEstudiante === true;

                if (! $menorDeEdad && ! $estudiante) {
                    // No califica por edad; si falta un dato que podría cambiarlo, se pide.
                    if ($p->incapacitado === null) {
                        $faltaEn(__('si está incapacitado total y permanentemente (pregunta C8)'));
                    }

                    if ($edad < self::EDAD_ESTUDIANTE_MENOR_DE && $p->esEstudiante === null) {
                        $faltaEn(__('si es estudiante a tiempo completo (pregunta C7)'));
                    }

                    continue;
                }

                // "Younger than you (or your spouse if filing jointly)".
                if ($edades === []) {
                    $faltantes[] = __('la fecha de nacimiento del contribuyente (pregunta C2), para saber si el hijo es más joven');

                    continue;
                }

                if ($edad >= max($edades)) {
                    continue;
                }
            }

            if ($p->otroPuedeReclamar === true) {
                $faltantes[] = __('decidir quién reclama al dependiente #:id, porque otra persona también puede hacerlo (pregunta C11, reglas de desempate de las instrucciones del 1040, p. 44)', ['id' => $p->id]);

                continue;
            }

            $calificados[] = $p->id;

            if ($this->ssnValidoParaEic($p)) {
                $conSsn[] = $p->id;
            }
        }

        return new EvaluacionHijos($calificados, $conSsn, array_values(array_unique($faltantes)));
    }

    /**
     * Hoja B, partes 1 a 4 (p. 47): ingreso del trabajo de quien es autoempleado.
     * Con Schedule SE: 1e = (SE línea 3 + 4b + 5a) - SE línea 13; sin él (ganancia menor al mínimo): 2c = Schedule C línea 31.
     * 4b = 1e + 2c + 3 + 4a (el 3, empleado por ley, no está en el piloto).
     *
     * @return array{Dinero, Traza, list<string>}
     */
    private function ingresoHojaB(EntradasDeclaracion $e, Declaracion $d, Dinero $c31, Dinero $ganado1z, Traza $traza): array
    {
        $traza = $traza->conOperacion(__('Hoja de trabajo B (con Schedule C): el ingreso del trabajo suma lo ganado más la ganancia de autoempleo'))
            ->conEntrada(__('Schedule C, línea 31 (ganancia neta)'), $c31, OrigenTraza::Linea, 'sch-c:31');
        $sinValidar = $d->linea('sch-c', '31')?->datosSinValidar ?? [];

        $se4c = $d->linea('sch-se', '4c');

        if ($se4c === null || $se4c->estado === EstadoLinea::FaltaDato) {
            throw new SinCalculo(ResultadoEic::faltaDato(__('Falta la línea 4c del Schedule SE.')));
        }

        if ($se4c->estado === EstadoLinea::NoSoportada) {
            throw new SinCalculo(ResultadoEic::noSoportada(__('El Schedule SE no se calcula todavía (línea 4c); sin él no se puede calcular el crédito por ingreso del trabajo.')));
        }

        $minimo = Dinero::deDolares((int) $this->parametro($e->taxYear, 'self_employment_tax', 'minimo_ingreso_neto'));
        $presentaSe = $se4c->valor instanceof Dinero && $se4c->valor->centavos() >= $minimo->centavos();

        if ($presentaSe) {
            [$l3, $linea3] = $this->leer($d, 'sch-se', '3', 'Schedule SE');
            [$l13, $linea13] = $this->leer($d, 'sch-se', '13', 'Schedule SE');
            // Líneas 4b (métodos opcionales) y 5a (iglesia) no están en el piloto: solo cuentan si otra calculadora las puso.
            $l4b = $d->linea('sch-se', '4b')?->valor;
            $l5a = $d->linea('sch-se', '5a')?->valor;
            $extra = ($l4b instanceof Dinero ? $l4b : Dinero::deCentavos(0))->mas($l5a instanceof Dinero ? $l5a : Dinero::deCentavos(0));
            $ingresoSe = $l3->mas($extra)->menos($l13);
            $sinValidar = [...$sinValidar, ...$se4c->datosSinValidar, ...$linea3->datosSinValidar, ...$linea13->datosSinValidar];
            $traza = $traza
                ->conEntrada(__('Schedule SE, línea 3'), $l3, OrigenTraza::Linea, 'sch-se:3')
                ->conEntrada(__('Schedule SE, línea 13 (mitad del impuesto de autoempleo)'), $l13, OrigenTraza::Linea, 'sch-se:13')
                ->conOperacion(__('Hoja B, parte 1: línea 1e = línea 3 más 4b y 5a, menos la línea 13 (obliga al Schedule SE: línea 4c de USD :minimo o más)', ['minimo' => $minimo->texto()]), $ingresoSe);
        } else {
            $ingresoSe = $c31;
            $sinValidar = [...$sinValidar, ...$se4c->datosSinValidar];
            $traza = $traza->conOperacion(__('Hoja B, parte 2: sin Schedule SE (línea 4c menor de USD :minimo), línea 2c = Schedule C, línea 31', ['minimo' => $minimo->texto()]), $ingresoSe);
        }

        $total = $ingresoSe->mas($ganado1z);
        $traza = $traza->conOperacion(__('Hoja B, parte 4: línea 4b = ingreso de autoempleo más el ingreso del trabajo de la línea 1z'), $total);

        return [$total, $traza, $sinValidar];
    }

    /** Línea 31 del Schedule C si hay negocio: null cuando no hay (hoja A). */
    private function lineaSchedule31(Declaracion $d): ?Dinero
    {
        $c31 = $d->linea('sch-c', '31');

        if ($c31 === null || $c31->estado === EstadoLinea::NoAplica) {
            return null;
        }

        if ($c31->estado === EstadoLinea::FaltaDato) {
            throw new SinCalculo(ResultadoEic::faltaDato(__('Falta la línea 31 del Schedule C.')));
        }

        if ($c31->estado === EstadoLinea::NoSoportada) {
            throw new SinCalculo(ResultadoEic::noSoportada(__('El Schedule C no se calcula completo (línea 31); sin él no se puede calcular el crédito por ingreso del trabajo.')));
        }

        return $c31->valor instanceof Dinero ? $c31->valor : null;
    }

    /** @return array{Dinero, Linea} */
    private function leer(Declaracion $d, string $formulario, string $linea, string $nombre): array
    {
        $l = $d->linea($formulario, $linea);

        if ($l === null || $l->estado === EstadoLinea::FaltaDato) {
            throw new SinCalculo(ResultadoEic::faltaDato(__('Falta la línea :linea del :formulario.', ['linea' => $linea, 'formulario' => $nombre])));
        }

        if ($l->estado === EstadoLinea::NoSoportada) {
            throw new SinCalculo(ResultadoEic::noSoportada(__('La línea :linea del :formulario no se puede calcular con los documentos del expediente; sin ella no se puede calcular el crédito por ingreso del trabajo.', ['linea' => $linea, 'formulario' => $nombre])));
        }

        // no_aplica (por ejemplo, sin W-2): cuenta como cero.
        return [$l->valor instanceof Dinero ? $l->valor : Dinero::deCentavos(0), $l];
    }

    private function documentoFueraDelPiloto(EntradasDeclaracion $e): ?string
    {
        foreach (self::DOCUMENTOS_FUERA_DEL_PILOTO as $documento) {
            foreach (array_keys($e->datos) as $clave) {
                if (str_starts_with((string) $clave, $documento.':')) {
                    return $documento;
                }
            }
        }

        return null;
    }

    private function persona(EntradasDeclaracion $e, RolPersona $rol): ?PersonaEntrada
    {
        foreach ($e->personas as $p) {
            if ($p->rol === $rol) {
                return $p;
            }
        }

        return null;
    }

    /** "Valid SSN" del EIC: emitido por el SSA y sin la leyenda "Not Valid for Employment" (instrucciones del 1040, p. 44). */
    private function ssnValidoParaEic(PersonaEntrada $p): bool
    {
        return $p->ssnValido && ! $p->noValidoParaTrabajar;
    }

    private function parametroTabla(int $taxYear): TablaFiscal
    {
        try {
            return ParametrosFiscales::tabla($taxYear, 'credito_eic', 'por_hijos');
        } catch (RuntimeException) {
            throw $this->parametroFaltante('credito_eic.por_hijos', $taxYear);
        }
    }

    private function topeIngresoInversion(int $taxYear): Dinero
    {
        return Dinero::deDolares((int) $this->parametro($taxYear, 'credito_eic', 'tope_ingreso_inversion'));
    }

    private function parametro(int $taxYear, string $categoria, string $clave): mixed
    {
        try {
            return ParametrosFiscales::valorRequerido($taxYear, $categoria, $clave);
        } catch (RuntimeException) {
            throw $this->parametroFaltante("{$categoria}.{$clave}", $taxYear);
        }
    }

    private function parametroFaltante(string $clave, int $taxYear): SinCalculo
    {
        return new SinCalculo(ResultadoEic::faltaDato(__('No se puede calcular: falta el parámetro :clave de :anio (Catálogo → Parámetros).', ['clave' => $clave, 'anio' => $taxYear])));
    }

    private function escribirLinea(Declaracion $d, string $formulario, string $linea, ResultadoEic $r): void
    {
        $d->poner(match ($r->estado) {
            EstadoLinea::Calculada => Linea::calculada($formulario, $linea, $r->credito ?? Dinero::deCentavos(0), $r->traza, $r->datosSinValidar),
            EstadoLinea::NoAplica => Linea::noAplica($formulario, $linea, $r->motivo, $r->traza),
            EstadoLinea::NoSoportada => Linea::noSoportada($formulario, $linea, (string) $r->motivo, $r->traza),
            EstadoLinea::FaltaDato => Linea::faltaDato($formulario, $linea, (string) $r->motivo, $r->traza),
        });
    }

    /**
     * Schedule EIC, líneas 1 a 6: solo "qué hijos entran" (ids separados por coma, nunca nombres ni SSN). Son los
     * hijos calificados, con o sin SSN válido: el Schedule EIC se anexa aunque el hijo no tenga SSN válido (p. 40).
     */
    private function escribirHijos(Declaracion $d, ResultadoEic $r, ?EvaluacionHijos $hijos): void
    {
        $origen = ['1' => 'C1', '2' => 'C9', '3' => 'C2', '4' => 'C7;C8', '5' => 'C3', '6' => 'C4'];

        foreach ($origen as $linea => $preguntas) {
            if ($r->estado === EstadoLinea::FaltaDato || $r->estado === EstadoLinea::NoSoportada) {
                $d->poner($r->estado === EstadoLinea::FaltaDato
                    ? Linea::faltaDato('sch-eic', $linea, (string) $r->motivo)
                    : Linea::noSoportada('sch-eic', $linea, (string) $r->motivo));

                continue;
            }

            if ($r->estado === EstadoLinea::NoAplica || $hijos === null || ! $hijos->hayHijoCalificado()) {
                $d->poner(Linea::noAplica('sch-eic', $linea, __('No se anexa el Schedule EIC: no hay crédito o no hay hijo calificado.')));

                continue;
            }

            $ids = implode(',', $hijos->calificados);
            $traza = Traza::vacia()
                ->conEntrada(__('Dependientes que entran al Schedule EIC (ids)'), $ids, OrigenTraza::Respuesta, $preguntas)
                ->conResultado(__('Hijos con SSN válido que cuentan para la tabla: :n', ['n' => count($hijos->conSsnValido)]), (string) count($hijos->conSsnValido));
            $d->poner(Linea::calculada('sch-eic', $linea, $ids, $traza, $r->datosSinValidar));
        }
    }
}
