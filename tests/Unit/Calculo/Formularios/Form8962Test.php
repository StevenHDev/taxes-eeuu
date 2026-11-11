<?php

namespace Tests\Unit\Calculo\Formularios;

use App\Enums\Expediente\ClasificacionPersona;
use App\Enums\Expediente\EstadoCivilIrs;
use App\Models\ParametroFiscal;
use App\Services\Calculo\Formularios\Form8962;
use App\Services\Calculo\Formularios\Form8962\EstadoResidencia;
use App\Services\Calculo\Nucleo\DatoEntrada;
use App\Services\Calculo\Nucleo\Declaracion;
use App\Services\Calculo\Nucleo\Dinero;
use App\Services\Calculo\Nucleo\DocumentoEntrada;
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
 * 6.4h: Form8962 (crédito de la prima del Marketplace, 2025).
 *
 * Las cifras esperadas se arman a mano con las instrucciones del Form 8962 de 2025 (i8962): Tablas 1-1 a
 * 1-3 (p. 8 y 9), Worksheet 2 (p. 9), Tabla 2 (p. 11), líneas 8a y 8b (p. 12), líneas 10 a 25 (p. 14 a
 * 18) y Tabla 5 (p. 18). Ninguna sale del código que se prueba. Las primas de USD 800 y SLCSP de USD 850 al
 * mes son las del ejemplo 2 de la línea 10 (Mike y Susan, p. 15).
 */
class Form8962Test extends TestCase
{
    use RefreshDatabase;

    private const MIAMI = '100 Main St, Miami, FL 33101';

    protected function setUp(): void
    {
        parent::setUp();
        // Los motivos se comparan en español; el idioma por defecto de la aplicación es inglés.
        app()->setLocale('es');
    }

    private function dato(string $dolares, bool $validado = true, ?string $referencia = null): DatoEntrada
    {
        return new DatoEntrada(Dinero::deDolares($dolares), OrigenTraza::Leido, $referencia, $validado);
    }

    /**
     * Un 1095-A: por mes [A, B, C] (prima, SLCSP, adelanto) y, si se da, la línea 33.
     *
     * @param  array<int, array{0: string, 1: string, 2: string}>  $meses
     * @param  array{0: string, 1: string, 2: string}|null  $anual
     * @param  array<string, DatoEntrada>  $extra
     */
    private function poliza(int $item, array $meses, ?array $anual = null, array $extra = []): DocumentoEntrada
    {
        $casillas = $extra;

        foreach ($meses as $mes => [$a, $b, $c]) {
            $fila = 20 + $mes;
            $casillas["{$fila}-A"] = $this->dato($a);
            $casillas["{$fila}-B"] = $this->dato($b);
            $casillas["{$fila}-C"] = $this->dato($c);
        }

        if ($anual !== null) {
            [$a, $b, $c] = $anual;
            $casillas['33-A'] = $this->dato($a);
            $casillas['33-B'] = $this->dato($b);
            $casillas['33-C'] = $this->dato($c);
        }

        return new DocumentoEntrada('1095-a', $item, 1, $casillas);
    }

    /** Los 12 meses iguales. */
    private function todoElAnio(int $item, string $a, string $b, string $c, bool $conAnual = true): DocumentoEntrada
    {
        $meses = array_fill(1, 12, [$a, $b, $c]);
        $anual = $conAnual ? [$this->por12($a), $this->por12($b), $this->por12($c)] : null;

        return $this->poliza($item, $meses, $anual);
    }

    private function por12(string $dolares): string
    {
        return (string) ((int) $dolares * 12);
    }

    private function persona(int $id, RolPersona $rol, ClasificacionPersona $clasificacion = ClasificacionPersona::NoDependiente): PersonaEntrada
    {
        return new PersonaEntrada($id, $rol, $clasificacion, 30, true, false);
    }

