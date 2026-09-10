<?php

namespace Tests\Feature;

use App\Services\DocumentoExtraccion\DocumentoVisionExtractorService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class DocumentoVisionExtractorServiceTest extends TestCase
{
    private function crearPngMinimo(): string
    {
        // PNG 1x1 transparente válido, embebido como constante — no hace
        // falta la extensión GD para producir un fixture de imagen real.
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $ruta = tempnam(sys_get_temp_dir(), 'imagen_test_').'.png';
        file_put_contents($ruta, $bytes);

        return $ruta;
    }

    /**
     * PDF de una sola página, sin texto (Nivel 1 ya lo habría descartado) —
     * solo necesitamos que `pdftoppm` pueda rasterizarlo, no que tenga texto.
     */
    private function crearPdfMinimo(): string
    {
        $objetos = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /Resources << >> /MediaBox [0 0 200 200] /Contents 4 0 R >>',
            4 => "<< /Length 0 >>\nstream\n\nendstream",
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

        $ruta = tempnam(sys_get_temp_dir(), 'pdf_vision_').'.pdf';
        file_put_contents($ruta, $pdf);

        return $ruta;
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.openai.vision_model' => 'test-vision-model']);
    }

    public function test_transcribe_una_imagen_directamente_sin_rasterizar(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'W-2: salarios 52000.00 [no legible]']]],
            ], 200),
        ]);

        $ruta = $this->crearPngMinimo();

        $texto = app(DocumentoVisionExtractorService::class)->transcribir($ruta, 'image/png');

        $this->assertSame('W-2: salarios 52000.00 [no legible]', $texto);
        Http::assertSentCount(1);

        unlink($ruta);
    }

    public function test_rasteriza_un_pdf_sin_texto_y_transcribe_su_pagina(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'contenido transcrito de la página']]],
            ], 200),
        ]);

        $ruta = $this->crearPdfMinimo();

        $texto = app(DocumentoVisionExtractorService::class)->transcribir($ruta, 'application/pdf');

        $this->assertSame('contenido transcrito de la página', $texto);
        Http::assertSentCount(1);

        unlink($ruta);
    }

    public function test_lanza_una_excepcion_clara_si_pdftoppm_no_esta_disponible(): void
    {
        $pathOriginal = getenv('PATH');
        putenv('PATH=');

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('pdftoppm');

            app(DocumentoVisionExtractorService::class)->transcribir($this->crearPdfMinimo(), 'application/pdf');
        } finally {
            putenv("PATH={$pathOriginal}");
        }
    }
}
