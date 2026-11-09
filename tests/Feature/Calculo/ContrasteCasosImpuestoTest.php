<?php

namespace Tests\Feature\Calculo;

use App\Enums\Expediente\EstadoCivilIrs;
use App\Services\Calculo\Formularios\ImpuestoYSchedule2;
use App\Services\Calculo\Nucleo\Declaracion;
use App\Services\Calculo\Nucleo\Dinero;
use App\Services\Calculo\Nucleo\EntradasDeclaracion;
use App\Services\Calculo\Nucleo\EstadoLinea;
use App\Services\Calculo\Nucleo\Linea;
use App\Services\Calculo\Nucleo\Traza;
use Database\Seeders\ParametrosFiscalesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CasosIrs;
use Tests\TestCase;

/**
 * Doble lectura (F6, 6.9a y 6.4e): los casos de la línea 16 del 1040 los escribió quien no vio la
 * calculadora, con la Tax Table y la hoja de cálculo de la Pub. 1040 (2025). Aquí se corren a través de
 * `ImpuestoYSchedule2` y deben dar lo mismo. Si no coinciden, se revisa contra el PDF antes de tocar
 * la calculadora o el caso.
 */
class ContrasteCasosImpuestoTest extends TestCase
{
    use RefreshDatabase;

    private const ESTADOS = [
        'soltero' => EstadoCivilIrs::Single,
        'casado_conjunto' => EstadoCivilIrs::Mfj,
        'casado_separado' => EstadoCivilIrs::Mfs,
        'jefe_de_hogar' => EstadoCivilIrs::Hoh,
    ];

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function casosDeLaLinea16(): array
    {
        $casos = [];

        foreach (CasosIrs::casos(2025) as $caso) {
            if (isset($caso['entradas']['ingreso_gravable'], $caso['lineas']['1040']['16'])) {
                $casos[$caso['codigo']] = [$caso];
            }
        }

        return $casos;
    }

    /**
     * @param  array<string, mixed>  $caso
     */
    #[DataProvider('casosDeLaLinea16')]
    public function test_la_linea_16_da_lo_que_dice_el_caso_del_irs(array $caso): void
    {
        $this->seed(ParametrosFiscalesSeeder::class);

        $estado = self::ESTADOS[$caso['entradas']['estado_civil']] ?? null;
        $this->assertNotNull($estado, "{$caso['codigo']}: estado civil desconocido");

        $declaracion = new Declaracion(2025, 'contraste');
        $declaracion->poner(Linea::calculada('1040', '15', Dinero::deDolares($caso['entradas']['ingreso_gravable']), Traza::vacia()));
        $declaracion->poner(Linea::calculada('1040', '19', Dinero::deCentavos(0), Traza::vacia()));
        $declaracion->poner(Linea::calculada('1040', '20', Dinero::deCentavos(0), Traza::vacia()));

        (new ImpuestoYSchedule2)->calcular(new EntradasDeclaracion(2025, $estado), $declaracion);

        $linea = $declaracion->linea('1040', '16');
        $this->assertNotNull($linea, $caso['codigo']);
        $this->assertSame(EstadoLinea::Calculada, $linea->estado, "{$caso['codigo']}: {$linea->motivo}");
        $this->assertSame(
            Dinero::deDolares($caso['lineas']['1040']['16'])->centavos(),
            $linea->valor->centavos(),
            "{$caso['codigo']}: la calculadora no coincide con el caso del IRS",
        );
    }
}
