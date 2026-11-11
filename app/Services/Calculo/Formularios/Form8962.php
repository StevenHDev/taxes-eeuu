<?php

namespace App\Services\Calculo\Formularios;

use App\Enums\Expediente\EstadoCivilIrs;
use App\Services\Calculo\Formularios\Form8962\EstadoResidencia;
use App\Services\Calculo\Formularios\Form8962\PolizasMarketplace;
use App\Services\Calculo\Formularios\Form8962\TablasPrima;
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
use RuntimeException;

/**
 * Form 8962 de 2025, crédito de la prima del Marketplace (instrucciones i8962 de 2025).
 *
 * - Parte I: 1 (tamaño de la familia), 2a (MAGI = línea 11a del 1040; el interés exento, el Seguro Social
 *   no gravable y el Form 2555 no son del piloto), 2b (MAGI de los dependientes obligados a declarar: no
 *   soportada si un dependiente tiene documentos de ingresos), 3, 4 (línea de pobreza según el estado de la
 *   dirección postal, A2), 5 (Worksheet 2), 7 (Tabla 2), 8a y 8b.
 * - Parte II: 9 (sin asignación ni alternativa del año de matrimonio), 10, la línea 11 (anual) o las 12 a
 *   23 (por mes) con sus seis columnas, 24 y 25; 26 (crédito neto → Schedule 3, línea 9).
 * - Parte III: 27, 28 (Tabla 5) y 29 (→ Schedule 2, línea 1a).
 * - Partes IV y V no se calculan: casarse en el año (B2) las puede pedir y deja todo no soportado.
 * - MFS (p. 7, 15 y 17): sin la excepción 2 (que la plataforma no pregunta) no hay crédito: líneas 1 a 5,
 *   solo la columna (f), 24 = 0 y todo el adelanto a la Parte III con el tope de la línea 28.
 *
 * Sin 1095-A escribe las líneas 26 y 29 como "no aplica". Corre después de `Ingresos1040` (línea 11a) y
 * antes de `ImpuestoYSchedule2` (lee la 29) y de `Schedule3` (lee la 26).
 */
final class Form8962 implements Calculadora
{
    private const F = 'f8962';

    private const F1040 = '1040';

    /** Worksheet 1-1 (p. 8): suman al MAGI el interés exento (1099-INT c. 8, 1099-DIV c. 12) y el Seguro Social no gravable. */
    private const DOCUMENTOS_MAGI = ['1099-int', '1099-div', 'ssa-1099'];

    /** Documentos de ingresos: si alguno es de un dependiente, su MAGI podría ir a la línea 2b. */
    private const DOCUMENTOS_INGRESO = ['w2', '1099-nec', '1099-k', '1099-misc', '1099-r', 'ssa-1099', '1099-g', '1099-int',
        '1099-div', '1099-b', '1099-s', '1099-da', 'w2g', '1099-c', 'k-1', '1099-q', '1099-sa'];

    /** Las líneas que se escriben aunque no se pueda calcular nada. */
    private const RESUMEN = ['1', '2a', '2b', '3', '4', '5', '7', '8a', '8b', '24', '25', '26', '27', '28', '29'];

    /** Worksheet 2 (p. 9): si la línea 3 es más del 400 % de la línea 4, la línea 5 es 401. */
    private const PORCENTAJE_TOPE = 400;

    private const PORCENTAJE_SOBRE_TOPE = 401;

    /** p. 9, "Household income below 100% of the federal poverty line". */
    private const PORCENTAJE_MINIMO = 100;

    public function formulario(): string
    {
        return self::F;
    }

