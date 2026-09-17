<?php

use App\Enums\TaxForm;
use App\Support\TaxFieldCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Relación documento→campo nueva (w2 → salarios_medicare, Box 5) — igual que
 * el campo de catálogo que la acompaña
 * (2026_09_17_130000_agrega_salarios_medicare_a_catalogo.php), esta fila
 * también depende de que RelacionesDocumentoCampoSeeder::firstOrCreate corra
 * de nuevo para llegar a producción, cosa que no pasa en una instalación ya
 * provisionada.
 */
return new class extends Migration
{
    private const TAX_YEAR = 2025;

    public function up(): void
    {
        $catalogoTieneCampo = DB::table('catalogo_campos')
            ->where('forma', TaxForm::Form1040->value)
            ->where('clave', 'salarios_medicare')
            ->where('tax_year', self::TAX_YEAR)
            ->exists();

        if (! $catalogoTieneCampo) {
            return;
        }

        DB::table('relaciones_documento_campo')->insertOrIgnore([
            'documento_forma' => 'transversal',
            'documento_campo' => 'w2',
            'campo_destino_forma' => TaxForm::Form1040->value,
            'campo_destino' => 'salarios_medicare',
            'subcampo_destino' => null,
            'descripcion' => 'Box 5 (Medicare wages and tips) del W-2 — puede ser distinto de Box 1 (salarios) cuando hay descuentos pre-tax de nómina (401k, HSA, sección 125); necesario para Additional Medicare Tax (Form 8959).',
            'acumulable' => true,
            'tax_year' => self::TAX_YEAR,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TaxFieldCatalog::invalidate();
    }

    public function down(): void
    {
        DB::table('relaciones_documento_campo')
            ->where('documento_campo', 'w2')
            ->where('campo_destino_forma', TaxForm::Form1040->value)
            ->where('campo_destino', 'salarios_medicare')
            ->where('tax_year', self::TAX_YEAR)
            ->delete();

        TaxFieldCatalog::invalidate();
    }
};
