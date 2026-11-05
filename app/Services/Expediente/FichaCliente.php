<?php

namespace App\Services\Expediente;

use App\Enums\UserRole;
use App\Models\Entrevista\Entrevista;
use App\Models\Expediente\Expediente;
use App\Models\User;
use App\Services\Entrevista\ChecklistDocumentos;
use App\Services\Lectura\ColaExcepciones;
use Illuminate\Support\Facades\Route;

/**
 * Lo que comparten todas las pestañas de la ficha del cliente (prop `ficha`):
 * cliente, año, estado y su fecha, turno, vencimiento, conteos y pestañas
 * disponibles. Solo lee: si todavía no hay expediente, el estado sale del
 * diagnóstico. Nada sensible: sin SSN, montos ni datos del bloque C.
 */
class FichaCliente
{
    /** Clave de pestaña → nombre de la ruta, en el orden en que se muestran. */
    public const PESTANAS = [
        'resumen' => 'clientes.show',
        'entrevista' => 'clientes.ficha.entrevista',
        'documentos' => 'clientes.ficha.documentos',
        'excepciones' => 'clientes.ficha.excepciones',
        'revision' => 'clientes.revision.show',
        'hoja' => 'clientes.hoja-taxwise.show',
        'debida_diligencia' => 'clientes.debida-diligencia.show',
        'historial' => 'clientes.ficha.historial',
    ];

    public function __construct(
        private readonly DiagnosticoExpediente $diagnostico,
        private readonly ChecklistDocumentos $checklist,
        private readonly ColaExcepciones $cola,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function encabezado(User $cliente, int $taxYear, User $actor): array
    {
        $expediente = Expediente::query()->where('user_id', $cliente->id)->where('tax_year', $taxYear)->first();
        $diagnostico = $expediente === null ? $this->diagnostico->para($cliente, $taxYear) : null;
        $estado = $expediente?->estado ?? $diagnostico->estado;

        $documentos = $this->checklist->resumenPorCliente([$cliente->id], $taxYear)[$cliente->id] ?? null;
        $excepciones = $this->cola->conteo($cliente, $taxYear);

        return [
            'cliente' => [
                'id' => $cliente->id,
                'name' => $cliente->name,
                'email' => $cliente->email,
                'phone' => $cliente->phone,
                'role' => $cliente->role->value,
                'es_prueba' => (bool) $cliente->es_prueba,
                'archivado' => $cliente->trashed(),
            ],
            'tax_year' => $taxYear,
            'anios' => $this->anios($cliente, $taxYear),
            'expediente' => [
                'existe' => $expediente !== null,
                'estado' => $estado->value,
                'label' => $estado->label(),
                'desde' => $expediente?->estado_desde->toIso8601String(),
                'turno' => $estado->turno(),
                'vence_en' => $expediente?->vence_en?->toDateString(),
                'cambios_tras_presentar' => $expediente?->cambios_tras_presentar_en !== null,
            ],
            'conteos' => [
                'excepciones' => $excepciones['total'],
                'bloqueantes' => $excepciones['bloqueantes'],
                'documentos_por_validar' => $documentos['por_revisar'] ?? 0,
                'documentos_faltan' => $documentos['faltan'] ?? 0,
            ],
            'pestanas' => $this->pestanas($cliente, $taxYear),
            'puede_administrar' => $actor->role === UserRole::Administrator,
        ];
    }

    /**
     * Solo las pestañas cuya ruta existe: las demás se llenan en otras subtareas.
     *
     * @return list<array{clave: string, url: string}>
     */
    private function pestanas(User $cliente, int $taxYear): array
    {
        $pestanas = [];

        foreach (self::PESTANAS as $clave => $ruta) {
            if (Route::has($ruta)) {
                $pestanas[] = ['clave' => $clave, 'url' => route($ruta, ['cliente' => $cliente->id, 'tax_year' => $taxYear])];
            }
        }

        return $pestanas;
    }

    /**
     * Los años con entrevista o expediente del cliente, más el año de la ficha
     * y el año fiscal en curso, del más reciente al más antiguo.
     *
     * @return list<int>
     */
    private function anios(User $cliente, int $taxYear): array
    {
        return collect([$taxYear, (int) config('tax.current_tax_year')])
            ->merge(Entrevista::query()->where('user_id', $cliente->id)->pluck('tax_year'))
            ->merge(Expediente::query()->where('user_id', $cliente->id)->pluck('tax_year'))
            ->map(fn ($anio) => (int) $anio)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }
}
