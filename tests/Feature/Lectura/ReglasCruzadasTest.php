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
use App\Models\Lectura\DocumentoLectura;
use App\Models\Lectura\Excepcion;
use App\Models\User;
use App\Services\Entrevista\DocumentosEntrevista;
use App\Services\Entrevista\RespuestaData;
use App\Services\Entrevista\RespuestaGuardador;
use App\Services\Lectura\Reglas\MotorReglas;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * F4.5b: R-01 (SSN, solo los últimos 4), R-05 (exceso del Seguro Social por
 * persona), R-08 (respuestas contra documentos) y R-11 (hijo sin SSN, solo
 * aviso). El motor corre también al guardar una respuesta. Ninguna excepción
 * guarda un SSN ni un monto.
 */
class ReglasCruzadasTest extends TestCase
{
    use RefreshDatabase;

    private Entrevista $entrevista;

    private RespuestaGuardador $guardador;

    private User $cliente;

    private int $archivos = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Storage::fake(Documento::disco());
        $this->seed(CatalogoFiscalSeeder::class);

        $this->cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => User::factory()->create(['role' => UserRole::Preparer])->id]);
        $this->guardador = app(RespuestaGuardador::class);
        $this->entrevista = $this->guardador->entrevistaDe($this->cliente, CanalRespuesta::Portal);
        $this->responder('D1', 'si');
    }

    private function responder(string $codigo, mixed $valor, ?int $personaId = null): void
    {
        $this->guardador->guardar($this->entrevista, new RespuestaData($codigo, $valor, $personaId), CanalRespuesta::Portal, $this->cliente);
    }

    /**
     * Un ítem con una lectura terminada y sus datos (casilla => valor).
     *
     * @param  array<string, string|null>  $datos
     */
    private function documento(string $codigo, array $datos): EntrevistaDocumento
    {
        // Usa el ítem que el checklist ya armó, para que un cambio de respuesta no lo retire.
        $item = $this->entrevista->documentos()->vigentes()->where('documento_codigo', $codigo)->whereDoesntHave('lecturas')->first()
            ?? $this->entrevista->documentos()->create(['documento_codigo' => $codigo, 'numero' => (int) $this->entrevista->documentos()->where('documento_codigo', $codigo)->max('numero') + 1, 'estado' => EstadoDocumento::Pendiente]);
        $archivo = app(DocumentosEntrevista::class)->subir($item, UploadedFile::fake()->createWithContent('doc.pdf', 'archivo '.++$this->archivos));

        $lectura = DocumentoLectura::query()->create([
            'documento_id' => $archivo->id,
            'entrevista_documento_id' => $item->id,
            'lector' => 'openai',
            'estado' => EstadoLectura::Leida,
            'tipo_detectado' => $codigo,
            'anio_detectado' => 2025,
            'paginas' => 1,
            'terminada_en' => now(),
        ]);

        foreach ($datos as $casilla => $valor) {
            $lectura->datos()->create([
                'entrevista_documento_id' => $item->id,
                'casilla' => (string) $casilla,
                'valor' => $valor,
                'valor_leido' => $valor,
                'confianza' => 0.99,
                'motivo_confianza' => MotivoConfianza::EnTextoDelPdf,
                'legibilidad' => Legibilidad::Claro,
                'pagina' => 1,
                'origen' => OrigenDato::Lector,
            ]);
        }

        return $item;
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

    public function test_r01_el_ssn_de_otra_persona_abre_una_bloqueante_sin_guardar_el_numero(): void
    {
        $this->documento('tarjeta-ssn', ['ssn' => '123-45-6789']);
        $w2 = $this->documento('w2', ['a' => '987-65-4321']);

        $this->correr();

        $excepcion = $this->excepciones('R-01')->sole();
        $this->assertSame(SeveridadRegla::Bloqueante, $excepcion->severidad);
        $this->assertSame(EstadoExcepcion::Abierta, $excepcion->estado);
        $this->assertSame($w2->id, $excepcion->entrevista_documento_id);
        $this->assertSame('a', $excepcion->dato->casilla);
        $this->assertStringNotContainsString('4321', json_encode($excepcion->getAttributes()));
        $this->assertStringNotContainsString('6789', json_encode($excepcion->getAttributes()));
    }

    public function test_r01_el_ssn_truncado_con_los_mismos_ultimos_4_pasa(): void
    {
        $this->documento('tarjeta-ssn', ['ssn' => '123-45-6789']);
        $this->documento('w2', ['a' => '***-**-6789']);
        $this->documento('1099-nec', ['receptor-tin' => 'XXX-XX-6789']);
        $this->documento('1099-int', ['receptor-tin' => '12-3456789']); // EIN: no se compara

        $this->correr();

        $this->assertCount(0, $this->excepciones('R-01'));
    }

    public function test_r01_el_conyuge_se_compara_con_b43_y_sin_ningun_numero_no_corre(): void
    {
        $this->documento('w2', ['a' => '***-**-4321']);
        $this->correr();
        $this->assertCount(0, $this->excepciones('R-01'));

        $this->responder('B1', 'casado');
        $this->responder('B4.3', '987-65-4321');
        $this->documento('tarjeta-ssn', ['ssn' => '123-45-6789']);
        $this->documento('ssa-1099', ['receptor-tin' => '555-55-0000']);
        $this->correr();

        $excepcion = $this->excepciones('R-01')->sole();
        $this->assertSame('receptor-tin', $excepcion->dato->casilla);
    }

    public function test_r01_casado_sin_el_numero_del_conyuge_no_acusa(): void
    {
        $this->responder('B1', 'casado');
        $this->documento('tarjeta-ssn', ['ssn' => '123-45-6789']);
        $this->documento('w2', ['a' => '987-65-4321']);

        $this->correr();

        $this->assertCount(0, $this->excepciones('R-01'));
    }

    public function test_r05_dos_w2_de_la_misma_persona_sobre_el_maximo_es_informativa_sin_montos(): void
    {
        $this->documento('w2', ['a' => '123-45-6789', '4' => '6000.00']);
        $this->documento('w2', ['a' => '***-**-6789', '4' => '5000.00']);

        $this->correr();

        $excepcion = $this->excepciones('R-05')->sole();
        $this->assertSame(SeveridadRegla::Informativa, $excepcion->severidad);
        $this->assertSame(2, $excepcion->detalle['w2']);
        $this->assertStringNotContainsString('6000', json_encode($excepcion->getAttributes()));
    }

    public function test_r05_dos_conyuges_cada_uno_bajo_el_maximo_no_abre_nada(): void
    {
        $this->documento('w2', ['a' => '123-45-6789', '4' => '6000.00']);
        $this->documento('w2', ['a' => '987-65-4321', '4' => '6000.00']);

        $this->correr();

        $this->assertCount(0, $this->excepciones('R-05'));
    }

    public function test_r05_un_solo_w2_o_bajo_el_maximo_no_abre_nada(): void
    {
        $this->documento('w2', ['a' => '123-45-6789', '4' => '11000.00']);
        $this->documento('w2', ['a' => '987-65-4321', '4' => '2000.00']);
        $this->documento('w2', ['a' => '987-65-4321', '4' => '3000.00']);

        $this->correr();

        $this->assertCount(0, $this->excepciones('R-05'));
    }

    public function test_r08a_la_casilla_10_sin_cuidado_en_e6_abre_y_cambiar_e6_a_si_la_cierra(): void
    {
        // E6 solo se pregunta si alguien vivió con el cliente o lo mantuvo (C0) o si está casado.
        $this->responder('C0', 'si');
        $this->responder('E6', 'no');
        $w2 = $this->documento('w2', ['10' => '2500.00']);

        $this->correr();

        $excepcion = $this->excepciones('R-08')->sole();
        $this->assertSame(SeveridadRegla::Revision, $excepcion->severidad);
        $this->assertSame($w2->id, $excepcion->entrevista_documento_id);
        $this->assertSame('a', $excepcion->detalle['caso']);
        $this->assertStringNotContainsString('2500', json_encode($excepcion->getAttributes()));

        // El motor corre al guardar la respuesta.
        $this->responder('E6', 'si');

        $this->assertSame(EstadoExcepcion::Corregida, $excepcion->fresh()->estado);

        $this->responder('E6', 'no');
        $this->assertSame(EstadoExcepcion::Abierta, $excepcion->fresh()->estado);
        $this->assertCount(1, $this->excepciones('R-08'));
    }

    public function test_r08b_mas_w2_leidos_que_empleos_en_d11(): void
    {
        $this->responder('D1.1', 1);
        $this->documento('w2', ['1' => '1000.00']);

        $this->correr();
        $this->assertCount(0, $this->excepciones('R-08'));

        $this->documento('w2', ['1' => '2000.00']);
        $this->correr();

        $excepcion = $this->excepciones('R-08')->sole();
        $this->assertSame('b', $excepcion->detalle['caso']);
        $this->assertNull($excepcion->entrevista_documento_id);
    }

    public function test_r08c_persona_cubierta_del_1095a_que_no_esta_en_la_entrevista(): void
    {
        $this->documento('tarjeta-ssn', ['ssn' => '123-45-6789']);
        $this->documento('1095-a', ['5' => '123-45-6789', '16-B' => '123-45-6789', '17-B' => '***-**-5555']);

        $this->correr();

        $excepcion = $this->excepciones('R-08')->sole();
        $this->assertSame('c', $excepcion->detalle['caso']);
        $this->assertSame('17-B', $excepcion->dato->casilla);
        $this->assertStringNotContainsString('5555', json_encode($excepcion->getAttributes()));
        $this->assertCount(0, $this->excepciones('R-01'));
    }

    public function test_r08c_con_una_tarjeta_sin_leer_no_acusa(): void
    {
        $this->documento('tarjeta-ssn', ['ssn' => '123-45-6789']);
        $this->entrevista->documentos()->create(['documento_codigo' => 'tarjeta-ssn', 'numero' => 2, 'estado' => EstadoDocumento::Pendiente]);
        $this->documento('1095-a', ['17-B' => '***-**-5555']);

        $this->correr();

        $this->assertCount(0, $this->excepciones('R-08'));
    }

    public function test_r11_un_hijo_menor_de_17_sin_ssn_valido_es_solo_un_aviso(): void
    {
        $this->responder('C0', 'si');
        $hijo = $this->guardador->agregarPersona($this->entrevista, 'Hijo');
        $this->responder('C3', 'hijo', $hijo->id);
        $this->responder('C2', '2012-05-10', $hijo->id);
        $this->responder('C9', 'itin', $hijo->id);

        // Sin documentos: C9 es de las respuestas que corren el motor.
        $excepcion = $this->excepciones('R-11')->sole();
        $this->assertSame(SeveridadRegla::Revision, $excepcion->severidad);
        $this->assertSame(['persona_id' => $hijo->id, 'problema' => $excepcion->detalle['problema']], $excepcion->detalle);
        $this->assertStringNotContainsString('2012', json_encode($excepcion->getAttributes()));

        $this->responder('C9', 'ssn_valido', $hijo->id);
        $this->assertSame(EstadoExcepcion::Corregida, $excepcion->fresh()->estado);
    }

    public function test_r11_un_hijo_de_17_o_un_pariente_que_no_es_hijo_no_abre_nada(): void
    {
        $this->responder('C0', 'si');
        $mayor = $this->guardador->agregarPersona($this->entrevista, 'Mayor');
        $this->responder('C3', 'hijo', $mayor->id);
        $this->responder('C2', '2008-12-31', $mayor->id); // cumple 17 el 31 de diciembre de 2025
        $this->responder('C9', 'itin', $mayor->id);

        $padre = $this->guardador->agregarPersona($this->entrevista, 'Padre');
        $this->responder('C3', 'padre', $padre->id);
        $this->responder('C2', '2015-01-01', $padre->id);
        $this->responder('C9', 'itin', $padre->id);

        $this->correr();

        $this->assertCount(0, $this->excepciones('R-11'));
    }

    public function test_correr_dos_veces_no_duplica_y_las_reglas_nuevas_estan_implementadas(): void
    {
        $this->documento('tarjeta-ssn', ['ssn' => '123-45-6789']);
        $this->documento('w2', ['a' => '987-65-4321', '10' => '100.00']);

        $this->correr();
        $total = Excepcion::query()->count();
        $this->correr();

        $this->assertGreaterThan(1, $total);
        $this->assertSame($total, Excepcion::query()->count());
        $this->assertSame(['R-01', 'R-05', 'R-08', 'R-11'], CatalogoRegla::query()->delAnio(2025)->where('implementada', true)->whereIn('codigo', ['R-01', 'R-05', 'R-08', 'R-11'])->orderBy('codigo')->pluck('codigo')->all());
    }
}
