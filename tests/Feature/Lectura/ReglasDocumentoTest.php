<?php

namespace Tests\Feature\Lectura;

use App\Enums\CatalogoFiscal\SeveridadRegla;
use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\Entrevista\EstadoDocumento;
use App\Enums\Lectura\EstadoExcepcion;
use App\Enums\Lectura\EstadoLectura;
use App\Enums\Lectura\Legibilidad;
use App\Enums\Lectura\MotivoConfianza;
use App\Enums\Lectura\OrigenDato;
use App\Enums\UserRole;
use App\Models\CatalogoFiscal\CatalogoRegla;
use App\Models\Documento;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaDocumento;
use App\Models\Lectura\DocumentoDato;
use App\Models\Lectura\DocumentoLectura;
use App\Models\Lectura\Excepcion;
use App\Models\ParametroFiscal;
use App\Models\User;
use App\Services\Entrevista\ChecklistDocumentos;
use App\Services\Entrevista\DocumentosEntrevista;
use App\Services\Entrevista\RespuestaData;
use App\Services\Entrevista\RespuestaGuardador;
use App\Services\Entrevista\TransicionNoPermitida;
use App\Services\Lectura\DatosDocumento;
use App\Services\Lectura\Reglas\MotorReglas;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * F4.5a: el motor corre las reglas implementadas (R-03, R-04, R-06, R-07,
 * R-09), abre y cierra excepciones sin duplicarlas y no deja validar con una
 * bloqueante abierta. Ningún detalle lleva montos ni SSN.
 */
class ReglasDocumentoTest extends TestCase
{
    use RefreshDatabase;

    private const SSN = '123-45-6789';

    private User $preparador;

    private User $cliente;

