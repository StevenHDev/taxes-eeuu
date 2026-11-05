<?php

namespace Tests\Feature\Expediente;

use App\Enums\AccionAuditoria;
use App\Enums\Entrevista\EstadoDocumento;
use App\Enums\UserRole;
use App\Models\BitacoraActividad;
use App\Models\User;
use App\Services\Expediente\HojaTaxwise;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Expediente\Concerns\ArmaExpediente;
use Tests\Feature\Expediente\Concerns\ArmaHojaTaxwise;
use Tests\TestCase;

/**
 * F5.5a: el CSV de la hoja para TaxWise.
 */
class HojaTaxwiseCsvTest extends TestCase
{
    use ArmaExpediente;
    use ArmaHojaTaxwise;
    use RefreshDatabase;

    private int $anio;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->seed(CatalogoFiscalSeeder::class);
        $this->anio = (int) config('tax.current_tax_year');
        App::setLocale('es');
    }

    /**
     * @return list<list<string>>
     */
    private function filasDelCsv(string $contenido): array
    {
        $this->assertStringStartsWith("\xEF\xBB\xBF", $contenido);
        $sinBom = substr($contenido, 3);
        $flujo = fopen('php://memory', 'r+');
        fwrite($flujo, $sinBom);
        rewind($flujo);

        $filas = [];
        while (($fila = fgetcsv($flujo, null, ',', '"', '')) !== false) {
            $filas[] = $fila;
        }

        return $filas;
    }

    public function test_el_csv_trae_bom_encabezados_y_las_mismas_filas_que_la_pantalla(): void
    {
        [$cliente, , $preparador] = $this->maria();

        $respuesta = $this->actingAs($preparador)->get(route('clientes.hoja-taxwise.csv', $cliente))->assertOk();

        $this->assertSame('text/csv; charset=UTF-8', $respuesta->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', $respuesta->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $respuesta->headers->get('Cache-Control'));
        $this->assertStringContainsString("hoja-taxwise-{$cliente->id}-{$this->anio}.csv", $respuesta->headers->get('Content-Disposition'));

        $filas = $this->filasDelCsv($respuesta->streamedContent());
        $this->assertSame(['Sección', 'Formulario y línea', 'Descripción', 'Valor', 'Origen', 'Estado'], $filas[0]);

        $hoja = app(HojaTaxwise::class)->para($cliente, $this->anio);
        $this->assertCount(1 + count($this->filas($hoja)) + count($hoja['totales']), $filas);
        $this->assertContainsEquals(['W-2', 'Form 1040 · línea 1a', 'Casilla 1 — Salarios, propinas y otra compensación', '45120.00', 'Form W-2 (Empresa X), casilla 1', $this->filas($hoja)[array_search('Form 1040 · línea 1a', array_column($this->filas($hoja), 'destino'), true)]['estado_texto']], $filas);
        $this->assertContainsEquals(['Totales de control', 'Form 1040 · línea 1a', 'Salarios (suma de la casilla 1 de todos los W-2)', '45120.00', 'Suma de 1 documento', 'Completo'], $filas);
    }

    public function test_el_csv_sale_siempre_enmascarado(): void
    {
        [$cliente, , $preparador] = $this->maria();

        $contenido = $this->actingAs($preparador)->get(route('clientes.hoja-taxwise.csv', $cliente))->streamedContent();

        $this->assertStringContainsString('***-**-6789', $contenido);
        $this->assertStringContainsString('**-***6789', $contenido);
        $this->assertStringContainsString('Oculto', $contenido);

        foreach ([$this->ssn, '123456789', $this->ein, 'Ana Hija', '2015-03-04'] as $secreto) {
            $this->assertStringNotContainsString($secreto, $contenido, "Salió en claro: {$secreto}");
        }
    }

    public function test_descargar_deja_exportado_en_la_bitacora_sin_valores(): void
    {
        [$cliente, , $preparador] = $this->maria();

        $this->actingAs($preparador)->get(route('clientes.hoja-taxwise.csv', $cliente))->assertOk()->streamedContent();

        $registro = BitacoraActividad::query()->where('accion', AccionAuditoria::Exportado)->sole();
        $this->assertSame($preparador->id, $registro->actor_id);
        $this->assertSame("Descargó la hoja para TaxWise {$this->anio} (cliente #{$cliente->id})", $registro->etiqueta);
        $this->assertNull($registro->campos_afectados);

        $guardado = json_encode($registro->getAttributes());
        foreach (['45120', $this->ssn, '6789', 'Ana Hija', 'Empresa X'] as $valor) {
            $this->assertStringNotContainsString($valor, $guardado);
        }
    }

    public function test_el_texto_que_parece_una_formula_no_se_ejecuta_en_excel(): void
    {
        [$cliente, $e, $preparador] = $this->maria();
        $item = $this->item($e, 'w2', EstadoDocumento::Leido);
        $this->leer($item, ['c' => ['=HYPERLINK("http://x")'], '1' => ['-100.00']]);

        $contenido = $this->actingAs($preparador)->get(route('clientes.hoja-taxwise.csv', $cliente))->streamedContent();

        $this->assertStringNotContainsString(',=HYPERLINK', $contenido);
        $this->assertStringContainsString('-100.00', $contenido);
        $this->assertStringNotContainsString("'-100.00", $contenido);
    }

    public function test_otro_preparador_y_el_cliente_reciben_403_y_no_queda_registro(): void
    {
        [$cliente] = $this->maria();
        $otro = User::factory()->create(['role' => UserRole::Preparer]);

        $this->actingAs($otro)->get(route('clientes.hoja-taxwise.csv', $cliente))->assertForbidden();
        $this->actingAs($cliente)->get(route('clientes.hoja-taxwise.csv', $cliente))->assertForbidden();

        $this->assertSame(0, BitacoraActividad::query()->where('accion', AccionAuditoria::Exportado)->count());
    }

    public function test_el_administrador_puede_descargarlo(): void
    {
        [$cliente] = $this->maria();
        $admin = User::factory()->create(['role' => UserRole::Administrator]);

        $this->actingAs($admin)->get(route('clientes.hoja-taxwise.csv', $cliente))->assertOk();
    }

    public function test_los_encabezados_salen_en_ingles_con_el_idioma_en(): void
    {
        [$cliente, , $preparador] = $this->maria();
        App::setLocale('en');

        $contenido = $this->actingAs($preparador)->get(route('clientes.hoja-taxwise.csv', $cliente))->streamedContent();

        $this->assertSame(['Section', 'Form and line', 'Description', 'Value', 'Source', 'Status'], $this->filasDelCsv($contenido)[0]);
    }
}
