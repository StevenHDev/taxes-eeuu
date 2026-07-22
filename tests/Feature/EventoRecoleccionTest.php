<?php

namespace Tests\Feature;

use App\Enums\ApiAbility;
use App\Enums\UserRole;
use App\Models\CampoCliente;
use App\Models\ClientIntakeSession;
use App\Models\FormaCliente;
use App\Models\HistorialCambio;
use App\Models\User;
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

    public function test_un_evento_sin_cliente_id_crea_un_cliente_nuevo_y_lo_devuelve(): void
    {
        $this->actingAsAgente();

        $response = $this->postJson('/api/eventos', [
            'forma' => 'form_1040',
            'campo' => 'ingresos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'number',
            'contenido' => 52000,
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
            'campo' => 'ingresos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'number',
            'contenido' => 1000,
        ])->assertCreated();

        $segundo = $this->postJson('/api/eventos', [
            'external_ref' => 'whatsapp:+15551234567',
            'forma' => 'form_1040',
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

    public function test_reenviar_el_mismo_campo_sobrescribe_y_registra_historial(): void
    {
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'form_1040',
            'campo' => 'ingresos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'number',
            'contenido' => 1000,
        ])->assertCreated();

        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'form_1040',
            'campo' => 'ingresos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'number',
            'contenido' => 2000,
        ])->assertCreated();

        $this->assertSame(1, CampoCliente::query()->where('user_id', $cliente->id)->where('campo', 'ingresos')->count());

        $campo = CampoCliente::query()->where('user_id', $cliente->id)->where('campo', 'ingresos')->first();
        $this->assertSame(2000, $campo->valor);

        $historial = HistorialCambio::query()->where('user_id', $cliente->id)->where('campo', 'ingresos')->latest('id')->first();
        $this->assertSame(1000, $historial->valor_anterior);
        $this->assertSame(2000, $historial->valor_nuevo);
    }

    public function test_contenido_invalido_se_persiste_como_invalido_y_no_cuenta_para_completitud(): void
    {
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'form_1040',
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

    public function test_la_forma_permanece_en_progreso_mientras_falten_campos_requeridos(): void
    {
        $this->actingAsAgente();
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->postJson('/api/eventos', [
            'cliente_id' => $cliente->id,
            'forma' => 'form_1040',
            'campo' => 'ingresos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'number',
            'contenido' => 1000,
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
            ['campo' => 'pl_balance_general', 'tipo_campo' => 'mixto', 'tipo_dato' => 'number', 'contenido' => 5000],
            ['campo' => 'gastos_deducibles', 'tipo_campo' => 'mixto', 'tipo_dato' => 'number', 'contenido' => 300],
            ['campo' => 'activos_depreciacion', 'tipo_campo' => 'mixto', 'tipo_dato' => 'object', 'contenido' => ['descripcion' => 'Laptop']],
            ['campo' => 'ingresos', 'tipo_campo' => 'dato', 'tipo_dato' => 'number', 'contenido' => 52000],
            ['campo' => 'dependientes', 'tipo_campo' => 'dato', 'tipo_dato' => 'array_object', 'contenido' => []],
            ['campo' => 'deducciones', 'tipo_campo' => 'mixto', 'tipo_dato' => 'number', 'contenido' => 1000],
            ['campo' => 'creditos', 'tipo_campo' => 'dato', 'tipo_dato' => 'array_string', 'contenido' => []],
            ['campo' => 'impuestos_retenidos', 'tipo_campo' => 'dato', 'tipo_dato' => 'number', 'contenido' => 0],
            ['campo' => 'info_bancaria', 'tipo_campo' => 'dato', 'tipo_dato' => 'object', 'contenido' => [
                'banco' => 'Banco X', 'tipo_cuenta' => 'checking', 'numero_cuenta' => '123', 'routing_number' => '456',
            ]],
        ];

        foreach ($datos as $campo) {
            $this->postJson('/api/eventos', array_merge([
                'cliente_id' => $cliente->id,
                'forma' => 'form_1040',
                'modo' => 'texto',
            ], $campo))->assertCreated();
        }

        $documentos = [
            ['campo' => 'w2', 'nombre' => 'w2.pdf'],
            ['campo' => 'form_1099_nec', 'nombre' => 'f1099.pdf'],
            ['campo' => 'estados_bancarios', 'nombre' => 'estado.pdf'],
        ];

        foreach ($documentos as $documento) {
            $this->post('/api/eventos', [
                'cliente_id' => $cliente->id,
                'forma' => 'form_1040',
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
            'campo' => 'ingresos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'number',
            'contenido' => 1,
        ])->assertForbidden();
    }
}
