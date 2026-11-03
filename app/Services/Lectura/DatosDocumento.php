<?php

namespace App\Services\Lectura;

use App\Enums\Lectura\EstadoLectura;
use App\Enums\Lectura\OrigenDato;
use App\Models\Entrevista\EntrevistaDocumento;
use App\Models\Lectura\DocumentoDato;
use App\Models\Lectura\DocumentoLectura;
use App\Models\User;
use App\Services\Entrevista\ChecklistDocumentos;
use App\Services\Entrevista\TransicionNoPermitida;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Los datos leídos de los documentos (F4.2). Es el único que los corrige:
 * guarda el historial, nunca pisa el valor leído y, si el documento ya
 * estaba validado, reabre la validación (documento madre, 6.4.3).
 */
class DatosDocumento
{
    public function __construct(private readonly ChecklistDocumentos $checklist) {}

    /**
     * Los datos que cuentan de un ítem: los de la última lectura terminada
     * de cada uno de sus archivos vigentes.
     *
     * @return Collection<int, DocumentoDato>
     */
    public function vigentes(EntrevistaDocumento $item): Collection
    {
        $lecturas = DocumentoLectura::query()
            ->where('entrevista_documento_id', $item->id)
            ->where('estado', EstadoLectura::Leida)
            ->whereHas('documento')
            ->orderByDesc('id')
            ->get()
            ->unique('documento_id');

        return DocumentoDato::query()
            ->whereIn('lectura_id', $lecturas->modelKeys())
            ->orderBy('lectura_id')
            ->orderBy('id')
            ->get();
    }

    /**
     * El preparador corrige un dato leído en el visor.
     *
     * @throws TransicionNoPermitida
     */
    public function corregir(DocumentoDato $dato, mixed $valor, string $motivo, User $preparador): void
    {
        if (trim($motivo) === '') {
            throw new TransicionNoPermitida(__('Escribe por qué corriges el dato.'));
        }

        $valor = is_string($valor) ? trim($valor) : $valor;
        $valor = $valor === '' ? null : $valor;

        if ($valor === $dato->valor) {
            return;
        }

        DB::transaction(function () use ($dato, $valor, $motivo, $preparador) {
            $dato->cambios()->create([
                'valor_anterior' => $dato->valor,
                'valor_nuevo' => $valor,
                'motivo' => trim($motivo),
                'actor_id' => $preparador->id,
            ]);

            $dato->update([
                'valor' => $valor,
                'origen' => OrigenDato::Preparador,
                'corregido_por' => $preparador->id,
                'corregido_en' => now(),
                'motivo' => trim($motivo),
            ]);

            if ($dato->item !== null) {
                $this->checklist->reabrirValidacion($dato->item);
            }
        });
    }
}
