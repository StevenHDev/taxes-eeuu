<?php

namespace Tests\Feature\Lectura;

use App\Enums\Lectura\SentidoRenglon;
use App\Models\Documento;
use App\Models\Entrevista\EntrevistaDocumento;
use App\Models\Expediente\Expediente;
use App\Models\Lectura\DocumentoRenglon;
use App\Services\Calculo\Nucleo\Dinero;
use App\Services\Expediente\EstadosExpediente;
use App\Services\Lectura\ResultadoSumas;
use App\Services\Lectura\SumasRegistros;
use InvalidArgumentException;

/**
 * F6 6.3b: `SumasRegistros` suma por línea del Schedule C los renglones
 * vigentes de las hojas y plantillas del cliente, con los ids de los
 * renglones que forman cada total, su confianza y su motivo.
 */
class SumasRegistrosTest extends RegistrosTestCase
{
    private function sumas(): ResultadoSumas
    {
        return app(SumasRegistros::class)->para($this->expediente());
    }

    private function expediente(): Expediente
    {
        return app(EstadosExpediente::class)->recalcular($this->cliente, $this->entrevista->tax_year);
    }

    public function test_suma_por_categoria_con_los_ids_de_sus_renglones_la_confianza_y_el_motivo(): void
    {
        $lectura = $this->subirYLeer('plantilla-registros-llena-en.xlsx');
        $sumas = $this->sumas();

        // Los montos son los de la plantilla llena (sintética): 52.000 de ingresos, 4.800 de oficina, 1.750 de comidas.
        $ingresos = $sumas->para('1-sin-formulario');
        $this->assertTrue($ingresos->conDatos);
        $this->assertSame(5200000, $ingresos->total->centavos());
        $this->assertSame([$lectura->renglones->firstWhere('categoria', '1-sin-formulario')->id], $ingresos->renglonIds);
        $this->assertSame(0.99, $ingresos->confianza);
        $this->assertStringContainsString('Leído de la hoja de cálculo (sin IA)', $ingresos->motivo);

        $this->assertSame(480000, $sumas->total('18')->centavos());
        $this->assertSame(175000, $sumas->total('24b')->centavos());
        $this->assertSame(31000, $sumas->total('27b')->centavos());

        // Una línea con el cero de la hoja ("Wages") no trae renglón: sin datos, no "cero reportado".
        $this->assertFalse($sumas->para('26')->conDatos);

        // Las cantidades salen de las casillas: 9.800 millas, 1.850 y 140 pies cuadrados.
        $this->assertSame(9800, intdiv($sumas->total('millas')->centavos(), 100));
        $this->assertSame(1850, intdiv($sumas->total('pies-casa')->centavos(), 100));
        $this->assertSame(140, intdiv($sumas->total('pies-oficina')->centavos(), 100));
        $this->assertTrue($sumas->para('millas')->conDatos);
        $this->assertSame([], $sumas->para('millas')->renglonIds);
        $this->assertSame(0.99, $sumas->para('millas')->confianza);
    }

    public function test_siempre_trae_todas_las_claves_y_las_que_no_tienen_datos_lo_dicen(): void
    {
        $sumas = $this->sumas();

        $this->assertSame(ResultadoSumas::CLAVES, array_map('strval', array_keys($sumas->sumas)));
        $this->assertCount(29, ResultadoSumas::CLAVES);
        $this->assertFalse($sumas->conDatos());

        foreach (ResultadoSumas::CLAVES as $clave) {
            $suma = $sumas->para($clave);
            $this->assertSame($clave, $suma->linea);
            $this->assertFalse($suma->conDatos);
            $this->assertSame(0, $suma->total->centavos());
            $this->assertSame([], $suma->renglonIds);
        }

        $this->expectException(InvalidArgumentException::class);
        $sumas->para('31');
    }

