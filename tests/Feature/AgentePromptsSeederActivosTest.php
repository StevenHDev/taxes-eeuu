<?php

namespace Tests\Feature;

use App\Enums\FaseConversacion;
use App\Support\AgentePromptVigente;
use Database\Seeders\AgentePromptsSeeder;
use Database\Seeders\PromptActivoStepsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AgentePromptsSeeder debe sustituir los marcadores de
 * prompt_actuales/fases/recoleccion.md por el texto compilado desde
 * prompt_activo_steps — nunca dejar el marcador literal en el prompt real
 * que llega al modelo.
 */
class AgentePromptsSeederActivosTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_prompt_publicado_de_recoleccion_no_tiene_marcadores_sin_sustituir(): void
    {
        $this->seed(PromptActivoStepsSeeder::class);
        $this->seed(AgentePromptsSeeder::class);

        $contenido = AgentePromptVigente::paraFase(FaseConversacion::Recoleccion);

        $this->assertNotNull($contenido);
        $this->assertStringNotContainsString('<!-- ACTIVOS_LISTA -->', $contenido);
        $this->assertStringNotContainsString('<!-- ACTIVOS_SALVAGUARDAS -->', $contenido);
        $this->assertStringContainsString('1. identificacion_ssn_itin', $contenido);
        $this->assertStringContainsString('form_1095_a', $contenido);
    }

    public function test_las_demas_fases_no_se_tocan(): void
    {
        $this->seed(PromptActivoStepsSeeder::class);
        $this->seed(AgentePromptsSeeder::class);

        $verificacion = AgentePromptVigente::paraFase(FaseConversacion::VerificacionCuenta);
        $original = file_get_contents(base_path('prompt_actuales/fases/verificacion_cuenta.md'));

        $this->assertSame($original, $verificacion);
    }
}