    /**
     * @param  list<DocumentoEntrada>  $polizas
     * @param  list<PersonaEntrada>  $dependientes
     * @param  array<string, RespuestaEntrada>  $respuestas
     * @param  array<string, list<DocumentoEntrada>>  $otros
     */
    private function correr(?EstadoCivilIrs $estado, string $agi, array $polizas, array $dependientes = [], array $respuestas = [], array $otros = [], array $datos = []): Declaracion
    {
        $dec = new Declaracion(2025, 'prueba');
        $dec->poner(Linea::calculada('1040', '11a', Dinero::deDolares($agi), Traza::vacia()));

        $personas = [$this->persona(1, RolPersona::Contribuyente)];

        if ($estado === EstadoCivilIrs::Mfj) {
            $personas[] = $this->persona(2, RolPersona::Conyuge);
        }

        $e = new EntradasDeclaracion(
            2025,
            $estado,
            personas: [...$personas, ...$dependientes],
            datos: $datos,
            respuestas: $respuestas + ['A2' => new RespuestaEntrada(self::MIAMI)],
            documentos: ['1095-a' => $polizas] + $otros,
        );

        (new Form8962)->calcular($e, $dec);

        return $dec;
    }

    private function valor(Declaracion $dec, string $linea): string
    {
        $l = $dec->linea('f8962', $linea);
        $this->assertNotNull($l, "f8962:{$linea} no se escribió");
        $this->assertSame(EstadoLinea::Calculada, $l->estado, "f8962:{$linea}: {$l->motivo}");

        return match (true) {
            $l->valor instanceof Dinero => $l->valor->texto(),
            is_bool($l->valor) => $l->valor ? 'sí' : 'no',
            default => (string) $l->valor,
        };
    }

    private function estado(Declaracion $dec, string $linea): EstadoLinea
    {
        $l = $dec->linea('f8962', $linea);
        $this->assertNotNull($l, "f8962:{$linea} no se escribió");

        return $l->estado;
    }

    /** @param array<string, string> $esperado línea => texto */
    private function assertLineas(Declaracion $dec, array $esperado): void
    {
        foreach ($esperado as $linea => $texto) {
            $this->assertSame($texto, $this->valor($dec, (string) $linea), "f8962:{$linea}");
        }
    }

    public function test_sin_1095_a_las_lineas_26_y_29_no_aplican_y_no_se_llena_el_formulario(): void
    {
        $dec = $this->correr(EstadoCivilIrs::Single, '30000', []);

        $this->assertSame(EstadoLinea::NoAplica, $this->estado($dec, '26'));
        $this->assertSame(EstadoLinea::NoAplica, $this->estado($dec, '29'));
        $this->assertNull($dec->linea('f8962', '1'));
    }

    public function test_familia_de_1_todo_el_anio_igual_da_credito_neto_por_la_linea_11(): void
    {
        // 30.000 / 15.060 (Tabla 1-1, 1 persona) = 1,992 → 199 (Worksheet 2); Tabla 2: 199 → 0,0196;
        // 8a = 30.000 × 0,0196 = 588; 8b = 588 / 12 = 49. Línea 11: (b) 10.200 − (c) 588 = 9.612;
        // (e) = menor entre 9.600 y 9.612 = 9.600; 26 = 9.600 − 9.000 = 600.
        $dec = $this->correr(EstadoCivilIrs::Single, '30000', [$this->todoElAnio(7, '800', '850', '750')]);

        $this->assertLineas($dec, [
            '1' => '1', '2a' => '30.000,00', '3' => '30.000,00', '4' => '15.060,00', '5' => '199', '7' => '0.0196',
            '8a' => '588,00', '8b' => '49,00', '10' => 'sí',
            '11a' => '9.600,00', '11b' => '10.200,00', '11c' => '588,00', '11d' => '9.612,00', '11e' => '9.600,00', '11f' => '9.000,00',
            '24' => '9.600,00', '25' => '9.000,00', '26' => '600,00',
        ]);
        $this->assertSame(EstadoLinea::NoAplica, $this->estado($dec, '2b'));
        $this->assertSame(EstadoLinea::NoAplica, $this->estado($dec, '27'));
        $this->assertSame(EstadoLinea::NoAplica, $this->estado($dec, '29'));
        $this->assertNull($dec->linea('f8962', '12a'));
        $this->assertFalse($dec->linea('f8962', '9')->valor);
    }

    public function test_adelanto_de_mas_se_devuelve_con_el_tope_de_la_tabla_5(): void
    {
        // 60.000 / 15.060 = 3,984 → 398; Tabla 2: 0,0845; 8a = 5.070; (d) = 10.200 − 5.070 = 5.130;
        // (e) = menor entre 9.600 y 5.130 = 5.130; 27 = 9.000 − 5.130 = 3.870; Tabla 5 (300 a 399, Single) = 1.625.
        $dec = $this->correr(EstadoCivilIrs::Single, '60000', [$this->todoElAnio(7, '800', '850', '750')]);

        $this->assertLineas($dec, ['5' => '398', '7' => '0.0845', '8a' => '5.070,00', '24' => '5.130,00', '27' => '3.870,00', '28' => '1.625,00', '29' => '1.625,00']);
        $this->assertSame(EstadoLinea::NoAplica, $this->estado($dec, '26'));
    }

