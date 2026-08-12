<?php

namespace Tests\Feature;

use App\Enums\ApiAbility;
use App\Enums\UserRole;
use App\Models\CampoCatalogo;
use App\Models\CampoCliente;
use App\Models\ClientIntakeSession;
use App\Models\FormaCliente;
use App\Models\HistorialCambio;
use App\Models\RelacionDocumentoCampo;
use App\Models\User;
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
        Storage::fake('local');
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $datos = [
            ['campo' => 'identificacion_ssn_itin', 'tipo_campo' => 'dato', 'tipo_dato' => 'string', 'contenido' => '123456789'],
            ['campo' => 'info_conyuge', 'tipo_campo' => 'dato', 'tipo_dato' => 'object', 'contenido' => [
                'nombre_completo' => 'Jane Doe', 'fecha_nacimiento' => '1990-01-01', 'ssn' => '987654321',
            ]],
            ['campo' => 'info_dependientes', 'tipo_campo' => 'dato', 'tipo_dato' => 'array_object', 'contenido' => []],
            ['campo' => 'estado_civil', 'tipo_campo' => 'dato', 'tipo_dato' => 'object', 'contenido' => [
                'casado_al_31_dic' => false, 'convivio_conyuge_ultimos_6_meses' => false, 'costeo_mas_mitad_hogar' => false,
                'existe_persona_calificable' => false, 'conyuge_fallecio_en_anio' => false, 'anio_fallecimiento_conyuge' => null,
            ]],
            ['campo' => 'ingresos', 'tipo_campo' => 'dato', 'tipo_dato' => 'object', 'contenido' => $this->ingresosPayload()],
            ['campo' => 'deducciones', 'tipo_campo' => 'mixto', 'tipo_dato' => 'number', 'contenido' => 1000],
            ['campo' => 'impuestos_retenidos', 'tipo_campo' => 'dato', 'tipo_dato' => 'number', 'contenido' => 0],
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
        Storage::fake('local');
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
        Storage::fake('local');
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $datos = [
            ['campo' => 'identificacion_ssn_itin', 'tipo_campo' => 'dato', 'tipo_dato' => 'string', 'contenido' => '123456789'],
            ['campo' => 'info_conyuge', 'tipo_campo' => 'dato', 'tipo_dato' => 'object', 'contenido' => [
                'nombre_completo' => 'Jane Doe', 'fecha_nacimiento' => '1990-01-01', 'ssn' => '987654321',
            ]],
            ['campo' => 'info_dependientes', 'tipo_campo' => 'dato', 'tipo_dato' => 'array_object', 'contenido' => []],
            ['campo' => 'estado_civil', 'tipo_campo' => 'dato', 'tipo_dato' => 'object', 'contenido' => [
                'casado_al_31_dic' => false, 'convivio_conyuge_ultimos_6_meses' => false, 'costeo_mas_mitad_hogar' => false,
                'existe_persona_calificable' => false, 'conyuge_fallecio_en_anio' => false, 'anio_fallecimiento_conyuge' => null,
            ]],
            ['campo' => 'ingresos', 'tipo_campo' => 'dato', 'tipo_dato' => 'object', 'contenido' => $this->ingresosPayload()],
            ['campo' => 'deducciones', 'tipo_campo' => 'mixto', 'tipo_dato' => 'number', 'contenido' => 1000],
            ['campo' => 'impuestos_retenidos', 'tipo_campo' => 'dato', 'tipo_dato' => 'number', 'contenido' => 0],
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

        // declaracion_anio_anterior es documento y obligatorio: false en el catálogo.
        $response = $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'transversal',
            'tax_year' => 2025,
            'campo' => 'declaracion_anio_anterior',
            'tipo_campo' => 'documento',
            'modo' => 'no_aplica',
        ]);

        $response->assertCreated()->assertJsonPath('estado', 'no_aplica');

        $campo = CampoCliente::query()->where('user_id', $cliente->id)->where('campo', 'declaracion_anio_anterior')->first();
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
        Storage::fake('local');
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'transversal',
            'tax_year' => 2025,
            'campo' => 'declaracion_anio_anterior',
            'tipo_campo' => 'documento',
            'modo' => 'no_aplica',
        ])->assertCreated();

        $this->post('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'transversal',
            'tax_year' => 2025,
            'campo' => 'declaracion_anio_anterior',
            'tipo_campo' => 'documento',
            'modo' => 'archivo',
            'file' => UploadedFile::fake()->create('1040_2024.pdf', 10),
        ])->assertCreated()->assertJsonPath('estado', 'recibido');

        $campo = CampoCliente::query()->where('user_id', $cliente->id)->where('campo', 'declaracion_anio_anterior')->first();
        $this->assertSame('recibido', $campo->estado->value);
        $this->assertNotNull($campo->documento_id);

        // El historial conserva ambos movimientos, con el "no_aplica" anterior visible.
        $historial = HistorialCambio::query()->where('user_id', $cliente->id)->where('campo', 'declaracion_anio_anterior')->orderBy('id')->get();
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
        Storage::fake('local');
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

        $this->assertCount(1, $revela);
        $this->assertSame('form_1040', $revela[0]['forma']);
        $this->assertSame('ingresos', $revela[0]['campo']);
        $this->assertSame('salarios', $revela[0]['subcampo']);
        $this->assertSame(false, $revela[0]['acumulable']);
        // El agente necesita el tipo_campo/tipo_dato del campo DESTINO (no del
        // documento) para armar el item de `revelados` sin adivinarlos —
        // encontrado en producción: sin esto, el agente asumía "dato"/"number"
        // en vez del tipo real en el catálogo ("dato"/"object" para `ingresos`,
        // o "mixto" para campos como gastos_cuidado_dependientes).
        $this->assertSame('dato', $revela[0]['tipo_campo']);
        $this->assertSame('object', $revela[0]['tipo_dato']);
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
        Storage::fake('local');
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
        Storage::fake('local');
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
        Storage::fake('local');
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
        Storage::fake('local');
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
        Storage::fake('local');
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
        Storage::fake('local');
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
        Storage::fake('local');
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
}
