<?php

namespace Tests\Feature;

use App\Services\DocumentoExtraccion\RevelacionExtractorService;
use App\Services\WhatsappAgent\OpenAiClient;
use App\Support\EventoValidator;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RevelacionExtractorServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openai.api_key' => 'test-key',
            'services.openai.model' => 'test-model',
        ]);
    }

    private function servicio(): RevelacionExtractorService
    {
        return new RevelacionExtractorService(new OpenAiClient, new EventoValidator);
    }

    private function fakeToolCall(array $revelados): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'tool_calls' => [[
                            'function' => [
                                'name' => 'registrar_revelados',
                                'arguments' => json_encode(['revelados' => $revelados]),
                            ],
                        ]],
                    ],
                ]],
            ], 200),
        ]);
    }

    /**
     * Reproduce el incidente real en producción (2026-10-16): un 1095-A
     * declara 3 relaciones hacia el MISMO campo-destino (marketplace_seguro)
     * con distinto subcampo cada una. Antes del fix, combinarConMetadata()
     * las indexaba por 'campo' con keyBy() y las 3 colapsaban en una sola
     * (la IA sí extraía los 3 valores, pero los 3 quedaban etiquetados con
     * el subcampo de la última relación declarada) — acá confirmamos que
     * las 3 llegan separadas y con su propio subcampo.
     */
    public function test_separa_relaciones_con_el_mismo_campo_pero_distinto_subcampo(): void
    {
        $revela = [
            ['forma' => 'form_1040', 'campo' => 'marketplace_seguro', 'subcampo' => 'premium_mensual', 'descripcion' => 'Columna A', 'acumulable' => false, 'tipo_campo' => 'dato', 'tipo_dato' => 'object'],
            ['forma' => 'form_1040', 'campo' => 'marketplace_seguro', 'subcampo' => 'slcsp', 'descripcion' => 'Columna B', 'acumulable' => false, 'tipo_campo' => 'dato', 'tipo_dato' => 'object'],
            ['forma' => 'form_1040', 'campo' => 'marketplace_seguro', 'subcampo' => 'aptc_recibido', 'descripcion' => 'Columna C', 'acumulable' => false, 'tipo_campo' => 'dato', 'tipo_dato' => 'object'],
        ];

        $this->fakeToolCall([
            ['id' => '0', 'contenido' => '100.00'],
            ['id' => '1', 'contenido' => '90.00'],
            ['id' => '2', 'contenido' => '80.00'],
        ]);

        $resultado = $this->servicio()->extraer('texto del 1095-A', $revela);

        $this->assertCount(3, $resultado);

        $porSubcampo = collect($resultado)->keyBy('subcampo');
        $this->assertSame('100.00', $porSubcampo['premium_mensual']['contenido']);
        $this->assertSame('90.00', $porSubcampo['slcsp']['contenido']);
        $this->assertSame('80.00', $porSubcampo['aptc_recibido']['contenido']);
    }

    public function test_sigue_funcionando_cuando_cada_relacion_tiene_un_campo_distinto(): void
    {
        $revela = [
            ['forma' => 'form_1040', 'campo' => 'ingresos', 'subcampo' => 'salarios', 'descripcion' => 'Box 1', 'acumulable' => false, 'tipo_campo' => 'dato', 'tipo_dato' => 'object'],
            ['forma' => 'form_1040', 'campo' => 'impuestos_retenidos', 'subcampo' => null, 'descripcion' => 'Box 2', 'acumulable' => true, 'tipo_campo' => 'dato', 'tipo_dato' => 'number'],
        ];

        $this->fakeToolCall([
            ['id' => '0', 'contenido' => '50000'],
            ['id' => '1', 'contenido' => '5000'],
        ]);

        $resultado = $this->servicio()->extraer('texto del w2', $revela);

        $this->assertCount(2, $resultado);
        $porCampo = collect($resultado)->keyBy('campo');
        $this->assertSame('50000', $porCampo['ingresos']['contenido']);
        $this->assertSame('5000', $porCampo['impuestos_retenidos']['contenido']);
        $this->assertTrue($porCampo['impuestos_retenidos']['acumular']);
    }

    /**
     * Un id repetido en la respuesta del modelo no debe duplicar el valor —
     * crítico para un campo acumulable (EventoRecoleccionService SUMA en vez
     * de sobrescribir), donde contar el mismo hallazgo dos veces inflaría el
     * monto guardado.
     */
    public function test_descarta_un_id_repetido(): void
    {
        $revela = [
            ['forma' => 'form_1040', 'campo' => 'impuestos_retenidos', 'subcampo' => null, 'descripcion' => 'Box 2', 'acumulable' => true, 'tipo_campo' => 'dato', 'tipo_dato' => 'number'],
        ];

        $this->fakeToolCall([
            ['id' => '0', 'contenido' => '5000'],
            ['id' => '0', 'contenido' => '5000'],
        ]);

        $resultado = $this->servicio()->extraer('texto del w2', $revela);

        $this->assertCount(1, $resultado);
    }

    /**
     * Un id fuera de rango (el modelo no respetó el enum, o alucinó uno) se
     * descarta en silencio en vez de romper el guardado del resto.
     */
    public function test_descarta_un_id_fuera_de_rango_sin_afectar_los_demas(): void
    {
        $revela = [
            ['forma' => 'form_1040', 'campo' => 'ingresos', 'subcampo' => 'salarios', 'descripcion' => 'Box 1', 'acumulable' => false, 'tipo_campo' => 'dato', 'tipo_dato' => 'object'],
        ];

        $this->fakeToolCall([
            ['id' => '99', 'contenido' => 'inventado'],
            ['id' => '0', 'contenido' => '50000'],
        ]);

        $resultado = $this->servicio()->extraer('texto del w2', $revela);

        $this->assertCount(1, $resultado);
        $this->assertSame('50000', $resultado[0]['contenido']);
    }

    public function test_el_schema_de_la_tool_restringe_el_id_a_las_posiciones_declaradas(): void
    {
        $revela = [
            ['forma' => 'form_1040', 'campo' => 'a', 'subcampo' => null, 'descripcion' => 'A', 'acumulable' => false, 'tipo_campo' => 'dato', 'tipo_dato' => 'string'],
            ['forma' => 'form_1040', 'campo' => 'b', 'subcampo' => null, 'descripcion' => 'B', 'acumulable' => false, 'tipo_campo' => 'dato', 'tipo_dato' => 'string'],
        ];

        $this->fakeToolCall([]);

        $this->servicio()->extraer('texto', $revela);

        Http::assertSent(function ($request) {
            $schema = $request->data()['tools'][0]['function']['parameters']['properties']['revelados']['items']['properties'];

            return ($schema['id']['enum'] ?? null) === ['0', '1'];
        });
    }

    public function test_no_llama_a_la_ia_cuando_no_hay_nada_que_revelar(): void
    {
        Http::fake();

        $resultado = $this->servicio()->extraer('texto', []);

        $this->assertSame([], $resultado);
        Http::assertNothingSent();
    }
}
