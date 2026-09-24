<?php

namespace Tests\Feature;

use App\Enums\EventSource;
use App\Enums\FieldState;
use App\Enums\UserRole;
use App\Models\CampoCliente;
use App\Models\Documento;
use App\Models\FormaCliente;
use App\Models\User;
use Database\Seeders\PromptActivoStepsSeeder;
use Database\Seeders\RelacionesDocumentoCampoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PortalFormularioControllerTest extends TestCase
{
    use RefreshDatabase;

    private const TAX_YEAR = 2025;

    public function test_un_invitado_es_redirigido_a_login(): void
    {
        $this->get(route('portal.formulario'))->assertRedirect(route('login'));
    }

    public function test_un_preparador_no_puede_entrar_al_formulario_del_cliente(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);

        $this->actingAs($preparador)->get(route('portal.formulario'))->assertForbidden();
    }

    public function test_un_cliente_sin_formas_declaradas_va_al_chat(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);

        $this->actingAs($cliente)
            ->get(route('portal.formulario'))
            ->assertRedirect(route('portal.chat'));
    }

    public function test_un_cliente_con_formas_declaradas_ve_sus_campos_pendientes(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        FormaCliente::query()->create(['user_id' => $cliente->id, 'forma' => 'form_1040', 'tax_year' => self::TAX_YEAR, 'estado' => 'en_progreso']);

        $this->actingAs($cliente)
            ->get(route('portal.formulario'))
            ->assertInertia(fn ($page) => $page
                ->component('portal/formulario')
                ->where('taxYear', self::TAX_YEAR)
                ->where('respondidos', [])
                ->has('pendientes', fn ($pendientes) => $pendientes
                    ->where(0, fn ($campo) => true)
                    ->etc())
                ->where('pendientes.0.forma', 'transversal'));
    }

    public function test_w2_y_1099_nec_no_son_obligatorios(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        FormaCliente::query()->create(['user_id' => $cliente->id, 'forma' => 'form_1040', 'tax_year' => self::TAX_YEAR, 'estado' => 'en_progreso']);

        $this->actingAs($cliente)
            ->get(route('portal.formulario'))
            ->assertInertia(fn ($page) => $page
                ->component('portal/formulario')
                ->where('taxYear', self::TAX_YEAR)
                ->where('respondidos', [])
                ->has('pendientes', fn ($pendientes) => $pendientes
                    ->where(0, fn ($campo) => true)
                    ->etc())
                ->where(
                    'pendientes',
                    fn ($pendientes) => collect($pendientes)
                        ->firstWhere('campo', 'w2')['obligatorio'] === false
                        && collect($pendientes)->firstWhere('campo', 'form_1099_nec')['obligatorio'] === false,
                ));
    }

    public function test_los_campos_de_un_grupo_de_baja_frecuencia_traen_su_etiqueta(): void
    {
        $this->seed(PromptActivoStepsSeeder::class);

        $cliente = User::factory()->create(['role' => UserRole::Client]);
        FormaCliente::query()->create(['user_id' => $cliente->id, 'forma' => 'form_1040', 'tax_year' => self::TAX_YEAR, 'estado' => 'en_progreso']);

        $this->actingAs($cliente)
            ->get(route('portal.formulario'))
            ->assertInertia(fn ($page) => $page
                ->component('portal/formulario')
                ->has('pendientes', fn ($pendientes) => $pendientes
                    ->where(0, fn ($campo) => true)
                    ->etc())
                ->where(
                    'pendientes',
                    fn ($pendientes) => collect($pendientes)
                        ->firstWhere('campo', 'perdida_capital_arrastrada')['grupo'] === 'Inversiones menos comunes',
                ));
    }

    public function test_info_conyuge_no_aparece_pendiente_para_un_cliente_soltero(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        FormaCliente::query()->create(['user_id' => $cliente->id, 'forma' => 'form_1040', 'tax_year' => self::TAX_YEAR, 'estado' => 'en_progreso']);

        $this->actingAs($cliente)
            ->get(route('portal.formulario'))
            ->assertInertia(fn ($page) => $page
                ->component('portal/formulario')
                ->has('pendientes', fn ($pendientes) => $pendientes
                    ->where(0, fn ($campo) => true)
                    ->etc())
                ->where(
                    'pendientes',
                    fn ($pendientes) => collect($pendientes)->firstWhere('campo', 'info_conyuge') === null
                        && collect($pendientes)->firstWhere('campo', 'mas_w2') === null,
                ));
    }

    public function test_info_conyuge_aparece_pendiente_una_vez_que_estado_civil_dice_casado(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        FormaCliente::query()->create(['user_id' => $cliente->id, 'forma' => 'form_1040', 'tax_year' => self::TAX_YEAR, 'estado' => 'en_progreso']);

        CampoCliente::query()->create([
            'user_id' => $cliente->id,
            'forma' => 'transversal',
            'tax_year' => self::TAX_YEAR,
            'campo' => 'estado_civil',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'valor_texto' => ['casado_al_31_dic' => true],
            'estado' => 'recibido',
            'source' => 'preparador',
        ]);

        $this->actingAs($cliente)
            ->get(route('portal.formulario'))
            ->assertInertia(fn ($page) => $page
                ->component('portal/formulario')
                ->has('pendientes', fn ($pendientes) => $pendientes
                    ->where(0, fn ($campo) => true)
                    ->etc())
                ->where(
                    'pendientes',
                    fn ($pendientes) => collect($pendientes)->firstWhere('campo', 'info_conyuge') !== null,
                ));
    }

    /**
     * Regresión (2026-09-24): el formulario del portal (CampoValorInput)
     * nunca produce un boolean real, solo texto — "si" debe detectarse igual
     * de bien que `true`.
     */
    public function test_info_conyuge_aparece_pendiente_cuando_estado_civil_dice_casado_como_texto(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        FormaCliente::query()->create(['user_id' => $cliente->id, 'forma' => 'form_1040', 'tax_year' => self::TAX_YEAR, 'estado' => 'en_progreso']);

        CampoCliente::query()->create([
            'user_id' => $cliente->id,
            'forma' => 'transversal',
            'tax_year' => self::TAX_YEAR,
            'campo' => 'estado_civil',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'valor_texto' => ['casado_al_31_dic' => 'si'],
            'estado' => 'recibido',
            'source' => 'cliente',
        ]);

        $this->actingAs($cliente)
            ->get(route('portal.formulario'))
            ->assertInertia(fn ($page) => $page
                ->component('portal/formulario')
                ->has('pendientes', fn ($pendientes) => $pendientes
                    ->where(0, fn ($campo) => true)
                    ->etc())
                ->where(
                    'pendientes',
                    fn ($pendientes) => collect($pendientes)->firstWhere('campo', 'info_conyuge') !== null,
                ));
    }

    public function test_un_cliente_puede_guardar_un_campo_de_texto_directo(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        FormaCliente::query()->create(['user_id' => $cliente->id, 'forma' => 'form_1040', 'tax_year' => self::TAX_YEAR, 'estado' => 'en_progreso']);

        $this->actingAs($cliente)
            ->post(route('portal.formulario.campos.store'), [
                'forma' => 'transversal',
                'tax_year' => self::TAX_YEAR,
                'campo' => 'identificacion_ssn_itin',
                'tipo_campo' => 'dato',
                'modo' => 'texto',
                'tipo_dato' => 'string',
                'contenido' => '123-45-6789',
            ])
            ->assertRedirect();

        $campoCliente = CampoCliente::query()
            ->where('user_id', $cliente->id)
            ->where('forma', 'transversal')
            ->where('campo', 'identificacion_ssn_itin')
            ->first();

        $this->assertNotNull($campoCliente);
        $this->assertSame(FieldState::Recibido, $campoCliente->estado);
        $this->assertSame(EventSource::Cliente, $campoCliente->source);
    }

    /**
     * Mismo generador de PDF mínimo válido que AgenteConversacionalServiceTest
     * — necesario desde que subir un documento que "revela" algo (ver
     * TaxFieldCatalog::revelaPara()) intenta extraer su texto de verdad
     * (RevelacionExtractorService), y un PDF inválido tronaba al caer al
     * respaldo de visión (pdftoppm).
     */
    private function crearPdfConTexto(string $texto): string
    {
        $streamContenido = "BT /F1 12 Tf 20 700 Td ({$texto}) Tj ET";

        $objetos = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /MediaBox [0 0 612 792] /Contents 5 0 R >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            5 => '<< /Length '.strlen($streamContenido).' >>'."\nstream\n{$streamContenido}\nendstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objetos as $numero => $cuerpo) {
            $offsets[$numero] = strlen($pdf);
            $pdf .= "{$numero} 0 obj\n{$cuerpo}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $total = count($objetos) + 1;
        $pdf .= "xref\n0 {$total}\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer\n<< /Size {$total} /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        $ruta = tempnam(sys_get_temp_dir(), 'portal_formulario_').'.pdf';
        file_put_contents($ruta, $pdf);

        return $ruta;
    }

    /**
     * @param  array<string, mixed>  $respuesta
     */
    private function respuestaOpenAi(array $respuesta): array
    {
        return ['choices' => [['message' => $respuesta]]];
    }

    public function test_un_cliente_puede_subir_un_documento_directo(): void
    {
        Storage::fake('s3');
        config(['services.openai.api_key' => 'test-key', 'services.openai.model' => 'test-model']);

        $cliente = User::factory()->create(['role' => UserRole::Client]);
        FormaCliente::query()->create(['user_id' => $cliente->id, 'forma' => 'form_1040', 'tax_year' => self::TAX_YEAR, 'estado' => 'en_progreso']);

        $rutaPdf = $this->crearPdfConTexto('W-2 de prueba sin datos que la IA pueda leer.');
        $archivo = new UploadedFile($rutaPdf, 'w2.pdf', 'application/pdf', test: true);

        // w2 revela otros campos (ver RevelacionExtractorService) — cualquier
        // subida pasa por una llamada a la IA para ver si hay algo que
        // completar; acá se simula que no encontró nada.
        Http::fake([
            'api.openai.com/*' => Http::response($this->respuestaOpenAi([
                'role' => 'assistant',
                'tool_calls' => [[
                    'id' => 'call_1',
                    'type' => 'function',
                    'function' => ['name' => 'registrar_revelados', 'arguments' => json_encode(['revelados' => []])],
                ]],
            ])),
        ]);

        $this->actingAs($cliente)
            ->post(route('portal.formulario.campos.store'), [
                'forma' => 'transversal',
                'tax_year' => self::TAX_YEAR,
                'campo' => 'w2',
                'tipo_campo' => 'documento',
                'modo' => 'archivo',
                'archivo' => $archivo,
            ])
            ->assertRedirect();

        $documento = Documento::query()->where('user_id', $cliente->id)->where('campo', 'w2')->first();
        $this->assertNotNull($documento);

        unlink($rutaPdf);
    }

    public function test_subir_un_documento_que_revela_completa_los_campos_revelados_por_ia(): void
    {
        Storage::fake('s3');
        config(['services.openai.api_key' => 'test-key', 'services.openai.model' => 'test-model']);
        $this->seed(RelacionesDocumentoCampoSeeder::class);

        $cliente = User::factory()->create(['role' => UserRole::Client]);
        FormaCliente::query()->create(['user_id' => $cliente->id, 'forma' => 'form_1040', 'tax_year' => self::TAX_YEAR, 'estado' => 'en_progreso']);

        $rutaPdf = $this->crearPdfConTexto('Box 2 Federal income tax withheld: 1500.00');
        $archivo = new UploadedFile($rutaPdf, 'w2.pdf', 'application/pdf', test: true);

        Http::fake([
            'api.openai.com/*' => Http::response($this->respuestaOpenAi([
                'role' => 'assistant',
                'tool_calls' => [[
                    'id' => 'call_1',
                    'type' => 'function',
                    'function' => [
                        'name' => 'registrar_revelados',
                        'arguments' => json_encode([
                            'revelados' => [
                                ['campo' => 'impuestos_retenidos', 'contenido' => '1500'],
                            ],
                        ]),
                    ],
                ]],
            ])),
        ]);

        $this->actingAs($cliente)
            ->post(route('portal.formulario.campos.store'), [
                'forma' => 'transversal',
                'tax_year' => self::TAX_YEAR,
                'campo' => 'w2',
                'tipo_campo' => 'documento',
                'modo' => 'archivo',
                'archivo' => $archivo,
            ])
            ->assertRedirect();

        $documento = Documento::query()->where('user_id', $cliente->id)->where('campo', 'w2')->first();
        $this->assertNotNull($documento);

        $retenciones = CampoCliente::query()
            ->where('user_id', $cliente->id)
            ->where('campo', 'impuestos_retenidos')
            ->first();

        $this->assertNotNull($retenciones);
        $this->assertEquals('1500', (string) $retenciones->valor);
        $this->assertSame(EventSource::AgenteIa, $retenciones->source);

        unlink($rutaPdf);
    }

    public function test_un_campo_que_no_coincide_con_el_catalogo_no_se_guarda(): void
    {
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        FormaCliente::query()->create(['user_id' => $cliente->id, 'forma' => 'form_1040', 'tax_year' => self::TAX_YEAR, 'estado' => 'en_progreso']);

        $this->actingAs($cliente)
            ->post(route('portal.formulario.campos.store'), [
                'forma' => 'transversal',
                'tax_year' => self::TAX_YEAR,
                'campo' => 'identificacion_ssn_itin',
                // tipo_campo incorrecto a propósito: el catálogo dice "dato".
                'tipo_campo' => 'documento',
                'modo' => 'texto',
                'tipo_dato' => 'string',
                'contenido' => '123-45-6789',
            ])
            ->assertSessionHasErrors();

        $this->assertDatabaseCount('campos_cliente', 0);
    }
}
