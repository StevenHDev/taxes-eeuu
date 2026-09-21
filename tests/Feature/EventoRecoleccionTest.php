<?php

namespace Tests\Feature;

use App\Enums\ApiAbility;
use App\Enums\UserRole;
use App\Models\CampoCatalogo;
use App\Models\CampoCliente;
use App\Models\ClientIntakeSession;
use App\Models\Documento;
use App\Models\FormaCliente;
use App\Models\HistorialCambio;
use App\Models\RelacionDocumentoCampo;
use App\Models\User;
use App\Services\EventoRecoleccionService;
use App\Support\TaxFieldCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventoRecoleccionTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAgente(): User
    {
        $agente = User::factory()->create(['role' => UserRole::Administrator, 'name' => 'Agente conversacional']);

        Sanctum::actingAs($agente, [ApiAbility::EventosWrite->value]);

        return $agente;
    }

    /**
     * El seeder solo carga el catálogo del año base (2025) — estos tests
     * necesitan que un campo puntual también exista en otro año fiscal, tal
     * como haría un admin al extender el catálogo a un año nuevo.
     */
    private function extenderCampoAlAno(string $forma, string $clave, int $anio): void
    {
        $original = CampoCatalogo::query()
            ->where('forma', $forma)
            ->where('clave', $clave)
            ->where('tax_year', 2025)
            ->firstOrFail();

        CampoCatalogo::query()->create([
            'forma' => $forma,
            'clave' => $clave,
            'tax_year' => $anio,
            'tipo_campo' => $original->tipo_campo,
            'tipo_dato' => $original->tipo_dato,
            'formatos_aceptados' => $original->formatos_aceptados,
            'subcampos' => $original->subcampos,
            'obligatorio' => $original->obligatorio,
            'sensible' => $original->sensible,
            'unico_por_cliente' => $original->unico_por_cliente,
        ]);

        TaxFieldCatalog::invalidate();
    }

    /**
     * @return array<string, float>
     */
    private function ingresosPayload(float $salarios = 52000): array
    {
        return [
            'salarios' => $salarios,
            'intereses_dividendos' => 0,
            'ganancias_capital' => 0,
            'ingresos_jubilacion' => 0,
            'otros_ingresos' => 0,
            'ajustes_ingreso' => 0,
            'seguridad_social' => 0,
        ];
    }

    public function test_un_evento_sin_cliente_id_crea_un_cliente_nuevo_y_lo_devuelve(): void
    {
        $this->actingAsAgente();

        $response = $this->postJson('/api/eventos', [
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'ingresos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'object',
            'contenido' => $this->ingresosPayload(),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('estado', 'recibido');

        $clienteId = $response->json('cliente_id');
        $this->assertIsInt($clienteId);
        $this->assertSame(UserRole::Client, User::find($clienteId)->role);
    }

    public function test_external_ref_deduplica_la_creacion_del_cliente(): void
    {
        $this->actingAsAgente();

        $primero = $this->postJson('/api/eventos', [
            'external_ref' => 'whatsapp:+15551234567',
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'ingresos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'object',
            'contenido' => $this->ingresosPayload(1000),
        ])->assertCreated();

        $segundo = $this->postJson('/api/eventos', [
            'external_ref' => 'whatsapp:+15551234567',
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'impuestos_retenidos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'number',
            'contenido' => 200,
        ])->assertCreated();

        $this->assertSame($primero->json('cliente_id'), $segundo->json('cliente_id'));
        $this->assertSame(1, ClientIntakeSession::query()->count());
        $this->assertSame(1, User::query()->where('role', UserRole::Client)->count());
    }

    public function test_el_telefono_deduplica_la_creacion_del_cliente(): void
    {
        $this->actingAsAgente();

        $primero = $this->postJson('/api/eventos', [
            'phone' => '+15559876543',
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'ingresos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'object',
            'contenido' => $this->ingresosPayload(1000),
        ])->assertCreated();

        $segundo = $this->postJson('/api/eventos', [
            'phone' => '+15559876543',
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'impuestos_retenidos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'number',
            'contenido' => 200,
        ])->assertCreated();

        $this->assertSame($primero->json('cliente_id'), $segundo->json('cliente_id'));
        $this->assertSame(1, User::query()->where('role', UserRole::Client)->count());
        $this->assertSame('+15559876543', User::find($primero->json('cliente_id'))->phone);
    }

    public function test_reenviar_el_mismo_campo_sobrescribe_y_registra_historial(): void
    {
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'ingresos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'object',
            'contenido' => $this->ingresosPayload(1000),
        ])->assertCreated();

        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'ingresos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'object',
            'contenido' => $this->ingresosPayload(2000),
        ])->assertCreated();

        $this->assertSame(1, CampoCliente::query()->where('user_id', $cliente->id)->where('campo', 'ingresos')->count());

        $campo = CampoCliente::query()->where('user_id', $cliente->id)->where('campo', 'ingresos')->first();
        $this->assertSame(2000.0, (float) $campo->valor['salarios']);

        $historial = HistorialCambio::query()->where('user_id', $cliente->id)->where('campo', 'ingresos')->latest('id')->first();
        $this->assertSame(1000.0, (float) $historial->valor_anterior['salarios']);
        $this->assertSame(2000.0, (float) $historial->valor_nuevo['salarios']);
    }

    public function test_contenido_invalido_se_persiste_como_invalido_y_no_cuenta_para_completitud(): void
    {
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'identificacion_ssn_itin',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'string',
            'contenido' => 'no-es-un-ssn',
        ])->assertCreated()
            ->assertJsonPath('estado', 'invalido');

        $campo = CampoCliente::query()->where('user_id', $cliente->id)->where('campo', 'identificacion_ssn_itin')->first();
        $this->assertNotNull($campo, 'El evento inválido igual debe conservarse para trazabilidad.');
    }

    /**
     * Regresión de un caso real (2026-09-21, conversación con 3213445027): el
     * agente guardó estado_civil completo, luego el cliente corrigió un solo
     * subcampo en un mensaje aparte, y el reenvío (con solo ese subcampo)
     * quedó `invalido` por faltarle el resto — el cliente tuvo que repetir
     * toda la información. Ahora el reenvío se fusiona con lo ya guardado.
     */
    public function test_un_envio_parcial_de_un_campo_objeto_se_fusiona_con_lo_ya_guardado(): void
    {
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'transversal',
            'tax_year' => 2025,
            'campo' => 'estado_civil',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'object',
            'contenido' => [
                'casado_al_31_dic' => false, 'convivio_conyuge_ultimos_6_meses' => false,
                'costeo_mas_mitad_hogar' => false, 'existe_persona_calificable' => true,
            ],
        ])->assertCreated()->assertJsonPath('estado', 'recibido');

        // Solo el subcampo que se está corrigiendo — así llega un reenvío
        // real cuando el agente pide una aclaración puntual.
        $response = $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'transversal',
            'tax_year' => 2025,
            'campo' => 'estado_civil',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'object',
            'contenido' => ['existe_persona_calificable' => false],
        ])->assertCreated();

        $response->assertJsonPath('estado', 'recibido');

        $campo = CampoCliente::query()->where('user_id', $cliente->id)->where('campo', 'estado_civil')->first();
        $this->assertFalse($campo->valor['casado_al_31_dic']);
        $this->assertFalse($campo->valor['existe_persona_calificable']);
    }

    /**
     * Mismo caso que el anterior, pero para info_dependientes (lista de
     * objetos, no un objeto simple) — el bug real en producción fue
     * justamente sobre la info de un dependiente completada en varios
     * mensajes.
     */
    public function test_un_envio_parcial_de_un_elemento_de_array_object_se_fusiona_cuando_el_largo_no_cambia(): void
    {
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'transversal',
            'tax_year' => 2025,
            'campo' => 'info_dependientes',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'array_object',
            'contenido' => [[
                'nombre_completo' => 'Karol Perez', 'fecha_nacimiento' => '2000-05-20', 'ssn' => '987654321',
                'relacion' => 'hija', 'meses_en_hogar' => 6, 'estudiante_tiempo_completo' => true,
                'discapacitado' => false, 'provee_mas_50_soporte_propio' => false,
                'ingreso_bruto_anual' => 10000, 'custodia_compartida_sin_conflicto' => true,
            ]],
        ])->assertCreated()->assertJsonPath('estado', 'recibido');

        // El agente pide solo la aclaración de meses_en_hogar — el reenvío
        // trae nada más ese subcampo, para ese mismo (único) dependiente.
        $response = $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'transversal',
            'tax_year' => 2025,
            'campo' => 'info_dependientes',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'array_object',
            'contenido' => [['meses_en_hogar' => 12]],
        ])->assertCreated();

        $response->assertJsonPath('estado', 'recibido');

        $campo = CampoCliente::query()->where('user_id', $cliente->id)->where('campo', 'info_dependientes')->first();
        // info_dependientes es sensible: `valor` enmascara — se compara
        // contra `valor_texto` (ya desencriptado por el cast del modelo)
        // para verificar el dato real, no la versión enmascarada.
        $this->assertSame('Karol Perez', $campo->valor_texto[0]['nombre_completo']);
        $this->assertSame(12, $campo->valor_texto[0]['meses_en_hogar']);
    }

    /**
     * La fusión de array_object solo tiene sentido índice a índice cuando el
     * largo no cambió — con un dependiente nuevo agregado (o quitado), no
     * hay forma de saber a cuál correspondía cada corrección, así que se
     * confía en el envío nuevo tal cual (y, si viene incompleto, queda
     * inválido como antes — no es una regresión, es la falta de fusión
     * funcionando como se espera en el caso ambiguo).
     */
    public function test_array_object_con_largo_distinto_no_se_fusiona(): void
    {
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $completo = [
            'nombre_completo' => 'Karol Perez', 'fecha_nacimiento' => '2000-05-20', 'ssn' => '987654321',
            'relacion' => 'hija', 'meses_en_hogar' => 12, 'estudiante_tiempo_completo' => true,
            'discapacitado' => false, 'provee_mas_50_soporte_propio' => false,
            'ingreso_bruto_anual' => 10000, 'custodia_compartida_sin_conflicto' => true,
        ];

        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'transversal',
            'tax_year' => 2025,
            'campo' => 'info_dependientes',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'array_object',
            'contenido' => [$completo],
        ])->assertCreated()->assertJsonPath('estado', 'recibido');

        $response = $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'transversal',
            'tax_year' => 2025,
            'campo' => 'info_dependientes',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'array_object',
            // Un segundo dependiente incompleto — largo distinto (2 vs 1).
            'contenido' => [$completo, ['nombre_completo' => 'Otro Hijo']],
        ])->assertCreated();

        $response->assertJsonPath('estado', 'invalido');
    }

    /**
     * Regresión de un caso real (2026-09-21): "20 de mayo del 2000" y
     * "20/05/2000" (DD/MM/YYYY, como escribe la mayoría de los clientes de
     * este producto) fallaban Carbon::parse() y marcaban info_dependientes
     * completo `invalido` aunque el dato estuviera bien.
     */
    public function test_fecha_nacimiento_acepta_formato_dd_mm_yyyy(): void
    {
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $response = $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'transversal',
            'tax_year' => 2025,
            'campo' => 'info_dependientes',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'array_object',
            'contenido' => [[
                'nombre_completo' => 'Karol Perez', 'fecha_nacimiento' => '20/05/2000', 'ssn' => '987654321',
                'relacion' => 'hija', 'meses_en_hogar' => 12, 'estudiante_tiempo_completo' => true,
                'discapacitado' => false, 'provee_mas_50_soporte_propio' => false,
                'ingreso_bruto_anual' => 10000, 'custodia_compartida_sin_conflicto' => true,
            ]],
        ])->assertCreated();

        $response->assertJsonPath('estado', 'recibido');
    }

    public function test_fecha_nacimiento_sigue_rechazando_texto_que_no_es_una_fecha(): void
    {
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $response = $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'transversal',
            'tax_year' => 2025,
            'campo' => 'info_dependientes',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'array_object',
            'contenido' => [[
                'nombre_completo' => 'Karol Perez', 'fecha_nacimiento' => 'no es una fecha', 'ssn' => '987654321',
                'relacion' => 'hija', 'meses_en_hogar' => 12, 'estudiante_tiempo_completo' => true,
                'discapacitado' => false, 'provee_mas_50_soporte_propio' => false,
                'ingreso_bruto_anual' => 10000, 'custodia_compartida_sin_conflicto' => true,
            ]],
        ])->assertCreated();

        $response->assertJsonPath('estado', 'invalido');
    }

    public function test_un_campo_unico_por_cliente_no_se_duplica_entre_formas(): void
    {
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        // El mismo dato personal (SSN) llega en eventos de dos formas distintas.
        foreach (['form_1040', 'schedule_c'] as $forma) {
            $this->postJson('/api/eventos', [
                'cliente_id' => $cliente->id,
                'forma' => $forma,
                'tax_year' => 2025,
                'campo' => 'identificacion_ssn_itin',
                'tipo_campo' => 'dato',
                'modo' => 'texto',
                'tipo_dato' => 'string',
                'contenido' => '123-45-6789',
            ])->assertCreated();
        }

        // Debe existir una sola fila, bajo la forma canónica 'transversal'.
        $filas = CampoCliente::query()
            ->where('user_id', $cliente->id)
            ->where('campo', 'identificacion_ssn_itin')
            ->get();

        $this->assertCount(1, $filas);
        $this->assertSame('transversal', $filas->first()->forma);
    }

    public function test_un_dato_unico_se_puede_enviar_con_forma_transversal(): void
    {
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'transversal',
            'tax_year' => 2025,
            'campo' => 'identificacion_ssn_itin',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'string',
            'contenido' => '123-45-6789',
        ])->assertCreated()->assertJsonPath('estado', 'recibido');

        $this->assertDatabaseHas('campos_cliente', [
            'user_id' => $cliente->id,
            'forma' => 'transversal',
            'tax_year' => 2025,
            'campo' => 'identificacion_ssn_itin',
        ]);
    }

    public function test_un_campo_de_forma_no_se_puede_enviar_como_transversal(): void
    {
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        // 'ingresos' pertenece a una forma, no es transversal → 422.
        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'transversal',
            'tax_year' => 2025,
            'campo' => 'ingresos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'object',
            'contenido' => $this->ingresosPayload(),
        ])->assertStatus(422)->assertJsonValidationErrors(['campo']);
    }

    public function test_dependientes_ya_no_existe_en_form_1040(): void
    {
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'dependientes',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'array_object',
            'contenido' => [],
        ])->assertStatus(422)->assertJsonValidationErrors(['campo']);
    }

    public function test_la_forma_permanece_en_progreso_mientras_falten_campos_requeridos(): void
    {
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'ingresos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'object',
            'contenido' => $this->ingresosPayload(1000),
        ])->assertCreated();

        $forma = FormaCliente::query()->where('user_id', $cliente->id)->where('forma', 'form_1040')->first();
        $this->assertSame('en_progreso', $forma->estado->value);
    }

    public function test_la_forma_se_marca_completa_cuando_todos_los_campos_requeridos_estan_recibidos(): void
    {
        Storage::fake('s3');
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $datos = [
            ['campo' => 'identificacion_ssn_itin', 'tipo_campo' => 'dato', 'tipo_dato' => 'string', 'contenido' => '123456789'],
            // Fase 1 del plan de cierre de brecha GTS: activos_digitales y
            // cuentas_extranjero son obligatorio:true desde ahora — sin
            // estos dos, form_1040 nunca llega a "completo".
            ['campo' => 'activos_digitales', 'tipo_campo' => 'dato', 'tipo_dato' => 'string', 'contenido' => 'no'],
            ['campo' => 'cuentas_extranjero', 'tipo_campo' => 'dato', 'tipo_dato' => 'string', 'contenido' => 'no'],
            // Fase 3a del plan de cierre de brecha GTS: 5 campos más
            // obligatorio:true (identidad del contribuyente).
            ['campo' => 'fecha_nacimiento_contribuyente', 'tipo_campo' => 'dato', 'tipo_dato' => 'string', 'contenido' => '1990-01-01'],
            ['campo' => 'direccion_contribuyente', 'tipo_campo' => 'dato', 'tipo_dato' => 'object', 'contenido' => [
                'calle' => '123 Main St', 'ciudad' => 'Miami', 'estado' => 'FL', 'codigo_postal' => '33101',
            ]],
            ['campo' => 'ocupacion', 'tipo_campo' => 'dato', 'tipo_dato' => 'string', 'contenido' => 'Contador'],
            ['campo' => 'puede_ser_reclamado_como_dependiente', 'tipo_campo' => 'dato', 'tipo_dato' => 'string', 'contenido' => 'no'],
            ['campo' => 'vivio_trabajo_fuera_eeuu', 'tipo_campo' => 'dato', 'tipo_dato' => 'string', 'contenido' => 'no'],
            // Fase 3b: mas_w2 es obligatorio:true, solo admite "no" (ver
            // EventoRecoleccionService::validarString).
            ['campo' => 'mas_w2', 'tipo_campo' => 'dato', 'tipo_dato' => 'string', 'contenido' => 'no'],
            ['campo' => 'info_conyuge', 'tipo_campo' => 'dato', 'tipo_dato' => 'object', 'contenido' => [
                'nombre_completo' => 'Jane Doe', 'fecha_nacimiento' => '1990-01-01', 'ssn' => '987654321',
            ]],
            ['campo' => 'info_dependientes', 'tipo_campo' => 'dato', 'tipo_dato' => 'array_object', 'contenido' => []],
            ['campo' => 'estado_civil', 'tipo_campo' => 'dato', 'tipo_dato' => 'object', 'contenido' => [
                'casado_al_31_dic' => false, 'convivio_conyuge_ultimos_6_meses' => false, 'costeo_mas_mitad_hogar' => false,
                'existe_persona_calificable' => false, 'conyuge_fallecio_en_anio' => false, 'anio_fallecimiento_conyuge' => null,
            ]],
            ['campo' => 'ingresos', 'tipo_campo' => 'dato', 'tipo_dato' => 'object', 'contenido' => $this->ingresosPayload()],
            // Fase 4 del plan de cierre de brecha GTS: deducciones pasó de
            // Number suelto a objeto por categoría — todos los subcampos
            // deben venir presentes, aunque sea en 0 (mismo patrón que
            // info_dependientes/estado_civil).
            ['campo' => 'deducciones', 'tipo_campo' => 'mixto', 'tipo_dato' => 'object', 'contenido' => [
                'intereses_hipotecarios' => 1000, 'impuestos_propiedad' => 0, 'donaciones_efectivo' => 0,
                'donaciones_bienes' => 0, 'gastos_medicos' => 0, 'intereses_inversion' => 0, 'perdidas_desastre' => 0,
            ]],
            ['campo' => 'impuestos_retenidos', 'tipo_campo' => 'dato', 'tipo_dato' => 'number', 'contenido' => 0],
            ['campo' => 'salarios_medicare', 'tipo_campo' => 'dato', 'tipo_dato' => 'number', 'contenido' => 52000],
            ['campo' => 'info_bancaria', 'tipo_campo' => 'dato', 'tipo_dato' => 'object', 'contenido' => [
                'banco' => 'Banco X', 'tipo_cuenta' => 'checking', 'numero_cuenta' => '123', 'routing_number' => '456',
            ]],
        ];

        foreach ($datos as $campo) {
            $this->postJson('/api/eventos', array_merge([
                'cliente_id' => $cliente->id,
                'forma' => 'form_1040',
                'tax_year' => 2025,
                'modo' => 'texto',
            ], $campo))->assertCreated();
        }

        // w2 y form_1099_nec son únicos por cliente (documentos básicos); aplican a
        // todas las formas. estados_bancarios ya no es de form_1040 (es por negocio).
        $documentos = [
            ['campo' => 'w2', 'nombre' => 'w2.pdf'],
            ['campo' => 'form_1099_nec', 'nombre' => 'f1099.pdf'],
        ];

        foreach ($documentos as $documento) {
            $this->post('/api/eventos', [
                'cliente_id' => $cliente->id,
                'forma' => 'form_1040',
                'tax_year' => 2025,
                'campo' => $documento['campo'],
                'tipo_campo' => 'documento',
                'modo' => 'archivo',
                'file' => UploadedFile::fake()->create($documento['nombre'], 10),
            ])->assertCreated();
        }

        $forma = FormaCliente::query()->where('user_id', $cliente->id)->where('forma', 'form_1040')->first();
        $this->assertSame('completo', $forma->estado->value);
    }

    public function test_archivo_con_formato_no_aceptado_se_marca_invalido(): void
    {
        Storage::fake('s3');
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $response = $this->post('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'w2',
            'tipo_campo' => 'documento',
            'modo' => 'archivo',
            'file' => UploadedFile::fake()->create('w2.exe', 10),
        ], ['Accept' => 'application/json']);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('file');
    }

    public function test_el_valor_se_cifra_en_la_base_de_datos(): void
    {
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'identificacion_ssn_itin',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'string',
            'contenido' => '123456789',
        ])->assertCreated();

        $raw = DB::table('campos_cliente')->where('user_id', $cliente->id)->value('valor_texto');

        $this->assertStringNotContainsString('123456789', $raw);
    }

    public function test_un_token_sin_ability_eventos_write_recibe_403(): void
    {
        $agente = User::factory()->create(['role' => UserRole::Administrator]);
        Sanctum::actingAs($agente, [ApiAbility::ClientesRead->value]);

        $this->postJson('/api/eventos', [
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'ingresos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'number',
            'contenido' => 1,
        ])->assertForbidden();
    }

    public function test_un_evento_sin_tax_year_es_rechazado(): void
    {
        $this->actingAsAgente();

        $this->postJson('/api/eventos', [
            'forma' => 'form_1040',
            'campo' => 'ingresos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'number',
            'contenido' => 1000,
        ])->assertStatus(422)->assertJsonValidationErrors(['tax_year']);
    }

    public function test_el_mismo_campo_en_dos_anos_fiscales_crea_filas_independientes(): void
    {
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'ingresos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'object',
            'contenido' => $this->ingresosPayload(50000),
        ])->assertCreated();

        $this->extenderCampoAlAno('form_1040', 'ingresos', 2026);

        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'form_1040',
            'tax_year' => 2026,
            'campo' => 'ingresos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'object',
            'contenido' => $this->ingresosPayload(60000),
        ])->assertCreated();

        $filas = CampoCliente::query()->where('user_id', $cliente->id)->where('campo', 'ingresos')->get();

        $this->assertCount(2, $filas);
        $this->assertSame(50000.0, (float) $filas->firstWhere('tax_year', 2025)->valor['salarios']);
        $this->assertSame(60000.0, (float) $filas->firstWhere('tax_year', 2026)->valor['salarios']);
    }

    public function test_la_completitud_de_una_forma_es_independiente_por_ano_fiscal(): void
    {
        Storage::fake('s3');
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $datos = [
            ['campo' => 'identificacion_ssn_itin', 'tipo_campo' => 'dato', 'tipo_dato' => 'string', 'contenido' => '123456789'],
            // Fase 1 del plan de cierre de brecha GTS: activos_digitales y
            // cuentas_extranjero son obligatorio:true desde ahora — sin
            // estos dos, form_1040 nunca llega a "completo".
            ['campo' => 'activos_digitales', 'tipo_campo' => 'dato', 'tipo_dato' => 'string', 'contenido' => 'no'],
            ['campo' => 'cuentas_extranjero', 'tipo_campo' => 'dato', 'tipo_dato' => 'string', 'contenido' => 'no'],
            // Fase 3a del plan de cierre de brecha GTS: 5 campos más
            // obligatorio:true (identidad del contribuyente).
            ['campo' => 'fecha_nacimiento_contribuyente', 'tipo_campo' => 'dato', 'tipo_dato' => 'string', 'contenido' => '1990-01-01'],
            ['campo' => 'direccion_contribuyente', 'tipo_campo' => 'dato', 'tipo_dato' => 'object', 'contenido' => [
                'calle' => '123 Main St', 'ciudad' => 'Miami', 'estado' => 'FL', 'codigo_postal' => '33101',
            ]],
            ['campo' => 'ocupacion', 'tipo_campo' => 'dato', 'tipo_dato' => 'string', 'contenido' => 'Contador'],
            ['campo' => 'puede_ser_reclamado_como_dependiente', 'tipo_campo' => 'dato', 'tipo_dato' => 'string', 'contenido' => 'no'],
            ['campo' => 'vivio_trabajo_fuera_eeuu', 'tipo_campo' => 'dato', 'tipo_dato' => 'string', 'contenido' => 'no'],
            // Fase 3b: mas_w2 es obligatorio:true, solo admite "no" (ver
            // EventoRecoleccionService::validarString).
            ['campo' => 'mas_w2', 'tipo_campo' => 'dato', 'tipo_dato' => 'string', 'contenido' => 'no'],
            ['campo' => 'info_conyuge', 'tipo_campo' => 'dato', 'tipo_dato' => 'object', 'contenido' => [
                'nombre_completo' => 'Jane Doe', 'fecha_nacimiento' => '1990-01-01', 'ssn' => '987654321',
            ]],
            ['campo' => 'info_dependientes', 'tipo_campo' => 'dato', 'tipo_dato' => 'array_object', 'contenido' => []],
            ['campo' => 'estado_civil', 'tipo_campo' => 'dato', 'tipo_dato' => 'object', 'contenido' => [
                'casado_al_31_dic' => false, 'convivio_conyuge_ultimos_6_meses' => false, 'costeo_mas_mitad_hogar' => false,
                'existe_persona_calificable' => false, 'conyuge_fallecio_en_anio' => false, 'anio_fallecimiento_conyuge' => null,
            ]],
            ['campo' => 'ingresos', 'tipo_campo' => 'dato', 'tipo_dato' => 'object', 'contenido' => $this->ingresosPayload()],
            // Fase 4 del plan de cierre de brecha GTS: deducciones pasó de
            // Number suelto a objeto por categoría — todos los subcampos
            // deben venir presentes, aunque sea en 0 (mismo patrón que
            // info_dependientes/estado_civil).
            ['campo' => 'deducciones', 'tipo_campo' => 'mixto', 'tipo_dato' => 'object', 'contenido' => [
                'intereses_hipotecarios' => 1000, 'impuestos_propiedad' => 0, 'donaciones_efectivo' => 0,
                'donaciones_bienes' => 0, 'gastos_medicos' => 0, 'intereses_inversion' => 0, 'perdidas_desastre' => 0,
            ]],
            ['campo' => 'impuestos_retenidos', 'tipo_campo' => 'dato', 'tipo_dato' => 'number', 'contenido' => 0],
            ['campo' => 'salarios_medicare', 'tipo_campo' => 'dato', 'tipo_dato' => 'number', 'contenido' => 52000],
            ['campo' => 'info_bancaria', 'tipo_campo' => 'dato', 'tipo_dato' => 'object', 'contenido' => [
                'banco' => 'Banco X', 'tipo_cuenta' => 'checking', 'numero_cuenta' => '123', 'routing_number' => '456',
            ]],
        ];

        // Completa el Form 1040 2025 con todos los campos requeridos.
        foreach ($datos as $campo) {
            $this->postJson('/api/eventos', array_merge([
                'cliente_id' => $cliente->id,
                'forma' => 'form_1040',
                'tax_year' => 2025,
                'modo' => 'texto',
            ], $campo))->assertCreated();
        }

        foreach ([['campo' => 'w2', 'nombre' => 'w2.pdf'], ['campo' => 'form_1099_nec', 'nombre' => 'f1099.pdf']] as $documento) {
            $this->post('/api/eventos', [
                'cliente_id' => $cliente->id,
                'forma' => 'form_1040',
                'tax_year' => 2025,
                'campo' => $documento['campo'],
                'tipo_campo' => 'documento',
                'modo' => 'archivo',
                'file' => UploadedFile::fake()->create($documento['nombre'], 10),
            ])->assertCreated();
        }

        // Inicia (sin completar) el Form 1040 2026 del mismo cliente — el
        // catálogo 2026 necesita al menos dos campos requeridos para que
        // cargar solo uno deje la forma genuinamente en_progreso, no completa.
        $this->extenderCampoAlAno('form_1040', 'ingresos', 2026);
        $this->extenderCampoAlAno('form_1040', 'impuestos_retenidos', 2026);

        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'form_1040',
            'tax_year' => 2026,
            'campo' => 'ingresos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'object',
            'contenido' => $this->ingresosPayload(60000),
        ])->assertCreated();

        $forma2025 = FormaCliente::query()->where('user_id', $cliente->id)->where('forma', 'form_1040')->where('tax_year', 2025)->first();
        $forma2026 = FormaCliente::query()->where('user_id', $cliente->id)->where('forma', 'form_1040')->where('tax_year', 2026)->first();

        $this->assertSame('completo', $forma2025->estado->value);
        $this->assertSame('en_progreso', $forma2026->estado->value);
    }

    public function test_un_campo_unico_por_cliente_no_crea_formas_espurias_en_otro_ano_fiscal(): void
    {
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        // El cliente tiene dos formas activas en 2025: form_1040 y schedule_c.
        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'ingresos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'object',
            'contenido' => $this->ingresosPayload(50000),
        ])->assertCreated();

        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'schedule_c',
            'tax_year' => 2025,
            'campo' => 'ingresos_negocio',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'number',
            'contenido' => 30000,
        ])->assertCreated();

        // En 2026 el cliente solo declara form_1040 (todavía no tiene schedule_c
        // ese año). Un campo único por cliente (SSN) llega para 2026, bajo form_1040.
        $this->extenderCampoAlAno('transversal', 'identificacion_ssn_itin', 2026);

        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'form_1040',
            'tax_year' => 2026,
            'campo' => 'identificacion_ssn_itin',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'string',
            'contenido' => '123456789',
        ])->assertCreated();

        // Sin el escopeo por año en recalcularAfectadas, el pluck de "todas las
        // formas del cliente" habría arrastrado 'schedule_c' de 2025 y creado una
        // fila espuria FormaCliente(schedule_c, 2026) que el cliente nunca inició.
        $formasDe2026 = FormaCliente::query()
            ->where('user_id', $cliente->id)
            ->where('tax_year', 2026)
            ->pluck('forma');

        $this->assertSame(['form_1040'], $formasDe2026->all());
        $this->assertDatabaseMissing('formas_cliente', ['user_id' => $cliente->id, 'forma' => 'schedule_c', 'tax_year' => 2026]);
    }

    public function test_modo_no_aplica_se_acepta_para_un_campo_opcional(): void
    {
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        // form_1098_t es documento y obligatorio: false en el catálogo — el
        // único que queda bajo documentos_extra desde la Fase 2 del plan de
        // cierre de brecha GTS (declaracion_anio_anterior se promovió a
        // transversal — ver CatalogoCamposSeeder).
        $response = $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'documentos_extra',
            'tax_year' => 2025,
            'campo' => 'form_1098_t',
            'tipo_campo' => 'documento',
            'modo' => 'no_aplica',
        ]);

        $response->assertCreated()->assertJsonPath('estado', 'no_aplica');

        $campo = CampoCliente::query()->where('user_id', $cliente->id)->where('campo', 'form_1098_t')->first();
        $this->assertNotNull($campo);
        $this->assertNull($campo->valor);
        $this->assertNull($campo->documento_id);
    }

    public function test_modo_no_aplica_es_rechazado_para_un_campo_obligatorio(): void
    {
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        // ingresos es obligatorio: true — no_aplica no tiene sentido ahí.
        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'ingresos',
            'tipo_campo' => 'dato',
            'modo' => 'no_aplica',
        ])->assertStatus(422)->assertJsonValidationErrors(['modo']);

        $this->assertDatabaseMissing('campos_cliente', ['user_id' => $cliente->id, 'campo' => 'ingresos']);
    }

    public function test_no_aplica_puede_reemplazarse_despues_por_el_archivo_real(): void
    {
        Storage::fake('s3');
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        // form_1098_t es el único campo que queda bajo documentos_extra desde
        // la Fase 2 del plan de cierre de brecha GTS (declaracion_anio_anterior
        // se promovió a transversal — ver CatalogoCamposSeeder).
        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'documentos_extra',
            'tax_year' => 2025,
            'campo' => 'form_1098_t',
            'tipo_campo' => 'documento',
            'modo' => 'no_aplica',
        ])->assertCreated();

        $this->post('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'documentos_extra',
            'tax_year' => 2025,
            'campo' => 'form_1098_t',
            'tipo_campo' => 'documento',
            'modo' => 'archivo',
            'file' => UploadedFile::fake()->create('1098t.pdf', 10),
        ])->assertCreated()->assertJsonPath('estado', 'recibido');

        $campo = CampoCliente::query()->where('user_id', $cliente->id)->where('campo', 'form_1098_t')->first();
        $this->assertSame('recibido', $campo->estado->value);
        $this->assertNotNull($campo->documento_id);

        // El historial conserva ambos movimientos, con el "no_aplica" anterior visible.
        $historial = HistorialCambio::query()->where('user_id', $cliente->id)->where('campo', 'form_1098_t')->orderBy('id')->get();
        $this->assertCount(2, $historial);
        $this->assertSame('no_aplica', $historial->first()->valor_nuevo);
    }

    /**
     * Bug encontrado en pruebas end-to-end: dos documentos revelando el mismo
     * campo numérico simple (ej. w2 y 1099-NEC ambos hacia
     * `impuestos_retenidos`) perdían en silencio la retención del primero —
     * el segundo evento sobrescribía en vez de sumar. `acumular: true` sobre
     * un campo `number` debe sumar sobre lo ya guardado.
     */
    public function test_acumular_suma_sobre_un_campo_numerico_simple(): void
    {
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'impuestos_retenidos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'number',
            'contenido' => 400,
            'acumular' => true,
        ])->assertCreated();

        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'impuestos_retenidos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'number',
            'contenido' => 150,
            'acumular' => true,
        ])->assertCreated();

        $campo = CampoCliente::query()->where('user_id', $cliente->id)->where('campo', 'impuestos_retenidos')->first();
        $this->assertEquals(550.0, $campo->valor_texto);
    }

    /**
     * Mismo bug que el test anterior, pero sobre un subcampo de un campo tipo
     * objeto (ej. `ingresos.intereses_dividendos`, resuelto tanto por un
     * 1099-INT como por un 1099-DIV) — solo el subcampo indicado se suma, los
     * demás se guardan tal como llegan en `contenido`.
     */
    public function test_acumular_suma_sobre_un_subcampo_de_un_campo_objeto(): void
    {
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'ingresos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'object',
            'contenido' => array_merge($this->ingresosPayload(salarios: 0), ['intereses_dividendos' => 500]),
            'acumular' => true,
            'subcampo' => 'intereses_dividendos',
        ])->assertCreated();

        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'ingresos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'object',
            'contenido' => array_merge($this->ingresosPayload(salarios: 0), ['intereses_dividendos' => 1200]),
            'acumular' => true,
            'subcampo' => 'intereses_dividendos',
        ])->assertCreated();

        $campo = CampoCliente::query()->where('user_id', $cliente->id)->where('forma', 'form_1040')->where('campo', 'ingresos')->first();
        $this->assertEquals(1700.0, $campo->valor_texto['intereses_dividendos']);
    }

    public function test_acumular_sin_subcampo_en_un_campo_objeto_es_invalido(): void
    {
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'ingresos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'object',
            'contenido' => $this->ingresosPayload(),
            'acumular' => true,
        ])->assertStatus(422)->assertJsonValidationErrors(['subcampo']);
    }

    public function test_acumular_con_subcampo_inexistente_es_invalido(): void
    {
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'ingresos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'object',
            'contenido' => $this->ingresosPayload(),
            'acumular' => true,
            'subcampo' => 'no_existe',
        ])->assertStatus(422)->assertJsonValidationErrors(['subcampo']);
    }

    public function test_acumular_con_subcampo_sobre_un_campo_numerico_es_invalido(): void
    {
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'impuestos_retenidos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'number',
            'contenido' => 400,
            'acumular' => true,
            'subcampo' => 'algo',
        ])->assertStatus(422)->assertJsonValidationErrors(['subcampo']);
    }

    /**
     * Encontrado en producción: el agente conversacional externo guardaba el
     * documento principal (ej. w2) pero nunca encadenaba las llamadas para
     * guardar los campos de su `revela`, porque esa clave solo vivía en la
     * respuesta de consultar_pendientes_cliente de 1-2 turnos atrás — con
     * prompts largos, modelos más chicos pierden esa referencia. La respuesta
     * de guardar_campo_cliente ahora repite el mismo `revela`, justo en el
     * resultado del guardado que el agente acaba de hacer.
     */
    public function test_la_respuesta_incluye_el_revela_del_documento_guardado(): void
    {
        Storage::fake('s3');
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        // El seeder de relaciones no corre en TestCase::setUp() (solo catálogo
        // y parámetros fiscales) — se siembra acá la única relación que este
        // test necesita.
        RelacionDocumentoCampo::query()->create([
            'documento_forma' => 'transversal',
            'documento_campo' => 'w2',
            'campo_destino_forma' => 'form_1040',
            'campo_destino' => 'ingresos',
            'subcampo_destino' => 'salarios',
            'descripcion' => 'Box 1 del W-2 es el salario total.',
            'acumulable' => false,
            'tax_year' => 2025,
        ]);
        TaxFieldCatalog::invalidate();

        $response = $this->post('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'transversal',
            'tax_year' => 2025,
            'campo' => 'w2',
            'tipo_campo' => 'documento',
            'modo' => 'archivo',
            'file' => UploadedFile::fake()->create('w2.pdf', 10),
        ]);

        $response->assertCreated();
        $revela = $response->json('revela');

        // No se filtra a un único elemento: las migraciones de datos ya
        // siembran también la relación real w2 → salarios_medicare (Box 5),
        // presente en toda base de datos (incluida la de tests) — no solo la
        // que este test crea a mano.
        $salarios = collect($revela)->firstWhere('subcampo', 'salarios');

        $this->assertNotNull($salarios);
        $this->assertSame('form_1040', $salarios['forma']);
        $this->assertSame('ingresos', $salarios['campo']);
        $this->assertSame(false, $salarios['acumulable']);
        // El agente necesita el tipo_campo/tipo_dato del campo DESTINO (no del
        // documento) para armar el item de `revelados` sin adivinarlos —
        // encontrado en producción: sin esto, el agente asumía "dato"/"number"
        // en vez del tipo real en el catálogo ("dato"/"object" para `ingresos`,
        // o "mixto" para campos como gastos_cuidado_dependientes).
        $this->assertSame('dato', $salarios['tipo_campo']);
        $this->assertSame('object', $salarios['tipo_dato']);
    }

    /**
     * Bug real reportado en producción: `gastos_cuidado_dependientes` está
     * catalogado como tipo_campo="mixto" (acepta documento o texto), pero el
     * agente enviaba tipo_campo="dato" en el revelado porque `revela` no le
     * decía el tipo real del campo destino y point 10 decía "siempre dato".
     * Ahora `revela` expone el tipo_campo real, y el agente solo tiene que
     * copiarlo — nunca asumirlo.
     */
    public function test_revela_expone_tipo_campo_mixto_para_un_campo_destino_mixto(): void
    {
        Storage::fake('s3');
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        RelacionDocumentoCampo::query()->create([
            'documento_forma' => 'transversal',
            'documento_campo' => 'w2',
            'campo_destino_forma' => 'form_1040',
            'campo_destino' => 'gastos_cuidado_dependientes',
            'subcampo_destino' => 'monto_anual',
            'descripcion' => 'Box 10 del W-2 es el monto anual de beneficios de cuidado de dependientes.',
            'acumulable' => false,
            'tax_year' => 2025,
        ]);
        TaxFieldCatalog::invalidate();

        $response = $this->post('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'transversal',
            'tax_year' => 2025,
            'campo' => 'w2',
            'tipo_campo' => 'documento',
            'modo' => 'archivo',
            'file' => UploadedFile::fake()->create('w2.pdf', 10),
        ]);

        $response->assertCreated();
        $revela = $response->json('revela');

        $gastosCuidado = collect($revela)->firstWhere('campo', 'gastos_cuidado_dependientes');
        $this->assertNotNull($gastosCuidado);
        $this->assertSame('mixto', $gastosCuidado['tipo_campo']);
        $this->assertSame('object', $gastosCuidado['tipo_dato']);
    }

    public function test_la_respuesta_trae_revela_vacio_para_un_campo_sin_relaciones(): void
    {
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $response = $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'transversal',
            'tax_year' => 2025,
            'campo' => 'identificacion_ssn_itin',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'string',
            'contenido' => '123-45-6789',
        ]);

        $response->assertCreated()->assertJsonPath('revela', []);
    }

    /**
     * Encontrado en producción: aunque el backend y el prompt ya eran
     * correctos, un modelo más chico (gpt-5-mini) no siempre decidía invocar
     * guardar_campo_cliente una segunda vez para cada campo de `revela` —
     * confirmado que era una limitación de razonamiento multi-paso del
     * modelo, no de configuración (un modelo más grande sí encadenaba bien).
     * `revelados` elimina esa necesidad: todo se resuelve en una sola
     * invocación, algo que hasta un modelo chico arma de forma confiable.
     */
    public function test_revelados_guarda_el_campo_principal_y_los_revelados_en_una_sola_llamada(): void
    {
        Storage::fake('s3');
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $response = $this->post('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'transversal',
            'tax_year' => 2025,
            'campo' => 'w2',
            'tipo_campo' => 'documento',
            'modo' => 'archivo',
            'file' => UploadedFile::fake()->create('w2.pdf', 10),
            'revelados' => [
                [
                    'forma' => 'schedule_c',
                    'campo' => 'ingresos_negocio',
                    'tipo_campo' => 'dato',
                    'tipo_dato' => 'number',
                    'contenido' => 9600,
                ],
                [
                    'forma' => 'form_1040',
                    'campo' => 'impuestos_retenidos',
                    'tipo_campo' => 'dato',
                    'tipo_dato' => 'number',
                    'contenido' => 1200,
                    'acumular' => true,
                ],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonCount(2, 'revelados');
        $response->assertJsonPath('revelados.0.estado', 'recibido');
        $response->assertJsonPath('revelados.1.estado', 'recibido');

        $this->assertDatabaseHas('campos_cliente', [
            'user_id' => $cliente->id,
            'forma' => 'transversal',
            'campo' => 'w2',
        ]);

        $ingresoNegocio = CampoCliente::query()->where('user_id', $cliente->id)->where('campo', 'ingresos_negocio')->first();
        $this->assertEquals(9600.0, $ingresoNegocio->valor_texto);

        $impuestosRetenidos = CampoCliente::query()->where('user_id', $cliente->id)->where('campo', 'impuestos_retenidos')->first();
        $this->assertEquals(1200.0, $impuestosRetenidos->valor_texto);
    }

    public function test_revelados_acumulable_suma_sobre_lo_ya_guardado(): void
    {
        Storage::fake('s3');
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'impuestos_retenidos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'number',
            'contenido' => 400,
            'acumular' => true,
        ])->assertCreated();

        $this->post('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'transversal',
            'tax_year' => 2025,
            'campo' => 'w2',
            'tipo_campo' => 'documento',
            'modo' => 'archivo',
            'file' => UploadedFile::fake()->create('w2.pdf', 10),
            'revelados' => [
                [
                    'forma' => 'form_1040',
                    'campo' => 'impuestos_retenidos',
                    'tipo_campo' => 'dato',
                    'tipo_dato' => 'number',
                    'contenido' => 300,
                    'acumular' => true,
                ],
            ],
        ])->assertCreated();

        $campo = CampoCliente::query()->where('user_id', $cliente->id)->where('campo', 'impuestos_retenidos')->first();
        $this->assertEquals(700.0, $campo->valor_texto);
    }

    /**
     * Encontrado al reproducir el caso real vía curl/multipart (como lo
     * envía n8n): esta API siempre envía los parámetros como texto (ver
     * `docs/prompt.md`, punto 8 de guardar_campo_cliente), así que `acumular`
     * llega como el STRING "true"/"false", nunca un boolean nativo. La regla
     * `boolean` de Laravel rechaza esos strings con 422, y un cast `(bool)`
     * directo sobre el string "false" da `true` en PHP (cualquier string no
     * vacío es "truthy") — ambos bugs reales, corregidos en EventoRequest y
     * EventoRecoleccionService.
     */
    public function test_acumular_como_texto_true_o_false_se_interpreta_correctamente(): void
    {
        Storage::fake('s3');
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        // Raíz: acumular="true" (string) debe aceptarse y sumar.
        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'impuestos_retenidos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'number',
            'contenido' => 400,
            'acumular' => 'true',
        ])->assertCreated();

        // Documento con dos revelados: uno con acumular="true" (debe sumar) y
        // otro con acumular="false" (debe sobrescribir, NUNCA tratarse como true
        // por ser un string no vacío).
        $this->post('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'transversal',
            'tax_year' => 2025,
            'campo' => 'w2',
            'tipo_campo' => 'documento',
            'modo' => 'archivo',
            'file' => UploadedFile::fake()->create('w2.pdf', 10),
            'revelados' => [
                [
                    'forma' => 'form_1040',
                    'campo' => 'impuestos_retenidos',
                    'tipo_campo' => 'dato',
                    'tipo_dato' => 'number',
                    'contenido' => 300,
                    'acumular' => 'true',
                ],
                [
                    'forma' => 'schedule_c',
                    'campo' => 'ingresos_negocio',
                    'tipo_campo' => 'dato',
                    'tipo_dato' => 'number',
                    'contenido' => 9600,
                    'acumular' => 'false',
                ],
            ],
        ])->assertCreated();

        $impuestosRetenidos = CampoCliente::query()->where('user_id', $cliente->id)->where('campo', 'impuestos_retenidos')->first();
        $this->assertEquals(700.0, $impuestosRetenidos->valor_texto);

        $ingresoNegocio = CampoCliente::query()->where('user_id', $cliente->id)->where('campo', 'ingresos_negocio')->first();
        $this->assertEquals(9600.0, $ingresoNegocio->valor_texto);
    }

    public function test_revelados_con_tipo_dato_que_no_coincide_con_catalogo_es_invalido(): void
    {
        Storage::fake('s3');
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->post('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'transversal',
            'tax_year' => 2025,
            'campo' => 'w2',
            'tipo_campo' => 'documento',
            'modo' => 'archivo',
            'file' => UploadedFile::fake()->create('w2.pdf', 10),
            'revelados' => [
                [
                    'forma' => 'schedule_c',
                    'campo' => 'ingresos_negocio',
                    'tipo_campo' => 'dato',
                    'tipo_dato' => 'string',
                    'contenido' => 'no es un número',
                ],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['revelados.0.tipo_dato']);
    }

    public function test_revelados_no_puede_apuntar_a_un_campo_tipo_documento(): void
    {
        Storage::fake('s3');
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->post('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'transversal',
            'tax_year' => 2025,
            'campo' => 'w2',
            'tipo_campo' => 'documento',
            'modo' => 'archivo',
            'file' => UploadedFile::fake()->create('w2.pdf', 10),
            'revelados' => [
                [
                    'forma' => 'transversal',
                    'campo' => 'form_1099_nec',
                    'tipo_campo' => 'documento',
                    'tipo_dato' => 'number',
                    'contenido' => 'x',
                ],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['revelados.0.campo']);
    }

    public function test_sin_revelados_la_respuesta_trae_un_arreglo_vacio(): void
    {
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'transversal',
            'tax_year' => 2025,
            'campo' => 'identificacion_ssn_itin',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'string',
            'contenido' => '123-45-6789',
        ])->assertCreated()->assertJsonPath('revelados', []);
    }

    /**
     * Bug real reportado en producción: el nodo Tool de n8n envía `revelados`
     * como un string JSON (no como campos exploded revelados[0][forma]=...)
     * cuando la request es multipart/form-data (necesaria por el `file` del
     * documento) — Laravel rechazaba esto con 422 "validation.array" porque
     * nunca se decodificaba. Ver EventoRequest::prepareForValidation().
     */
    public function test_revelados_como_string_json_en_multipart_se_decodifica_correctamente(): void
    {
        Storage::fake('s3');
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $revelados = json_encode([
            [
                'forma' => 'schedule_c',
                'campo' => 'ingresos_negocio',
                'tipo_campo' => 'dato',
                'tipo_dato' => 'number',
                'contenido' => '9600',
            ],
        ]);

        $this->post('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'transversal',
            'tax_year' => 2025,
            'campo' => 'form_1099_nec',
            'tipo_campo' => 'documento',
            'modo' => 'archivo',
            'file' => UploadedFile::fake()->create('1099nec.pdf', 10),
            'revelados' => $revelados,
        ])->assertCreated()->assertJsonPath('revelados.0.estado', 'recibido');

        $campo = CampoCliente::query()->where('user_id', $cliente->id)->where('campo', 'ingresos_negocio')->first();
        $this->assertEquals(9600.0, $campo->valor_texto);
    }

    /**
     * Mismo bug que el test anterior, pero sobre `contenido` cuando su
     * tipo_dato es object/array — también llega como string JSON en una
     * request multipart, y también se decodifica en prepareForValidation().
     */
    public function test_contenido_tipo_object_como_string_json_en_multipart_se_decodifica_correctamente(): void
    {
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->post('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'ingresos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'object',
            'contenido' => json_encode(['salarios' => '55665']),
            'subcampo' => 'salarios',
        ])->assertCreated();

        $campo = CampoCliente::query()->where('user_id', $cliente->id)->where('campo', 'ingresos')->first();
        $this->assertEquals('recibido', $campo->estado->value);
        $this->assertEquals(55665.0, $campo->valor_texto['salarios']);
    }

    /**
     * Bug real reportado en producción: un W-2 revela `ingresos.salarios` con
     * `acumulable: false` (solo un W-2 aporta ese subcampo). Antes de este fix,
     * guardar ese subcampo sobrescribía TODO el objeto `ingresos`, borrando
     * subcampos que otro documento (ej. un SSA-1099 con `seguridad_social`) ya
     * hubiera guardado. Ver EventoRecoleccionService::resolverSubcampo().
     */
    public function test_subcampo_no_acumulable_preserva_los_demas_subcampos_ya_guardados(): void
    {
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'ingresos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'object',
            'contenido' => ['seguridad_social' => '18200.50'],
            'subcampo' => 'seguridad_social',
        ])->assertCreated();

        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'ingresos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'object',
            'contenido' => ['salarios' => '55665'],
            'subcampo' => 'salarios',
        ])->assertCreated();

        $campo = CampoCliente::query()->where('user_id', $cliente->id)->where('campo', 'ingresos')->first();
        $this->assertEquals('recibido', $campo->estado->value);
        $this->assertEquals(55665.0, $campo->valor_texto['salarios']);
        $this->assertEquals(18200.5, $campo->valor_texto['seguridad_social']);
        $this->assertEquals(0.0, $campo->valor_texto['intereses_dividendos']);
    }

    /**
     * Bug real reportado en producción: un cliente soltero respondía las 4
     * preguntas de estado_civil que sí aplican, pero el agente nunca
     * pregunta (ni el prompt instruye rellenar con un valor neutro) los 2
     * subcampos de viudez — el objeto quedaba Invalido por faltarle esas 2
     * claves, así que consultar_pendientes_cliente lo seguía devolviendo
     * como pendiente y el agente repetía la misma pregunta indefinidamente.
     * Ver EventoRecoleccionService::SUBCAMPOS_OPCIONALES.
     */
    public function test_estado_civil_sin_datos_de_viudez_es_valido_para_un_cliente_soltero(): void
    {
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $response = $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'transversal',
            'tax_year' => 2025,
            'campo' => 'estado_civil',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'object',
            'contenido' => [
                'casado_al_31_dic' => false,
                'convivio_conyuge_ultimos_6_meses' => false,
                'costeo_mas_mitad_hogar' => true,
                'existe_persona_calificable' => true,
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('estado', 'recibido');
    }

    /**
     * Mismo bug que el test anterior, para info_conyuge: el prompt instruye
     * explícitamente omitir fecha_nacimiento del JSON guardado (limitación
     * temporal documentada), pero el objeto igual exigía esa clave.
     */
    public function test_info_conyuge_sin_fecha_nacimiento_es_valido(): void
    {
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $response = $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'transversal',
            'tax_year' => 2025,
            'campo' => 'info_conyuge',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'object',
            'contenido' => [
                'nombre_completo' => 'Jane Doe',
                'ssn' => '987654321',
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('estado', 'recibido');
    }

    /**
     * Control: un objeto SIN subcampos opcionales conocidos (info_bancaria)
     * sigue exigiendo todas sus claves — el fix de SUBCAMPOS_OPCIONALES es
     * una excepción puntual, no una relajación general de la validación.
     */
    public function test_info_bancaria_incompleta_sigue_siendo_invalida(): void
    {
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $response = $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'info_bancaria',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'object',
            'contenido' => ['banco' => 'Banco X', 'tipo_cuenta' => 'checking'],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('estado', 'invalido');
    }

    /**
     * revalidarValorExistente() repara, sin re-enviar el evento, una fila que
     * quedó Invalido por el bug ya corregido — el caso real fue un cliente
     * cuyo estado_civil quedó atascado como pendiente en el agente de
     * WhatsApp porque nunca pasaba de Invalido a Recibido.
     */
    public function test_revalidar_valor_existente_corrige_un_campo_invalido_por_el_bug_ya_arreglado(): void
    {
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $campo = CampoCliente::query()->create([
            'user_id' => $cliente->id,
            'forma' => 'transversal',
            'tax_year' => 2025,
            'campo' => 'estado_civil',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'valor_texto' => [
                'casado_al_31_dic' => false,
                'convivio_conyuge_ultimos_6_meses' => false,
                'costeo_mas_mitad_hogar' => true,
                'existe_persona_calificable' => true,
            ],
            'estado' => 'invalido',
            'source' => 'agente_ia',
        ]);

        $actualizada = app(EventoRecoleccionService::class)->revalidarValorExistente($campo);

        $this->assertSame('recibido', $actualizada->estado->value);
    }

    /**
     * Segundo bug real, más grave, del mismo caso en producción:
     * ToolExecutor no decodificaba `contenido` antes de guardarlo (ver
     * EventoValidator::decodificarContenido) — un campo tipo objeto del
     * agente de WhatsApp quedaba con valor_texto literalmente el string
     * JSON crudo, no un array. revalidarValorExistente() también repara
     * este caso: decodifica el string y persiste el array real (no solo
     * re-evalúa el estado sobre el string, que seguiría siendo Invalido).
     */
    public function test_revalidar_valor_existente_decodifica_un_valor_texto_guardado_como_string_json(): void
    {
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $contenido = [
            'casado_al_31_dic' => false,
            'convivio_conyuge_ultimos_6_meses' => false,
            'costeo_mas_mitad_hogar' => true,
            'existe_persona_calificable' => true,
            'conyuge_fallecio_en_anio' => false,
            'anio_fallecimiento_conyuge' => null,
        ];

        $campo = CampoCliente::query()->create([
            'user_id' => $cliente->id,
            'forma' => 'transversal',
            'tax_year' => 2025,
            'campo' => 'estado_civil',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'valor_texto' => json_encode($contenido),
            'estado' => 'invalido',
            'source' => 'agente_ia',
        ]);

        $this->assertIsString($campo->fresh()->valor_texto, 'Precondición: reproduce el bug real (guardado como string, no array).');

        $actualizada = app(EventoRecoleccionService::class)->revalidarValorExistente($campo);

        $this->assertSame('recibido', $actualizada->estado->value);
        $this->assertIsArray($actualizada->valor_texto);
        $this->assertTrue($actualizada->valor_texto['costeo_mas_mitad_hogar']);
    }

    /**
     * Bug real evaluando un W-2 de ejemplo: `deducciones` terminó guardado
     * con el mismo valor exacto que `impuestos_retenidos` (ambos 3291.79, el
     * número de Box 2 reutilizado por error) — no existía ninguna relación
     * documento→campo que justificara eso. Este guardarraíl no bloquea el
     * guardado, solo deja una nota (`advertencia`) para que el preparador lo
     * revise. Ver EventoRecoleccionService::detectarValorDuplicado().
     */
    public function test_un_campo_numerico_con_el_mismo_valor_que_otro_ya_guardado_queda_con_advertencia(): void
    {
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'impuestos_retenidos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'number',
            'contenido' => 3291.79,
        ])->assertCreated();

        // pagos_estimados (Dato, Number, distinto de 'deducciones', que
        // desde la Fase 4 es un objeto por categoría — ver
        // CatalogoCamposSeeder) como segundo campo numérico simple.
        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'pagos_estimados',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'number',
            'contenido' => 3291.79,
        ])->assertCreated();

        $retenido = CampoCliente::query()->where('user_id', $cliente->id)->where('campo', 'impuestos_retenidos')->first();
        $pagosEstimados = CampoCliente::query()->where('user_id', $cliente->id)->where('campo', 'pagos_estimados')->first();

        $this->assertNull($retenido->advertencia);
        $this->assertNotNull($pagosEstimados->advertencia);
        $this->assertStringContainsString('impuestos_retenidos', $pagosEstimados->advertencia);
    }

    public function test_dos_campos_numericos_con_valores_distintos_no_generan_advertencia(): void
    {
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'impuestos_retenidos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'number',
            'contenido' => 3291.79,
        ])->assertCreated();

        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'pagos_estimados',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'number',
            'contenido' => 1500,
        ])->assertCreated();

        $pagosEstimados = CampoCliente::query()->where('user_id', $cliente->id)->where('campo', 'pagos_estimados')->first();
        $this->assertNull($pagosEstimados->advertencia);
    }

    public function test_dos_campos_numericos_en_cero_no_generan_advertencia(): void
    {
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'impuestos_retenidos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'number',
            'contenido' => 0,
        ])->assertCreated();

        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'pagos_estimados',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'number',
            'contenido' => 0,
        ])->assertCreated();

        $pagosEstimados = CampoCliente::query()->where('user_id', $cliente->id)->where('campo', 'pagos_estimados')->first();
        $this->assertNull($pagosEstimados->advertencia);
    }

    public function test_corregir_el_valor_duplicado_limpia_la_advertencia(): void
    {
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'impuestos_retenidos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'number',
            'contenido' => 3291.79,
        ])->assertCreated();

        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'pagos_estimados',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'number',
            'contenido' => 3291.79,
        ])->assertCreated();

        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'pagos_estimados',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'number',
            'contenido' => 4200,
        ])->assertCreated();

        $pagosEstimados = CampoCliente::query()->where('user_id', $cliente->id)->where('campo', 'pagos_estimados')->first();
        $this->assertNull($pagosEstimados->advertencia);
    }

    /**
     * Fase 3b del plan de cierre de brecha GTS (múltiples W-2): sin
     * acumular=true, subir un segundo archivo para el mismo campo reemplaza
     * y BORRA el anterior — comportamiento ya existente, que este test deja
     * como regresión guardada antes de tocar aplicarCambio() para el caso
     * acumular=true de abajo.
     */
    public function test_sin_acumular_el_segundo_archivo_reemplaza_y_borra_el_anterior(): void
    {
        Storage::fake('s3');
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->post('/api/eventos', [
            'cliente_id' => $cliente->id, 'forma' => 'transversal', 'tax_year' => 2025,
            'campo' => 'w2', 'tipo_campo' => 'documento', 'modo' => 'archivo',
            'file' => UploadedFile::fake()->create('w2_empleador_1.pdf', 10),
        ])->assertCreated();

        $primerDocumentoId = CampoCliente::query()->where('user_id', $cliente->id)->where('campo', 'w2')->first()->documento_id;

        $this->post('/api/eventos', [
            'cliente_id' => $cliente->id, 'forma' => 'transversal', 'tax_year' => 2025,
            'campo' => 'w2', 'tipo_campo' => 'documento', 'modo' => 'archivo',
            'file' => UploadedFile::fake()->create('w2_corregido.pdf', 10),
        ])->assertCreated();

        $this->assertDatabaseMissing('documentos', ['id' => $primerDocumentoId]);
        $this->assertSame(1, Documento::query()->where('user_id', $cliente->id)->count());
    }

    public function test_acumular_true_en_modo_archivo_agrega_un_documento_sin_borrar_el_anterior(): void
    {
        Storage::fake('s3');
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->post('/api/eventos', [
            'cliente_id' => $cliente->id, 'forma' => 'transversal', 'tax_year' => 2025,
            'campo' => 'w2', 'tipo_campo' => 'documento', 'modo' => 'archivo',
            'file' => UploadedFile::fake()->create('w2_empleador_1.pdf', 10),
        ])->assertCreated();

        $primerDocumentoId = CampoCliente::query()->where('user_id', $cliente->id)->where('campo', 'w2')->first()->documento_id;

        $this->post('/api/eventos', [
            'cliente_id' => $cliente->id, 'forma' => 'transversal', 'tax_year' => 2025,
            'campo' => 'w2', 'tipo_campo' => 'documento', 'modo' => 'archivo', 'acumular' => true,
            'file' => UploadedFile::fake()->create('w2_empleador_2.pdf', 10),
        ])->assertCreated();

        // Ninguno de los dos documentos se borró.
        $this->assertDatabaseHas('documentos', ['id' => $primerDocumentoId]);
        $this->assertSame(2, Documento::query()->where('user_id', $cliente->id)->count());

        $campo = CampoCliente::query()->where('user_id', $cliente->id)->where('campo', 'w2')->first();

        // documento_id apunta al más reciente (para compatibilidad con los
        // paneles que hoy solo leen el documento_id de la fila).
        $segundoDocumentoId = $campo->documento_id;
        $this->assertNotSame($primerDocumentoId, $segundoDocumentoId);

        // valor_texto acumula la referencia a AMBOS documentos, en orden.
        $this->assertSame([
            ['documento_id' => $primerDocumentoId, 'file_original_name' => 'w2_empleador_1.pdf'],
            ['documento_id' => $segundoDocumentoId, 'file_original_name' => 'w2_empleador_2.pdf'],
        ], $campo->valor_texto);
    }

    /**
     * Bug real que este cambio previene: sin la relación w2→salarios marcada
     * acumulable, un segundo W-2 sobrescribiría el salario del primero en
     * vez de sumarlo — ver RelacionesDocumentoCampoSeeder.
     */
    public function test_dos_w2_acumulan_salarios_e_impuestos_retenidos_via_revelados(): void
    {
        Storage::fake('s3');
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->post('/api/eventos', [
            'cliente_id' => $cliente->id, 'forma' => 'transversal', 'tax_year' => 2025,
            'campo' => 'w2', 'tipo_campo' => 'documento', 'modo' => 'archivo',
            'file' => UploadedFile::fake()->create('w2_empleador_1.pdf', 10),
            'revelados' => [
                ['forma' => 'form_1040', 'campo' => 'ingresos', 'tipo_campo' => 'dato', 'tipo_dato' => 'object', 'subcampo' => 'salarios', 'contenido' => ['salarios' => 40000], 'acumular' => true],
            ],
        ])->assertCreated();

        $this->post('/api/eventos', [
            'cliente_id' => $cliente->id, 'forma' => 'transversal', 'tax_year' => 2025,
            'campo' => 'w2', 'tipo_campo' => 'documento', 'modo' => 'archivo', 'acumular' => true,
            'file' => UploadedFile::fake()->create('w2_empleador_2.pdf', 10),
            'revelados' => [
                ['forma' => 'form_1040', 'campo' => 'ingresos', 'tipo_campo' => 'dato', 'tipo_dato' => 'object', 'subcampo' => 'salarios', 'contenido' => ['salarios' => 25000], 'acumular' => true],
            ],
        ])->assertCreated();

        $ingresos = CampoCliente::query()->where('user_id', $cliente->id)->where('campo', 'ingresos')->first();
        $this->assertEquals(65000.0, $ingresos->valor_texto['salarios']);
    }
}
