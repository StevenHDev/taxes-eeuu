<?php

namespace App\Services\Entrevista;

use App\Enums\CatalogoFiscal\SeveridadRegla;
use App\Enums\Entrevista\EstadoDocumento;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaDocumento;
use App\Models\Lectura\Excepcion;
use App\Models\User;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * El checklist de documentos guardado (F3.2, documento madre 5.1 y 5.3).
 * Es el único que crea ítems y les cambia el estado.
 *
 * - `sincronizar()` lo pone al día con lo que activa la entrevista: un ítem
 *   por documento, por persona y por fuente (el W-2 por cada empleo de D1.1,
 *   el 1095-A por cada póliza de F4.1). Lo que deja de aplicar se retira,
 *   nunca se borra; vuelve si se activa de nuevo.
 * - Las transiciones dicen quién puede hacer cada cambio. Una no permitida
 *   lanza TransicionNoPermitida y no escribe nada.
 */
class ChecklistDocumentos
{
    /**
     * `uno_por` del catálogo → pregunta que dice cuántos hay.
     */
    private const CUANTOS = [
        'empleo' => 'D1.1',
        'poliza' => 'F4.1',
    ];

    /** Tope de ítems que crea una cantidad respondida (el cliente puede agregar más). */
    private const MAXIMO_POR_CANTIDAD = 10;

    public function __construct(
        private readonly CatalogoFiscal $catalogo,
        private readonly MotorEntrevista $motor,
    ) {}

    public function sincronizar(Entrevista $entrevista, ?SituacionEntrevista $situacion = null): void
    {
        DB::transaction(function () use ($entrevista, $situacion) {
            Entrevista::query()->whereKey($entrevista->id)->lockForUpdate()->first();
            $situacion ??= $this->motor->situacion($entrevista->unsetRelation('respuestas')->unsetRelation('personas'));

            $catalogo = $this->catalogo->documentos($entrevista->tax_year)->keyBy('codigo');
            $deseados = [];

            foreach ($situacion->documentos as $d) {
                $documento = $catalogo->get($d['codigo']);

                if ($documento === null) {
                    continue;
                }

                $cuantos = $this->cuantos($documento, $situacion);

                for ($numero = 1; $numero <= $cuantos; $numero++) {
                    $deseados[self::clave($d['codigo'], $d['persona_id'], $numero)] = [$d['codigo'], $d['persona_id'], $numero];
                }
            }

            $activados = collect($situacion->documentos)
                ->map(fn (array $d) => self::clave($d['codigo'], $d['persona_id'], 0))
                ->flip();

            $existentes = $entrevista->documentos()->get()
                ->keyBy(fn (EntrevistaDocumento $i) => self::clave($i->documento_codigo, $i->persona_id, $i->numero));

            foreach ($deseados as $clave => [$codigo, $personaId, $numero]) {
                $item = $existentes->get($clave);

                if ($item === null) {
                    $entrevista->documentos()->create([
                        'documento_codigo' => $codigo,
                        'persona_id' => $personaId,
                        'numero' => $numero,
                        'estado' => EstadoDocumento::Pendiente,
                    ]);
                } elseif ($item->retirado_en !== null) {
                    $item->update(['retirado_en' => null]);
                }
            }

            foreach ($existentes as $clave => $item) {
                if ($item->esOtro() || $item->retirado_en !== null || isset($deseados[$clave])) {
                    continue;
                }

                // Lo que agregó el cliente ("otro banco") se queda mientras
                // el documento siga activado; si se desactiva, se retira.
                $sigueActivado = $activados->has(self::clave($item->documento_codigo, $item->persona_id, 0));

                if ($item->agregado_por_cliente && $sigueActivado) {
                    continue;
                }

                $item->update(['retirado_en' => now()]);
            }
        });
    }

    /**
     * Los ítems vigentes en el orden en que se muestran.
     *
     * @return Collection<int, EntrevistaDocumento>
     */
    public function vigentes(Entrevista $entrevista): Collection
    {
        return $entrevista->documentos()->vigentes()->with('archivos')->orderBy('id')->get();
    }

    /**
     * El primer ítem vigente de un documento (y persona), para la carga de
     * 2.6 que todavía no elige el número. Lo crea si falta.
     */
    public function primero(Entrevista $entrevista, string $codigo, ?int $personaId): ?EntrevistaDocumento
    {
        $buscar = fn () => $entrevista->documentos()->vigentes()
            ->where('documento_codigo', $codigo)
            ->where('persona_id', $personaId)
            ->orderBy('numero')
            ->first();

        $item = $buscar();

        if ($item === null) {
            $this->sincronizar($entrevista);
            $item = $buscar();
        }

        return $item;
    }

