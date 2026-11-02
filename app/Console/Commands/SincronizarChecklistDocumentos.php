<?php

namespace App\Console\Commands;

use App\Enums\Entrevista\EstadoDocumento;
use App\Models\Documento;
use App\Models\Entrevista\Entrevista;
use App\Services\Entrevista\ChecklistDocumentos;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Arma el checklist de documentos (F3.2) de las entrevistas que ya existían
 * y le liga los archivos subidos con la lista de 2.6 (por documento y
 * persona, al ítem número 1). Se puede correr de nuevo: salta lo que ya
 * está armado y ligado. Nunca muestra nombres de archivo ni valores.
 */
class SincronizarChecklistDocumentos extends Command
{
    protected $signature = 'documentos:sincronizar';

    protected $description = 'Arma el checklist de documentos de cada entrevista y le liga los archivos ya subidos';

    public function handle(ChecklistDocumentos $checklist): int
    {
        $entrevistas = 0;
        $ligados = 0;

        Entrevista::query()->orderBy('id')->chunkById(100, function ($lote) use ($checklist, &$entrevistas, &$ligados) {
            foreach ($lote as $entrevista) {
                $checklist->sincronizar($entrevista);
                $entrevistas++;

                $ligados += DB::transaction(fn () => $this->ligarArchivos($entrevista));
            }
        });

        $this->info("Entrevistas: {$entrevistas}. Archivos ligados a su documento: {$ligados}.");

        return self::SUCCESS;
    }

    private function ligarArchivos(Entrevista $entrevista): int
    {
        $ligados = 0;

        $sueltos = Documento::query()
            ->where('entrevista_id', $entrevista->id)
            ->whereNotNull('documento_codigo')
            ->whereNull('entrevista_documento_id')
            ->get();

        foreach ($sueltos as $archivo) {
            $item = $entrevista->documentos()
                ->where('documento_codigo', $archivo->documento_codigo)
                ->where('persona_id', $archivo->persona_id)
                ->orderByRaw('retirado_en is not null')
                ->orderBy('numero')
                ->first();

            if ($item === null) {
                // Un documento que ya no se activa (y nunca tuvo ítem): se
                // le crea uno retirado para no perder el vínculo.
                $item = $entrevista->documentos()->create([
                    'documento_codigo' => $archivo->documento_codigo,
                    'persona_id' => $archivo->persona_id,
                    'numero' => 1,
                    'estado' => EstadoDocumento::Pendiente,
                    'retirado_en' => now(),
                ]);
            }

            $archivo->update(['entrevista_documento_id' => $item->id]);
            $ligados++;

            if ($item->estado === EstadoDocumento::Pendiente) {
                $item->update(['estado' => EstadoDocumento::Subido]);
            }
        }

        return $ligados;
    }
}
