<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\BaseConocimientoDocumento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AgenteBaseConocimientoControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    /**
     * Mismo generador de PDF mínimo válido usado en BaseConocimientoServiceTest
     * y PdfTextExtractorServiceTest.
     */
    private function crearArchivoPdf(string $texto): UploadedFile
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

        $ruta = tempnam(sys_get_temp_dir(), 'base_conocimiento_ctrl_').'.pdf';
        file_put_contents($ruta, $pdf);

        return new UploadedFile($ruta, 'guia.pdf', 'application/pdf', null, true);
    }

    public function test_un_administrador_ve_la_lista_de_documentos(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);

        $response = $this->actingAs($admin)->get(route('agente.base-conocimiento.index'))->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->component('agente/base-conocimiento')
            ->has('documentos', 0));
    }

    public function test_un_preparador_no_puede_ver_la_lista(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);

        $this->actingAs($preparador)->get(route('agente.base-conocimiento.index'))->assertForbidden();
    }

    public function test_un_administrador_puede_subir_un_pdf(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);

        $this->actingAs($admin)->post(route('agente.base-conocimiento.store'), [
            'file' => $this->crearArchivoPdf('Un ITIN es un numero de identificacion fiscal.'),
        ])->assertRedirect();

        $this->assertDatabaseHas('base_conocimiento_documentos', [
            'nombre_original' => 'guia.pdf',
            'estado' => 'procesado',
            'subido_por_user_id' => $admin->id,
        ]);
    }

    public function test_subir_un_archivo_que_no_es_pdf_falla_la_validacion(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);

        $this->actingAs($admin)->post(route('agente.base-conocimiento.store'), [
            'file' => UploadedFile::fake()->create('notas.txt', 10, 'text/plain'),
        ])->assertSessionHasErrors('file');
    }

    public function test_un_preparador_no_puede_subir_documentos(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);

        $this->actingAs($preparador)->post(route('agente.base-conocimiento.store'), [
            'file' => $this->crearArchivoPdf('contenido'),
        ])->assertForbidden();
    }

    public function test_un_administrador_puede_eliminar_un_documento(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $documento = BaseConocimientoDocumento::query()->create([
            'nombre_original' => 'guia.pdf',
            'ruta_pdf' => 'base_conocimiento/x.pdf',
            'contenido_markdown' => '# guia.pdf',
            'tamano' => 100,
            'estado' => 'procesado',
            'subido_por_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('agente.base-conocimiento.destroy', $documento))
            ->assertRedirect();

        $this->assertDatabaseMissing('base_conocimiento_documentos', ['id' => $documento->id]);
    }

    public function test_un_preparador_no_puede_eliminar(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $documento = BaseConocimientoDocumento::query()->create([
            'nombre_original' => 'guia.pdf',
            'ruta_pdf' => 'base_conocimiento/x.pdf',
            'tamano' => 100,
            'estado' => 'procesado',
        ]);

        $this->actingAs($preparador)
            ->delete(route('agente.base-conocimiento.destroy', $documento))
            ->assertForbidden();
    }
}
