<?php

namespace Tests\Feature\Portal;

use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Consentimiento\Consentimientos;
use App\Services\Consentimiento\ConsentimientosCargador;
use App\Services\Entrevista\ChecklistDocumentos;
use App\Services\Entrevista\DocumentosEntrevista;
use App\Services\Entrevista\PasosEntrevista;
use App\Services\Entrevista\RespuestaData;
use App\Services\Entrevista\RespuestaGuardador;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F4.8b: la miniatura del formulario oficial del IRS solo sale en los
 * documentos que la tienen en el catálogo.
 */
class ImagenEjemploDocumentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_lista_trae_la_url_solo_donde_el_catalogo_tiene_imagen(): void
    {
        $this->seed(CatalogoFiscalSeeder::class);
        app(ConsentimientosCargador::class)->cargar();
        $cliente = User::factory()->create(['role' => UserRole::Client, 'es_prueba' => true]);
        app(Consentimientos::class)->firmar($cliente, (int) config('tax.current_tax_year'), 1, 'Cliente', null, null);
        $entrevista = app(RespuestaGuardador::class)->entrevistaDe($cliente, CanalRespuesta::Portal);

        foreach (['D1', 'D12'] as $pregunta) {
            app(RespuestaGuardador::class)->guardar($entrevista, new RespuestaData($pregunta, 'si', null), CanalRespuesta::Portal, $cliente);
        }

        app(ChecklistDocumentos::class)->sincronizar($entrevista);

        $filas = collect(app(DocumentosEntrevista::class)->lista($entrevista, app(PasosEntrevista::class)->situacion($entrevista), false))->keyBy('codigo');

        $this->assertSame(asset('ejemplos-documentos/w2.png'), $filas['w2']['imagen_ejemplo']);
        $this->assertNull($filas['id-foto']['imagen_ejemplo']);
        $this->assertNull($filas['registros-alquiler']['imagen_ejemplo']);
    }
}
