<?php

namespace Tests\Feature\Lectura;

use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\Lectura\EstadoLectura;
use App\Enums\Lectura\Legibilidad;
use App\Enums\Lectura\MotivoConfianza;
use App\Enums\UserRole;
use App\Models\Documento;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaDocumento;
use App\Models\Lectura\DocumentoLectura;
use App\Models\User;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use App\Services\Consentimiento\Consentimientos;
use App\Services\Consentimiento\ConsentimientosCargador;
use App\Services\Entrevista\ChecklistDocumentos;
use App\Services\Entrevista\DocumentosEntrevista;
use App\Services\Entrevista\RespuestaData;
use App\Services\Entrevista\RespuestaGuardador;
use App\Services\Lectura\DatosDocumento;
use App\Services\Lectura\EsquemaLectura;
use App\Services\Lectura\LectorDocumentos;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * F6 6.3a: los documentos de montos de campos fijos (recibo de cuidado,
 * comprobantes de pagos, útiles del educador, horas y propinas, préstamo del
 * vehículo y las casillas fijas de los registros del negocio) se leen con el
 * lector de F4 sin cambios de código. OpenAI se simula; los fixtures de
 * `tests/Fixtures/lectura/montos/` son sintéticos.
 */
class LecturaMontosTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://api.openai.com/v1/chat/completions';

    private User $cliente;

    private Entrevista $entrevista;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Storage::fake(Documento::disco());
        config([
            'tax.lectura_automatica' => true,
            'services.openai.api_key' => 'clave-de-prueba',
            'services.openai.vision_model' => 'modelo-de-vision',
            'services.openai.retry_backoff_ms' => 0,
            'inertia.testing.ensure_pages_exist' => false,
        ]);
        $this->seed(CatalogoFiscalSeeder::class);
        app(ConsentimientosCargador::class)->cargar();

        $this->cliente = User::factory()->create(['role' => UserRole::Client, 'es_prueba' => true]);
        app(Consentimientos::class)->firmar($this->cliente, (int) config('tax.current_tax_year'), 1, 'Cliente', null, null);
        $this->entrevista = app(RespuestaGuardador::class)->entrevistaDe($this->cliente, CanalRespuesta::Portal);

        // Cada respuesta activa el documento de montos que se prueba aquí.
        foreach ([['B1', 'casado'], ['E6', 'si'], ['E8', 'si'], ['D15', 'si'], ['G1', 'si']] as [$pregunta, $respuesta]) {
            app(RespuestaGuardador::class)->guardar($this->entrevista, new RespuestaData($pregunta, $respuesta), CanalRespuesta::Portal, $this->cliente);
        }
    }

    private function item(string $codigo): EntrevistaDocumento
    {
        return app(ChecklistDocumentos::class)->primero($this->entrevista, $codigo, null) ?? $this->fail("El documento {$codigo} no está activo.");
    }

    private function fixture(string $nombre): string
    {
        return base_path("tests/Fixtures/lectura/montos/{$nombre}");
    }

    private function subirFixture(EntrevistaDocumento $item, string $nombre): Documento
    {
        return app(DocumentosEntrevista::class)->subir($item, UploadedFile::fake()->createWithContent($nombre, (string) file_get_contents($this->fixture($nombre))));
    }

    /**
     * @param  array<string, array{valor: string|null, legibilidad?: string, texto?: string|null}>  $casillas
     */
    private function fingir(string $tipo, array $casillas): void
    {
        $lectura = ['tipo_documento' => $tipo, 'anio' => 2025, 'paginas_legibles' => true, 'casillas' => collect($casillas)->map(fn (array $c) => [
            'valor' => $c['valor'],
            'legibilidad' => $c['legibilidad'] ?? 'claro',
            'pagina' => $c['valor'] === null ? null : 1,
            'texto_cercano' => $c['texto'] ?? null,
        ])->all()];

        // Un fake nuevo no pisa al anterior (gana el primero): se parte de un cliente limpio.
        Http::swap(new HttpFactory);
        Http::fake([self::URL => Http::response(['choices' => [['finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => json_encode($lectura)]]]])]);
    }

    private function leer(Documento $archivo, string $codigo): DocumentoLectura
    {
        $lectura = DocumentoLectura::query()->create([
            'documento_id' => $archivo->id,
            'entrevista_documento_id' => $archivo->entrevista_documento_id,
            'lector' => 'openai',
            'estado' => EstadoLectura::EnCola,
        ]);
        app(DatosDocumento::class)->guardarLectura($lectura, app(LectorDocumentos::class)->leer($archivo, $lectura, $codigo, 2025));

        return $lectura->fresh();
    }

    // ── Las casillas del catálogo ──

    /**
     * @return array<string, array<string, array{string, bool, bool}>> documento → casilla → [tipo, clave, sensible]
     */
    public static function casillasEsperadas(): array
    {
        return [
            'recibo-cuidado' => [
                'proveedor-nombre' => ['nombre', false, true], 'proveedor-direccion' => ['texto', false, true], 'proveedor-tin' => ['tin', false, true],
                'monto-pagado' => ['monto', true, false], 'periodo-desde' => ['fecha', false, false], 'periodo-hasta' => ['fecha', false, false],
                'persona-cuidada' => ['nombre', false, true],
            ],
            'comprobantes-pagos' => [
                'fecha-pago' => ['fecha', true, false], 'monto' => ['monto', true, false], 'tipo-pago' => ['codigo', false, false], 'anio-aplicado' => ['anio', false, false],
            ],
            'recibos-utiles-educador' => [
                'comercio' => ['nombre', false, false], 'fecha' => ['fecha', false, false], 'total' => ['monto', true, false], 'descripcion' => ['texto', false, false],
            ],
            'constancia-horas-propinas' => [
                'horas-extra-calificadas' => ['monto', true, false], 'propinas-calificadas' => ['monto', true, false], 'ocupacion' => ['texto', false, false],
            ],
            'estado-prestamo-vehiculo' => [
                'intereses-pagados' => ['monto', true, false], 'vin' => ['texto', false, true], 'fecha-prestamo' => ['fecha', false, false], 'vehiculo-nuevo' => ['si_no', false, false],
            ],
            'registros-ingresos-gastos' => [
                'clase-detectada' => ['codigo', false, false], 'periodo-desde' => ['fecha', false, false], 'periodo-hasta' => ['fecha', false, false],
                'total-depositos' => ['monto', false, false], 'total-retiros' => ['monto', false, false], 'saldo-inicial' => ['monto', false, false], 'saldo-final' => ['monto', false, false],
                'millas-negocio' => ['monto', true, false], 'pies-cuadrados-casa' => ['monto', true, false], 'pies-cuadrados-oficina' => ['monto', true, false],
            ],
        ];
    }

    public function test_cada_documento_de_montos_trae_sus_casillas_con_tipo_clave_y_sensible_en_2025_y_2026(): void
    {
        foreach ([2025, 2026] as $anio) {
            foreach (self::casillasEsperadas() as $documento => $esperadas) {
                $casillas = collect(app(EsquemaLectura::class)->casillas($documento, $anio))->keyBy('casilla');

                $this->assertSame(array_keys($esperadas), $casillas->keys()->all(), "{$anio} {$documento}: casillas y orden.");

                foreach ($esperadas as $casilla => [$tipo, $clave, $sensible]) {
                    $this->assertSame($tipo, $casillas[$casilla]['tipo_dato'], "{$anio} {$documento}:{$casilla} tipo.");
                    $this->assertSame($clave, $casillas[$casilla]['clave'], "{$anio} {$documento}:{$casilla} clave.");
                    $this->assertSame($sensible, $casillas[$casilla]['sensible'], "{$anio} {$documento}:{$casilla} sensible.");
                }
            }
        }
    }

    public function test_los_formatos_xlsx_y_csv_son_solo_de_los_registros_del_negocio(): void
    {
        $formatos = app(CatalogoFiscal::class)->documentos(2025)->mapWithKeys(fn (array $d) => [$d['codigo'] => $d['formatos'] ?? []]);

        $this->assertSame(['pdf', 'jpg', 'png', 'heic', 'xlsx', 'csv'], $formatos['registros-ingresos-gastos']);

        foreach ($formatos as $codigo => $lista) {
            if ($codigo !== 'registros-ingresos-gastos') {
                $this->assertEmpty(array_intersect(['xlsx', 'csv'], $lista), "{$codigo} no debe aceptar hojas de cálculo.");
            }
        }

        foreach (['recibo-cuidado', 'comprobantes-pagos', 'recibos-utiles-educador', 'constancia-horas-propinas', 'estado-prestamo-vehiculo'] as $codigo) {
            $this->assertSame(['pdf', 'jpg', 'png', 'heic'], $formatos[$codigo], $codigo);
        }
    }

    // ── El portal ──

    public function test_el_portal_acepta_xlsx_y_csv_solo_en_los_registros_del_negocio(): void
    {
        $registros = $this->item('registros-ingresos-gastos');
        $recibo = $this->item('recibo-cuidado');
        $ruta = fn (EntrevistaDocumento $i) => route('portal.entrevista.documentos.archivos.store', ['item' => $i->id]);

        foreach (['hoja.xlsx', 'hoja.csv'] as $nombre) {
            $this->actingAs($this->cliente)->post($ruta($registros), ['archivos' => [UploadedFile::fake()->create($nombre, 10)]])
                ->assertSessionHasNoErrors();
        }
        $this->assertSame(2, Documento::query()->where('entrevista_documento_id', $registros->id)->count());

        foreach (['hoja.xlsx', 'hoja.csv'] as $nombre) {
            $this->actingAs($this->cliente)->post($ruta($recibo), ['archivos' => [UploadedFile::fake()->create($nombre, 10)]])
                ->assertSessionHasErrors('archivos.0');
        }
        $this->assertSame(0, Documento::query()->where('entrevista_documento_id', $recibo->id)->count());
    }

    // ── Lectura simulada ──

    public function test_el_recibo_de_guarderia_se_lee_con_el_tin_cifrado_enmascarado_y_el_monto_en_el_texto_del_pdf(): void
    {
        $item = $this->item('recibo-cuidado');
        $archivo = $this->subirFixture($item, 'recibo-guarderia.pdf');
        $this->fingir('recibo-cuidado', [
            'proveedor-nombre' => ['valor' => 'Little Sprouts Daycare', 'texto' => 'Provider'],
            'proveedor-direccion' => ['valor' => '100 Example Street, Orlando, FL 32801', 'texto' => 'Address'],
            'proveedor-tin' => ['valor' => '12-3456789', 'texto' => 'Provider EIN'],
            'monto-pagado' => ['valor' => '6400.00', 'texto' => 'Amount paid'],
            'periodo-desde' => ['valor' => '2025-01-01', 'texto' => 'Period'],
            'periodo-hasta' => ['valor' => '2025-12-31', 'texto' => 'Period'],
            'persona-cuidada' => ['valor' => 'Sample Child', 'texto' => 'Child'],
        ]);

        $lectura = $this->leer($archivo, 'recibo-cuidado');

        $this->assertSame(EstadoLectura::Leida, $lectura->estado);
        $datos = $lectura->datos->keyBy('casilla');
        $this->assertCount(7, $datos);
        $this->assertSame('6400.00', $datos['monto-pagado']->valor);
        $this->assertSame(0.99, $datos['monto-pagado']->confianza);
        $this->assertSame(MotivoConfianza::EnTextoDelPdf, $datos['monto-pagado']->motivo_confianza);
        $this->assertSame('12-3456789', $datos['proveedor-tin']->valor);

        // En la base el TIN va cifrado, no en claro.
        $this->assertStringNotContainsString('3456789', (string) DB::table('documento_datos')->where('casilla', 'proveedor-tin')->value('valor'));

        // En las props del visor, el TIN y lo sensible no salen.
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $this->cliente->update(['preparer_id' => $preparador->id]);
        $respuesta = $this->actingAs($preparador)->get(route('clientes.documentos.visor', [$this->cliente, $item]));
        $respuesta->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('datos', 7)
            ->where('datos', fn ($datos) => collect($datos)->where('sensible', true)->every(fn ($d) => $d['valor'] === null && $d['tiene_valor'] === true)
                && collect($datos)->where('sensible', true)->count() === 4)
            ->etc());
        foreach (['3456789', 'Sprouts', 'Sample Child', 'Example Street'] as $secreto) {
            $this->assertStringNotContainsString($secreto, $respuesta->getContent());
        }
    }

    public function test_un_recibo_sin_tin_deja_proveedor_tin_vacio_y_con_confianza_cero(): void
    {
        // Es lo que más adelante abrirá R-12 (6.6): el proveedor sin SSN o EIN.
        $archivo = $this->subirFixture($this->item('recibo-cuidado'), 'recibo-guarderia.pdf');
        $this->fingir('recibo-cuidado', [
            'proveedor-nombre' => ['valor' => 'Little Sprouts Daycare'],
            'proveedor-tin' => ['valor' => null, 'legibilidad' => 'no_se_ve'],
            'monto-pagado' => ['valor' => '6400.00', 'texto' => 'Amount paid'],
        ]);

        $datos = $this->leer($archivo, 'recibo-cuidado')->datos->keyBy('casilla');

        $this->assertNull($datos['proveedor-tin']->valor);
        $this->assertSame(0.0, $datos['proveedor-tin']->confianza);
        $this->assertSame(Legibilidad::NoSeVe, $datos['proveedor-tin']->legibilidad);
    }

    public function test_el_recibo_de_utiles_se_lee_con_su_total_y_el_sentido_de_cada_casilla(): void
    {
        $archivo = $this->subirFixture($this->item('recibos-utiles-educador'), 'recibo-util-escolar.pdf');
        $this->fingir('recibos-utiles-educador', [
            'comercio' => ['valor' => 'Papeleria Ejemplo', 'texto' => 'Store'],
            'fecha' => ['valor' => '2025-08-20', 'texto' => 'Date'],
            'total' => ['valor' => '79.75', 'texto' => 'Total'],
            'descripcion' => ['valor' => 'Notebooks and markers; classroom books', 'texto' => 'Notebooks'],
        ]);

        $datos = $this->leer($archivo, 'recibos-utiles-educador')->datos->keyBy('casilla');

        $this->assertSame('79.75', $datos['total']->valor);
        $this->assertSame(MotivoConfianza::EnTextoDelPdf, $datos['total']->motivo_confianza);
        $this->assertSame('2025-08-20', $datos['fecha']->valor);
        $this->assertCount(4, $datos);
    }

    public function test_los_comprobantes_de_pagos_se_leen_uno_por_archivo(): void
    {
        $item = $this->item('comprobantes-pagos');
        $esperado = [
            'comprobante-1040es.pdf' => ['2025-06-16', '1500.00', 'estimado'],
            'comprobante-direct-pay.pdf' => ['2026-04-14', '800.00', 'extension'],
        ];

        foreach ($esperado as $nombre => [$fecha, $monto, $tipo]) {
            $this->fingir('comprobantes-pagos', [
                'fecha-pago' => ['valor' => $fecha, 'texto' => 'Payment date'],
                'monto' => ['valor' => $monto, 'texto' => 'Amount'],
                'tipo-pago' => ['valor' => $tipo],
                'anio-aplicado' => ['valor' => '2025', 'texto' => 'tax year'],
            ]);

            $datos = $this->leer($this->subirFixture($item, $nombre), 'comprobantes-pagos')->datos->keyBy('casilla');

            $this->assertSame($monto, $datos['monto']->valor, $nombre);
            $this->assertSame($fecha, $datos['fecha-pago']->valor, $nombre);
            $this->assertSame($tipo, $datos['tipo-pago']->valor, $nombre);
            $this->assertSame('2025', $datos['anio-aplicado']->valor, $nombre);
        }

        $this->assertSame(2, DocumentoLectura::query()->where('entrevista_documento_id', $item->id)->count());
    }
}
