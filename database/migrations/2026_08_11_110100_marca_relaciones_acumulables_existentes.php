<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `RelacionesDocumentoCampoSeeder` usa firstOrCreate, así que no actualiza
 * filas ya sembradas en producción antes de que existiera la columna
 * `acumulable` (agregada en 2026_08_11_110000_...). Esta migración de datos
 * marca directamente los 4 grupos de colisión confirmados — mismo
 * campo_destino_forma/campo_destino/subcampo_destino resuelto por más de un
 * documento — para que no se pierda silenciosamente el segundo valor
 * (ver EventoRecoleccionService::procesar()).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('relaciones_documento_campo')
            ->whereIn('documento_campo', ['w2', 'form_1099_nec', 'form_1099_int', 'form_1099_div', 'form_1099_r'])
            ->where('campo_destino', 'impuestos_retenidos')
            ->whereNull('subcampo_destino')
            ->update(['acumulable' => true]);

        DB::table('relaciones_documento_campo')
            ->whereIn('documento_campo', ['form_1099_int', 'form_1099_div'])
            ->where('campo_destino', 'ingresos')
            ->where('subcampo_destino', 'intereses_dividendos')
            ->update(['acumulable' => true]);

        DB::table('relaciones_documento_campo')
            ->whereIn('documento_campo', ['form_1099_g', 'form_w2g', 'form_1099_c'])
            ->where('campo_destino', 'ingresos')
            ->where('subcampo_destino', 'otros_ingresos')
            ->update(['acumulable' => true]);

        DB::table('relaciones_documento_campo')
            ->whereIn('documento_campo', ['form_1098_e', 'form_5498_sa'])
            ->where('campo_destino', 'ingresos')
            ->where('subcampo_destino', 'ajustes_ingreso')
            ->update(['acumulable' => true]);
    }

    public function down(): void
    {
        DB::table('relaciones_documento_campo')->update(['acumulable' => false]);
    }
};