    public function calcular(EntradasDeclaracion $e, Declaracion $d): void
    {
        $polizas = $e->documentos('1095-a');

        if ($polizas === [] && ! Apoyo::hayDatos($e, '1095-a')) {
            $motivo = __('Sin 1095-A no hay crédito de la prima que conciliar.');
            $d->poner(Linea::noAplica(self::F, '26', $motivo));
            $d->poner(Linea::noAplica(self::F, '29', $motivo));

            return;
        }

        $bloqueo = $this->bloqueo($e, $polizas);

        if ($bloqueo !== null) {
            foreach (self::RESUMEN as $linea) {
                $d->poner($this->igual($bloqueo, $linea));
            }

            return;
        }

        $mfs = $e->estadoCivil === EstadoCivilIrs::Mfs;

        $tamano = $this->linea1($e, $d);
        $this->linea2a($e, $d);
        $this->linea2b($e, $d);
        $this->linea3($d);
        $this->linea4($e, $d, $tamano);
        $porcentaje = $this->linea5($d);

        if ($mfs) {
            $motivo = __('Con MFS sin la excepción del 8962 se saltan las líneas 7 a 8b (instrucciones del 8962, p. 7).');

            foreach (['7', '8a', '8b'] as $linea) {
                $d->poner(Linea::noAplica(self::F, $linea, $motivo));
            }
        } else {
            $cifra = $this->linea7($e, $d, $porcentaje);
            $this->linea8($d, $cifra);
        }

        $this->linea9($e, $d);

        $lectura = PolizasMarketplace::leer($polizas, $mfs);

        if ($lectura instanceof Linea) {
            $d->poner($lectura);
            $d->poner($this->igual($lectura, '25'));

            foreach (['26', '27', '28', '29'] as $linea) {
                $d->poner($this->propagar($d, $linea, '24'));
            }

            return;
        }

        $d->poner(Linea::calculada(self::F, '10', $lectura->todoElAnio, Traza::vacia()
            ->conOperacion($lectura->todoElAnio
                ? __('Cada 1095-A cubre los 12 meses con la misma prima y el mismo SLCSP: se usa la línea 11 (instrucciones del 8962, línea 10, p. 14).')
                : __('Alguna póliza no cubre los 12 meses o cambia de prima o de SLCSP: se usan las líneas 12 a 23 (instrucciones del 8962, línea 10, p. 14).'))
            ->conResultado(Apoyo::nombreLinea(self::F, '10'), $lectura->todoElAnio)));

        if ($lectura->todoElAnio) {
            $this->linea11($d, $lectura, $mfs);
        } else {
            $this->lineas12a23($d, $lectura, $mfs);
        }

        $this->lineas26a29($e, $d, $porcentaje);
    }

    /**
     * Lo que impide calcular el formulario entero: la línea modelo (falta_dato o no_soportada), o null.
     *
     * @param  list<DocumentoEntrada>  $polizas
     */
    private function bloqueo(EntradasDeclaracion $e, array $polizas): ?Linea
    {
        if ($e->estadoCivil === null) {
            return Linea::faltaDato(self::F, '1', Apoyo::motivoSinEstadoCivil());
        }

        if ($polizas === []) {
            return Linea::faltaDato(self::F, '1', __('Faltan los 1095-A por separado (cada póliza con sus meses): con los totales sumados no se puede llenar el 8962.'));
        }

        if ($this->respuestaSi($e, 'A5')) {
            return Linea::noSoportada(self::F, '1', __('Otra persona puede reclamarte como dependiente (A5): el 8962 lo presenta quien te reclama (instrucciones del 8962, p. 3). Lo revisa el preparador.'));
        }

        if ($this->respuestaSi($e, 'B2')) {
            return Linea::noSoportada(self::F, '1', __('Te casaste en el año (B2): el 8962 puede necesitar la asignación de la Parte IV o el cálculo alternativo de la Parte V. Lo calcula el preparador.'));
        }

        return null;
    }

    /** Línea 1 (p. 7): tú, tu cónyuge si declaran juntos y tus dependientes. */
    private function linea1(EntradasDeclaracion $e, Declaracion $d): int
    {
        $conyuge = $e->estadoCivil === EstadoCivilIrs::Mfj ? 1 : 0;
        $dependientes = count($this->dependientes($e));
        $tamano = 1 + $conyuge + $dependientes;

        $traza = Traza::vacia()
            ->conEntrada($e->estadoCivil?->label() ?? '', $e->estadoCivil?->value, OrigenTraza::Decision, 'estado_civil_irs')
            ->conOperacion(__('Tú (1), tu cónyuge si declaran juntos (:conyuge) y tus dependientes (:dependientes).', ['conyuge' => (string) $conyuge, 'dependientes' => (string) $dependientes]))
            ->conResultado(Apoyo::nombreLinea(self::F, '1'), (string) $tamano);

        $d->poner(Linea::calculada(self::F, '1', (string) $tamano, $traza));

        return $tamano;
    }