    private Entrevista $entrevista;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
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
        return $this->entrevista->documentos()->where('documento_codigo', $codigo)->first()
            ?? $this->entrevista->documentos()->create(['documento_codigo' => $codigo, 'numero' => 1, 'estado' => EstadoDocumento::Pendiente]);
    }

    /**
     * Sube un archivo al ítem y deja una lectura terminada.
     */
    private function lectura(EntrevistaDocumento $item, ?string $tipo = null, ?int $anio = 2025, string $contenido = 'a'): DocumentoLectura
    {
        $archivo = app(DocumentosEntrevista::class)->subir($item, UploadedFile::fake()->createWithContent('doc.pdf', $contenido));

        return DocumentoLectura::query()->create([
            'documento_id' => $archivo->id,
            'entrevista_documento_id' => $item->id,
            'lector' => 'openai',
            'estado' => EstadoLectura::Leida,
            'tipo_detectado' => $tipo ?? $item->documento_codigo,
            'anio_detectado' => $anio,
            'paginas' => 1,
            'terminada_en' => now(),
        ]);
    }

    private function dato(DocumentoLectura $lectura, string $casilla, ?string $valor, float $confianza = 0.99, MotivoConfianza $motivo = MotivoConfianza::EnTextoDelPdf, Legibilidad $legibilidad = Legibilidad::Claro): DocumentoDato
    {
        return $lectura->datos()->create([
            'entrevista_documento_id' => $lectura->entrevista_documento_id,
            'casilla' => $casilla,
            'valor' => $valor,
            'valor_leido' => $valor,
            'confianza' => $confianza,
            'motivo_confianza' => $motivo,
            'legibilidad' => $legibilidad,
            'pagina' => 1,
            'origen' => OrigenDato::Lector,
        ]);
    }

    private function correr(): void
    {
        app(MotorReglas::class)->correr($this->entrevista);
    }

    /**
     * @return Collection<int, Excepcion>
     */
    private function excepciones(string $regla): Collection
    {
        return Excepcion::query()->where('entrevista_id', $this->entrevista->id)->where('regla_codigo', $regla)->get();
    }

    public function test_r04_la_casilla_2_mayor_que_la_1_abre_una_bloqueante(): void
    {
        $lectura = $this->lectura($this->item());
        $this->dato($lectura, '1', '1000.00');
        $this->dato($lectura, '2', '2000.00');

        $this->correr();

        $excepcion = $this->excepciones('R-04')->sole();
        $this->assertSame(SeveridadRegla::Bloqueante, $excepcion->severidad);
        $this->assertSame(EstadoExcepcion::Abierta, $excepcion->estado);
        $this->assertSame('2', $excepcion->dato->casilla);
        $this->assertSame($lectura->entrevista_documento_id, $excepcion->entrevista_documento_id);
    }

    public function test_r04_la_casilla_3_mas_la_7_sobre_el_tope_del_ano(): void
    {
        $lectura = $this->lectura($this->item());
        $this->dato($lectura, '3', '170000.00');
        $this->dato($lectura, '7', '7000.00');

        $this->correr();

        $this->assertSame('3', $this->excepciones('R-04')->sole()->dato->casilla);
    }

    public function test_r04_un_w2_en_regla_no_abre_nada(): void
    {
        $lectura = $this->lectura($this->item());
        $this->dato($lectura, '1', '45120.00');
        $this->dato($lectura, '2', '4512.00');
        $this->dato($lectura, '3', '45120.00');
        $this->dato($lectura, '7', null, 0.0, MotivoConfianza::SinValor);

        $this->correr();

        $this->assertCount(0, $this->excepciones('R-04'));
    }

    public function test_r03_un_dato_clave_con_confianza_baja_va_a_revision_con_su_motivo(): void
    {
        $lectura = $this->lectura($this->item());
        $this->dato($lectura, '1', '45120.00', 0.60, MotivoConfianza::Dudoso, Legibilidad::Dudoso);
        $this->dato($lectura, '2', '4512.00', 0.90, MotivoConfianza::Claro);
        $this->dato($lectura, 'c', 'ACME', 0.60, MotivoConfianza::Dudoso, Legibilidad::Dudoso);

        $this->correr();

        $excepcion = $this->excepciones('R-03')->sole();
        $this->assertSame(SeveridadRegla::Revision, $excepcion->severidad);
        $this->assertSame('1', $excepcion->dato->casilla);
        $this->assertSame('dudoso', $excepcion->detalle['motivo_confianza']);
        $this->assertSame(0.9, $excepcion->detalle['umbral']);
    }

    public function test_r03_una_casilla_vacia_que_se_vio_con_claridad_no_es_una_duda_pero_una_que_no_se_vio_si(): void
    {
        $lectura = $this->lectura($this->item());
        $this->dato($lectura, '7', null, 0.0, MotivoConfianza::SinValor, Legibilidad::Claro);
        $this->dato($lectura, '10', null, 0.0, MotivoConfianza::NoSeVe, Legibilidad::NoSeVe);

        $this->correr();

        $this->assertSame('10', $this->excepciones('R-03')->sole()->dato->casilla);
    }

    public function test_r03_el_umbral_es_el_parametro_del_ano(): void
    {
        $lectura = $this->lectura($this->item());
        $this->dato($lectura, '1', '45120.00', 0.90, MotivoConfianza::Claro);

        $this->correr();
        $this->assertCount(0, $this->excepciones('R-03'));

        ParametroFiscal::query()->where(['tax_year' => 2025, 'categoria' => 'lectura', 'clave' => 'umbral_confianza'])->firstOrFail()->update(['valor' => 0.95]);
        $this->correr();

        $this->assertCount(1, $this->excepciones('R-03'));
    }

    public function test_r03_lo_que_corrige_el_preparador_no_cuenta(): void
    {
        $dato = $this->dato($this->lectura($this->item()), '1', '45120.00', 0.60, MotivoConfianza::Dudoso, Legibilidad::Dudoso);
        $this->correr();
        $this->assertSame(EstadoExcepcion::Abierta, $this->excepciones('R-03')->sole()->estado);

        app(DatosDocumento::class)->corregir($dato, '45210.00', 'Se veía otro número.', $this->preparador);

        $this->assertSame(EstadoExcepcion::Corregida, $this->excepciones('R-03')->sole()->estado, 'Corregir corre el motor.');
    }

    public function test_r06_el_anio_o_el_tipo_distinto_se_marcan_sin_descartar_lo_leido(): void
    {
        $lectura = $this->lectura($this->item(), tipo: '1099-nec', anio: 2024);
        $this->dato($lectura, '1', '45120.00');

        $this->correr();

        $claves = $this->excepciones('R-06')->pluck('detalle.campo')->sort()->values()->all();
        $this->assertSame(['anio', 'tipo'], $claves);
        $this->assertSame(SeveridadRegla::Bloqueante, $this->excepciones('R-06')->first()->severidad);
        $this->assertSame(1, $lectura->datos()->count(), 'Lo leído se conserva.');
    }

    public function test_r06_un_documento_del_ano_y_tipo_correctos_no_abre_nada(): void
    {
        $this->lectura($this->item());

        $this->correr();

        $this->assertCount(0, $this->excepciones('R-06'));
    }

    public function test_r07_un_archivo_repetido_se_marca_sin_decir_de_quien(): void
    {
        $otro = User::factory()->create(['role' => UserRole::Client]);
        $guardador = app(RespuestaGuardador::class);
        $otraEntrevista = $guardador->entrevistaDe($otro, CanalRespuesta::Portal);
        $guardador->guardar($otraEntrevista, new RespuestaData('D1', 'si'), CanalRespuesta::Portal, $otro);
        app(DocumentosEntrevista::class)->subir($otraEntrevista->documentos()->where('documento_codigo', 'w2')->firstOrFail(), UploadedFile::fake()->createWithContent('otro.pdf', 'mismos-bytes'));

        $this->lectura($this->item(), contenido: 'mismos-bytes');

        $this->correr();

        $excepcion = $this->excepciones('R-07')->sole();
        $this->assertSame(SeveridadRegla::Revision, $excepcion->severidad);
        $this->assertSame(1, $excepcion->detalle['coincidencias']);
        $this->assertTrue($excepcion->detalle['de_otro_cliente']);
        $this->assertStringNotContainsString($otro->email, json_encode($excepcion->detalle));
    }

    public function test_r09_un_1095a_con_adelanto_dice_que_el_8962_es_obligatorio(): void
    {
        $item = $this->item('1095-a');
        $lectura = $this->lectura($item);
        $this->dato($lectura, '21-C', '0.00');
        $this->dato($lectura, '22-C', '250.00');

        $this->correr();

        $excepcion = $this->excepciones('R-09')->sole();
        $this->assertSame(SeveridadRegla::Informativa, $excepcion->severidad);
        $this->assertSame('22-C', $excepcion->dato->casilla);
    }

    public function test_r09_un_1095a_sin_adelanto_no_abre_nada(): void
    {
        $lectura = $this->lectura($this->item('1095-a'));
        $this->dato($lectura, '21-C', '0.00');
        $this->dato($lectura, '33-C', '0.00');

        $this->correr();

        $this->assertCount(0, $this->excepciones('R-09'));
    }

    public function test_correr_dos_veces_no_duplica_y_corregir_el_dato_la_pasa_a_corregida_y_si_vuelve_la_reabre(): void
    {
        $lectura = $this->lectura($this->item());
        $uno = $this->dato($lectura, '1', '1000.00');
        $dos = $this->dato($lectura, '2', '2000.00');

        $this->correr();
        $this->correr();
        $this->assertCount(1, $this->excepciones('R-04'));

        app(DatosDocumento::class)->corregir($dos, '500.00', 'Estaba mal leído.', $this->preparador);
        $this->assertSame(EstadoExcepcion::Corregida, $this->excepciones('R-04')->sole()->estado);
        $this->assertNotNull($this->excepciones('R-04')->sole()->resuelta_en);

        app(DatosDocumento::class)->corregir($dos->fresh(), '5000.00', 'Era ese.', $this->preparador);
        $this->assertSame(EstadoExcepcion::Abierta, $this->excepciones('R-04')->sole()->estado);
        $this->assertNull($this->excepciones('R-04')->sole()->resuelta_en);
        $this->assertCount(1, $this->excepciones('R-04'));
        $this->assertSame('1000.00', $uno->fresh()->valor);
    }

    public function test_una_excepcion_aceptada_no_se_toca(): void
    {
        $lectura = $this->lectura($this->item());
        $this->dato($lectura, '1', '1000.00');
        $this->dato($lectura, '2', '2000.00');
        $this->correr();

        $excepcion = $this->excepciones('R-04')->sole();
        $excepcion->update(['estado' => EstadoExcepcion::Aceptada, 'nota' => 'Es correcto.']);

        $this->correr();

        $this->assertSame(EstadoExcepcion::Aceptada, $excepcion->fresh()->estado);
        $this->assertSame('Es correcto.', $excepcion->fresh()->nota);
    }

    public function test_no_se_puede_validar_con_una_bloqueante_abierta_y_si_con_una_aceptada_o_de_revision(): void
    {
        $item = $this->item();
        $lectura = $this->lectura($item);
        $this->dato($lectura, '1', '1000.00');
        $this->dato($lectura, '2', '2000.00');
        $this->dato($lectura, '3', '1000.00', 0.60, MotivoConfianza::Dudoso, Legibilidad::Dudoso);
        $this->correr();

        $checklist = app(ChecklistDocumentos::class);
        $item->update(['estado' => EstadoDocumento::Leido]);

        try {
            $checklist->validar($item->fresh(), $this->preparador);
            $this->fail('Debió impedir validar con una bloqueante abierta.');
        } catch (TransicionNoPermitida $e) {
            $this->assertSame(EstadoDocumento::Leido, $item->fresh()->estado);
        }

        $this->excepciones('R-04')->sole()->update(['estado' => EstadoExcepcion::Aceptada, 'nota' => 'Es correcto.']);

        $checklist->validar($item->fresh(), $this->preparador);

        $this->assertSame(EstadoDocumento::Validado, $item->fresh()->estado, 'La de R-03 es de revisión: no bloquea.');
    }

    public function test_el_detalle_de_ninguna_excepcion_lleva_montos_ni_ssn(): void
    {
        $lectura = $this->lectura($this->item(), tipo: '1099-nec', anio: 2024);
        $this->dato($lectura, 'a', self::SSN, 0.60, MotivoConfianza::Dudoso, Legibilidad::Dudoso);
        $this->dato($lectura, '1', '1000.00', 0.60, MotivoConfianza::Dudoso, Legibilidad::Dudoso);
        $this->dato($lectura, '2', '2000.00');

        $this->correr();

        $this->assertGreaterThanOrEqual(4, Excepcion::query()->count());

        foreach (Excepcion::all() as $excepcion) {
            $json = json_encode($excepcion->detalle);

            foreach (['1000', '2000', '6789', '123-45'] as $secreto) {
                $this->assertStringNotContainsString($secreto, $json, "{$excepcion->regla_codigo} dejó un valor en el detalle.");
            }
        }
    }

    public function test_una_regla_que_no_esta_implementada_en_el_catalogo_no_corre(): void
    {
        CatalogoRegla::query()->delAnio(2025)->where('codigo', 'R-04')->update(['implementada' => false]);
        $lectura = $this->lectura($this->item());
        $this->dato($lectura, '1', '1000.00');
        $this->dato($lectura, '2', '2000.00');

        $this->correr();

        $this->assertCount(0, $this->excepciones('R-04'));
    }

    public function test_las_reglas_implementadas_quedan_implementadas_en_el_catalogo(): void
    {
        $implementadas = CatalogoRegla::query()->delAnio(2025)->where('implementada', true)->orderBy('codigo')->pluck('codigo')->all();

        $this->assertSame(['R-01', 'R-03', 'R-04', 'R-05', 'R-06', 'R-07', 'R-08', 'R-09', 'R-11'], $implementadas);
    }
}
