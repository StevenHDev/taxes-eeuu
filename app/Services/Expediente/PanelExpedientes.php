<?php

namespace App\Services\Expediente;

use App\Enums\Expediente\EstadoExpediente;
use App\Enums\Expediente\TipoEventoExpediente;
use App\Http\Concerns\ManagesClientes;
use App\Models\Entrevista\Entrevista;
use App\Models\Expediente\Expediente;
use App\Models\Expediente\ExpedienteEvento;
use App\Models\Lectura\DocumentoDatoCambio;
use App\Models\Lectura\Excepcion;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Lo que ve el preparador en el panel (F5.6): expedientes por estado, colas,
 * lo que vence pronto, avisos de los últimos 14 días sacados de los eventos y
 * tiempos del año. Solo cuenta sobre los clientes que el actor puede ver y
 * nunca lleva SSN, montos ni valores leídos: solo nombres, estados y conteos.
 * Los tiempos se calculan en PHP para que SQLite y PostgreSQL den lo mismo.
 */
class PanelExpedientes
{
    use ManagesClientes;

    public const DIAS_AVISOS = 14;

    public const DIAS_VENCIMIENTO = 30;

    /**
     * @return array<string, mixed>
     */
    public function resumen(User $actor, int $taxYear): array
    {
        $clientes = $this->clientesVisiblesPara($actor)->orderByDesc('created_at')->get(['id', 'name', 'created_at']);
        $nombres = $clientes->pluck('name', 'id');

        $expedientes = Expediente::query()
            ->where('tax_year', $taxYear)
            ->whereIn('user_id', $clientes->modelKeys())
            ->get();

        $excepciones = $this->excepciones($clientes->modelKeys(), $taxYear);
        $porEstado = $this->porEstado($expedientes, $clientes->count(), $taxYear);

        $contar = fn (EstadoExpediente $estado) => $porEstado->firstWhere('estado', $estado->value)['cantidad'];

        return [
            'por_estado' => $porEstado->all(),
            'colas' => [
                'listos' => $contar(EstadoExpediente::ListoParaPreparar),
                'por_validar' => $contar(EstadoExpediente::PorValidar),
                'en_consulta' => $contar(EstadoExpediente::EnConsulta),
                'excepciones' => $excepciones['total'],
                'bloqueantes' => $excepciones['bloqueantes'],
                'urls' => [
                    'listos' => $this->urlLista(EstadoExpediente::ListoParaPreparar, $taxYear),
                    'por_validar' => $this->urlLista(EstadoExpediente::PorValidar, $taxYear),
                    'en_consulta' => $this->urlLista(EstadoExpediente::EnConsulta, $taxYear),
                    'excepciones' => route('clientes.excepciones.index'),
                ],
            ],
            'vencen_pronto' => $this->vencenPronto($expedientes, $nombres, $taxYear),
            'avisos' => $this->avisos($expedientes, $nombres, $taxYear),
            'tiempos' => $this->tiempos($expedientes),
            'ultimos_clientes' => $clientes->take(6)->map(fn (User $c) => ['id' => $c->id, 'name' => $c->name])->values()->all(),
        ];
    }

    /**
     * Los 12 estados en orden; `invitado` suma a quienes aún no tienen expediente.
     *
     * @param  Collection<int, Expediente>  $expedientes
     * @return Collection<int, array{estado: string, label: string, cantidad: int, url: string}>
     */
    private function porEstado(Collection $expedientes, int $totalClientes, int $taxYear): Collection
    {
        $cuenta = $expedientes->countBy(fn (Expediente $e) => $e->estado->value);
        $sinExpediente = $totalClientes - $expedientes->count();

        return collect(EstadoExpediente::cases())->map(fn (EstadoExpediente $estado) => [
            'estado' => $estado->value,
            'label' => $estado->label(),
            'cantidad' => (int) $cuenta->get($estado->value, 0) + ($estado === EstadoExpediente::Invitado ? $sinExpediente : 0),
            'url' => $this->urlLista($estado, $taxYear),
        ]);
    }

    private function urlLista(EstadoExpediente $estado, int $taxYear): string
    {
        return route('clientes.index', ['estado' => $estado->value, 'tax_year' => $taxYear]);
    }

    /**
     * @param  list<int>  $clienteIds
     * @return array{total: int, bloqueantes: int}
     */
    private function excepciones(array $clienteIds, int $taxYear): array
    {
        $abiertas = Excepcion::query()->abiertas()
            ->whereHas('entrevista', fn ($q) => $q->whereIn('user_id', $clienteIds)->where('tax_year', $taxYear));

        return [
            'total' => (clone $abiertas)->count(),
            'bloqueantes' => (clone $abiertas)->where('severidad', 'bloqueante')->count(),
        ];
    }

