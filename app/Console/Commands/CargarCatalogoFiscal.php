<?php

namespace App\Console\Commands;

use App\Services\CatalogoFiscal\CatalogoFiscalImportador;
use Illuminate\Console\Command;

/**
 * Carga el catálogo fiscal de un año desde database/data/catalogo-fiscal
 * (F1.3). Se corre a mano después del deploy: los seeders no corren en
 * producción.
 */
class CargarCatalogoFiscal extends Command
{
    protected $signature = 'catalogo-fiscal:cargar
        {anio : Año fiscal, por ejemplo 2025}
        {--dry-run : Muestra qué crearía, actualizaría o saltaría, sin escribir}
        {--forzar : Reemplaza también lo que un administrador editó desde la pantalla}';

    protected $description = 'Carga el catálogo fiscal de un año desde los archivos de datos';

    public function handle(CatalogoFiscalImportador $importador): int
    {
        $anio = (int) $this->argument('anio');
        $dryRun = (bool) $this->option('dry-run');

        $resultado = $importador->cargar($anio, $dryRun, (bool) $this->option('forzar'));

        $this->info(($dryRun ? '[dry-run] ' : '')."Catálogo fiscal {$anio}:");
        $this->table(['Pieza', 'Creados', 'Actualizados', 'Sin cambios'], $resultado->filas());

        foreach ($resultado->saltados as $saltado) {
            $this->warn("Saltado: {$saltado}");
        }

        foreach ($resultado->avisos as $aviso) {
            $this->warn($aviso);
        }

        if (! $dryRun) {
            $this->line("Revisa la integridad con: php artisan catalogo-fiscal:verificar {$anio}");
        }

        return self::SUCCESS;
    }
}
