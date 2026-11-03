<?php

namespace App\Services\Lectura;

use App\Enums\CatalogoFiscal\SeveridadRegla;
use App\Enums\Lectura\EstadoExcepcion;
use App\Enums\Lectura\MotivoConfianza;
use App\Http\Concerns\ManagesClientes;
use App\Models\Lectura\Excepcion;
use App\Models\User;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use App\Services\Entrevista\ChecklistDocumentos;
use App\Services\Entrevista\TransicionNoPermitida;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * La cola de excepciones del preparador (F4.7a): las abiertas de sus
 * clientes (el administrador ve todas) y las tres salidas de cada una:
 * corregir (abre el visor, no cambia el estado), aceptar con nota y
 * consultar al cliente. Nada de lo que sale lleva valores leídos, SSN ni
 * montos; el rastro de cada salida queda en la bitácora por el observer
 * del modelo (quién, cuándo y qué atributos cambiaron).
 */
class ColaExcepciones
{
    use ManagesClientes;

    public function __construct(
        private readonly CatalogoFiscal $catalogo,
        private readonly ChecklistDocumentos $checklist,
    ) {}

    /**
     * @param  array{severidad?: string|null, estado?: string|null, cliente?: int|string|null}  $filtros
     * @return list<array<string, mixed>>
     */
    public function lista(User $actor, array $filtros = []): array
    {
        $severidad = SeveridadRegla::tryFrom((string) ($filtros['severidad'] ?? ''));
        $estado = EstadoExcepcion::tryFrom((string) ($filtros['estado'] ?? ''));
        $cliente = (int) ($filtros['cliente'] ?? 0);

        $excepciones = Excepcion::query()
            ->abiertas()
            ->whereHas('entrevista', fn ($q) => $q->whereIn('user_id', $this->clientesVisiblesPara($actor)->select('id')))
            ->when($severidad, fn ($q) => $q->where('severidad', $severidad))
            ->when($estado?->abierta(), fn ($q) => $q->where('estado', $estado))
            ->when($cliente > 0, fn ($q) => $q->whereHas('entrevista', fn ($e) => $e->where('user_id', $cliente)))
            ->with(['entrevista.user:id,name', 'item:id,entrevista_id,documento_codigo,numero', 'dato:id,casilla'])
            ->orderByRaw("case severidad when 'bloqueante' then 0 when 'revision' then 1 else 2 end")
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return $excepciones->map(fn (Excepcion $e) => $this->fila($e))->values()->all();
    }

    /**
     * Cuántas abiertas tiene un cliente: el total y las que bloquean.
     *
     * @return array{total: int, bloqueantes: int}
     */
    public function conteo(User $cliente, int $taxYear): array
    {
        $abiertas = Excepcion::query()->abiertas()
            ->whereHas('entrevista', fn ($q) => $q->where('user_id', $cliente->id)->where('tax_year', $taxYear));

        return [
            'total' => (clone $abiertas)->count(),
            'bloqueantes' => (clone $abiertas)->where('severidad', SeveridadRegla::Bloqueante)->count(),
        ];
    }

    /**
     * @throws TransicionNoPermitida
     */
    public function aceptar(Excepcion $excepcion, string $nota, User $preparador): void
    {
        DB::transaction(function () use ($excepcion, $nota, $preparador) {
            $this->abierta($excepcion);

            $excepcion->update([
                'estado' => EstadoExcepcion::Aceptada,
                'resuelta_por' => $preparador->id,
                'resuelta_en' => now(),
                'nota' => trim($nota),
            ]);
        });
    }

    /**
     * Le devuelve el documento al cliente con la pregunta del preparador
     * (queda "con observación") y la excepción pasa a consultada. Todo o
     * nada: si el ítem no se puede devolver, la excepción no cambia.
     *
     * @throws TransicionNoPermitida
     */
    public function consultar(Excepcion $excepcion, string $texto, User $preparador): void
    {
        DB::transaction(function () use ($excepcion, $texto, $preparador) {
            $this->abierta($excepcion);

            $item = $excepcion->item;

            if ($item === null) {
                throw new TransicionNoPermitida(__('Esta excepción no tiene un documento para devolverle al cliente.'));
            }

            $this->checklist->observar($item, $texto, $preparador);

            $excepcion->update(['estado' => EstadoExcepcion::Consultada]);
        });
    }

    /**
     * Relee la excepción con candado: dos preparadores no la resuelven a la vez.
     *
     * @throws TransicionNoPermitida
     */
    private function abierta(Excepcion $excepcion): void
    {
        $excepcion->refresh();

        if (! $excepcion->estado->abierta()) {
            throw new TransicionNoPermitida(__('Esta excepción ya no está abierta.'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function fila(Excepcion $e): array
    {
        $anio = $e->entrevista->tax_year;
        $regla = $this->catalogo->reglas($anio)->firstWhere('codigo', $e->regla_codigo);
        $documento = $e->item === null ? null : $this->catalogo->documentos($anio)->firstWhere('codigo', $e->item->documento_codigo);
        $motivo = $e->regla_codigo === 'R-03' ? MotivoConfianza::tryFrom((string) ($e->detalle['motivo_confianza'] ?? '')) : null;
        $clienteId = $e->entrevista->user_id;

        return [
            'id' => $e->id,
            'regla' => ['codigo' => $e->regla_codigo, 'descripcion' => $regla['descripcion'] ?? null],
            'severidad' => $e->severidad->value,
            'estado' => $e->estado->value,
            'cliente' => ['id' => $clienteId, 'nombre' => $e->entrevista->user->name],
            'item' => $e->item === null ? null : [
                'id' => $e->item->id,
                'documento_codigo' => $e->item->documento_codigo,
                'nombre' => $documento['nombre_sencillo_es'] ?? $e->item->documento_codigo,
            ],
            'casilla' => $e->dato?->casilla ?? ($e->detalle['casilla'] ?? null),
            'motivo_confianza' => $motivo === null ? null : [
                'valor' => $motivo->value,
                'titulo' => $motivo->titulo(),
                'explicacion' => $motivo->explicacion(),
            ],
            'creada_en' => $e->created_at->toIso8601String(),
            'enlaces' => [
                'corregir' => $e->item !== null && Route::has('clientes.documentos.visor')
                    ? route('clientes.documentos.visor', ['cliente' => $clienteId, 'item' => $e->item->id])
                    : null,
                'aceptar' => route('clientes.excepciones.aceptar', $e),
                'consultar' => $e->item === null ? null : route('clientes.excepciones.consultar', $e),
            ],
        ];
    }
}
