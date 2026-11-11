<?php

namespace Tests\Feature\Lectura;

use App\Enums\Entrevista\EstadoDocumento;
use App\Enums\Lectura\ClaseDetectada;
use App\Enums\Lectura\EstadoLectura;
use App\Enums\Lectura\Legibilidad;
use App\Enums\Lectura\MotivoConfianza;
use App\Enums\Lectura\OrigenCategoria;
use App\Enums\Lectura\SentidoRenglon;
use App\Jobs\LeerDocumentoJob;
use App\Models\Documento;
use App\Models\Lectura\DocumentoLectura;
use App\Models\Lectura\DocumentoRenglon;
use App\Models\Lectura\Excepcion;
use App\Models\ParametroFiscal;
use App\Services\Lectura\CalculadoraConfianza;
use App\Services\Lectura\EsquemaLectura;
use App\Services\Lectura\ExplicadorExcepcion;
use App\Services\Lectura\LectorOpenAi;
use App\Services\Lectura\ResultadoSumas;
use App\Support\ParametrosFiscales;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * F6 6.3c: los PDF y las fotos de los registros del negocio (extractos,
 * recibos sueltos) se leen con IA en modo lista, por bloques de páginas, con
 * la confianza de cada renglón calculada por la plataforma, el límite de
 * páginas por archivo y el tope de páginas con IA por cliente. OpenAI siempre
 * se simula: las respuestas están en `tests/Fixtures/lectura/montos/respuestas/`
 * (una por bloque de páginas) y los documentos son sintéticos.
 */
