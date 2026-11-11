<?php

namespace Tests\Unit\Calculo\Formularios;

use App\Enums\Expediente\EstadoCivilIrs;
use App\Models\ParametroFiscal;
use App\Services\Calculo\Formularios\PagosYResultado;
use App\Services\Calculo\Nucleo\DatoEntrada;
use App\Services\Calculo\Nucleo\Declaracion;
use App\Services\Calculo\Nucleo\Dinero;
use App\Services\Calculo\Nucleo\DocumentoEntrada;
use App\Services\Calculo\Nucleo\EntradasDeclaracion;
use App\Services\Calculo\Nucleo\EstadoLinea;
use App\Services\Calculo\Nucleo\Linea;
use App\Services\Calculo\Nucleo\OrigenTraza;
use App\Services\Calculo\Nucleo\RespuestaEntrada;
use App\Services\Calculo\Nucleo\Traza;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 6.4h: PagosYResultado (1040 de 2025, líneas 25a a 38).
 *
 * Las reglas salen de las instrucciones del 1040 de 2025 (i1040gi): líneas 25a a 26 (p. 38 y 39), 37 y 38
 * (p. 64 y 65); y del Form 8959 de 2025, parte V (p. 1, líneas 19 a 24). Las sumas se arman a mano.
 */
class PagosYResultadoTest extends TestCase
{
    use RefreshDatabase;

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

    private function comprobante(int $item, ?string $tipo, ?string $anio, string $monto): DocumentoEntrada
    {
        $casillas = ['monto' => $this->dato($monto), 'fecha-pago' => new DatoEntrada('2025-06-15', OrigenTraza::Leido)];

        if ($tipo !== null) {
            $casillas['tipo-pago'] = new DatoEntrada($tipo, OrigenTraza::Leido);
        }

        if ($anio !== null) {
            $casillas['anio-aplicado'] = new DatoEntrada($anio, OrigenTraza::Leido);
        }

        return new DocumentoEntrada('comprobantes-pagos', $item, 1, $casillas);
    }

    /**
     * La declaración con lo que escriben las otras calculadoras (24, 27a, 27 del 8812 y 15 del Schedule 3); null = no aplica.
     *
     * @param  array<string, DatoEntrada>  $datos
     * @param  array<string, RespuestaEntrada>  $respuestas
     * @param  array<string, list<DocumentoEntrada>>  $documentos
     */
    private function correr(
        string $impuesto,
        array $datos = [],
        ?string $eic = null,
        ?string $actc = null,
        ?string $schedule3 = null,
        array $respuestas = [],
        array $documentos = [],
        EstadoCivilIrs $estado = EstadoCivilIrs::Single,
    ): Declaracion {
        $dec = new Declaracion(2025, 'prueba');
        $dec->poner(Linea::calculada('1040', '24', Dinero::deDolares($impuesto), Traza::vacia()));

        foreach ([['1040', '27a', $eic], ['sch-8812', '27', $actc], ['sch-3', '15', $schedule3]] as [$formulario, $linea, $valor]) {
            $dec->poner($valor === null
                ? Linea::noAplica($formulario, $linea)
                : Linea::calculada($formulario, $linea, Dinero::deDolares($valor), Traza::vacia()));
        }

        (new PagosYResultado)->calcular(new EntradasDeclaracion(2025, $estado, datos: $datos, respuestas: $respuestas, documentos: $documentos), $dec);

        return $dec;
    }

    private function valor(Declaracion $dec, string $linea): string
    {
        $l = $dec->linea('1040', $linea);
        $this->assertNotNull($l, "1040:{$linea} no se escribió");
        $this->assertSame(EstadoLinea::Calculada, $l->estado, "1040:{$linea}: {$l->motivo}");

        return $l->valor->texto();
    }

    private function estado(Declaracion $dec, string $linea): EstadoLinea
    {
        $l = $dec->linea('1040', $linea);
        $this->assertNotNull($l, "1040:{$linea} no se escribió");

        return $l->estado;
    }

    private function crearTasaMedicare(): void
    {
        // Form 8959 de 2025, p. 1, línea 21: "Multiply line 20 by 1.45% (0.0145)". Ya viene en el catálogo (ParametrosFiscalesSeeder).
        $this->assertTrue(ParametroFiscal::query()->where(['tax_year' => 2025, 'categoria' => 'additional_medicare_tax', 'clave' => 'tasa_medicare_regular'])->exists());
    }

