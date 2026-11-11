<?php

namespace Tests\Unit\Calculo\Formularios;

use App\Enums\Expediente\ClasificacionPersona;
use App\Enums\Expediente\EstadoCivilIrs;
use App\Services\Calculo\Formularios\Schedule3;
use App\Services\Calculo\Nucleo\DatoEntrada;
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
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 6.4f: Schedule 3 (líneas 2, 3, 8, 9, 10, 11 y 15) y la línea 20 del 1040.
 *
 * Fuentes: Schedule 3 de 2025 (p. 1) e instrucciones del 1040 de 2025 (i1040gi, p. 117): línea 10, lo pagado con la
 * extensión; línea 11, "If you, or your spouse if filing a joint return, had more than one employer for 2025 and total
 * wages of more than $176,100 … You can take a credit on this line for the amount withheld in excess of $10,918.20.
 * But if any one employer withheld more than $10,918.20, you can't claim the excess on your return … Figure this
 * amount separately for you and your spouse."
 */
class Schedule3Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('es');
        // El máximo de Seguro Social retenido por persona viene del catálogo (2025/parametros.php; 2026 pendiente).
        $this->seed(CatalogoFiscalSeeder::class);
    }

    public function test_el_credito_del_2441_va_a_las_lineas_2_y_8_y_a_la_20_del_1040(): void
    {
        $dec = $this->declaracion(['f2441:11' => '550']);
        (new Schedule3)->calcular($this->entradas(), $dec);

        $this->assertSame('550,00', $this->valor($dec, 'sch-3', '2'));
        $this->assertSame('550,00', $this->valor($dec, 'sch-3', '8'));
        $this->assertSame('550,00', $this->valor($dec, '1040', '20'));
    }

    public function test_sin_nada_del_piloto_todas_las_lineas_no_aplican(): void
    {
        $dec = new Declaracion(2025, 'prueba');
        $dec->poner(Linea::noAplica('f2441', '11'));
        (new Schedule3)->calcular($this->entradas(), $dec);

        foreach (['2', '8', '9', '10', '11', '15'] as $linea) {
            $this->assertSame(EstadoLinea::NoAplica, $dec->linea('sch-3', $linea)->estado, "línea {$linea}");
        }

        $this->assertSame(EstadoLinea::NoAplica, $dec->linea('1040', '20')->estado);
    }

    public function test_un_1098_t_deja_la_parte_i_como_no_soportada(): void
    {
        $dec = $this->declaracion(['f2441:11' => '0']);
        (new Schedule3)->calcular($this->entradas(datos: ['1098-t:1' => new DatoEntrada(Dinero::deDolares(4000), OrigenTraza::Leido, '1098-t#9:1')]), $dec);

        $this->assertSame(EstadoLinea::NoSoportada, $dec->linea('sch-3', '3')->estado);
        $this->assertSame(EstadoLinea::NoSoportada, $dec->linea('sch-3', '8')->estado);
        $this->assertSame(EstadoLinea::NoSoportada, $dec->linea('1040', '20')->estado);
    }

    public function test_el_2441_pendiente_deja_pendiente_la_linea_20(): void
    {
        $dec = new Declaracion(2025, 'prueba');
        $dec->poner(Linea::faltaDato('f2441', '11', 'Con MFS, este crédito lo revisa el preparador.'));
        (new Schedule3)->calcular($this->entradas(), $dec);

        $this->assertSame(EstadoLinea::FaltaDato, $dec->linea('1040', '20')->estado);
    }

    public function test_el_pago_con_la_extension_del_ano_va_a_la_linea_10(): void
    {
        $comprobantes = [
            $this->comprobante(40, 'estimado', '2025', '1000'),
            $this->comprobante(41, 'extension', '2025', '2500'),
            $this->comprobante(42, 'extension', '2024', '700'),
        ];
        $dec = $this->declaracion(['f2441:11' => '0']);
        (new Schedule3)->calcular($this->entradas(documentos: ['comprobantes-pagos' => $comprobantes]), $dec);

        $this->assertSame('2.500,00', $this->valor($dec, 'sch-3', '10'));
        $this->assertSame('2.500,00', $this->valor($dec, 'sch-3', '15'));
    }

    public function test_un_comprobante_sin_tipo_de_pago_queda_pendiente(): void
    {
        $dec = $this->declaracion(['f2441:11' => '0']);
        (new Schedule3)->calcular($this->entradas(documentos: ['comprobantes-pagos' => [$this->comprobante(40, null, '2025', '1000')]]), $dec);

        $this->assertSame(EstadoLinea::FaltaDato, $dec->linea('sch-3', '10')->estado);
        $this->assertSame(EstadoLinea::FaltaDato, $dec->linea('sch-3', '15')->estado);
    }

    public function test_exceso_de_seguro_social_con_dos_empleadores(): void
    {
        // Persona 1: 6.000 + 6.200 = 12.200 − 10.918,20 = 1.281,80. Persona 2 (un solo W-2) no cuenta.
        $w2 = [$this->w2(1, 1, '6000', '11-1111111'), $this->w2(2, 1, '6200', '22-2222222'), $this->w2(3, 2, '9000', '33-3333333')];
        $dec = $this->declaracion(['f2441:11' => '0']);
        (new Schedule3)->calcular($this->entradas(EstadoCivilIrs::Mfj, documentos: ['w2' => $w2]), $dec);

        $this->assertSame('1.281,80', $this->valor($dec, 'sch-3', '11'));
        $this->assertSame('1.281,80', $this->valor($dec, 'sch-3', '15'));
    }

    public function test_cada_conyuge_se_calcula_por_separado(): void
    {
        // Cada cónyuge con 5.000 + 5.000 = 10.000: ninguno pasa del máximo aunque juntos sí.
        $w2 = [$this->w2(1, 1, '5000', '11-1111111'), $this->w2(2, 1, '5000', '22-2222222'), $this->w2(3, 2, '5000', '33-3333333'), $this->w2(4, 2, '5000', '44-4444444')];
        $dec = $this->declaracion(['f2441:11' => '0']);
        (new Schedule3)->calcular($this->entradas(EstadoCivilIrs::Mfj, documentos: ['w2' => $w2]), $dec);

        $this->assertSame('0,00', $this->valor($dec, 'sch-3', '11'));
    }

    public function test_lo_que_un_solo_empleador_retuvo_de_mas_no_se_reclama(): void
    {
        // 11.000 de un empleador (pasa de 10.918,20: se cuenta hasta el máximo) + 2.000 de otro = 12.918,20 − 10.918,20.
        $w2 = [$this->w2(1, 1, '11000', '11-1111111'), $this->w2(2, 1, '2000', '22-2222222')];
        $dec = $this->declaracion(['f2441:11' => '0']);
        (new Schedule3)->calcular($this->entradas(documentos: ['w2' => $w2]), $dec);

        $this->assertSame('2.000,00', $this->valor($dec, 'sch-3', '11'));
    }

    public function test_dos_w2_del_mismo_empleador_son_un_solo_empleador(): void
    {
        $w2 = [$this->w2(1, 1, '6000', '11-1111111'), $this->w2(2, 1, '6200', '111111111')];
        $dec = $this->declaracion(['f2441:11' => '0']);
        (new Schedule3)->calcular($this->entradas(documentos: ['w2' => $w2]), $dec);

        $this->assertSame('0,00', $this->valor($dec, 'sch-3', '11'));
        foreach ($dec->linea('sch-3', '11')->traza->pasos as $paso) {
            $this->assertStringNotContainsString('1111111', $paso->texto.$paso->referencia);
        }
    }

    public function test_un_w2_sin_dueno_deja_pendiente_el_exceso(): void
    {
        $w2 = [$this->w2(1, 1, '6000', '11-1111111'), $this->w2(2, null, '6200', '22-2222222')];
        $dec = $this->declaracion(['f2441:11' => '0']);
        (new Schedule3)->calcular($this->entradas(documentos: ['w2' => $w2]), $dec);

        $this->assertSame('Falta saber de quién es el W-2 #2.', $dec->linea('sch-3', '11')->motivo);
    }

    public function test_en_2026_sin_el_maximo_de_seguro_social_queda_pendiente_con_el_nombre_del_parametro(): void
    {
        $w2 = [$this->w2(1, 1, '6000', '11-1111111'), $this->w2(2, 1, '6200', '22-2222222')];
        $dec = new Declaracion(2026, 'prueba');
        $dec->poner(Linea::noAplica('f2441', '11'));
        (new Schedule3)->calcular(new EntradasDeclaracion(2026, EstadoCivilIrs::Single, personas: $this->personas(EstadoCivilIrs::Single), documentos: ['w2' => $w2]), $dec);

        $this->assertSame(EstadoLinea::FaltaDato, $dec->linea('sch-3', '11')->estado);
        $this->assertStringContainsString('self_employment_tax.maximo_seguro_social_retenido_por_persona', $dec->linea('sch-3', '11')->motivo);
    }

    public function test_el_credito_neto_de_la_prima_va_a_la_linea_9(): void
    {
        $dec = $this->declaracion(['f2441:11' => '0', 'f8962:26' => '1200']);
        (new Schedule3)->calcular($this->entradas(datos: ['1095-a:a' => new DatoEntrada(Dinero::deDolares(100), OrigenTraza::Leido)]), $dec);

        $this->assertSame('1.200,00', $this->valor($dec, 'sch-3', '9'));
        $this->assertSame('1.200,00', $this->valor($dec, 'sch-3', '15'));
    }

    public function test_con_1095_a_y_sin_el_8962_falta_el_dato(): void
    {
        $dec = $this->declaracion(['f2441:11' => '0']);
        (new Schedule3)->calcular($this->entradas(datos: ['1095-a:a' => new DatoEntrada(Dinero::deDolares(100), OrigenTraza::Leido)]), $dec);

        $this->assertSame(EstadoLinea::FaltaDato, $dec->linea('sch-3', '9')->estado);
        $this->assertSame(EstadoLinea::FaltaDato, $dec->linea('sch-3', '15')->estado);
    }

    public function test_los_datos_sin_validar_marcan_la_linea_15(): void
    {
        $comprobante = new DocumentoEntrada('comprobantes-pagos', 41, 1, [
            'tipo-pago' => new DatoEntrada('extension', OrigenTraza::Leido),
            'anio-aplicado' => new DatoEntrada('2025', OrigenTraza::Leido),
            'monto' => new DatoEntrada(Dinero::deDolares(300), OrigenTraza::Leido, 'comprobantes-pagos#41:monto', validado: false),
        ]);
        $dec = $this->declaracion(['f2441:11' => '0']);
        (new Schedule3)->calcular($this->entradas(documentos: ['comprobantes-pagos' => [$comprobante]]), $dec);

        $this->assertTrue($dec->linea('sch-3', '15')->usaSinValidar);
        $this->assertContains('comprobantes-pagos#41:monto', $dec->linea('sch-3', '15')->datosSinValidar);
    }

    // ── Ayudantes ──

    /** @param array<string, string> $lineas 'formulario:linea' => dólares */
    private function declaracion(array $lineas): Declaracion
    {
        $dec = new Declaracion(2025, 'prueba');

        foreach ($lineas as $clave => $dolares) {
            [$formulario, $linea] = explode(':', $clave);
            $dec->poner(Linea::calculada($formulario, $linea, Dinero::deDolares($dolares), Traza::vacia()));
        }

        return $dec;
    }

    /**
     * @param  array<string, DatoEntrada>  $datos
     * @param  array<string, list<DocumentoEntrada>>  $documentos
     */
    private function entradas(EstadoCivilIrs $estado = EstadoCivilIrs::Single, array $datos = [], array $documentos = []): EntradasDeclaracion
    {
        return new EntradasDeclaracion(2025, $estado, personas: $this->personas($estado), datos: $datos, documentos: $documentos);
    }

    /** @return list<PersonaEntrada> */
    private function personas(EstadoCivilIrs $estado): array
    {
        $personas = [new PersonaEntrada(1, RolPersona::Contribuyente, ClasificacionPersona::NoDependiente, 40, true, false)];

        if ($estado === EstadoCivilIrs::Mfj) {
            $personas[] = new PersonaEntrada(2, RolPersona::Conyuge, ClasificacionPersona::NoDependiente, 40, true, false);
        }

        return $personas;
    }

    private function w2(int $item, ?int $persona, string $retenido, string $ein): DocumentoEntrada
    {
        return new DocumentoEntrada('w2', $item, $persona, [
            'b' => new DatoEntrada($ein, OrigenTraza::Leido),
            '4' => new DatoEntrada(Dinero::deDolares($retenido), OrigenTraza::Leido, "w2#{$item}:4"),
        ]);
    }

    private function comprobante(int $item, ?string $tipo, string $anio, string $monto): DocumentoEntrada
    {
        $casillas = [
            'anio-aplicado' => new DatoEntrada($anio, OrigenTraza::Leido),
            'monto' => new DatoEntrada(Dinero::deDolares($monto), OrigenTraza::Leido, "comprobantes-pagos#{$item}:monto"),
        ];

        if ($tipo !== null) {
            $casillas['tipo-pago'] = new DatoEntrada($tipo, OrigenTraza::Leido);
        }

        return new DocumentoEntrada('comprobantes-pagos', $item, 1, $casillas);
    }

    private function valor(Declaracion $dec, string $formulario, string $linea): string
    {
        $l = $dec->linea($formulario, $linea);
        $this->assertNotNull($l, "{$formulario}:{$linea} no se escribió");
        $this->assertSame(EstadoLinea::Calculada, $l->estado, "{$formulario}:{$linea}: {$l->motivo}");

        return $l->valor->texto();
    }
}