    /** Línea 2a (Worksheet 1-1, p. 8): el AGI (1040, línea 11a) más el ingreso no gravable que no es del piloto. */
    private function linea2a(EntradasDeclaracion $e, Declaracion $d): void
    {
        $lectura = Apoyo::leerUna($d, self::F1040, '11a', self::F, '2a');

        if ($lectura instanceof Linea) {
            $d->poner($lectura);

            return;
        }

        $fuera = Apoyo::documentosPresentes($e, self::DOCUMENTOS_MAGI);

        if ($fuera !== []) {
            $d->poner(Linea::noSoportada(self::F, '2a', __('Hay documentos de intereses, dividendos o Seguro Social (:documentos): el interés exento y el Seguro Social no gravable suman al MAGI (Worksheet 1-1) y no son del piloto.', ['documentos' => implode(', ', $fuera)])));

            return;
        }

        [$agi, $sinValidar] = $lectura;
        $traza = Traza::vacia()
            ->conEntrada(Apoyo::nombreLinea(self::F1040, '11a'), $agi, OrigenTraza::Linea, self::F1040.':11a')
            ->conOperacion(__('Worksheet 1-1: sin interés exento, Seguro Social ni Form 2555 en el piloto, el MAGI es el AGI.'))
            ->conResultado(Apoyo::nombreLinea(self::F, '2a'), $agi);

        $d->poner(Linea::calculada(self::F, '2a', $agi, $traza, $sinValidar));
    }

    /** Línea 2b (Worksheet 1-2, p. 8): MAGI de los dependientes que están obligados a declarar. */
    private function linea2b(EntradasDeclaracion $e, Declaracion $d): void
    {
        $dependientes = $this->dependientes($e);

        if ($dependientes === []) {
            $d->poner(Linea::noAplica(self::F, '2b', __('Sin dependientes.')));

            return;
        }

        $ids = array_map(fn (PersonaEntrada $p): int => $p->id, $dependientes);

        foreach (self::DOCUMENTOS_INGRESO as $codigo) {
            foreach ($e->documentos($codigo) as $documento) {
                if (in_array($documento->personaId, $ids, true)) {
                    $d->poner(Linea::noSoportada(self::F, '2b', __('Un dependiente tiene ingresos (:documento): si está obligado a declarar, su MAGI va a la línea 2b. Lo calcula el preparador.', ['documento' => $documento->referencia()])));

                    return;
                }
            }
        }

        $cero = Dinero::deCentavos(0);
        $d->poner(Linea::calculada(self::F, '2b', $cero, Traza::vacia()
            ->conOperacion(__('Ningún documento de ingresos es de un dependiente: no hay MAGI de dependientes obligados a declarar.'))
            ->conResultado(Apoyo::nombreLinea(self::F, '2b'), $cero)));
    }

    /** Línea 3 (p. 8): 2a + 2b; si es menos de cero, cero. */
    private function linea3(Declaracion $d): void
    {
        $lectura = Apoyo::leerVarias($d, [[self::F, '2a'], [self::F, '2b']], self::F, '3');

        if ($lectura instanceof Linea) {
            $d->poner($lectura);

            return;
        }

        [$valores, $traza, $sinValidar] = $lectura;
        $valor = $valores[0]->mas($valores[1])->max(Dinero::deCentavos(0));

        $d->poner(Linea::calculada(self::F, '3', $valor, $traza
            ->conOperacion(__('Línea 2a más línea 2b; si es menos de cero, cero.'))
            ->conResultado(Apoyo::nombreLinea(self::F, '3'), $valor), $sinValidar));
    }

