<?php

namespace App\Console\Commands;

use App\Enums\FieldState;
use App\Models\CampoCliente;
use App\Services\EventoRecoleccionService;
use Illuminate\Console\Command;

/**
 * Repara filas de campos_cliente que quedaron en FieldState::Invalido por el
 * bug de EventoRecoleccionService::objetoTieneSubcampos corregido junto con
 * este comando (exigía subcampos condicionales que el prompt del agente
 * nunca instruye rellenar, ej. datos de viudez de estado_civil para un
 * cliente soltero). No modifica valor_texto: solo re-evalúa el estado con la
 * validación ya corregida y, si cambia, recalcula la completitud de las
 * formas afectadas — ver EventoRecoleccionService::revalidarValorExistente.
 */
class RevalidarCamposInvalidos extends Command
{
    protected $signature = 'campos:revalidar-invalidos
        {campos* : Claves de campo a reprocesar (ej. estado_civil info_conyuge)}
        {--dry-run : Solo lista las filas afectadas, sin escribir}';

    protected $description = 'Reprocesa campos_cliente en estado invalido tras un fix de validación';

    public function handle(EventoRecoleccionService $service): int
    {
        $campos = $this->argument('campos');
        $dryRun = (bool) $this->option('dry-run');

        $filas = CampoCliente::query()
            ->where('estado', FieldState::Invalido)
            ->whereIn('campo', $campos)
            ->get();

        $this->info(sprintf('%d fila(s) invalida(s) para: %s', $filas->count(), implode(', ', $campos)));

        if ($filas->isEmpty()) {
            return self::SUCCESS;
        }

        $corregidas = 0;

        foreach ($filas as $fila) {
            if ($dryRun) {
                $this->line("[dry-run] user_id={$fila->user_id} campo={$fila->campo} forma={$fila->forma} tax_year={$fila->tax_year}");

                continue;
            }

            $actualizada = $service->revalidarValorExistente($fila);

            $this->line("user_id={$fila->user_id} campo={$fila->campo} forma={$fila->forma} tax_year={$fila->tax_year} -> {$actualizada->estado->value}");

            if ($actualizada->estado === FieldState::Recibido) {
                $corregidas++;
            }
        }

        if (! $dryRun) {
            $this->info("{$corregidas} de {$filas->count()} corregida(s) a 'recibido'.");
        }

        return self::SUCCESS;
    }
}