    public function test_adelanto_de_mas_por_debajo_del_tope_se_devuelve_completo(): void
    {
        // HOH ("any other filing status"): 60.000 → 398 → tope 3.250; 27 = 3.870 > 3.250 → 29 = 3.250.
        $dec = $this->correr(EstadoCivilIrs::Hoh, '60000', [$this->todoElAnio(7, '800', '850', '750')]);
        $this->assertLineas($dec, ['27' => '3.870,00', '28' => '3.250,00', '29' => '3.250,00']);

        // Single con 27 menor que el tope: 40.000 / 15.060 = 2,656 → 265 → 0,0460 → 8a = 1.840; (d) = 8.360;
        // (e) = 8.360; adelanto 12 × 750 = 9.000 → 27 = 640; Tabla 5 (200 a 299, Single) = 975 → 29 = 640.
        $dec = $this->correr(EstadoCivilIrs::Single, '40000', [$this->todoElAnio(7, '800', '850', '750')]);
        $this->assertLineas($dec, ['5' => '265', '7' => '0.0460', '8a' => '1.840,00', '24' => '8.360,00', '27' => '640,00', '28' => '975,00', '29' => '640,00']);
    }

    public function test_por_encima_del_400_por_ciento_la_linea_5_es_401_y_no_hay_tope(): void
    {
        // 70.000 > 4 × 15.060 = 60.240 → 401 (Worksheet 2); Tabla 2: 400 o más → 0,0850; 8a = 5.950;
        // (e) = 10.200 − 5.950 = 4.250; 27 = 9.000 − 4.250 = 4.750; Tabla 5: 400 o más → 28 en blanco, 29 = 27.
        $dec = $this->correr(EstadoCivilIrs::Single, '70000', [$this->todoElAnio(7, '800', '850', '750')]);

        $this->assertLineas($dec, ['5' => '401', '7' => '0.0850', '8a' => '5.950,00', '24' => '4.250,00', '27' => '4.750,00', '29' => '4.750,00']);
        $this->assertSame(EstadoLinea::NoAplica, $this->estado($dec, '28'));
    }

    public function test_justo_en_el_400_por_ciento_la_linea_5_es_400(): void
    {
        // 60.240 = 4 × 15.060: no es "más" del 400 %, así que 60.240 / 15.060 = 4,00 → 400 → 0,0850.
        $dec = $this->correr(EstadoCivilIrs::Single, '60240', [$this->todoElAnio(7, '800', '850', '750')]);

        $this->assertLineas($dec, ['5' => '400', '7' => '0.0850']);
    }

    public function test_familia_de_2_de_4_y_de_10_usan_la_tabla_1_1(): void
    {
        $hijo = fn (int $id): PersonaEntrada => $this->persona($id, RolPersona::Dependiente, ClasificacionPersona::HijoCalificado);
        $poliza = [$this->todoElAnio(7, '800', '850', '700')];

        // MFJ: 40.000 / 20.440 = 1,956 → 195 → 0,0180; 8a = 720; (d) = 9.480; (e) = 9.480; 26 = 9.480 − 8.400 = 1.080.
        $dec = $this->correr(EstadoCivilIrs::Mfj, '40000', $poliza);
        $this->assertLineas($dec, ['1' => '2', '4' => '20.440,00', '5' => '195', '7' => '0.0180', '8a' => '720,00', '11e' => '9.480,00', '26' => '1.080,00']);

        // MFJ + 2 hijos: 50.000 / 31.200 = 1,602 → 160 → 0,0040; 8a = 200; 8b = 200 / 12 = 16,67 → 17.
        $dec = $this->correr(EstadoCivilIrs::Mfj, '50000', $poliza, [$hijo(3), $hijo(4)]);
        $this->assertLineas($dec, ['1' => '4', '4' => '31.200,00', '5' => '160', '7' => '0.0040', '8a' => '200,00', '8b' => '17,00']);

        // MFJ + 8 dependientes = 10: 52.720 + 2 × 5.380 = 63.480 (nota de la Tabla 1-1).
        $dec = $this->correr(EstadoCivilIrs::Mfj, '50000', $poliza, array_map($hijo, range(3, 10)));
        $this->assertLineas($dec, ['1' => '10', '4' => '63.480,00']);
    }

