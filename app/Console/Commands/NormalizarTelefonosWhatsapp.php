<?php

namespace App\Console\Commands;

use App\Models\WhatsappControl;
use App\Models\WhatsappMensaje;
use App\Support\TelefonoWhatsapp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Repara el fragmentamiento causado por el bug ya corregido de
 * App\Support\TelefonoWhatsapp: antes de esa normalización, la misma
 * conversación podía quedar guardada bajo dos formatos de teléfono distintos
 * (ej. "573213445027" y "+573213445027") — dos filas de whatsapp_control
 * para la misma persona, y whatsapp_mensajes fragmentado entre ambas.
 *
 * whatsapp_mensajes.telefono no tiene constraint único: se actualiza en el
 * lugar, sin fusionar filas. whatsapp_control.telefono SÍ es único, así que
 * un grupo con más de una fila para el mismo teléfono normalizado se
 * fusiona en una sola: se conserva la fila con cliente_id (o, si ambas lo
 * tienen y difieren, se reporta como conflicto y no se toca — requiere
 * revisión manual), heredando estado/tomado_por_user_id/tomado_en de la
 * otra fila solo si la conservada no tiene ya su propio valor.
 */
class NormalizarTelefonosWhatsapp extends Command
{
    protected $signature = 'whatsapp:normalizar-telefonos {--dry-run : Solo reporta lo que cambiaría, sin escribir}';

    protected $description = 'Normaliza whatsapp_control/whatsapp_mensajes al formato canónico de TelefonoWhatsapp y fusiona duplicados';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->normalizarMensajes($dryRun);
        $this->normalizarYFusionarControl($dryRun);

        return self::SUCCESS;
    }

    private function normalizarMensajes(bool $dryRun): void
    {
        $mensajes = WhatsappMensaje::query()->get(['id', 'telefono']);
        $cambios = 0;

        foreach ($mensajes as $mensaje) {
            $normalizado = TelefonoWhatsapp::normalizar($mensaje->telefono);

            if ($normalizado === null || $normalizado === $mensaje->telefono) {
                continue;
            }

            $cambios++;
            $this->line("whatsapp_mensajes.id={$mensaje->id}: '{$mensaje->telefono}' -> '{$normalizado}'".($dryRun ? ' [dry-run]' : ''));

            if (! $dryRun) {
                $mensaje->update(['telefono' => $normalizado]);
            }
        }

        $this->info("whatsapp_mensajes: {$cambios} fila(s) ".($dryRun ? 'a normalizar' : 'normalizada(s)').'.');
    }

    private function normalizarYFusionarControl(bool $dryRun): void
    {
        $porNormalizado = WhatsappControl::query()->get()->groupBy(
            fn (WhatsappControl $c) => TelefonoWhatsapp::normalizar($c->telefono) ?? $c->telefono,
        );

        foreach ($porNormalizado as $telefonoNormalizado => $filas) {
            if ($filas->count() === 1) {
                $fila = $filas->first();

                if ($fila->telefono !== $telefonoNormalizado) {
                    $this->line("whatsapp_control.id={$fila->id}: '{$fila->telefono}' -> '{$telefonoNormalizado}'".($dryRun ? ' [dry-run]' : ''));

                    if (! $dryRun) {
                        $fila->update(['telefono' => $telefonoNormalizado]);
                    }
                }

                continue;
            }

            $conCliente = $filas->filter(fn (WhatsappControl $c) => $c->cliente_id !== null)->unique('cliente_id');

            if ($conCliente->count() > 1) {
                $ids = $filas->pluck('id')->implode(', ');
                $this->error("whatsapp_control: {$filas->count()} filas para '{$telefonoNormalizado}' (ids: {$ids}) tienen cliente_id distintos entre sí — conflicto real, requiere revisión manual, no se toca.");

                continue;
            }

            $keeper = $conCliente->first() ?? $filas->sortByDesc('updated_at')->first();
            $otras = $filas->reject(fn (WhatsappControl $c) => $c->is($keeper));

            $this->line('whatsapp_control: fusionando '.$otras->pluck('id')->implode(', ')." en id={$keeper->id} (telefono='{$telefonoNormalizado}')".($dryRun ? ' [dry-run]' : ''));

            if ($dryRun) {
                continue;
            }

            DB::transaction(function () use ($keeper, $otras, $telefonoNormalizado) {
                $conControlHumano = $otras->first(fn (WhatsappControl $c) => $c->tomado_por_user_id !== null);

                $actualizacion = ['telefono' => $telefonoNormalizado];

                if ($keeper->tomado_por_user_id === null && $conControlHumano !== null) {
                    $actualizacion['estado'] = $conControlHumano->estado;
                    $actualizacion['tomado_por_user_id'] = $conControlHumano->tomado_por_user_id;
                    $actualizacion['tomado_en'] = $conControlHumano->tomado_en;
                }

                $keeper->update($actualizacion);

                foreach ($otras as $fila) {
                    $fila->delete();
                }
            });
        }
    }
}
