<?php

namespace Tests\Feature\Expediente;

use App\Enums\CatalogoFiscal\SeveridadRegla;
use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\Entrevista\EstadoDocumento;
use App\Enums\Entrevista\EstadoEntrevista;
use App\Enums\Lectura\MotivoConfianza;
use App\Enums\Lectura\OrigenDato;
use App\Enums\UserRole;
use App\Models\Entrevista\Entrevista;
use App\Models\ParametroFiscal;
use App\Models\User;
use App\Services\Entrevista\RespuestaData;
use App\Services\Entrevista\RespuestaGuardador;
use App\Services\Expediente\HojaTaxwise;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Expediente\Concerns\ArmaExpediente;
use Tests\Feature\Expediente\Concerns\ArmaHojaTaxwise;
use Tests\TestCase;

/**
 * F5.5a: la hoja para TaxWise (servicio y página).
 */
class HojaTaxwiseTest extends TestCase
{
    use ArmaExpediente;
    use ArmaHojaTaxwise;
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

    private function hoja(User $cliente, ?int $anio = null): array
    {
        return app(HojaTaxwise::class)->para($cliente, $anio ?? $this->anio);
    }

    /**
     * @param  array<string, mixed>  $hoja
     * @return array<string, mixed>
     */
    private function fila(array $hoja, string $descripcionEmpieza, ?string $origenEmpieza = null): array
    {
        return collect($this->filas($hoja))->first(
            fn ($f) => str_starts_with($f['descripcion'], $descripcionEmpieza) && ($origenEmpieza === null || str_starts_with($f['origen'], $origenEmpieza)),
        ) ?? $this->fail("No hay una fila que empiece por «{$descripcionEmpieza}».");
    }

    public function test_el_caso_de_maria_sale_en_el_orden_de_carga_con_sus_destinos_y_totales(): void
    {
        [$cliente] = $this->maria();

        $hoja = $this->hoja($cliente);

        $codigos = array_column($hoja['secciones'], 'codigo');
        $this->assertSame(['encabezado', 'dependientes', 'salarios'], array_slice($codigos, 0, 3));
        $this->assertSame($codigos, array_values(array_intersect(array_keys(HojaTaxwise::SECCIONES), $codigos)));

        $salarios = collect($hoja['secciones'])->firstWhere('codigo', 'salarios');
        $this->assertCount(1, $salarios['grupos']);
        $this->assertSame('Form W-2 (Empresa X)', $salarios['grupos'][0]['titulo']);

        $casilla1 = $this->fila($hoja, 'Casilla 1 —');
        $this->assertSame('Form 1040 · línea 1a', $casilla1['destino']);
        $this->assertSame('45120.00', $casilla1['valor']);
        $this->assertSame('monto', $casilla1['tipo']);
        $this->assertSame('validado', $casilla1['estado']);
        $this->assertStringStartsWith('Validado por ', $casilla1['estado_texto']);
        $this->assertStringContainsString('Form W-2 (Empresa X), casilla 1', $casilla1['origen']);
        $this->assertStringContainsString('?dato=', $casilla1['enlace']);
        $this->assertSame(0.99, $casilla1['confianza']['valor']);

        $this->assertSame('Form 1040 · línea 25a', $this->fila($hoja, 'Casilla 2 —')['destino']);

        $total = collect($hoja['totales'])->firstWhere('destino', 'Form 1040 · línea 1a');
        $this->assertSame('45120.00', $total['valor']);
        $this->assertSame(1, $total['documentos']);
        $this->assertTrue($total['completo']);
        $this->assertSame('Suma de 1 documento', $total['nota']);

        $estadoCivil = $this->fila($hoja, 'Estado civil para el IRS');
        $this->assertSame('decidido', $estadoCivil['estado']);
        $this->assertSame('Cabeza de familia (HOH)', $estadoCivil['valor']);

        $dependientes = collect($hoja['secciones'])->firstWhere('codigo', 'dependientes');
        $this->assertSame('Persona 1', $dependientes['grupos'][0]['titulo']);
        $this->assertSame('Hijo calificado', collect($dependientes['grupos'][0]['filas'])->firstWhere('descripcion', 'Clasificación')['valor']);

        $this->assertSame(count($this->filas($hoja)), $hoja['conteos']['filas']);
        $this->assertSame(0.9, $hoja['umbral']);
        // Un motivo por fila: los 7 de F4 y 6.3b más los 3 de los renglones leídos con IA (6.3c).
        $this->assertCount(10, $hoja['confianza_tabla']);
    }

