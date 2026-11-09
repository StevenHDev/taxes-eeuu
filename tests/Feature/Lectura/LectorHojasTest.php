<?php

namespace Tests\Feature\Lectura;

use App\Enums\Lectura\ClaseDetectada;
use App\Enums\Lectura\EstadoLectura;
use App\Enums\Lectura\MotivoConfianza;
use App\Enums\Lectura\OrigenCategoria;
use App\Enums\Lectura\SentidoRenglon;
use App\Http\Requests\Portal\SubirDocumentoRequest;
use App\Models\Documento;
use App\Models\Lectura\DocumentoLectura;
use App\Models\Lectura\Excepcion;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use App\Services\Lectura\LectorDocumentos;
use App\Services\Lectura\LectorHojas;
use App\Services\Lectura\LectorManual;
use App\Services\Lectura\LectorOpenAi;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * F6 6.3b: las hojas `xlsx` y `csv` de los registros del negocio se leen sin
 * IA (LectorHojas): la plantilla de F3.7 por sus etiquetas y una hoja propia
 * por sus encabezados. Lo que no se reconoce va a R-14. Los archivos son
 * sintéticos (`tests/Fixtures/lectura/montos/`) y OpenAI nunca se llama.
 */
class LectorHojasTest extends RegistrosTestCase
{
    // ── Plantilla de F3.7 ──

    public function test_la_plantilla_en_xlsx_lleva_cada_categoria_a_su_linea_del_schedule_c_con_confianza_099_y_sin_llamar_a_openai(): void
    {
        $lectura = $this->subirYLeer('plantilla-registros-llena-en.xlsx');

        $this->assertSame(EstadoLectura::Leida, $lectura->estado);
        $this->assertSame(LectorHojas::NOMBRE, $lectura->lector);
        $this->assertSame(ClaseDetectada::Plantilla, $lectura->clase_detectada);
        $this->assertSame('registros-ingresos-gastos', $lectura->tipo_detectado);
        $this->assertSame(2025, $lectura->anio_detectado);

        // Cada línea con el monto que tiene la hoja (el cero de "Wages" no es un renglón).
        $this->assertSame([
            '1-sin-formulario' => ['52000.00'], '2' => ['1200.00'], '8' => ['450.00'], '9' => ['3100.00'], '10' => ['600.00'],
            '11' => ['2000.00'], '15' => ['900.00'], '17' => ['1100.00'], '18' => ['4800.00'], '20b' => ['750.00'],
            '21' => ['1250.00'], '22' => ['380.00'], '23' => ['2100.00'], '24a' => ['520.00'], '24b' => ['1750.00'],
            '25' => ['640.00'], '27b' => ['310.00'],
        ], $this->montosPorLinea($lectura));

        foreach ($lectura->renglones as $renglon) {
            $this->assertSame(0.99, $renglon->confianza);
            $this->assertSame(MotivoConfianza::HojaCalculo, $renglon->motivo_confianza);
            $this->assertSame(OrigenCategoria::Documento, $renglon->categoria_origen);
            $this->assertNotNull($renglon->pagina, 'La fila de la hoja queda como página.');
            $this->assertNull($renglon->recuadro);
        }

        $this->assertSame(SentidoRenglon::Ingreso, $lectura->renglones->firstWhere('categoria', '1-sin-formulario')->sentido);
        $this->assertSame(SentidoRenglon::Gasto, $lectura->renglones->firstWhere('categoria', '2')->sentido);
        $this->assertSame(SentidoRenglon::Gasto, $lectura->renglones->firstWhere('categoria', '27b')->sentido);

        // Millas y pies cuadrados van a sus casillas fijas, con el mismo motivo.
        $datos = $lectura->datos->keyBy('casilla');
        $this->assertSame('9800.00', $datos['millas-negocio']->valor);
        $this->assertSame('1850.00', $datos['pies-cuadrados-casa']->valor);
        $this->assertSame('140.00', $datos['pies-cuadrados-oficina']->valor);
        $this->assertSame('plantilla', $datos['clase-detectada']->valor);
        $this->assertSame(MotivoConfianza::HojaCalculo, $datos['millas-negocio']->motivo_confianza);

        // Las filas de total traen fórmulas sin valor guardado, pero no se leen: no son una duda.
        $this->assertNull($lectura->observaciones);
        $this->assertSame(0, Excepcion::query()->where('regla_codigo', 'R-14')->count());
    }

