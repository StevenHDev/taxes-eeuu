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

    /**
     * Desde la Fase 2 del plan de cierre de brecha GTS, form_1098_t es el
     * único campo que queda bajo documentos_extra — el resto se promovió a
     * ACTIVO (transversal, ver CatalogoCamposSeeder::documentosPromovidosAActivo()).
     * form_1098_t se queda pasivo a propósito: gastos_educacion (form_1040)
     * ya pregunta lo mismo y acepta este documento como respuesta.
     */
    public function test_devuelve_solo_form_1098_t_bajo_su_propia_pseudo_forma(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        Sanctum::actingAs($admin, [ApiAbility::ClientesRead->value]);

        $response = $this->getJson('/api/catalogo/documentos-extra?tax_year=2025')
            ->assertOk()
            ->assertJsonPath('tax_year', 2025);

        $documentos = collect($response->json('documentos'));

        $this->assertCount(1, $documentos);
        $this->assertSame('form_1098_t', $documentos->first()['campo']);
        $this->assertTrue($documentos->every(fn (array $d) => $d['forma'] === 'documentos_extra'));
    }

    /**
     * form_1098_t revela hacia form_1040.gastos_educacion — ver
     * RelacionesDocumentoCampoSeeder. El agente necesita ese `revela` para
     * saber qué guardar sin volver a preguntarlo.
     */
    public function test_un_documento_extra_trae_su_revela(): void
    {
        // El seeder de relaciones no corre en TestCase::setUp() (solo catálogo
        // y parámetros fiscales) — se siembra acá la única relación que este
        // test necesita.
        RelacionDocumentoCampo::query()->create([
            'documento_forma' => 'documentos_extra',
            'documento_campo' => 'form_1098_t',
            'campo_destino_forma' => 'form_1040',
            'campo_destino' => 'gastos_educacion',
            'subcampo_destino' => null,
            'descripcion' => 'Casilla 1 (Payments received) del 1098-T son gastos calificados de educación.',
            'acumulable' => false,
            'tax_year' => 2025,
        ]);
        TaxFieldCatalog::invalidate();

        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        Sanctum::actingAs($admin, [ApiAbility::ClientesRead->value]);

        $response = $this->getJson('/api/catalogo/documentos-extra?tax_year=2025')->assertOk();

        $documento = collect($response->json('documentos'))->firstWhere('campo', 'form_1098_t');

        $this->assertNotNull($documento);
        $this->assertNotEmpty($documento['revela']);
        $this->assertTrue(collect($documento['revela'])->contains(fn (array $r) => $r['campo'] === 'gastos_educacion'));
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
