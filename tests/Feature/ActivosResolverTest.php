<?php

namespace Tests\Feature;

use App\Enums\FieldKind;
use App\Enums\TipoPromptActivoStep;
use App\Enums\UserRole;
use App\Models\CampoCatalogo;
use App\Models\CampoCliente;
use App\Models\Documento;
use App\Models\PromptActivoStep;
use App\Models\User;
use App\Services\WhatsappAgent\ActivosResolver;
use App\Support\TaxFieldCatalog;
use Database\Seeders\PromptActivoStepsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ver evaluación de la conversación real con 3213445027 (2026-09-18): el
 * modelo (gpt-5.6-luna) perdía el orden fijo de ACTIVOS y hasta repetía
 * "¿eres empleado?" pese a estar prohibido explícitamente en el prompt,
 * porque todo el cálculo de orden/condiciones vivía en prosa. Estos tests
 * verifican que ActivosResolver lo resuelve en código, de forma imposible de
 * romper para el modelo — usa el mismo tax_year (2025) y el mismo catálogo
 * sembrado por defecto (ver Tests\TestCase) que el resto del suite.
 */
class ActivosResolverTest extends TestCase
{
    use RefreshDatabase;

    private ActivosResolver $resolver;

    private User $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PromptActivoStepsSeeder::class);
        $this->resolver = new ActivosResolver;
        $this->cliente = User::factory()->create(['role' => UserRole::Client]);
    }

    private function pendientes(): array
    {
        return TaxFieldCatalog::pendientesPara(2025, [], $this->cliente->id);
    }

    public function test_el_primer_activo_pendiente_es_identificacion_ssn_itin(): void
    {
        $siguiente = $this->resolver->siguiente(2025, $this->cliente->id, $this->pendientes());

        $this->assertSame('identificacion_ssn_itin', $siguiente['campo']);
    }

    public function test_al_resolver_ssn_el_siguiente_activo_es_estado_civil(): void
    {
        CampoCliente::query()->create([
            'user_id' => $this->cliente->id, 'forma' => 'transversal', 'campo' => 'identificacion_ssn_itin',
            'tax_year' => 2025, 'tipo_campo' => 'dato', 'modo' => 'texto', 'valor_texto' => '123-45-6789',
            'estado' => 'recibido', 'source' => 'agente_ia',
        ]);

        $siguiente = $this->resolver->siguiente(2025, $this->cliente->id, $this->pendientes());

        $this->assertSame('estado_civil', $siguiente['campo']);
    }

    public function test_info_conyuge_se_salta_si_el_cliente_es_soltero(): void
    {
        CampoCliente::query()->create([
            'user_id' => $this->cliente->id, 'forma' => 'transversal', 'campo' => 'identificacion_ssn_itin',
            'tax_year' => 2025, 'tipo_campo' => 'dato', 'modo' => 'texto', 'valor_texto' => '123-45-6789',
            'estado' => 'recibido', 'source' => 'agente_ia',
        ]);
        CampoCliente::query()->create([
            'user_id' => $this->cliente->id, 'forma' => 'transversal', 'campo' => 'estado_civil',
            'tax_year' => 2025, 'tipo_campo' => 'dato', 'modo' => 'texto',
            'valor_texto' => ['casado_al_31_dic' => false], 'estado' => 'recibido', 'source' => 'agente_ia',
        ]);

        $siguiente = $this->resolver->siguiente(2025, $this->cliente->id, $this->pendientes());

        // Salta info_conyuge (soltero) y pasa directo a info_dependientes.
        $this->assertSame('info_dependientes', $siguiente['campo']);
    }

    public function test_info_conyuge_se_pregunta_si_el_cliente_es_casado(): void
    {
        CampoCliente::query()->create([
            'user_id' => $this->cliente->id, 'forma' => 'transversal', 'campo' => 'identificacion_ssn_itin',
            'tax_year' => 2025, 'tipo_campo' => 'dato', 'modo' => 'texto', 'valor_texto' => '123-45-6789',
            'estado' => 'recibido', 'source' => 'agente_ia',
        ]);
        CampoCliente::query()->create([
            'user_id' => $this->cliente->id, 'forma' => 'transversal', 'campo' => 'estado_civil',
            'tax_year' => 2025, 'tipo_campo' => 'dato', 'modo' => 'texto',
            'valor_texto' => ['casado_al_31_dic' => true], 'estado' => 'recibido', 'source' => 'agente_ia',
        ]);

        $siguiente = $this->resolver->siguiente(2025, $this->cliente->id, $this->pendientes());

        $this->assertSame('info_conyuge', $siguiente['campo']);
    }

    public function test_la_bifurcacion_de_empleo_se_devuelve_mientras_ninguno_de_los_dos_este_resuelto(): void
    {
        foreach (['identificacion_ssn_itin', 'estado_civil', 'info_dependientes'] as $campo) {
            CampoCliente::query()->create([
                'user_id' => $this->cliente->id, 'forma' => 'transversal', 'campo' => $campo,
                'tax_year' => 2025, 'tipo_campo' => 'dato', 'modo' => 'no_aplica',
                'valor_texto' => null, 'estado' => 'no_aplica', 'source' => 'agente_ia',
            ]);
        }

        $siguiente = $this->resolver->siguiente(2025, $this->cliente->id, $this->pendientes());

        $this->assertSame('bifurcacion', $siguiente['tipo']);
        $this->assertSame('w2', $siguiente['campo_si']['campo']);
        $this->assertSame('form_1099_nec', $siguiente['campo_no']['campo']);
    }

    public function test_la_bifurcacion_de_empleo_ya_no_se_devuelve_una_vez_resuelta(): void
    {
        foreach (['identificacion_ssn_itin', 'estado_civil', 'info_dependientes'] as $campo) {
            CampoCliente::query()->create([
                'user_id' => $this->cliente->id, 'forma' => 'transversal', 'campo' => $campo,
                'tax_year' => 2025, 'tipo_campo' => 'dato', 'modo' => 'no_aplica',
                'valor_texto' => null, 'estado' => 'no_aplica', 'source' => 'agente_ia',
            ]);
        }

        CampoCliente::query()->create([
            'user_id' => $this->cliente->id, 'forma' => 'transversal', 'campo' => 'w2',
            'tax_year' => 2025, 'tipo_campo' => 'documento', 'modo' => 'archivo',
            'valor_texto' => 'https://ejemplo.com/w2.pdf', 'estado' => 'recibido', 'source' => 'agente_ia',
        ]);
        CampoCliente::query()->create([
            'user_id' => $this->cliente->id, 'forma' => 'transversal', 'campo' => 'form_1099_nec',
            'tax_year' => 2025, 'tipo_campo' => 'documento', 'modo' => 'no_aplica',
            'valor_texto' => null, 'estado' => 'no_aplica', 'source' => 'agente_ia',
        ]);

        $siguiente = $this->resolver->siguiente(2025, $this->cliente->id, $this->pendientes());

        // Ya resuelta la bifurcación, el próximo ACTIVO es form_1095_a — nunca
        // vuelve a ofrecerse "¿eres empleado?" (ver GROUNDING ESTRICTO).
        $this->assertSame('form_1095_a', $siguiente['campo']);
    }

    /**
     * Resuelve los 33 pasos ACTIVOS sembrados hoy por PromptActivoStepsSeeder
     * (los 6 originales + los 4 de compliance de la Fase 1 + los 16 de
     * documentos promovidos de la Fase 2 + los 7 de identidad del
     * contribuyente de la Fase 3a — ver esa clase), dejando la conversación
     * en "no queda ningún ACTIVO real pendiente". cuentas_extranjero_detalle
     * se salta solo (cuentas_extranjero queda en "no"), así que no hace
     * falta resolverlo acá aparte.
     */
    private function marcarTodosLosActivosComoResueltos(): void
    {
        foreach (['identificacion_ssn_itin', 'estado_civil', 'info_dependientes', 'w2', 'form_1099_nec', 'form_1095_a'] as $campo) {
            CampoCliente::query()->create([
                'user_id' => $this->cliente->id, 'forma' => 'transversal', 'campo' => $campo,
                'tax_year' => 2025, 'tipo_campo' => 'dato', 'modo' => 'no_aplica',
                'valor_texto' => null, 'estado' => 'no_aplica', 'source' => 'agente_ia',
            ]);
        }

        foreach ([
            'activos_digitales', 'cuentas_extranjero', 'puede_ser_reclamado_como_dependiente',
            'vivio_trabajo_fuera_eeuu',
        ] as $campo) {
            CampoCliente::query()->create([
                'user_id' => $this->cliente->id, 'forma' => 'transversal', 'campo' => $campo,
                'tax_year' => 2025, 'tipo_campo' => 'dato', 'modo' => 'texto',
                'valor_texto' => 'no', 'estado' => 'recibido', 'source' => 'agente_ia',
            ]);
        }

        foreach (['fecha_nacimiento_contribuyente', 'ocupacion'] as $campo) {
            CampoCliente::query()->create([
                'user_id' => $this->cliente->id, 'forma' => 'transversal', 'campo' => $campo,
                'tax_year' => 2025, 'tipo_campo' => 'dato', 'modo' => 'texto',
                'valor_texto' => 'x', 'estado' => 'recibido', 'source' => 'agente_ia',
            ]);
        }

        CampoCliente::query()->create([
            'user_id' => $this->cliente->id, 'forma' => 'transversal', 'campo' => 'direccion_contribuyente',
            'tax_year' => 2025, 'tipo_campo' => 'dato', 'modo' => 'texto',
            'valor_texto' => ['calle' => 'x'], 'estado' => 'recibido', 'source' => 'agente_ia',
        ]);

        foreach ([
            'venta_residencia_principal', 'form_1099_r', 'ssa_1099', 'form_1099_int', 'form_1099_div',
            'form_1099_b', 'form_1099_g', 'form_1098', 'form_1098_e', 'form_1099_misc', 'form_1099_k',
            'form_1099_s', 'k1_recibido', 'form_w2g', 'form_1099_c', 'form_1099_sa', 'form_5498_sa',
            'declaracion_anio_anterior', 'ip_pin', 'form_8332',
            // Fase 3c
            'salarios_empleado_domestico', 'intereses_exentos_impuestos', 'mejoras_propiedad_vendida',
            'venta_a_plazos', 'retiro_rollover_o_conversion_roth', 'retiro_distribucion_anticipada',
            'railroad_retirement', 'perdida_capital_arrastrada', 'compensacion_acciones',
            'k1_distribuciones_recibidas', 'k1_perdidas_pasivas_o_basis_pendiente',
            // Fase 3d
            'foreclosure_abandono_propiedad', 'premios_hobby', 'ingresos_demanda_legal', 'alimony_recibido',
            'contribuciones_ira_sep_simple', 'seguro_medico_self_employed', 'gastos_educador',
            'mejoras_eficiencia_energetica', 'gastos_adopcion', 'regalos_herencia_extranjero', 'foreign_trust',
            'cartas_irs', 'declaracion_enmendada', 'bankruptcy',
        ] as $campo) {
            CampoCliente::query()->create([
                'user_id' => $this->cliente->id, 'forma' => 'transversal', 'campo' => $campo,
                'tax_year' => 2025, 'tipo_campo' => 'mixto', 'modo' => 'no_aplica',
                'valor_texto' => null, 'estado' => 'no_aplica', 'source' => 'agente_ia',
            ]);
        }
    }

    /**
     * Los únicos campos `transversal` obligatorio:false hoy ya están
     * asignados a los pasos Simple/Condicional/Bifurcacion/DocumentoConNota
     * sembrados por PromptActivoStepsSeeder (ver esa clase) — promover
     * campos reales (ej. los `documentos_extra` como form_1099_int) a un
     * grupo es trabajo de la Fase 2 del plan, no de esta (Fase 0, solo
     * arquitectura). Estos tests siembran dos campos `transversal`
     * sintéticos, exclusivos de este archivo, en un `orden` alto (999) para
     * no chocar con ningún paso real de producción, para probar `Grupo` sin
     * tocar el catálogo real ni el seeder de producción.
     */
    private function seedGrupoDePrueba(): void
    {
        foreach (['grupo_prueba_a', 'grupo_prueba_b'] as $clave) {
            CampoCatalogo::query()->create([
                'forma' => 'transversal', 'tax_year' => 2025, 'clave' => $clave,
                'tipo_campo' => FieldKind::Documento, 'formatos_aceptados' => ['pdf'],
                'obligatorio' => false, 'sensible' => false, 'unico_por_cliente' => true,
            ]);
        }

        PromptActivoStep::query()->create([
            'orden' => 999,
            'tipo' => TipoPromptActivoStep::Grupo,
            'etiqueta' => 'Grupo de prueba',
            'pregunta' => '¿Tuviste alguno de estos?',
            'miembros' => ['grupo_prueba_a', 'grupo_prueba_b'],
        ]);
    }

    public function test_el_grupo_se_devuelve_con_todos_los_miembros_pendientes(): void
    {
        $this->marcarTodosLosActivosComoResueltos();
        $this->seedGrupoDePrueba();

        $siguiente = $this->resolver->siguiente(2025, $this->cliente->id, $this->pendientes());

        $this->assertSame('grupo', $siguiente['tipo']);
        $this->assertSame('Grupo de prueba', $siguiente['etiqueta']);
        $this->assertCount(2, $siguiente['miembros']);
        $this->assertSame('grupo_prueba_a', $siguiente['miembros'][0]['campo']);
        $this->assertSame('grupo_prueba_b', $siguiente['miembros'][1]['campo']);
    }

    public function test_el_grupo_solo_devuelve_los_miembros_que_faltan_cuando_esta_parcialmente_resuelto(): void
    {
        $this->marcarTodosLosActivosComoResueltos();
        $this->seedGrupoDePrueba();

        CampoCliente::query()->create([
            'user_id' => $this->cliente->id, 'forma' => 'transversal', 'campo' => 'grupo_prueba_a',
            'tax_year' => 2025, 'tipo_campo' => 'documento', 'modo' => 'no_aplica',
            'valor_texto' => null, 'estado' => 'no_aplica', 'source' => 'agente_ia',
        ]);

        $siguiente = $this->resolver->siguiente(2025, $this->cliente->id, $this->pendientes());

        $this->assertSame('grupo', $siguiente['tipo']);
        $this->assertCount(1, $siguiente['miembros']);
        $this->assertSame('grupo_prueba_b', $siguiente['miembros'][0]['campo']);
    }

    public function test_el_grupo_ya_no_se_devuelve_una_vez_que_todos_sus_miembros_estan_resueltos(): void
    {
        $this->marcarTodosLosActivosComoResueltos();
        $this->seedGrupoDePrueba();

        foreach (['grupo_prueba_a', 'grupo_prueba_b'] as $campo) {
            CampoCliente::query()->create([
                'user_id' => $this->cliente->id, 'forma' => 'transversal', 'campo' => $campo,
                'tax_year' => 2025, 'tipo_campo' => 'documento', 'modo' => 'no_aplica',
                'valor_texto' => null, 'estado' => 'no_aplica', 'source' => 'agente_ia',
            ]);
        }

        $siguiente = $this->resolver->siguiente(2025, $this->cliente->id, $this->pendientes());

        $this->assertNull($siguiente);
    }

    public function test_devuelve_null_cuando_ya_no_queda_ningun_activo_pendiente(): void
    {
        $this->marcarTodosLosActivosComoResueltos();

        $siguiente = $this->resolver->siguiente(2025, $this->cliente->id, $this->pendientes());

        $this->assertNull($siguiente);
    }

    /**
     * Fase 1 del plan de cierre de brecha GTS (compliance crítico P1) —
     * activos_digitales/cuentas_extranjero/venta_residencia_principal se
     * agregaron al final de la lista (orden 7-10), después de form_1095_a.
     */
    public function test_despues_de_form_1095_a_el_siguiente_activo_es_activos_digitales(): void
    {
        foreach (['identificacion_ssn_itin', 'estado_civil', 'info_dependientes', 'w2', 'form_1099_nec', 'form_1095_a'] as $campo) {
            CampoCliente::query()->create([
                'user_id' => $this->cliente->id, 'forma' => 'transversal', 'campo' => $campo,
                'tax_year' => 2025, 'tipo_campo' => 'dato', 'modo' => 'no_aplica',
                'valor_texto' => null, 'estado' => 'no_aplica', 'source' => 'agente_ia',
            ]);
        }

        $siguiente = $this->resolver->siguiente(2025, $this->cliente->id, $this->pendientes());

        $this->assertSame('activos_digitales', $siguiente['campo']);
    }

    public function test_cuentas_extranjero_detalle_se_salta_si_el_cliente_respondio_que_no(): void
    {
        foreach (['identificacion_ssn_itin', 'estado_civil', 'info_dependientes', 'w2', 'form_1099_nec', 'form_1095_a'] as $campo) {
            CampoCliente::query()->create([
                'user_id' => $this->cliente->id, 'forma' => 'transversal', 'campo' => $campo,
                'tax_year' => 2025, 'tipo_campo' => 'dato', 'modo' => 'no_aplica',
                'valor_texto' => null, 'estado' => 'no_aplica', 'source' => 'agente_ia',
            ]);
        }
        CampoCliente::query()->create([
            'user_id' => $this->cliente->id, 'forma' => 'transversal', 'campo' => 'activos_digitales',
            'tax_year' => 2025, 'tipo_campo' => 'dato', 'modo' => 'texto',
            'valor_texto' => 'no', 'estado' => 'recibido', 'source' => 'agente_ia',
        ]);
        CampoCliente::query()->create([
            'user_id' => $this->cliente->id, 'forma' => 'transversal', 'campo' => 'cuentas_extranjero',
            'tax_year' => 2025, 'tipo_campo' => 'dato', 'modo' => 'texto',
            'valor_texto' => 'no', 'estado' => 'recibido', 'source' => 'agente_ia',
        ]);

        $siguiente = $this->resolver->siguiente(2025, $this->cliente->id, $this->pendientes());

        // Salta cuentas_extranjero_detalle (respondió "no") y pasa directo a
        // venta_residencia_principal.
        $this->assertSame('venta_residencia_principal', $siguiente['campo']);
    }

    public function test_cuentas_extranjero_detalle_se_pregunta_si_el_cliente_respondio_que_si(): void
    {
        foreach (['identificacion_ssn_itin', 'estado_civil', 'info_dependientes', 'w2', 'form_1099_nec', 'form_1095_a'] as $campo) {
            CampoCliente::query()->create([
                'user_id' => $this->cliente->id, 'forma' => 'transversal', 'campo' => $campo,
                'tax_year' => 2025, 'tipo_campo' => 'dato', 'modo' => 'no_aplica',
                'valor_texto' => null, 'estado' => 'no_aplica', 'source' => 'agente_ia',
            ]);
        }
        CampoCliente::query()->create([
            'user_id' => $this->cliente->id, 'forma' => 'transversal', 'campo' => 'activos_digitales',
            'tax_year' => 2025, 'tipo_campo' => 'dato', 'modo' => 'texto',
            'valor_texto' => 'no', 'estado' => 'recibido', 'source' => 'agente_ia',
        ]);
        CampoCliente::query()->create([
            'user_id' => $this->cliente->id, 'forma' => 'transversal', 'campo' => 'cuentas_extranjero',
            'tax_year' => 2025, 'tipo_campo' => 'dato', 'modo' => 'texto',
            'valor_texto' => 'si', 'estado' => 'recibido', 'source' => 'agente_ia',
        ]);

        $siguiente = $this->resolver->siguiente(2025, $this->cliente->id, $this->pendientes());

        $this->assertSame('cuentas_extranjero_detalle', $siguiente['campo']);
    }

    /**
     * Deja resueltos los pasos 1-33 salvo w2/form_1099_nec/mas_w2, con w2
     * "recibido" (un Documento real, para que tieneAlMenosUnDocumento() lo
     * cuente) — el estado exacto en el que mas_w2 (Fase 3b, múltiples W-2)
     * debería empezar a ofrecerse.
     */
    private function marcarTodoResueltoConUnW2YaEntregado(): void
    {
        foreach (['identificacion_ssn_itin', 'estado_civil', 'info_dependientes', 'form_1095_a'] as $campo) {
            CampoCliente::query()->create([
                'user_id' => $this->cliente->id, 'forma' => 'transversal', 'campo' => $campo,
                'tax_year' => 2025, 'tipo_campo' => 'dato', 'modo' => 'no_aplica',
                'valor_texto' => null, 'estado' => 'no_aplica', 'source' => 'agente_ia',
            ]);
        }

        $documento = Documento::query()->create([
            'user_id' => $this->cliente->id, 'forma' => 'transversal', 'tax_year' => 2025, 'campo' => 'w2',
            'file_path' => 'documentos/w2.pdf', 'file_original_name' => 'w2.pdf', 'file_mime_type' => 'application/pdf',
            'file_size' => 100, 'formato' => 'pdf', 'estado_validacion' => 'recibido',
        ]);

        CampoCliente::query()->create([
            'user_id' => $this->cliente->id, 'forma' => 'transversal', 'campo' => 'w2',
            'tax_year' => 2025, 'tipo_campo' => 'documento', 'modo' => 'archivo',
            'valor_texto' => null, 'documento_id' => $documento->id, 'estado' => 'recibido', 'source' => 'agente_ia',
        ]);

        CampoCliente::query()->create([
            'user_id' => $this->cliente->id, 'forma' => 'transversal', 'campo' => 'form_1099_nec',
            'tax_year' => 2025, 'tipo_campo' => 'documento', 'modo' => 'no_aplica',
            'valor_texto' => null, 'estado' => 'no_aplica', 'source' => 'agente_ia',
        ]);

        foreach ([
            'activos_digitales', 'cuentas_extranjero', 'puede_ser_reclamado_como_dependiente',
            'vivio_trabajo_fuera_eeuu',
        ] as $campo) {
            CampoCliente::query()->create([
                'user_id' => $this->cliente->id, 'forma' => 'transversal', 'campo' => $campo,
                'tax_year' => 2025, 'tipo_campo' => 'dato', 'modo' => 'texto',
                'valor_texto' => 'no', 'estado' => 'recibido', 'source' => 'agente_ia',
            ]);
        }

        foreach (['fecha_nacimiento_contribuyente', 'ocupacion'] as $campo) {
            CampoCliente::query()->create([
                'user_id' => $this->cliente->id, 'forma' => 'transversal', 'campo' => $campo,
                'tax_year' => 2025, 'tipo_campo' => 'dato', 'modo' => 'texto',
                'valor_texto' => 'x', 'estado' => 'recibido', 'source' => 'agente_ia',
            ]);
        }

        CampoCliente::query()->create([
            'user_id' => $this->cliente->id, 'forma' => 'transversal', 'campo' => 'direccion_contribuyente',
            'tax_year' => 2025, 'tipo_campo' => 'dato', 'modo' => 'texto',
            'valor_texto' => ['calle' => 'x'], 'estado' => 'recibido', 'source' => 'agente_ia',
        ]);

        foreach ([
            'venta_residencia_principal', 'form_1099_r', 'ssa_1099', 'form_1099_int', 'form_1099_div',
            'form_1099_b', 'form_1099_g', 'form_1098', 'form_1098_e', 'form_1099_misc', 'form_1099_k',
            'form_1099_s', 'k1_recibido', 'form_w2g', 'form_1099_c', 'form_1099_sa', 'form_5498_sa',
            'declaracion_anio_anterior', 'ip_pin', 'form_8332',
            // Fase 3c
            'salarios_empleado_domestico', 'intereses_exentos_impuestos', 'mejoras_propiedad_vendida',
            'venta_a_plazos', 'retiro_rollover_o_conversion_roth', 'retiro_distribucion_anticipada',
            'railroad_retirement', 'perdida_capital_arrastrada', 'compensacion_acciones',
            'k1_distribuciones_recibidas', 'k1_perdidas_pasivas_o_basis_pendiente',
            // Fase 3d
            'foreclosure_abandono_propiedad', 'premios_hobby', 'ingresos_demanda_legal', 'alimony_recibido',
            'contribuciones_ira_sep_simple', 'seguro_medico_self_employed', 'gastos_educador',
            'mejoras_eficiencia_energetica', 'gastos_adopcion', 'regalos_herencia_extranjero', 'foreign_trust',
            'cartas_irs', 'declaracion_enmendada', 'bankruptcy',
        ] as $campo) {
            CampoCliente::query()->create([
                'user_id' => $this->cliente->id, 'forma' => 'transversal', 'campo' => $campo,
                'tax_year' => 2025, 'tipo_campo' => 'mixto', 'modo' => 'no_aplica',
                'valor_texto' => null, 'estado' => 'no_aplica', 'source' => 'agente_ia',
            ]);
        }
    }

    public function test_mas_w2_se_ofrece_una_vez_que_el_cliente_ya_entrego_al_menos_un_w2(): void
    {
        $this->marcarTodoResueltoConUnW2YaEntregado();

        $siguiente = $this->resolver->siguiente(2025, $this->cliente->id, $this->pendientes());

        $this->assertSame('mas_w2', $siguiente['campo']);
    }

    public function test_mas_w2_deja_de_ofrecerse_una_vez_guardado_como_no(): void
    {
        $this->marcarTodoResueltoConUnW2YaEntregado();

        CampoCliente::query()->create([
            'user_id' => $this->cliente->id, 'forma' => 'transversal', 'campo' => 'mas_w2',
            'tax_year' => 2025, 'tipo_campo' => 'dato', 'modo' => 'texto',
            'valor_texto' => 'no', 'estado' => 'recibido', 'source' => 'agente_ia',
        ]);

        $siguiente = $this->resolver->siguiente(2025, $this->cliente->id, $this->pendientes());

        $this->assertNull($siguiente);
    }
}
