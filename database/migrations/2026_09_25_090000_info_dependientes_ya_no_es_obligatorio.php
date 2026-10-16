<?php

use App\Support\TaxFieldCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Observación #1 de observacionesFormulario.md: `transversal.info_dependientes`
 * era obligatorio, pero al ser tipo array_object no tenía forma de guardar
 * "no tengo dependientes" — el botón "No aplica" solo se muestra para campos
 * no obligatorios (ver CampoInputForm en formulario.tsx), y un array vacío no
 * sobrevive la serialización multipart del formulario del portal (queda
 * rechazado con "The contenido field must be present"). Mismo bug ya resuelto
 * antes para w2/form_1099_nec (ver CatalogoCamposSeeder.php) — ahora aplicado
 * acá.
 *
 * Migración de datos, no de schema — mismo patrón que
 * 2026_09_18_200000_fase3a_agrega_cambio_estado_civil_en_anio.php:
 * `info_dependientes` YA EXISTE como fila en producción desde antes, así que
 * firstOrCreate() de CatalogoCamposSeeder nunca la tocaría.
 */
return new class extends Migration
{
    private const TAX_YEAR = 2025;

    public function up(): void
    {
        DB::table('catalogo_campos')
            ->where('forma', 'transversal')->where('clave', 'info_dependientes')->where('tax_year', self::TAX_YEAR)
            ->update(['obligatorio' => false, 'updated_at' => now()]);

        TaxFieldCatalog::invalidate();
    }

    public function down(): void
    {
        DB::table('catalogo_campos')
            ->where('forma', 'transversal')->where('clave', 'info_dependientes')->where('tax_year', self::TAX_YEAR)
            ->update(['obligatorio' => true, 'updated_at' => now()]);

        TaxFieldCatalog::invalidate();
    }
};
