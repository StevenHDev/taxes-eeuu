<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CampoCatalogo;
use App\Models\CampoCliente;
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
            'clave' => 'campo_de_prueba',
            'tipo_campo' => 'dato',
            'tipo_dato' => 'string',
            'obligatorio' => true,
            'sensible' => false,
        ])->assertRedirect();

        $this->assertDatabaseHas('catalogo_campos', ['forma' => 'form_1040', 'clave' => 'campo_de_prueba']);

        $encontrado = TaxFieldCatalog::find('form_1040', 'campo_de_prueba');
        $this->assertNotNull($encontrado, 'La caché debe invalidarse al crear un campo.');
    }

    public function test_un_administrador_puede_editar_una_definicion(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $campo = CampoCatalogo::query()->where('forma', 'form_1040')->where('clave', 'ingresos')->firstOrFail();

        $this->actingAs($admin)->patch(route('catalogo.update', $campo), [
            'forma' => 'form_1040',
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
        $this->assertNull(TaxFieldCatalog::find('form_1040', 'ingresos'));
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
