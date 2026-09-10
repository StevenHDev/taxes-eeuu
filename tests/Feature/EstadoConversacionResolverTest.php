<?php

namespace Tests\Feature;

use App\Enums\FaseConversacion;
use App\Enums\TaxForm;
use App\Enums\UserRole;
use App\Models\CampoCliente;
use App\Models\FormaCliente;
use App\Models\User;
use App\Services\WhatsappAgent\EstadoConversacionResolver;
use App\Support\TaxFieldCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EstadoConversacionResolverTest extends TestCase
{
    use RefreshDatabase;

    private EstadoConversacionResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new EstadoConversacionResolver;
    }

    /**
     * Mismo helper que ClientePendientesApiTest: marca como Recibido todos los
     * campos obligatorios de una forma, sin pasar por la validación real de
     * /eventos — lo único bajo prueba acá es a qué fase salta el resolver.
     */
    private function completarObligatoriosDe(User $cliente, int $taxYear, TaxForm $forma): void
    {
        foreach (TaxFieldCatalog::requiredFieldsFor($taxYear, $forma) as $field) {
            $formaAlmacen = TaxFieldCatalog::formaAlmacen($taxYear, $field['campo'], $forma->value);

            CampoCliente::query()->firstOrCreate(
                ['user_id' => $cliente->id, 'forma' => $formaAlmacen, 'campo' => $field['campo'], 'tax_year' => $taxYear],
                [
                    'tipo_campo' => $field['tipo']->value,
                    'modo' => 'texto',
                    'valor_texto' => 'x',
                    'estado' => 'recibido',
                    'source' => 'agente_ia',
                ],
            );
        }
    }

    public function test_sin_cliente_la_fase_es_verificacion_de_cuenta(): void
    {
        $this->assertSame(FaseConversacion::VerificacionCuenta, $this->resolver->resolver(null));
    }

    public function test_con_cliente_pero_sin_formas_declaradas_la_fase_es_determinacion_de_formas(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->assertSame(FaseConversacion::DeterminacionFormas, $this->resolver->resolver($cliente));
    }

    public function test_con_formas_declaradas_y_pendientes_obligatorios_la_fase_es_recoleccion(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        FormaCliente::query()->create(['user_id' => $cliente->id, 'forma' => 'form_990', 'tax_year' => 2025, 'estado' => 'en_progreso']);

        $this->assertSame(FaseConversacion::Recoleccion, $this->resolver->resolver($cliente));
    }

    public function test_sin_pendientes_obligatorios_la_fase_es_cierre(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        FormaCliente::query()->create(['user_id' => $cliente->id, 'forma' => 'form_990', 'tax_year' => 2025, 'estado' => 'en_progreso']);
        $this->completarObligatoriosDe($cliente, 2025, TaxForm::Form990);

        $this->assertSame(FaseConversacion::Cierre, $this->resolver->resolver($cliente));
    }

    public function test_usa_el_tax_year_mas_reciente_entre_las_formas_declaradas(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        // Declaración de un año anterior, ya completa — no debe hacer que el
        // resolver ignore que este año (2025) todavía tiene pendientes.
        FormaCliente::query()->create(['user_id' => $cliente->id, 'forma' => 'form_990', 'tax_year' => 2024, 'estado' => 'completo']);
        $this->completarObligatoriosDe($cliente, 2024, TaxForm::Form990);
        FormaCliente::query()->create(['user_id' => $cliente->id, 'forma' => 'form_990', 'tax_year' => 2025, 'estado' => 'en_progreso']);

        $this->assertSame(FaseConversacion::Recoleccion, $this->resolver->resolver($cliente));
    }
}