    /** Línea 4 (p. 8 y 9): la línea de pobreza de la tabla del estado de residencia para el tamaño de la línea 1. */
    private function linea4(EntradasDeclaracion $e, Declaracion $d, int $tamano): void
    {
        $direccion = ($e->respuestas['A2'] ?? null)?->valor;
        $tabla = EstadoResidencia::tablaDesdeDireccion(is_string($direccion) ? $direccion : null);

        if ($tabla === null) {
            $d->poner(Linea::faltaDato(self::F, '4', __('Falta el estado de residencia de 2025: no se reconoce un estado en la dirección postal (A2). El preparador elige la tabla 1-1, 1-2 o 1-3 del 8962.')));

            return;
        }

        try {
            $valor = TablasPrima::lineaPobreza($e->taxYear, $tabla, $tamano);
        } catch (RuntimeException $excepcion) {
            $d->poner(Linea::faltaDato(self::F, '4', Apoyo::motivoSinParametro($e->taxYear, $excepcion)));

            return;
        }

        $nombreTabla = match ($tabla) {
            TablasPrima::TABLA_ALASKA => __('Tabla 1-2 (Alaska)'),
            TablasPrima::TABLA_HAWAII => __('Tabla 1-3 (Hawái)'),
            default => __('Tabla 1-1 (48 estados contiguos y DC)'),
        };
        $clave = 'credito_prima_salud.'.TablasPrima::claveLineaPobreza($tabla);

        $traza = Traza::vacia()
            ->conEntrada(__('Estado de la dirección postal (A2)'), null, OrigenTraza::Respuesta, 'A2')
            ->conEntrada(Apoyo::nombreLinea(self::F, '1'), (string) $tamano, OrigenTraza::Linea, self::F.':1')
            ->conParametro($nombreTabla, $clave, $clave)
            ->conOperacion(__('Si en 2025 te mudaste y viviste en Alaska o Hawái, o los cónyuges vivieron en estados distintos, se usa la tabla más alta: lo revisa el preparador.'))
            ->conResultado(Apoyo::nombreLinea(self::F, '4'), $valor);

        $d->poner(Linea::calculada(self::F, '4', $valor, $traza));
    }

    /** Línea 5 (Worksheet 2, p. 9): el porcentaje entero (sin redondear) de la línea 3 sobre la 4; 401 si pasa del 400 %. */
    private function linea5(Declaracion $d): ?int
    {
        $lectura = Apoyo::leerVarias($d, [[self::F, '3'], [self::F, '4']], self::F, '5');

        if ($lectura instanceof Linea) {
            $d->poner($lectura);

            return null;
        }

        [[$ingreso, $pobreza], $traza, $sinValidar] = $lectura;

        if ($ingreso->centavos() > $pobreza->centavos() * intdiv(self::PORCENTAJE_TOPE, 100)) {
            $porcentaje = self::PORCENTAJE_SOBRE_TOPE;
            $traza = $traza->conOperacion(__('La línea 3 es más del 400 % de la línea 4: se anota 401 (Worksheet 2, p. 9).'));
        } else {
            $porcentaje = intdiv($ingreso->centavos() * 100, $pobreza->centavos());
            $traza = $traza->conOperacion(__('Línea 3 entre línea 4, por 100, sin redondear: se quitan los decimales (Worksheet 2, p. 9).'));
        }

        $d->poner(Linea::calculada(self::F, '5', (string) $porcentaje, $traza->conResultado(Apoyo::nombreLinea(self::F, '5'), (string) $porcentaje), $sinValidar));

        return $porcentaje;
    }

