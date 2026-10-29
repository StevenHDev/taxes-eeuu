<?php

namespace Database\Seeders;

use App\Models\CatalogoFiscal\CatalogoPregunta;
use App\Services\CatalogoFiscal\CatalogoFiscalCopiador;
use App\Services\CatalogoFiscal\CatalogoFiscalImportador;
use Illuminate\Database\Seeder;

/**
 * Catálogo fiscal por año (F1.3) para `migrate:fresh --seed` en local: carga
 * 2025 desde los archivos, lo copia a 2026 y aplica los ajustes de 2026. En
 * producción se usan los comandos catalogo-fiscal:* (los seeders no corren
 * en el deploy).
 */
class CatalogoFiscalSeeder extends Seeder
{
    public function run(CatalogoFiscalImportador $importador, CatalogoFiscalCopiador $copiador): void
    {
        $importador->cargar(2025);

        if (! CatalogoPregunta::query()->delAnio(2026)->exists()) {
            $copiador->copiar(2025, 2026);
        }

        $importador->cargar(2026);
    }
}
