<?php

namespace App\Console\Commands;

use App\Services\CatalogoFiscal\CatalogoFiscalCopiador;
use Illuminate\Console\Command;

/**
 * Copia el catálogo fiscal de un año a otro (F1.3). Todo queda sin validar
 * y los parámetros quedan pendientes (sin cifra).
 */
class CopiarCatalogoFiscal extends Command
{
    protected $signature = 'catalogo-fiscal:copiar
        {desde : Año de origen, por ejemplo 2025}
        {hasta : Año nuevo, por ejemplo 2026}';

    protected $description = 'Copia el catálogo fiscal de un año a otro como punto de partida';

    public function handle(CatalogoFiscalCopiador $copiador): int
    {
        $desde = (int) $this->argument('desde');
        $hasta = (int) $this->argument('hasta');

        $conteo = $copiador->copiar($desde, $hasta);

        $this->info("Catálogo fiscal {$desde} copiado a {$hasta}:");
        $this->table(['Pieza', 'Copiados'], collect($conteo)->map(fn (int $n, string $pieza) => [$pieza, $n])->values()->all());
        $this->line("Los parámetros de {$hasta} quedaron pendientes. Aplica los ajustes con: php artisan catalogo-fiscal:cargar {$hasta}");

        return self::SUCCESS;
    }
}
