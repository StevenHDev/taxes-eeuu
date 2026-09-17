<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CampoCatalogo;
use App\Models\CampoCliente;
use App\Models\CampoDerivationLog;
use App\Models\Documento;
use App\Models\RelacionDocumentoCampo;
use App\Models\User;
use App\Support\TaxFieldCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogoPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_solo_un_administrador_puede_ver_el_catalogo(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->actingAs($admin)->get(route('catalogo.index'))->assertOk();
        $this->actingAs($preparador)->get(route('catalogo.index'))->assertForbidden();
        $this->actingAs($cliente)->get(route('catalogo.index'))->assertForbidden();
    }

    public function test_un_administrador_puede_agregar_un_campo_nuevo_y_queda_disponible_en_el_catalogo(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);

        $this->actingAs($admin)->post(route('catalogo.store'), [
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'clave' => 'campo_de_prueba',
            'tipo_campo' => 'dato',
            'tipo_dato' => 'string',
            'obligatorio' => true,
            'sensible' => false,
        ])->assertRedirect();

        $this->assertDatabaseHas('catalogo_campos', ['forma' => 'form_1040', 'tax_year' => 2025, 'clave' => 'campo_de_prueba']);

        $encontrado = TaxFieldCatalog::find(2025, 'form_1040', 'campo_de_prueba');
        $this->assertNotNull($encontrado, 'La caché debe invalidarse al crear un campo.');
    }

    public function test_el_mismo_campo_puede_existir_en_dos_anos_fiscales_distintos(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);

        $this->actingAs($admin)->post(route('catalogo.store'), [
            'forma' => 'form_1040',
            'tax_year' => 2026,
            'clave' => 'ingresos',
            'tipo_campo' => 'dato',
            'tipo_dato' => 'number',
            'obligatorio' => true,
            'sensible' => false,
        ])->assertRedirect();

        $this->assertDatabaseHas('catalogo_campos', ['forma' => 'form_1040', 'tax_year' => 2025, 'clave' => 'ingresos']);
        $this->assertDatabaseHas('catalogo_campos', ['forma' => 'form_1040', 'tax_year' => 2026, 'clave' => 'ingresos']);
    }

    /**
     * Ver agentes-subagentes-flujos-motor-decision.md (Fase 3, inspector de
     * catálogo): el admin debe ver, por documento, exactamente lo que el
     * agente ve vía `revela` (RelacionDocumentoCampo) y el rastro real de
     * uso (CampoDerivationLog, Fase 1).
     */
    public function test_expone_las_relaciones_declaradas_y_las_estadisticas_de_uso_por_documento(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        RelacionDocumentoCampo::query()->create([
            'documento_forma' => 'transversal',
            'documento_campo' => 'w2',
            'campo_destino_forma' => 'form_1040',
            'campo_destino' => 'ingresos',
            'subcampo_destino' => 'salarios',
            'descripcion' => 'Box 1 del W-2.',
            'acumulable' => false,
            'tax_year' => 2025,
        ]);

        $documento = Documento::query()->create([
            'user_id' => $cliente->id,
            'forma' => 'transversal',
            'tax_year' => 2025,
            'campo' => 'w2',
            'file_path' => 'clientes/w2.pdf',
            'file_original_name' => 'w2.pdf',
            'file_mime_type' => 'application/pdf',
            'file_size' => 10,
            'formato' => 'pdf',
            'estado_validacion' => 'recibido',
        ]);

        CampoDerivationLog::query()->create([
            'user_id' => $cliente->id,
            'tax_year' => 2025,
            'documento_id' => $documento->id,
            'documento_campo' => 'w2',
            'relaciones_esperadas' => [['forma' => 'form_1040', 'campo' => 'ingresos', 'subcampo' => 'salarios', 'descripcion' => null, 'acumulable' => false]],
            'revelados_recibidos' => [],
            'relaciones_faltantes' => [['forma' => 'form_1040', 'campo' => 'ingresos', 'subcampo' => 'salarios', 'descripcion' => null, 'acumulable' => false]],
        ]);

        // w2 ya trae otras relaciones reales sembradas por migración (ver
        // Fase de Medicare wages) además de la que este test crea — no se
        // asume posición, se busca la propia por nombre.
        $response = $this->actingAs($admin)
            ->get(route('catalogo.index', ['tax_year' => 2025]))
            ->assertOk();

        $relacionesW2 = collect($response->viewData('page')['props']['relacionesPorDocumento']['w2']);
        $ingresos = $relacionesW2->firstWhere('subcampo', 'salarios');

        $this->assertNotNull($ingresos);
        $this->assertSame('ingresos', $ingresos['campo']);

        $stats = $response->viewData('page')['props']['statsPorDocumento']['w2'];
        $this->assertSame(1, $stats['total']);
        $this->assertSame(1, $stats['con_faltantes']);
    }

    public function test_duplicar_el_mismo_campo_dentro_del_mismo_ano_falla(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);

        $this->actingAs($admin)->post(route('catalogo.store'), [
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'clave' => 'ingresos',
            'tipo_campo' => 'dato',
            'tipo_dato' => 'number',
            'obligatorio' => true,
            'sensible' => false,
        ])->assertSessionHasErrors('clave');
    }

    public function test_un_administrador_puede_editar_una_definicion(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $campo = CampoCatalogo::query()->where('forma', 'form_1040')->where('clave', 'ingresos')->firstOrFail();

        $this->actingAs($admin)->patch(route('catalogo.update', $campo), [
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'clave' => 'ingresos',
            'tipo_campo' => 'dato',
            'tipo_dato' => 'number',
            'obligatorio' => false,
            'sensible' => false,
        ])->assertRedirect();

        $this->assertFalse($campo->fresh()->obligatorio);
    }

    public function test_eliminar_una_definicion_no_borra_los_datos_ya_cargados_del_cliente(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        $campo = CampoCatalogo::query()->where('forma', 'form_1040')->where('clave', 'ingresos')->firstOrFail();

        CampoCliente::query()->create([
            'user_id' => $cliente->id,
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'ingresos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'valor_texto' => 5000,
            'estado' => 'recibido',
            'source' => 'agente_ia',
        ]);

        $this->actingAs($admin)->delete(route('catalogo.destroy', $campo))->assertRedirect();

        $this->assertDatabaseMissing('catalogo_campos', ['id' => $campo->id]);
        $this->assertDatabaseHas('campos_cliente', ['user_id' => $cliente->id, 'campo' => 'ingresos']);
        $this->assertNull(TaxFieldCatalog::find(2025, 'form_1040', 'ingresos'));
    }

    public function test_un_preparador_no_puede_modificar_el_catalogo(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);

        $this->actingAs($preparador)->post(route('catalogo.store'), [
            'forma' => 'form_1040',
            'clave' => 'otro_campo',
            'tipo_campo' => 'dato',
            'tipo_dato' => 'string',
            'obligatorio' => true,
            'sensible' => false,
        ])->assertForbidden();
    }
}
