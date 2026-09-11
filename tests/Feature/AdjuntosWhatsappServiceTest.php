<?php

namespace Tests\Feature;

use App\DataTransferObjects\MensajeEntranteWhatsapp;
use App\Enums\MetodoExtraccionDocumento;
use App\Services\Whatsapp\WhatsappChannel;
use App\Services\WhatsappAgent\AdjuntosWhatsappService;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Throwable;

class AdjuntosWhatsappServiceTest extends TestCase
{
    /**
     * Mismo generador de PDF mínimo válido usado en PdfTextExtractorServiceTest
     * — smalot/pdfparser exige una tabla xref real.
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

        $ruta = tempnam(sys_get_temp_dir(), 'adjunto_whatsapp_').'.pdf';
        file_put_contents($ruta, $pdf);

        return $ruta;
    }

    /**
     * @param  array<string, array{ruta_local: string, mime_type: string}|Throwable>  $porReferencia
     */
    private function canalFake(array $porReferencia): WhatsappChannel
    {
        return new class($porReferencia) implements WhatsappChannel
        {
            public function __construct(private array $porReferencia) {}

            public function manejarHandshake(Request $request): ?Response
            {
                return null;
            }

            public function validarFirma(Request $request): bool
            {
                return true;
            }

            public function normalizarEntrante(Request $request): ?MensajeEntranteWhatsapp
            {
                return null;
            }

            public function enviarTexto(string $telefono, string $mensaje): string
            {
                return 'x';
            }

            public function descargarMedia(string $referencia): array
            {
                $resultado = $this->porReferencia[$referencia] ?? throw new RuntimeException("sin fake para {$referencia}");

                if ($resultado instanceof Throwable) {
                    throw $resultado;
                }

                return $resultado;
            }
        };
    }

    public function test_resuelve_texto_y_metodo_de_un_pdf_con_texto_util(): void
    {
        $ruta = $this->crearPdfConTexto('Un W-2 de prueba con texto legible.');
        $this->app->instance(WhatsappChannel::class, $this->canalFake([
            'media-1' => ['ruta_local' => $ruta, 'mime_type' => 'application/pdf'],
        ]));

        $adjuntos = app(AdjuntosWhatsappService::class)->resolver(['media-1']);

        $this->assertCount(1, $adjuntos);
        $this->assertSame('media-1', $adjuntos[0]->referencia);
        $this->assertSame(MetodoExtraccionDocumento::TextoPdf, $adjuntos[0]->metodo);
        $this->assertStringContainsString('W-2 de prueba', $adjuntos[0]->texto);

        unlink($ruta);
    }

    public function test_una_referencia_que_falla_al_descargar_se_omite_sin_tronar(): void
    {
        $ruta = $this->crearPdfConTexto('Documento que sí se descarga bien.');
        $this->app->instance(WhatsappChannel::class, $this->canalFake([
            'media-ok' => ['ruta_local' => $ruta, 'mime_type' => 'application/pdf'],
            'media-caida' => new RuntimeException('proveedor caído'),
        ]));

        $adjuntos = app(AdjuntosWhatsappService::class)->resolver(['media-ok', 'media-caida']);

        $this->assertCount(1, $adjuntos);
        $this->assertSame('media-ok', $adjuntos[0]->referencia);

        unlink($ruta);
    }

    public function test_lista_vacia_de_referencias_devuelve_lista_vacia(): void
    {
        $this->app->instance(WhatsappChannel::class, $this->canalFake([]));

        $this->assertSame([], app(AdjuntosWhatsappService::class)->resolver([]));
    }
}
