<?php

namespace App\Console\Commands;

use App\Enums\Expediente\EstadoExpediente;
use App\Models\Entrevista\Entrevista;
use App\Models\User;
use App\Services\Expediente\DiagnosticoExpediente;
use App\Services\Expediente\EstadosExpediente;
use Illuminate\Console\Command;

/**
 * Crea o actualiza el expediente de cada entrevista (clientes que ya existían
 * antes de F5). Imprime solo cantidades por estado: nunca nombres ni datos.
 */
class RecalcularExpedientes extends Command
{
    protected $signature = 'expedientes:recalcular
        {--anio= : Solo las entrevistas de este año fiscal}
        {--solo-prueba : Solo clientes marcados como de prueba}
        {--dry-run : Solo calcula el diagnóstico, no escribe nada}';

    protected $description = 'Crea o actualiza el expediente de cada entrevista; con --dry-run solo muestra cuántos quedarían en cada estado.';

    public function handle(EstadosExpediente $estados, DiagnosticoExpediente $diagnostico): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $cuentas = [];

        Entrevista::query()
            ->when($this->option('anio'), fn ($q, $anio) => $q->where('tax_year', (int) $anio))
            ->when($this->option('solo-prueba'), fn ($q) => $q->whereIn('user_id', User::withTrashed()->where('es_prueba', true)->select('id')))
            ->orderBy('id')
            ->chunkById(100, function ($entrevistas) use ($dryRun, $estados, $diagnostico, &$cuentas) {
                foreach ($entrevistas as $entrevista) {
                    $cliente = User::withTrashed()->find($entrevista->user_id);

                    if ($cliente === null) {
                        continue;
                    }

                    $estado = $dryRun
                        ? $diagnostico->para($cliente, $entrevista->tax_year)->estado
                        : $estados->recalcular($cliente, $entrevista->tax_year)->estado;

                    $cuentas[$estado->value] = ($cuentas[$estado->value] ?? 0) + 1;
                }
            });

        $filas = [];

        foreach (EstadoExpediente::cases() as $estado) {
            if (isset($cuentas[$estado->value])) {
                $filas[] = [$estado->value, $cuentas[$estado->value]];
            }
        }

        $this->info($dryRun ? 'Simulacro: no se escribió nada.' : 'Expedientes recalculados.');
        $this->table(['Estado', 'Cantidad'], $filas);

        return self::SUCCESS;
    }
}
