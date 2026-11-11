<?php

namespace Tests\Unit\Calculo\Formularios;

use App\Enums\Expediente\ClasificacionPersona;
use App\Enums\Expediente\EstadoCivilIrs;
use App\Models\ParametroFiscal;
use App\Services\Calculo\Formularios\Eitc;
use App\Services\Calculo\Nucleo\DatoEntrada;
use App\Services\Calculo\Nucleo\Declaracion;
use App\Services\Calculo\Nucleo\Dinero;
use App\Services\Calculo\Nucleo\EntradasDeclaracion;
use App\Services\Calculo\Nucleo\EstadoLinea;
use App\Services\Calculo\Nucleo\Linea;
use App\Services\Calculo\Nucleo\OrigenTraza;
use App\Services\Calculo\Nucleo\PersonaEntrada;
use App\Services\Calculo\Nucleo\RespuestaEntrada;
use App\Services\Calculo\Nucleo\RolPersona;
use App\Services\Calculo\Nucleo\Traza;
use App\Support\ParametrosFiscales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Calculadora Eitc (línea 27a del 1040 y Schedule EIC). Los valores esperados salen de la EIC Table de la Pub. 1040
 * de 2025 (tests/Fixtures/irs/2025/pdf/p1040.pdf, pp. 16 a 27) y de las instrucciones de la línea 27a del 1040
 * (i1040gi, pp. 40 a 48, "Hoja A" p. 46 y "Hoja B" pp. 47 y 48), no del código.
 */
class EitcTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Los motivos se comparan en español; el idioma por defecto de la aplicación es inglés.
        app()->setLocale('es');
    }

    // ---- Ejemplo del propio IRS --------------------------------------------------------------------------------

    public function test_ejemplo_de_la_eic_table_soltero_un_hijo_con_2455_da_842(): void
    {
        // Pub. 1040 de 2025, p. 16 (recuadro "Example"): soltero, un hijo con SSN válido, monto de la hoja USD 2.455 -> USD 842.
        $d = $this->correr($this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(), $this->hijo(3)]), '2455', '2455');

        $this->assertSame(84200, $this->credito($d));
        $this->assertSame(84200, $d->linea('sch-eic', 'credito')?->valor?->centavos());
    }

    // ---- Número de hijos y estado civil ------------------------------------------------------------------------

    public function test_cero_hijos_en_el_tope_de_la_fase_de_entrada_y_al_empezar_la_reduccion(): void
    {
        $sinHijos = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(30)]);

        // Pub. 1040, p. 17: 8.450 a 8.500 con 0 hijos = 649 (el máximo).
        $this->assertSame(64900, $this->credito($this->correr($sinHijos, '8475', '8475')));
        // Pub. 1040, p. 17: 10.650 a 10.700 con 0 hijos: soltero 645, casados conjunta 649.
        $this->assertSame(64500, $this->credito($this->correr($sinHijos, '10675', '10675')));
        $conjunta = $this->entradas(EstadoCivilIrs::Mfj, [$this->contribuyente(30), $this->conyuge(31)]);
        $this->assertSame(64900, $this->credito($this->correr($conjunta, '10675', '10675')));
    }

    public function test_dos_tres_y_cuatro_hijos_con_el_mismo_ingreso(): void
    {
        // Pub. 1040, p. 18: 17.850 a 17.900: 2 hijos 7.152, 3 hijos 8.046 (columna solo); con 4 o más hijos se usa la de 3.
        $dos = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(), $this->hijo(3), $this->hijo(4)]);
        $tres = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(), $this->hijo(3), $this->hijo(4), $this->hijo(5)]);
        $cuatro = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(), $this->hijo(3), $this->hijo(4), $this->hijo(5), $this->hijo(6)]);

        $this->assertSame(715200, $this->credito($this->correr($dos, '17875', '17875')));
        $this->assertSame(804600, $this->credito($this->correr($tres, '17875', '17875')));
        $this->assertSame(804600, $this->credito($this->correr($cuatro, '17875', '17875')));
    }

    public function test_casados_conjunta_tienen_otra_columna_que_los_demas(): void
    {
        // Pub. 1040, p. 19: 20.000 a 20.050 con 0 hijos: soltero 0, conjunta 473.
        $solo = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(30)]);
        $conjunta = $this->entradas(EstadoCivilIrs::Mfj, [$this->contribuyente(30), $this->conyuge(31)]);

        $this->assertNull($this->credito($this->correr($solo, '20025', '20025')), 'la tabla da 0: sin crédito');
        $this->assertSame(47300, $this->credito($this->correr($conjunta, '20025', '20025')));
        // Pub. 1040, p. 22: 40.000 a 40.050 con 1 hijo: soltero 1.663, conjunta 2.801.
        $unHijoSolo = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(), $this->hijo(3)]);
        $unHijoMfj = $this->entradas(EstadoCivilIrs::Mfj, [$this->contribuyente(), $this->conyuge(), $this->hijo(3)]);
        $this->assertSame(166300, $this->credito($this->correr($unHijoSolo, '40025', '40025')));
        $this->assertSame(280100, $this->credito($this->correr($unHijoMfj, '40025', '40025')));
    }

    public function test_viudo_calificado_y_cabeza_de_familia_usan_la_columna_de_soltero(): void
    {
        // Pub. 1040, p. 16: el encabezado de la columna es "Single, head of household, or qualifying surviving spouse".
        foreach ([EstadoCivilIrs::Hoh, EstadoCivilIrs::Qss] as $estado) {
            $d = $this->correr($this->entradas($estado, [$this->contribuyente(), $this->hijo(3)]), '2455', '2455');
            $this->assertSame(84200, $this->credito($d), $estado->value);
        }
    }

    // ---- Hoja A: ingreso del trabajo contra ingreso ajustado ---------------------------------------------------

    public function test_hoja_a_con_ingreso_ajustado_mayor_toma_el_menor_de_los_dos_creditos(): void
    {
        $unHijo = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(), $this->hijo(3)]);

        // Hoja A, p. 46: línea 2 = EIC Table(1z) = p. 19, 20.000 a 20.050, 1 hijo = 4.328;
        // línea 3 (11b) = 40.025, no menor de 23.350, así que línea 5 = EIC Table(11b) = p. 22, 40.000 a 40.050 = 1.663; línea 6 = el menor.
        $this->assertSame(166300, $this->credito($this->correr($unHijo, '20025', '40025')));
        // Con 11b = 22.025 (menor de 23.350): línea 5 en blanco y línea 6 = línea 2 = 4.328 (p. 19, 22.000 a 22.050).
        $this->assertSame(432800, $this->credito($this->correr($unHijo, '22025', '22025')));
        $this->assertSame(432800, $this->credito($this->correr($unHijo, '20025', '22025')));
    }

    public function test_el_inicio_de_la_reduccion_cambia_en_el_dolar_exacto(): void
    {
        $unHijo = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(), $this->hijo(3)]);

        // Hoja A, línea 5 (p. 46): "is the amount on line 3 less than $23,350". Con 23.349 el crédito es el de la línea 2 (4.328, p. 19, 23.300 a 23.350);
        // con 23.350 se busca el 11b en la tabla: 23.350 a 23.400 = 4.324 (p. 19).
        $this->assertSame(432800, $this->credito($this->correr($unHijo, '20025', '23349')));
        $this->assertSame(432400, $this->credito($this->correr($unHijo, '20025', '23350')));
    }

    // ---- Fin del crédito (Paso 1, pregunta 1) ------------------------------------------------------------------

    public function test_en_el_ultimo_dolar_antes_del_fin_hay_credito_y_en_el_fin_no(): void
    {
        $unHijo = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(), $this->hijo(3)]);

        // Pub. 1040, p. 24, nota: "at least $50,400 but less than $50,434 ... one qualifying child ... your credit is $3";
        // "$50,434 or more ... you can't take the credit". Instrucciones del 1040, p. 40, Paso 1: 11b menor de $50,434.
        $this->assertSame(300, $this->credito($this->correr($unHijo, '50433', '50433')));
        $d = $this->correr($unHijo, '50434', '50434');
        $this->assertNull($this->credito($d));
        $this->assertSame(EstadoLinea::NoAplica, $d->linea('1040', '27a')?->estado);

        // Pub. 1040, p. 20, nota: dos casados conjunta sin hijos: 26.200 a 26.214 = $1; desde 26.214 no hay crédito.
        $conjunta = $this->entradas(EstadoCivilIrs::Mfj, [$this->contribuyente(30), $this->conyuge(31)]);
        $this->assertSame(100, $this->credito($this->correr($conjunta, '26213', '26213')));
        $this->assertNull($this->credito($this->correr($conjunta, '26214', '26214')));

        // Pub. 1040, p. 19, nota: sin hijos, 19.100 a 19.104, el crédito es $0 (sin crédito: la hoja se detiene en la línea 2).
        $sinHijos = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(30)]);
        $this->assertNull($this->credito($this->correr($sinHijos, '19103', '19103')));
    }

    public function test_si_el_ingreso_ajustado_pasa_el_fin_no_hay_credito_aunque_el_ingreso_del_trabajo_sea_bajo(): void
    {
        $unHijo = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(), $this->hijo(3)]);

        // Instrucciones del 1040, p. 40, Paso 1, pregunta 1: 11b no menor de 50.434 -> "You can't take the credit".
        $d = $this->correr($unHijo, '10000', '60000');

        $this->assertNull($this->credito($d));
        $this->assertStringContainsString('11b', (string) $d->linea('1040', '27a')?->motivo);
    }

    // ---- Hoja B: con Schedule C ----------------------------------------------------------------------------------

    public function test_hoja_b_con_schedule_se_suma_la_ganancia_menos_la_mitad_del_impuesto(): void
    {
        $unHijo = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(), $this->hijo(3)]);
        $lineas = [
            $this->calculada('sch-c', '31', '10000'),
            $this->calculada('sch-se', '3', '10000'),
            $this->calculada('sch-se', '4c', '9235'),
            $this->calculada('sch-se', '13', '706.48'),
        ];

        // Hoja B (p. 47), parte 1: línea 1e = 10.000 - 706,48 = 9.293,52; parte 4: 4b = 1e + 4a (0) = 9.293,52;
        // línea 7 = EIC Table(9.293,52) = p. 17, 9.250 a 9.300, 1 hijo = 3.154; línea 8 (11b) = 9.293,52 es igual: línea 11 = línea 7.
        $d = $this->correr($unHijo, '0', '9293.52', $lineas);

        $this->assertSame(315400, $this->credito($d));
    }

    public function test_hoja_b_sin_schedule_se_usa_la_ganancia_del_schedule_c_aunque_sea_perdida(): void
    {
        $sinHijos = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(30)]);
        // Schedule SE no se presenta (línea 4c menor de USD 400, aquí no aplica por la pérdida): hoja B, parte 2: 2c = Schedule C línea 31 = -2.000.
        $lineas = [
            $this->calculada('sch-c', '31', '-2000'),
            Linea::noAplica('sch-se', '4c', 'Sin ganancia'),
        ];

        // 4b = 0 + (-2.000) + 15.000 = 13.000 -> p. 18, 13.000 a 13.050, 0 hijos = 465; 11b = 13.000 igual -> 465.
        $this->assertSame(46500, $this->credito($this->correr($sinHijos, '15000', '13000', $lineas)));

        // Ganancia de 300 con línea 4c de 277,05 (< 400): parte 2, 2c = 300 -> p. 16, 300 a 350, 0 hijos = 25.
        $pequena = [$this->calculada('sch-c', '31', '300'), $this->calculada('sch-se', '4c', '277.05')];
        $this->assertSame(2500, $this->credito($this->correr($sinHijos, '0', '300', $pequena)));
    }

    public function test_hoja_b_sin_la_linea_4c_del_schedule_se_es_falta_dato(): void
    {
        $sinHijos = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(30)]);

        $d = $this->correr($sinHijos, '0', '5000', [$this->calculada('sch-c', '31', '5000')]);

        $this->assertSame(EstadoLinea::FaltaDato, $d->linea('1040', '27a')?->estado);
        $this->assertStringContainsString('4c del Schedule SE', (string) $d->linea('1040', '27a')?->motivo);
    }

    public function test_schedule_c_con_falta_dato_propaga_falta_dato(): void
    {
        $sinHijos = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(30)]);

        $d = $this->correr($sinHijos, '0', '5000', [Linea::faltaDato('sch-c', '31', 'Faltan los registros')]);

        $this->assertSame(EstadoLinea::FaltaDato, $d->linea('1040', '27a')?->estado);
        $this->assertStringContainsString('línea 31 del Schedule C', (string) $d->linea('1040', '27a')?->motivo);
    }

    // ---- Ingreso de inversión -----------------------------------------------------------------------------------

    public function test_con_documentos_de_inversion_fuera_del_piloto_es_no_soportada(): void
    {
        foreach (['1099-int:1', '1099-div:1a', '1099-b:1d', '1099-da:1', 'k-1:1'] as $clave) {
            $e = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(30)], datos: [$clave => new DatoEntrada(Dinero::deDolares('100'), OrigenTraza::Leido, 'd1')]);

            $d = $this->correr($e, '8475', '8475');

            $this->assertSame(EstadoLinea::NoSoportada, $d->linea('1040', '27a')?->estado, $clave);
            $this->assertStringContainsString('11.950', (string) $d->linea('1040', '27a')?->motivo);
            $this->assertSame('parcial', $d->estadoGeneral());
        }
    }

    // ---- SSN del contribuyente, del cónyuge y de los hijos -------------------------------------------------------

    public function test_sin_ssn_valido_del_contribuyente_o_del_conyuge_en_conjunta_no_hay_credito(): void
    {
        // Instrucciones del 1040, p. 40, Paso 1, pregunta 2: quien declara "y su cónyuge si es conjunta" con SSN válido para trabajar.
        $sinSsn = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(30, ssnValido: false)]);
        $conLeyenda = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(30, noValidoParaTrabajar: true)]);
        $conyugeSinSsn = $this->entradas(EstadoCivilIrs::Mfj, [$this->contribuyente(30), $this->conyuge(31, ssnValido: false)]);

        foreach ([$sinSsn, $conLeyenda, $conyugeSinSsn] as $e) {
            $d = $this->correr($e, '8475', '8475');
            $this->assertSame(EstadoLinea::NoAplica, $d->linea('1040', '27a')?->estado);
            $this->assertNull($this->credito($d));
        }
    }

    public function test_el_hijo_sin_ssn_valido_no_cuenta_para_la_tabla_pero_entra_al_schedule_eic(): void
    {
        // Instrucciones del 1040, p. 45: un hijo sin SSN válido no cuenta para el monto del crédito; se usa la columna de 0 hijos
        // (Pub. 1040, p. 17: 8.450 a 8.500 con 0 hijos = 649) y el hijo igual entra al Schedule EIC (p. 40).
        $e = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(30), $this->hijo(7, ssnValido: false)]);

        $d = $this->correr($e, '8475', '8475');

        $this->assertSame(64900, $this->credito($d));
        $this->assertSame('7', $d->linea('sch-eic', '1')?->valor);
    }

    public function test_hijo_con_ssn_de_solo_beneficios_no_valido_para_trabajar_no_cuenta(): void
    {
        // Instrucciones del 1040, p. 44: "Not Valid for Employment" impreso en la tarjeta -> no es SSN válido para el EIC.
        $e = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(30), $this->hijo(7, noValidoParaTrabajar: true)]);

        $this->assertSame(64900, $this->credito($this->correr($e, '8475', '8475')));
    }

    // ---- Hijo calificado -----------------------------------------------------------------------------------------

    public function test_hijo_de_19_anos_estudiante_cuenta_y_sin_estudiar_no(): void
    {
        // Instrucciones del 1040, p. 41, Paso 3: menor de 24 al cierre del año y estudiante a tiempo completo.
        $estudiante = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(45), $this->hijo(7, edad: 19, esEstudiante: true)]);
        $noEstudia = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(45), $this->hijo(7, edad: 19, esEstudiante: false)]);

        // Con un hijo: 2.455 -> 842 (p. 16). Sin hijo calificado: columna 0 hijos, 2.455 -> 189 (p. 16, 2.450 a 2.500).
        $this->assertSame(84200, $this->credito($this->correr($estudiante, '2455', '2455')));
        $this->assertSame(18900, $this->credito($this->correr($noEstudia, '2455', '2455')));
    }

    public function test_hijo_de_19_anos_sin_saber_si_estudia_o_esta_incapacitado_es_falta_dato(): void
    {
        $e = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(45), $this->hijo(7, edad: 19, esEstudiante: null, incapacitado: null)]);

        $d = $this->correr($e, '2455', '2455');

        $this->assertSame(EstadoLinea::FaltaDato, $d->linea('1040', '27a')?->estado);
        $motivo = (string) $d->linea('1040', '27a')?->motivo;
        $this->assertStringContainsString('C7', $motivo);
        $this->assertStringContainsString('C8', $motivo);
        $this->assertStringContainsString('#7', $motivo);
    }

    public function test_hijo_incapacitado_cuenta_a_cualquier_edad(): void
    {
        // Instrucciones del 1040, p. 41: "Any age and permanently and totally disabled".
        $e = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(70), $this->hijo(7, edad: 40, incapacitado: true)]);

        $this->assertSame(84200, $this->credito($this->correr($e, '2455', '2455')));
    }

    public function test_hijo_de_24_anos_o_mas_sin_incapacidad_no_cuenta_y_menor_de_19_si(): void
    {
        $de24 = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(50), $this->hijo(7, edad: 24, esEstudiante: true, incapacitado: false)]);
        $de18 = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(50), $this->hijo(7, edad: 18)]);

        $this->assertSame(18900, $this->credito($this->correr($de24, '2455', '2455')), 'columna de 0 hijos, p. 16');
        $this->assertSame(84200, $this->credito($this->correr($de18, '2455', '2455')));
    }

    public function test_el_hijo_tiene_que_ser_mas_joven_que_quien_declara(): void
    {
        // Instrucciones del 1040, p. 41: "younger than you (or your spouse if filing jointly)".
        $mayor = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(17), $this->hijo(7, edad: 18)]);

        // Sin hijo calificado y con 17 años, el contribuyente tampoco cumple la edad de 25 a 64.
        $d = $this->correr($mayor, '2455', '2455');
        $this->assertSame(EstadoLinea::NoAplica, $d->linea('1040', '27a')?->estado);
    }

    public function test_menos_de_siete_meses_en_casa_no_es_hijo_calificado(): void
    {
        // Instrucciones del 1040, p. 41: vivió con usted "more than half of 2025" (más de 6 meses).
        $seis = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(30), $this->hijo(7, meses: 6)]);
        $siete = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(30), $this->hijo(7, meses: 7)]);

        $this->assertSame(18900, $this->credito($this->correr($seis, '2455', '2455')), 'columna de 0 hijos, p. 16');
        $this->assertSame(84200, $this->credito($this->correr($siete, '2455', '2455')));
    }

    public function test_hijo_nacido_en_el_ano_con_menos_de_siete_meses_lo_revisa_el_preparador(): void
    {
        // Instrucciones del 1040, p. 43, "Exception to time lived with you": nacido en el año cuenta si el hogar fue el suyo más de la mitad del tiempo vivido.
        $e = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(30), $this->hijo(7, edad: 0, meses: 3)]);

        $d = $this->correr($e, '2455', '2455');

        $this->assertSame(EstadoLinea::FaltaDato, $d->linea('1040', '27a')?->estado);
    }

    public function test_parentesco_que_no_califica_no_cuenta_como_hijo(): void
    {
        $e = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(30), $this->hijo(7, parentesco: 'padre')]);

        $this->assertSame(18900, $this->credito($this->correr($e, '2455', '2455')));
    }

    public function test_hermano_y_nieto_si_cuentan_como_hijo_calificado(): void
    {
        // Instrucciones del 1040, p. 41: hermano, hermana... o descendiente de ellos (por ejemplo, un nieto).
        foreach (['hermano', 'nieto', 'hijastro', 'hijo_crianza'] as $parentesco) {
            $e = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(40), $this->hijo(7, parentesco: $parentesco)]);
            $this->assertSame(84200, $this->credito($this->correr($e, '2455', '2455')), $parentesco);
        }
    }

    public function test_si_otra_persona_tambien_puede_reclamar_al_hijo_lo_decide_el_preparador(): void
    {
        $e = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(30), $this->hijo(7, otroPuedeReclamar: true)]);

        $d = $this->correr($e, '2455', '2455');

        $this->assertSame(EstadoLinea::FaltaDato, $d->linea('1040', '27a')?->estado);
        $this->assertStringContainsString('C11', (string) $d->linea('1040', '27a')?->motivo);
    }

    public function test_pariente_calificado_y_no_dependiente_no_son_hijos_del_eic(): void
    {
        $pariente = $this->hijo(7, clasificacion: ClasificacionPersona::ParienteCalificado);
        $otro = $this->hijo(8, clasificacion: ClasificacionPersona::NoDependiente);
        $e = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(30), $pariente, $otro]);

        $this->assertSame(18900, $this->credito($this->correr($e, '2455', '2455')));
    }

    public function test_datos_opcionales_en_null_piden_el_dato_por_su_pregunta(): void
    {
        $sinParentesco = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(30), $this->hijo(7, parentesco: null)]);
        $sinMeses = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(30), $this->hijo(7, meses: null)]);
        $sinEdad = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(30), $this->hijo(7, edad: null)]);

        foreach ([[$sinParentesco, 'C3'], [$sinMeses, 'C4'], [$sinEdad, 'C2']] as [$e, $pregunta]) {
            $d = $this->correr($e, '2455', '2455');
            $this->assertSame(EstadoLinea::FaltaDato, $d->linea('1040', '27a')?->estado, $pregunta);
            $this->assertStringContainsString($pregunta, (string) $d->linea('1040', '27a')?->motivo);
            $this->assertSame(EstadoLinea::FaltaDato, $d->linea('sch-eic', 'credito')?->estado);
            $this->assertSame(EstadoLinea::FaltaDato, $d->linea('sch-eic', '1')?->estado);
        }
    }

    // ---- Sin hijo calificado -------------------------------------------------------------------------------------

    public function test_sin_hijos_la_edad_va_de_25_a_64_anos(): void
    {
        // Instrucciones del 1040, p. 41, Paso 4, pregunta 2: al menos 25 y menos de 65 al cierre de 2025.
        foreach ([25 => true, 64 => true, 65 => false, 30 => true] as $edad => $recibe) {
            $d = $this->correr($this->entradas(EstadoCivilIrs::Single, [$this->contribuyente($edad)]), '2455', '2455');
            // Pub. 1040, p. 16: 2.450 a 2.500 con 0 hijos = 189.
            $this->assertSame($recibe ? 18900 : null, $this->credito($d), "edad {$edad}");
        }

        $d = $this->correr($this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(20)]), '2455', '2455');
        $this->assertSame(EstadoLinea::NoAplica, $d->linea('1040', '27a')?->estado);
    }

    public function test_sin_hijos_con_24_anos_el_preparador_confirma_la_fecha_de_nacimiento(): void
    {
        // p. 41: "born after December 31, 1960, and before January 2, 2001" -> quien nació el 1 de enero de 2001 cuenta como 25.
        $d = $this->correr($this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(24)]), '2455', '2455');

        $this->assertSame(EstadoLinea::FaltaDato, $d->linea('1040', '27a')?->estado);
    }

    public function test_sin_hijos_en_conjunta_basta_que_uno_de_los_dos_tenga_de_25_a_64(): void
    {
        $e = $this->entradas(EstadoCivilIrs::Mfj, [$this->contribuyente(22), $this->conyuge(40)]);

        // Pub. 1040, p. 16: 2.450 a 2.500 con 0 hijos, conjunta = 189.
        $this->assertSame(18900, $this->credito($this->correr($e, '2455', '2455')));
    }

    public function test_sin_hijos_si_alguien_puede_reclamar_al_contribuyente_no_hay_credito_y_sin_respuesta_falta_dato(): void
    {
        $e = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(30)], respuestas: ['A5' => new RespuestaEntrada(true)]);
        $sinA5 = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(30)], respuestas: []);

        // Instrucciones del 1040, p. 42, Paso 4, pregunta 6: se puede reclamar como dependiente -> "You can't take the credit".
        $this->assertSame(EstadoLinea::NoAplica, $this->correr($e, '2455', '2455')->linea('1040', '27a')?->estado);
        $d = $this->correr($sinA5, '2455', '2455');
        $this->assertSame(EstadoLinea::FaltaDato, $d->linea('1040', '27a')?->estado);
        $this->assertStringContainsString('A5', (string) $d->linea('1040', '27a')?->motivo);
    }

    public function test_cabeza_de_familia_casado_sin_hijo_calificado_no_tiene_credito(): void
    {
        // p. 41, Paso 4, pregunta 1: "married taxpayer whose filing status is MFS or HOH" -> STOP.
        $e = $this->entradas(EstadoCivilIrs::Hoh, [$this->contribuyente(30)], respuestas: ['A5' => new RespuestaEntrada(false), 'B1' => new RespuestaEntrada('casado')]);
        $soltero = $this->entradas(EstadoCivilIrs::Hoh, [$this->contribuyente(30)], respuestas: ['A5' => new RespuestaEntrada(false), 'B1' => new RespuestaEntrada('soltero')]);

        $this->assertNull($this->credito($this->correr($e, '2455', '2455')));
        $this->assertSame(18900, $this->credito($this->correr($soltero, '2455', '2455')));
    }

    // ---- MFS -----------------------------------------------------------------------------------------------------

    public function test_mfs_sin_hijo_calificado_no_tiene_credito(): void
    {
        // Instrucciones del 1040, p. 41, Paso 4, pregunta 1 (verificado en el PDF de 2025).
        $e = $this->entradas(EstadoCivilIrs::Mfs, [$this->contribuyente(30)]);

        $d = $this->correr($e, '2455', '2455');

        $this->assertSame(EstadoLinea::NoAplica, $d->linea('1040', '27a')?->estado);
    }

    public function test_mfs_con_hijo_y_viviendo_aparte_los_ultimos_6_meses_toma_el_credito_con_la_columna_de_soltero(): void
    {
        // p. 44, "Special rule for separated spouses"; Pub. 1040, p. 16: columna con ★ "married filing separately and you qualify".
        $e = $this->entradas(EstadoCivilIrs::Mfs, [$this->contribuyente(), $this->hijo(3)], respuestas: ['A5' => new RespuestaEntrada(false), 'B3' => new RespuestaEntrada(true)]);

        $this->assertSame(84200, $this->credito($this->correr($e, '2455', '2455')));
    }

    public function test_mfs_con_hijo_sin_saber_si_vivio_aparte_lo_revisa_el_preparador(): void
    {
        $sinDato = $this->entradas(EstadoCivilIrs::Mfs, [$this->contribuyente(), $this->hijo(3)], respuestas: ['A5' => new RespuestaEntrada(false)]);
        $separado = $this->entradas(EstadoCivilIrs::Mfs, [$this->contribuyente(), $this->hijo(3)], respuestas: ['A5' => new RespuestaEntrada(false), 'B3' => new RespuestaEntrada(false), 'B1' => new RespuestaEntrada('separado')]);
        $viveJuntos = $this->entradas(EstadoCivilIrs::Mfs, [$this->contribuyente(), $this->hijo(3)], respuestas: ['A5' => new RespuestaEntrada(false), 'B3' => new RespuestaEntrada(false), 'B1' => new RespuestaEntrada('casado')]);

        foreach ([$sinDato, $separado] as $e) {
            $d = $this->correr($e, '2455', '2455');
            $this->assertSame(EstadoLinea::FaltaDato, $d->linea('1040', '27a')?->estado);
            $this->assertStringContainsString('Con MFS, este crédito lo revisa el preparador', (string) $d->linea('1040', '27a')?->motivo);
        }

        $this->assertSame(EstadoLinea::NoAplica, $this->correr($viveJuntos, '2455', '2455')->linea('1040', '27a')?->estado);
    }

    // ---- Faltan datos y parámetros -------------------------------------------------------------------------------

    public function test_sin_estado_civil_es_falta_dato(): void
    {
        $e = new EntradasDeclaracion(2025, null, [$this->contribuyente()]);

        $d = $this->correr($e, '2455', '2455');

        $this->assertSame(EstadoLinea::FaltaDato, $d->linea('1040', '27a')?->estado);
        $this->assertSame('Falta decidir el estado civil en Revisión.', $d->linea('1040', '27a')?->motivo);
    }

    public function test_sin_las_lineas_1z_u_11b_es_falta_dato_y_no_supone_cero(): void
    {
        $e = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(30)]);

        $sin1z = new Declaracion(2025, 't');
        $sin1z->poner($this->calculada('1040', '11b', '2455'));
        (new Eitc)->calcular($e, $sin1z);
        $this->assertSame(EstadoLinea::FaltaDato, $sin1z->linea('1040', '27a')?->estado);
        $this->assertStringContainsString('1z', (string) $sin1z->linea('1040', '27a')?->motivo);

        $con11bPendiente = new Declaracion(2025, 't');
        $con11bPendiente->poner($this->calculada('1040', '1z', '2455'));
        $con11bPendiente->poner(Linea::faltaDato('1040', '11b', 'x'));
        (new Eitc)->calcular($e, $con11bPendiente);
        $this->assertSame(EstadoLinea::FaltaDato, $con11bPendiente->linea('1040', '27a')?->estado);
    }

    public function test_sin_contribuyente_o_sin_conyuge_en_conjunta_es_falta_dato(): void
    {
        $this->assertSame(EstadoLinea::FaltaDato, $this->correr($this->entradas(EstadoCivilIrs::Single, []), '2455', '2455')->linea('1040', '27a')?->estado);
        $this->assertSame(EstadoLinea::FaltaDato, $this->correr($this->entradas(EstadoCivilIrs::Mfj, [$this->contribuyente()]), '2455', '2455')->linea('1040', '27a')?->estado);
    }

    public function test_un_parametro_pendiente_es_falta_dato_con_el_parametro_en_el_motivo(): void
    {
        ParametroFiscal::query()->where('tax_year', 2025)->where('categoria', 'credito_eic')->where('clave', 'por_hijos')->update(['valor' => null]);
        ParametrosFiscales::invalidate();

        $d = $this->correr($this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(30)]), '2455', '2455');

        $this->assertSame(EstadoLinea::FaltaDato, $d->linea('1040', '27a')?->estado);
        $this->assertStringContainsString('credito_eic.por_hijos', (string) $d->linea('1040', '27a')?->motivo);
        $this->assertStringContainsString('2025', (string) $d->linea('1040', '27a')?->motivo);
    }

    public function test_si_las_lineas_leidas_usan_datos_sin_validar_el_credito_lo_marca(): void
    {
        $e = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(), $this->hijo(3)]);
        $d = new Declaracion(2025, 't');
        $d->poner(Linea::calculada('1040', '1z', Dinero::deDolares('2455'), Traza::vacia(), ['dato:12']));
        $d->poner($this->calculada('1040', '11b', '2455'));

        (new Eitc)->calcular($e, $d);

        $this->assertTrue($d->linea('1040', '27a')?->usaSinValidar);
        $this->assertSame(['dato:12'], $d->linea('1040', '27a')?->datosSinValidar);
    }

    // ---- Salidas y traza -----------------------------------------------------------------------------------------

    public function test_el_schedule_eic_lista_los_ids_de_los_hijos_sin_nombres_ni_ssn(): void
    {
        $e = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(), $this->hijo(3), $this->hijo(4, ssnValido: false)]);

        $d = $this->correr($e, '2455', '2455');

        foreach (['1', '2', '3', '4', '5', '6'] as $linea) {
            $this->assertSame('3,4', $d->linea('sch-eic', $linea)?->valor, "línea {$linea}");
        }
        // 1 hijo con SSN válido cuenta para la tabla: 842.
        $this->assertSame(84200, $this->credito($d));
    }

    public function test_sin_hijos_o_sin_credito_las_lineas_1_a_6_no_aplican(): void
    {
        $d = $this->correr($this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(30)]), '2455', '2455');

        foreach (['1', '2', '3', '4', '5', '6'] as $linea) {
            $this->assertSame(EstadoLinea::NoAplica, $d->linea('sch-eic', $linea)?->estado);
        }
    }

    public function test_la_traza_trae_las_entradas_con_su_origen_el_parametro_y_el_resultado_sin_datos_sensibles(): void
    {
        $e = $this->entradas(EstadoCivilIrs::Single, [$this->contribuyente(), $this->hijo(3)]);

        $d = $this->correr($e, '20025', '40025');
        $pasos = $d->linea('1040', '27a')?->traza->pasos ?? [];
        $referencias = array_map(fn ($p) => $p->referencia, $pasos);

        $this->assertContains('1040:1z', $referencias);
        $this->assertContains('1040:11b', $referencias);
        $this->assertContains('credito_eic.por_hijos', $referencias);
        $this->assertSame(166300, end($pasos)->valor->centavos());
        $this->assertSame($d->linea('1040', '27a')?->traza, $d->linea('sch-eic', 'credito')?->traza);
    }

    public function test_el_credito_nunca_es_negativo_ni_sube_mas_alla_del_maximo(): void
    {
        $maximo = [0 => 649, 1 => 4328, 2 => 7152, 3 => 8046];

        foreach ($maximo as $hijos => $tope) {
            $personas = [$this->contribuyente(30)];
            for ($i = 0; $i < $hijos; $i++) {
                $personas[] = $this->hijo(10 + $i);
            }

            for ($ingreso = 0; $ingreso <= 70000; $ingreso += 733) {
                $d = $this->correr($this->entradas(EstadoCivilIrs::Single, $personas), (string) $ingreso, (string) $ingreso);
                $credito = $this->credito($d) ?? 0;
                $this->assertGreaterThanOrEqual(0, $credito);
                $this->assertLessThanOrEqual($tope * 100, $credito);
            }
        }
    }

    public function test_la_calculadora_declara_su_formulario(): void
    {
        $this->assertSame('sch-eic', (new Eitc)->formulario());
    }

    // ---- Armado -------------------------------------------------------------------------------------------------

    /**
     * @param  list<PersonaEntrada>  $personas
     * @param  array<string, DatoEntrada>  $datos
     * @param  array<string, RespuestaEntrada>|null  $respuestas  por defecto, nadie puede reclamar al contribuyente (A5 = no)
     */
    private function entradas(EstadoCivilIrs $estado, array $personas, array $datos = [], ?array $respuestas = null): EntradasDeclaracion
    {
        return new EntradasDeclaracion(2025, $estado, $personas, $datos, [], $respuestas ?? ['A5' => new RespuestaEntrada(false)]);
    }

    /** @param list<Linea> $otras */
    private function correr(EntradasDeclaracion $e, string $linea1z, string $linea11b, array $otras = []): Declaracion
    {
        $d = new Declaracion(2025, 'test');
        $d->poner($this->calculada('1040', '1z', $linea1z));
        $d->poner($this->calculada('1040', '11b', $linea11b));

        foreach ($otras as $otra) {
            $d->poner($otra);
        }

        (new Eitc)->calcular($e, $d);

        return $d;
    }

    private function calculada(string $formulario, string $linea, string $dolares): Linea
    {
        return Linea::calculada($formulario, $linea, Dinero::deDolares($dolares), Traza::vacia());
    }

    private function credito(Declaracion $d): ?int
    {
        $linea = $d->linea('1040', '27a');

        return $linea?->valor instanceof Dinero ? $linea->valor->centavos() : null;
    }

    private function contribuyente(int $edad = 35, bool $ssnValido = true, bool $noValidoParaTrabajar = false): PersonaEntrada
    {
        return new PersonaEntrada(1, RolPersona::Contribuyente, ClasificacionPersona::NoDependiente, $edad, $ssnValido, $noValidoParaTrabajar);
    }

    private function conyuge(int $edad = 35, bool $ssnValido = true): PersonaEntrada
    {
        return new PersonaEntrada(2, RolPersona::Conyuge, ClasificacionPersona::NoDependiente, $edad, $ssnValido, false);
    }

    private function hijo(
        int $id,
        ?int $edad = 8,
        bool $ssnValido = true,
        bool $noValidoParaTrabajar = false,
        ?bool $esEstudiante = false,
        ?bool $incapacitado = false,
        ?int $meses = 12,
        ?string $parentesco = 'hijo',
        ?bool $otroPuedeReclamar = false,
        ClasificacionPersona $clasificacion = ClasificacionPersona::HijoCalificado,
    ): PersonaEntrada {
        return new PersonaEntrada($id, RolPersona::Dependiente, $clasificacion, $edad, $ssnValido, $noValidoParaTrabajar, $esEstudiante, $incapacitado, $meses, $parentesco, $otroPuedeReclamar);
    }
}