    public function test_suma_los_renglones_de_varios_archivos_y_el_menos_seguro_manda_la_confianza(): void
    {
        $plantilla = $this->subirYLeer('plantilla-registros-llena-en.xlsx');
        $hoja = $this->subirYLeer('hoja-propia.csv');

        $sumas = $this->sumas();

        // Oficina: 4.800,00 de la plantilla + 45,20 de la hoja propia.
        $oficina = $sumas->para('18');
        $this->assertSame(484520, $oficina->total->centavos());
        $this->assertEqualsCanonicalizing([
            $plantilla->renglones->firstWhere('categoria', '18')->id,
            $hoja->renglones->firstWhere('categoria', '18')->id,
        ], $oficina->renglonIds);
        $this->assertStringContainsString('Suma de 2 renglones; el menos seguro:', $oficina->motivo);

        // Publicidad: 450 + 300; ingresos: 52.000 + 1.200.
        $this->assertSame(75000, $sumas->total('8')->centavos());
        $this->assertSame(5320000, $sumas->total('1-sin-formulario')->centavos());
    }

    public function test_no_suma_transferencias_personales_no_se_ni_lo_que_ya_reporta_un_1099(): void
    {
        $lectura = $this->subirYLeer('hoja-propia.csv');
        $item = $this->itemRegistros();
        $ids = $lectura->renglones->pluck('id', 'categoria');

        // El de sin categoría (`no_se`) ya no suma; uno pasa a transferencia, otro está cubierto por un 1099.
        $this->assertFalse($this->sumas()->para('27b')->conDatos);
        DocumentoRenglon::query()->whereKey($ids['18'])->update(['sentido' => SentidoRenglon::Transferencia]);
        $otroItem = EntrevistaDocumento::query()->where('id', '!=', $item->id)->first();
        DocumentoRenglon::query()->whereKey($ids['8'])->update(['cubierto_por_item_id' => $otroItem->id]);

        $sumas = $this->sumas();
        $this->assertFalse($sumas->para('18')->conDatos, 'Una transferencia entre cuentas propias no suma.');
        $this->assertFalse($sumas->para('8')->conDatos, 'Lo que ya reporta un 1099 no suma otra vez.');
        $this->assertSame(120000, $sumas->total('1-sin-formulario')->centavos());
        $this->assertFalse($sumas->para('9')->conDatos);
        $this->assertCount(1, array_filter($sumas->sumas, fn ($s) => $s->conDatos), 'Solo queda el ingreso.');
    }

    public function test_un_archivo_retirado_deja_de_sumar_y_la_ultima_lectura_de_cada_archivo_es_la_que_cuenta(): void
    {
        $archivo = $this->subir('hoja-propia.csv');
        $this->leer($archivo);
        $this->assertTrue($this->sumas()->para('18')->conDatos);

        // Releer es otra lectura: las sumas no cuentan las dos.
        $this->leer($archivo);
        $this->assertSame(4520, $this->sumas()->total('18')->centavos());

        $archivo->delete();
        $this->assertFalse($this->sumas()->conDatos(), 'Un archivo retirado no suma.');
    }

    public function test_pies_cuadrados_distintos_en_dos_archivos_bajan_la_confianza_y_millas_se_suman(): void
    {
        $this->subirYLeer('plantilla-registros-llena-en.xlsx');
        $this->subirYLeer('plantilla-registros-llena-es.xlsx');

        $sumas = $this->sumas();

        // 9.800 + 1.200 millas se suman; los pies cuadrados no (1.850 contra 900): se toma el último, con duda.
        $this->assertSame(11000, intdiv($sumas->total('millas')->centavos(), 100));
        $this->assertSame(900, intdiv($sumas->total('pies-casa')->centavos(), 100));
        $this->assertSame(0.60, $sumas->para('pies-casa')->confianza);
        $this->assertStringContainsString('valores distintos', $sumas->para('pies-casa')->motivo);
        $this->assertSame(0.99, $sumas->para('millas')->confianza);
    }

    public function test_sin_entrevista_en_el_expediente_no_hay_sumas_y_dinero_no_usa_float(): void
    {
        $expediente = $this->expediente();
        $expediente->entrevista_id = null;

        $sumas = app(SumasRegistros::class)->para($expediente);

        $this->assertFalse($sumas->conDatos());
        $this->assertInstanceOf(Dinero::class, $sumas->total('18'));
        $this->assertSame(0, Documento::query()->count());
    }
}