    public function test_retenciones_de_varios_w2_y_1099_van_a_25a_25b_y_25d(): void
    {
        // Los datos ya llegan sumados por casilla: W-2 c. 2 = 2.000 + 1.000; 1099-NEC c. 4 = 200; 1099-MISC c. 4 = 100.
        $dec = $this->correr('5000', [
            'w2:2' => $this->dato('3000'),
            '1099-nec:4' => $this->dato('200'),
            '1099-misc:4' => $this->dato('100'),
        ]);

        $this->assertSame('3.000,00', $this->valor($dec, '25a'));
        $this->assertSame('300,00', $this->valor($dec, '25b'));
        $this->assertSame(EstadoLinea::NoAplica, $this->estado($dec, '25c'));
        $this->assertSame('3.300,00', $this->valor($dec, '25d'));
    }

    public function test_sin_retenciones_25a_25b_y_25c_no_aplican_y_25d_es_cero(): void
    {
        $dec = $this->correr('0');

        $this->assertSame(EstadoLinea::NoAplica, $this->estado($dec, '25a'));
        $this->assertSame(EstadoLinea::NoAplica, $this->estado($dec, '25b'));
        $this->assertSame('0,00', $this->valor($dec, '25d'));
    }

    public function test_medicare_adicional_retenido_va_a_25c_por_el_form_8959_parte_v(): void
    {
        $this->crearTasaMedicare();

        // Salarios de Medicare 250.000; Medicare retenido 4.075 (1,45 % de 250.000 = 3.625 más 0,9 % de lo que pasa
        // de 200.000 = 450). Línea 22 = 4.075 − 3.625 = 450. Más W-2G c. 4 = 50 → 25c = 500.
        $dec = $this->correr('60000', [
            'w2:2' => $this->dato('40000'),
            'w2:5' => $this->dato('250000'),
            'w2:6' => $this->dato('4075'),
            'w2g:4' => $this->dato('50'),
        ]);

        $this->assertSame('500,00', $this->valor($dec, '25c'));
        $this->assertSame('40.500,00', $this->valor($dec, '25d'));
    }

    public function test_sin_medicare_adicional_retenido_la_parte_v_da_cero(): void
    {
        $this->crearTasaMedicare();

        // 1,45 % de 50.000 = 725, igual a lo retenido: línea 22 = 0.
        $dec = $this->correr('5000', ['w2:5' => $this->dato('50000'), 'w2:6' => $this->dato('725')]);

        $this->assertSame('0,00', $this->valor($dec, '25c'));
    }

    public function test_sin_el_parametro_de_la_tasa_de_medicare_25c_sale_falta_dato_con_su_nombre(): void
    {
        ParametroFiscal::query()->where(['tax_year' => 2025, 'categoria' => 'additional_medicare_tax', 'clave' => 'tasa_medicare_regular'])->delete();
        $dec = $this->correr('5000', ['w2:2' => $this->dato('3000'), 'w2:5' => $this->dato('50000'), 'w2:6' => $this->dato('725')]);

        $this->assertSame(EstadoLinea::FaltaDato, $this->estado($dec, '25c'));
        $this->assertSame('No se puede calcular: falta el parámetro additional_medicare_tax.tasa_medicare_regular de 2025 (Catálogo → Parámetros).', $dec->linea('1040', '25c')->motivo);
        $this->assertSame(EstadoLinea::FaltaDato, $this->estado($dec, '25d'));
        $this->assertSame(EstadoLinea::FaltaDato, $this->estado($dec, '34'));
    }

    public function test_un_k1_deja_25c_no_soportada(): void
    {
        $dec = $this->correr('5000', documentos: ['k-1' => [new DocumentoEntrada('k-1', 30, 1, [])]]);

        $this->assertSame(EstadoLinea::NoSoportada, $this->estado($dec, '25c'));
    }

    public function test_solo_cuentan_los_pagos_estimados_del_anio_de_la_declaracion(): void
    {
        // i1040gi p. 39: "estimated federal income tax payments you made for 2025". El de 2024, el de la extensión
        // (Schedule 3, línea 10) y "otro" no cuentan: 26 = 1.000 + 500.
        $dec = $this->correr('5000', ['w2:2' => $this->dato('2000')], documentos: ['comprobantes-pagos' => [
            $this->comprobante(1, 'estimado', '2025', '1000'),
            $this->comprobante(2, 'estimado', '2025', '500'),
            $this->comprobante(3, 'estimado', '2024', '700'),
            $this->comprobante(4, 'extension', '2025', '300'),
            $this->comprobante(5, 'otro', '2025', '90'),
        ]], respuestas: ['G1' => new RespuestaEntrada('si')]);

        $this->assertSame('1.500,00', $this->valor($dec, '26'));
        $this->assertSame('3.500,00', $this->valor($dec, '33'));
    }

