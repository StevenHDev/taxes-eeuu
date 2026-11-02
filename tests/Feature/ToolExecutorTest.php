<?php

namespace Tests\Feature;

use App\Enums\UserRole;
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
        $resultado = $this->tools->ejecutar('consultar_entrevista', [], null, $this->actor);

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

    /**
     * Regresión de un caso real (2026-09-24, conversación con
     * +573213445027): un correo con un carácter de control colado se
     * guardaba tal cual y solo se notaba cuando BienvenidaClientePortal
     * fallaba en la cola — dejando al cliente con una cuenta creada pero sin
     * ninguna forma de ponerle contraseña.
     */
    public function test_crear_cliente_taxes_con_correo_invalido_devuelve_error_y_no_crea_el_cliente(): void
    {
        $resultado = $this->tools->ejecutar('crear_cliente_taxes', [
            'nombre' => 'Jane Doe',
            'email' => "jane\x10@example.com",
        ], null, $this->actor);

        $this->assertArrayHasKey('error', $resultado);
        $this->assertSame(0, User::query()->where('role', UserRole::Client)->count());
    }

    public function test_crear_cliente_taxes_vincula_el_telefono_de_la_conversacion(): void
    {
        $resultado = $this->tools->ejecutar('crear_cliente_taxes', [
            'nombre' => 'Jane Doe',
            'email' => 'jane@example.com',
        ], null, $this->actor, telefono: '+15551234567');

        $cliente = User::find($resultado['cliente_id']);
        $this->assertSame('+15551234567', $cliente->phone);
    }

    public function test_consultar_base_conocimiento_delega_en_el_servicio_de_busqueda(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $resultado = $this->tools->ejecutar('consultar_base_conocimiento', [
            'consulta' => 'palabra_que_no_existe_en_ningun_documento',
        ], $cliente, $this->actor);

        $this->assertSame(['resultados' => []], $resultado);
    }

    public function test_crear_cliente_taxes_no_duplica_el_correo_de_un_cliente_archivado(): void
    {
        $archivado = User::factory()->create(['role' => UserRole::Client, 'email' => 'ana@example.com']);
        $archivado->delete();

        $resultado = $this->tools->ejecutar('crear_cliente_taxes', [
            'nombre' => 'Ana',
            'email' => 'ana@example.com',
        ], null, $this->actor, telefono: '+15551234567');

        $this->assertArrayHasKey('error', $resultado);
        $this->assertSame(1, User::withTrashed()->where('email', 'ana@example.com')->count());
    }
}
