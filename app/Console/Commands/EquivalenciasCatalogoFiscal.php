<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use App\Services\Entrevista\EquivalenciasCamposCliente;
use App\Services\Entrevista\MigradorCamposCliente;
use Illuminate\Console\Command;

/**
 * Revisa el archivo de equivalencias del formulario actual con el catálogo
 * nuevo (F2.3) y, con --reporte, cuenta cuántos datos de `campos_cliente`
 * tienen destino. Con --migrar (F2.10) pasa esos datos a la entrevista de
 * cada cliente (MigradorCamposCliente); con --dry-run lo hace dentro de una
 * transacción que se deshace. Nunca muestra valores.
 */
class EquivalenciasCatalogoFiscal extends Command
{
    protected $signature = 'catalogo-fiscal:equivalencias
        {--anio=2025 : Año de catalogo_campos y campos_cliente}
        {--catalogo= : Año del catálogo nuevo (por defecto, el mismo)}
        {--reporte : Contar los datos de campos_cliente por destino}
        {--migrar : Pasar los datos de campos_cliente a la entrevista (año fiscal actual)}
        {--dry-run : Con --migrar: hacerlo todo y deshacerlo, para ver los conteos}
        {--cliente= : Con --migrar: solo este cliente (id)}';

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

            return $this->option('migrar') ? $this->migrar(app(MigradorCamposCliente::class)) : self::SUCCESS;
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

    private function migrar(MigradorCamposCliente $migrador): int
    {
        $anio = (int) config('tax.current_tax_year');
        $simular = (bool) $this->option('dry-run');

        if ((int) $this->option('anio') !== $anio) {
            $this->error("La migración es solo del año fiscal actual ({$anio}).");

            return self::FAILURE;
        }

        $clientes = $this->option('cliente') !== null
            ? User::query()->whereKey((int) $this->option('cliente'))->get()
            : $migrador->clientesConDatos($anio);

        $filas = $clientes->map(function (User $cliente) use ($migrador, $simular) {
            $r = $migrador->migrar($cliente, $simular);

            return [$cliente->id, $r['estado'], $r['respuestas'], $r['personas'], $r['documentos'], $r['sin_equivalente'], $r['fuera_de_alcance'], implode(', ', $r['no_aplicaron']) ?: '—'];
        });

        $this->newLine();
        $this->line($simular ? "Migración {$anio} (dry-run: no se guardó nada):" : "Migración {$anio}:");
        $this->table(['Cliente', 'Estado', 'Respuestas', 'Personas', 'Documentos', 'Sin equivalente', 'Fuera de alcance', 'No aplicaron'], $filas->all());

        return self::SUCCESS;
    }
}
