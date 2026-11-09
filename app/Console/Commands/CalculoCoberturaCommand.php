<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Tests\Support\CasosIrs;

/**
 * Reescribe database/data/catalogo-fiscal/<año>/cobertura-casos.php con las líneas que
 * cubren los casos del IRS (tests/Fixtures/irs/casos), para que producción no lea tests/.
 * Es una herramienta de desarrollo: necesita las dependencias de desarrollo.
 */
class CalculoCoberturaCommand extends Command
{
    protected $signature = 'calculo:cobertura {anio : Año fiscal, por ejemplo 2025}
        {--salida= : Ruta del archivo (por defecto database/data/catalogo-fiscal/<año>/cobertura-casos.php)}';

    protected $description = 'Regenera la lista de líneas cubiertas por los casos del IRS de un año';

    public function handle(): int
    {
        if (! class_exists(CasosIrs::class)) {
            $this->error(__('Este comando solo corre con las dependencias de desarrollo.'));

            return self::FAILURE;
        }

        $anio = (int) $this->argument('anio');
        $mapa = CasosIrs::cubrenPorLinea($anio); // clave = "formulario:línea"

        $salida = $this->option('salida') ?: database_path("data/catalogo-fiscal/{$anio}/cobertura-casos.php");

        $lineas = ['<?php', '', '// Generado por `php artisan calculo:cobertura '.$anio.'`. No se edita a mano.', '', 'return ['];

        foreach (array_keys($mapa) as $linea) {
            $lineas[] = '    '.var_export($linea, true).',';
        }

        $lineas[] = '];';

        if (! is_dir(dirname($salida))) {
            $this->error(__('No existe la carpeta de destino: :ruta', ['ruta' => dirname($salida)]));

            return self::FAILURE;
        }

        file_put_contents($salida, implode("\n", $lineas)."\n");

        $this->info(__(':n línea(s) cubierta(s) por los casos de :anio.', ['n' => count($mapa), 'anio' => $anio]));

        return self::SUCCESS;
    }
}
