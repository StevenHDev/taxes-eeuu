<?php

namespace Tests\Feature\Expediente;

use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\Entrevista\EstadoDocumento;
use App\Enums\Entrevista\EstadoEntrevista;
use App\Enums\Expediente\ClasificacionPersona;
use App\Enums\Expediente\EstadoCivilIrs;
use App\Enums\Expediente\EstadoExpediente;
use App\Enums\Expediente\PuntoRevision;
use App\Enums\Expediente\TipoEventoExpediente;
use App\Models\Documento;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaDocumento;
use App\Models\Expediente\Expediente;
use App\Models\User;
use App\Services\Consentimiento\Consentimientos;
use App\Services\Consentimiento\ConsentimientosCargador;
use App\Services\Entrevista\ChecklistDocumentos;
use App\Services\Entrevista\DocumentosEntrevista;
use App\Services\Entrevista\RespuestaData;
use App\Services\Entrevista\RespuestaGuardador;
use App\Services\Expediente\DebidaDiligencia;
use App\Services\Expediente\HojaTaxwise;
use App\Services\Expediente\RevisionExpediente;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Expediente\Concerns\ArmaExpediente;
use Tests\Feature\Expediente\Concerns\ArmaHojaTaxwise;
use Tests\TestCase;

/**
 * F5, de punta a punta: el caso de María, desde la entrevista hasta que el IRS
 * acepta la declaración. Las lecturas de OpenAI se siembran (como en las demás
 * pruebas del expediente): lo que se prueba aquí es el expediente, no el lector.
 */
class MariaExpedienteTest extends TestCase
{
    use ArmaExpediente;
    use ArmaHojaTaxwise;
    use RefreshDatabase;

