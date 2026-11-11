<?php

namespace Tests\Feature\Lectura;

use App\Enums\Entrevista\EstadoDocumento;
use App\Enums\Lectura\EstadoExcepcion;
use App\Enums\Lectura\OrigenCategoria;
use App\Enums\Lectura\OrigenDato;
use App\Enums\Lectura\SentidoRenglon;
use App\Enums\UserRole;
use App\Models\CatalogoFiscal\CatalogoDocumento;
use App\Models\Documento;
use App\Models\Lectura\DocumentoRenglon;
use App\Models\Lectura\DocumentoRenglonCambio;
use App\Models\Lectura\Excepcion;
use App\Models\User;
use App\Services\Entrevista\ChecklistDocumentos;
use App\Services\Entrevista\TransicionNoPermitida;
use App\Services\Expediente\EstadosExpediente;
use App\Services\Lectura\DatosDocumento;
use App\Services\Lectura\ExplicadorExcepcion;
use App\Services\Lectura\Reglas\MotorReglas;
use App\Services\Lectura\ResultadoSumas;
use App\Services\Lectura\SumasRegistros;
use App\Services\Lectura\SumasRegistrosPorRenglones;
use Illuminate\Support\Facades\DB;

/**
 * F6 6.3b: el modelo de renglones. Se guardan con la descripción y el monto
 * cifrados, se corrigen solo con `DatosDocumento::corregirRenglon()` (con
 * historial) y al corregir las sumas y la regla R-14 se rehacen.
 */
