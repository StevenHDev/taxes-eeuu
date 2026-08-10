<?php

namespace Tests\Feature;

use App\Enums\ApiAbility;
use App\Enums\TaxForm;
use App\Enums\UserRole;
use App\Models\CampoCliente;
use App\Models\FormaCliente;
use App\Models\User;
use App\Support\TaxFieldCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientePendientesApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Marca como Recibido todos los campos obligatorios de una forma (los
     * transversales y los propios), para simular un caso ya completo sin
     * pasar por la validación real de /eventos — la única pieza bajo prueba
     * acá es el diff de "qué falta", no la validación de contenido.
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

    public function test_estados_bancarios_aparece_una_vez_por_cada_forma_de_negocio_declarada(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        FormaCliente::query()->create(['user_id' => $cliente->id, 'forma' => 'schedule_c', 'tax_year' => 2025, 'estado' => 'en_progreso']);
        FormaCliente::query()->create(['user_id' => $cliente->id, 'forma' => 'schedule_e', 'tax_year' => 2025, 'estado' => 'en_progreso']);
        Sanctum::actingAs($admin, [ApiAbility::ClientesRead->value]);

        $response = $this->getJson("/api/clientes/{$cliente->id}/pendientes?tax_year=2025")->assertOk();

        $estadosBancarios = collect($response->json('pendientes'))
            ->filter(fn (array $p) => $p['campo'] === 'estados_bancarios');

        $this->assertCount(2, $estadosBancarios);
        $this->assertEqualsCanonicalizing(
            ['schedule_c', 'schedule_e'],
            $estadosBancarios->pluck('forma')->all(),
        );
    }

    public function test_campos_transversales_no_se_duplican_entre_formas(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        FormaCliente::query()->create(['user_id' => $cliente->id, 'forma' => 'schedule_c', 'tax_year' => 2025, 'estado' => 'en_progreso']);
        FormaCliente::query()->create(['user_id' => $cliente->id, 'forma' => 'schedule_e', 'tax_year' => 2025, 'estado' => 'en_progreso']);
        Sanctum::actingAs($admin, [ApiAbility::ClientesRead->value]);

        $response = $this->getJson("/api/clientes/{$cliente->id}/pendientes?tax_year=2025")->assertOk();

        $ssn = collect($response->json('pendientes'))->filter(fn (array $p) => $p['campo'] === 'identificacion_ssn_itin');

        $this->assertCount(1, $ssn);
        $this->assertSame('transversal', $ssn->first()['forma']);
    }

    public function test_completo_es_true_cuando_ya_no_falta_ningun_obligatorio(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        FormaCliente::query()->create(['user_id' => $cliente->id, 'forma' => 'form_990', 'tax_year' => 2025, 'estado' => 'en_progreso']);
        $this->completarObligatoriosDe($cliente, 2025, TaxForm::Form990);
        Sanctum::actingAs($admin, [ApiAbility::ClientesRead->value]);

        $response = $this->getJson("/api/clientes/{$cliente->id}/pendientes?tax_year=2025")
            ->assertOk()
            ->assertJsonPath('completo', true);

        // Los campos obligatorios ya no aparecen; solo quedan opcionales
        // transversales (w2, form_1099_nec, declaracion_anio_anterior, etc.),
        // que nunca cuentan para "completo" — pero `siguiente` SÍ los sigue
        // señalando (ver pendientesEnvelope): un campo opcional se pregunta
        // igual que cualquier otro, en su turno, y el cliente puede responder
        // "no aplica" si no lo tiene.
        $pendientes = collect($response->json('pendientes'));
        $this->assertTrue($pendientes->isNotEmpty());
        $this->assertTrue($pendientes->every(fn (array $p) => $p['obligatorio'] === false));
        $this->assertSame($pendientes->first()['campo'], $response->json('siguiente.campo'));
    }

    /**
     * Los transversales (SSN, cónyuge, dependientes, estado_civil...) no
     * pertenecen a ninguna forma — se piden sin importar cuál(es) apliquen,
     * así que ya aparecen aunque el agente todavía no haya llamado a /formas.
     * `completo`, en cambio, sí exige al menos una forma declarada.
     */
    public function test_sin_formas_declaradas_igual_muestra_los_transversales_pendientes(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        Sanctum::actingAs($admin, [ApiAbility::ClientesRead->value]);

        $response = $this->getJson("/api/clientes/{$cliente->id}/pendientes?tax_year=2025")
            ->assertOk()
            ->assertJsonPath('completo', false);

        $pendientes = collect($response->json('pendientes'));
        $this->assertTrue($pendientes->isNotEmpty());
        $this->assertTrue($pendientes->every(fn (array $p) => $p['forma'] === 'transversal'));
    }

    /**
     * Encontrado en una prueba real end-to-end del agente conversacional: si
     * `siguiente` filtrara solo por `obligatorio`, saltaría por encima de
     * documentos opcionales (w2, 1099-nec, ...) que aparecen antes en
     * `pendientes`, y le pediría al cliente teclear a mano un monto (ej.
     * `impuestos_retenidos`) que esos mismos documentos ya revelan (ver
     * RelacionDocumentoCampo) — inutilizando esa relación para ese campo.
     */
    public function test_siguiente_no_salta_documentos_opcionales_por_delante_de_lo_obligatorio(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        FormaCliente::query()->create(['user_id' => $cliente->id, 'forma' => 'form_1040', 'tax_year' => 2025, 'estado' => 'en_progreso']);

        // Resuelve TODOS los transversales obligatorios (cuáles sean exactos
        // depende del catálogo, ver CatalogoCamposSeeder), dejando pendientes
        // solo los opcionales (documentos como w2/1099-nec, ...) y los campos
        // de form_1040.
        foreach (TaxFieldCatalog::transversales(2025) as $field) {
            if (! $field['obligatorio']) {
                continue;
            }

            CampoCliente::query()->create([
                'user_id' => $cliente->id, 'forma' => 'transversal', 'campo' => $field['campo'], 'tax_year' => 2025,
                'tipo_campo' => $field['tipo']->value, 'modo' => 'texto', 'valor_texto' => 'x', 'estado' => 'recibido', 'source' => 'agente_ia',
            ]);
        }

        Sanctum::actingAs($admin, [ApiAbility::ClientesRead->value]);

        $response = $this->getJson("/api/clientes/{$cliente->id}/pendientes?tax_year=2025")->assertOk();

        $primerPendiente = $response->json('pendientes.0');

        $this->assertFalse($primerPendiente['obligatorio']);
        $this->assertSame($primerPendiente['campo'], $response->json('siguiente.campo'));
        $this->assertNotSame('impuestos_retenidos', $response->json('siguiente.campo'));
    }

    public function test_falta_tax_year_devuelve_422(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        Sanctum::actingAs($admin, [ApiAbility::ClientesRead->value]);

        $this->getJson("/api/clientes/{$cliente->id}/pendientes")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tax_year']);
    }

    public function test_un_preparador_no_puede_ver_pendientes_de_un_cliente_que_no_tiene_asignado(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $otroPreparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $otroPreparador->id]);
        Sanctum::actingAs($preparador, [ApiAbility::ClientesRead->value]);

        $this->getJson("/api/clientes/{$cliente->id}/pendientes?tax_year=2025")
            ->assertForbidden();
    }
}
