<?php

namespace Tests\Feature;

use App\Enums\FaseConversacion;
use App\Services\WhatsappAgent\ToolDefinitions;
use Tests\TestCase;

class ToolDefinitionsTest extends TestCase
{
    /**
     * @param  array<int, array<string, mixed>>  $tools
     * @return array<int, string>
     */
    private function nombres(array $tools): array
    {
        return collect($tools)->map(fn (array $t) => $t['function']['name'])->all();
    }

    public function test_verificacion_cuenta_solo_expone_crear_cliente_taxes_y_think(): void
    {
        $nombres = $this->nombres(ToolDefinitions::paraFase(FaseConversacion::VerificacionCuenta));

        $this->assertEqualsCanonicalizing(['crear_cliente_taxes', 'think'], $nombres);
    }

    public function test_ninguna_tool_expone_cliente_id_como_parametro(): void
    {
        foreach (FaseConversacion::cases() as $fase) {
            foreach (ToolDefinitions::paraFase($fase) as $tool) {
                $propiedades = $tool['function']['parameters']['properties'];

                // Tools sin parámetros (ej. consultar_pendientes_cliente) usan
                // stdClass vacío para serializar como `{}` en vez de `[]`.
                if ($propiedades instanceof \stdClass) {
                    continue;
                }

                $this->assertArrayNotHasKey(
                    'cliente_id',
                    $propiedades,
                    "{$tool['function']['name']} no debería exponer cliente_id",
                );
            }
        }
    }
}