    public function test_la_prioridad_de_los_estados(): void
    {
        [$cliente, $e, $preparador, $w2] = $this->maria();
        $w2->update(['estado' => EstadoDocumento::Leido]);
        // Reemplaza la lectura por una con un caso de cada estado.
        $datos = $this->leer($w2, [
            'c' => ['Empresa X'],
            '1' => ['45120.00', 0.99],
            '2' => ['4810.00', 0.60, MotivoConfianza::Dudoso],
            '3' => ['50000.00', 0.60, MotivoConfianza::Dudoso],
            '4' => [null, 0.0, MotivoConfianza::SinValor],
            '5' => ['50000.00', 0.99],
            '7' => ['100.00', 0.60, MotivoConfianza::Dudoso],
        ]);
        $datos['1']->update(['origen' => OrigenDato::Preparador, 'corregido_por' => $preparador->id, 'corregido_en' => now()]);
        $this->excepcionDeDato($e, $datos['3']);
        // Corregido y con excepción abierta: gana la excepción.
        $datos['7']->update(['origen' => OrigenDato::Preparador, 'corregido_por' => $preparador->id, 'corregido_en' => now()]);
        $this->excepcionDeDato($e, $datos['7'], 'R-03', SeveridadRegla::Revision);

        $hoja = $this->hoja($cliente);

        $estados = fn (string $casilla) => $this->fila($hoja, "Casilla {$casilla} —", 'Form W-2')['estado'];
        $this->assertSame('corregido', $estados('1'));
        $this->assertSame('baja_confianza', $estados('2'));
        $this->assertSame('con_excepcion', $estados('3'));
        $this->assertSame('pendiente', $estados('4'));
        $this->assertSame('sin_validar', $estados('5'));
        $this->assertSame('con_excepcion', $estados('7'));

        $this->assertSame('Leído con dudas (0,60)', $this->fila($hoja, 'Casilla 2 —')['estado_texto']);
        $this->assertSame('Leído, sin validar', $this->fila($hoja, 'Casilla 5 —')['estado_texto']);
        $this->assertStringStartsWith('Regla R-04 abierta (bloqueante): ', $this->fila($hoja, 'Casilla 3 —')['estado_texto']);
        $this->assertSame('Sin valor en el documento', $this->fila($hoja, 'Casilla 4 —')['estado_texto']);
        $this->assertStringStartsWith('Corregido por '.$preparador->name, $this->fila($hoja, 'Casilla 1 —')['estado_texto']);
        $this->assertSame('Leído con dudas', $this->fila($hoja, 'Casilla 2 —')['confianza']['titulo']);
        $this->assertNotEmpty($this->fila($hoja, 'Casilla 2 —')['confianza']['explicacion']);

        $this->assertSame(2, $hoja['conteos']['con_excepcion']);
        $this->assertSame(1, $hoja['conteos']['baja_confianza']);
        $this->assertGreaterThanOrEqual(1, $hoja['conteos']['sin_validar']);

        // Una casilla clave sin ninguna fila en la lectura también es pendiente.
        $this->assertSame('pendiente', $this->fila($hoja, 'Casilla 8 —')['estado']);

        // La 1a solo tiene un dato corregido: completa. La 25a tiene uno leído con dudas: no.
        $this->assertTrue(collect($hoja['totales'])->firstWhere('destino', 'Form 1040 · línea 1a')['completo']);
        $this->assertFalse(collect($hoja['totales'])->firstWhere('destino', 'Form 1040 · línea 25a')['completo']);
    }

    public function test_cambiar_el_umbral_cambia_baja_confianza_por_validado(): void
    {
        [$cliente, , , $w2] = $this->maria();
        $this->leer($w2, ['c' => ['Empresa X'], '1' => ['45120.00', 0.60, MotivoConfianza::Dudoso]]);

        $this->assertSame('baja_confianza', $this->fila($this->hoja($cliente), 'Casilla 1 —')['estado']);

        ParametroFiscal::query()->where(['tax_year' => $this->anio, 'categoria' => 'lectura', 'clave' => 'umbral_confianza'])->firstOrFail()->update(['valor' => 0.5]);

        $hoja = $this->hoja($cliente);
        $this->assertSame(0.5, $hoja['umbral']);
        $this->assertSame('validado', $this->fila($hoja, 'Casilla 1 —')['estado']);
    }

