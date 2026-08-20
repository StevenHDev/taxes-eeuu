<?php

namespace Tests\Feature;

use App\Enums\ApiAbility;
use App\Enums\UserRole;
use App\Models\RelacionDocumentoCampo;
use App\Models\User;
use App\Support\TaxFieldCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CatalogoDocumentosExtraApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_devuelve_los_18_documentos_extra_bajo_su_propia_pseudo_forma(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        Sanctum::actingAs($admin, [ApiAbility::ClientesRead->value]);

        $response = $this->getJson('/api/catalogo/documentos-extra?tax_year=2025')
            ->assertOk()
            ->assertJsonPath('tax_year', 2025);

        $documentos = collect($response->json('documentos'));

        $this->assertCount(18, $documentos);
        $this->assertTrue($documentos->every(fn (array $d) => $d['forma'] === 'documentos_extra'));
    }

    /**
     * form_1099_int revela hacia form_1040.ingresos (subcampo
     * intereses_dividendos, acumulable) y form_1040.impuestos_retenidos
     * (acumulable) — ver RelacionesDocumentoCampoSeeder. El agente necesita
     * ese `revela` para saber qué guardar sin volver a preguntarlo.
     */
    public function test_un_documento_extra_trae_su_revela(): void
    {
        // El seeder de relaciones no corre en TestCase::setUp() (solo catálogo
        // y parámetros fiscales) — se siembra acá la única relación que este
        // test necesita.
        RelacionDocumentoCampo::query()->create([
            'documento_forma' => 'documentos_extra',
            'documento_campo' => 'form_1099_int',
            'campo_destino_forma' => 'form_1040',
            'campo_destino' => 'ingresos',
            'subcampo_destino' => 'intereses_dividendos',
            'descripcion' => 'Casilla 1 (Interest income) del 1099-INT es interés gravable a incluir en ingresos.',
            'acumulable' => true,
            'tax_year' => 2025,
        ]);
        TaxFieldCatalog::invalidate();

        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        Sanctum::actingAs($admin, [ApiAbility::ClientesRead->value]);

        $response = $this->getJson('/api/catalogo/documentos-extra?tax_year=2025')->assertOk();

        $documento = collect($response->json('documentos'))->firstWhere('campo', 'form_1099_int');

        $this->assertNotNull($documento);
        $this->assertNotEmpty($documento['revela']);
        $this->assertTrue(collect($documento['revela'])->contains(fn (array $r) => $r['campo'] === 'ingresos' && $r['subcampo'] === 'intereses_dividendos'));
    }

    public function test_ningun_campo_de_identidad_del_cliente_aparece_aqui(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        Sanctum::actingAs($admin, [ApiAbility::ClientesRead->value]);

        $response = $this->getJson('/api/catalogo/documentos-extra?tax_year=2025')->assertOk();

        $campos = collect($response->json('documentos'))->pluck('campo');

        foreach (['w2', 'form_1099_nec', 'form_1095_a', 'estado_civil', 'identificacion_ssn_itin', 'info_conyuge', 'info_dependientes'] as $campoIdentidad) {
            $this->assertFalse($campos->contains($campoIdentidad));
        }
    }

    public function test_falta_tax_year_devuelve_422(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        Sanctum::actingAs($admin, [ApiAbility::ClientesRead->value]);

        $this->getJson('/api/catalogo/documentos-extra')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tax_year']);
    }

    public function test_sin_ability_clientes_read_devuelve_403(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        Sanctum::actingAs($admin, [ApiAbility::ClientesWrite->value]);

        $this->getJson('/api/catalogo/documentos-extra?tax_year=2025')->assertForbidden();
    }
}