    public function test_un_comprobante_sin_tipo_o_sin_anio_sale_falta_dato(): void
    {
        $dec = $this->correr('5000', documentos: ['comprobantes-pagos' => [$this->comprobante(1, null, '2025', '1000')]]);
        $this->assertSame(EstadoLinea::FaltaDato, $this->estado($dec, '26'));
        $this->assertStringContainsString('Falta el tipo de pago del comprobante comprobantes-pagos#1', $dec->linea('1040', '26')->motivo);

        $dec = $this->correr('5000', documentos: ['comprobantes-pagos' => [$this->comprobante(1, 'estimado', null, '1000')]]);
        $this->assertStringContainsString('Falta el año al que se aplica', $dec->linea('1040', '26')->motivo);
    }

    public function test_g1_si_sin_comprobantes_de_pagos_estimados_sale_falta_dato(): void
    {
        $dec = $this->correr('5000', respuestas: ['G1' => new RespuestaEntrada('si')]);

        $this->assertSame(EstadoLinea::FaltaDato, $this->estado($dec, '26'));
        $this->assertStringContainsString('G1', $dec->linea('1040', '26')->motivo);
    }

    public function test_sin_pagos_estimados_la_26_no_aplica(): void
    {
        $dec = $this->correr('5000', respuestas: ['G1' => new RespuestaEntrada('no')]);

        $this->assertSame(EstadoLinea::NoAplica, $this->estado($dec, '26'));
    }

    public function test_con_mfs_los_pagos_estimados_los_reparte_el_preparador(): void
    {
        $dec = $this->correr('5000', documentos: ['comprobantes-pagos' => [$this->comprobante(1, 'estimado', '2025', '1000')]], estado: EstadoCivilIrs::Mfs);

        $this->assertSame(EstadoLinea::FaltaDato, $this->estado($dec, '26'));
        $this->assertStringContainsString('Con MFS', $dec->linea('1040', '26')->motivo);
    }

    public function test_creditos_reembolsables_se_copian_y_suman_en_la_32(): void
    {
        // 27a = 500 (Eitc), 28 = 27 del 8812 = 1.700, 29 y 30 no aplican, 31 = 15 del Schedule 3 = 600 → 32 = 2.800.
        $dec = $this->correr('1000', ['w2:2' => $this->dato('400')], eic: '500', actc: '1700', schedule3: '600');

        $this->assertSame('1.700,00', $this->valor($dec, '28'));
        $this->assertSame(EstadoLinea::NoAplica, $this->estado($dec, '29'));
        $this->assertSame(EstadoLinea::NoAplica, $this->estado($dec, '30'));
        $this->assertSame('600,00', $this->valor($dec, '31'));
        $this->assertSame('2.800,00', $this->valor($dec, '32'));
        // 33 = 400 + 0 + 2.800 = 3.200; 34 = 3.200 − 1.000 = 2.200.
        $this->assertSame('3.200,00', $this->valor($dec, '33'));
        $this->assertSame('2.200,00', $this->valor($dec, '34'));
    }

    public function test_sin_la_27_del_8812_la_28_sale_falta_dato(): void
    {
        $dec = new Declaracion(2025, 'prueba');
        $dec->poner(Linea::calculada('1040', '24', Dinero::deDolares('1000'), Traza::vacia()));
        $dec->poner(Linea::noAplica('1040', '27a'));
        $dec->poner(Linea::noAplica('sch-3', '15'));
        (new PagosYResultado)->calcular(new EntradasDeclaracion(2025, EstadoCivilIrs::Single), $dec);

        $this->assertSame(EstadoLinea::FaltaDato, $dec->linea('1040', '28')->estado);
        $this->assertSame(EstadoLinea::FaltaDato, $dec->linea('1040', '37')->estado);
    }

    public function test_un_1098_t_o_una_adopcion_no_se_soportan(): void
    {
        $dec = $this->correr('1000', ['1098-t:1' => $this->dato('4000')]);
        $this->assertSame(EstadoLinea::NoSoportada, $this->estado($dec, '29'));
        $this->assertSame(EstadoLinea::NoSoportada, $this->estado($dec, '32'));
        $this->assertSame(EstadoLinea::NoSoportada, $this->estado($dec, '34'));

        $dec = $this->correr('1000', respuestas: ['F12' => new RespuestaEntrada('si')]);
        $this->assertSame(EstadoLinea::NoSoportada, $this->estado($dec, '30'));
    }

    public function test_reembolso_todo_a_la_35a_sin_multa(): void
    {
        $dec = $this->correr('5000', ['w2:2' => $this->dato('6000')], respuestas: ['G3' => new RespuestaEntrada('deposito_directo')]);

        $this->assertSame('6.000,00', $this->valor($dec, '33'));
        $this->assertSame('1.000,00', $this->valor($dec, '34'));
        $this->assertSame('1.000,00', $this->valor($dec, '35a'));
        $this->assertSame(EstadoLinea::NoAplica, $this->estado($dec, '36'));
        $this->assertSame(EstadoLinea::NoAplica, $this->estado($dec, '37'));
        $this->assertSame(EstadoLinea::NoAplica, $this->estado($dec, '38'));
        $this->assertNull($dec->linea('1040', '35b'));
        $this->assertNull($dec->linea('1040', '35c'));
        $this->assertNull($dec->linea('1040', '35d'));
    }

