<?php

use App\Support\TaxFieldCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Observaciones #2 y #3 de observacionesFormulario.md: `convivio_conyuge_ultimos_6_meses`
 * y `existe_persona_calificable` (subcampos de `transversal.estado_civil`) no
 * se leen en ningún cálculo — `FilingStatusCalculator` nunca los consulta, y
 * `DependentQualificationCalculator::calcular()` calcula "existe un
 * dependiente calificado" de forma independiente a partir de
 * `info_dependientes`, a propósito, para no confiar en el flag auto-reportado
 * (ver su docblock). Se dejan de preguntar — los valores ya guardados en
 * producción quedan huérfanos en el JSON de `campos_cliente.valor`, no se
 * borran.
 *
 * Migración de datos, no de schema — mismo patrón que
 * 2026_09_18_200000_fase3a_agrega_cambio_estado_civil_en_anio.php.
 */
return new class extends Migration
{
    private const TAX_YEAR = 2025;

    private const SUBCAMPOS_A_QUITAR = ['convivio_conyuge_ultimos_6_meses', 'existe_persona_calificable'];

    public function up(): void
    {
        $fila = DB::table('catalogo_campos')
            ->where('forma', 'transversal')->where('clave', 'estado_civil')->where('tax_year', self::TAX_YEAR)
            ->first();

        if (! $fila) {
            return;
        }

        $subcampos = array_values(array_diff(
            json_decode($fila->subcampos ?? '[]', true) ?? [],
            self::SUBCAMPOS_A_QUITAR,
        ));

        DB::table('catalogo_campos')
            ->where('id', $fila->id)
            ->update(['subcampos' => json_encode($subcampos), 'updated_at' => now()]);

        TaxFieldCatalog::invalidate();
    }

    public function down(): void
    {
        $fila = DB::table('catalogo_campos')
            ->where('forma', 'transversal')->where('clave', 'estado_civil')->where('tax_year', self::TAX_YEAR)
            ->first();

        if (! $fila) {
            return;
        }

        $subcampos = json_decode($fila->subcampos ?? '[]', true) ?? [];

        foreach (self::SUBCAMPOS_A_QUITAR as $subcampo) {
            if (! in_array($subcampo, $subcampos, true)) {
                $subcampos[] = $subcampo;
            }
        }

        DB::table('catalogo_campos')
            ->where('id', $fila->id)
            ->update(['subcampos' => json_encode($subcampos), 'updated_at' => now()]);

        TaxFieldCatalog::invalidate();
    }
};
