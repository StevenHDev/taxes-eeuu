<?php

use App\Support\TaxFieldCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `CatalogoCamposSeeder` reduce 'transversal' a solo la identidad del cliente
 * (SSN, cónyuge, dependientes, estado civil, w2, 1099-NEC, 1095-A) y mueve el
 * resto de documentos opcionales a la nueva pseudo-forma 'documentos_extra'
 * (ver App\Models\CampoCatalogo::DOCUMENTOS_EXTRA). Por usar `firstOrCreate`,
 * el seeder no toca las filas ya sembradas en producción — mismo patrón que
 * 2026_07_28_120000_add_unico_por_cliente_to_catalogo_campos.php y
 * 2026_08_11_120000_agrega_jpeg_a_formatos_aceptados.php. Esta migración
 * reetiqueta esas filas ya existentes en las 4 tablas que referencian la
 * pseudo-forma por su literal.
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private array $movidos = [
        'form_1099_int',
        'form_1099_div',
        'form_1099_r',
        'form_1099_g',
        'form_1098',
        'form_1098_e',
        'ssa_1099',
        'form_1099_b',
        'form_1098_t',
        'form_1099_misc',
        'form_1099_k',
        'form_1099_s',
        'k1_recibido',
        'form_w2g',
        'form_1099_c',
        'form_1099_sa',
        'form_5498_sa',
        'declaracion_anio_anterior',
    ];

    public function up(): void
    {
        $this->reetiquetar(desde: 'transversal', hacia: 'documentos_extra');

        TaxFieldCatalog::invalidate();
    }

    public function down(): void
    {
        $this->reetiquetar(desde: 'documentos_extra', hacia: 'transversal');

        TaxFieldCatalog::invalidate();
    }

    private function reetiquetar(string $desde, string $hacia): void
    {
        DB::table('catalogo_campos')
            ->where('forma', $desde)
            ->whereIn('clave', $this->movidos)
            ->update(['forma' => $hacia]);

        DB::table('campos_cliente')
            ->where('forma', $desde)
            ->whereIn('campo', $this->movidos)
            ->update(['forma' => $hacia]);

        DB::table('historial_cambios')
            ->where('forma', $desde)
            ->whereIn('campo', $this->movidos)
            ->update(['forma' => $hacia]);

        DB::table('relaciones_documento_campo')
            ->where('documento_forma', $desde)
            ->whereIn('documento_campo', $this->movidos)
            ->update(['documento_forma' => $hacia]);
    }
};