    /** Línea 7 (Tabla 2, p. 11): la cifra aplicable para el porcentaje de la línea 5. */
    private function linea7(EntradasDeclaracion $e, Declaracion $d, ?int $porcentaje): ?string
    {
        if ($porcentaje === null) {
            $d->poner($this->propagar($d, '7', '5'));

            return null;
        }

        if ($porcentaje < self::PORCENTAJE_MINIMO) {
            $d->poner(Linea::faltaDato(self::F, '7', __('El ingreso del hogar es menos del 100 % de la línea de pobreza (línea 5 = :porcentaje): solo hay crédito si el Marketplace estimó al inscribirte un ingreso de al menos el 100 % y se pagó adelanto, o por la excepción de extranjeros con presencia legal (instrucciones del 8962, p. 9 y 10). Lo confirma el preparador.', ['porcentaje' => (string) $porcentaje])));

            return null;
        }

        try {
            $cifra = TablasPrima::cifraAplicable($e->taxYear, $porcentaje);
        } catch (RuntimeException $excepcion) {
            $d->poner(Linea::faltaDato(self::F, '7', Apoyo::motivoSinParametro($e->taxYear, $excepcion)));

            return null;
        }

        $d->poner(Linea::calculada(self::F, '7', $cifra, Traza::vacia()
            ->conEntrada(Apoyo::nombreLinea(self::F, '5'), (string) $porcentaje, OrigenTraza::Linea, self::F.':5')
            ->conParametro(__('Tabla 2 del 8962 (cifra aplicable)'), 'credito_prima_salud.tabla_cifra_aplicable', 'credito_prima_salud.tabla_cifra_aplicable')
            ->conResultado(Apoyo::nombreLinea(self::F, '7'), $cifra), $d->linea(self::F, '5')->datosSinValidar ?? []));

        return $cifra;
    }

    /** Líneas 8a y 8b (p. 12): 3 × 7 redondeado a dólares; 8a ÷ 12 redondeado a dólares. */
    private function linea8(Declaracion $d, ?string $cifra): void
    {
        if ($cifra === null) {
            $d->poner($this->propagar($d, '8a', '7'));
            $d->poner($this->propagar($d, '8b', '7'));

            return;
        }

        [$ingreso, $sinValidar] = Apoyo::leerUna($d, self::F, '3', self::F, '8a');
        $anual = $ingreso->por($cifra)->aDolares();

        $d->poner(Linea::calculada(self::F, '8a', $anual, Traza::vacia()
            ->conEntrada(Apoyo::nombreLinea(self::F, '3'), $ingreso, OrigenTraza::Linea, self::F.':3')
            ->conEntrada(Apoyo::nombreLinea(self::F, '7'), $cifra, OrigenTraza::Linea, self::F.':7')
            ->conOperacion(__('Línea 3 por la línea 7, redondeado al dólar más cercano.'))
            ->conResultado(Apoyo::nombreLinea(self::F, '8a'), $anual), $sinValidar));

        // Dividir entre 12 y redondear al dólar: entero de (2 × dólares + 12) / 24, sin coma flotante.
        $dolares = intdiv($anual->centavos(), 100);
        $mensual = Dinero::deDolares(intdiv(2 * $dolares + 12, 24));

        $d->poner(Linea::calculada(self::F, '8b', $mensual, Traza::vacia()
            ->conEntrada(Apoyo::nombreLinea(self::F, '8a'), $anual, OrigenTraza::Linea, self::F.':8a')
            ->conOperacion(__('Línea 8a entre 12, redondeado al dólar más cercano.'))
            ->conResultado(Apoyo::nombreLinea(self::F, '8b'), $mensual), $sinValidar));
    }

    /** Línea 9 (p. 12): la asignación de la Parte IV y la alternativa de la Parte V no se calculan. */
    private function linea9(EntradasDeclaracion $e, Declaracion $d): void
    {
        $traza = Traza::vacia()->conOperacion(__('La plataforma no pregunta si compartiste una póliza con otra familia fiscal (Parte IV): se marca "No".'));
        $estado = ($e->respuestas['B1'] ?? null)?->valor;

        if (in_array($estado, ['divorciado', 'separado'], true)) {
            $traza = $traza->conOperacion(__('Si te divorciaste o separaste en 2025 y la póliza cubría a tu excónyuge, hay que asignar los montos (Parte IV): lo revisa el preparador.'));
        }

        if ($e->estadoCivil === EstadoCivilIrs::Mfs) {
            $traza = $traza->conOperacion(__('Con MFS, si la póliza cubrió a alguien de la familia fiscal del cónyuge, se devuelve la mitad del adelanto (Parte IV): lo revisa el preparador.'));
        }

        $d->poner(Linea::calculada(self::F, '9', false, $traza->conResultado(Apoyo::nombreLinea(self::F, '9'), false)));
    }

