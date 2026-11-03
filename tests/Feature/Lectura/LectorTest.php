<?php

namespace Tests\Feature\Lectura;

use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\Lectura\EstadoLectura;
use App\Enums\Lectura\Legibilidad;
use App\Enums\Lectura\MotivoConfianza;
use App\Enums\Lectura\OrigenDato;
use App\Enums\UserRole;
use App\Models\Documento;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaDocumento;
use App\Models\Lectura\DocumentoLectura;
use App\Models\User;
use App\Services\Entrevista\DocumentosEntrevista;
use App\Services\Entrevista\RespuestaData;
use App\Services\Entrevista\RespuestaGuardador;
use App\Services\Lectura\DatosDocumento;
use App\Services\Lectura\EsquemaLectura;
use App\Services\Lectura\LectorDocumentos;
use App\Services\Lectura\LectorManual;
use App\Services\Lectura\LectorOpenAi;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * F4.3d: el lector une páginas, esquema, OpenAI y confianza; guardar la
 * lectura deja los datos cifrados con su motivo; la bandera apaga la lectura
 * automática sin llamar a OpenAI.
 */
class LectorTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://api.openai.com/v1/chat/completions';

    private const SSN = 'XXX-XX-6789';

    private Entrevista $entrevista;

    protected function setUp(): void
    {
        parent::setUp();

        // Subir un archivo encola su lectura; aquí el lector se prueba a mano.
        Queue::fake();
        Storage::fake(Documento::disco());
        $this->seed(CatalogoFiscalSeeder::class);
        config([
            'tax.lectura_automatica' => true,
            'services.openai.api_key' => 'clave-de-prueba',
            'services.openai.vision_model' => 'modelo-de-vision',
            'services.openai.retry_backoff_ms' => 0,
        ]);

        $cliente = User::factory()->create(['role' => UserRole::Client]);
        $guardador = app(RespuestaGuardador::class);
        $this->entrevista = $guardador->entrevistaDe($cliente, CanalRespuesta::Portal);
        $guardador->guardar($this->entrevista, new RespuestaData('D1', 'si'), CanalRespuesta::Portal, $cliente);
    }

    private function item(): EntrevistaDocumento
    {
        return $this->entrevista->documentos()->where('documento_codigo', 'w2')->firstOrFail();
    }

    private function subir(string $nombre, string $contenido): Documento
    {
        return app(DocumentosEntrevista::class)->subir($this->item(), UploadedFile::fake()->createWithContent($nombre, $contenido));
    }

    private function pdf(): Documento
    {
        return $this->subir('w2.pdf', (string) file_get_contents(base_path('tests/Fixtures/lectura/dos-paginas.pdf')));
    }

    private function foto(): Documento
    {
        $imagen = imagecreatetruecolor(300, 200);
        imagefilledrectangle($imagen, 0, 0, 300, 200, imagecolorallocate($imagen, 240, 240, 240));

        ob_start();
        imagepng($imagen);

        return $this->subir('w2.png', (string) ob_get_clean());
    }

    private function lectura(Documento $archivo): DocumentoLectura
    {
        return DocumentoLectura::query()->create([
            'documento_id' => $archivo->id,
            'entrevista_documento_id' => $archivo->entrevista_documento_id,
            'lector' => 'openai',
            'estado' => EstadoLectura::EnCola,
        ]);
    }

    /**
     * @return array{valor: string|null, legibilidad: string, pagina: int|null, texto_cercano: string|null}
     */
    private function casilla(?string $valor, string $legibilidad = 'claro', ?int $pagina = 1, ?string $texto = null): array
    {
        return ['valor' => $valor, 'legibilidad' => $legibilidad, 'pagina' => $pagina, 'texto_cercano' => $texto];
    }

    /**
     * Una respuesta de OpenAI con la lectura de un W-2.
     *
     * @param  array<string, array<string, mixed>>  $casillas
     */
    private function fingirOpenAi(array $casillas, bool $legibles = true, string $tipo = 'w2'): void
    {
        $lectura = ['tipo_documento' => $tipo, 'anio' => 2025, 'paginas_legibles' => $legibles, 'casillas' => $casillas];

        Http::fake([self::URL => Http::response(['choices' => [['finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => json_encode($lectura)]]]])]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function w2(): array
    {
        return [
            'a' => $this->casilla(self::SSN, texto: 'a Employee SSN'),
            'b' => $this->casilla('12-3456789', texto: 'b Employer EIN'),
            'e' => $this->casilla('Ana Perez', 'dudoso'),
            '1' => $this->casilla('45120.00', texto: 'Wages, tips, other compensation'),
            '2' => $this->casilla('4512.00', texto: 'Federal income tax withheld'),
            '3' => $this->casilla('45.1.2'),
            '4' => $this->casilla('9999.99', texto: 'Social security tax withheld'),
            '5' => $this->casilla(null, 'no_se_ve'),
        ];
    }

    private function leerYGuardar(Documento $archivo): DocumentoLectura
    {
        $lectura = $this->lectura($archivo);
        $resultado = app(LectorDocumentos::class)->leer($archivo, $lectura, 'w2', 2025);

        app(DatosDocumento::class)->guardarLectura($lectura, $resultado);

        return $lectura->fresh();
    }

    public function test_un_w2_en_pdf_queda_con_sus_casillas_confianza_motivo_y_evidencia(): void
    {
        $this->fingirOpenAi($this->w2());

        $lectura = $this->leerYGuardar($this->pdf());

        $this->assertSame(EstadoLectura::Leida, $lectura->estado);
        $this->assertSame('openai', $lectura->lector);
        $this->assertSame('modelo-de-vision', $lectura->modelo);
        $this->assertSame('w2', $lectura->tipo_detectado);
        $this->assertSame(2025, $lectura->anio_detectado);
        $this->assertSame(2, $lectura->paginas);
        $this->assertNotNull($lectura->terminada_en);

        $datos = $lectura->datos->keyBy('casilla');

        $this->assertSame(count(app(EsquemaLectura::class)->casillas('w2', 2025)), $datos->count(), 'Hay un dato por cada casilla del W-2, también las que no se leyeron.');

        // En la capa de texto del PDF: 0,99 y con recuadro.
        $this->assertSame('45120.00', $datos['1']->valor);
        $this->assertSame('45120.00', $datos['1']->valor_leido);
        $this->assertSame(0.99, $datos['1']->confianza);
        $this->assertSame(MotivoConfianza::EnTextoDelPdf, $datos['1']->motivo_confianza);
        $this->assertSame(Legibilidad::Claro, $datos['1']->legibilidad);
        $this->assertSame(OrigenDato::Lector, $datos['1']->origen);
        $this->assertSame('Wages, tips, other compensation', $datos['1']->evidencia);
        $this->assertNotNull($datos['1']->recuadro);
        $this->assertSame(['x', 'y', 'ancho', 'alto'], array_keys($datos['1']->recuadro));
        $this->assertSame(1, $datos['1']->pagina);

        // El SSN truncado y el EIN también están en el texto.
        $this->assertSame(MotivoConfianza::EnTextoDelPdf, $datos['a']->motivo_confianza);
        $this->assertSame(MotivoConfianza::EnTextoDelPdf, $datos['b']->motivo_confianza);

        // Claro pero que no está en el texto: 0,90.
        $this->assertSame(0.90, $datos['4']->confianza);
        $this->assertSame(MotivoConfianza::Claro, $datos['4']->motivo_confianza);
        $this->assertNull($datos['4']->recuadro);

        // Mal formateado: 0,60.
        $this->assertSame(0.60, $datos['3']->confianza);
        $this->assertSame(MotivoConfianza::FormatoIncorrecto, $datos['3']->motivo_confianza);

        // Dudoso: 0,60.
        $this->assertSame(0.60, $datos['e']->confianza);
        $this->assertSame(MotivoConfianza::Dudoso, $datos['e']->motivo_confianza);

        // No se ve, o el modelo ni lo mencionó: 0 y sin valor.
        $this->assertSame(MotivoConfianza::SinValor, $datos['5']->motivo_confianza);
        $this->assertNull($datos['5']->valor);
        $this->assertSame(0.0, $datos['6']->confianza);
        $this->assertSame(MotivoConfianza::SinValor, $datos['6']->motivo_confianza);
    }

    public function test_lo_leido_queda_cifrado_en_la_base(): void
    {
        $this->fingirOpenAi($this->w2());

        $lectura = $this->leerYGuardar($this->pdf());

        $crudo = DB::table('documento_datos')->where('lectura_id', $lectura->id)->where('casilla', 'a')->first();

        foreach (['valor', 'valor_leido', 'evidencia'] as $columna) {
            $this->assertStringNotContainsString('6789', (string) $crudo->{$columna}, "{$columna} quedó en claro.");
        }

        $this->assertStringNotContainsString('Employee SSN', (string) $crudo->evidencia);
    }

    public function test_a_openai_se_le_manda_el_esquema_del_documento_y_las_paginas(): void
    {
        $this->fingirOpenAi($this->w2());

        $this->leerYGuardar($this->pdf());

        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            $cuerpo = $request->data();

            return $request->url() === self::URL
                && $cuerpo['model'] === 'modelo-de-vision'
                && $cuerpo['response_format']['json_schema']['name'] === 'lectura_w2'
                && count($cuerpo['messages'][1]['content']) === 3
                && str_starts_with($cuerpo['messages'][1]['content'][1]['image_url']['url'], 'data:image/png;base64,');
        });
    }

    public function test_una_foto_no_tiene_capa_de_texto_y_lo_claro_queda_en_0_90(): void
    {
        $this->fingirOpenAi($this->w2());

        $datos = $this->leerYGuardar($this->foto())->datos->keyBy('casilla');

        $this->assertSame(0.90, $datos['1']->confianza);
        $this->assertSame(MotivoConfianza::Claro, $datos['1']->motivo_confianza);
        $this->assertNull($datos['1']->recuadro);
        $this->assertSame('Wages, tips, other compensation', $datos['1']->evidencia);
        $this->assertSame(1, $datos['1']->pagina);
    }

    public function test_si_las_hojas_no_son_legibles_nada_se_da_por_claro(): void
    {
        $this->fingirOpenAi($this->w2(), legibles: false);

        $datos = $this->leerYGuardar($this->foto())->datos->keyBy('casilla');

        $this->assertSame(Legibilidad::Dudoso, $datos['1']->legibilidad);
        $this->assertSame(0.60, $datos['1']->confianza);
        $this->assertSame(MotivoConfianza::Dudoso, $datos['1']->motivo_confianza);
    }

    public function test_el_tipo_detectado_distinto_se_guarda_para_que_las_reglas_lo_revisen(): void
    {
        $this->fingirOpenAi([], tipo: 'desconocido');

        $lectura = $this->leerYGuardar($this->foto());

        $this->assertSame('desconocido', $lectura->tipo_detectado);
    }

    public function test_con_la_bandera_apagada_se_usa_el_lector_manual_y_no_se_llama_a_openai(): void
    {
        Http::fake();
        config(['tax.lectura_automatica' => false]);

        $this->assertInstanceOf(LectorManual::class, app(LectorDocumentos::class));

        $archivo = $this->pdf();
        $lectura = $this->leerYGuardar($archivo);

        $this->assertSame('manual', $lectura->lector);
        $this->assertSame(EstadoLectura::Leida, $lectura->estado);
        $this->assertCount(0, $lectura->datos);
        Http::assertNothingSent();
    }

    public function test_con_la_bandera_prendida_se_usa_el_lector_de_openai(): void
    {
        $this->assertInstanceOf(LectorOpenAi::class, app(LectorDocumentos::class));
    }

    public function test_un_documento_fuera_de_la_lista_usa_el_lector_manual_sin_llamar_a_openai(): void
    {
        Http::fake();

        $archivo = $this->pdf();
        $lectura = $this->lectura($archivo);

        $resultado = app(LectorDocumentos::class)->leer($archivo, $lectura, 'registros-ingresos-gastos', 2025);

        $this->assertSame('manual', $resultado->lector);
        $this->assertSame([], $resultado->casillas);
        $this->assertSame(0, $lectura->fresh()->paginas, 'No se generan páginas de lo que no se lee.');
        Http::assertNothingSent();
    }

    public function test_un_error_de_openai_deja_la_lectura_fallida_sin_datos_del_cliente(): void
    {
        Http::fake([self::URL => Http::response(['error' => ['code' => 'invalid_image', 'message' => 'SSN 123-45-6789 en la imagen']], 500)]);

        $archivo = $this->pdf();
        $lectura = $this->lectura($archivo);

        try {
            app(LectorDocumentos::class)->leer($archivo, $lectura, 'w2', 2025);
            $this->fail('Debió lanzar una excepción.');
        } catch (RuntimeException $e) {
            app(DatosDocumento::class)->fallar($lectura, $e);
        }

        $lectura = $lectura->fresh();
        $this->assertSame(EstadoLectura::Fallida, $lectura->estado);
        $this->assertStringContainsString('HTTP 500', (string) $lectura->error);
        $this->assertStringNotContainsString('123-45-6789', (string) $lectura->error);
        $this->assertNotNull($lectura->terminada_en);
        $this->assertCount(0, $lectura->datos);
    }

    public function test_de_un_error_inesperado_solo_se_guarda_su_clase(): void
    {
        $lectura = $this->lectura($this->pdf());

        app(DatosDocumento::class)->fallar($lectura, new \LogicException('SSN 123-45-6789'));

        $this->assertSame('Error inesperado (LogicException).', $lectura->fresh()->error);
    }

    public function test_sin_clave_o_modelo_de_openai_falla_antes_de_llamar(): void
    {
        Http::fake();
        config(['services.openai.vision_model' => null]);

        $archivo = $this->pdf();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OPENAI_VISION_MODEL');

        try {
            app(LectorDocumentos::class)->leer($archivo, $this->lectura($archivo), 'w2', 2025);
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_una_lectura_terminada_no_se_guarda_dos_veces_y_releer_no_toca_la_anterior(): void
    {
        $this->fingirOpenAi($this->w2());
        $archivo = $this->pdf();

        $primera = $this->leerYGuardar($archivo);
        $cuantos = $primera->datos()->count();

        try {
            app(DatosDocumento::class)->guardarLectura($primera, app(LectorDocumentos::class)->leer($archivo, $primera, 'w2', 2025));
            $this->fail('Una lectura terminada no se guarda otra vez.');
        } catch (RuntimeException) {
            $this->assertSame($cuantos, $primera->datos()->count());
        }

        $segunda = $this->leerYGuardar($archivo);

        $this->assertNotSame($primera->id, $segunda->id);
        $this->assertSame($cuantos, $primera->datos()->count(), 'La lectura anterior no se toca.');
        $this->assertSame($cuantos, $segunda->datos()->count());

        $vigentes = app(DatosDocumento::class)->vigentes($this->item());
        $this->assertTrue($vigentes->every(fn ($d) => $d->lectura_id === $segunda->id), 'Cuentan los datos de la última lectura.');
    }
}