    public function test_un_documento_que_no_se_lee_sale_manual_y_uno_pendiente_sale_pendiente(): void
    {
        [$cliente, $e] = $this->maria();
        // F6 (6.3a): los registros del negocio ya tienen casillas; el K-1 sigue sin casillas y sale manual.
        $registros = $this->item($e, 'k-1', EstadoDocumento::Subido);
        $this->archivo($registros);
        $this->item($e, 'recibos-medicos', EstadoDocumento::Pendiente);
        $otro = $this->item($e, 'otro', EstadoDocumento::Subido);
        $otro->update(['descripcion' => 'Carta del banco', 'agregado_por_cliente' => true]);
        $this->archivo($otro);

        $hoja = $this->hoja($cliente);

        $negocio = collect($hoja['secciones'])->firstWhere('codigo', 'negocio');
        $manual = $negocio['grupos'][0]['filas'][0];
        $this->assertSame('manual', $manual['estado']);
        $this->assertSame('Digitar desde el documento', $manual['estado_texto']);
        $this->assertStringContainsString('1 archivo', $manual['origen']);
        $this->assertStringContainsString('?', '?'.$negocio['grupos'][0]['enlace']);

        $deducciones = collect($hoja['secciones'])->firstWhere('codigo', 'deducciones');
        $this->assertSame('pendiente', $deducciones['grupos'][0]['filas'][0]['estado']);
        $this->assertSame('Falta: el cliente no lo ha subido', $deducciones['grupos'][0]['filas'][0]['estado_texto']);

        $situaciones = collect($hoja['secciones'])->firstWhere('codigo', 'situaciones');
        $this->assertSame('Carta del banco', $situaciones['grupos'][0]['titulo']);
        $this->assertSame('manual', $situaciones['grupos'][0]['filas'][0]['estado']);
    }

    public function test_un_documento_que_se_lee_y_no_tiene_lectura_falta_leerlo(): void
    {
        [$cliente, $e] = $this->maria();
        $w2b = $this->item($e, 'w2', EstadoDocumento::Subido, null, 2);
        $this->archivo($w2b);

        $salarios = collect($this->hoja($cliente)['secciones'])->firstWhere('codigo', 'salarios');

        $this->assertSame('Form W-2 #1 (Empresa X)', $salarios['grupos'][0]['titulo']);
        $this->assertSame('Form W-2 #2', $salarios['grupos'][1]['titulo']);
        $this->assertSame('Falta leerlo', $salarios['grupos'][1]['filas'][0]['estado_texto']);
        $this->assertSame('pendiente', $salarios['grupos'][1]['filas'][0]['estado']);
    }

    public function test_las_lineas_de_calculo_no_salen_como_filas(): void
    {
        [$cliente] = $this->maria();

        $hoja = $this->hoja($cliente);
        $destinos = array_filter(array_column($this->filas($hoja), 'destino'));

        // 1040 · 11a (ingreso ajustado), 12 (deducción estándar) y 28 son de cálculo.
        $this->assertNotContains('Form 1040 · línea 11a', $destinos);
        $this->assertNotContains('Form 1040 · línea 12', $destinos);
        $this->assertNotContains('Form 1040 · línea 11a', array_column($hoja['totales'], 'destino'));
    }

    public function test_2026_usa_las_casillas_de_2026(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);
        $e = Entrevista::query()->create(['user_id' => $cliente->id, 'tax_year' => 2026, 'estado' => EstadoEntrevista::EnCurso, 'canal_inicio' => CanalRespuesta::Portal, 'iniciada_en' => now()]);
        app(RespuestaGuardador::class)->guardar($e, new RespuestaData('D15', 'si'), CanalRespuesta::Portal, $cliente);
        $nec = $this->item($e, '1099-nec', EstadoDocumento::Leido);
        $this->leer($nec, ['pagador-nombre' => ['Cliente Y'], '1a' => ['8000.00']]);

        $hoja = $this->hoja($cliente, 2026);