    /** Línea 11 (p. 15 y 16): totales del año. Con MFS sin la excepción, solo la columna (f). */
    private function linea11(Declaracion $d, PolizasMarketplace $polizas, bool $mfs): void
    {
        $anual = $polizas->anual;
        $sinValidar = $polizas->sinValidar;

        $this->poner($d, '11f', $anual['f'], $anual['traza'], $sinValidar);

        if ($mfs) {
            $this->lineas24y25Mfs($d, '11f', $anual['f'], $sinValidar);

            return;
        }

        $this->poner($d, '11a', $anual['a'], $anual['traza'], $sinValidar);
        $this->poner($d, '11b', $anual['b'], $anual['traza'], $sinValidar);

        $contribucion = Apoyo::leerUna($d, self::F, '8a', self::F, '11c');

        if ($contribucion instanceof Linea) {
            foreach (['11c', '11d', '11e'] as $linea) {
                $d->poner($this->propagar($d, $linea, '8a'));
            }
            $d->poner($this->propagar($d, '24', '8a'));
        } else {
            [$c, $pendientes] = $contribucion;
            $sinValidar = array_values(array_unique([...$sinValidar, ...$pendientes]));
            [$dd, $e] = $this->columnasDyE($anual['a'], $anual['b'], $c);
            $traza = Traza::vacia()->conEntrada(Apoyo::nombreLinea(self::F, '8a'), $c, OrigenTraza::Linea, self::F.':8a');

            $this->poner($d, '11c', $c, $traza, $sinValidar);
            $this->poner($d, '11d', $dd, $traza->conOperacion(__('Columna (b) menos columna (c); si es cero o menos, cero.')), $sinValidar);
            $this->poner($d, '11e', $e, $traza->conOperacion(__('La menor entre la columna (a) y la (d).')), $sinValidar);
            $this->poner($d, '24', $e, Traza::vacia()->conEntrada(Apoyo::nombreLinea(self::F, '11').' (e)', $e, OrigenTraza::Linea, self::F.':11e'), $sinValidar);
        }

        $this->poner($d, '25', $anual['f'], Traza::vacia()->conEntrada(Apoyo::nombreLinea(self::F, '11').' (f)', $anual['f'], OrigenTraza::Linea, self::F.':11f'), $sinValidar);
    }

    /** Líneas 12 a 23 (p. 16 y 17): mes por mes; solo los meses con cobertura. */
    private function lineas12a23(Declaracion $d, PolizasMarketplace $polizas, bool $mfs): void
    {
        $cero = Dinero::deCentavos(0);
        $sumaE = $cero;
        $sumaF = $cero;
        $trazaF = Traza::vacia();
        $trazaE = Traza::vacia();
        $sinValidar = $polizas->sinValidar;
        $contribucion = $mfs ? null : Apoyo::leerUna($d, self::F, '8b', self::F, '12c');

        foreach ($polizas->meses as $mes => $columnas) {
            $linea = (string) (11 + $mes);
            $this->poner($d, $linea.'f', $columnas['f'], $columnas['traza'], $sinValidar);
            $sumaF = $sumaF->mas($columnas['f']);
            $trazaF = $trazaF->conEntrada(Apoyo::nombreLinea(self::F, $linea).' (f)', $columnas['f'], OrigenTraza::Linea, self::F.':'.$linea.'f');

            if ($mfs) {
                continue;
            }

            $this->poner($d, $linea.'a', $columnas['a'], $columnas['traza'], $sinValidar);
            $this->poner($d, $linea.'b', $columnas['b'], $columnas['traza'], $sinValidar);

            if ($contribucion instanceof Linea) {
                foreach (['c', 'd', 'e'] as $columna) {
                    $d->poner($this->propagar($d, $linea.$columna, '8b'));
                }

                continue;
            }

            [$c, $pendientes] = $contribucion;
            [$dd, $e] = $this->columnasDyE($columnas['a'], $columnas['b'], $c);
            $traza = Traza::vacia()->conEntrada(Apoyo::nombreLinea(self::F, '8b'), $c, OrigenTraza::Linea, self::F.':8b');
            $todos = array_values(array_unique([...$sinValidar, ...$pendientes]));

            $this->poner($d, $linea.'c', $c, $traza, $todos);
            $this->poner($d, $linea.'d', $dd, $traza->conOperacion(__('Columna (b) menos columna (c); si es cero o menos, cero.')), $todos);
            $this->poner($d, $linea.'e', $e, $traza->conOperacion(__('La menor entre la columna (a) y la (d).')), $todos);
            $sumaE = $sumaE->mas($e);
            $trazaE = $trazaE->conEntrada(Apoyo::nombreLinea(self::F, $linea).' (e)', $e, OrigenTraza::Linea, self::F.':'.$linea.'e');
        }

        if ($mfs) {
            $this->lineas24y25Mfs($d, null, $sumaF, $sinValidar, $trazaF);

            return;
        }

        if ($contribucion instanceof Linea) {
            $d->poner($this->propagar($d, '24', '8b'));
        } else {
            $this->poner($d, '24', $sumaE, $trazaE->conOperacion(__('Suma de la columna (e) de las líneas 12 a 23.')), array_values(array_unique([...$sinValidar, ...$contribucion[1]])));
        }

        $this->poner($d, '25', $sumaF, $trazaF->conOperacion(__('Suma de la columna (f) de las líneas 12 a 23.')), $sinValidar);
    }

