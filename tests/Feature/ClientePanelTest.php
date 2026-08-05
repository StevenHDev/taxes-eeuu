<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\CampoCliente;
use App\Models\CampoReveal;
use App\Models\Documento;
use App\Models\FormaCliente;
use App\Models\HistorialCambio;
use App\Models\User;
use App\Support\TaxFieldCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClientePanelTest extends TestCase
{
    use RefreshDatabase;

    private function crearCampo(User $cliente, string $campo = 'impuestos_retenidos', array $overrides = []): CampoCliente
    {
        return CampoCliente::query()->create(array_merge([
            'user_id' => $cliente->id,
            // Los campos únicos por cliente (SSN, cónyuge, dependientes) se guardan
            // bajo la forma canónica 'transversal'.
            'forma' => TaxFieldCatalog::formaAlmacen(2025, $campo, 'form_1040'),
            'tax_year' => 2025,
            'campo' => $campo,
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'valor_texto' => 1000,
            'estado' => 'recibido',
            'source' => 'agente_ia',
        ], $overrides));
    }

    public function test_un_cliente_no_puede_acceder_al_panel(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->actingAs($cliente)->get(route('clientes.index'))->assertForbidden();
        $this->actingAs($cliente)->get(route('clientes.show', $cliente))->assertForbidden();
    }

    public function test_un_preparador_solo_ve_a_sus_clientes_asignados(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $propio = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);
        $ajeno = User::factory()->create(['role' => UserRole::Client]);

        $this->actingAs($preparador)->get(route('clientes.show', $propio))->assertOk();
        $this->actingAs($preparador)->get(route('clientes.show', $ajeno))->assertForbidden();
    }

    public function test_un_administrador_ve_a_todos_los_clientes(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->actingAs($admin)->get(route('clientes.show', $cliente))->assertOk();
    }

    public function test_el_detalle_carga_con_campos_sensibles_con_acentos(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        // Enmascarar por bytes partía el carácter acentuado y producía UTF-8
        // inválido, que hacía fallar la serialización JSON de toda la respuesta.
        $this->crearCampo($cliente, 'info_conyuge', [
            'valor_texto' => ['nombre_completo' => 'María López', 'ssn' => '123-45-6789'],
        ]);

        // Petición Inertia (XHR) — es la que serializa las props como JsonResponse.
        $respuesta = $this->actingAs($admin)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
            ])
            ->get(route('clientes.show', $cliente))
            ->assertOk();

        $conyuge = collect($respuesta->json('props.campos'))
            ->firstWhere('campo', 'info_conyuge');

        $this->assertSame('*******ópez', $conyuge['valor']['nombre_completo']);
        $this->assertSame('*******6789', $conyuge['valor']['ssn']);
    }

    public function test_el_detalle_expone_subcampos_y_tipo_dato_para_editar_objetos_correctamente(): void
    {
        // Regresión: sin esto, el editor genérico del panel no tenía forma de
        // saber qué subcampos existen para un objeto/array_object (ej. los
        // nuevos de info_dependientes/estado_civil de la Fase 2), y los
        // omitía por completo del formulario de edición.
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->crearCampo($cliente, 'info_dependientes', [
            'forma' => 'transversal',
            'valor_texto' => [['nombre_completo' => 'Kid One', 'fecha_nacimiento' => '2015-01-01', 'ssn' => '111-22-3333']],
        ]);

        $respuesta = $this->actingAs($admin)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
            ])
            ->get(route('clientes.show', $cliente))
            ->assertOk();

        // Campo ya cargado: 'campos' trae sus subcampos completos (no solo los 3 viejos).
        $dependientes = collect($respuesta->json('props.campos'))->firstWhere('campo', 'info_dependientes');
        $this->assertContains('relacion', $dependientes['subcampos']);
        $this->assertContains('meses_en_hogar', $dependientes['subcampos']);
        $this->assertSame('array_object', $dependientes['tipo_dato']);

        // Campo nunca cargado (estado_civil): 'catalogoDisponible' también trae subcampos.
        $estadoCivil = collect($respuesta->json('props.catalogoDisponible'))->firstWhere('campo', 'estado_civil');
        $this->assertNotNull($estadoCivil);
        $this->assertContains('casado_al_31_dic', $estadoCivil['subcampos']);
        $this->assertSame('object', $estadoCivil['tipo_dato']);
    }

    public function test_un_preparador_puede_corregir_un_campo_manualmente_y_queda_en_el_historial(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);
        $this->crearCampo($cliente);

        $this->actingAs($preparador)
            ->patch(route('clientes.campos.update', ['cliente' => $cliente, 'campo' => 'impuestos_retenidos']).'?forma=form_1040&tax_year=2025', [
                'forma' => 'form_1040',
                'modo' => 'texto',
                'tipo_dato' => 'number',
                'contenido' => 9999,
            ])
            ->assertRedirect();

        $campo = CampoCliente::query()->where('user_id', $cliente->id)->where('campo', 'impuestos_retenidos')->first();
        $this->assertSame(9999, $campo->valor);
        $this->assertSame('preparador', $campo->source->value);

        $historial = HistorialCambio::query()->where('user_id', $cliente->id)->where('campo', 'impuestos_retenidos')->first();
        $this->assertNotNull($historial);
        $this->assertSame('preparador', $historial->source->value);
    }

    public function test_un_preparador_puede_corregir_ingresos_con_su_shape_de_objeto(): void
    {
        // ingresos pasó de number a object desglosado en la Fase 2 — la
        // corrección manual del preparador debe aceptar (y validar) ese shape.
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);

        $this->actingAs($preparador)
            ->patch(route('clientes.campos.update', ['cliente' => $cliente, 'campo' => 'ingresos']).'?forma=form_1040&tax_year=2025', [
                'forma' => 'form_1040',
                'modo' => 'texto',
                'tipo_dato' => 'object',
                'contenido' => [
                    'salarios' => 60000,
                    'intereses_dividendos' => 500,
                    'ganancias_capital' => 0,
                    'ingresos_jubilacion' => 0,
                    'otros_ingresos' => 0,
                    'ajustes_ingreso' => 1000,
                ],
            ])
            ->assertRedirect();

        $campo = CampoCliente::query()->where('user_id', $cliente->id)->where('campo', 'ingresos')->first();
        $this->assertSame('recibido', $campo->estado->value);
        $this->assertSame(60000, $campo->valor['salarios']);
    }

    public function test_corregir_ingresos_con_el_shape_viejo_number_falla_la_validacion(): void
    {
        // Regresión: una integración desactualizada que siga mandando el shape
        // number viejo debe rechazarse, no corromper el AGI calculado en silencio.
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);

        $this->actingAs($preparador)
            ->patch(route('clientes.campos.update', ['cliente' => $cliente, 'campo' => 'ingresos']).'?forma=form_1040&tax_year=2025', [
                'forma' => 'form_1040',
                'modo' => 'texto',
                'tipo_dato' => 'number',
                'contenido' => 52000,
            ])
            ->assertSessionHasErrors('tipo_dato');
    }

    public function test_un_preparador_puede_marcar_un_campo_opcional_como_no_aplica(): void
    {
        // declaracion_anio_anterior es documento y obligatorio: false — el
        // preparador puede registrar que el cliente no lo tiene sin subir nada.
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);

        $this->actingAs($preparador)
            ->patch(route('clientes.campos.update', ['cliente' => $cliente, 'campo' => 'declaracion_anio_anterior']).'?forma=transversal&tax_year=2025', [
                'forma' => 'transversal',
                'modo' => 'no_aplica',
            ])
            ->assertRedirect();

        $campo = CampoCliente::query()->where('user_id', $cliente->id)->where('campo', 'declaracion_anio_anterior')->first();
        $this->assertNotNull($campo);
        $this->assertSame('no_aplica', $campo->estado->value);
        $this->assertSame('preparador', $campo->source->value);
    }

    public function test_no_aplica_es_rechazado_en_correccion_manual_para_un_campo_obligatorio(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);

        $this->actingAs($preparador)
            ->patch(route('clientes.campos.update', ['cliente' => $cliente, 'campo' => 'impuestos_retenidos']).'?forma=form_1040&tax_year=2025', [
                'forma' => 'form_1040',
                'modo' => 'no_aplica',
            ])
            ->assertSessionHasErrors('modo');

        $this->assertDatabaseMissing('campos_cliente', ['user_id' => $cliente->id, 'campo' => 'impuestos_retenidos']);
    }

    public function test_un_preparador_no_puede_corregir_campos_de_un_cliente_ajeno(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $ajeno = User::factory()->create(['role' => UserRole::Client]);
        $this->crearCampo($ajeno);

        $this->actingAs($preparador)
            ->patch(route('clientes.campos.update', ['cliente' => $ajeno, 'campo' => 'impuestos_retenidos']).'?forma=form_1040&tax_year=2025', [
                'forma' => 'form_1040',
                'modo' => 'texto',
                'tipo_dato' => 'number',
                'contenido' => 1,
            ])
            ->assertForbidden();
    }

    public function test_un_preparador_carga_un_documento_via_web(): void
    {
        Storage::fake('local');
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);

        // Subida real del navegador: POST con _method=patch (method spoofing) para
        // que PHP parsee el multipart; un PATCH multipart directo no expone $_FILES.
        $this->actingAs($preparador)
            ->post(route('clientes.campos.update', ['cliente' => $cliente, 'campo' => 'w2']).'?forma=form_1040&tax_year=2025', [
                '_method' => 'patch',
                'forma' => 'form_1040',
                'modo' => 'archivo',
                'file' => UploadedFile::fake()->create('w2.pdf', 200, 'application/pdf'),
            ])
            ->assertRedirect();

        $documento = Documento::query()->where('user_id', $cliente->id)->where('campo', 'w2')->first();
        $this->assertNotNull($documento);
        $this->assertSame('w2.pdf', $documento->file_original_name);
        Storage::disk('local')->assertExists($documento->file_path);
    }

    public function test_rechaza_un_documento_de_mas_de_10mb(): void
    {
        Storage::fake('local');
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);

        $this->actingAs($preparador)
            ->from(route('clientes.show', $cliente))
            ->post(route('clientes.campos.update', ['cliente' => $cliente, 'campo' => 'w2']).'?forma=form_1040&tax_year=2025', [
                '_method' => 'patch',
                'forma' => 'form_1040',
                'modo' => 'archivo',
                'file' => UploadedFile::fake()->create('grande.pdf', 11 * 1024, 'application/pdf'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('documentos', 0);
    }

    public function test_rechaza_un_formato_de_archivo_no_permitido(): void
    {
        Storage::fake('local');
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);

        // w2 solo acepta pdf/jpg/png/heic; un .txt debe rechazarse.
        $this->actingAs($preparador)
            ->from(route('clientes.show', $cliente))
            ->post(route('clientes.campos.update', ['cliente' => $cliente, 'campo' => 'w2']).'?forma=form_1040&tax_year=2025', [
                '_method' => 'patch',
                'forma' => 'form_1040',
                'modo' => 'archivo',
                'file' => UploadedFile::fake()->create('w2.txt', 100, 'text/plain'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('documentos', 0);
    }

    public function test_marcar_una_forma_como_revisada(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);
        FormaCliente::query()->create(['user_id' => $cliente->id, 'forma' => 'form_1040', 'tax_year' => 2025, 'estado' => 'en_progreso']);

        $this->actingAs($preparador)
            ->post(route('clientes.marcar-revisado', ['cliente' => $cliente, 'forma' => 'form_1040']), ['tax_year' => 2025])
            ->assertRedirect();

        $forma = FormaCliente::query()->where('user_id', $cliente->id)->first();
        $this->assertNotNull($forma->revisado_en);
        $this->assertSame($preparador->id, $forma->revisado_por);
    }

    public function test_revelar_un_campo_sensible_exige_reconfirmar_la_contrasena(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);
        $campo = $this->crearCampo($cliente, 'identificacion_ssn_itin', ['valor_texto' => '123456789']);

        $this->actingAs($preparador)
            ->post(
                route('clientes.campos.reveal', ['cliente' => $cliente, 'campo' => 'identificacion_ssn_itin']).'?forma=form_1040&tax_year=2025',
                [],
                ['Accept' => 'application/json'],
            )
            ->assertStatus(423);

        $this->assertSame(0, CampoReveal::query()->count());

        $this->actingAs($preparador)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('clientes.campos.reveal', ['cliente' => $cliente, 'campo' => 'identificacion_ssn_itin']).'?forma=form_1040&tax_year=2025')
            ->assertOk()
            ->assertJson(['valor' => '123456789']);

        $this->assertSame(1, CampoReveal::query()->where('campo_cliente_id', $campo->id)->count());
    }

    public function test_un_preparador_puede_crear_un_cliente_y_queda_asignado_a_si_mismo(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);

        $this->actingAs($preparador)->post(route('clientes.store'), [
            'name' => 'Cliente Nuevo',
            'email' => 'cliente-nuevo@example.com',
        ])->assertRedirect();

        $cliente = User::query()->where('email', 'cliente-nuevo@example.com')->firstOrFail();
        $this->assertSame(UserRole::Client, $cliente->role);
        $this->assertSame($preparador->id, $cliente->preparer_id);
    }

    public function test_un_cliente_no_puede_crear_otro_cliente(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->actingAs($cliente)->post(route('clientes.store'), [
            'name' => 'Otro',
            'email' => 'otro@example.com',
        ])->assertForbidden();
    }

    public function test_un_preparador_no_puede_eliminar_un_cliente_pero_un_administrador_si(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);

        $this->actingAs($preparador)->delete(route('clientes.destroy', $cliente))->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $cliente->id]);

        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $this->actingAs($admin)->delete(route('clientes.destroy', $cliente))->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $cliente->id]);
    }

    public function test_agregar_un_campo_que_nunca_envio_el_agente(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);

        $this->actingAs($preparador)
            ->patch(route('clientes.campos.update', ['cliente' => $cliente, 'campo' => 'impuestos_retenidos']).'?forma=form_1040&tax_year=2025', [
                'forma' => 'form_1040',
                'modo' => 'texto',
                'tipo_dato' => 'number',
                'contenido' => 4500,
            ])
            ->assertRedirect();

        $campo = CampoCliente::query()->where('user_id', $cliente->id)->where('campo', 'impuestos_retenidos')->first();
        $this->assertNotNull($campo);
        $this->assertSame(4500, $campo->valor);
    }

    public function test_eliminar_un_campo_lo_borra_pero_conserva_el_historial(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);
        $this->crearCampo($cliente);

        $this->actingAs($preparador)
            ->delete(route('clientes.campos.destroy', ['cliente' => $cliente, 'campo' => 'impuestos_retenidos']).'?forma=form_1040&tax_year=2025')
            ->assertRedirect();

        $this->assertDatabaseMissing('campos_cliente', ['user_id' => $cliente->id, 'campo' => 'impuestos_retenidos']);

        $historial = HistorialCambio::query()
            ->where('user_id', $cliente->id)
            ->where('campo', 'impuestos_retenidos')
            ->latest('id')
            ->first();

        $this->assertNotNull($historial);
        $this->assertNull($historial->valor_nuevo);
        $this->assertSame(1000, $historial->valor_anterior);
    }

    public function test_el_buscador_filtra_clientes_por_nombre_email_o_telefono(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id, 'name' => 'Jane Doe', 'phone' => '+15551112222']);
        User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id, 'name' => 'John Smith']);

        $this->actingAs($preparador)
            ->get(route('clientes.index', ['search' => 'Jane']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('clientes.data.0.name', 'Jane Doe')
                ->where('search', 'Jane'));

        $this->actingAs($preparador)
            ->get(route('clientes.index', ['search' => '+15551112222']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('clientes.data.0.name', 'Jane Doe'));
    }
}