    public function test_un_no_dependiente_no_cuenta_en_la_familia(): void
    {
        $otro = $this->persona(3, RolPersona::Dependiente, ClasificacionPersona::NoDependiente);
        $dec = $this->correr(EstadoCivilIrs::Single, '30000', [$this->todoElAnio(7, '800', '850', '750')], [$otro]);

        $this->assertSame('1', $this->valor($dec, '1'));
    }

    public function test_alaska_y_hawai_usan_sus_tablas_segun_la_direccion(): void
    {
        $poliza = [$this->todoElAnio(7, '800', '850', '750')];

        $dec = $this->correr(EstadoCivilIrs::Single, '30000', $poliza, respuestas: ['A2' => new RespuestaEntrada('PO Box 12, Anchorage, AK 99501')]);
        $this->assertSame('18.810,00', $this->valor($dec, '4')); // Tabla 1-2, 1 persona

        $dec = $this->correr(EstadoCivilIrs::Single, '30000', $poliza, respuestas: ['A2' => new RespuestaEntrada('1 Kalakaua Ave, Honolulu, Hawaii 96815')]);
        $this->assertSame('17.310,00', $this->valor($dec, '4')); // Tabla 1-3, 1 persona
    }

    public function test_el_estado_se_reconoce_solo_con_un_estado_y_su_codigo_postal(): void
    {
        $this->assertSame('FL', EstadoResidencia::estadoDesdeDireccion('100 Main St, Miami, FL 33101'));
        $this->assertSame('WV', EstadoResidencia::estadoDesdeDireccion('5 Elm St, Charleston, West Virginia 25301'));
        $this->assertSame('MO', EstadoResidencia::estadoDesdeDireccion('1 Kansas Ave, Kansas City, MO 64101-1234'));
        $this->assertNull(EstadoResidencia::estadoDesdeDireccion('Miami'));
        $this->assertNull(EstadoResidencia::estadoDesdeDireccion('San Juan, PR 00901'));
        $this->assertNull(EstadoResidencia::estadoDesdeDireccion('Miami, FL 33101 / Anchorage, AK 99501'));
        $this->assertNull(EstadoResidencia::estadoDesdeDireccion(null));
    }

    public function test_sin_estado_reconocible_la_linea_4_y_lo_que_depende_salen_falta_dato(): void
    {
        $dec = $this->correr(EstadoCivilIrs::Single, '60000', [$this->todoElAnio(7, '800', '850', '750')], respuestas: ['A2' => new RespuestaEntrada('Calle 5, Miami')]);

        $this->assertSame(EstadoLinea::FaltaDato, $this->estado($dec, '4'));
        $this->assertStringContainsString('Falta el estado de residencia de 2025', $dec->linea('f8962', '4')->motivo);

        foreach (['5', '7', '8a', '8b', '24', '26', '27', '28', '29'] as $linea) {
            $this->assertSame(EstadoLinea::FaltaDato, $this->estado($dec, $linea), "f8962:{$linea}");
        }

        $this->assertSame('9.000,00', $this->valor($dec, '25'));
    }

    public function test_por_debajo_del_100_por_ciento_lo_confirma_el_preparador(): void
    {
        // 14.000 / 15.060 = 0,929 → 92: por debajo de 100 solo hay crédito con una excepción (i8962 p. 9 y 10).
        $dec = $this->correr(EstadoCivilIrs::Single, '14000', [$this->todoElAnio(7, '800', '850', '750')]);

        $this->assertSame('92', $this->valor($dec, '5'));
        $this->assertSame(EstadoLinea::FaltaDato, $this->estado($dec, '7'));
        $this->assertStringContainsString('menos del 100 %', $dec->linea('f8962', '7')->motivo);
        $this->assertSame(EstadoLinea::FaltaDato, $this->estado($dec, '8a'));
        $this->assertSame(EstadoLinea::FaltaDato, $this->estado($dec, '24'));
        $this->assertSame(EstadoLinea::FaltaDato, $this->estado($dec, '29'));
    }