    /**
     * MFS sin la excepción (p. 15 y 17): línea 24 en cero y en la 25 todo el adelanto.
     *
     * @param  list<string>  $sinValidar
     */
    private function lineas24y25Mfs(Declaracion $d, ?string $lineaAnual, Dinero $adelanto, array $sinValidar, ?Traza $traza = null): void
    {
        $cero = Dinero::deCentavos(0);
        $d->poner(Linea::calculada(self::F, '24', $cero, Traza::vacia()
            ->conOperacion(__('Con MFS sin la excepción 2 (violencia doméstica o abandono, que la plataforma no pregunta) no hay crédito: línea 24 en cero (instrucciones del 8962, p. 15 y 17).'))
            ->conResultado(Apoyo::nombreLinea(self::F, '24'), $cero)));

        $traza ??= Traza::vacia()->conEntrada(Apoyo::nombreLinea(self::F, '11').' (f)', $adelanto, OrigenTraza::Linea, self::F.':'.$lineaAnual);
        $this->poner($d, '25', $adelanto, $traza, $sinValidar);
    }

    /** Líneas 26 a 29 (p. 18): crédito neto, o adelanto de más con su tope. */
    private function lineas26a29(EntradasDeclaracion $e, Declaracion $d, ?int $porcentaje): void
    {
        $lectura = Apoyo::leerVarias($d, [[self::F, '24'], [self::F, '25']], self::F, '26');

        if ($lectura instanceof Linea) {
            $d->poner($lectura);

            foreach (['27', '28', '29'] as $linea) {
                $d->poner($this->igual($lectura, $linea));
            }

            return;
        }

        [[$credito, $adelanto], $traza, $sinValidar] = $lectura;

        if ($credito->centavos() >= $adelanto->centavos()) {
            $neto = $credito->menos($adelanto);
            $this->poner($d, '26', $neto, $traza->conOperacion(__('Línea 24 menos línea 25 (cero si son iguales); va a la línea 9 del Schedule 3.')), $sinValidar);

            foreach (['27', '28', '29'] as $linea) {
                $d->poner(Linea::noAplica(self::F, $linea, __('No se adelantó más que el crédito.')));
            }

            return;
        }

        $d->poner(Linea::noAplica(self::F, '26', __('Se adelantó más que el crédito: va a la Parte III.')));
        $exceso = $adelanto->menos($credito);
        $this->poner($d, '27', $exceso, $traza->conOperacion(__('Línea 25 menos línea 24.')), $sinValidar);

        if ($porcentaje === null) {
            $d->poner($this->propagar($d, '28', '5'));
            $d->poner($this->propagar($d, '29', '5'));

            return;
        }

        try {
            $tope = TablasPrima::topeDevolucion($e->taxYear, $porcentaje, $e->estadoCivil ?? EstadoCivilIrs::Single);
        } catch (RuntimeException $excepcion) {
            $motivo = Apoyo::motivoSinParametro($e->taxYear, $excepcion);
            $d->poner(Linea::faltaDato(self::F, '28', $motivo));
            $d->poner(Linea::faltaDato(self::F, '29', $motivo));

            return;
        }

        $trazaTope = Traza::vacia()
            ->conEntrada(Apoyo::nombreLinea(self::F, '5'), (string) $porcentaje, OrigenTraza::Linea, self::F.':5')
            ->conParametro(__('Tabla 5 del 8962 (tope de la devolución)'), 'credito_prima_salud.tabla_tope_devolucion', 'credito_prima_salud.tabla_tope_devolucion')
            ->conOperacion(__('Si trabajas por cuenta propia y tomas la deducción del seguro médico, o alguien cubierto no tenía presencia legal, el tope cambia (Pub. 974): lo revisa el preparador.'));

        $trazaExceso = Traza::vacia()->conEntrada(Apoyo::nombreLinea(self::F, '27'), $exceso, OrigenTraza::Linea, self::F.':27');

        if ($tope === null) {
            $d->poner(Linea::noAplica(self::F, '28', __('Con la línea 5 en 400 o más no hay tope: la línea 28 queda en blanco.'), $trazaTope));
            $this->poner($d, '29', $exceso, $trazaExceso->conOperacion(__('Sin tope: la línea 29 es la 27; va a la línea 1a del Schedule 2.')), $sinValidar);

            return;
        }

        $this->poner($d, '28', $tope, $trazaTope, $sinValidar);
        $devolucion = $exceso->min($tope);
        $this->poner($d, '29', $devolucion, $trazaExceso
            ->conEntrada(Apoyo::nombreLinea(self::F, '28'), $tope, OrigenTraza::Linea, self::F.':28')
            ->conOperacion(__('La menor entre la línea 27 y la 28; va a la línea 1a del Schedule 2.')), $sinValidar);
    }