    public function test_la_plantilla_en_espanol_se_reconoce_por_sus_etiquetas(): void
    {
        $lectura = $this->subirYLeer('plantilla-registros-llena-es.xlsx');

        $this->assertSame(ClaseDetectada::Plantilla, $lectura->clase_detectada);
        $this->assertSame([
            '1-sin-formulario' => ['8000.00'], '2' => ['150.50'], '8' => ['120.00'], '9' => ['300.00'], '25' => ['95.25'], '27b' => ['40.00'],
        ], $this->montosPorLinea($lectura));
        $this->assertSame('1200.00', $lectura->datos->firstWhere('casilla', 'millas-negocio')->valor);
        $this->assertSame(0, Excepcion::query()->where('regla_codigo', 'R-14')->count());
    }

    public function test_una_plantilla_sin_su_titulo_se_reconoce_si_trae_cuatro_etiquetas_de_categoria(): void
    {
        // Sin el título: el cliente lo borró. Se arma desde una hoja con las etiquetas y nada más.
        $csv = "Publicidad;100\nSupplies;50\nOffice expense;25\nTravel;10\nCosa rara;5\n";
        $lectura = $this->leer($this->subir('sin-titulo.csv', $csv));

        $this->assertSame(ClaseDetectada::Plantilla, $lectura->clase_detectada);
        $this->assertSame(['8' => ['100.00'], '22' => ['50.00'], '18' => ['25.00'], '24a' => ['10.00'], 'sin' => ['5.00']], $this->montosPorLinea($lectura));
    }

    public function test_una_categoria_que_no_esta_en_el_catalogo_queda_no_se_y_una_formula_sin_valor_va_a_r14(): void
    {
        $lectura = $this->subirYLeer('plantilla-registros-problemas.xlsx');

        // "Pet grooming" no es una categoría: el renglón queda no_se, sin categoría, y su monto se conserva.
        $sinCategoria = $lectura->renglones->firstWhere('categoria', null);
        $this->assertSame(SentidoRenglon::NoSe, $sinCategoria->sentido);
        $this->assertSame('35.00', $sinCategoria->monto);
        $this->assertNull($sinCategoria->categoria_origen);

        // La fórmula de "Advertising" no trae valor guardado: no se lee y se cuenta.
        $this->assertNull($lectura->renglones->firstWhere('categoria', '8'));
        $this->assertSame(['formulas_sin_valor' => 1], $lectura->observaciones);

        $excepciones = Excepcion::query()->where('regla_codigo', 'R-14')->orderBy('clave')->get();
        $this->assertCount(2, $excepciones);
        $this->assertSame(['hoja', 'sin_categoria'], $excepciones->pluck('detalle.tipo')->sort()->values()->all());
        $this->assertSame(1, $excepciones->firstWhere('detalle.tipo', 'sin_categoria')->detalle['renglones']);
        $this->assertSame(1, $excepciones->firstWhere('detalle.tipo', 'hoja')->detalle['formulas_sin_valor']);
    }

    // ── Hoja propia ──

    public function test_una_hoja_propia_en_csv_con_encabezados_en_ingles_lee_los_renglones_y_abre_r14_por_la_columna_desconocida(): void
    {
        $lectura = $this->subirYLeer('hoja-propia.csv');

        $this->assertSame(ClaseDetectada::HojaCalculo, $lectura->clase_detectada);
        $this->assertSame(['1-sin-formulario' => ['1200.00'], '18' => ['45.20'], '8' => ['300.00'], 'sin' => ['60.00']], $this->montosPorLinea($lectura));

        // El signo del monto no cambia el monto guardado (siempre positivo); la categoría reconocida manda el sentido.
        $this->assertSame(SentidoRenglon::Ingreso, $lectura->renglones->firstWhere('categoria', '1-sin-formulario')->sentido);
        $this->assertSame(SentidoRenglon::Gasto, $lectura->renglones->firstWhere('categoria', '18')->sentido);
        $this->assertSame('2025-02-03', $lectura->renglones->first()->fecha->toDateString());
        $this->assertSame('Client payment invoice 101', $lectura->renglones->first()->descripcion);
        $this->assertSame(0.99, $lectura->renglones->first()->confianza);
        $this->assertSame(MotivoConfianza::HojaCalculo, $lectura->renglones->first()->motivo_confianza);

        // "Internal ref" es la columna E: solo la letra va al detalle. "Gifts to staff" no es categoría: no_se.
        $this->assertSame(['columnas_no_reconocidas' => ['E']], $lectura->observaciones);
        $this->assertSame('2025-02-03', $lectura->periodo_desde->toDateString());
        $this->assertSame('2025-05-02', $lectura->periodo_hasta->toDateString());

        $excepciones = Excepcion::query()->where('regla_codigo', 'R-14')->get();
        $this->assertSame(['hoja', 'sin_categoria'], $excepciones->pluck('detalle.tipo')->sort()->values()->all());
        $this->assertSame(['E'], $excepciones->firstWhere('detalle.tipo', 'hoja')->detalle['columnas_no_reconocidas']);
        // Una por archivo y por problema, nunca por renglón: 4 renglones, 2 excepciones.
        $this->assertCount(4, $lectura->renglones);
    }