    public function test_meses_distintos_van_por_las_lineas_12_a_23(): void
    {
        // Enero a junio: prima 500, SLCSP 600, adelanto 400. 8b = 49 (ver el caso de 30.000); cada mes:
        // (d) = 600 − 49 = 551; (e) = menor entre 500 y 551 = 500. 24 = 6 × 500 = 3.000; 25 = 2.400; 26 = 600.
        $poliza = $this->poliza(7, array_fill(1, 6, ['500', '600', '400']));
        $dec = $this->correr(EstadoCivilIrs::Single, '30000', [$poliza]);

        $this->assertSame('no', $this->valor($dec, '10'));
        $this->assertNull($dec->linea('f8962', '11a'));
        $this->assertLineas($dec, ['12a' => '500,00', '12b' => '600,00', '12c' => '49,00', '12d' => '551,00', '12e' => '500,00', '12f' => '400,00', '17e' => '500,00']);
        $this->assertNull($dec->linea('f8962', '18a'));
        $this->assertLineas($dec, ['24' => '3.000,00', '25' => '2.400,00', '26' => '600,00']);
    }

    public function test_una_prima_que_cambia_en_el_anio_obliga_a_ir_mes_por_mes(): void
    {
        $meses = array_fill(1, 12, ['800', '850', '750']);
        $meses[12] = ['900', '850', '750'];
        $dec = $this->correr(EstadoCivilIrs::Single, '30000', [$this->poliza(7, $meses)]);

        $this->assertSame('no', $this->valor($dec, '10'));
        // (e) de enero a noviembre = 800, diciembre = 900 (850 − 49 = 801 → menor 801). 11 × 800 + 801 = 9.601.
        $this->assertLineas($dec, ['23e' => '801,00', '24' => '9.601,00']);
    }

    public function test_dos_polizas_con_el_mismo_slcsp_suman_primas_y_adelantos_y_toman_un_solo_slcsp(): void
    {
        // (a) = 3.600 + 4.800 = 8.400; (b) = 10.200 de un solo 1095-A; (f) = 2.400 + 3.600 = 6.000.
        // (c) = 588; (d) = 9.612; (e) = 8.400; 26 = 8.400 − 6.000 = 2.400.
        $dec = $this->correr(EstadoCivilIrs::Single, '30000', [
            $this->todoElAnio(7, '300', '850', '200'),
            $this->todoElAnio(8, '400', '850', '300'),
        ]);

        $this->assertLineas($dec, ['11a' => '8.400,00', '11b' => '10.200,00', '11e' => '8.400,00', '11f' => '6.000,00', '26' => '2.400,00']);
    }

    public function test_dos_polizas_con_distinto_slcsp_no_se_soportan(): void
    {
        $dec = $this->correr(EstadoCivilIrs::Single, '30000', [
            $this->todoElAnio(7, '300', '850', '200'),
            $this->todoElAnio(8, '400', '700', '300'),
        ]);

        $this->assertSame(EstadoLinea::NoSoportada, $this->estado($dec, '24'));
        $this->assertSame(EstadoLinea::NoSoportada, $this->estado($dec, '26'));
        $this->assertSame(EstadoLinea::NoSoportada, $this->estado($dec, '29'));
    }

    public function test_sin_la_linea_33_se_suman_los_meses(): void
    {
        $dec = $this->correr(EstadoCivilIrs::Single, '30000', [$this->todoElAnio(7, '800', '850', '750', conAnual: false)]);

        $this->assertLineas($dec, ['11a' => '9.600,00', '11b' => '10.200,00', '11f' => '9.000,00', '26' => '600,00']);
    }

    public function test_un_mes_sin_slcsp_o_con_prima_en_cero_sale_falta_dato(): void
    {
        $meses = array_fill(1, 12, ['800', '850', '750']);
        $meses[3] = ['800', '0', '750'];
        $dec = $this->correr(EstadoCivilIrs::Single, '30000', [$this->poliza(7, $meses)]);
        $this->assertSame(EstadoLinea::FaltaDato, $this->estado($dec, '24'));
        $this->assertStringContainsString('no trae el SLCSP de marzo', $dec->linea('f8962', '24')->motivo);
        $this->assertSame(EstadoLinea::FaltaDato, $this->estado($dec, '26'));

        $meses[3] = ['0', '850', '750'];
        $dec = $this->correr(EstadoCivilIrs::Single, '30000', [$this->poliza(7, $meses)]);
        $this->assertStringContainsString('prima de marzo en cero', $dec->linea('f8962', '24')->motivo);
    }

