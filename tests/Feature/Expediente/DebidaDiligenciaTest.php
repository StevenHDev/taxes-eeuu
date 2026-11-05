<?php

namespace Tests\Feature\Expediente;

use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\Entrevista\EstadoDocumento;
use App\Enums\Expediente\ClasificacionPersona;
use App\Enums\Expediente\EstadoCivilIrs;
use App\Enums\Expediente\EstadoExpediente;
use App\Enums\Expediente\TipoEventoExpediente;
use App\Enums\UserRole;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaPersona;
use App\Models\Expediente\Expediente;
use App\Models\User;
use App\Services\Entrevista\RespuestaData;
use App\Services\Entrevista\RespuestaGuardador;
use App\Services\Expediente\DebidaDiligencia;
use App\Services\Expediente\EstadosExpediente;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Expediente\Concerns\ArmaExpediente;
use Tests\TestCase;

/**
 * F5.4a: el respaldo de la debida diligencia (Form 8867) y los créditos.
 */
class DebidaDiligenciaTest extends TestCase
{
    use ArmaExpediente;
    use RefreshDatabase;

    private int $anio;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->seed(CatalogoFiscalSeeder::class);
        $this->anio = (int) config('tax.current_tax_year');
        App::setLocale('es');
    }

    /**
     * @return array{0: User, 1: Entrevista, 2: User, 3: EntrevistaPersona}
     */
    private function clienteConHijo(array $extra = []): array
    {
        [$cliente, $e, $preparador] = $this->clienteConExpediente(['A3' => 'ssn_valido', 'D1' => 'si', 'C0' => 'si', ...$extra]);
        $guardador = app(RespuestaGuardador::class);
        $persona = $guardador->agregarPersona($e);
        $guardador->guardar($e, new RespuestaData('C3', 'hijo', $persona->id), CanalRespuesta::Portal, $cliente);
        $guardador->guardar($e, new RespuestaData('C4', 12, $persona->id), CanalRespuesta::Portal, $cliente);

        return [$cliente, $e, $preparador, $persona];
    }

    private function vista(User $cliente, bool $ingles = false): array
    {
        return app(DebidaDiligencia::class)->vista($cliente, $this->anio, $ingles);
    }

    private function seccion(array $vista, string $parte): ?array
    {
        return collect($vista['secciones'])->firstWhere('parte', $parte);
    }

    private function reclamar(User $cliente, array $creditos): void
    {
        $expediente = app(EstadosExpediente::class)->recalcular($cliente, $this->anio);
        $expediente->update(['creditos_8867' => $creditos]);
    }

    public function test_las_partes_y_sus_preguntas_salen_del_catalogo(): void
    {
        [$cliente, , , $persona] = $this->clienteConHijo(['F1' => 'si']);
        $this->reclamar($cliente, ['ctc', 'aotc', 'eic', 'hoh']);

        $vista = $this->vista($cliente);

        $this->assertSame(['parte-i', 'parte-ii', 'parte-iii', 'parte-iv', 'parte-v'], array_column($vista['secciones'], 'parte'));
        $this->assertStringStartsWith('Parte III', $this->seccion($vista, 'parte-iii')['titulo']);
        $this->assertSame('ctc', $this->seccion($vista, 'parte-iii')['credito']);
        $this->assertNull($this->seccion($vista, 'parte-i')['credito']);

        $c3 = collect($this->seccion($vista, 'parte-iii')['preguntas'])->firstWhere('codigo', 'C3');
        $this->assertSame(['orden' => $persona->orden, 'etiqueta' => $persona->etiqueta], $c3['persona']);
        $this->assertSame('Hijo', $c3['valor_texto']);
        $this->assertContains('F1', array_column($this->seccion($vista, 'parte-iv')['preguntas'], 'codigo'));
    }

    public function test_un_credito_no_reclamado_no_muestra_su_parte(): void
    {
        [$cliente] = $this->clienteConHijo(['F1' => 'si']);
        $this->reclamar($cliente, ['ctc']);

        $partes = array_column($this->vista($cliente)['secciones'], 'parte');

        $this->assertSame(['parte-i', 'parte-iii'], $partes);

        $this->reclamar($cliente, []);
        $this->assertSame(['parte-i'], array_column($this->vista($cliente)['secciones'], 'parte'));
    }

    public function test_las_sugerencias_con_su_motivo(): void
    {
        [$cliente, $e, , $persona] = $this->clienteConHijo(['F1' => 'si']);
        $expediente = app(EstadosExpediente::class)->recalcular($cliente, $this->anio);

        // Sin decisiones del preparador: solo lo que dicen las respuestas.
        $creditos = collect($this->vista($cliente)['creditos'])->keyBy('credito');
        $this->assertTrue($creditos['aotc']['sugerido']);
        $this->assertTrue($creditos['eic']['sugerido']);
        $this->assertStringContainsString('D1', $creditos['eic']['motivo_sugerencia']);
        $this->assertFalse($creditos['ctc']['sugerido']);
        $this->assertFalse($creditos['hoh']['sugerido']);
        $this->assertNull($creditos['hoh']['motivo_sugerencia']);
        // Nunca se guardó: reclamado sigue a sugerido.
        $this->assertTrue($creditos['eic']['reclamado']);
        $this->assertFalse($creditos['ctc']['reclamado']);

        $expediente->update(['estado_civil_irs' => EstadoCivilIrs::Hoh]);
        $expediente->personas()->create(['persona_id' => $persona->id, 'clasificacion' => ClasificacionPersona::HijoCalificado, 'motivo' => 'Vive con el cliente', 'decidido_por' => null, 'decidido_en' => now()]);

        $creditos = collect($this->vista($cliente)['creditos'])->keyBy('credito');
        $this->assertTrue($creditos['hoh']['sugerido']);
        $this->assertTrue($creditos['ctc']['sugerido']);
        $this->assertSame('La persona 1 está clasificada como hijo calificado', $creditos['ctc']['motivo_sugerencia']);

        // Una persona exenta (no dependiente) no cuenta.
        $expediente->personas()->update(['clasificacion' => ClasificacionPersona::NoDependiente]);
        $this->assertFalse(collect($this->vista($cliente)['creditos'])->firstWhere('credito', 'ctc')['sugerido']);

        // EIC pide SSN válido.
        app(RespuestaGuardador::class)->guardar($e, new RespuestaData('A3', 'itin'), CanalRespuesta::Portal, $cliente);
        $this->assertFalse(collect($this->vista($cliente)['creditos'])->firstWhere('credito', 'eic')['sugerido']);
    }

    public function test_ningun_valor_sensible_sale_en_las_props(): void
    {
        [$cliente, $e, , $persona] = $this->clienteConHijo();
        app(RespuestaGuardador::class)->guardar($e, new RespuestaData('C9', 'ssn_valido', $persona->id), CanalRespuesta::Portal, $cliente);
        $this->reclamar($cliente, ['ctc', 'eic']);

        $vista = $this->vista($cliente);
        $preguntas = collect($vista['secciones'])->flatMap(fn ($s) => $s['preguntas']);

        $sensibles = $preguntas->where('sensible', true);
        $this->assertNotEmpty($sensibles);
        $this->assertTrue($sensibles->every(fn ($p) => $p['valor_texto'] === null));
        $this->assertTrue($preguntas->firstWhere('codigo', 'C9')['respondida']);
        $this->assertStringNotContainsString('ssn_valido', json_encode($vista));
        $this->assertStringNotContainsString('SSN válido para trabajar', json_encode($vista));
    }

    public function test_cada_respuesta_trae_canal_fecha_y_quien(): void
    {
        [$cliente, $e, $preparador, $persona] = $this->clienteConHijo();
        $this->reclamar($cliente, ['ctc']);
        app(RespuestaGuardador::class)->guardar($e, new RespuestaData('C4', 10, $persona->id), CanalRespuesta::Preparador, $preparador);

        $preguntas = collect($this->seccion($this->vista($cliente), 'parte-iii')['preguntas']);

        $c3 = $preguntas->firstWhere('codigo', 'C3');
        $this->assertSame('portal', $c3['canal']);
        $this->assertNotNull($c3['respondida_en']);
        $this->assertNull($c3['actor']);
        $this->assertSame(0, $c3['cambios']);

        $c4 = $preguntas->firstWhere('codigo', 'C4');
        $this->assertSame('preparador', $c4['canal']);
        $this->assertSame($preparador->name, $c4['actor']);
        $this->assertSame(1, $c4['cambios']);
        $this->assertSame('10', $c4['valor_texto']);

        $c5 = $preguntas->firstWhere('codigo', 'C5');
        $this->assertFalse($c5['respondida']);
        $this->assertNull($c5['canal']);
    }

    public function test_los_documentos_de_cada_parte_con_quien_los_valido(): void
    {
        [$cliente, $e, $preparador, $persona] = $this->clienteConHijo(['F1' => 'si']);
        $this->reclamar($cliente, ['aotc']);
        $tarjeta = $this->item($e, 'tarjeta-ssn', EstadoDocumento::Validado);
        $tarjeta->update(['revisado_por' => $preparador->id, 'revisado_en' => now()]);
        $t1098 = $this->item($e, '1098-t', EstadoDocumento::Leido);

        $vista = $this->vista($cliente);

        $base = collect($this->seccion($vista, 'parte-i')['documentos'])->firstWhere('codigo', 'tarjeta-ssn');
        $this->assertSame($tarjeta->id, $base['item_id']);
        $this->assertSame('validado', $base['estado']);
        $this->assertSame($preparador->name, $base['validado_por']);
        $this->assertNotNull($base['validado_en']);
        $this->assertSame(route('clientes.documentos.visor', ['cliente' => $cliente->id, 'item' => $tarjeta->id]), $base['enlace']);

        $iv = $this->seccion($vista, 'parte-iv')['documentos'];
        $this->assertContains('1098-t', array_column($iv, 'codigo'));
        $fila = collect($iv)->firstWhere('codigo', '1098-t');
        $this->assertNull($fila['validado_por']);
        $this->assertSame($t1098->id, $fila['item_id']);
        // Sin repetir dentro de la parte.
        $ids = array_column($this->seccion($vista, 'parte-i')['documentos'], 'item_id');
        $this->assertSame($ids, array_values(array_unique($ids)));
    }

    public function test_guardar_creditos_deja_el_evento(): void
    {
        [$cliente, , $preparador] = $this->clienteConHijo();

        $this->actingAs($preparador)
            ->put(route('clientes.debida-diligencia.creditos', $cliente), ['creditos' => ['ctc', 'eic'], 'tax_year' => $this->anio])
            ->assertRedirect();

        $expediente = Expediente::query()->where('user_id', $cliente->id)->firstOrFail();
        $this->assertSame(['ctc', 'eic'], $expediente->creditos_8867);
        $this->assertSame($preparador->id, $expediente->creditos_8867_por);
        $this->assertNotNull($expediente->creditos_8867_en);

        $evento = $expediente->eventos()->where('tipo', TipoEventoExpediente::Decision)->firstOrFail();
        $this->assertSame($preparador->id, $evento->actor_id);
        $this->assertSame(['decision' => 'creditos_8867', 'valor' => ['ctc', 'eic']], $evento->datos);

        // Ningún crédito también es una decisión.
        $this->actingAs($preparador)
            ->put(route('clientes.debida-diligencia.creditos', $cliente), ['tax_year' => $this->anio])
            ->assertRedirect();
        $this->assertSame([], $expediente->fresh()->creditos_8867);

        $this->actingAs($preparador)
            ->put(route('clientes.debida-diligencia.creditos', $cliente), ['creditos' => ['eic', 'eic']])
            ->assertSessionHasErrors('creditos.0');
        $this->actingAs($preparador)
            ->put(route('clientes.debida-diligencia.creditos', $cliente), ['creditos' => ['otro']])
            ->assertSessionHasErrors('creditos.0');
    }

    public function test_despues_de_presentar_no_se_cambian_los_creditos(): void
    {
        [$cliente, , $preparador] = $this->clienteConHijo();
        $expediente = app(EstadosExpediente::class)->recalcular($cliente, $this->anio);
        $expediente->update(['estado' => EstadoExpediente::Presentado, 'creditos_8867' => ['ctc']]);

        $this->actingAs($preparador)
            ->put(route('clientes.debida-diligencia.creditos', $cliente), ['creditos' => ['eic'], 'tax_year' => $this->anio])
            ->assertStatus(422);

        $this->assertSame(['ctc'], $expediente->fresh()->creditos_8867);
    }

    public function test_otro_preparador_recibe_403(): void
    {
        [$cliente] = $this->clienteConHijo();
        $otro = User::factory()->create(['role' => UserRole::Preparer]);

        $this->actingAs($otro)->get(route('clientes.debida-diligencia.show', $cliente))->assertForbidden();
        $this->actingAs($otro)
            ->put(route('clientes.debida-diligencia.creditos', $cliente), ['creditos' => ['eic']])
            ->assertForbidden();
    }

    public function test_el_preparador_ve_la_pagina_con_la_ficha(): void
    {
        [$cliente, , $preparador] = $this->clienteConHijo();

        $this->actingAs($preparador)
            ->get(route('clientes.debida-diligencia.show', $cliente))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('ficha/debida-diligencia')->has('ficha')->missing('debida'));
    }
}
