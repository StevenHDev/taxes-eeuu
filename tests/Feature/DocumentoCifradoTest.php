<?php

namespace Tests\Feature;

use App\Enums\FieldState;
use App\Enums\UserRole;
use App\Models\CampoCliente;
use App\Models\Documento;
use App\Models\User;
use App\Services\ClienteExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * F0.2: los archivos de los documentos se guardan cifrados en disco y la
 * plataforma los entrega descifrados.
 */
class DocumentoCifradoTest extends TestCase
{
    use RefreshDatabase;

    private const CONTENIDO = '%PDF-1.4 contenido original del W-2';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(Documento::disco());
    }

    private function subirW2(User $preparador, User $cliente, string $nombre = 'w2.pdf'): Documento
    {
        $this->actingAs($preparador)
            ->post(route('clientes.campos.update', ['cliente' => $cliente, 'campo' => 'w2']).'?forma=form_1040&tax_year=2025', [
                '_method' => 'patch',
                'forma' => 'form_1040',
                'modo' => 'archivo',
                'file' => UploadedFile::fake()->createWithContent($nombre, self::CONTENIDO),
            ])
            ->assertRedirect();

        return Documento::query()->where('user_id', $cliente->id)->where('campo', 'w2')->latest('id')->firstOrFail();
    }

    /**
     * Documento guardado como antes de F0.2: archivo en claro y `cifrado = false`.
     */
    private function documentoSinCifrar(User $cliente, string $ruta, ?string $contenido = self::CONTENIDO): Documento
    {
        if ($contenido !== null) {
            Storage::disk(Documento::disco())->put($ruta, $contenido);
        }

        return Documento::query()->create([
            'user_id' => $cliente->id,
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'w2',
            'file_path' => $ruta,
            'file_original_name' => 'w2-viejo.pdf',
            'file_mime_type' => 'application/pdf',
            'file_size' => strlen((string) $contenido),
            'formato' => 'pdf',
            'estado_validacion' => FieldState::Recibido,
        ]);
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function preparadorYCliente(): array
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);

        return [$preparador, $cliente];
    }

    public function test_un_documento_subido_queda_cifrado_en_disco(): void
    {
        [$preparador, $cliente] = $this->preparadorYCliente();

        $documento = $this->subirW2($preparador, $cliente);

        $enDisco = Storage::disk(Documento::disco())->get($documento->file_path);
        $this->assertTrue($documento->cifrado);
        $this->assertNotSame(self::CONTENIDO, $enDisco);
        $this->assertStringNotContainsString('W-2', $enDisco);
        $this->assertSame(self::CONTENIDO, Crypt::decryptString($enDisco));
        // El hash de duplicados es del archivo original, no del cifrado.
        $this->assertSame(hash('sha256', self::CONTENIDO), $documento->hash_contenido);
    }

    public function test_la_plataforma_entrega_el_documento_descifrado(): void
    {
        [$preparador, $cliente] = $this->preparadorYCliente();
        $documento = $this->subirW2($preparador, $cliente, 'W-2 Año 2025.pdf');

        $respuesta = $this->get($documento->downloadUrl())->assertOk();

        $this->assertSame(self::CONTENIDO, $respuesta->getContent());
        $respuesta->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('attachment;', (string) $respuesta->headers->get('Content-Disposition'));
        $this->assertStringContainsString("filename*=utf-8''W-2%20A%C3%B1o%202025.pdf", (string) $respuesta->headers->get('Content-Disposition'));

        $vista = $this->get($documento->previewUrl())
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame(self::CONTENIDO, $vista->getContent());
        $this->assertStringStartsWith('inline;', (string) $vista->headers->get('Content-Disposition'));
    }

    public function test_un_documento_viejo_sin_cifrar_se_sigue_viendo(): void
    {
        [$preparador, $cliente] = $this->preparadorYCliente();
        $documento = $this->documentoSinCifrar($cliente, 'documentos/viejo.pdf');

        $respuesta = $this->actingAs($preparador)->get($documento->downloadUrl())->assertOk();

        $this->assertSame(self::CONTENIDO, $respuesta->getContent());
    }

    public function test_un_documento_sin_archivo_devuelve_404(): void
    {
        [$preparador, $cliente] = $this->preparadorYCliente();
        $documento = $this->documentoSinCifrar($cliente, 'documentos/no-existe.pdf', null);

        $this->actingAs($preparador)->get($documento->downloadUrl())->assertNotFound();
    }

    public function test_el_zip_de_exportacion_trae_el_contenido_original(): void
    {
        [$preparador, $cliente] = $this->preparadorYCliente();
        $documento = $this->subirW2($preparador, $cliente);
        $this->assertTrue(CampoCliente::query()->where('documento_id', $documento->id)->exists());

        $zipPath = app(ClienteExportService::class)->exportarZip($cliente, 2025);

        $zip = new ZipArchive;
        $zip->open($zipPath);
        $this->assertSame(self::CONTENIDO, $zip->getFromName("documentos/{$documento->forma}_w2_w2.pdf"));
        $zip->close();
        @unlink($zipPath);
    }

    public function test_el_comando_cifra_los_pendientes_y_no_toca_los_ya_cifrados(): void
    {
        [$preparador, $cliente] = $this->preparadorYCliente();
        $nuevo = $this->subirW2($preparador, $cliente);
        $cifradoAntes = Storage::disk(Documento::disco())->get($nuevo->file_path);

        $viejo = $this->documentoSinCifrar($cliente, 'documentos/viejo.pdf');
        $archivado = $this->documentoSinCifrar($cliente, 'documentos/archivado.pdf');
        $archivado->delete();
        $sinArchivo = $this->documentoSinCifrar($cliente, 'documentos/perdido.pdf', null);

        $this->artisan('documentos:cifrar-existentes')
            ->expectsOutputToContain('3 documento(s) sin cifrar.')
            ->expectsOutputToContain('2 documento(s) cifrado(s).')
            ->expectsOutputToContain("ids: {$sinArchivo->id}")
            ->assertSuccessful();

        foreach ([$viejo, $archivado] as $documento) {
            $documento = Documento::withTrashed()->findOrFail($documento->id);
            $enDisco = Storage::disk(Documento::disco())->get($documento->file_path);
            $this->assertTrue($documento->cifrado);
            $this->assertSame(self::CONTENIDO, Crypt::decryptString($enDisco));
        }

        $this->assertFalse($sinArchivo->fresh()->cifrado);
        $this->assertSame($cifradoAntes, Storage::disk(Documento::disco())->get($nuevo->file_path));
        $this->assertFalse(Storage::disk(Documento::disco())->exists('documentos/viejo.pdf.cifrando'));

        // Correrlo otra vez no vuelve a cifrar nada.
        $this->artisan('documentos:cifrar-existentes')
            ->expectsOutputToContain('0 documento(s) cifrado(s).')
            ->assertSuccessful();
        $this->assertSame(self::CONTENIDO, $this->get($viejo->downloadUrl())->getContent());
    }

    public function test_el_comando_no_cifra_dos_veces_un_archivo_que_ya_estaba_cifrado(): void
    {
        [$preparador, $cliente] = $this->preparadorYCliente();
        // Corte entre escribir el archivo cifrado y marcar la fila.
        $documento = $this->documentoSinCifrar($cliente, 'documentos/a-medias.pdf', Crypt::encryptString(self::CONTENIDO));

        $this->artisan('documentos:cifrar-existentes')->assertSuccessful();

        $this->assertTrue($documento->fresh()->cifrado);
        $this->assertSame(self::CONTENIDO, $this->actingAs($preparador)->get($documento->downloadUrl())->getContent());
    }

    public function test_el_modo_dry_run_no_escribe(): void
    {
        [, $cliente] = $this->preparadorYCliente();
        $documento = $this->documentoSinCifrar($cliente, 'documentos/viejo.pdf');

        $this->artisan('documentos:cifrar-existentes', ['--dry-run' => true])
            ->expectsOutputToContain('1 documento(s) sin cifrar.')
            ->assertSuccessful();

        $this->assertFalse($documento->fresh()->cifrado);
        $this->assertSame(self::CONTENIDO, Storage::disk(Documento::disco())->get('documentos/viejo.pdf'));
    }
}
