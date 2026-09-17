<?php

use App\Enums\FieldKind;
use App\Enums\TaxForm;
use App\Support\TaxFieldCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 'salarios_medicare' (Box 5 del W-2) es una fila NUEVA de catalogo_campos —
 * a diferencia de un subcampo agregado a un campo ya existente (ver
 * 2026_08_11_090000_fase6_agrega_seguridad_social_a_ingresos.php),
 * CatalogoCamposSeeder::crear() usa firstOrCreate por (forma, clave,
 * tax_year), así que en una instalación fresca esta fila se inserta sola —
 * pero una instalación ya provisionada (producción) nunca vuelve a correr el
 * seeder, así que necesita esta migración para recibirla.
 */
return new class extends Migration
{
    private const TAX_YEAR = 2025;

    public function up(): void
    {
        DB::table('catalogo_campos')->insertOrIgnore([
            'forma' => TaxForm::Form1040->value,
            'clave' => 'salarios_medicare',
            'tax_year' => self::TAX_YEAR,
            'tipo_campo' => FieldKind::Dato->value,
            'tipo_dato' => 'number',
            'formatos_aceptados' => null,
            'subcampos' => null,
            'obligatorio' => true,
            'sensible' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TaxFieldCatalog::invalidate();
    }

    public function down(): void
    {
        DB::table('catalogo_campos')
            ->where('forma', TaxForm::Form1040->value)
            ->where('clave', 'salarios_medicare')
            ->where('tax_year', self::TAX_YEAR)
            ->delete();

        TaxFieldCatalog::invalidate();
    }
};
