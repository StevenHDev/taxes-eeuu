<?php

namespace Tests\Feature\CatalogoFiscal;

use App\Enums\CatalogoFiscal\GrupoParametro;
use App\Enums\CatalogoFiscal\OrigenCambio;
use App\Enums\CatalogoFiscal\TipoParametro;
use App\Enums\UserRole;
use App\Models\CatalogoFiscal\CatalogoCambio;
use App\Models\ParametroFiscal;
use App\Models\User;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use App\Support\ParametrosFiscales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F1.2: parámetros del año sobre `parametros_fiscales`.
 */
class ParametrosTest extends TestCase
{
    use RefreshDatabase;

    public function test_los_parametros_sembrados_tienen_grupo_tipo_y_nombre(): void
    {
        $sinGrupo = ParametroFiscal::query()->where('tax_year', 2025)->whereNull('grupo')->pluck('clave');

        $this->assertSame([], $sinGrupo->all());

        $estandar = ParametroFiscal::query()->where('categoria', 'deduccion_estandar')->where('clave', 'monto_mfj')->sole();
        $this->assertSame(GrupoParametro::DeduccionEstandar, $estandar->grupo);
        $this->assertSame(TipoParametro::Monto, $estandar->tipo);
        $this->assertSame('Form 1040, línea 12', $estandar->formulario_linea);
        $this->assertFalse($estandar->estaValidado());
        $this->assertSame(TipoParametro::Tabla, ParametroFiscal::query()->where('categoria', 'tax_brackets')->first()->tipo);
    }

    public function test_el_archivo_de_metadatos_cubre_todos_los_parametros_del_seeder(): void
    {
        $metadatos = require database_path('data/catalogo-fiscal/2025/parametros-metadatos.php');
        $sembrados = ParametroFiscal::query()->where('tax_year', 2025)->get()
            ->map(fn (ParametroFiscal $p) => "{$p->categoria}.{$p->clave}")->sort()->values()->all();

        $this->assertSame($sembrados, collect(array_keys($metadatos))->sort()->values()->all());

        foreach ($metadatos as $codigo => [$grupo, $tipo]) {
            $this->assertNotNull(GrupoParametro::tryFrom($grupo), "grupo inválido en {$codigo}");
            $this->assertNotNull(TipoParametro::tryFrom($tipo), "tipo inválido en {$codigo}");
        }
    }

    public function test_un_parametro_pendiente_no_inventa_una_cifra(): void
    {
        ParametroFiscal::query()->create([
            'tax_year' => 2026, 'categoria' => 'credito_ctc', 'clave' => 'monto_por_dependiente',
            'valor' => null, 'grupo' => GrupoParametro::Creditos, 'tipo' => TipoParametro::Monto,
        ]);

        $this->assertNull(ParametrosFiscales::valor(2026, 'credito_ctc', 'monto_por_dependiente'));

        $this->expectExceptionMessage('[credito_ctc.monto_por_dependiente] de tax_year=2026 está pendiente');
        ParametrosFiscales::valorRequerido(2026, 'credito_ctc', 'monto_por_dependiente');
    }

    public function test_un_parametro_que_no_existe_sigue_diciendo_que_falta_sembrarlo(): void
    {
        $this->expectExceptionMessage('Falta sembrar el parámetro fiscal [credito_ctc.monto_por_dependiente] para tax_year=2030');

        ParametrosFiscales::valorRequerido(2030, 'credito_ctc', 'monto_por_dependiente');
    }

    public function test_cambiar_2026_no_toca_2025_y_queda_en_el_historial(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $p2026 = ParametroFiscal::query()->create([
            'tax_year' => 2026, 'categoria' => 'credito_ctc', 'clave' => 'monto_por_dependiente', 'valor' => null,
        ]);
        $this->assertSame(2200, ParametrosFiscales::valor(2025, 'credito_ctc', 'monto_por_dependiente'));

        $this->actingAs($admin);
        $p2026->update(['valor' => 2300]);

        $this->assertSame(2300, ParametrosFiscales::valor(2026, 'credito_ctc', 'monto_por_dependiente'));
        $this->assertSame(2200, ParametrosFiscales::valor(2025, 'credito_ctc', 'monto_por_dependiente'));

        $cambio = CatalogoCambio::query()->where('auditable_type', ParametroFiscal::class)->sole();
        $this->assertNull($cambio->valor_anterior);
        $this->assertSame(2300, $cambio->valor_nuevo);
        $this->assertSame(OrigenCambio::Pantalla, $cambio->origen);
        $this->assertSame(2026, $cambio->tax_year);
    }

    public function test_el_lector_del_catalogo_trae_los_parametros_del_anio(): void
    {
        $lector = app(CatalogoFiscal::class);

        $this->assertSame(
            ParametroFiscal::query()->where('tax_year', 2025)->count(),
            $lector->parametros(2025)->count(),
        );
        $this->assertTrue($lector->parametros(2026)->isEmpty());
    }
}
