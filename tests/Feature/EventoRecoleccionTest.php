<?php

namespace Tests\Feature;

use App\Enums\ApiAbility;
use App\Enums\UserRole;
use App\Models\CampoCatalogo;
use App\Models\CampoCliente;
use App\Models\ClientIntakeSession;
use App\Models\FormaCliente;
use App\Models\HistorialCambio;
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
}
