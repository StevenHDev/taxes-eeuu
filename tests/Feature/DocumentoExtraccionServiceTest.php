<?php

namespace Tests\Feature;

use App\Enums\MetodoExtraccionDocumento;
use App\Services\DocumentoExtraccion\DocumentoExtraccionService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DocumentoExtraccionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.openai.vision_model' => 'test-vision-model']);
    }

    /** @return array{objetos: array<int, string>, offsets: array<int, int>} */
    private function crearPdf(string $streamContenido, bool $conFuente): string
    {
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

        $ruta = tempnam(sys_get_temp_dir(), 'pdf_orquestador_').'.pdf';
        file_put_contents($ruta, $pdf);

        return $ruta;
    }

    public function test_un_pdf_con_texto_util_se_resuelve_en_nivel_1_sin_llamar_a_openai(): void
    {
        Http::fake(); // cualquier llamada HTTP hace fallar el test

        $texto = 'Formulario W-2 de prueba con texto legible y longitud suficiente para la heuristica.';
        $ruta = $this->crearPdf("BT /F1 12 Tf 20 700 Td ({$texto}) Tj ET", conFuente: true);

        $resultado = app(DocumentoExtraccionService::class)->extraer($ruta, 'application/pdf');

        $this->assertSame(MetodoExtraccionDocumento::TextoPdf, $resultado['metodo']);
        $this->assertStringContainsString('W-2 de prueba', $resultado['texto']);
        Http::assertNothingSent();
    }

    public function test_un_pdf_sin_texto_util_cae_a_vision(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'transcripcion via vision']]],
            ], 200),
        ]);

        $ruta = $this->crearPdf('', conFuente: false);

        $resultado = app(DocumentoExtraccionService::class)->extraer($ruta, 'application/pdf');

        $this->assertSame(MetodoExtraccionDocumento::Vision, $resultado['metodo']);
        $this->assertSame('transcripcion via vision', $resultado['texto']);
    }

    public function test_una_imagen_va_directo_a_vision(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'transcripcion de la foto']]],
            ], 200),
        ]);

        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $ruta = tempnam(sys_get_temp_dir(), 'imagen_orquestador_').'.jpg';
        file_put_contents($ruta, $bytes);

        $resultado = app(DocumentoExtraccionService::class)->extraer($ruta, 'image/jpeg');

        $this->assertSame(MetodoExtraccionDocumento::Vision, $resultado['metodo']);
        $this->assertSame('transcripcion de la foto', $resultado['texto']);
    }
}