    private int $anio;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Storage::fake(Documento::disco());
        $this->seed(CatalogoFiscalSeeder::class);
        $this->anio = (int) config('tax.current_tax_year');
        App::setLocale('es');
    }

    private function expediente(User $cliente): Expediente
    {
        return Expediente::query()->where('user_id', $cliente->id)->where('tax_year', $this->anio)->firstOrFail();
    }

    private function estado(User $cliente): EstadoExpediente
    {
        return $this->expediente($cliente)->fresh()->estado;
    }

    /**
     * @return list<string> los estados por los que pasó, en orden
     */
    private function recorrido(User $cliente): array
    {
        return $this->expediente($cliente)->eventos()
            ->whereIn('tipo', [TipoEventoExpediente::Estado, TipoEventoExpediente::Reabierto])
            ->orderBy('id')->get()
            ->map(fn ($ev) => $ev->estado_nuevo->value)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function accion(User $preparador, User $cliente, string $accion, array $extra = []): TestResponse
    {
        return $this->actingAs($preparador)->post(route('clientes.revision.transicion', $cliente), ['tax_year' => $this->anio, 'accion' => $accion, ...$extra]);
    }

    private function decidirEstadoCivil(User $preparador, User $cliente): void
    {
        $this->actingAs($preparador)->put(route('clientes.revision.estado-civil', $cliente), ['tax_year' => $this->anio, 'valor' => 'hoh', 'motivo' => 'Vive con su hijo y paga el hogar.'])->assertRedirect();
    }

    private function confirmar(User $preparador, User $cliente, string $punto): void
    {
        $this->actingAs($preparador)->put(route('clientes.revision.punto', [$cliente, $punto]), ['tax_year' => $this->anio, 'confirmado' => true])->assertRedirect()->assertSessionHasNoErrors();
    }

    /**
     * Los datos de las casillas del W-2, la tarjeta y el 1095-A (todo con el mismo SSN).
     */
    private function leerDocumentos(EntrevistaDocumento $w2, EntrevistaDocumento $tarjeta, EntrevistaDocumento $a1095): void
    {
        $this->leer($w2, ['c' => ['Empresa X'], 'b' => [$this->ein], '1' => ['45120.00'], '2' => ['4810.00']]);
        $this->leer($tarjeta, ['nombre' => ['María Pérez'], 'ssn' => [$this->ssn]]);
        $this->leer($a1095, ['anio' => [(string) $this->anio], '4' => ['María Pérez'], '5' => [$this->ssn], '33-A' => ['6000.00'], '33-B' => ['6600.00'], '33-C' => ['3000.00']]);
    }

    public function test_maria_de_la_entrevista_a_la_declaracion_aceptada(): void
    {
        // 1. Invitada: tiene respuestas pero ni consentimiento ni entrevista confirmada.
        [$cliente, $e, $preparador] = $this->clienteConExpediente([
            'A1' => 'María Pérez', 'A3' => 'ssn_valido', 'B1' => 'nunca_casado', 'D1' => 'si', 'C0' => 'si', 'G5' => 'no',
        ], consentimiento: false, confirmada: false);
        $guardador = app(RespuestaGuardador::class);
        $persona = $guardador->agregarPersona($e, 'Persona 1');
        foreach (['C3' => 'hijo', 'C4' => 12, 'C1' => 'Ana Hija', 'C2' => '2015-03-04'] as $codigo => $valor) {
            $guardador->guardar($e, new RespuestaData($codigo, $valor, $persona->id), CanalRespuesta::Portal, $cliente);
        }
        $this->assertSame(EstadoExpediente::Invitado, $this->estado($cliente));

        // 2. Firma el consentimiento: sigue en la entrevista.
        app(ConsentimientosCargador::class)->cargar();
        app(Consentimientos::class)->firmar($cliente, $this->anio, 1, 'María Pérez', null, null);
        $this->assertSame(EstadoExpediente::EnEntrevista, $this->estado($cliente));

        // 3. Confirma la entrevista: pasa a recolectar documentos.
        $e->update(['estado' => EstadoEntrevista::Confirmada, 'confirmada_en' => now()]);
        $w2 = $this->item($e, 'w2', EstadoDocumento::Pendiente);
        $tarjeta = $this->item($e, 'tarjeta-ssn', EstadoDocumento::Pendiente);
        $a1095 = $this->item($e, '1095-a', EstadoDocumento::Pendiente);
        // El resto del checklist, fuera del camino de María.
        $e->documentos()->vigentes()->whereNotIn('id', [$w2->id, $tarjeta->id, $a1095->id])->get()
            ->each(fn ($i) => $i->update(['estado' => EstadoDocumento::Validado]));
        $this->assertSame(EstadoExpediente::EnRecoleccion, $this->estado($cliente));

        // 4. Sube el W-2 (por el servicio real), la tarjeta y el 1095-A: queda por leer y validar.
        app(DocumentosEntrevista::class)->subir($w2, UploadedFile::fake()->create('w2.pdf', 10, 'application/pdf'));
        $this->assertSame(1, $w2->fresh()->archivos()->count());
        $this->assertSame(EstadoExpediente::EnRecoleccion, $this->estado($cliente));

        $this->archivo($tarjeta);
        $this->archivo($a1095);
        $tarjeta->update(['estado' => EstadoDocumento::Subido]);
        $a1095->update(['estado' => EstadoDocumento::Subido]);

        // 5. Las lecturas (simuladas) terminan; el preparador observa el W-2 y María responde.
        $this->leerDocumentos($w2, $tarjeta, $a1095);
        foreach ([$w2, $tarjeta, $a1095] as $item) {
            $item->update(['estado' => EstadoDocumento::Leido]);
        }
        $this->assertSame(EstadoExpediente::PorValidar, $this->estado($cliente));

        app(ChecklistDocumentos::class)->observar($w2->fresh(), 'La imagen sale cortada', $preparador);
        $this->assertSame(EstadoExpediente::EnConsulta, $this->estado($cliente));
        $w2->fresh()->update(['estado' => EstadoDocumento::Leido]);
        $this->assertSame(EstadoExpediente::PorValidar, $this->estado($cliente));

        // 6. El preparador valida los tres documentos: listo para preparar.
        foreach ([$w2, $tarjeta, $a1095] as $item) {
            app(ChecklistDocumentos::class)->validar($item->fresh(), $preparador);
        }
        $this->assertSame(EstadoExpediente::ListoParaPreparar, $this->estado($cliente));

        $this->assertSame([
            'en_entrevista', 'en_recoleccion', 'por_validar', 'en_consulta', 'por_validar', 'listo_para_preparar',
        ], array_slice($this->recorrido($cliente), -6));
        $this->assertSame(0, $this->expediente($cliente)->eventos()->where('tipo', TipoEventoExpediente::Reabierto)->count());

        // 7. Empieza la revisión.
        $this->accion($preparador, $cliente, 'empezar')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(EstadoExpediente::EnRevision, $this->estado($cliente));

        // Aún no se puede aprobar: faltan los puntos.
        $this->accion($preparador, $cliente, 'preparado')->assertSessionHasErrors('accion');

        // 8. Decide HOH y clasifica al hijo, con motivo.
        $this->decidirEstadoCivil($preparador, $cliente);
        $this->actingAs($preparador)->put(route('clientes.revision.persona', [$cliente, $persona]), ['tax_year' => $this->anio, 'clasificacion' => 'hijo_calificado', 'motivo' => 'Vivió 12 meses con ella.'])->assertRedirect();

        $expediente = $this->expediente($cliente);
        $this->assertSame(EstadoCivilIrs::Hoh, $expediente->estado_civil_irs);
        $this->assertSame(ClasificacionPersona::HijoCalificado, $expediente->personas()->firstOrFail()->clasificacion);
        $this->assertSame(2, $expediente->eventos()->where('tipo', TipoEventoExpediente::Decision)->count());

        // 9. Marca los créditos del 8867 (CTC y HOH) y confirma los puntos manuales.
        $this->actingAs($preparador)->put(route('clientes.debida-diligencia.creditos', $cliente), ['creditos' => ['ctc', 'hoh'], 'tax_year' => $this->anio])->assertRedirect();
        $this->confirmar($preparador, $cliente, 'identidad');
        $this->confirmar($preparador, $cliente, 'debida_diligencia');

        $puntos = collect(app(RevisionExpediente::class)->vista($cliente, $this->anio, false)['puntos'])->where('requerido', true);
        $this->assertTrue($puntos->every(fn ($p) => $p['cumplido']), 'Quedaron puntos sin cumplir: '.$puntos->where('cumplido', false)->pluck('punto')->implode(', '));
        $this->assertTrue($puntos->pluck('punto')->contains(PuntoRevision::DebidaDiligencia->value));

        // 10. La hoja trae 1a, 25a y el 8962 con su destino y origen.
        $hoja = app(HojaTaxwise::class)->para($cliente, $this->anio);
        $destinos = collect($this->filas($hoja))->pluck('destino');
        $this->assertContains('Form 1040 · línea 1a', $destinos);
        $this->assertContains('Form 1040 · línea 25a', $destinos);
        $this->assertTrue($destinos->contains(fn ($d) => str_starts_with((string) $d, 'Form 8962')), 'La hoja no trae filas del Form 8962.');
        $this->assertSame('45120.00', collect($hoja['totales'])->firstWhere('destino', 'Form 1040 · línea 1a')['valor']);
        $this->assertSame('4810.00', collect($hoja['totales'])->firstWhere('destino', 'Form 1040 · línea 25a')['valor']);
        $this->assertStringNotContainsString($this->ssn, json_encode($hoja));
        $this->actingAs($preparador)->get(route('clientes.hoja-taxwise.show', $cliente))->assertOk();

        // 11. La debida diligencia trae CTC y HOH con su sugerencia.
        $debida = app(DebidaDiligencia::class)->vista($cliente, $this->anio, false);
        $creditos = collect($debida['creditos'])->keyBy('credito');
        $this->assertTrue($creditos['ctc']['reclamado']);
        $this->assertTrue($creditos['ctc']['sugerido']);
        $this->assertTrue($creditos['hoh']['reclamado']);
        $this->assertTrue($creditos['hoh']['sugerido']);
        $this->assertContains('parte-iii', array_column($debida['secciones'], 'parte'));
        $this->actingAs($preparador)->get(route('clientes.debida-diligencia.show', $cliente))->assertOk();

        // 12. Aprueba, presenta y el IRS acepta.
        $this->accion($preparador, $cliente, 'preparado')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(EstadoExpediente::PreparadoEnTaxwise, $this->estado($cliente));

        $fecha = ($this->anio + 1).'-02-10';
        $this->accion($preparador, $cliente, 'presentado', ['fecha' => $fecha])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(EstadoExpediente::Presentado, $this->estado($cliente));
        $this->assertSame($fecha, $this->expediente($cliente)->presentado_en->toDateString());

        $this->accion($preparador, $cliente, 'aceptado')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(EstadoExpediente::Aceptado, $this->estado($cliente));

        $this->assertSame(
            ['en_revision', 'preparado_en_taxwise', 'presentado', 'aceptado'],
            array_slice($this->recorrido($cliente), -4),
        );

        // Cada transición manual la hizo el preparador.
        $actores = $this->expediente($cliente)->eventos()->whereIn('estado_nuevo', ['en_revision', 'preparado_en_taxwise', 'presentado', 'aceptado'])->pluck('actor_id')->unique()->all();
        $this->assertSame([$preparador->id], $actores);
    }

    public function test_un_w2_corregido_despues_de_preparar_reabre_la_revision(): void
    {
        [$cliente, $e, $preparador, $w2] = $this->maria();
        // maria() guarda respuestas después de confirmar y eso reabre la entrevista: se confirma otra vez.
        $e->update(['estado' => EstadoEntrevista::Confirmada, 'confirmada_en' => now()]);
        $e->documentos()->vigentes()->get()->each(fn ($i) => $i->update(['estado' => EstadoDocumento::Validado]));
        $this->assertSame(EstadoExpediente::ListoParaPreparar, $this->estado($cliente));

        $this->accion($preparador, $cliente, 'empezar')->assertRedirect();
        $this->confirmar($preparador, $cliente, 'identidad');
        $this->accion($preparador, $cliente, 'preparado')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(EstadoExpediente::PreparadoEnTaxwise, $this->estado($cliente));

        // El preparador corrige la casilla 1 en el visor.
        $dato = $w2->fresh()->lecturas()->firstOrFail()->datos()->where('casilla', '1')->firstOrFail();
        $this->actingAs($preparador)->patch(
            route('clientes.documentos.visor.corregir', ['cliente' => $cliente->id, 'item' => $w2->id, 'dato' => $dato->id]),
            ['valor' => '46120.00', 'motivo' => 'Había un error de lectura'],
        )->assertRedirect()->assertSessionHasNoErrors();

        // El documento vuelve a validar y la revisión se reabre con su evento, sin las confirmaciones.
        $this->assertSame(EstadoDocumento::Leido, $w2->fresh()->estado);
        $this->assertNotSame(EstadoExpediente::PreparadoEnTaxwise, $this->estado($cliente));
        $this->assertSame(EstadoExpediente::PorValidar, $this->estado($cliente));

        $reabierto = $this->expediente($cliente)->eventos()->where('tipo', TipoEventoExpediente::Reabierto)->firstOrFail();
        $this->assertSame(EstadoExpediente::PreparadoEnTaxwise, $reabierto->estado_anterior);
        $this->assertSame(EstadoExpediente::PorValidar, $reabierto->estado_nuevo);
        $this->assertStringContainsString('1 documento sin validar', (string) $reabierto->motivo);
        $this->assertSame(0, $this->expediente($cliente)->puntos()->where('confirmado', true)->count());

        // No se puede aprobar hasta validar otra vez; al validar queda listo y se revisa de nuevo.
        $this->accion($preparador, $cliente, 'preparado')->assertSessionHasErrors('accion');
        app(ChecklistDocumentos::class)->validar($w2->fresh(), $preparador);
        $this->assertSame(EstadoExpediente::ListoParaPreparar, $this->estado($cliente));

        $this->accion($preparador, $cliente, 'empezar')->assertRedirect();
        $this->assertSame(EstadoExpediente::EnRevision, $this->estado($cliente));
        $this->assertSame('46120.00', collect(app(HojaTaxwise::class)->para($cliente, $this->anio)['totales'])->firstWhere('destino', 'Form 1040 · línea 1a')['valor']);
    }
}