    public function test_una_hoja_propia_en_xlsx_con_debito_y_credito_cuenta_lo_que_no_reconoce(): void
    {
        $lectura = $this->subirYLeer('hoja-propia-debito-credito.xlsx');

        $this->assertSame(ClaseDetectada::HojaCalculo, $lectura->clase_detectada);
        $this->assertSame([
            '1-sin-formulario' => ['900.00'], '8' => ['60.50'], 'sin' => ['25.00', '10.00'],
        ], $this->montosPorLinea($lectura));

        // La columna "Notas" no se reconoce; la fila con una fecha que no es fecha no es un renglón.
        $this->assertSame(['columnas_no_reconocidas' => ['F'], 'filas_sin_fecha' => 1], $lectura->observaciones);
        $this->assertSame('2025-01-15', $lectura->periodo_desde->toDateString());
        $this->assertSame('2025-03-05', $lectura->periodo_hasta->toDateString());
    }

    public function test_un_csv_en_latin_1_con_punto_y_coma_coma_decimal_y_parentesis_negativos_se_lee_bien(): void
    {
        $lectura = $this->subirYLeer('hoja-propia-latin1.csv');

        // 1.250,00 → 1250.00; (45,20) → 45.20 (el signo no se guarda); 300,5 → 300.50.
        $this->assertSame(['1-sin-formulario' => ['1250.00'], '18' => ['45.20'], '8' => ['300.50']], $this->montosPorLinea($lectura));
        $this->assertSame('Cobro por diseño', $lectura->renglones->first()->descripcion);
        // 03/15/2025 es mes/día; 25/03/2025 solo puede ser día/mes.
        $this->assertSame(['2025-03-15', '2025-03-18', '2025-03-25'], $lectura->renglones->map(fn ($r) => $r->fecha->toDateString())->all());
        $this->assertNull($lectura->observaciones);
    }

    public function test_un_archivo_que_no_es_ni_plantilla_ni_lista_de_movimientos_no_se_lee_y_va_a_r14(): void
    {
        $lectura = $this->leer($this->subir('notas.csv', "Hola;mundo\nesto no;es una hoja\n"));

        $this->assertSame(ClaseDetectada::Otro, $lectura->clase_detectada);
        $this->assertCount(0, $lectura->renglones);
        $this->assertSame(['formato_no_reconocido' => true], $lectura->observaciones);
        $this->assertSame(['hoja'], Excepcion::query()->where('regla_codigo', 'R-14')->get()->pluck('detalle.tipo')->all());
    }

    // ── PDF y fotos: no salen a OpenAI ──

    public function test_un_pdf_de_registros_no_sale_a_openai_se_lee_manual_y_abre_r14(): void
    {
        $archivo = $this->subir('plantilla-registros-llena-en.pdf');

        $this->assertInstanceOf(LectorOpenAi::class, app(LectorDocumentos::class));

        $lectura = $this->leer($archivo);

        Http::assertNothingSent();
        $this->assertSame(LectorManual::NOMBRE, $lectura->lector);
        $this->assertSame(EstadoLectura::Leida, $lectura->estado);
        $this->assertCount(0, $lectura->datos);
        $this->assertCount(0, $lectura->renglones);

        $r14 = Excepcion::query()->where('regla_codigo', 'R-14')->sole();
        $this->assertSame('archivo_sin_lector', $r14->detalle['tipo']);
        $this->assertSame($archivo->id, $r14->detalle['documento_id']);
        $this->assertSame(['tipo', 'documento_id'], array_keys($r14->detalle));
    }

    public function test_el_resto_de_los_documentos_sigue_igual_las_casillas_fijas_no_pasan_por_el_lector_de_hojas(): void
    {
        $documentos = app(CatalogoFiscal::class)->documentos(2025)->where('lectura', 'renglones')->pluck('codigo')->all();

        // Solo los registros del negocio se leen por renglones.
        $this->assertSame(['registros-ingresos-gastos'], $documentos);
    }

    public function test_un_xlsx_dañado_falla_sin_llevar_nada_del_archivo_en_el_mensaje(): void
    {
        $archivo = $this->subir('rota.xlsx', 'esto no es un zip con texto-secreto-del-cliente');
        $lectura = DocumentoLectura::query()->create(['documento_id' => $archivo->id, 'entrevista_documento_id' => $archivo->entrevista_documento_id, 'lector' => 'hoja', 'estado' => EstadoLectura::EnCola]);

        try {
            app(LectorHojas::class)->leer($archivo, $lectura, 'registros-ingresos-gastos', 2025);
            $this->fail('Debió fallar.');
        } catch (RuntimeException $error) {
            $this->assertStringNotContainsString('secreto', $error->getMessage());
            $this->assertStringContainsString('dañado', $error->getMessage());
        }
    }

