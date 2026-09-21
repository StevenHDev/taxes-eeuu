<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CampoCliente;
use App\Models\DeterminacionFiscal;
use App\Models\ParametroFiscal;
use App\Models\User;
use App\Support\ParametrosFiscales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DeterminacionFiscalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Las calculadoras nuevas de la Fase 6 (deducción aplicable, QBI, impuesto
     * sobre el ingreso, Additional Medicare Tax, NIIT) están menos
     * condicionadas que las 4 originales — corren en cuanto filing_status/AGI
     * están disponibles, sin depender de info_dependientes. Para un test que
     * calcula un segundo tax_year (2026) sin haber sembrado parámetros para
     * ese año (ParametrosFiscalesSeeder solo siembra 2025 a propósito), hay
     * que duplicar los parámetros — mismo dato, otro año — como haría un
     * despliegue real al llegar la siguiente temporada fiscal.
     */
    private function duplicarParametrosPara(int $taxYear): void
    {
        ParametroFiscal::query()->where('tax_year', 2025)->get()->each(
            fn (ParametroFiscal $p) => ParametroFiscal::query()->firstOrCreate(
                ['tax_year' => $taxYear, 'categoria' => $p->categoria, 'clave' => $p->clave],
                ['valor' => $p->valor],
            ),
        );

        ParametrosFiscales::invalidate();
    }

    private function cargarCampo(User $cliente, string $forma, string $campo, mixed $valor, int $taxYear = 2025): void
    {
        CampoCliente::query()->create([
            'user_id' => $cliente->id,
            'forma' => $forma,
            'tax_year' => $taxYear,
            'campo' => $campo,
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'valor_texto' => $valor,
            'estado' => 'recibido',
            'source' => 'agente_ia',
        ]);
    }

    private function cargarDatosCompletos(User $cliente): void
    {
        $this->cargarCampo($cliente, 'transversal', 'estado_civil', [
            'casado_al_31_dic' => false,
            'convivio_conyuge_ultimos_6_meses' => false,
            'costeo_mas_mitad_hogar' => true,
            'existe_persona_calificable' => true,
            'conyuge_fallecio_en_anio' => false,
            'anio_fallecimiento_conyuge' => null,
        ]);

        $this->cargarCampo($cliente, 'transversal', 'info_dependientes', [
            [
                'nombre_completo' => 'Kid One',
                'fecha_nacimiento' => '2015-03-01',
                'ssn' => '111-22-3333',
                'relacion' => 'hija',
                'meses_en_hogar' => 12,
                'estudiante_tiempo_completo' => false,
                'discapacitado' => false,
                'provee_mas_50_soporte_propio' => false,
                'ingreso_bruto_anual' => 0,
                'custodia_compartida_sin_conflicto' => false,
            ],
        ]);

        $this->cargarCampo($cliente, 'form_1040', 'ingresos', [
            'salarios' => 60000,
            'intereses_dividendos' => 0,
            'ganancias_capital' => 0,
            'ingresos_jubilacion' => 0,
            'otros_ingresos' => 0,
            'ajustes_ingreso' => 0,
            'seguridad_social' => 0,
        ]);
    }

    public function test_calcular_determinaciones_crea_las_12_filas(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);
        $this->cargarDatosCompletos($cliente);

        $this->actingAs($preparador)
            ->post(route('clientes.determinaciones.store', $cliente), ['tax_year' => 2025])
            ->assertRedirect();

        // 12 desde la Fase 4 del plan de cierre de brecha GTS — se agregó
        // 'credito_extranjero' (Foreign Tax Credit simplificado).
        $this->assertSame(12, DeterminacionFiscal::query()->where('user_id', $cliente->id)->where('tax_year', 2025)->count());

        $filingStatus = DeterminacionFiscal::query()->where('user_id', $cliente->id)->where('tipo', 'filing_status')->first();
        $this->assertSame('hoh', $filingStatus->resultado['estado']);

        $creditos = DeterminacionFiscal::query()->where('user_id', $cliente->id)->where('tipo', 'creditos')->first();
        $this->assertTrue($creditos->resultado['disponible']);
        $this->assertGreaterThan(0, $creditos->resultado['total']);
    }

    /**
     * Bug real encontrado evaluando un W-2 de ejemplo: Additional Medicare
     * Tax (Form 8959) usaba ingresos.salarios (Box 1) como sustituto de
     * Medicare wages (Box 5) — subestima el impuesto cuando hay descuentos
     * pre-tax de nómina (401k, HSA), porque Box 5 siempre es >= Box 1 en esos
     * casos. Este cliente tiene Box 1 = 195,000 (bajo el umbral soltero de
     * 200,000) pero Box 5 = 210,000 (sobre el umbral) — sin el fix, el
     * impuesto adicional daría 0.
     */
    public function test_additional_medicare_tax_usa_salarios_medicare_cuando_esta_disponible(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);

        $this->cargarCampo($cliente, 'transversal', 'estado_civil', [
            'casado_al_31_dic' => false,
            'convivio_conyuge_ultimos_6_meses' => false,
            'costeo_mas_mitad_hogar' => false,
            'existe_persona_calificable' => false,
            'conyuge_fallecio_en_anio' => false,
            'anio_fallecimiento_conyuge' => null,
        ]);

        $this->cargarCampo($cliente, 'form_1040', 'ingresos', [
            'salarios' => 195000,
            'intereses_dividendos' => 0,
            'ganancias_capital' => 0,
            'ingresos_jubilacion' => 0,
            'otros_ingresos' => 0,
            'ajustes_ingreso' => 0,
            'seguridad_social' => 0,
        ]);

        $this->cargarCampo($cliente, 'form_1040', 'salarios_medicare', 210000);

        $this->actingAs($preparador)
            ->post(route('clientes.determinaciones.store', $cliente), ['tax_year' => 2025])
            ->assertRedirect();

        $determinacion = DeterminacionFiscal::query()
            ->where('user_id', $cliente->id)
            ->where('tipo', 'impuesto_medicare_adicional')
            ->first();

        $this->assertTrue($determinacion->resultado['disponible']);
        $this->assertEqualsWithDelta(90.0, $determinacion->resultado['impuesto'], 0.01);
    }

    public function test_additional_medicare_tax_cae_a_salarios_si_no_hay_salarios_medicare_guardado(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);

        $this->cargarCampo($cliente, 'transversal', 'estado_civil', [
            'casado_al_31_dic' => false,
            'convivio_conyuge_ultimos_6_meses' => false,
            'costeo_mas_mitad_hogar' => false,
            'existe_persona_calificable' => false,
            'conyuge_fallecio_en_anio' => false,
            'anio_fallecimiento_conyuge' => null,
        ]);

        $this->cargarCampo($cliente, 'form_1040', 'ingresos', [
            'salarios' => 195000,
            'intereses_dividendos' => 0,
            'ganancias_capital' => 0,
            'ingresos_jubilacion' => 0,
            'otros_ingresos' => 0,
            'ajustes_ingreso' => 0,
            'seguridad_social' => 0,
        ]);

        $this->actingAs($preparador)
            ->post(route('clientes.determinaciones.store', $cliente), ['tax_year' => 2025])
            ->assertRedirect();

        $determinacion = DeterminacionFiscal::query()
            ->where('user_id', $cliente->id)
            ->where('tipo', 'impuesto_medicare_adicional')
            ->first();

        $this->assertTrue($determinacion->resultado['disponible']);
        $this->assertEqualsWithDelta(0.0, $determinacion->resultado['impuesto'], 0.01);
    }

    public function test_un_preparador_no_asignado_no_puede_calcular(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $ajeno = User::factory()->create(['role' => UserRole::Client]);

        $this->actingAs($preparador)
            ->post(route('clientes.determinaciones.store', $ajeno), ['tax_year' => 2025])
            ->assertForbidden();
    }

    public function test_un_cliente_no_puede_calcular(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->actingAs($cliente)
            ->post(route('clientes.determinaciones.store', $cliente), ['tax_year' => 2025])
            ->assertForbidden();
    }

    public function test_sin_estado_civil_capturado_la_fila_queda_no_disponible_sin_excepcion(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);

        $this->actingAs($preparador)
            ->post(route('clientes.determinaciones.store', $cliente), ['tax_year' => 2025])
            ->assertRedirect();

        $filingStatus = DeterminacionFiscal::query()->where('user_id', $cliente->id)->where('tipo', 'filing_status')->first();
        $this->assertFalse($filingStatus->resultado['disponible']);
        $this->assertNotNull($filingStatus->resultado['motivo_no_disponible']);
    }

    /**
     * Bug real encontrado evaluando la conversación con 3213445027
     * (2026-09-18): un cliente sin dependientes responde que no tiene, y eso
     * se guarda como modo="no_aplica" — pero antes del fix, leerValor() solo
     * contaba Recibido, así que trataba "confirmó que no tiene" exactamente
     * igual que "nunca se le preguntó" y bloqueaba créditos/liquidación para
     * el caso más común de la plataforma (cliente soltero sin hijos).
     */
    public function test_info_dependientes_marcado_no_aplica_se_calcula_como_cero_dependientes(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);

        $this->cargarCampo($cliente, 'transversal', 'estado_civil', [
            'casado_al_31_dic' => false,
            'convivio_conyuge_ultimos_6_meses' => false,
            'costeo_mas_mitad_hogar' => false,
            'existe_persona_calificable' => false,
            'conyuge_fallecio_en_anio' => false,
            'anio_fallecimiento_conyuge' => null,
        ]);
        CampoCliente::query()->create([
            'user_id' => $cliente->id, 'forma' => 'transversal', 'tax_year' => 2025, 'campo' => 'info_dependientes',
            'tipo_campo' => 'dato', 'modo' => 'no_aplica', 'valor_texto' => null, 'estado' => 'no_aplica', 'source' => 'agente_ia',
        ]);
        $this->cargarCampo($cliente, 'form_1040', 'ingresos', [
            'salarios' => 55665, 'intereses_dividendos' => 0, 'ganancias_capital' => 0,
            'ingresos_jubilacion' => 0, 'otros_ingresos' => 0, 'ajustes_ingreso' => 0, 'seguridad_social' => 0,
        ]);

        $this->actingAs($preparador)
            ->post(route('clientes.determinaciones.store', $cliente), ['tax_year' => 2025])
            ->assertRedirect();

        $dependientes = DeterminacionFiscal::query()->where('user_id', $cliente->id)->where('tipo', 'dependientes')->first();
        $this->assertTrue($dependientes->resultado['disponible']);
        $this->assertSame(0, $dependientes->resultado['conteo_qualifying_child']);
        $this->assertSame([], $dependientes->resultado['dependientes']);

        $creditos = DeterminacionFiscal::query()->where('user_id', $cliente->id)->where('tipo', 'creditos')->first();
        $this->assertTrue($creditos->resultado['disponible']);

        $liquidacion = DeterminacionFiscal::query()->where('user_id', $cliente->id)->where('tipo', 'liquidacion')->first();
        $this->assertTrue($liquidacion->resultado['disponible']);
    }

    public function test_ingresos_con_formato_antiguo_de_antes_de_la_fase_2_no_truena_sino_queda_no_disponible(): void
    {
        // Regresión: un campo `ingresos` cargado antes de la Fase 2 (cuando
        // era un number suelto, no un object) sigue teniendo un valor no-null
        // en la base — sin el chequeo is_array(), esto llegaba directo a
        // AgiCalculator::calcular(array $ingresos) con un int y tronaba con
        // un TypeError (500) en vez de mostrar "no disponible".
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);
        $this->cargarCampo($cliente, 'form_1040', 'ingresos', 52000);

        $this->actingAs($preparador)
            ->post(route('clientes.determinaciones.store', $cliente), ['tax_year' => 2025])
            ->assertRedirect();

        $agi = DeterminacionFiscal::query()->where('user_id', $cliente->id)->where('tipo', 'agi')->first();
        $this->assertFalse($agi->resultado['disponible']);
        $this->assertNotNull($agi->resultado['motivo_no_disponible']);
    }

    public function test_recalcular_dos_veces_actualiza_en_el_lugar(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);
        $this->cargarDatosCompletos($cliente);

        $this->actingAs($preparador)->post(route('clientes.determinaciones.store', $cliente), ['tax_year' => 2025]);
        $this->actingAs($preparador)->post(route('clientes.determinaciones.store', $cliente), ['tax_year' => 2025]);

        $this->assertSame(12, DeterminacionFiscal::query()->where('user_id', $cliente->id)->count());
    }

    public function test_dos_anos_fiscales_del_mismo_cliente_no_se_cruzan(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);
        $this->cargarDatosCompletos($cliente);
        $this->cargarCampo($cliente, 'form_1040', 'ingresos', [
            'salarios' => 90000, 'intereses_dividendos' => 0, 'ganancias_capital' => 0,
            'ingresos_jubilacion' => 0, 'otros_ingresos' => 0, 'ajustes_ingreso' => 0, 'seguridad_social' => 0,
        ], 2026);
        $this->cargarCampo($cliente, 'transversal', 'estado_civil', [
            'casado_al_31_dic' => false, 'convivio_conyuge_ultimos_6_meses' => false, 'costeo_mas_mitad_hogar' => false,
            'existe_persona_calificable' => false, 'conyuge_fallecio_en_anio' => false, 'anio_fallecimiento_conyuge' => null,
        ], 2026);
        $this->duplicarParametrosPara(2026);

        $this->actingAs($preparador)->post(route('clientes.determinaciones.store', $cliente), ['tax_year' => 2025]);
        $this->actingAs($preparador)->post(route('clientes.determinaciones.store', $cliente), ['tax_year' => 2026]);

        $agi2025 = DeterminacionFiscal::query()->where('user_id', $cliente->id)->where('tax_year', 2025)->where('tipo', 'agi')->first();
        $agi2026 = DeterminacionFiscal::query()->where('user_id', $cliente->id)->where('tax_year', 2026)->where('tipo', 'agi')->first();

        $this->assertSame(60000.0, (float) $agi2025->resultado['agi']);
        $this->assertSame(90000.0, (float) $agi2026->resultado['agi']);
    }

    /**
     * Fase 4 del plan de cierre de brecha GTS: impuesto_extranjero_pagado se
     * capturaba desde antes pero no alimentaba ningún crédito real — ahora
     * reduce el saldo a pagar vía el Foreign Tax Credit simplificado (de
     * minimis, sin Form 1116).
     */
    public function test_impuesto_extranjero_pagado_bajo_el_umbral_reduce_el_saldo_a_pagar(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);
        $this->cargarDatosCompletos($cliente);
        $this->cargarCampo($cliente, 'form_1040', 'impuesto_extranjero_pagado', 150);

        $this->actingAs($preparador)
            ->post(route('clientes.determinaciones.store', $cliente), ['tax_year' => 2025])
            ->assertRedirect();

        $creditoExtranjero = DeterminacionFiscal::query()->where('user_id', $cliente->id)->where('tipo', 'credito_extranjero')->first();
        $this->assertTrue($creditoExtranjero->resultado['disponible']);
        $this->assertEquals(150.0, $creditoExtranjero->resultado['credito']);
        $this->assertFalse($creditoExtranjero->resultado['requiere_form_1116']);

        $liquidacion = DeterminacionFiscal::query()->where('user_id', $cliente->id)->where('tipo', 'liquidacion')->first();
        $this->assertTrue($liquidacion->resultado['disponible']);
    }

    public function test_el_detalle_del_cliente_incluye_la_prop_determinaciones(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);
        $this->cargarDatosCompletos($cliente);

        $this->actingAs($preparador)->post(route('clientes.determinaciones.store', $cliente), ['tax_year' => 2025]);

        $this->actingAs($preparador)
            ->get(route('clientes.show', $cliente))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('determinaciones', 12));
    }
}
