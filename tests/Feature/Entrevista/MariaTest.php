<?php

namespace Tests\Feature\Entrevista;

use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\Entrevista\EstadoEntrevista;
use App\Enums\UserRole;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaRespuestaCambio;
use App\Models\User;
use App\Services\Consentimiento\Consentimientos;
use App\Services\Consentimiento\ConsentimientosCargador;
use App\Services\Entrevista\MotorEntrevista;
use App\Services\Entrevista\RespuestaGuardador;
use App\Services\WhatsappAgent\ToolExecutor;
use App\Support\AgenteWhatsappUser;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Caso María (documento madre, sección 3 y 4.8): empleada soltera con un
 * hijo de 8 años que va a guardería. Empieza la entrevista por WhatsApp
 * (las tool calls del agente) y la termina en el portal sin repetir nada.
 */
class MariaTest extends TestCase
{
    use RefreshDatabase;

    private const TELEFONO = '+15550006666';

    private User $maria;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogoFiscalSeeder::class);
        app(ConsentimientosCargador::class)->cargar();

        $this->maria = User::factory()->create(['role' => UserRole::Client, 'es_prueba' => true, 'phone' => self::TELEFONO, 'name' => 'María Prueba']);
        $this->maria->forceFill(['entrevista_catalogo' => true])->save();
        app(Consentimientos::class)->firmar($this->maria, (int) config('tax.current_tax_year'), 1, 'María Prueba', null, null);
    }

    /**
     * @return array<string, mixed>
     */
    private function agente(string $tool, array $argumentos = []): array
    {
        return app(ToolExecutor::class)->ejecutar($tool, $argumentos, $this->maria->fresh(), AgenteWhatsappUser::resolver(), telefono: self::TELEFONO);
    }

    private function entrevista(): Entrevista
    {
        return app(RespuestaGuardador::class)->entrevistaDe($this->maria, CanalRespuesta::Portal)->fresh();
    }

    /**
     * @param  list<string>  $si
     * @return list<array{codigo: string, valor: string}>
     */
    private function casillas(string $bloque, array $si, array $excepto = []): array
    {
        $codigos = collect($this->agente('consultar_entrevista')['proximas_preguntas'])
            ->filter(fn (array $p) => str_starts_with($p['codigo'], $bloque) && $p['persona'] === null && in_array($p['tipo'], ['si_no', 'usted_conyuge_no'], true))
            ->pluck('codigo')
            ->reject(fn (string $c) => in_array($c, $excepto, true));

        return $codigos->map(fn (string $c) => ['codigo' => $c, 'valor' => in_array($c, $si, true) ? 'si' : 'no'])->values()->all();
    }

    public function test_maria_empieza_por_whatsapp_y_termina_en_el_portal_sin_repetir(): void
    {
        // ── WhatsApp ──
        $this->assertSame([], $this->agente('guardar_respuestas', ['respuestas' => [
            ['codigo' => 'B1', 'valor' => 'nunca_casado'],
            ['codigo' => 'B5', 'valor' => 'si'],
        ]])['rechazadas']);

        $this->assertSame([], $this->agente('guardar_respuestas', ['respuestas' => [
            ['codigo' => 'A5', 'valor' => 'no'],
            ['codigo' => 'A6', 'valor' => ['usted']],
            ...array_map(fn ($c) => ['codigo' => $c, 'valor' => 'no'], ['A8', 'A9', 'A10', 'A11', 'A13']),
        ]])['rechazadas']);

        $this->agente('guardar_respuestas', ['respuestas' => [['codigo' => 'C0', 'valor' => 'si']]]);
        $hijo = $this->agente('agregar_persona', ['parentesco' => 'hijo']);
        $this->assertTrue($hijo['parentesco_guardado']);
        $this->assertSame([], $this->agente('guardar_respuestas', ['respuestas' => collect([
            'C4' => 12, 'C5' => 'no', 'C6' => 'si', 'C7' => 'no', 'C8' => 'no', 'C11' => 'no',
        ])->map(fn ($v, $c) => ['codigo' => $c, 'persona_id' => $hijo['persona_id'], 'valor' => $v])->values()->all()])['rechazadas']);

        $this->assertSame([], $this->agente('guardar_respuestas', ['respuestas' => [
            ...$this->casillas('D', ['D1']),
            ['codigo' => 'D1.1', 'valor' => 1],
        ]])['rechazadas']);
        $this->assertSame([], $this->agente('guardar_respuestas', ['respuestas' => $this->casillas('E', ['E6'])])['rechazadas']);
        $this->assertSame([], $this->agente('guardar_respuestas', ['respuestas' => $this->casillas('F', [])])['rechazadas']);
        $this->assertSame([], $this->agente('guardar_respuestas', ['respuestas' => [
            ['codigo' => 'G1', 'valor' => 'no'], ['codigo' => 'G2', 'valor' => 'no'], ['codigo' => 'G5', 'valor' => 'si'],
        ]])['rechazadas']);

        $estado = $this->agente('consultar_entrevista');
        $this->assertSame([], $estado['proximas_preguntas'], 'Ya no queda nada para WhatsApp.');
        $this->assertSame(route('portal.entrevista.inicio'), $estado['enlaces']['entrevista']);

        $porWhatsapp = $this->entrevista()->respuestas()->count();

        // ── Portal: las pantallas 2, 5, 6 y 7 ya aparecen completas; a la 3
        // solo le falta A12 (IP PIN), que es sensible y va por el portal ──
        $this->actingAs($this->maria)->get(route('portal.entrevista.inicio'))
            ->assertInertia(function (AssertableInertia $page) {
                $pantallas = collect($page->toArray()['props']['pantallas'])->keyBy('numero');

                foreach ([2, 5, 6, 7] as $n) {
                    $this->assertSame('completa', $pantallas[$n]['estado'], "Pantalla {$n}");
                }

                $this->assertSame($pantallas[3]['total'] - 1, $pantallas[3]['respondidas']);
                $this->assertNotSame('completa', $pantallas[1]['estado']);
            });

        // Le queda la identidad, lo sensible del hijo y del pago.
        $this->actingAs($this->maria)->put(route('portal.entrevista.guardar', ['pantalla' => 1, 'paso' => 'entrada']), ['respuestas' => [
            'A1' => 'María Prueba', 'A1.1' => '1990-04-12', 'A1.2' => 'Cajera', 'A2' => '100 Main St, Miami, FL 33101', 'A3' => 'ssn_valido',
        ]])->assertSessionHasNoErrors();
        $this->actingAs($this->maria)->put(route('portal.entrevista.persona.guardar', ['persona' => $hijo['persona_id'], 'paso' => 'entrada']), ['respuestas' => [
            'C1' => 'Mateo Prueba', 'C2' => '2017-06-01', 'C3' => 'hijo', 'C4' => '12', 'C5' => 'no', 'C6' => 'si', 'C7' => 'no', 'C8' => 'no', 'C9' => 'ssn_valido', 'C10' => 'no', 'C11' => 'no',
        ]])->assertSessionHasNoErrors();
        $this->actingAs($this->maria)->put(route('portal.entrevista.guardar', ['pantalla' => 3, 'paso' => 'entrada']), ['respuestas' => [
            'A5' => 'no', 'A6' => ['usted'], 'A8' => 'no', 'A9' => 'no', 'A10' => 'no', 'A11' => 'no', 'A12' => 'no', 'A13' => 'no',
        ]])->assertSessionHasNoErrors();
        $this->actingAs($this->maria)->put(route('portal.entrevista.guardar', ['pantalla' => 8, 'paso' => 'entrada']), ['respuestas' => [
            'G1' => 'no', 'G2' => 'no', 'G3' => 'deposito_directo', 'G4' => 'otro', 'G5' => 'si',
        ]])->assertRedirect(route('portal.entrevista.resumen'));

        $this->actingAs($this->maria)->post(route('portal.entrevista.confirmar'))->assertSessionHasNoErrors();

        // ── Lo que se comprueba ──
        $entrevista = $this->entrevista();
        $this->assertSame(EstadoEntrevista::Confirmada, $entrevista->estado);

        // Ninguna respuesta se pidió dos veces: lo que el portal repitió con
        // el mismo valor no deja cambios en el historial.
        $this->assertSame(0, EntrevistaRespuestaCambio::query()->count());
        $porCodigo = $entrevista->respuestas()->get()->groupBy(fn ($r) => $r->pregunta_codigo.'#'.$r->persona_id);
        $this->assertTrue($porCodigo->every(fn ($grupo) => $grupo->count() === 1));

        // Lo que María contó de verdad (sin los "no" de las listas).
        $contadas = $entrevista->respuestas()->get()->reject(fn ($r) => $r->valor === 'no')->count();
        $this->assertLessThanOrEqual(25, $contadas, "María dio {$contadas} respuestas.");
        $this->assertGreaterThan($porWhatsapp / 2, $porWhatsapp, 'La mayoría llegó por WhatsApp.');

        $situacion = app(MotorEntrevista::class)->situacion($entrevista);
        $this->assertTrue($situacion->tieneDocumento('w2'));
        $this->assertTrue($situacion->tieneDocumento('tarjeta-ssn', $hijo['persona_id']));
        $this->assertTrue($situacion->tieneDocumento('recibo-cuidado'));
        foreach (['sch-8812', 'f2441', 'sch-eic'] as $formulario) {
            $this->assertTrue($situacion->tieneFormulario($formulario), $formulario);
        }
    }
}
