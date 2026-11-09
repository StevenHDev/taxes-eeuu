<?php

namespace Tests\Feature\Lectura;

use App\Enums\Entrevista\EstadoDocumento;
use App\Jobs\LeerDocumentoJob;
use App\Models\Lectura\DocumentoLectura;
use App\Services\Entrevista\ChecklistDocumentos;
use App\Services\Lectura\DatosDocumento;
use App\Services\Lectura\EsquemaLectura;
use App\Services\Lectura\LectorDocumentos;
use App\Services\Lectura\Reglas\MotorReglas;
use Illuminate\Support\Facades\Http;

/**
 * F6 (6.3b): la bandera `tax.lectura_automatica` apaga la lectura con IA, pero las hojas `xlsx` y `csv`
 * no usan IA y se leen siempre.
 */
class LeerHojaSinBanderaTest extends RegistrosTestCase
{
    private function correrJob(int $documentoId): void
    {
        (new LeerDocumentoJob($documentoId))->handle(
            app(LectorDocumentos::class),
            app(DatosDocumento::class),
            app(ChecklistDocumentos::class),
            app(EsquemaLectura::class),
            app(MotorReglas::class),
        );
    }

    public function test_una_hoja_xlsx_se_lee_aunque_la_lectura_automatica_este_apagada(): void
    {
        config(['tax.lectura_automatica' => false]);
        Http::fake();

        $archivo = $this->subir('plantilla-registros-llena-en.xlsx');
        $this->correrJob($archivo->id);

        $lectura = DocumentoLectura::query()->where('documento_id', $archivo->id)->sole();
        $this->assertSame('hoja', $lectura->lector);
        $this->assertSame(EstadoDocumento::Leido, $this->itemRegistros()->fresh()->estado);
        Http::assertNothingSent();
    }

    public function test_un_csv_tambien_se_lee_con_la_lectura_automatica_apagada(): void
    {
        config(['tax.lectura_automatica' => false]);
        Http::fake();

        $archivo = $this->subir('hoja-propia.csv');
        $this->correrJob($archivo->id);

        $this->assertSame(1, DocumentoLectura::query()->where('documento_id', $archivo->id)->count());
        Http::assertNothingSent();
    }

    public function test_un_pdf_de_registros_no_se_lee_con_la_lectura_automatica_apagada(): void
    {
        config(['tax.lectura_automatica' => false]);
        Http::fake();

        $archivo = $this->subir('plantilla-registros-llena-en.pdf');
        $this->correrJob($archivo->id);

        $this->assertSame(0, DocumentoLectura::query()->where('documento_id', $archivo->id)->count());
        $this->assertSame(EstadoDocumento::Subido, $this->itemRegistros()->fresh()->estado);
        Http::assertNothingSent();
    }
}