    /**
     * Todos los ítems vigentes están validados o en "no aplica" (5.3).
     */
    public function completo(Entrevista $entrevista): bool
    {
        $vigentes = $entrevista->documentos()->vigentes()->get();

        return $vigentes->isNotEmpty() && $vigentes->every(fn (EntrevistaDocumento $i) => $i->estado->cerrado());
    }

    /**
     * Cuántos ítems vigentes hay en cada grupo (EstadoDocumento::grupo()),
     * por cliente, para la ficha y la lista de clientes del preparador.
     *
     * @param  list<int>  $clienteIds
     * @return array<int, array{faltan: int, por_revisar: int, validados: int, no_aplica: int, total: int, completo: bool}>
     */
    public function resumenPorCliente(array $clienteIds, int $taxYear): array
    {
        $filas = EntrevistaDocumento::query()
            ->join('entrevistas', 'entrevistas.id', '=', 'entrevista_documentos.entrevista_id')
            ->whereIn('entrevistas.user_id', $clienteIds)
            ->where('entrevistas.tax_year', $taxYear)
            ->whereNull('entrevista_documentos.retirado_en')
            ->selectRaw('entrevistas.user_id as user_id, entrevista_documentos.estado as estado, count(*) as cuantos')
            ->groupBy('entrevistas.user_id', 'entrevista_documentos.estado')
            ->toBase()
            ->get();

        $resumen = [];

        foreach ($filas as $fila) {
            $grupo = EstadoDocumento::from($fila->estado)->grupo();
            $resumen[$fila->user_id] ??= ['faltan' => 0, 'por_revisar' => 0, 'validados' => 0, 'no_aplica' => 0, 'total' => 0, 'completo' => false];
            $resumen[$fila->user_id][$grupo] += (int) $fila->cuantos;
            $resumen[$fila->user_id]['total'] += (int) $fila->cuantos;
        }

        foreach ($resumen as &$r) {
            $r['completo'] = $r['total'] > 0 && $r['faltan'] === 0 && $r['por_revisar'] === 0;
        }

        return $resumen;
    }

    /**
     * Otro del mismo documento ("otro banco", "otro empleo"), o un "otro
     * documento" fuera de la lista con su descripción.
     *
     * @throws TransicionNoPermitida
     */
    public function agregar(Entrevista $entrevista, string $codigo, ?int $personaId, ?string $descripcion = null): EntrevistaDocumento
    {
        return DB::transaction(function () use ($entrevista, $codigo, $personaId, $descripcion) {
            Entrevista::query()->whereKey($entrevista->id)->lockForUpdate()->first();

            if ($codigo === EntrevistaDocumento::OTRO) {
                if (blank($descripcion)) {
                    throw new TransicionNoPermitida(__('Cuéntanos qué documento es.'));
                }
            } elseif (! $entrevista->documentos()->vigentes()->where('documento_codigo', $codigo)->where('persona_id', $personaId)->exists()) {
                throw new TransicionNoPermitida(__('Este documento no está en tu lista.'));
            }

            $numero = (int) $entrevista->documentos()->where('documento_codigo', $codigo)->where('persona_id', $personaId)->max('numero') + 1;

            return $entrevista->documentos()->create([
                'documento_codigo' => $codigo,
                'persona_id' => $personaId,
                'numero' => $numero,
                'agregado_por_cliente' => true,
                'descripcion' => $descripcion,
                'estado' => EstadoDocumento::Pendiente,
            ]);
        });
    }

    /**
     * El cliente subió un archivo al ítem. Un documento validado vuelve a
     * revisión: el preparador ve el archivo nuevo.
     */
    public function archivoSubido(EntrevistaDocumento $item): void
    {
        if ($item->estado !== EstadoDocumento::Subido) {
            $item->update(['estado' => EstadoDocumento::Subido, 'motivo_no_aplica' => null]);
        }
    }

    /**
     * La lectura terminó bien (F4.4). Solo cambia lo que está recién subido o
     * no se había podido leer: si el preparador ya lo validó u observó, se
     * queda como está.
     */
    public function leido(EntrevistaDocumento $item): void
    {
        $item->refresh();

        if (in_array($item->estado, [EstadoDocumento::Subido, EstadoDocumento::NoSePudoLeer], true)) {
            $item->update(['estado' => EstadoDocumento::Leido]);
        }
    }

    /**
     * La lectura falló en todos sus intentos (F4.4): el preparador lo ve
     * "por revisar" y escribe los datos a mano. Solo desde `subido`.
     */
    public function noSePudoLeer(EntrevistaDocumento $item): void
    {
        $item->refresh();

        if ($item->estado === EstadoDocumento::Subido) {
            $item->update(['estado' => EstadoDocumento::NoSePudoLeer]);
        }
    }

