<?php

use App\Support\TaxFieldCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fase 4 del plan de cierre de brecha GTS: `form_1040.deducciones` pasa de
 * un único Number suelto a un objeto con subcampos por categoría (Schedule
 * A) — ver CatalogoCamposSeeder. Migración de datos, no de schema — mismo
 * patrón que 2026_08_11_090000_fase6_agrega_seguridad_social_a_ingresos.php:
 * la fila ya existe en producción desde antes, así que firstOrCreate() del
 * seeder nunca la tocaría.
 *
 * A propósito, esta migración NO toca `campos_cliente` — no hay forma
 * confiable de saber a qué categoría pertenecía el número suelto que un
 * cliente ya haya cargado (mismo criterio ya aplicado al cambio de shape de
 * 'ingresos' en su momento: StandardDeductionCalculator detecta que el
 * valor guardado no es un array y reporta "no disponible, vuelve a
 * cargarlo" en vez de adivinar la categoría).
 */
return new class extends Migration
{
    private const TAX_YEAR = 2025;

    /** @var array<int, string> */
    private const SUBCAMPOS = [
        'intereses_hipotecarios', 'impuestos_propiedad', 'donaciones_efectivo', 'donaciones_bienes',
        'gastos_medicos', 'intereses_inversion', 'perdidas_desastre',
    ];

    public function up(): void
    {
        $fila = DB::table('catalogo_campos')
            ->where('forma', 'form_1040')->where('clave', 'deducciones')->where('tax_year', self::TAX_YEAR)
            ->first();

        if (! $fila) {
            return;
        }

        DB::table('catalogo_campos')
            ->where('id', $fila->id)
            ->update([
                'tipo_dato' => 'object',
                'subcampos' => json_encode(self::SUBCAMPOS),
                'updated_at' => now(),
            ]);

        TaxFieldCatalog::invalidate();
    }

    public function down(): void
    {
        $fila = DB::table('catalogo_campos')
            ->where('forma', 'form_1040')->where('clave', 'deducciones')->where('tax_year', self::TAX_YEAR)
            ->first();

        if (! $fila) {
            return;
        }

        DB::table('catalogo_campos')
            ->where('id', $fila->id)
            ->update([
                'tipo_dato' => 'number',
                'subcampos' => null,
                'updated_at' => now(),
            ]);

        TaxFieldCatalog::invalidate();
    }
};