    // ── Portal ──

    public function test_el_mensaje_de_formato_no_permitido_nombra_los_formatos_del_documento_y_no_una_lista_fija(): void
    {
        $registros = $this->itemRegistros();
        $ruta = route('portal.entrevista.documentos.archivos.store', ['item' => $registros->id]);

        $respuesta = $this->actingAs($this->cliente)->post($ruta, ['archivos' => [UploadedFile::fake()->create('programa.exe', 10)]]);
        $respuesta->assertSessionHasErrors('archivos.0');
        $this->assertStringContainsString('PDF, JPG, PNG, HEIC, XLSX', session('errors')->first('archivos.0'));
        $this->assertStringContainsString('CSV', session('errors')->first('archivos.0'));

        // Un documento con los formatos de siempre sigue diciendo lo de siempre, y la lista nueva se une según el idioma.
        $mensaje = fn (array $formatos) => SubirDocumentoRequest::mensajes($formatos)['archivos.*.extensions'];

        app()->setLocale('es');
        $this->assertSame('Formato no permitido. Usa PDF, JPG, PNG o HEIC.', $mensaje(['pdf', 'jpg', 'jpeg', 'png', 'heic']));
        $this->assertSame('Formato no permitido. Usa PDF, JPG, PNG, HEIC, XLSX o CSV.', $mensaje(['pdf', 'jpg', 'jpeg', 'png', 'heic', 'xlsx', 'csv']));

        app()->setLocale('en');
        $this->assertSame('Format not allowed. Use PDF, JPG, PNG or HEIC.', $mensaje(['pdf', 'jpg', 'png', 'heic']));
        $this->assertSame('Format not allowed. Use PDF, JPG, PNG, HEIC, XLSX or CSV.', $mensaje(['pdf', 'jpg', 'png', 'heic', 'xlsx', 'csv']));
    }

    public function test_el_texto_que_subir_presenta_la_plantilla_como_la_via_mas_sencilla_sin_presionar(): void
    {
        $documento = app(CatalogoFiscal::class)->documentos(2025)->firstWhere('codigo', 'registros-ingresos-gastos');

        $this->assertStringStartsWith('Si llevas tus cuentas, la plantilla es lo más rápido', $documento['que_subir_es']);
        $this->assertStringContainsString('Si no, sube tus extractos y recibos', $documento['que_subir_es']);
        $this->assertStringStartsWith('If you keep your own accounts, the template is the quickest way', $documento['que_subir_en']);
        $this->assertStringContainsString('If not, upload your statements and receipts', $documento['que_subir_en']);
    }

    public function test_normalizar_junta_mayusculas_tildes_parentesis_y_espacios(): void
    {
        $this->assertSame('auto y camioneta', LectorHojas::normalizar('Auto y camioneta (gasolina, mantenimiento, seguro del vehículo)'));
        $this->assertSame('servicios', LectorHojas::normalizar('  SERVICIOS  (luz) '));
        $this->assertSame('descripcion', LectorHojas::normalizar('Descripción'));
        $this->assertSame('tax year', LectorHojas::normalizar('Tax year:'));
    }

    // ── Datos sensibles ──

    public function test_ningun_monto_ni_descripcion_sale_en_excepciones_logs_ni_en_claro_en_la_base(): void
    {
        $this->subirYLeer('hoja-propia.csv');
        $this->subirYLeer('plantilla-registros-problemas.xlsx');

        $secretos = ['Client payment invoice 101', 'Printer paper', 'Facebook ads', 'Pet grooming', '1200.00', '45.20', '300.00', '35.00', 'A-1'];
        $detalles = (string) json_encode(Excepcion::query()->pluck('detalle')->all());

        foreach ($secretos as $secreto) {
            $this->assertStringNotContainsString($secreto, $detalles, "El detalle de las excepciones no debe llevar «{$secreto}».");
            $this->assertStringNotContainsString($secreto, implode("\n", $this->registrosDeLog), "Los logs no deben llevar «{$secreto}».");
            $this->assertStringNotContainsString($secreto, (string) json_encode(DB::table('expediente_eventos')->get()->all()), "La bitácora no debe llevar «{$secreto}».");
            $this->assertStringNotContainsString($secreto, (string) json_encode(DB::table('documento_renglones')->get(['descripcion', 'monto'])->all()), "La base debe guardar «{$secreto}» cifrado.");
        }

        $this->assertSame(Documento::query()->count(), 2);
    }
}
