<?php

namespace Tests\Feature;

use App\Enums\FaseConversacion;
use App\Models\AgenteToolEstado;
use App\Services\WhatsappAgent\ToolDefinitions;
use App\Support\AgenteToolEstados;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgenteToolEstadosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<int, array<string, mixed>>  $tools
     * @return array<int, string>
     */
    private function nombres(array $tools): array
    {
        return collect($tools)->map(fn (array $t) => $t['function']['name'])->all();
    }

    public function test_sin_filas_en_la_tabla_habilitadas_para_fase_es_igual_a_para_fase(): void
    {
        foreach (FaseConversacion::cases() as $fase) {
            $this->assertSame(
                $this->nombres(ToolDefinitions::paraFase($fase)),
                $this->nombres(ToolDefinitions::habilitadasParaFase($fase)),
            );
        }
    }

    public function test_desactivar_una_tool_la_excluye_solo_de_esa_fase(): void
    {
        AgenteToolEstado::query()->create([
            'fase' => FaseConversacion::Recoleccion->value,
            'tool_name' => 'consultar_documentos_extra',
            'activo' => false,
        ]);
        AgenteToolEstados::invalidate();

        $enRecoleccion = $this->nombres(ToolDefinitions::habilitadasParaFase(FaseConversacion::Recoleccion));
        $enCierre = $this->nombres(ToolDefinitions::habilitadasParaFase(FaseConversacion::Cierre));

        $this->assertNotContains('consultar_documentos_extra', $enRecoleccion);
        $this->assertContains('consultar_documentos_extra', $enCierre);
    }

    public function test_think_no_se_puede_desactivar_desde_la_tabla_pero_si_se_forzara_no_rompe_el_loop(): void
    {
        // No hay UI que permita esto (AgenteToolController excluye `think`),
        // pero si alguien escribe la fila directo en la tabla, el helper
        // igual debe respetarla — no hay ningún caso especial hardcodeado.
        AgenteToolEstado::query()->create([
            'fase' => FaseConversacion::Cierre->value,
            'tool_name' => 'think',
            'activo' => false,
        ]);
        AgenteToolEstados::invalidate();

        $this->assertNotContains('think', $this->nombres(ToolDefinitions::habilitadasParaFase(FaseConversacion::Cierre)));
    }
}
