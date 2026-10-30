<?php

namespace Tests\Feature\Entrevista;

use App\Models\CampoCliente;
use App\Models\User;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use App\Services\Entrevista\EquivalenciasCamposCliente;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F2.3: el archivo de equivalencias cubre todo el formulario actual en
 * alcance, apunta a piezas que existen en el catálogo nuevo y el reporte
 * solo cuenta, nunca muestra valores.
 */
class EquivalenciasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogoFiscalSeeder::class);
    }

    public function test_el_archivo_cubre_todo_lo_que_esta_en_alcance_y_apunta_a_piezas_que_existen(): void
    {
        $this->assertSame([], app(EquivalenciasCamposCliente::class)->problemas(2025, 2025, app(CatalogoFiscal::class)));
    }

    public function test_las_formas_de_empresa_y_la_1040_nr_quedan_fuera(): void
    {
        $claves = array_keys(app(EquivalenciasCamposCliente::class)->todas());

        $this->assertEmpty(array_filter($claves, fn (string $c) => preg_match('/^form_(1065|1120|1120_s|1041|990|1040_nr)\./', $c) === 1));
        $this->assertContains('transversal.info_dependientes', $claves);
        $this->assertContains('documentos_extra.form_1098_t', $claves);
    }

    public function test_detecta_claves_que_faltan_codigos_que_no_existen_y_transformaciones_desconocidas(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'equivalencias').'.php';
        file_put_contents($ruta, "<?php return [
            'transversal.w2' => ['documento' => 'no-existe', 'responde' => ['D1' => 'tal vez']],
            'transversal.ocupacion' => ['pregunta' => 'Z9', 'transforma' => 'adivinar'],
            'transversal.estado_civil' => ['subcampos' => ['casado_al_31_dic' => ['pregunta' => 'B1', 'transforma' => 'casado_si_verdadero']]],
            'form_1040.no_existe' => ['sin_equivalente' => 'x'],
        ];");

        try {
            $problemas = collect((new EquivalenciasCamposCliente($ruta))->problemas(2025, 2025, app(CatalogoFiscal::class)));
        } finally {
            unlink($ruta);
        }

        $this->assertTrue($problemas->contains('Falta en el archivo: transversal.form_1099_nec.'));
        $this->assertTrue($problemas->contains('No existe en catalogo_campos 2025: form_1040.no_existe.'));
        $this->assertTrue($problemas->contains('transversal.w2: el documento no-existe no existe en el catálogo nuevo.'));
        $this->assertTrue($problemas->contains('transversal.w2: La respuesta de D1 debe ser "si" o "no".'));
        $this->assertTrue($problemas->contains('transversal.ocupacion: la pregunta Z9 no existe en el catálogo nuevo.'));
        $this->assertTrue($problemas->contains('transversal.ocupacion: transformación desconocida para Z9.'));
        $this->assertTrue($problemas->contains(fn (string $p) => str_starts_with($p, 'transversal.estado_civil: los subcampos no calzan')));
    }

    public function test_el_reporte_cuenta_por_destino_y_no_muestra_valores(): void
    {
        foreach (User::factory()->count(2)->create() as $cliente) {
            foreach ([['transversal', 'identificacion_ssn_itin', 'dato'], ['transversal', 'w2', 'documento']] as [$forma, $campo, $tipo]) {
                CampoCliente::query()->create([
                    'user_id' => $cliente->id, 'forma' => $forma, 'tax_year' => 2025, 'campo' => $campo,
                    'tipo_campo' => $tipo, 'modo' => 'texto', 'valor_texto' => '987-65-4321',
                    'estado' => 'recibido', 'source' => 'agente_ia',
                ]);
            }
        }

        $this->artisan('catalogo-fiscal:equivalencias', ['--reporte' => true])
            ->expectsOutputToContain('transversal.w2')
            ->expectsTable(['Destino', 'Filas'], [['documento', 2], ['sin_equivalente', 2]])
            ->doesntExpectOutputToContain('987-65-4321')
            ->assertSuccessful();
    }
}
