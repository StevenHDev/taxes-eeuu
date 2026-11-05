<?php

namespace Tests\Feature\Expediente;

use App\Enums\CatalogoFiscal\SeveridadRegla;
use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\Entrevista\EstadoDocumento;
use App\Enums\Expediente\ClasificacionPersona;
use App\Enums\Expediente\EstadoCivilIrs;
use App\Enums\Expediente\EstadoExpediente;
use App\Enums\Expediente\PuntoRevision;
use App\Enums\Expediente\TipoEventoExpediente;
use App\Enums\UserRole;
use App\Models\Documento;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaPersona;
use App\Models\Expediente\Expediente;
use App\Models\User;
use App\Services\Entrevista\RespuestaData;
use App\Services\Entrevista\RespuestaGuardador;
use App\Services\Expediente\RevisionExpediente;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Expediente\Concerns\ArmaExpediente;
use Tests\TestCase;

/**
 * F5.3a: la Revisión del preparador. Decisiones con motivo, puntos del
 * checklist 7.2 (automáticos y manuales) y lo que la vista deja ver.
 */
class RevisionTest extends TestCase
{
    use ArmaExpediente;
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

    /**
     * Un cliente con todo cerrado, listo para empezar la revisión.
     *
     * @param  array<string, mixed>  $respuestas
     * @return array{0: User, 1: Entrevista, 2: User}
     */
    private function listo(array $respuestas = ['D1' => 'si']): array
    {
        [$cliente, $e, $preparador] = $this->clienteConExpediente($respuestas);

        foreach ($e->documentos()->vigentes()->get() as $item) {
            $item->update(['estado' => EstadoDocumento::Validado]);
        }

        return [$cliente, $e, $preparador];
    }

    private function expediente(User $cliente): Expediente
    {
        return Expediente::query()->where('user_id', $cliente->id)->where('tax_year', $this->anio)->firstOrFail();
    }

    private function enRevision(User $cliente): Expediente
    {
        $expediente = $this->expediente($cliente);
        $expediente->update(['estado' => EstadoExpediente::EnRevision, 'estado_desde' => now()]);

        return $expediente;
    }

    private function persona(Entrevista $e, array $respuestas = []): EntrevistaPersona
    {
        $guardador = app(RespuestaGuardador::class);
        $persona = $guardador->agregarPersona($e);

        foreach ($respuestas as $codigo => $valor) {
            $guardador->guardar($e, new RespuestaData($codigo, $valor, $persona->id), CanalRespuesta::Portal, $e->user);
        }

        return $persona;
    }

    /**
     * @return array<string, mixed>
     */
    private function vista(User $cliente, bool $ingles = false): array
    {
        return app(RevisionExpediente::class)->vista($cliente, $this->anio, $ingles);
    }

    /**
     * @param  array<string, mixed>  $vista
     * @return array<string, mixed>
     */
    private function punto(array $vista, PuntoRevision $punto): array
    {
        return collect($vista['puntos'])->firstWhere('punto', $punto->value);
    }

