<?php

namespace Tests\Feature\Lectura;

use App\Services\Lectura\EsquemaLectura;
use App\Services\WhatsappAgent\OpenAiClient;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/**
 * F4.3b: el esquema que se le pide a OpenAI sale de las casillas del
 * catálogo, y la llamada con salida estructurada no filtra lo leído.
 */
class EsquemaLecturaTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://api.openai.com/v1/chat/completions';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogoFiscalSeeder::class);
        config([
            'services.openai.api_key' => 'clave-de-prueba',
            'services.openai.vision_model' => 'modelo-de-vision',
            'services.openai.retry_backoff_ms' => 0,
        ]);
    }

    private function esquemas(): EsquemaLectura
    {
        return app(EsquemaLectura::class);
    }

    /**
     * Una respuesta válida de OpenAI con el JSON de la lectura.
     *
     * @param  array<string, mixed>  $lectura
     * @return array<string, mixed>
     */
    private function respuesta(array $lectura, string $finish = 'stop'): array
    {
        return ['choices' => [['finish_reason' => $finish, 'message' => ['role' => 'assistant', 'content' => json_encode($lectura)]]]];
    }

    /**
     * Cada objeto del esquema cumple el modo estricto de OpenAI.
     *
     * @param  array<string, mixed>  $nodo
     */
    private function afirmarEstricto(array $nodo, string $ruta = 'raíz'): void
    {
        if (($nodo['type'] ?? null) === 'object') {
            $this->assertFalse($nodo['additionalProperties'], "{$ruta}: admite propiedades de más.");
            $this->assertSame(array_map('strval', array_keys($nodo['properties'])), $nodo['required'], "{$ruta}: required no lista todas las propiedades.");

            foreach ($nodo['required'] as $requerida) {
                $this->assertIsString($requerida, "{$ruta}: required lleva algo que no es texto.");
            }

            foreach ($nodo['properties'] as $nombre => $hijo) {
                $this->afirmarEstricto($hijo, "{$ruta}.{$nombre}");
            }
        }
    }

    public function test_el_esquema_del_w2_trae_sus_casillas_en_modo_estricto(): void
    {
        $esquema = $this->esquemas()->para('w2', 2025);

        $this->afirmarEstricto($esquema);
        $this->assertSame(['tipo_documento', 'anio', 'paginas_legibles', 'casillas'], $esquema['required']);

        $casillas = $esquema['properties']['casillas']['properties'];

        foreach (['a', 'b', 'e', 'anio', '1', '2', '3', '4', '7', '10', '12a-codigo', '12a-monto', '12d-monto'] as $casilla) {
            $this->assertArrayHasKey($casilla, $casillas, "Falta la casilla {$casilla} del W-2.");
        }

        $uno = $casillas['1'];
        $this->assertSame(['valor', 'legibilidad', 'pagina', 'texto_cercano'], $uno['required']);
        $this->assertSame(['claro', 'dudoso', 'no_se_ve'], $uno['properties']['legibilidad']['enum']);
        $this->assertSame(['string', 'null'], $uno['properties']['valor']['type']);
        $this->assertStringContainsString('Box 1:', $uno['properties']['valor']['description']);
        $this->assertStringContainsString('no thousands separators', $uno['properties']['valor']['description'], 'Un monto se pide sin separadores.');
    }

    public function test_el_esquema_del_1095a_trae_las_personas_cubiertas_y_los_meses(): void
    {
        $esquema = $this->esquemas()->para('1095-a', 2025);

        $this->afirmarEstricto($esquema);

        $casillas = array_keys($esquema['properties']['casillas']['properties']);

        foreach (['5', '16-A', '20-E', '21-A', '32-C', '33-A', '33-C'] as $casilla) {
            $this->assertContains((string) $casilla, array_map('strval', $casillas), "Falta la casilla {$casilla} del 1095-A.");
        }

        $this->assertCount(74, $casillas);
    }

    public function test_todos_los_documentos_que_se_leen_dan_un_esquema_que_se_codifica_como_objeto(): void
    {
        foreach ([2025, 2026] as $anio) {
            $leibles = array_keys($this->esquemas()->documentosLeibles($anio));
            // 22 + los 6 documentos de montos de F6 (6.3a): recibo de cuidado, comprobantes de pagos, útiles del educador,
            // horas extra y propinas, préstamo del vehículo y registros del negocio.
            $this->assertCount(28, $leibles, "{$anio}: son 28 los documentos que se leen.");

            // OpenAI rechaza el esquema entero si un valor del enum de texto es un número: el código "1098" (Form 1098)
            // se vuelve entero al ser clave de un arreglo de PHP. Con eso ninguna lectura funcionó en producción.
            $tipos = $this->esquemas()->para('w2', $anio)['properties']['tipo_documento']['enum'];
            $this->assertSame($tipos, array_map('strval', $tipos), "{$anio}: todos los tipos del enum son texto.");
            $this->assertContains('1098', $tipos);

            foreach ($leibles as $codigo) {
                $esquema = $this->esquemas()->para($codigo, $anio);
                $this->afirmarEstricto($esquema);

                // Con llaves como "1" y "2" PHP puede codificar una lista en vez de un objeto.
                $json = json_encode($esquema, JSON_THROW_ON_ERROR);
                $this->assertStringContainsString('"casillas":{"type":"object","properties":{"', $json, "{$anio} {$codigo}: casillas no es un objeto.");
                $this->assertStringNotContainsString('"properties":[', $json, "{$anio} {$codigo}: unas propiedades salieron como lista.");
            }
        }
    }

    public function test_tipo_documento_es_una_lista_cerrada_con_los_codigos_del_catalogo(): void
    {
        $tipo = $this->esquemas()->para('w2', 2025)['properties']['tipo_documento'];

        $this->assertContains('w2', $tipo['enum']);
        $this->assertContains('1095-a', $tipo['enum']);
        $this->assertContains('tarjeta-ssn', $tipo['enum']);
        $this->assertSame(EsquemaLectura::DESCONOCIDO, last($tipo['enum']));
        $this->assertNotContains('datos-bancarios', $tipo['enum'], 'Lo que no tiene casillas no se lee.');
    }

    public function test_2026_toma_las_casillas_de_2026(): void
    {
        $nec2025 = array_keys($this->esquemas()->para('1099-nec', 2025)['properties']['casillas']['properties']);
        $nec2026 = array_keys($this->esquemas()->para('1099-nec', 2026)['properties']['casillas']['properties']);

        $this->assertContains(1, $nec2025);
        $this->assertNotContains('1a', $nec2025);
        $this->assertContains('1a', $nec2026, 'En 2026 la compensación pasa de la casilla 1 a la 1a.');
        $this->assertNotContains(1, $nec2026);
    }

    public function test_un_documento_sin_casillas_o_que_no_existe_lanza_un_error(): void
    {
        foreach (['datos-bancarios', 'no-existe'] as $codigo) {
            try {
                $this->esquemas()->para($codigo, 2025);
                $this->fail("{$codigo} debió dar error.");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString($codigo, $e->getMessage());
            }
        }
    }

    public function test_las_instrucciones_dicen_el_ano_y_el_documento_y_no_dejan_inventar(): void
    {
        $instrucciones = $this->esquemas()->instrucciones('w2', 2025);

        $this->assertStringContainsString('2025', $instrucciones);
        $this->assertStringContainsString('Form W-2', $instrucciones);
        $this->assertStringContainsString('never infer', $instrucciones);
        $this->assertStringContainsString('no_se_ve', $instrucciones);
    }

    public function test_la_llamada_manda_el_modelo_las_imagenes_y_el_esquema_y_devuelve_el_json(): void
    {
        Http::fake([self::URL => Http::response($this->respuesta(['tipo_documento' => 'w2', 'anio' => 2025]))]);
        $esquema = $this->esquemas()->para('w2', 2025);

        $leido = app(OpenAiClient::class)->leerConEsquema(
            $this->esquemas()->instrucciones('w2', 2025),
            ['data:image/png;base64,AAAA', 'data:image/png;base64,BBBB'],
            $esquema,
            'lectura_w2',
        );

        $this->assertSame(['tipo_documento' => 'w2', 'anio' => 2025], $leido);

        Http::assertSent(function (Request $request) use ($esquema) {
            $cuerpo = $request->data();
            $usuario = $cuerpo['messages'][1]['content'];

            return $request->url() === self::URL
                && $cuerpo['model'] === 'modelo-de-vision'
                && $cuerpo['messages'][0]['role'] === 'system'
                && str_contains($cuerpo['messages'][0]['content'], '2025')
                && $usuario[1]['image_url']['url'] === 'data:image/png;base64,AAAA'
                && $usuario[2]['image_url']['url'] === 'data:image/png;base64,BBBB'
                && $usuario[1]['image_url']['detail'] === 'high'
                && $cuerpo['response_format']['type'] === 'json_schema'
                && $cuerpo['response_format']['json_schema']['strict'] === true
                && $cuerpo['response_format']['json_schema']['name'] === 'lectura_w2'
                && $cuerpo['response_format']['json_schema']['schema'] === json_decode(json_encode($esquema), true)
                && $request->hasHeader('Authorization', 'Bearer clave-de-prueba');
        });
    }

    public function test_un_error_de_http_no_copia_lo_que_dijo_openai(): void
    {
        Http::fake([self::URL => Http::response(['error' => ['code' => 'invalid_image', 'message' => 'SSN 123-45-6789 en la imagen']], 400)]);

        try {
            app(OpenAiClient::class)->leerConEsquema('instrucciones', ['data:image/png;base64,AAAA'], ['type' => 'object']);
            $this->fail('Debió lanzar una excepción.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('HTTP 400', $e->getMessage());
            $this->assertStringContainsString('invalid_image', $e->getMessage());
            $this->assertStringNotContainsString('123-45-6789', $e->getMessage());
        }
    }

    public function test_una_respuesta_cortada_rechazada_o_sin_json_lanza_un_error_sin_el_contenido(): void
    {
        $casos = [
            'cortada' => $this->respuesta(['valor' => 'SECRETO-1'], 'length'),
            'rechazada' => ['choices' => [['finish_reason' => 'stop', 'message' => ['content' => null, 'refusal' => 'SECRETO-2']]]],
            'sin JSON' => ['choices' => [['finish_reason' => 'stop', 'message' => ['content' => 'esto no es json SECRETO-3']]]],
            'sin choices' => ['id' => 'x'],
        ];

        foreach ($casos as $nombre => $cuerpo) {
            Http::fake([self::URL => Http::response($cuerpo)]);

            try {
                app(OpenAiClient::class)->leerConEsquema('instrucciones', ['data:image/png;base64,AAAA'], ['type' => 'object']);
                $this->fail("El caso '{$nombre}' debió lanzar una excepción.");
            } catch (RuntimeException $e) {
                $this->assertStringNotContainsString('SECRETO', $e->getMessage(), "El caso '{$nombre}' copió el contenido.");
            }
        }
    }

    public function test_sin_imagenes_no_se_llama_a_openai(): void
    {
        Http::fake();

        try {
            app(OpenAiClient::class)->leerConEsquema('instrucciones', [], ['type' => 'object']);
            $this->fail('Debió exigir una imagen.');
        } catch (InvalidArgumentException) {
            Http::assertNothingSent();
        }
    }
}
