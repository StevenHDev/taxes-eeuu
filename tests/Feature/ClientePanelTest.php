<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\CampoCliente;
use App\Models\CampoDerivationLog;
use App\Models\CampoReveal;
use App\Models\Documento;
use App\Models\FormaCliente;
use App\Models\User;
use App\Models\WhatsappMensaje;
use App\Notifications\BienvenidaClientePortal;
use App\Support\TaxFieldCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
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
        Notification::fake();

        $preparador = User::factory()->create(['role' => UserRole::Preparer]);

        $this->actingAs($preparador)->post(route('clientes.store'), [
            'name' => 'Cliente Nuevo',
            'email' => 'cliente-nuevo@example.com',
        ])->assertRedirect();

        $cliente = User::query()->where('email', 'cliente-nuevo@example.com')->firstOrFail();
        $this->assertSame(UserRole::Client, $cliente->role);
        $this->assertSame($preparador->id, $cliente->preparer_id);

        // La cuenta se crea con una contraseña aleatoria que nadie conoce —
        // sin este aviso, este cliente nunca podría entrar al portal seguro.
        Notification::assertSentTo($cliente, BienvenidaClientePortal::class);
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
        $this->assertSoftDeleted('users', ['id' => $cliente->id]);
    }

    /**
     * F0.3: archivar a un cliente real conserva sus datos, documentos,
     * archivos y conversación (el IRS exige 3 años). Ya no aparece en la
     * lista ni puede iniciar sesión.
     */
    public function test_archivar_un_cliente_conserva_sus_datos_documentos_y_conversacion(): void
    {
        Storage::fake(Documento::disco());
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'phone' => '+15551234567']);
        $campo = $this->crearCampo($cliente);
        $documento = Documento::query()->create([
            'user_id' => $cliente->id, 'forma' => 'transversal', 'tax_year' => 2025, 'campo' => 'w2',
            'file_path' => 'documentos/w2.pdf', 'file_original_name' => 'w2.pdf', 'file_mime_type' => 'application/pdf',
            'file_size' => 10, 'formato' => 'pdf', 'estado_validacion' => 'recibido',
        ]);
        Storage::disk(Documento::disco())->put('documentos/w2.pdf', 'contenido');
        WhatsappMensaje::query()->create(['telefono' => '+15551234567', 'rol' => 'cliente', 'contenido' => 'hola', 'cliente_id' => $cliente->id]);

        $this->actingAs($admin)
            ->delete(route('clientes.destroy', $cliente), ['eliminar_conversacion_whatsapp' => true])
            ->assertRedirect(route('clientes.index'));

        $this->assertSoftDeleted('users', ['id' => $cliente->id]);
        $this->assertDatabaseHas('campos_cliente', ['id' => $campo->id]);
        $this->assertNotSoftDeleted('documentos', ['id' => $documento->id]);
        Storage::disk(Documento::disco())->assertExists('documentos/w2.pdf');
        $this->assertDatabaseHas('whatsapp_mensajes', ['telefono' => '+15551234567']);

        $this->actingAs($admin)
            ->get(route('clientes.index'))
            ->assertInertia(fn (Assert $page) => $page->has('clientes', 0));
    }

    public function test_un_cliente_archivado_no_puede_iniciar_sesion(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        $cliente->delete();

        $this->post(route('login.store'), ['email' => $cliente->email, 'password' => 'password']);

        $this->assertGuest();
    }

    /**
     * El buscador es client-side (DataTable de TanStack en el navegador, ver
     * resources/js/pages/clientes/index.tsx) — el servidor siempre entrega la
     * colección completa visible para el actor, sin paginar ni filtrar por
     * `search`, para que el filtrado/orden/paginado corran en el navegador
     * sin ida y vuelta al servidor.
     */
    public function test_el_index_entrega_la_coleccion_completa_de_clientes_visibles_sin_filtrar_en_el_servidor(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        // created_at explícito y distinto: el controlador ordena por
        // created_at desc (ManagesClientes/ClienteController::index), así que
        // sin esto el orden entre ambos quedaría indeterminado si el factory
        // les asigna el mismo segundo.
        User::factory()->create([
            'role' => UserRole::Client, 'preparer_id' => $preparador->id,
            'name' => 'Jane Doe', 'phone' => '+15551112222', 'created_at' => now()->subMinute(),
        ]);
        User::factory()->create([
            'role' => UserRole::Client, 'preparer_id' => $preparador->id,
            'name' => 'John Smith', 'created_at' => now(),
        ]);

        $this->actingAs($preparador)
            ->get(route('clientes.index', ['search' => 'Jane']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('clientes', 2)
                ->where('clientes.0.name', 'John Smith')
                ->where('clientes.1.name', 'Jane Doe'));
    }

    /**
     * Ver CampoDerivationLog / EventoRecoleccionService::registrarDerivacion().
     */
    public function test_el_show_expone_los_derivation_logs_del_ano_fiscal_actual(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);

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
            'relaciones_esperadas' => [
                ['forma' => 'form_1040', 'campo' => 'salarios_medicare', 'subcampo' => null, 'descripcion' => null, 'acumulable' => true],
            ],
            'revelados_recibidos' => [],
            'relaciones_faltantes' => [
                ['forma' => 'form_1040', 'campo' => 'salarios_medicare', 'subcampo' => null, 'descripcion' => null, 'acumulable' => true],
            ],
        ]);

        $this->actingAs($preparador)
            ->get(route('clientes.show', ['cliente' => $cliente, 'tax_year' => 2025]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('derivationLogs', 1)
                ->where('derivationLogs.0.documento_campo', 'w2')
                ->where('derivationLogs.0.documento_nombre', 'w2.pdf')
                ->has('derivationLogs.0.relaciones_faltantes', 1));
    }

    public function test_el_listado_solo_ofrece_formas_en_alcance_pero_muestra_las_viejas_del_cliente(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        FormaCliente::query()->create(['user_id' => $cliente->id, 'forma' => 'form_1065', 'tax_year' => 2025, 'estado' => 'en_progreso']);

        $this->actingAs($admin)
            ->get(route('clientes.index', ['tax_year' => 2025]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('formas', fn ($formas) => collect($formas)->pluck('value')->all() === ['form_1040', 'schedule_c', 'schedule_e', 'schedule_f'])
                ->where('clientes.0.formas.0.forma', 'form_1065'));

        $this->actingAs($admin)->get(route('clientes.show', ['cliente' => $cliente, 'tax_year' => 2025]))->assertOk();
    }

    public function test_los_datos_del_formulario_anterior_son_de_solo_lectura_en_la_ficha(): void
    {
        // F2.11: se corrigen en la sección Entrevista; las rutas web de
        // edición ya no existen (la API externa sigue con las suyas).
        $this->assertFalse(Route::has('clientes.campos.update'));
        $this->assertFalse(Route::has('clientes.campos.destroy'));
        $this->assertTrue(Route::has('clientes.campos.historial'));
        $this->assertTrue(Route::has('clientes.campos.reveal'));
    }
}
