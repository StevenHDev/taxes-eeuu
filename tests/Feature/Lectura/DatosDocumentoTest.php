<?php

namespace Tests\Feature\Lectura;

use App\Enums\AccionAuditoria;
use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\Entrevista\EstadoDocumento;
use App\Enums\Lectura\EstadoLectura;
use App\Enums\Lectura\Legibilidad;
use App\Enums\Lectura\MotivoConfianza;
use App\Enums\Lectura\OrigenDato;
use App\Enums\UserRole;
use App\Models\BitacoraActividad;
use App\Models\Documento;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaDocumento;
use App\Models\Lectura\DocumentoDato;
use App\Models\Lectura\DocumentoDatoCambio;
use App\Models\Lectura\DocumentoLectura;
use App\Models\User;
use App\Services\Entrevista\ChecklistDocumentos;
use App\Services\Entrevista\DocumentosEntrevista;
use App\Services\Entrevista\RespuestaData;
use App\Services\Entrevista\RespuestaGuardador;
use App\Services\Entrevista\TransicionNoPermitida;
use App\Services\Lectura\DatosDocumento;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * F4.2: lo que se lee de un documento. Los valores van cifrados, corregir
 * guarda el historial sin pisar lo leído y reabre lo que ya estaba
 * validado, y retirar un ítem no borra nada.
 */
class DatosDocumentoTest extends TestCase
{
    use RefreshDatabase;

    private const SSN = '123-45-6789';

    private User $preparador;

    private User $cliente;

