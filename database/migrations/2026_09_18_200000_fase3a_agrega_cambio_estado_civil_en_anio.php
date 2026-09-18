<?php

use App\Support\TaxFieldCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fase 3a del plan de cierre de brecha GTS (ver el artifact "Matriz GTS
 * 1040"): agrega los subcampos 'se_caso_en_anio' y 'se_divorcio_o_separo_en_anio'
 * a `transversal.estado_civil` (hechos crudos del filing status). Migración
 * de datos, no de schema — mismo patrón que
 * 2026_08_11_090000_fase6_agrega_seguridad_social_a_ingresos.php: `estado_civil`
 * YA EXISTE como fila en producción desde antes, así que firstOrCreate() de
 * CatalogoCamposSeeder nunca la tocaría.
 */
return new class extends Migration
{
    private const TAX_YEAR = 2025;

    private const SUBCAMPOS_NUEVOS = ['se_caso_en_anio', 'se_divorcio_o_separo_en_anio'];

    public function up(): void
    {
        $fila = DB::table('catalogo_campos')
            ->where('forma', 'transversal')->where('clave', 'estado_civil')->where('tax_year', self::TAX_YEAR)
            ->first();

        if (! $fila) {
            return;
        }

        $subcampos = json_decode($fila->subcampos ?? '[]', true) ?? [];

        foreach (self::SUBCAMPOS_NUEVOS as $nuevo) {
            if (! in_array($nuevo, $subcampos, true)) {
                $subcampos[] = $nuevo;
            }
        }

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

        $subcampos = array_values(array_diff(json_decode($fila->subcampos ?? '[]', true) ?? [], self::SUBCAMPOS_NUEVOS));

        DB::table('catalogo_campos')
            ->where('id', $fila->id)
            ->update(['subcampos' => json_encode($subcampos), 'updated_at' => now()]);

        TaxFieldCatalog::invalidate();
    }
};