    /**
     * Columnas (d) = (b) − (c), mínimo cero, y (e) = la menor entre (a) y (d).
     *
     * @return array{0: Dinero, 1: Dinero}
     */
    private function columnasDyE(Dinero $a, Dinero $b, Dinero $c): array
    {
        $d = $b->menos($c)->max(Dinero::deCentavos(0));

        return [$d, $a->min($d)];
    }

    /** @return list<PersonaEntrada> */
    private function dependientes(EntradasDeclaracion $e): array
    {
        return array_values(array_filter(
            $e->personas,
            fn (PersonaEntrada $p): bool => $p->rol === RolPersona::Dependiente && $p->clasificacion->esDependiente(),
        ));
    }

    private function respuestaSi(EntradasDeclaracion $e, string $codigo): bool
    {
        $valor = ($e->respuestas[$codigo] ?? null)?->valor;

        return $valor === true || $valor === 'si';
    }

    /** @param list<string> $sinValidar */
    private function poner(Declaracion $d, string $linea, Dinero $valor, Traza $traza, array $sinValidar = []): void
    {
        $d->poner(Linea::calculada(self::F, $linea, $valor, $traza->conResultado(Apoyo::nombreLinea(self::F, $linea), $valor), $sinValidar));
    }

    /** La línea `$destino` con el problema de la línea `$origen` del 8962 (falta_dato o no_soportada). */
    private function propagar(Declaracion $d, string $destino, string $origen): Linea
    {
        $resultado = Apoyo::leerLineas($d, self::F, $destino, [[self::F, $origen, true]]);

        return $resultado instanceof Linea
            ? $resultado
            : Linea::faltaDato(self::F, $destino, __('Falta :linea.', ['linea' => Apoyo::nombreLinea(self::F, $origen, true)]));
    }

    /** Otra línea del 8962 con el mismo estado y motivo que la modelo. */
    private function igual(Linea $modelo, string $linea): Linea
    {
        return new Linea(self::F, $linea, null, $modelo->estado, $modelo->motivo, Traza::vacia());
    }
}