    private Entrevista $entrevista;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(Documento::disco());
        $this->seed(CatalogoFiscalSeeder::class);
        $this->preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $this->cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $this->preparador->id]);

        $guardador = app(RespuestaGuardador::class);
        $this->entrevista = $guardador->entrevistaDe($this->cliente, CanalRespuesta::Portal);
        $guardador->guardar($this->entrevista, new RespuestaData('D1', 'si'), CanalRespuesta::Portal, $this->cliente);
    }

    private function item(string $codigo = 'w2'): EntrevistaDocumento
    {
        return $this->entrevista->documentos()->where('documento_codigo', $codigo)->firstOrFail();
    }

    private function subir(EntrevistaDocumento $item, string $nombre = 'w2.pdf'): Documento
    {
        return app(DocumentosEntrevista::class)->subir($item, UploadedFile::fake()->create($nombre, 10, 'application/pdf'));
    }

    private function lectura(Documento $archivo, EstadoLectura $estado = EstadoLectura::Leida): DocumentoLectura
    {
        return DocumentoLectura::query()->create([
            'documento_id' => $archivo->id,
            'entrevista_documento_id' => $archivo->entrevista_documento_id,
            'lector' => 'openai',
            'modelo' => 'modelo-de-prueba',
            'estado' => $estado,
            'paginas' => 1,
            'terminada_en' => now(),
        ]);
    }

    private function dato(DocumentoLectura $lectura, string $casilla, mixed $valor, ?EntrevistaDocumento $item = null): DocumentoDato
    {
        return $lectura->datos()->create([
            'entrevista_documento_id' => $item?->id ?? $lectura->entrevista_documento_id,
            'casilla' => $casilla,
            'valor' => $valor,
            'valor_leido' => $valor,
            'confianza' => 0.99,
            'motivo_confianza' => MotivoConfianza::EnTextoDelPdf,
            'legibilidad' => Legibilidad::Claro,
            'pagina' => 1,
            'recuadro' => ['x' => 0.1, 'y' => 0.2, 'ancho' => 0.3, 'alto' => 0.05],
            'evidencia' => "1 Wages, tips, other compensation {$valor}",
            'origen' => OrigenDato::Lector,
        ]);
    }

    public function test_el_valor_y_la_evidencia_quedan_cifrados_en_la_base(): void
    {
        $lectura = $this->lectura($this->subir($this->item()));
        $dato = $this->dato($lectura, 'a', self::SSN);

        $crudo = DB::table('documento_datos')->where('id', $dato->id)->first();

        foreach (['valor', 'valor_leido', 'evidencia'] as $columna) {
            $this->assertStringNotContainsString('6789', (string) $crudo->{$columna}, "{$columna} quedó en claro.");
        }

        $dato = $dato->fresh();
        $this->assertSame(self::SSN, $dato->valor);
        $this->assertSame(self::SSN, $dato->valor_leido);
        $this->assertSame('1 Wages, tips, other compensation '.self::SSN, $dato->evidencia);
        $this->assertSame(0.99, $dato->confianza);
        $this->assertSame(MotivoConfianza::EnTextoDelPdf, $dato->motivo_confianza);
        $this->assertSame(['x' => 0.1, 'y' => 0.2, 'ancho' => 0.3, 'alto' => 0.05], $dato->recuadro);
    }

    public function test_corregir_guarda_el_historial_y_no_pisa_lo_leido(): void
    {
        $dato = $this->dato($this->lectura($this->subir($this->item())), '1', '45120.00');

        app(DatosDocumento::class)->corregir($dato, '45210.00', 'Los dígitos estaban cambiados.', $this->preparador);

        $dato = $dato->fresh();
        $this->assertSame('45210.00', $dato->valor);
        $this->assertSame('45120.00', $dato->valor_leido, 'Lo leído no cambia nunca.');
        $this->assertSame(OrigenDato::Preparador, $dato->origen);
        $this->assertSame($this->preparador->id, $dato->corregido_por);
        $this->assertNotNull($dato->corregido_en);
        $this->assertSame('Los dígitos estaban cambiados.', $dato->motivo);

        $cambio = DocumentoDatoCambio::query()->where('dato_id', $dato->id)->sole();
        $this->assertSame('45120.00', $cambio->valor_anterior);
        $this->assertSame('45210.00', $cambio->valor_nuevo);
        $this->assertSame('Los dígitos estaban cambiados.', $cambio->motivo);
        $this->assertSame($this->preparador->id, $cambio->actor_id);

        $crudo = DB::table('documento_dato_cambios')->where('id', $cambio->id)->first();
        $this->assertStringNotContainsString('45210', (string) $crudo->valor_nuevo);
        $this->assertStringNotContainsString('45120', (string) $crudo->valor_anterior);
    }

    public function test_corregir_sin_motivo_no_escribe_nada(): void
    {
        $dato = $this->dato($this->lectura($this->subir($this->item())), '1', '45120.00');

        foreach (['', '   '] as $motivo) {
            try {
                app(DatosDocumento::class)->corregir($dato, '1.00', $motivo, $this->preparador);
                $this->fail('Debió exigir el motivo.');
            } catch (TransicionNoPermitida) {
                // esperado
            }
        }

        $this->assertSame('45120.00', $dato->fresh()->valor);
        $this->assertSame(OrigenDato::Lector, $dato->fresh()->origen);
        $this->assertSame(0, DocumentoDatoCambio::query()->count());
    }

    public function test_corregir_al_mismo_valor_no_hace_nada(): void
    {
        $dato = $this->dato($this->lectura($this->subir($this->item())), '1', '45120.00');

        app(DatosDocumento::class)->corregir($dato, ' 45120.00 ', 'Está bien.', $this->preparador);

        $this->assertSame(0, DocumentoDatoCambio::query()->count());
        $this->assertSame(OrigenDato::Lector, $dato->fresh()->origen);
    }

    public function test_corregir_un_documento_validado_reabre_la_validacion_y_uno_subido_no_cambia(): void
    {
        $item = $this->item();
        $dato = $this->dato($this->lectura($this->subir($item)), '1', '45120.00');
        app(ChecklistDocumentos::class)->validar($item->fresh(), $this->preparador);
        $this->assertSame(EstadoDocumento::Validado, $item->fresh()->estado);

        app(DatosDocumento::class)->corregir($dato, '45210.00', 'Corregido en el visor.', $this->preparador);

        $this->assertSame(EstadoDocumento::Leido, $item->fresh()->estado, 'Validado → vuelve a revisión.');

        $otro = $this->item('id-foto');
        $datoOtro = $this->dato($this->lectura($this->subir($otro, 'foto.pdf')), 'anio', '2025');
        $this->assertSame(EstadoDocumento::Subido, $otro->fresh()->estado);

        app(DatosDocumento::class)->corregir($datoOtro, '2024', 'Era 2024.', $this->preparador);

        $this->assertSame(EstadoDocumento::Subido, $otro->fresh()->estado, 'Un documento sin validar no cambia de estado.');
    }

    public function test_vigentes_trae_solo_la_ultima_lectura_terminada_de_cada_archivo(): void
    {
        $item = $this->item();
        $archivo = $this->subir($item);

        $vieja = $this->lectura($archivo);
        $this->dato($vieja, '1', '100.00');

        $nueva = $this->lectura($archivo);
        $this->dato($nueva, '1', '200.00');
        $this->dato($nueva, '2', '20.00');

        $fallida = $this->lectura($archivo, EstadoLectura::Fallida);
        $this->dato($fallida, '1', '999.00');

        $otroArchivo = $this->subir($item, 'w2-segundo-empleo.pdf');
        $this->dato($this->lectura($otroArchivo), '1', '300.00');

        $valores = app(DatosDocumento::class)->vigentes($item)->map(fn (DocumentoDato $d) => "{$d->casilla}={$d->valor}")->all();

        $this->assertEqualsCanonicalizing(['1=200.00', '2=20.00', '1=300.00'], $valores);
    }

    public function test_la_bitacora_registra_el_cambio_sin_el_valor(): void
    {
        $dato = $this->dato($this->lectura($this->subir($this->item())), 'a', self::SSN);

        app(DatosDocumento::class)->corregir($dato, '987-65-4321', 'Se leyó mal el SSN.', $this->preparador);

        $registro = BitacoraActividad::query()
            ->where('auditable_type', DocumentoDato::class)
            ->where('auditable_id', $dato->id)
            ->where('accion', AccionAuditoria::Actualizado->value)
            ->first();

        $this->assertNotNull($registro, 'El cambio no quedó en la bitácora.');
        $this->assertStringNotContainsString('4321', json_encode($registro->toArray()));
        $this->assertStringNotContainsString('6789', json_encode($registro->toArray()));
    }

    public function test_retirar_el_item_no_borra_lecturas_ni_datos(): void
    {
        $item = $this->item();
        $dato = $this->dato($this->lectura($this->subir($item)), '1', '45120.00');

        // D1 = no: el W-2 deja de aplicar y el ítem se retira, sin borrarlo.
        app(RespuestaGuardador::class)->guardar($this->entrevista->fresh(), new RespuestaData('D1', 'no'), CanalRespuesta::Portal, $this->cliente);
        app(ChecklistDocumentos::class)->sincronizar($this->entrevista->fresh());

        $this->assertNotNull($item->fresh()->retirado_en, 'El ítem debía quedar retirado.');
        $this->assertSame(1, DocumentoLectura::query()->count());
        $this->assertSame('45120.00', $dato->fresh()->valor);
    }
}