class RenglonesTest extends RegistrosTestCase
{
    private User $preparador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->preparador = User::factory()->create(['role' => UserRole::Preparer]);
    }

    private function sumas(): ResultadoSumas
    {
        return app(SumasRegistros::class)->para(app(EstadosExpediente::class)->recalcular($this->cliente, $this->entrevista->tax_year));
    }

    public function test_el_importador_carga_lectura_renglones_en_los_registros_y_casillas_en_el_resto_en_2025_y_2026(): void
    {
        foreach ([2025, 2026] as $anio) {
            $documentos = CatalogoDocumento::query()->delAnio($anio)->pluck('lectura', 'codigo');

            $this->assertSame('renglones', $documentos['registros-ingresos-gastos'], "{$anio}: registros por renglones.");
            $this->assertSame(['registros-ingresos-gastos'], $documentos->filter(fn ($l) => $l !== 'casillas')->keys()->all(), "{$anio}: solo los registros.");
            $this->assertSame('casillas', $documentos['w2']);
        }
    }

    public function test_la_descripcion_y_el_monto_van_cifrados_en_la_base_y_en_claro_en_el_modelo(): void
    {
        $lectura = $this->subirYLeer('hoja-propia.csv');
        $crudo = DB::table('documento_renglones')->where('lectura_id', $lectura->id)->orderBy('numero')->first();

        $this->assertStringNotContainsString('Client payment', (string) $crudo->descripcion);
        $this->assertStringNotContainsString('1200', (string) $crudo->monto);
        $this->assertSame('1200.00', $lectura->renglones->first()->monto);
        $this->assertSame('Client payment invoice 101', $lectura->renglones->first()->descripcion);
        $this->assertSame(OrigenDato::Lector, $lectura->renglones->first()->origen);
    }

    public function test_corregir_la_categoria_de_un_renglon_deja_historial_rehace_la_suma_y_cierra_la_excepcion(): void
    {
        $lectura = $this->subirYLeer('hoja-propia.csv');
        $item = $this->itemRegistros();
        app(ChecklistDocumentos::class)->leido($item);
        app(ChecklistDocumentos::class)->validar($item->fresh(), $this->preparador);

        $sumas = $this->sumas();
        $this->assertFalse($sumas->para('27b')->conDatos);

        $renglon = $lectura->renglones->firstWhere('categoria', null);
        $this->assertSame('60.00', $renglon->monto);
        $this->assertSame(EstadoExcepcion::Abierta, Excepcion::query()->where('clave', 'like', '%#sin_categoria')->sole()->estado);

        app(DatosDocumento::class)->corregirRenglon($renglon, ['categoria' => '27b'], 'Son regalos al equipo: otros gastos', $this->preparador);

        $renglon->refresh();
        $this->assertSame('27b', $renglon->categoria);
        $this->assertSame(SentidoRenglon::Gasto, $renglon->sentido, 'Sin decir el sentido, sale del lado de la categoría.');
        $this->assertSame(OrigenCategoria::Preparador, $renglon->categoria_origen);
        $this->assertSame(OrigenDato::Preparador, $renglon->origen);
        $this->assertSame($this->preparador->id, $renglon->corregido_por);
        $this->assertSame('60.00', $renglon->monto, 'El monto no cambió.');

        // Un cambio por campo, con quién, de qué a qué y por qué. Nada se borra.
        $cambios = $renglon->cambios()->orderBy('id')->get()->keyBy('campo');
        $this->assertSame(['categoria', 'sentido'], $cambios->keys()->all());
        $this->assertNull($cambios['categoria']->valor_anterior);
        $this->assertSame('27b', $cambios['categoria']->valor_nuevo);
        $this->assertSame('no_se', $cambios['sentido']->valor_anterior);
        $this->assertSame('gasto', $cambios['sentido']->valor_nuevo);
        $this->assertSame('Son regalos al equipo: otros gastos', $cambios['categoria']->motivo);
        $this->assertSame($this->preparador->id, $cambios['categoria']->actor_id);
        $this->assertStringNotContainsString('27b', (string) DB::table('documento_renglon_cambios')->value('valor_nuevo'), 'El historial va cifrado.');

        // La validación se reabrió y la suma se rehízo sola.
        $this->assertSame(EstadoDocumento::Leido, $item->fresh()->estado);
        $nueva = $this->sumas()->para('27b');
        $this->assertSame(6000, $nueva->total->centavos());
        $this->assertSame([$renglon->id], $nueva->renglonIds);
        $this->assertSame(1.0, $nueva->confianza, 'Lo que corrigió una persona cuenta como revisado.');

        // Y la excepción de "sin categoría" se cerró: ya no hay renglones sin ella.
        $this->assertSame(EstadoExcepcion::Corregida, Excepcion::query()->where('clave', 'like', '%#sin_categoria')->sole()->estado);
    }

    public function test_corregir_el_monto_y_marcar_un_renglon_como_personal_rehacen_la_suma(): void
    {
        $lectura = $this->subirYLeer('hoja-propia.csv');
        $papel = $lectura->renglones->firstWhere('categoria', '18');
        $cobro = $lectura->renglones->firstWhere('categoria', '1-sin-formulario');

        app(DatosDocumento::class)->corregirRenglon($papel, ['monto' => '50.5'], 'El recibo dice 50,50', $this->preparador);
        app(DatosDocumento::class)->corregirRenglon($cobro, ['sentido' => SentidoRenglon::Personal], 'Era un préstamo familiar, no un ingreso', $this->preparador);

        $this->assertSame('50.50', $papel->fresh()->monto);
        $this->assertSame('45.20', $papel->cambios()->sole()->valor_anterior, 'El valor leído queda en el historial.');
        $this->assertSame(SentidoRenglon::Personal, $cobro->fresh()->sentido);

        $sumas = $this->sumas();
        $this->assertSame(5050, $sumas->para('18')->total->centavos());
        $this->assertFalse($sumas->para('1-sin-formulario')->conDatos, 'Un renglón personal no suma.');
    }

    public function test_no_se_corrige_sin_motivo_ni_con_un_monto_o_una_categoria_invalidos_y_sin_cambios_no_hay_historial(): void
    {
        $renglon = $this->subirYLeer('hoja-propia.csv')->renglones->first();
        $datos = app(DatosDocumento::class);

        foreach ([
            [['monto' => '10'], '  '],
            [['monto' => '10.123'], 'x'],
            [['monto' => '-5'], 'x'],
            [['monto' => 'abc'], 'x'],
            [['categoria' => '99'], 'x'],
            [['categoria' => 'millas'], 'x'],
        ] as [$cambios, $motivo]) {
            try {
                $datos->corregirRenglon($renglon, $cambios, $motivo, $this->preparador);
                $this->fail('Debió rechazarse: '.json_encode($cambios));
            } catch (TransicionNoPermitida) {
                $this->assertSame(0, DocumentoRenglonCambio::query()->count());
            }
        }

        // El mismo valor, sin cambios: no es una corrección.
        $datos->corregirRenglon($renglon, ['monto' => '1200'], 'igual', $this->preparador);
        $this->assertSame(0, DocumentoRenglonCambio::query()->count());
        $this->assertSame(OrigenDato::Lector, $renglon->fresh()->origen);
    }

    public function test_r14_dice_en_palabras_que_pasa_sin_montos_ni_descripciones(): void
    {
        app()->setLocale('es');
        $this->subirYLeer('hoja-propia.csv');
        $this->subir('otra.pdf', '%PDF-1.4 vacío');
        app(MotorReglas::class)->correr($this->entrevista);

        $textos = Excepcion::query()->where('regla_codigo', 'R-14')->with('entrevista')->get()
            ->map(fn (Excepcion $e) => app(ExplicadorExcepcion::class)->explicar($e))->all();

        $this->assertCount(3, $textos);

        $todo = json_encode($textos, JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('no se reconocieron: la columna E', $todo);
        $this->assertStringContainsString('hay 1 renglón sin categoría', $todo);
        $this->assertStringContainsString('PDF o una foto', $todo);

        foreach (['Client payment', '1200', '45.20', 'Gifts'] as $secreto) {
            $this->assertStringNotContainsString($secreto, (string) $todo);
        }
    }

    public function test_la_suma_de_los_renglones_se_registra_en_el_contenedor_y_nadie_mas_escribe_renglones_a_mano(): void
    {
        $this->assertInstanceOf(SumasRegistrosPorRenglones::class, app(SumasRegistros::class));

        // Un solo camino de escritura: el modelo no expone nada que no sea el servicio.
        $lectura = $this->subirYLeer('hoja-propia.csv');
        $this->assertSame(4, DocumentoRenglon::query()->where('lectura_id', $lectura->id)->count());
        $this->assertSame(Documento::query()->count(), 1);
    }
}
