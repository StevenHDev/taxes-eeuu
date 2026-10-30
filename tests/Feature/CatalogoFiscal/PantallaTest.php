<?php

namespace Tests\Feature\CatalogoFiscal;

use App\Enums\UserRole;
use App\Models\CatalogoFiscal\CatalogoAnio;
use App\Models\CatalogoFiscal\CatalogoPregunta;
use App\Models\User;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * F1.4: pantalla "Catálogo fiscal del año" (5 pestañas, solo lectura).
 */
class PantallaTest extends TestCase
{
    use RefreshDatabase;

    private const PESTANAS = [
        'preguntas' => 93,
        'documentos' => 42,
        'formularios' => 26,
        'parametros' => 32,
        'reglas' => 13,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogoFiscalSeeder::class);
    }

    private function usuario(UserRole $rol): User
    {
        return User::factory()->create(['role' => $rol]);
    }

    public function test_preparador_y_administrador_ven_las_5_pestanas_con_sus_datos(): void
    {
        foreach ([UserRole::Preparer, UserRole::Administrator] as $rol) {
            $usuario = $this->usuario($rol);

            foreach (self::PESTANAS as $pestana => $cantidad) {
                $this->actingAs($usuario)
                    ->get(route("catalogo-fiscal.{$pestana}", ['anio' => 2025]))
                    ->assertOk()
                    ->assertInertia(fn (Assert $page) => $page
                        ->component("catalogo-fiscal/{$pestana}")
                        ->where('anio', 2025)
                        ->where('anios', [2026, 2025])
                        ->where('puedeEditar', $rol === UserRole::Administrator)
                        ->where('puedeValidar', true)
                        ->missing($pestana)
                        ->loadDeferredProps(fn (Assert $reload) => $reload->has($pestana, $cantidad)));
            }
        }
    }

    public function test_un_cliente_no_entra(): void
    {
        $cliente = $this->usuario(UserRole::Client);

        $this->actingAs($cliente)->get(route('catalogo-fiscal.inicio'))->assertForbidden();
        $this->actingAs($cliente)->get(route('catalogo-fiscal.preguntas', ['anio' => 2025]))->assertForbidden();
    }

    public function test_el_inicio_lleva_a_las_preguntas_del_anio_en_curso(): void
    {
        config(['tax.current_tax_year' => 2025]);

        $this->actingAs($this->usuario(UserRole::Preparer))
            ->get(route('catalogo-fiscal.inicio'))
            ->assertRedirect(route('catalogo-fiscal.preguntas', ['anio' => 2025]));
    }

    public function test_un_anio_sin_catalogo_da_404(): void
    {
        $this->actingAs($this->usuario(UserRole::Preparer))
            ->get(route('catalogo-fiscal.preguntas', ['anio' => 2019]))
            ->assertNotFound();
    }

    public function test_un_anio_creado_pero_vacio_responde_vacio_y_no_error(): void
    {
        CatalogoAnio::query()->create(['tax_year' => 2027, 'estado' => 'borrador']);

        $this->actingAs($this->usuario(UserRole::Preparer))
            ->get(route('catalogo-fiscal.reglas', ['anio' => 2027]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $reload) => $reload->has('reglas', 0)));
    }

    public function test_cada_anio_muestra_lo_suyo_con_el_anio_en_el_texto(): void
    {
        CatalogoPregunta::query()->delAnio(2026)->where('codigo', 'D1')->update(['texto_es' => 'Solo en 2026: {anio}']);
        CatalogoFiscal::invalidar(2026);

        $preparador = $this->usuario(UserRole::Preparer);

        foreach ([2025 => 'Recibiste salario', 2026 => 'Solo en 2026: 2026'] as $anio => $texto) {
            $this->actingAs($preparador)
                ->get(route('catalogo-fiscal.preguntas', ['anio' => $anio]))
                ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $reload) => $reload
                    ->where('preguntas', fn ($preguntas) => str_contains(collect($preguntas)->firstWhere('codigo', 'D1')['texto'], $texto))));
        }
    }

    public function test_los_parametros_dicen_si_estan_pendientes_y_los_documentos_quien_los_activa(): void
    {
        $preparador = $this->usuario(UserRole::Preparer);

        $this->actingAs($preparador)
            ->get(route('catalogo-fiscal.parametros', ['anio' => 2026]))
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('parametros', function ($parametros) {
                    $parametros = collect($parametros);

                    return $parametros->firstWhere('codigo', 'deduccion_estandar.monto_mfj')['valor'] === null
                        && $parametros->firstWhere('codigo', 'dependiente_calificado.limite_ingreso_bruto_pariente_calificado')['valor'] === 5300;
                })));

        $this->actingAs($preparador)
            ->get(route('catalogo-fiscal.documentos', ['anio' => 2025]))
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('documentos', fn ($documentos) => collect($documentos)->firstWhere('codigo', 'w2')['activado_por'] === ['D1'])));
    }

    public function test_los_documentos_salen_por_grupo_con_identidad_primero(): void
    {
        $this->actingAs($this->usuario(UserRole::Preparer))
            ->get(route('catalogo-fiscal.documentos', ['anio' => 2025]))
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('documentos', function ($documentos) {
                    $grupos = collect($documentos)->pluck('grupo')->unique()->values()->all();

                    return $grupos[0] === 'identidad' && end($grupos) === 'pagos';
                })));
    }
}