    public function test_la_pagina_trae_la_ficha_y_la_revision_diferida(): void
    {
        [$cliente, , $preparador] = $this->listo();

        $this->actingAs($preparador)->get(route('clientes.revision.show', ['cliente' => $cliente->id, 'tax_year' => $this->anio]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ficha/revision')
                ->has('ficha.expediente')
                ->loadDeferredProps(fn (Assert $r) => $r
                    ->where('revision.estado', 'listo_para_preparar')
                    ->has('revision.acciones', 1)
                    ->has('revision.puntos', 7)
                    ->has('revision.estado_civil.opciones', 5)
                    ->has('revision.clasificaciones', 3)
                    ->has('revision.enlaces')));
    }

    public function test_la_vista_no_trae_valores_sensibles_ni_las_respuestas_que_no_son_del_contrato(): void
    {
        [$cliente, $e] = $this->listo(['D1' => 'si', 'B1' => 'casado', 'B4.3' => '987654321', 'C0' => 'si']);
        $persona = $this->persona($e, ['C3' => 'hijo', 'C4' => 8, 'C1' => '123456789', 'C2' => '2019-03-04']);

        $vista = $this->vista($cliente);
        $texto = json_encode($vista, JSON_THROW_ON_ERROR);

        foreach (['987654321', '123456789', '2019-03-04'] as $valor) {
            $this->assertStringNotContainsString($valor, $texto);
        }

        $codigos = array_column($vista['personas'][0]['respuestas'], 'codigo');
        $this->assertSame($persona->id, $vista['personas'][0]['id']);
        $this->assertContains('C3', $codigos);
        $this->assertNotContains('C1', $codigos);
        $this->assertNotContains('C2', $codigos);
        $this->assertNotContains('C9', $codigos);

        $civil = array_column($vista['estado_civil']['respuestas_cliente'], 'codigo');
        $this->assertContains('B1', $civil);
        $this->assertNotContains('B4.3', $civil);
        $this->assertSame('Casado', collect($vista['estado_civil']['respuestas_cliente'])->firstWhere('codigo', 'B1')['valor_texto']);
    }

    public function test_decidir_el_estado_civil_guarda_el_motivo_y_deja_su_evento(): void
    {
        [$cliente, , $preparador] = $this->listo();

        $this->actingAs($preparador)->put(route('clientes.revision.estado-civil', $cliente), ['tax_year' => $this->anio, 'valor' => 'hoh', 'motivo' => 'Pagó más de la mitad del hogar'])
            ->assertRedirect();

        $expediente = $this->expediente($cliente);
        $this->assertSame(EstadoCivilIrs::Hoh, $expediente->estado_civil_irs);
        $this->assertSame('Pagó más de la mitad del hogar', $expediente->estado_civil_motivo);
        $this->assertSame($preparador->id, $expediente->estado_civil_por);

        $evento = $expediente->eventos()->where('tipo', TipoEventoExpediente::Decision)->firstOrFail();
        $this->assertSame($preparador->id, $evento->actor_id);
        $this->assertSame(['decision' => 'estado_civil', 'valor' => 'hoh'], $evento->datos);

        $vista = $this->vista($cliente);
        $this->assertSame('hoh', $vista['estado_civil']['valor']);
        $this->assertSame($preparador->name, $vista['estado_civil']['por']);
        $this->assertTrue($this->punto($vista, PuntoRevision::EstadoCivil)['cumplido']);
    }

    public function test_clasificar_a_una_persona_guarda_el_motivo_y_deja_su_evento(): void
    {
        [$cliente, $e, $preparador] = $this->listo(['D1' => 'si', 'C0' => 'si']);
        $persona = $this->persona($e, ['C3' => 'hijo', 'C4' => 6]);

        $this->actingAs($preparador)->put(route('clientes.revision.persona', [$cliente, $persona]), ['tax_year' => $this->anio, 'clasificacion' => 'hijo_calificado', 'motivo' => 'Vivió 12 meses con el cliente'])
            ->assertRedirect();

        $guardada = $this->expediente($cliente)->personas()->firstOrFail();
        $this->assertSame(ClasificacionPersona::HijoCalificado, $guardada->clasificacion);
        $this->assertSame('Vivió 12 meses con el cliente', $guardada->motivo);

        $evento = $this->expediente($cliente)->eventos()->where('tipo', TipoEventoExpediente::Decision)->firstOrFail();
        $this->assertSame(['decision' => 'persona', 'persona_id' => $persona->id, 'valor' => 'hijo_calificado'], $evento->datos);

        // Cambiar la decisión actualiza la misma fila.
        $this->actingAs($preparador)->put(route('clientes.revision.persona', [$cliente, $persona]), ['tax_year' => $this->anio, 'clasificacion' => 'pariente_calificado', 'motivo' => 'Corrección'])
            ->assertRedirect();
        $this->assertSame(1, $this->expediente($cliente)->personas()->count());
        $this->assertSame(ClasificacionPersona::ParienteCalificado, $this->expediente($cliente)->personas()->firstOrFail()->clasificacion);
    }

    public function test_el_motivo_vacio_da_422(): void
    {
        [$cliente, $e, $preparador] = $this->listo(['D1' => 'si', 'C0' => 'si']);
        $persona = $this->persona($e, ['C3' => 'hijo', 'C4' => 6]);

        $this->actingAs($preparador)->put(route('clientes.revision.estado-civil', $cliente), ['tax_year' => $this->anio, 'valor' => 'hoh', 'motivo' => ''])
            ->assertSessionHasErrors('motivo');
        $this->actingAs($preparador)->put(route('clientes.revision.persona', [$cliente, $persona]), ['tax_year' => $this->anio, 'clasificacion' => 'hijo_calificado', 'motivo' => ''])
            ->assertSessionHasErrors('motivo');

        $this->assertNull($this->expediente($cliente)->estado_civil_irs);
        $this->assertSame(0, $this->expediente($cliente)->personas()->count());
    }

    public function test_una_persona_de_otro_cliente_da_404(): void
    {
        [$cliente, , $preparador] = $this->listo(['D1' => 'si', 'C0' => 'si']);
        [, $otraEntrevista] = $this->clienteConExpediente(['D1' => 'si', 'C0' => 'si'], preparador: $preparador);
        $ajena = $this->persona($otraEntrevista, ['C3' => 'hijo', 'C4' => 6]);

        $this->actingAs($preparador)->put(route('clientes.revision.persona', [$cliente, $ajena]), ['tax_year' => $this->anio, 'clasificacion' => 'hijo_calificado', 'motivo' => 'x'])
            ->assertNotFound();
    }

    public function test_otro_preparador_recibe_403_y_el_cliente_tambien(): void
    {
        [$cliente, $e] = $this->listo(['D1' => 'si', 'C0' => 'si']);
        $persona = $this->persona($e, ['C3' => 'hijo', 'C4' => 6]);
        $otro = User::factory()->create(['role' => UserRole::Preparer]);

        foreach ([$otro, $cliente] as $quien) {
            $this->actingAs($quien)->get(route('clientes.revision.show', $cliente))->assertForbidden();
            $this->actingAs($quien)->put(route('clientes.revision.estado-civil', $cliente), ['tax_year' => $this->anio, 'valor' => 'hoh', 'motivo' => 'x'])->assertForbidden();
            $this->actingAs($quien)->put(route('clientes.revision.persona', [$cliente, $persona]), ['tax_year' => $this->anio, 'clasificacion' => 'hijo_calificado', 'motivo' => 'x'])->assertForbidden();
            $this->actingAs($quien)->put(route('clientes.revision.punto', [$cliente, 'identidad']), ['tax_year' => $this->anio, 'confirmado' => true])->assertForbidden();
            $this->actingAs($quien)->post(route('clientes.revision.transicion', $cliente), ['tax_year' => $this->anio, 'accion' => 'empezar'])->assertForbidden();
        }
    }

    public function test_solo_los_puntos_manuales_tienen_ruta(): void
    {
        [$cliente, , $preparador] = $this->listo();

        $this->actingAs($preparador)->put("/clientes/{$cliente->id}/revision/puntos/documentos", ['tax_year' => $this->anio, 'confirmado' => true])->assertNotFound();
    }

    public function test_los_puntos_automaticos_se_cumplen_con_los_datos(): void
    {
        [$cliente, $e, $preparador] = $this->listo(['D1' => 'si', 'C0' => 'si']);
        $persona = $this->persona($e, ['C3' => 'hijo', 'C4' => 6]);
        $item = $e->documentos()->where('documento_codigo', 'w2')->firstOrFail();
        $this->excepcion($e, 'R-04', SeveridadRegla::Bloqueante, $item);

        $vista = $this->vista($cliente);
        $this->assertFalse($this->punto($vista, PuntoRevision::EstadoCivil)['cumplido']);
        $this->assertFalse($this->punto($vista, PuntoRevision::Dependientes)['cumplido']);
        $this->assertSame('Falta clasificar a la persona 1', $this->punto($vista, PuntoRevision::Dependientes)['detalle']);
        $this->assertFalse($this->punto($vista, PuntoRevision::Excepciones)['cumplido']);
        $this->assertStringContainsString('R-04', $this->punto($vista, PuntoRevision::Excepciones)['detalle']);
        $this->assertTrue($this->punto($vista, PuntoRevision::Documentos)['cumplido']);

        $e->excepciones()->update(['estado' => 'resuelta']);
        $this->actingAs($preparador)->put(route('clientes.revision.estado-civil', $cliente), ['tax_year' => $this->anio, 'valor' => 'single', 'motivo' => 'Soltero'])->assertRedirect();
        $this->actingAs($preparador)->put(route('clientes.revision.persona', [$cliente, $persona]), ['tax_year' => $this->anio, 'clasificacion' => 'hijo_calificado', 'motivo' => 'Hijo'])->assertRedirect();

        $vista = $this->vista($cliente);
        foreach ([PuntoRevision::EstadoCivil, PuntoRevision::Dependientes, PuntoRevision::Documentos, PuntoRevision::Excepciones] as $punto) {
            $this->assertTrue($this->punto($vista, $punto)['cumplido'], $punto->value);
        }
    }

    public function test_el_punto_de_documentos_dice_cuantos_faltan_segun_r02(): void
    {
        [$cliente, $e] = $this->listo();
        $e->documentos()->where('documento_codigo', 'w2')->firstOrFail()->update(['estado' => EstadoDocumento::Leido]);

        $punto = $this->punto($this->vista($cliente), PuntoRevision::Documentos);

        $this->assertFalse($punto['cumplido']);
        $this->assertSame('R-02: 1 documento sin validar', $punto['detalle']);
    }

    public function test_el_punto_de_dependientes_solo_se_pide_si_hay_personas(): void
    {
        [$cliente, $e] = $this->listo(['D1' => 'si', 'C0' => 'si']);

        $punto = $this->punto($this->vista($cliente), PuntoRevision::Dependientes);
        $this->assertFalse($punto['requerido']);
        $this->assertTrue($punto['cumplido']);

        $this->persona($e, ['C3' => 'hijo', 'C4' => 6]);
        $this->assertTrue($this->punto($this->vista($cliente), PuntoRevision::Dependientes)['requerido']);
    }

    public function test_el_punto_de_debida_diligencia_solo_es_requerido_con_creditos(): void
    {
        [$cliente] = $this->listo();
        $expediente = $this->enRevision($cliente);

        $this->assertFalse($this->punto($this->vista($cliente), PuntoRevision::DebidaDiligencia)['requerido']);

        $expediente->update(['creditos_8867' => ['eic']]);
        $punto = $this->punto($this->vista($cliente), PuntoRevision::DebidaDiligencia);

        $this->assertTrue($punto['requerido']);
        $this->assertTrue($punto['editable']);
        $this->assertFalse($punto['cumplido']);
    }

    public function test_el_punto_del_anio_anterior_solo_es_requerido_si_g5_es_si(): void
    {
        [$cliente] = $this->listo(['D1' => 'si', 'G5' => 'no']);
        $this->assertFalse($this->punto($this->vista($cliente), PuntoRevision::AnioAnterior)['requerido']);

        [$otro] = $this->listo(['D1' => 'si', 'G5' => 'si']);
        $this->assertTrue($this->punto($this->vista($otro), PuntoRevision::AnioAnterior)['requerido']);
    }

    public function test_confirmar_la_identidad_en_revision_guarda_quien_y_cuando_y_deja_su_evento(): void
    {
        [$cliente, , $preparador] = $this->listo();
        $expediente = $this->enRevision($cliente);

        $this->actingAs($preparador)->put(route('clientes.revision.punto', [$cliente, 'identidad']), ['tax_year' => $this->anio, 'confirmado' => true, 'nota' => 'Cédula y tarjetas al día'])
            ->assertRedirect();

        $punto = $this->punto($this->vista($cliente), PuntoRevision::Identidad);
        $this->assertTrue($punto['cumplido']);
        $this->assertSame($preparador->name, $punto['confirmado_por']);
        $this->assertSame('Cédula y tarjetas al día', $punto['nota']);

        $evento = $expediente->eventos()->where('tipo', TipoEventoExpediente::Punto)->firstOrFail();
        $this->assertSame(['punto' => 'identidad', 'confirmado' => true], $evento->datos);

        $this->actingAs($preparador)->put(route('clientes.revision.punto', [$cliente, 'identidad']), ['tax_year' => $this->anio, 'confirmado' => false])->assertRedirect();
        $this->assertFalse($this->punto($this->vista($cliente), PuntoRevision::Identidad)['cumplido']);
    }

    public function test_confirmar_un_punto_fuera_de_en_revision_da_422(): void
    {
        [$cliente, , $preparador] = $this->listo();

        $this->actingAs($preparador)->put(route('clientes.revision.punto', [$cliente, 'identidad']), ['tax_year' => $this->anio, 'confirmado' => true])
            ->assertSessionHasErrors('accion');

        $this->assertNull($this->expediente($cliente)->puntos()->first());
    }

    public function test_confirmar_un_punto_que_no_se_pide_da_422(): void
    {
        [$cliente, , $preparador] = $this->listo();
        $this->enRevision($cliente);

        $this->actingAs($preparador)->put(route('clientes.revision.punto', [$cliente, 'debida_diligencia']), ['tax_year' => $this->anio, 'confirmado' => true])
            ->assertSessionHasErrors('accion');
    }

    public function test_la_persona_exenta_llega_sugerida_como_no_dependiente_y_se_muestra_por_parentesco(): void
    {
        [$cliente, $e] = $this->listo(['D1' => 'si', 'C0' => 'si']);
        $this->persona($e, ['C3' => 'padre', 'C12' => 'si']);

        $persona = $this->vista($cliente)['personas'][0];

        $this->assertSame('Persona 1 · padre', $persona['etiqueta']);
        $this->assertNull($persona['clasificacion']);
        $this->assertSame('no_dependiente', $persona['sugerida']['clasificacion']);
        $this->assertSame('Sus respuestas la dejan fuera como dependiente (C12)', $persona['sugerida']['motivo']);
    }

    public function test_la_vista_funciona_sin_expediente_ni_entrevista_y_no_crea_nada(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);

        $vista = $this->vista($cliente);

        $this->assertSame('invitado', $vista['estado']);
        $this->assertSame([], $vista['personas']);
        $this->assertSame(0, Expediente::query()->where('user_id', $cliente->id)->count());
    }

    public function test_la_vista_en_ingles_traduce_los_textos_del_servidor(): void
    {
        [$cliente] = $this->listo();
        App::setLocale('en');

        $vista = $this->vista($cliente, true);

        $this->assertSame('Start the review', $vista['acciones'][0]['etiqueta']);
    }
}
