<?php

namespace Tests\Feature\Lectura;

use App\Enums\CatalogoFiscal\SeveridadRegla;
use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\Entrevista\EstadoDocumento;
use App\Enums\Lectura\EstadoExcepcion;
use App\Enums\Lectura\MotivoConfianza;
use App\Enums\UserRole;
use App\Models\Entrevista\EntrevistaDocumento;
use App\Models\Lectura\Excepcion;
use App\Models\User;
use App\Services\Entrevista\RespuestaGuardador;
use App\Services\Lectura\ExplicadorExcepcion;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La cola, el visor y el diálogo dicen por qué saltó cada excepción en este
 * caso (no solo qué revisa la regla), sin datos del cliente.
 */
class ExplicadorExcepcionTest extends TestCase
{
    use RefreshDatabase;

    private EntrevistaDocumento $item;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('es');
        $this->seed(CatalogoFiscalSeeder::class);
        $cliente = User::factory()->create(['role' => UserRole::Client]);
        $entrevista = app(RespuestaGuardador::class)->entrevistaDe($cliente, CanalRespuesta::Portal);
        $this->item = $entrevista->documentos()->create(['documento_codigo' => 'w2', 'numero' => 1, 'estado' => EstadoDocumento::Leido]);
    }

    /**
     * @param  array<string, mixed>  $detalle
     * @return array{caso: string, que_hacer: string|null}
     */
    private function explicar(string $regla, array $detalle): array
    {
        $excepcion = Excepcion::query()->create([
            'entrevista_id' => $this->item->entrevista_id,
            'entrevista_documento_id' => $this->item->id,
            'regla_codigo' => $regla,
            'severidad' => SeveridadRegla::Bloqueante,
            'clave' => $regla.uniqid(),
            'detalle' => $detalle,
            'estado' => EstadoExcepcion::Abierta,
        ]);

        return app(ExplicadorExcepcion::class)->explicar($excepcion);
    }

    public function test_r06_dice_el_ano_que_trae_el_documento_y_el_que_se_declara(): void
    {
        $r = $this->explicar('R-06', ['campo' => 'anio', 'detectado' => 2024, 'esperado' => 2025]);

        $this->assertSame('Este documento dice que es del 2024, pero la declaración es del 2025.', $r['caso']);
        $this->assertStringContainsString('documento del 2025', (string) $r['que_hacer']);
    }

    public function test_r06_de_tipo_dice_que_parece_y_como_se_subio_con_los_nombres_del_catalogo(): void
    {
        $r = $this->explicar('R-06', ['campo' => 'tipo', 'detectado' => '1099-nec', 'esperado' => '1099-k']);

        $this->assertSame('Este archivo se leyó como «Form 1099-NEC», pero se subió como «Form 1099-K».', $r['caso']);
        $this->assertNotNull($r['que_hacer']);
    }

    public function test_r07_dice_si_el_archivo_esta_repetido_aqui_o_es_de_otro_cliente(): void
    {
        $this->assertSame('Este mismo archivo ya está subido en otro documento de esta declaración.', $this->explicar('R-07', ['coincidencias' => 1, 'de_otro_cliente' => false])['caso']);
        $this->assertSame('Este mismo archivo ya está subido en otros 2 documentos de esta declaración.', $this->explicar('R-07', ['coincidencias' => 2, 'de_otro_cliente' => false])['caso']);
        $this->assertSame('Este mismo archivo ya lo subió otro cliente.', $this->explicar('R-07', ['coincidencias' => 1, 'de_otro_cliente' => true])['caso']);
    }

    public function test_r03_dice_la_confianza_contra_el_minimo_o_que_no_salio_valor(): void
    {
        $this->assertSame(
            'La casilla 1 se leyó con una confianza de 60 %, por debajo del mínimo de 90 %.',
            $this->explicar('R-03', ['casilla' => '1', 'motivo_confianza' => MotivoConfianza::Dudoso->value, 'confianza' => 0.6, 'umbral' => 0.9])['caso'],
        );
        $this->assertSame(
            'No se pudo leer la casilla 4: no salió ningún valor.',
            $this->explicar('R-03', ['casilla' => '4', 'motivo_confianza' => MotivoConfianza::SinValor->value, 'confianza' => 0, 'umbral' => 0.9])['caso'],
        );
    }

    public function test_r01_dice_que_el_numero_no_coincide_con_nadie_de_la_declaracion_y_que_hacer(): void
    {
        $r = $this->explicar('R-01', ['casilla' => 'a', 'problema' => 'x']);

        $this->assertStringContainsString('casilla a', $r['caso']);
        $this->assertStringContainsString('ninguna persona de la declaración', $r['caso']);
        $this->assertStringContainsString('tarjeta del Seguro Social', (string) $r['que_hacer']);
    }

    public function test_r04_r05_r08_r09_y_r11_tienen_su_frase(): void
    {
        $this->assertStringContainsString('casilla 2', $this->explicar('R-04', ['casilla' => '2'])['caso']);
        $this->assertStringContainsString('tope del Seguro Social', $this->explicar('R-04', ['casilla' => '3'])['caso']);
        $this->assertStringContainsString('2 W-2', $this->explicar('R-05', ['casilla' => '4', 'w2' => 2])['caso']);
        $this->assertSame('Hay más W-2 leídos que empleos en la entrevista.', $this->explicar('R-08', ['problema' => 'Hay más W-2 leídos que empleos en la entrevista.'])['caso']);
        $this->assertStringContainsString('Form 8962', $this->explicar('R-09', ['problema' => 'Hay adelanto del crédito de la prima: el Form 8962 es obligatorio.'])['caso']);
        $this->assertStringContainsString('menor de 17', $this->explicar('R-11', ['problema' => 'Hijo menor de 17 sin SSN válido para trabajar: no da el crédito por hijos.'])['caso']);
    }

    public function test_las_frases_salen_en_ingles_con_el_idioma_del_preparador(): void
    {
        app()->setLocale('en');

        $this->assertSame('This document says it is for 2024, but the return is for 2025.', $this->explicar('R-06', ['campo' => 'anio', 'detectado' => 2024, 'esperado' => 2025])['caso']);
        $this->assertSame('This file was read as “Form 1099-NEC”, but it was uploaded as “Form 1099-K”.', $this->explicar('R-06', ['campo' => 'tipo', 'detectado' => '1099-nec', 'esperado' => '1099-k'])['caso']);
        $this->assertSame('There are more W-2s read than jobs in the interview.', $this->explicar('R-08', ['problema' => 'Hay más W-2 leídos que empleos en la entrevista.'])['caso']);
    }

    public function test_una_regla_sin_frase_propia_no_deja_la_pantalla_vacia(): void
    {
        $this->assertSame('La regla R-99 encontró algo que revisar.', $this->explicar('R-99', [])['caso']);
    }
}
