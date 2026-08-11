<?php

use App\Models\CampoCatalogo;
use App\Support\TaxFieldCatalog;
use Illuminate\Database\Migrations\Migration;

/**
 * `CatalogoCamposSeeder` usa firstOrCreate, así que agregar 'jpeg' a la lista
 * de formatos ahí no actualiza las filas ya sembradas en producción (mismo
 * patrón que 2026_08_11_090000_fase6_agrega_seguridad_social_a_ingresos.php).
 * Esta migración agrega 'jpeg' a `formatos_aceptados` en toda fila que ya
 * acepte 'jpg' pero todavía no 'jpeg' — muchos celulares guardan fotos con
 * esa extensión y antes de esto la plataforma la rechazaba.
 */
return new class extends Migration
{
    public function up(): void
    {
        CampoCatalogo::query()
            ->whereNotNull('formatos_aceptados')
            ->get()
            ->each(function (CampoCatalogo $campo) {
                $formatos = $campo->formatos_aceptados ?? [];

                if (! in_array('jpg', $formatos, true) || in_array('jpeg', $formatos, true)) {
                    return;
                }

                $campo->update(['formatos_aceptados' => [...$formatos, 'jpeg']]);
            });

        TaxFieldCatalog::invalidate();
    }

    public function down(): void
    {
        CampoCatalogo::query()
            ->whereNotNull('formatos_aceptados')
            ->get()
            ->each(function (CampoCatalogo $campo) {
                $formatos = array_values(array_diff($campo->formatos_aceptados ?? [], ['jpeg']));

                $campo->update(['formatos_aceptados' => $formatos]);
            });

        TaxFieldCatalog::invalidate();
    }
};
