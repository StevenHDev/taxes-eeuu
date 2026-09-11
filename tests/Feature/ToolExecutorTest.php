<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CampoCliente;
use App\Models\FormaCliente;
use App\Models\User;
use App\Services\WhatsappAgent\ToolExecutor;
use App\Support\AgenteWhatsappUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToolExecutorTest extends TestCase
{
    use RefreshDatabase;

    private ToolExecutor $tools;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tools = app(ToolExecutor::class);
        $this->actor = AgenteWhatsappUser::resolver();
    }

    public function test_think_no_hace_nada_y_no_requiere_cliente(): void
    {
        $resultado = $this->tools->ejecutar('think', ['razonamiento' => 'x'], null, $this->actor);

        $this->assertSame(['ok' => true], $resultado);
    }

    public function test_una_tool_desconocida_devuelve_error_en_vez_de_tronar(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $resultado = $this->tools->ejecutar('tool_inventada', [], $cliente, $this->actor);

        $this->assertArrayHasKey('error', $resultado);
    }

    public function test_una_tool_que_requiere_cliente_sin_cuenta_devuelve_error(): void
    {
        $resultado = $this->tools->ejecutar('consultar_pendientes_cliente', [], null, $this->actor);

        $this->assertArrayHasKey('error', $resultado);
    }

    public function test_crear_cliente_taxes_crea_el_cliente_y_devuelve_su_id(): void
    {
        $resultado = $this->tools->ejecutar('crear_cliente_taxes', [
            'nombre' => 'Jane Doe',
            'email' => 'jane@example.com',
        ], null, $this->actor);

        $this->assertArrayHasKey('cliente_id', $resultado);

        $cliente = User::find($resultado['cliente_id']);
        $this->assertSame('Jane Doe', $cliente->name);
        $this->assertSame(UserRole::Client, $cliente->role);
    }

    public function test_declarar_formas_cliente_crea_las_formas_y_devuelve_pendientes(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $resultado = $this->tools->ejecutar('declarar_formas_cliente', [
            'tax_year' => 2025,
            'formas_aplicables' => ['form_990'],
        ], $cliente, $this->actor);

        $this->assertDatabaseHas('formas_cliente', ['user_id' => $cliente->id, 'forma' => 'form_990', 'tax_year' => 2025]);
        $this->assertSame(2025, $resultado['tax_year']);
        $this->assertFalse($resultado['completo']);
    }

    public function test_consultar_pendientes_cliente_deriva_el_tax_year_de_las_formas_declaradas(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        FormaCliente::query()->create(['user_id' => $cliente->id, 'forma' => 'form_990', 'tax_year' => 2025, 'estado' => 'en_progreso']);

        $resultado = $this->tools->ejecutar('consultar_pendientes_cliente', [], $cliente, $this->actor);

        $this->assertSame(2025, $resultado['tax_year']);
    }

    public function test_consultar_documentos_extra_deriva_el_tax_year_y_devuelve_el_catalogo(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        FormaCliente::query()->create(['user_id' => $cliente->id, 'forma' => 'form_990', 'tax_year' => 2025, 'estado' => 'en_progreso']);

        $resultado = $this->tools->ejecutar('consultar_documentos_extra', [], $cliente, $this->actor);

        $this->assertSame(2025, $resultado['tax_year']);
        $this->assertArrayHasKey('documentos', $resultado);
    }

    public function test_guardar_campo_cliente_valido_guarda_y_devuelve_el_estado(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        FormaCliente::query()->create(['user_id' => $cliente->id, 'forma' => 'form_1040', 'tax_year' => 2025, 'estado' => 'en_progreso']);

        $resultado = $this->tools->ejecutar('guardar_campo_cliente', [
            'forma' => 'form_1040',
            'campo' => 'ingresos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'object',
            'contenido' => [
                'salarios' => 52000,
                'intereses_dividendos' => 0,
                'ganancias_capital' => 0,
                'ingresos_jubilacion' => 0,
                'otros_ingresos' => 0,
                'ajustes_ingreso' => 0,
                'seguridad_social' => 0,
            ],
        ], $cliente, $this->actor);

        $this->assertSame('ingresos', $resultado['campo']);
        $this->assertSame('recibido', $resultado['estado']->value);
        $this->assertDatabaseHas('campos_cliente', ['user_id' => $cliente->id, 'campo' => 'ingresos', 'tax_year' => 2025]);

        $campo = CampoCliente::query()->where('user_id', $cliente->id)->where('campo', 'ingresos')->first();
        $this->assertSame($this->actor->id, $campo->actualizado_por);
    }

    public function test_guardar_campo_cliente_invalido_no_guarda_y_devuelve_los_errores(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        FormaCliente::query()->create(['user_id' => $cliente->id, 'forma' => 'form_1040', 'tax_year' => 2025, 'estado' => 'en_progreso']);

        $resultado = $this->tools->ejecutar('guardar_campo_cliente', [
            'forma' => 'form_1040',
            'campo' => 'campo_inventado',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'object',
            'contenido' => ['x' => 1],
        ], $cliente, $this->actor);

        $this->assertSame('validacion', $resultado['error']);
        $this->assertArrayHasKey('campo', $resultado['detalles']);
        $this->assertDatabaseMissing('campos_cliente', ['user_id' => $cliente->id, 'campo' => 'campo_inventado']);
    }

    public function test_consultar_base_conocimiento_delega_en_el_servicio_de_busqueda(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $resultado = $this->tools->ejecutar('consultar_base_conocimiento', [
            'consulta' => 'palabra_que_no_existe_en_ningun_documento',
        ], $cliente, $this->actor);

        $this->assertSame(['resultados' => []], $resultado);
    }
}