class LecturaRenglonesIaTest extends RegistrosTestCase
{
    private const URL = 'https://api.openai.com/v1/chat/completions';

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('es');
        config(['services.openai.retry_backoff_ms' => 0]);
    }

    /**
     * Simula a OpenAI con las respuestas guardadas de un fixture, una por bloque y en orden.
     */
    private function fingir(string $respuestas): void
    {
        $bloques = json_decode((string) file_get_contents($this->fixture("respuestas/{$respuestas}")), true, 512, JSON_THROW_ON_ERROR);
        $secuencia = Http::sequence();

        foreach ($bloques as $bloque) {
            $secuencia->push([
                'choices' => [['finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => json_encode($bloque)]]],
                'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 200],
            ]);
        }

        // Un fake nuevo no pisa al anterior (gana el primero): se parte de un cliente limpio.
        Http::swap(new HttpFactory(app('events')));
        Http::fake([self::URL => $secuencia]);
    }

    private function sinOpenAi(): void
    {
        Http::swap(new HttpFactory(app('events')));
        Http::fake();
    }

    private function leerConIa(string $archivo, string $respuestas): DocumentoLectura
    {
        $this->fingir($respuestas);

        return $this->leer($this->subir($archivo));
    }

    private function parametro(string $clave, int $valor): void
    {
        ParametroFiscal::query()->where(['tax_year' => 2025, 'categoria' => 'lectura', 'clave' => $clave])->firstOrFail()->update(['valor' => $valor]);
        ParametrosFiscales::invalidate();
    }

    /**
     * @return array<string, mixed>
     */
    private function cuerpo(Request $request): array
    {
        return (array) $request->data();
    }

    // ── Esquema en modo lista ──

    public function test_el_esquema_de_los_registros_pide_renglones_con_las_lineas_del_schedule_c_del_anio_y_no_se(): void
    {
        $esquemas = app(EsquemaLectura::class);
        $esquema = $esquemas->para('registros-ingresos-gastos', 2025);

        $this->assertSame(['tipo_documento', 'anio', 'paginas_legibles', 'casillas', 'clase', 'renglones'], $esquema['required']);
        $this->assertFalse($esquema['additionalProperties']);

        $renglon = $esquema['properties']['renglones']['items'];
        $this->assertSame('array', $esquema['properties']['renglones']['type']);
        $this->assertSame(['pagina', 'fecha', 'descripcion', 'monto', 'sentido', 'categoria', 'categoria_impresa', 'legibilidad', 'evidencia'], $renglon['required']);
        $this->assertFalse($renglon['additionalProperties']);
        $this->assertSame([...ResultadoSumas::LINEAS, 'no_se'], $renglon['properties']['categoria']['enum']);
        $this->assertSame(['ingreso', 'gasto', 'transferencia', 'personal', 'no_se'], $renglon['properties']['sentido']['enum']);
        // Los textos de las opciones son los del Schedule C de 2025 (f1040sc.pdf, p. 1).
        $this->assertStringContainsString('18 = Line 18, office expense', $renglon['properties']['categoria']['description']);
        $this->assertStringContainsString('27b = Line 27b, other expenses', $renglon['properties']['categoria']['description']);
        $this->assertNotContains('hoja_calculo', $esquema['properties']['clase']['enum']);

        // Las casillas fijas del documento siguen en el esquema.
        $this->assertArrayHasKey('total-depositos', $esquema['properties']['casillas']['properties']);

        $instrucciones = $esquemas->instrucciones('registros-ingresos-gastos', 2025, 6, 10, 12);
        $this->assertStringContainsString('Do not invent movements', $instrucciones);
        $this->assertStringContainsString('If you are not sure of the category, use "no_se"', $instrucciones);
        $this->assertStringContainsString('"transferencia" for a movement between the person\'s own accounts', $instrucciones);
        $this->assertStringContainsString('pages 6 to 10 of a file with 12 pages', $instrucciones);

        // 2026 ofrece las líneas de su propio catálogo.
        $this->assertNotSame([], $esquemas->categorias(2026));
    }

    public function test_los_documentos_de_casillas_no_cambian_de_esquema(): void
    {
        $esquema = app(EsquemaLectura::class)->para('w2', 2025);

        $this->assertSame(['tipo_documento', 'anio', 'paginas_legibles', 'casillas'], $esquema['required']);
        $this->assertStringNotContainsString('renglones', app(EsquemaLectura::class)->instrucciones('w2', 2025));
    }

    // ── Renglones y su confianza ──

    public function test_un_extracto_con_texto_da_099_al_monto_unico_y_095_a_los_dos_iguales_de_la_misma_pagina(): void
    {
        $lectura = $this->leerConIa('extracto-banco-texto.pdf', 'extracto-banco-texto.json');

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => count($this->cuerpo($r)['messages'][1]['content']) === 4
            && str_contains($this->cuerpo($r)['messages'][0]['content'], 'pages 1 to 3 of a file with 3 pages'));

        $this->assertSame(LectorOpenAi::NOMBRE, $lectura->lector);
        $this->assertSame('modelo-de-vision', $lectura->modelo);
        $this->assertSame(EstadoLectura::Leida, $lectura->estado);
        $this->assertSame(3, $lectura->paginas);
        $this->assertSame(ClaseDetectada::ExtractoBanco, $lectura->clase_detectada);
        $this->assertSame('2025-03-01', $lectura->periodo_desde?->format('Y-m-d'));
        $this->assertSame('2025-03-31', $lectura->periodo_hasta?->format('Y-m-d'));

        $renglones = $lectura->renglones()->orderBy('numero')->get();
        $this->assertSame(range(1, 10), $renglones->pluck('numero')->all());
        $this->assertSame([1, 1, 1, 1, 2, 2, 2, 2, 3, 3], $renglones->pluck('pagina')->all());

        // El depósito de 1,200.00 es único en la página 1 y está en su texto.
        $deposito = $renglones[0];
        $this->assertSame('1200.00', $deposito->monto);
        $this->assertSame(0.99, $deposito->confianza);
        $this->assertSame(MotivoConfianza::EnTextoDelPdf, $deposito->motivo_confianza);
        $this->assertSame(SentidoRenglon::Ingreso, $deposito->sentido);
        $this->assertSame('1-sin-formulario', $deposito->categoria);
        $this->assertSame(OrigenCategoria::Ia, $deposito->categoria_origen);
        $this->assertNotNull($deposito->recuadro);

        // Un retiro impreso con signo ("-85.40") también se encuentra en el texto.
        $this->assertSame(0.99, $renglones[1]->confianza);

        // Dos movimientos de 45.00 en la misma página: no se puede comprobar cuál es cuál.
        foreach ([$renglones[2], $renglones[3]] as $igual) {
            $this->assertSame('45.00', $igual->monto);
            $this->assertSame(0.95, $igual->confianza);
            $this->assertSame(MotivoConfianza::RepetidoEnPagina, $igual->motivo_confianza);
        }

        // Si el ubicador no los distingue (aquí las columnas del extracto son textos separados), ninguno
        // lleva un recuadro equivocado: la página sí.
        $this->assertTrue($renglones[2]->recuadro === null || $renglones[2]->recuadro !== $renglones[3]->recuadro);
        $this->assertSame([1, 1], [$renglones[2]->pagina, $renglones[3]->pagina]);

        // Una transferencia entre cuentas propias no tiene categoría y no es "sin categoría".
        $transferencia = $renglones[5];
        $this->assertSame(SentidoRenglon::Transferencia, $transferencia->sentido);
        $this->assertNull($transferencia->categoria);
        $this->assertNull($transferencia->categoria_origen);
        $this->assertNull(app(CalculadoraConfianza::class)->evaluarCategoria($transferencia->categoria, $transferencia->categoria_origen, $transferencia->sentido));

        // El Zelle (ingreso sin categoría) abre R-14 por el archivo; el PDF ya no es "archivo sin lector".
        $this->assertNull($renglones[4]->categoria);
        $r14 = Excepcion::query()->where('regla_codigo', 'R-14')->get();
        $this->assertSame(['sin_categoria'], $r14->pluck('detalle.tipo')->all());
        $this->assertSame(1, $r14->first()->detalle['renglones']);

        // Las casillas fijas del extracto (totales impresos) se leen como siempre.
        $total = $lectura->datos()->where('casilla', 'total-depositos')->sole();
        $this->assertSame('4350.00', $total->valor);
        $this->assertSame(0.99, $total->confianza);
    }

    public function test_un_extracto_escaneado_no_pasa_de_090_y_lo_dudoso_queda_en_060(): void
    {
        $lectura = $this->leerConIa('extracto-banco-escaneado.pdf', 'extracto-banco-escaneado.json');

        $renglones = $lectura->renglones()->orderBy('numero')->get();
        $this->assertCount(10, $renglones);

        foreach ($renglones as $renglon) {
            $this->assertLessThanOrEqual(0.90, $renglon->confianza);
            $this->assertNull($renglon->recuadro);
        }

        $this->assertSame(MotivoConfianza::Claro, $renglones[2]->motivo_confianza, 'Sin texto del PDF no hay "repetido en la página".');
        $this->assertSame(0.60, $renglones[9]->confianza);
        $this->assertSame(MotivoConfianza::Dudoso, $renglones[9]->motivo_confianza);
    }

    public function test_un_recibo_con_la_categoria_impresa_cuenta_como_del_documento_y_una_foto_sin_ella_como_de_la_ia(): void
    {
        $oficina = $this->leerConIa('recibo-suelto-oficina.pdf', 'recibo-suelto-oficina.json');
        $renglon = $oficina->renglones()->sole();

        $this->assertSame(ClaseDetectada::Recibo, $oficina->clase_detectada);
        $this->assertSame('63.25', $renglon->monto);
        $this->assertSame('18', $renglon->categoria);
        $this->assertSame(OrigenCategoria::Documento, $renglon->categoria_origen);
        $this->assertSame(0.99, $renglon->confianza);
        $this->assertNull(app(CalculadoraConfianza::class)->evaluarCategoria($renglon->categoria, $renglon->categoria_origen, $renglon->sentido));

        $foto = $this->leerConIa('recibo-suelto-restaurante.jpg', 'recibo-suelto-restaurante.json');
        $comida = $foto->renglones()->sole();

        $this->assertSame('24b', $comida->categoria);
        $this->assertSame(OrigenCategoria::Ia, $comida->categoria_origen);
        $this->assertSame(0.90, $comida->confianza);
        $this->assertSame(MotivoConfianza::Claro, $comida->motivo_confianza);
        $this->assertSame(
            ['confianza' => 0.60, 'motivo' => MotivoConfianza::CategoriaIa],
            app(CalculadoraConfianza::class)->evaluarCategoria($comida->categoria, $comida->categoria_origen, $comida->sentido),
        );

        $this->assertSame(0, Excepcion::query()->where('regla_codigo', 'R-14')->count());
    }

    public function test_un_archivo_largo_se_lee_en_bloques_de_paginas_y_los_renglones_quedan_en_orden(): void
    {
        $lectura = $this->leerConIa('extracto-tarjeta.pdf', 'extracto-tarjeta.json');

        Http::assertSentCount(2);
        $pedidos = collect(Http::recorded())->map(fn (array $par) => $this->cuerpo($par[0]));
        $this->assertCount(6, $pedidos[0]['messages'][1]['content'], 'Primer bloque: el texto y 5 páginas.');
        $this->assertCount(2, $pedidos[1]['messages'][1]['content'], 'Segundo bloque: el texto y 1 página.');
        $this->assertStringContainsString('pages 1 to 5 of a file with 6 pages', $pedidos[0]['messages'][0]['content']);
        $this->assertStringContainsString('pages 6 to 6 of a file with 6 pages', $pedidos[1]['messages'][0]['content']);

        $renglones = $lectura->renglones()->orderBy('numero')->get();
        $this->assertSame(range(1, 12), $renglones->pluck('numero')->all());
        $this->assertSame(['120.00', '33.10', '18.75', '15.99', '310.40', '189.00', '72.30', '24.60', '96.45', '27.80', '500.00', '39.99'], $renglones->pluck('monto')->all());
        // El segundo bloque contestó la página como posición de la imagen (1): es la 6 del archivo.
        $this->assertSame([6, 6], $renglones->slice(10)->pluck('pagina')->values()->all());
        $this->assertSame(0.99, $renglones[11]->confianza);
        $this->assertSame(ClaseDetectada::ExtractoTarjeta, $lectura->clase_detectada);
        $this->assertSame('948.38', $lectura->datos()->where('casilla', 'saldo-final')->sole()->valor);
    }

    // ── Límites: páginas por archivo y tope por cliente ──

    public function test_los_limites_son_parametros_del_ano_y_2026_los_hereda(): void
    {
        foreach ([2025, 2026] as $anio) {
            $this->assertSame(40, ParametrosFiscales::valorRequerido($anio, 'lectura', 'maximo_paginas_renglones'), "{$anio}: páginas por archivo.");
            $this->assertSame(120, ParametrosFiscales::valorRequerido($anio, 'lectura', 'maximo_paginas_ia_por_cliente'), "{$anio}: tope por cliente.");
        }

        $parametro = ParametroFiscal::query()->where(['tax_year' => 2025, 'categoria' => 'lectura', 'clave' => 'maximo_paginas_ia_por_cliente'])->sole();
        $this->assertSame('Proceso: decisión de Steven (9 y 11 nov. 2026)', $parametro->fuente_texto);
    }

    public function test_un_archivo_con_mas_paginas_que_el_limite_no_va_a_openai_queda_no_se_pudo_leer_y_abre_r14(): void
    {
        // El límite real es 40; se baja a 2 para no guardar un extracto de 41 páginas en el repositorio.
        $this->parametro('maximo_paginas_renglones', 2);
        $this->sinOpenAi();
        $archivo = $this->subir('extracto-banco-texto.pdf');

        // Por el job, como en la plataforma: sin error.
        app()->call([new LeerDocumentoJob($archivo->id), 'handle']);

        Http::assertNothingSent();
        $lectura = DocumentoLectura::query()->where('documento_id', $archivo->id)->sole();
        $this->assertSame(EstadoLectura::Leida, $lectura->estado);
        $this->assertSame(['sin_leer' => 'demasiadas_paginas', 'maximo' => 2], $lectura->observaciones);
        $this->assertSame(0, $lectura->renglones()->count());
        $this->assertSame(EstadoDocumento::NoSePudoLeer, $this->itemRegistros()->fresh()->estado);

        $r14 = Excepcion::query()->where('regla_codigo', 'R-14')->with('entrevista')->sole();
        $this->assertSame('demasiadas_paginas', $r14->detalle['tipo']);
        $texto = app(ExplicadorExcepcion::class)->explicar($r14);
        $this->assertStringContainsString('tiene más de 2 páginas', $texto['caso']);
        $this->assertStringContainsString('Súbelo por meses', (string) $texto['que_hacer']);

        app()->setLocale('en');
        $this->assertStringContainsString('month by month', (string) app(ExplicadorExcepcion::class)->explicar($r14)['que_hacer']);
    }

    public function test_el_cliente_que_llega_al_tope_de_paginas_con_ia_no_manda_el_archivo_siguiente_a_openai(): void
    {
        $this->parametro('maximo_paginas_ia_por_cliente', 4);

        $primera = $this->leerConIa('extracto-banco-texto.pdf', 'extracto-banco-texto.json');
        $this->assertSame(3, app(LectorOpenAi::class)->paginasLeidasConIa($primera, 2025) + $primera->paginas);

        // Las hojas no usan IA y no cuentan.
        $this->sinOpenAi();
        $this->subirYLeer('hoja-propia.csv');

        // 3 páginas leídas + 3 del escaneado pasan del tope de 4: no sale nada.
        $this->sinOpenAi();
        $archivo = $this->subir('extracto-banco-escaneado.pdf');
        app()->call([new LeerDocumentoJob($archivo->id), 'handle']);

        Http::assertNothingSent();
        $lectura = DocumentoLectura::query()->where('documento_id', $archivo->id)->sole();
        $this->assertSame(['sin_leer' => 'tope_alcanzado', 'paginas' => 3, 'usadas' => 3, 'tope' => 4], $lectura->observaciones);
        $this->assertSame(0, $lectura->renglones()->count());

        $r14 = Excepcion::query()->where('regla_codigo', 'R-14')->with('entrevista')->get()->firstWhere('detalle.tipo', 'tope_alcanzado');
        $this->assertNotNull($r14);
        $this->assertSame($archivo->id, $r14->detalle['documento_id']);
        $this->assertStringContainsString('ya lleva 3 de 4 páginas leídas con IA', app(ExplicadorExcepcion::class)->explicar($r14)['caso']);

        // Lo que no se leyó no cuenta para el tope.
        $this->assertSame(3, app(LectorOpenAi::class)->paginasLeidasConIa($lectura, 2025));
    }

    // ── Lo que no cambia y lo que no se escribe ──

    public function test_un_pdf_de_registros_con_la_lectura_automatica_apagada_sigue_como_archivo_sin_lector(): void
    {
        config(['tax.lectura_automatica' => false]);
        $this->sinOpenAi();
        $archivo = $this->subir('extracto-banco-texto.pdf');

        $this->leer($archivo);

        Http::assertNothingSent();
        $this->assertSame('archivo_sin_lector', Excepcion::query()->where('regla_codigo', 'R-14')->sole()->detalle['tipo']);
    }

    public function test_ningun_monto_ni_descripcion_leidos_salen_en_excepciones_logs_ni_en_claro_en_la_base(): void
    {
        $this->leerConIa('extracto-banco-texto.pdf', 'extracto-banco-texto.json');
        $this->parametro('maximo_paginas_ia_por_cliente', 4);
        $this->sinOpenAi();
        app()->call([new LeerDocumentoJob($this->subir('extracto-tarjeta.pdf')->id), 'handle']);

        $excepciones = Excepcion::query()->with('entrevista')->get();
        $detalle = json_encode($excepciones->pluck('detalle')->all());
        $explicado = json_encode($excepciones->map(fn (Excepcion $e) => app(ExplicadorExcepcion::class)->explicar($e))->all(), JSON_UNESCAPED_UNICODE);
        $crudo = json_encode([
            DB::table('documento_renglones')->get()->all(),
            DB::table('documento_lecturas')->get()->all(),
        ]);

        foreach (['ACME', 'STRIPE', 'Zelle', 'SHELL', '1200.00', '2500.00', '570.12', '650.00'] as $secreto) {
            $this->assertStringNotContainsString($secreto, (string) $detalle, "excepciones.detalle no debe llevar «{$secreto}».");
            $this->assertStringNotContainsString($secreto, (string) $explicado, "La explicación no debe llevar «{$secreto}».");
            $this->assertStringNotContainsString($secreto, implode("\n", $this->registrosDeLog), "Los logs no deben llevar «{$secreto}».");
            $this->assertStringNotContainsString($secreto, (string) $crudo, "La base no debe guardar «{$secreto}» en claro.");
        }

        $this->assertGreaterThan(0, DocumentoRenglon::query()->count());
    }

    // ── La confianza de un renglón ──

    public function test_la_confianza_del_monto_y_la_de_la_categoria_salen_de_la_tabla(): void
    {
        $calculadora = app(CalculadoraConfianza::class);

        $this->assertSame(['confianza' => 0.99, 'motivo' => MotivoConfianza::EnTextoDelPdf], $calculadora->evaluarRenglon('45.00', Legibilidad::Claro, true, false));
        $this->assertSame(['confianza' => 0.95, 'motivo' => MotivoConfianza::RepetidoEnPagina], $calculadora->evaluarRenglon('45.00', Legibilidad::Claro, true, true));
        $this->assertSame(['confianza' => 0.90, 'motivo' => MotivoConfianza::Claro], $calculadora->evaluarRenglon('45.00', Legibilidad::Claro, false, true));
        $this->assertSame(['confianza' => 0.60, 'motivo' => MotivoConfianza::FormatoIncorrecto], $calculadora->evaluarRenglon('4.5.0', Legibilidad::Claro, false, false));
        $this->assertSame(['confianza' => 0.0, 'motivo' => MotivoConfianza::SinValor], $calculadora->evaluarRenglon(null, Legibilidad::Claro, false, false));

        $this->assertSame(['confianza' => 0.60, 'motivo' => MotivoConfianza::SinCategoria], $calculadora->evaluarCategoria(null, null, SentidoRenglon::Gasto));
        $this->assertSame(['confianza' => 0.60, 'motivo' => MotivoConfianza::CategoriaIa], $calculadora->evaluarCategoria('18', OrigenCategoria::Ia, SentidoRenglon::Gasto));
        $this->assertNull($calculadora->evaluarCategoria('18', OrigenCategoria::Documento, SentidoRenglon::Gasto));
        $this->assertNull($calculadora->evaluarCategoria(null, null, SentidoRenglon::Personal));

        $tabla = collect(CalculadoraConfianza::tabla())->keyBy('motivo');
        $this->assertSame(0.95, $tabla['repetido_en_pagina']['confianza']);
        $this->assertSame(0.60, $tabla['categoria_ia']['confianza']);
        $this->assertSame(0.60, $tabla['sin_categoria']['confianza']);

        foreach (['repetido_en_pagina', 'categoria_ia', 'sin_categoria'] as $motivo) {
            $this->assertGreaterThan(40, mb_strlen($tabla[$motivo]['explicacion']));
        }

        // La guía para el preparador explica los mismos motivos.
        $guia = (string) file_get_contents(base_path('docs/mejora-plataforma/guia-confianza-de-lectura.md'));

        foreach (['repetido_en_pagina', 'categoria_ia', 'sin_categoria'] as $motivo) {
            $this->assertStringContainsString($tabla[$motivo]['titulo'], $guia);
        }

        $this->assertStringContainsString('**0,95**', $guia);
    }

    // ── Comando de medición ──

    public function test_el_comando_de_medicion_se_niega_en_produccion_y_sin_la_clave(): void
    {
        $this->sinOpenAi();
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('lectura:medir-costo')->assertFailed();

        $this->app->detectEnvironment(fn () => 'testing');
        config(['services.openai.api_key' => '']);

        $this->artisan('lectura:medir-costo')->assertFailed();

        Http::assertNothingSent();
    }

    public function test_el_comando_de_medicion_cuenta_paginas_llamadas_tokens_y_costo_con_la_tarifa_que_se_le_pasa(): void
    {
        $this->fingir('extracto-tarjeta.json');

        $codigo = Artisan::call('lectura:medir-costo', ['--archivo' => ['extracto-tarjeta.pdf'], '--tarifa-entrada' => '2', '--tarifa-salida' => '8']);
        $salida = Artisan::output();

        $this->assertSame(0, $codigo);
        // Una fila por archivo: 6 páginas, 2 llamadas, 12 renglones, 2 × 1.000 tokens de entrada y 2 × 200 de salida.
        $this->assertMatchesRegularExpression('/extracto-tarjeta\.pdf\s*\|\s*registros-ingresos-gastos\s*\|\s*6\s*\|\s*2\s*\|\s*12\s*\|\s*2000\s*\|\s*400\s*\|/', $salida);
        // USD 2 el millón de entrada y USD 8 el de salida: 0,004 + 0,0032 = 0,0072.
        $this->assertStringContainsString('0.0072', $salida);
        $this->assertStringContainsString('Costo medio por página: USD 0.0012', $salida);
        // Solo conteos: nada de lo leído.
        $this->assertStringNotContainsString('GOOGLE', $salida);

        Http::assertSentCount(2);
    }
}
