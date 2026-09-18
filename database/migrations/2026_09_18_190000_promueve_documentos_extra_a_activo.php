<?php

use App\Support\TaxFieldCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fase 2 del plan de cierre de brecha GTS (ver el artifact "Matriz GTS
 * 1040"): CatalogoCamposSeeder mueve estas 17 claves de documentosExtra()
 * (forma 'documentos_extra') a documentosPromovidosAActivo() (forma
 * 'transversal') — pero `firstOrCreate` solo inserta filas nuevas, nunca
 * corrige la `forma` de una fila que ya existe en producción desde antes.
 * Migración de datos, no de schema — mismo patrón que
 * 2026_08_11_090000_fase6_agrega_seguridad_social_a_ingresos.php.
 *
 * Sin esto, en producción: (a) catalogo_campos seguiría teniendo estas 17
 * filas bajo 'documentos_extra', invisibles para `pendientesPara()` aunque
 * PromptActivoStepsSeeder ya apunte a ellas — el agente nunca las
 * preguntaría; y (b) cualquier cliente que ya subió alguno de estos
 * documentos espontáneamente tiene su fila en campos_cliente bajo
 * forma='documentos_extra' — sin migrarla también, `pendientesPara()` no la
 * reconocería como ya resuelta bajo la nueva forma='transversal', y el
 * agente se la volvería a pedir.
 *
 * form_1098_t se excluye a propósito: se queda pasivo (ver
 * CatalogoCamposSeeder::documentosExtra()).
 */
return new class extends Migration
{
    private const TAX_YEAR = 2025;

    /** @var array<int, string> */
    private const CLAVES = [
        'form_1099_int', 'form_1099_div', 'form_1099_r', 'form_1099_g', 'form_1098', 'form_1098_e',
        'ssa_1099', 'form_1099_b', 'form_1099_misc', 'form_1099_k', 'form_1099_s', 'k1_recibido',
        'form_w2g', 'form_1099_c', 'form_1099_sa', 'form_5498_sa', 'declaracion_anio_anterior',
    ];

    public function up(): void
    {
        DB::table('catalogo_campos')
            ->where('forma', 'documentos_extra')
            ->where('tax_year', self::TAX_YEAR)
            ->whereIn('clave', self::CLAVES)
            ->update(['forma' => 'transversal', 'updated_at' => now()]);

        // Sin filtro de tax_year: campos_cliente es único por (user_id,
        // forma, campo) sin importar el año (ver migración
        // create_campos_cliente_table) — cualquier fila ya guardada bajo
        // 'documentos_extra' para estas claves, de cualquier año, migra.
        DB::table('campos_cliente')
            ->where('forma', 'documentos_extra')
            ->whereIn('campo', self::CLAVES)
            ->update(['forma' => 'transversal', 'updated_at' => now()]);

        TaxFieldCatalog::invalidate();
    }

    public function down(): void
    {
        DB::table('catalogo_campos')
            ->where('forma', 'transversal')
            ->where('tax_year', self::TAX_YEAR)
            ->whereIn('clave', self::CLAVES)
            ->update(['forma' => 'documentos_extra', 'updated_at' => now()]);

        DB::table('campos_cliente')
            ->where('forma', 'transversal')
            ->whereIn('campo', self::CLAVES)
            ->update(['forma' => 'documentos_extra', 'updated_at' => now()]);

        TaxFieldCatalog::invalidate();
    }
};