    /**
     * @param  Collection<int, Expediente>  $expedientes
     * @param  Collection<int, string>  $nombres
     * @return list<array<string, mixed>>
     */
    private function vencenPronto(Collection $expedientes, Collection $nombres, int $taxYear): array
    {
        $hoy = now()->startOfDay();
        $limite = $hoy->copy()->addDays(self::DIAS_VENCIMIENTO);

        return $expedientes
            ->filter(fn (Expediente $e) => $e->vence_en !== null
                && ! in_array($e->estado, [EstadoExpediente::Presentado, EstadoExpediente::Aceptado], true)
                && $e->vence_en->startOfDay()->lessThanOrEqualTo($limite))
            ->sortBy(fn (Expediente $e) => $e->vence_en->timestamp)
            ->take(10)
            ->map(fn (Expediente $e) => [
                'cliente_id' => $e->user_id,
                'nombre' => $nombres[$e->user_id] ?? '',
                'estado' => $e->estado->value,
                'estado_label' => $e->estado->label(),
                'vence_en' => $e->vence_en->toDateString(),
                'dias' => (int) $hoy->diffInDays($e->vence_en->startOfDay(), false),
                'url' => route('clientes.show', ['cliente' => $e->user_id, 'tax_year' => $taxYear]),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Expediente>  $expedientes
     * @param  Collection<int, string>  $nombres
     * @return list<array<string, mixed>>
     */
    private function avisos(Collection $expedientes, Collection $nombres, int $taxYear): array
    {
        $porId = $expedientes->keyBy('id');

        return ExpedienteEvento::query()
            ->whereIn('expediente_id', $porId->keys())
            ->where('tipo', TipoEventoExpediente::Estado)
            ->where('created_at', '>=', now()->subDays(self::DIAS_AVISOS))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (ExpedienteEvento $evento) use ($porId, $nombres, $taxYear) {
                $tipo = $this->tipoAviso($evento);
                $clienteId = $porId[$evento->expediente_id]->user_id;

                return $tipo === null ? null : [
                    'tipo' => $tipo,
                    'texto' => $this->textoAviso($tipo, (string) ($nombres[$clienteId] ?? '')),
                    'cliente_id' => $clienteId,
                    'nombre' => $nombres[$clienteId] ?? '',
                    'creado_en' => $evento->created_at->toIso8601String(),
                    'url' => route('clientes.show', ['cliente' => $clienteId, 'tax_year' => $taxYear]),
                ];
            })
            ->filter()
            ->take(20)
            ->values()
            ->all();
    }

    private function tipoAviso(ExpedienteEvento $evento): ?string
    {
        if ($evento->estado_nuevo === EstadoExpediente::ListoParaPreparar) {
            return 'listo_para_preparar';
        }

        return match (true) {
            $evento->estado_anterior === EstadoExpediente::EnEntrevista => 'termino_entrevista',
            $evento->estado_anterior === EstadoExpediente::EnRecoleccion && $evento->estado_nuevo === EstadoExpediente::PorValidar => 'subio_documentos',
            $evento->estado_anterior === EstadoExpediente::EnConsulta => 'respondio_consulta',
            default => null,
        };
    }

    private function textoAviso(string $tipo, string $nombre): string
    {
        return match ($tipo) {
            'listo_para_preparar' => __(':cliente está lista para preparar', ['cliente' => $nombre]),
            'termino_entrevista' => __(':cliente terminó la entrevista', ['cliente' => $nombre]),
            'subio_documentos' => __(':cliente subió sus documentos', ['cliente' => $nombre]),
            default => __(':cliente respondió la consulta', ['cliente' => $nombre]),
        };
    }

    /**
     * @param  Collection<int, Expediente>  $expedientes
     * @return array<string, int|float>|null
     */
    private function tiempos(Collection $expedientes): ?array
    {
        if ($expedientes->isEmpty()) {
            return null;
        }

        $eventos = ExpedienteEvento::query()
            ->whereIn('expediente_id', $expedientes->modelKeys())
            ->where('tipo', TipoEventoExpediente::Estado)
            ->orderBy('id')
            ->get()
            ->groupBy('expediente_id');

        $iniciadas = Entrevista::query()
            ->whereIn('id', $expedientes->pluck('entrevista_id')->filter())
            ->pluck('iniciada_en', 'id');

        $dias = [];
        $horas = [];
        $consultas = [];

        foreach ($expedientes as $expediente) {
            $propios = $eventos->get($expediente->id, collect());
            $primero = fn (EstadoExpediente $estado): ?CarbonInterface => $propios->first(fn (ExpedienteEvento $e) => $e->estado_nuevo === $estado)?->created_at;

            $consultas[] = $propios->filter(fn (ExpedienteEvento $e) => $e->estado_nuevo === EstadoExpediente::EnConsulta)->count();

            $inicio = $iniciadas[$expediente->entrevista_id] ?? null;
            $listo = $primero(EstadoExpediente::ListoParaPreparar);

            if ($inicio !== null && $listo !== null) {
                $dias[] = Carbon::parse($inicio)->diffInSeconds($listo, false) / 86400;
            }

            $revision = $primero(EstadoExpediente::EnRevision);
            $preparado = $primero(EstadoExpediente::PreparadoEnTaxwise);

            if ($revision !== null && $preparado !== null) {
                $horas[] = $revision->diffInSeconds($preparado, false) / 3600;
            }
        }

        $correcciones = DocumentoDatoCambio::query()
            ->join('documento_datos', 'documento_datos.id', '=', 'documento_dato_cambios.dato_id')
            ->join('entrevista_documentos', 'entrevista_documentos.id', '=', 'documento_datos.entrevista_documento_id')
            ->whereIn('entrevista_documentos.entrevista_id', $expedientes->pluck('entrevista_id')->filter())
            ->count();

        $muestra = $expedientes->count();

        return [
            'dias_hasta_listo' => $this->promedio($dias),
            'horas_preparacion' => $this->promedio($horas),
            'consultas_por_expediente' => round(array_sum($consultas) / $muestra, 1),
            'correcciones_por_expediente' => round($correcciones / $muestra, 1),
            'muestra' => $muestra,
        ];
    }

    /**
     * @param  list<float>  $valores
     */
    private function promedio(array $valores): ?float
    {
        return $valores === [] ? null : round(array_sum($valores) / count($valores), 1);
    }
}