    public function test_mfs_sin_excepcion_no_tiene_credito_y_devuelve_el_adelanto_con_tope(): void
    {
        // i8962 p. 7, 15 y 17: líneas 1 a 5, solo la columna (f), 24 = 0, 25 y 27 = todo el adelanto.
        // 40.000 / 15.060 = 2,656 → 265; Tabla 5 (200 a 299, "any other filing status") = 1.950.
        $dec = $this->correr(EstadoCivilIrs::Mfs, '40000', [$this->todoElAnio(7, '800', '850', '200')]);

        $this->assertLineas($dec, ['1' => '1', '5' => '265', '11f' => '2.400,00', '24' => '0,00', '25' => '2.400,00', '27' => '2.400,00', '28' => '1.950,00', '29' => '1.950,00']);
        $this->assertSame(EstadoLinea::NoAplica, $this->estado($dec, '7'));
        $this->assertSame(EstadoLinea::NoAplica, $this->estado($dec, '8a'));
        $this->assertNull($dec->linea('f8962', '11a'));
    }

    public function test_mfs_mes_por_mes_solo_escribe_la_columna_f(): void
    {
        $dec = $this->correr(EstadoCivilIrs::Mfs, '40000', [$this->poliza(7, array_fill(1, 6, ['500', '600', '400']))]);

        $this->assertLineas($dec, ['12f' => '400,00', '24' => '0,00', '25' => '2.400,00', '27' => '2.400,00', '29' => '1.950,00']);
        $this->assertNull($dec->linea('f8962', '12a'));
    }

    public function test_sin_estado_civil_todo_sale_falta_dato(): void
    {
        $dec = $this->correr(null, '30000', [$this->todoElAnio(7, '800', '850', '750')]);

        $this->assertSame(EstadoLinea::FaltaDato, $this->estado($dec, '1'));
        $this->assertSame('Falta decidir el estado civil en Revisión.', $dec->linea('f8962', '29')->motivo);
    }

    public function test_si_otro_puede_reclamarte_o_te_casaste_en_el_anio_no_se_soporta(): void
    {
        $poliza = [$this->todoElAnio(7, '800', '850', '750')];

        $dec = $this->correr(EstadoCivilIrs::Single, '30000', $poliza, respuestas: ['A5' => new RespuestaEntrada('si')]);
        $this->assertSame(EstadoLinea::NoSoportada, $this->estado($dec, '26'));
        $this->assertStringContainsString('(A5)', $dec->linea('f8962', '26')->motivo);

        $dec = $this->correr(EstadoCivilIrs::Mfj, '30000', $poliza, respuestas: ['B2' => new RespuestaEntrada('si')]);
        $this->assertSame(EstadoLinea::NoSoportada, $this->estado($dec, '29'));
        $this->assertStringContainsString('(B2)', $dec->linea('f8962', '29')->motivo);
    }

    public function test_los_totales_sin_los_1095_a_por_separado_salen_falta_dato(): void
    {
        $dec = $this->correr(EstadoCivilIrs::Single, '30000', [], datos: ['1095-a:33-C' => $this->dato('9000')]);

        $this->assertSame(EstadoLinea::FaltaDato, $this->estado($dec, '29'));
        $this->assertStringContainsString('Faltan los 1095-A por separado', $dec->linea('f8962', '29')->motivo);
    }

    public function test_un_dependiente_con_ingresos_deja_la_2b_no_soportada(): void
    {
        $hijo = $this->persona(3, RolPersona::Dependiente, ClasificacionPersona::HijoCalificado);
        $w2 = new DocumentoEntrada('w2', 21, 3, ['1' => $this->dato('5000')]);

        $dec = $this->correr(EstadoCivilIrs::Single, '30000', [$this->todoElAnio(7, '800', '850', '750')], [$hijo], otros: ['w2' => [$w2]]);

        $this->assertSame(EstadoLinea::NoSoportada, $this->estado($dec, '2b'));
        $this->assertSame(EstadoLinea::NoSoportada, $this->estado($dec, '3'));
        $this->assertSame(EstadoLinea::NoSoportada, $this->estado($dec, '26'));

        // El W-2 del contribuyente no cuenta: la 2b sale en cero.
        $w2 = new DocumentoEntrada('w2', 21, 1, ['1' => $this->dato('5000')]);
        $dec = $this->correr(EstadoCivilIrs::Single, '30000', [$this->todoElAnio(7, '800', '850', '750')], [$hijo], otros: ['w2' => [$w2]]);
        $this->assertSame('0,00', $this->valor($dec, '2b'));
    }

