<?php

namespace Tests\Feature;

use App\Services\DocumentoExtraccion\PdfTextExtractorService;
use Tests\TestCase;

class PdfTextExtractorServiceTest extends TestCase
{
    private PdfTextExtractorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PdfTextExtractorService;
    }

    /**
     * PDF minimal pero estructuralmente válido (con tabla xref y offsets
     * reales — smalot/pdfparser exige "startxref" para parsear) con una capa
     * de texto real, o sin ningún operador de texto en el content stream
     * (simula un PDF que en realidad es una imagen/escaneo envuelta en un
     * contenedor PDF: el parser no truena, pero no hay texto que leer).
     */
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

        $ruta = tempnam(sys_get_temp_dir(), 'pdf_test_').'.pdf';
        file_put_contents($ruta, $pdf);

        return $ruta;
    }

    private function crearPdfConTexto(): string
    {
        $texto = 'Este es un W-2 de prueba con texto legible y suficiente longitud para pasar la heuristica.';

        return $this->crearPdf("BT /F1 12 Tf 20 700 Td ({$texto}) Tj ET", conFuente: true);
    }

    private function crearPdfSinTexto(): string
    {
        return $this->crearPdf('', conFuente: false);
    }

    public function test_extrae_el_texto_de_un_pdf_con_capa_de_texto_real(): void
    {
        $ruta = $this->crearPdfConTexto();

        $texto = $this->service->extraer($ruta);

        $this->assertNotNull($texto);
        $this->assertStringContainsString('W-2 de prueba', $texto);

        unlink($ruta);
    }

    public function test_devuelve_null_para_un_pdf_sin_texto_util(): void
    {
        $ruta = $this->crearPdfSinTexto();

        $texto = $this->service->extraer($ruta);

        $this->assertNull($texto);

        unlink($ruta);
    }

    public function test_devuelve_null_para_un_archivo_que_no_es_un_pdf_valido(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'no_pdf_');
        file_put_contents($ruta, 'esto no es un pdf, es basura binaria aleatoria');

        $texto = $this->service->extraer($ruta);

        $this->assertNull($texto);

        unlink($ruta);
    }
}
