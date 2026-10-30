<?php

namespace App\Console\Commands;

use App\Services\CatalogoFiscal\CatalogoFiscal;
use App\Services\Entrevista\EquivalenciasCamposCliente;
use Illuminate\Console\Command;

/**
 * Revisa el archivo de equivalencias del formulario actual con el catálogo
 * nuevo (F2.3) y, con --reporte, cuenta cuántos datos de `campos_cliente`
 * tienen destino. Solo lee: no escribe nada y nunca muestra valores.
 */
class EquivalenciasCatalogoFiscal extends Command
{
    protected $signature = 'catalogo-fiscal:equivalencias
        {--anio=2025 : Año de catalogo_campos y campos_cliente}
        {--catalogo= : Año del catálogo nuevo (por defecto, el mismo)}
        {--reporte : Contar los datos de campos_cliente por destino}';

    protected $description = 'Revisa las equivalencias del formulario actual con el catálogo fiscal nuevo';

    public function handle(EquivalenciasCamposCliente $equivalencias, CatalogoFiscal $catalogo): int
    {
        $anio = (int) $this->option('anio');
        $anioNuevo = (int) ($this->option('catalogo') ?? $anio);

        $porDestino = collect($equivalencias->todas())->countBy(fn (array $e) => EquivalenciasCamposCliente::destino($e));
        $this->table(['Destino', 'Campos'], $porDestino->sortKeys()->map(fn (int $n, string $d) => [$d, $n])->values()->all());

        if ($this->option('reporte')) {
            $this->reporte($equivalencias, $anio);
        }

        $problemas = $equivalencias->problemas($anio, $anioNuevo, $catalogo);

        if ($problemas === []) {
            $this->info("Equivalencias {$anio} → catálogo {$anioNuevo}: sin problemas.");

            return self::SUCCESS;
        }

        foreach ($problemas as $problema) {
            $this->error($problema);
        }

        $this->error(count($problemas).' problema(s) en las equivalencias.');

        return self::FAILURE;
    }

    private function reporte(EquivalenciasCamposCliente $equivalencias, int $anio): void
    {
        $enAlcance = EquivalenciasCamposCliente::formasEnAlcance();

        $filas = $equivalencias->conteos($anio)->map(function (object $c) use ($equivalencias, $enAlcance) {
            $entrada = $equivalencias->de($c->forma, $c->campo);

            return [
                'campo' => "{$c->forma}.{$c->campo}",
                'destino' => match (true) {
                    ! in_array($c->forma, $enAlcance, true) => 'fuera_de_alcance',
                    $entrada === null => 'falta_en_el_archivo',
                    default => EquivalenciasCamposCliente::destino($entrada),
                },
                'filas' => (int) $c->filas,
                'clientes' => (int) $c->clientes,
            ];
        });

        $this->newLine();
        $this->line("Datos en campos_cliente {$anio} (sin valores):");
        $this->table(['Campo', 'Destino', 'Filas', 'Clientes'], $filas->map(fn (array $f) => array_values($f))->all());

        $resumen = $filas->groupBy('destino')->map(fn ($g, string $destino) => [$destino, $g->sum('filas')])->sortKeys()->values();
        $this->table(['Destino', 'Filas'], $resumen->all());
    }
}
