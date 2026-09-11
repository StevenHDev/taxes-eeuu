<?php

namespace Tests\Feature;

use App\Enums\EstadoBaseConocimiento;
use App\Models\User;
use App\Services\BaseConocimientoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BaseConocimientoServiceTest extends TestCase
{
    use RefreshDatabase;

    private BaseConocimientoService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->service = app(BaseConocimientoService::class);
    }

    /**
     * Mismo generador de PDF mínimo válido que PdfTextExtractorServiceTest —
     * smalot/pdfparser exige una tabla xref real, un archivo con basura
     * binaria simplemente no parsea.
     */
    private function crearArchivoPdf(string $texto, bool $conFuente = true): UploadedFile
    {
        $streamContenido = $conFuente ? "BT /F1 12 Tf 20 700 Td ({$texto}) Tj ET" : '';

        $objetos = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /Resources '
                .($conFuente ? '<< /Font << /F1 4 0 R >> >>' : '<< >>')
                .' /MediaBox [0 0 612 792] /Contents 5 0 R >>',
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

        $ruta = tempnam(sys_get_temp_dir(), 'base_conocimiento_').'.pdf';
        file_put_contents($ruta, $pdf);

        return new UploadedFile($ruta, 'guia.pdf', 'application/pdf', null, true);
    }

    public function test_subir_un_pdf_con_texto_util_lo_deja_procesado_con_su_markdown(): void
    {
        $actor = User::factory()->create();
        $archivo = $this->crearArchivoPdf('Un ITIN es un numero de identificacion para quien no califica para SSN.');

        $documento = $this->service->subir($archivo, $actor);

        $this->assertSame(EstadoBaseConocimiento::Procesado, $documento->estado);
        $this->assertNull($documento->error_mensaje);
        $this->assertStringContainsString('# guia.pdf', (string) $documento->contenido_markdown);
        $this->assertStringContainsString('ITIN', (string) $documento->contenido_markdown);
        Storage::disk('local')->assertExists($documento->ruta_pdf);
    }

    public function test_subir_un_pdf_sin_texto_util_lo_deja_en_error(): void
    {
        $actor = User::factory()->create();
        $archivo = $this->crearArchivoPdf('', conFuente: false);

        $documento = $this->service->subir($archivo, $actor);

        $this->assertSame(EstadoBaseConocimiento::Error, $documento->estado);
        $this->assertNotNull($documento->error_mensaje);
        $this->assertNull($documento->contenido_markdown);
    }

    public function test_eliminar_borra_el_archivo_y_la_fila(): void
    {
        $actor = User::factory()->create();
        $documento = $this->service->subir($this->crearArchivoPdf('contenido de prueba'), $actor);
        $ruta = $documento->ruta_pdf;

        $this->service->eliminar($documento);

        Storage::disk('local')->assertMissing($ruta);
        $this->assertDatabaseMissing('base_conocimiento_documentos', ['id' => $documento->id]);
    }

    public function test_buscar_devuelve_el_parrafo_que_contiene_los_terminos(): void
    {
        $actor = User::factory()->create();
        $this->service->subir(
            $this->crearArchivoPdf('Un ITIN es un numero de identificacion fiscal para extranjeros.'),
            $actor,
        );
        $this->service->subir(
            $this->crearArchivoPdf('El W-2 lo entrega el empleador antes de fin de enero.'),
            $actor,
        );

        $resultados = $this->service->buscar('ITIN');

        $this->assertCount(1, $resultados);
        $this->assertSame('guia.pdf', $resultados[0]['documento']);
        $this->assertStringContainsString('ITIN', $resultados[0]['fragmento']);
    }

    public function test_buscar_sin_coincidencias_devuelve_vacio(): void
    {
        $actor = User::factory()->create();
        $this->service->subir($this->crearArchivoPdf('El W-2 lo entrega el empleador.'), $actor);

        $this->assertSame([], $this->service->buscar('palabra_que_no_existe_en_ningun_lado'));
    }

    public function test_buscar_ignora_documentos_en_estado_error(): void
    {
        $actor = User::factory()->create();
        $this->service->subir($this->crearArchivoPdf('', conFuente: false), $actor);

        $this->assertSame([], $this->service->buscar('ITIN'));
    }
}