    public function test_interes_exento_o_seguro_social_dejan_el_magi_no_soportado(): void
    {
        $dec = $this->correr(EstadoCivilIrs::Single, '30000', [$this->todoElAnio(7, '800', '850', '750')], datos: ['1099-int:1' => $this->dato('100')]);

        $this->assertSame(EstadoLinea::NoSoportada, $this->estado($dec, '2a'));
        $this->assertSame(EstadoLinea::NoSoportada, $this->estado($dec, '29'));
    }

    public function test_sin_la_linea_11a_del_1040_sale_falta_dato(): void
    {
        $dec = new Declaracion(2025, 'prueba');
        $e = new EntradasDeclaracion(2025, EstadoCivilIrs::Single, respuestas: ['A2' => new RespuestaEntrada(self::MIAMI)], documentos: ['1095-a' => [$this->todoElAnio(7, '800', '850', '750')]]);
        (new Form8962)->calcular($e, $dec);

        $this->assertSame(EstadoLinea::FaltaDato, $dec->linea('f8962', '2a')->estado);
        $this->assertSame(EstadoLinea::FaltaDato, $dec->linea('f8962', '26')->estado);
    }

    public function test_un_dato_sin_validar_marca_las_lineas_que_lo_usan(): void
    {
        $meses = array_fill(1, 12, ['800', '850', '750']);
        $poliza = $this->poliza(7, $meses, ['9600', '10200', '9000']);
        $casillas = $poliza->casillas;
        $casillas['33-C'] = $this->dato('9000', validado: false, referencia: 'dato-33');
        $poliza = new DocumentoEntrada('1095-a', 7, 1, $casillas);

        $dec = $this->correr(EstadoCivilIrs::Single, '30000', [$poliza]);

        $this->assertTrue($dec->linea('f8962', '25')->usaSinValidar);
        $this->assertContains('dato-33', $dec->linea('f8962', '26')->datosSinValidar);
    }

    public function test_la_traza_no_lleva_el_numero_de_poliza_ni_datos_de_las_personas_cubiertas(): void
    {
        $sensibles = [
            '2' => new DatoEntrada('POL-998877', OrigenTraza::Leido),
            '4' => new DatoEntrada('Persona Ficticia', OrigenTraza::Leido),
            '16-A' => new DatoEntrada('Persona Ficticia', OrigenTraza::Leido),
            '16-B' => new DatoEntrada('900-12-3456', OrigenTraza::Leido),
        ];
        $poliza = $this->poliza(7, array_fill(1, 12, ['800', '850', '750']), ['9600', '10200', '9000'], $sensibles);

        $dec = $this->correr(EstadoCivilIrs::Single, '60000', [$poliza]);

        foreach ($dec->lineas('f8962') as $linea) {
            foreach ($linea->traza->pasos as $paso) {
                $texto = $paso->texto.' '.($paso->referencia ?? '').' '.(is_string($paso->valor) ? $paso->valor : '');

                $this->assertStringNotContainsString('POL-998877', $texto);
                $this->assertStringNotContainsString('Ficticia', $texto);
                $this->assertStringNotContainsString('900-12-3456', $texto);
                $this->assertStringNotContainsString('Miami', $texto);
            }
        }

        $referencias = array_filter(array_map(fn ($p) => $p->referencia, $dec->linea('f8962', '4')->traza->pasos));
        $this->assertContains('A2', $referencias);
        $this->assertContains('credito_prima_salud.linea_pobreza_48_estados', $referencias);
    }

    public function test_un_parametro_pendiente_deja_la_linea_en_falta_dato_con_su_nombre(): void
    {
        ParametroFiscal::query()->where('tax_year', 2025)->where('categoria', 'credito_prima_salud')->where('clave', 'tabla_cifra_aplicable')->delete();
        ParametrosFiscales::invalidate();

        $dec = $this->correr(EstadoCivilIrs::Single, '30000', [$this->todoElAnio(7, '800', '850', '750')]);

        $this->assertSame('No se puede calcular: falta el parámetro credito_prima_salud.tabla_cifra_aplicable de 2025 (Catálogo → Parámetros).', $dec->linea('f8962', '7')->motivo);
        $this->assertSame(EstadoLinea::FaltaDato, $this->estado($dec, '26'));
    }
}