        $fila = $this->fila($hoja, 'Casilla 1a —');
        $this->assertSame('Schedule C · línea 1', $fila['destino']);
        $this->assertSame('8000.00', $fila['valor']);
        $this->assertSame('Schedule C · línea 1', collect($hoja['totales'])->firstWhere('destino', 'Schedule C · línea 1')['destino']);
        $this->assertSame('8000.00', collect($hoja['totales'])->firstWhere('destino', 'Schedule C · línea 1')['valor']);
        $this->assertStringContainsString('(Cliente Y)', collect($hoja['secciones'])->firstWhere('codigo', 'negocio')['grupos'][0]['titulo']);
    }

    public function test_la_retencion_del_1099_va_a_la_25b_y_la_casilla_sin_linea_trae_un_destino_por_defecto(): void
    {
        [$cliente, $e] = $this->clienteConExpediente(['D1' => 'si', 'D15' => 'si']);
        $w2 = $this->item($e, 'w2', EstadoDocumento::Leido);
        $this->leer($w2, ['c' => ['Empresa X'], 'anio' => ['2025'], '3' => ['45120.00']]);
        $nec = $this->item($e, '1099-nec', EstadoDocumento::Leido);
        $this->leer($nec, ['pagador-nombre' => ['Cliente Y'], '1' => ['8000.00'], '4' => ['800.00']]);

        $hoja = $this->hoja($cliente, 2025);

        $this->assertSame('Form 1040 · línea 25b', $this->fila($hoja, 'Casilla 4 —', 'Form 1099-NEC')['destino']);
        // W-2 casilla 3: desde F6 alimenta el Schedule SE, línea 8a.
        $this->assertSame('Schedule SE · línea 8a', $this->fila($hoja, 'Casilla 3 —', 'Form W-2')['destino']);
        $this->assertSame('Solo para validar (no se digita)', $this->fila($hoja, 'Casilla anio —', 'Form W-2')['destino']);
        $this->assertContains('Form 1040 · línea 25b', array_column($hoja['totales'], 'destino'));

        // El CSV usa el mismo destino.
        $csv = collect(iterator_to_array(app(HojaTaxwise::class)->filasCsv($cliente, 2025), false));
        $this->assertTrue($csv->contains(fn (array $f) => $f[1] === 'Schedule SE · línea 8a'));
    }

    public function test_los_documentos_sin_casillas_traen_el_destino_de_la_pregunta_que_los_activo(): void
    {
        [$cliente, $e] = $this->clienteConExpediente(['D15' => 'si', 'E8' => 'si', 'G3' => 'deposito_directo', 'G4' => 'debito_cuenta']);
        // F6 (6.3a): los registros del negocio y los recibos de útiles ya tienen casillas y salen por casilla.
        foreach (['datos-bancarios'] as $codigo) {
            $this->item($e, $codigo, EstadoDocumento::Subido);
        }

        $hoja = $this->hoja($cliente, 2025);

        $banco = $this->fila($hoja, 'Datos bancarios (número de ruta', 'Datos bancarios')['destino'];
        $this->assertStringContainsString('Form 1040 · línea 35a-35d', $banco);
        $this->assertStringContainsString('Form 1040 · línea 37', $banco);
        // Las preguntas de pago también traen su línea.
        $this->assertStringContainsString('Form 1040 · línea 37', $this->fila($hoja, 'Si te toca pagar')['destino']);
    }

    public function test_lo_sensible_sale_enmascarado_y_nunca_en_claro(): void
    {
        [$cliente, $e, $preparador, $w2, $tarjeta] = $this->maria();
        $casado = $this->clienteConExpediente(['B1' => 'casado', 'B4' => 'Pedro Gómez', 'B4.3' => $this->ssnConyuge, 'D1' => 'si'], preparador: $preparador);
        [$otroCliente, $otraEntrevista] = $casado;
        $conyuge = $this->item($otraEntrevista, 'tarjeta-ssn-conyuge', EstadoDocumento::Leido);
        $this->leer($conyuge, ['ssn' => [$this->ssnConyuge]]);

        foreach ([$cliente, $otroCliente] as $c) {
            $hoja = $this->hoja($c);
            $json = json_encode($hoja);

            foreach ([$this->ssn, '123456789', $this->ein, $this->ssnConyuge, '987654321', 'Ana Hija', 'Pedro Gómez', '2015-03-04'] as $secreto) {
                $this->assertStringNotContainsString($secreto, $json, "Salió en claro: {$secreto}");
            }
        }

        $maria = $this->hoja($cliente);
        $ssn = $this->fila($maria, 'SSN del contribuyente');
        $this->assertNull($ssn['valor']);
        $this->assertSame('***-**-6789', $ssn['mascara']);
        $this->assertTrue($ssn['sensible']);
        $this->assertSame(route('clientes.documentos.visor.revelar', ['cliente' => $cliente->id, 'item' => $tarjeta->id, 'dato' => (int) substr($ssn['id'], 5)]), $ssn['revelar_url']);

        $ein = $this->fila($maria, 'Casilla b —');
        $this->assertSame('**-***6789', $ein['mascara']);

        $nombre = $this->fila($maria, 'Nombre y apellido');
        $this->assertNull($nombre['valor']);
        $this->assertSame('Oculto', $nombre['mascara']);
        $this->assertStringContainsString('/entrevista/respuestas/', $nombre['revelar_url']);

        $casadoHoja = $this->hoja($otroCliente);
        $b43 = $this->fila($casadoHoja, 'SSN o ITIN de tu cónyuge');
        $this->assertNull($b43['valor']);
        $this->assertSame('***-**-4321', $b43['mascara']);
        $this->assertSame('Oculto', $this->fila($casadoHoja, 'Nombre completo de tu cónyuge')['mascara']);
        $this->assertSame('***-**-4321', $this->fila($casadoHoja, 'SSN del cónyuge')['mascara']);
    }

    public function test_la_pagina_no_trae_valores_sensibles_en_las_props(): void
    {
        [$cliente, , $preparador] = $this->maria();

        $json = '';
        $this->actingAs($preparador)->get(route('clientes.hoja-taxwise.show', $cliente))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$json) {
                $page->loadDeferredProps(function (Assert $reloaded) use (&$json) {
                    $json = json_encode($reloaded->toArray()['props']);
                });
            });

        $this->assertStringContainsString('45120.00', $json);

        foreach ([$this->ssn, '123456789', $this->ein, 'Ana Hija'] as $secreto) {
            $this->assertStringNotContainsString($secreto, $json);
        }
    }

    public function test_las_personas_exentas_no_salen_en_dependientes(): void
    {
        [$cliente, $e, $preparador] = $this->maria();
        $guardador = app(RespuestaGuardador::class);
        $padre = $guardador->agregarPersona($e, 'Persona 2 · padre');
        foreach (['C3' => 'padre', 'C4' => 3, 'C12' => 'no', 'C13' => 'no', 'C14' => 'si'] as $codigo => $valor) {
            $guardador->guardar($e, new RespuestaData($codigo, $valor, $padre->id), CanalRespuesta::Portal, $cliente);
        }

        $dependientes = collect($this->hoja($cliente)['secciones'])->firstWhere('codigo', 'dependientes');

        $this->assertSame(['Persona 1'], array_column($dependientes['grupos'], 'titulo'));
    }

    public function test_solo_el_preparador_asignado_y_el_administrador_ven_la_hoja(): void
    {
        [$cliente, , $preparador] = $this->maria();
        $otroPreparador = User::factory()->create(['role' => UserRole::Preparer]);
        $admin = User::factory()->create(['role' => UserRole::Administrator]);

        $this->actingAs($preparador)->get(route('clientes.hoja-taxwise.show', $cliente))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('ficha/hoja-taxwise')->has('ficha')->where('csv_url', route('clientes.hoja-taxwise.csv', ['cliente' => $cliente->id, 'tax_year' => $this->anio]))->missing('hoja'));
        $this->actingAs($admin)->get(route('clientes.hoja-taxwise.show', $cliente))->assertOk();
        $this->actingAs($otroPreparador)->get(route('clientes.hoja-taxwise.show', $cliente))->assertForbidden();
        $this->actingAs($cliente)->get(route('clientes.hoja-taxwise.show', $cliente))->assertForbidden();
    }

    public function test_un_cliente_sin_entrevista_da_una_hoja_vacia(): void
    {
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);

        $hoja = $this->hoja($cliente);

        $this->assertSame([], $hoja['secciones']);
        $this->assertSame(0, $hoja['conteos']['filas']);
    }
}
