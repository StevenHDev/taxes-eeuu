<?php

namespace App\Console\Commands;

use App\Services\CatalogoFiscal\CatalogoFiscalVerificador;
use Illuminate\Console\Command;

/**
 * Revisa la integridad del catálogo fiscal de un año (F1.3). Termina con
 * error si encuentra algún problema.
 */
class VerificarCatalogoFiscal extends Command
{
    protected $signature = 'catalogo-fiscal:verificar {anio : Año fiscal, por ejemplo 2025}';

    protected $description = 'Revisa la integridad del catálogo fiscal de un año';

    public function handle(CatalogoFiscalVerificador $verificador): int
    {
        $anio = (int) $this->argument('anio');

        $this->table(['Pieza', 'Activas'], collect($verificador->conteos($anio))->map(fn (int $n, string $pieza) => [$pieza, $n])->values()->all());

        $errores = $verificador->verificar($anio);

        if ($errores === []) {
            $this->info("Catálogo fiscal {$anio}: sin problemas.");

            return self::SUCCESS;
        }

        foreach ($errores as $error) {
            $this->error($error);
        }

        $this->error(count($errores)." problema(s) en el catálogo fiscal {$anio}.");

        return self::FAILURE;
    }
}
