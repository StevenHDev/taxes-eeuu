<?php

use App\Support\TaxFieldCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fase 6 (auditoría completa de la matriz GTS 1040 2025): agrega el subcampo
 * 'seguridad_social' a form_1040.ingresos. Migración de datos, no de schema —
 * mismo estilo que 2026_07_31_140000_fase2_catalogo_estado_civil_dependientes_ingresos.php.
 *
 * A diferencia de los documentos nuevos de esta misma fase (que solo viven en
 * CatalogoCamposSeeder, porque son filas nuevas y firstOrCreate las inserta
 * solas), 'ingresos' YA EXISTE como fila en producción desde la Fase 2 —
 * firstOrCreate nunca la tocaría, así que el subcampo nuevo necesita esta
 * migración de datos para llegar a instalaciones ya provisionadas.
 */
return new class extends Migration
{
    private const TAX_YEAR = 2025;

    public function up(): void
    {
        $fila = DB::table('catalogo_campos')
            ->where('forma', 'form_1040')->where('clave', 'ingresos')->where('tax_year', self::TAX_YEAR)
            ->first();

        if (! $fila) {
            return;
        }

        $subcampos = json_decode($fila->subcampos ?? '[]', true) ?? [];

        if (in_array('seguridad_social', $subcampos, true)) {
            return;
        }

        $subcampos[] = 'seguridad_social';

        DB::table('catalogo_campos')
            ->where('id', $fila->id)
            ->update(['subcampos' => json_encode($subcampos), 'updated_at' => now()]);

        TaxFieldCatalog::invalidate();
    }

    public function down(): void
    {
        $fila = DB::table('catalogo_campos')
            ->where('forma', 'form_1040')->where('clave', 'ingresos')->where('tax_year', self::TAX_YEAR)
            ->first();

        if (! $fila) {
            return;
        }

        $subcampos = array_values(array_diff(json_decode($fila->subcampos ?? '[]', true) ?? [], ['seguridad_social']));

        DB::table('catalogo_campos')
            ->where('id', $fila->id)
            ->update(['subcampos' => json_encode($subcampos), 'updated_at' => now()]);

        TaxFieldCatalog::invalidate();
    }
};