    public function test_reembolso_con_pagos_estimados_deja_la_multa_para_el_preparador(): void
    {
        $dec = $this->correr('5000', ['w2:2' => $this->dato('4000')], documentos: ['comprobantes-pagos' => [$this->comprobante(1, 'estimado', '2025', '2000')]]);

        $this->assertSame('1.000,00', $this->valor($dec, '35a'));
        $this->assertSame(EstadoLinea::NoSoportada, $this->estado($dec, '38'));
    }

    public function test_saldo_a_pagar_va_a_la_37_y_la_multa_la_revisa_el_preparador(): void
    {
        $dec = $this->correr('5000', ['w2:2' => $this->dato('4000')]);

        $this->assertSame('1.000,00', $this->valor($dec, '37'));
        $this->assertSame(EstadoLinea::NoAplica, $this->estado($dec, '34'));
        $this->assertSame(EstadoLinea::NoAplica, $this->estado($dec, '35a'));
        $this->assertSame(EstadoLinea::NoSoportada, $this->estado($dec, '38'));
        $this->assertStringContainsString('Form 2210', $dec->linea('1040', '38')->motivo);
    }

    public function test_pagos_iguales_al_impuesto_dejan_la_37_en_cero(): void
    {
        $dec = $this->correr('5000', ['w2:2' => $this->dato('5000')]);

        $this->assertSame('0,00', $this->valor($dec, '37'));
        $this->assertSame(EstadoLinea::NoAplica, $this->estado($dec, '34'));
        $this->assertSame(EstadoLinea::NoAplica, $this->estado($dec, '38'));
    }

    public function test_aplicar_el_reembolso_al_anio_siguiente_lo_decide_el_preparador(): void
    {
        $dec = $this->correr('5000', ['w2:2' => $this->dato('6000')], respuestas: ['G3' => new RespuestaEntrada('aplicar_anio_siguiente')]);

        $this->assertSame('1.000,00', $this->valor($dec, '34'));
        $this->assertSame(EstadoLinea::NoSoportada, $this->estado($dec, '35a'));
        $this->assertSame(EstadoLinea::NoSoportada, $this->estado($dec, '36'));
    }

    public function test_sin_la_linea_24_el_resultado_sale_falta_dato(): void
    {
        $dec = new Declaracion(2025, 'prueba');
        $dec->poner(Linea::noAplica('1040', '27a'));
        $dec->poner(Linea::noAplica('sch-8812', '27'));
        $dec->poner(Linea::noAplica('sch-3', '15'));
        (new PagosYResultado)->calcular(new EntradasDeclaracion(2025, EstadoCivilIrs::Single), $dec);

        foreach (['34', '35a', '36', '37', '38'] as $linea) {
            $this->assertSame(EstadoLinea::FaltaDato, $dec->linea('1040', $linea)->estado, "1040:{$linea}");
        }
    }

    public function test_un_dato_sin_validar_marca_las_lineas_que_lo_usan(): void
    {
        $dec = $this->correr('5000', ['w2:2' => $this->dato('6000', validado: false, referencia: 'w2#9:2')]);

        $this->assertTrue($dec->linea('1040', '25a')->usaSinValidar);
        $this->assertSame(['w2#9:2'], $dec->linea('1040', '33')->datosSinValidar);
        $this->assertSame(['w2#9:2'], $dec->linea('1040', '35a')->datosSinValidar);
    }

    public function test_la_traza_no_lleva_la_forma_de_cobro_ni_datos_bancarios(): void
    {
        $dec = $this->correr('5000', ['w2:2' => $this->dato('6000')], respuestas: [
            'G3' => new RespuestaEntrada('deposito_directo'),
            'G4' => new RespuestaEntrada('debito_cuenta'),
        ], documentos: ['datos-bancarios' => [new DocumentoEntrada('datos-bancarios', 40, 1, ['cuenta' => new DatoEntrada('000123456789', OrigenTraza::Leido)])]]);

        foreach ($dec->lineas('1040') as $linea) {
            foreach ($linea->traza->pasos as $paso) {
                $texto = $paso->texto.' '.($paso->referencia ?? '').' '.(is_string($paso->valor) ? $paso->valor : '');

                $this->assertStringNotContainsString('deposito_directo', $texto);
                $this->assertStringNotContainsString('debito_cuenta', $texto);
                $this->assertStringNotContainsString('000123456789', $texto);
            }
        }

        $referencias = array_filter(array_map(fn ($p) => $p->referencia, $dec->linea('1040', '35a')->traza->pasos));
        $this->assertContains('G3', $referencias);
    }
}