    /**
     * @throws TransicionNoPermitida
     */
    public function marcarNoAplica(EntrevistaDocumento $item, string $motivo): void
    {
        $this->exigir($item, [EstadoDocumento::Pendiente, EstadoDocumento::ConObservacion]);

        if (trim($motivo) === '') {
            throw new TransicionNoPermitida(__('Cuéntanos por qué no aplica.'));
        }

        $item->update(['estado' => EstadoDocumento::NoAplica, 'motivo_no_aplica' => trim($motivo)]);
    }

    /**
     * @throws TransicionNoPermitida
     */
    public function validar(EntrevistaDocumento $item, User $preparador): void
    {
        $this->exigir($item, [EstadoDocumento::Subido, EstadoDocumento::Leido, EstadoDocumento::ConObservacion]);
        $this->exigirArchivos($item);

        if (Excepcion::query()->abiertas()->where('entrevista_documento_id', $item->id)->where('severidad', SeveridadRegla::Bloqueante)->exists()) {
            throw new TransicionNoPermitida(__('Este documento tiene una regla bloqueante sin resolver: corrige el dato o acepta la excepción.'));
        }

        $item->update([
            'estado' => EstadoDocumento::Validado,
            'observacion' => null,
            'revisado_por' => $preparador->id,
            'revisado_en' => now(),
        ]);
    }

    /**
     * Un dato del documento cambió después de validarlo: la validación se
     * reabre y el preparador lo vuelve a mirar (documento madre, 6.4.3).
     */
    public function reabrirValidacion(EntrevistaDocumento $item): void
    {
        if ($item->estado === EstadoDocumento::Validado) {
            $item->update(['estado' => EstadoDocumento::Leido]);
        }
    }

    /**
     * La observación vuelve al cliente como consulta (5.3).
     *
     * @throws TransicionNoPermitida
     */
    public function observar(EntrevistaDocumento $item, string $observacion, User $preparador): void
    {
        $this->exigir($item, [EstadoDocumento::Subido, EstadoDocumento::Leido, EstadoDocumento::NoSePudoLeer, EstadoDocumento::Validado]);

        if (trim($observacion) === '') {
            throw new TransicionNoPermitida(__('Escribe qué debe revisar el cliente.'));
        }

        $item->update([
            'estado' => EstadoDocumento::ConObservacion,
            'observacion' => trim($observacion),
            'revisado_por' => $preparador->id,
            'revisado_en' => now(),
        ]);
    }

    /**
     * @throws TransicionNoPermitida
     */
    public function rechazarNoAplica(EntrevistaDocumento $item, string $motivo, User $preparador): void
    {
        $this->exigir($item, [EstadoDocumento::NoAplica]);

        if (trim($motivo) === '') {
            throw new TransicionNoPermitida(__('Escribe por qué sí hace falta.'));
        }

        $item->update([
            'estado' => EstadoDocumento::NoAplicaRechazado,
            'observacion' => trim($motivo),
            'revisado_por' => $preparador->id,
            'revisado_en' => now(),
        ]);
    }

    public static function clave(string $codigo, ?int $personaId, int $numero): string
    {
        return "{$codigo}#{$personaId}#{$numero}";
    }

    /**
     * @param  array<string, mixed>  $documento
     */
    private function cuantos(array $documento, SituacionEntrevista $situacion): int
    {
        $pregunta = self::CUANTOS[$documento['uno_por'] ?? ''] ?? null;
        $valor = $pregunta === null ? null : ($situacion->respuestas[$pregunta] ?? null);

        return is_numeric($valor) ? max(1, min(self::MAXIMO_POR_CANTIDAD, (int) $valor)) : 1;
    }

    /**
     * @param  list<EstadoDocumento>  $permitidos
     *
     * @throws TransicionNoPermitida
     */
    private function exigir(EntrevistaDocumento $item, array $permitidos): void
    {
        if ($item->retirado_en !== null) {
            throw new TransicionNoPermitida(__('Este documento ya no está en la lista.'));
        }

        if (! in_array($item->estado, $permitidos, true)) {
            throw new TransicionNoPermitida(__('Este documento no se puede cambiar en su estado actual.'));
        }
    }

    /**
     * @throws TransicionNoPermitida
     */
    private function exigirArchivos(EntrevistaDocumento $item): void
    {
        if (! $item->archivos()->exists()) {
            throw new TransicionNoPermitida(__('Este documento no tiene archivos.'));
        }
    }
}
