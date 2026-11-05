<?php

namespace App\Services\Expediente;

use App\Enums\TaxForm;
use App\Models\CampoCatalogo;
use App\Models\CampoDerivationLog;
use App\Models\DeterminacionFiscal;
use App\Models\Documento;
use App\Models\FormaCliente;
use App\Models\User;
use App\Services\DocumentoDuplicadoService;
use App\Support\TaxFieldCatalog;

/**
 * Los datos del formulario anterior (campos, formas, determinaciones y trazas
 * de derivación) que la pestaña Historial muestra de solo lectura. Es el código
 * que antes vivía en ClienteController@show, con la misma forma. Los valores
 * sensibles salen enmascarados por el modelo (`CampoCliente::valor`).
 */
class FichaAnterior
{
    public function __construct(private readonly DocumentoDuplicadoService $duplicados) {}

    /**
     * @return array<string, mixed>|null null si el cliente no tiene nada de ese año
     */
    public function para(User $cliente, int $taxYear, User $actor): ?array
    {
        $cliente->load([
            'formasCliente' => fn ($query) => $query->where('tax_year', $taxYear),
            'camposCliente' => fn ($query) => $query->where('tax_year', $taxYear)->with('documento')->orderBy('campo'),
            'determinacionesFiscales' => fn ($query) => $query->where('tax_year', $taxYear),
        ]);

        $logs = CampoDerivationLog::query()
            ->where('user_id', $cliente->id)
            ->where('tax_year', $taxYear)
            ->with('documento:id,file_original_name')
            ->latest('id')
            ->get();

        // Las formas solas no cuentan: el agente crea una vacía (form_1040, en progreso)
        // con cada cliente nuevo, así que sin campos, determinaciones ni trazas no hay historial.
        if ($cliente->camposCliente->isEmpty() && $cliente->determinacionesFiscales->isEmpty() && $logs->isEmpty()) {
            return null;
        }

        $camposCargados = $cliente->camposCliente->map(fn ($c) => "{$c->forma}:{$c->campo}");
        $unicosCargados = $cliente->camposCliente
            ->filter(fn ($c) => TaxFieldCatalog::isUnicoPorCliente($taxYear, $c->campo))
            ->pluck('campo');

        $formasOfrecidas = collect(TaxForm::enAlcance())
            ->concat($cliente->formasCliente->map(fn (FormaCliente $f) => TaxForm::tryFrom($f->forma))->filter())
            ->unique(fn (TaxForm $forma) => $forma->value);

        $disponiblePorForma = $formasOfrecidas
            ->flatMap(fn (TaxForm $forma) => collect(TaxFieldCatalog::fieldsFor($taxYear, $forma))
                ->reject(fn (array $campo) => TaxFieldCatalog::isUnicoPorCliente($taxYear, $campo['campo'])
                    || $camposCargados->contains("{$forma->value}:{$campo['campo']}"))
                ->map(fn (array $campo) => [
                    'forma' => $forma->value,
                    'campo' => $campo['campo'],
                    'tipo_campo' => $campo['tipo']->value,
                    'tipo_dato' => $campo['tipo_dato']?->value,
                    'subcampos' => $campo['subcampos'] ?? null,
                    'formatos_aceptados' => $campo['formatos_aceptados'] ?? null,
                    'obligatorio' => $campo['obligatorio'],
                ]));

        $disponibleUnicos = CampoCatalogo::query()
            ->where('unico_por_cliente', true)
            ->where('tax_year', $taxYear)
            ->orderBy('clave')
            ->get()
            ->reject(fn (CampoCatalogo $c) => $unicosCargados->contains($c->clave))
            ->map(fn (CampoCatalogo $c) => [
                'forma' => $c->forma,
                'campo' => $c->clave,
                'tipo_campo' => $c->tipo_campo->value,
                'tipo_dato' => $c->tipo_dato?->value,
                'subcampos' => $c->subcampos,
                'formatos_aceptados' => $c->formatos_aceptados,
                'obligatorio' => $c->obligatorio,
            ]);

        return [
            'catalogoDisponible' => $disponiblePorForma->concat($disponibleUnicos)->values(),
            'formas' => $cliente->formasCliente->map(fn (FormaCliente $f) => [
                'forma' => $f->forma,
                'forma_label' => TaxForm::from($f->forma)->label(),
                'estado' => $f->estado,
                'revisado_en' => $f->revisado_en,
            ]),
            'campos' => $cliente->camposCliente->map(function ($c) use ($taxYear, $actor) {
                $definicion = TaxFieldCatalog::find($taxYear, $c->forma, $c->campo);

                return [
                    'forma' => $c->forma,
                    'campo' => $c->campo,
                    'tipo_campo' => $c->tipo_campo,
                    'tipo_dato' => $definicion['tipo_dato']?->value,
                    'subcampos' => $definicion['subcampos'] ?? null,
                    'modo' => $c->modo,
                    'estado' => $c->estado,
                    'advertencia' => $c->advertencia,
                    'valor' => $c->valor,
                    'es_sensible' => $c->esSensible(),
                    'formatos_aceptados' => $definicion['formatos_aceptados'] ?? null,
                    'obligatorio' => $definicion['obligatorio'] ?? false,
                    'documento' => $c->documento ? [
                        'id' => $c->documento->id,
                        'file_original_name' => $c->documento->file_original_name,
                        'file_mime_type' => $c->documento->file_mime_type,
                        'formato' => $c->documento->formato,
                        'estado_validacion' => $c->documento->estado_validacion,
                        'download_url' => $c->documento->downloadUrl(),
                        'preview_url' => $c->documento->previewUrl(),
                        'duplicado' => $this->duplicadoDe($c->documento, $actor),
                    ] : null,
                    'updated_at' => $c->updated_at,
                ];
            }),
            'determinaciones' => $cliente->determinacionesFiscales->map(fn (DeterminacionFiscal $d) => [
                'tipo' => $d->tipo,
                'resultado' => $d->resultado,
                'version_reglas' => $d->version_reglas,
                'calculado_en' => $d->calculado_en,
            ]),
            'derivationLogs' => $logs->map(fn (CampoDerivationLog $log) => [
                'documento_campo' => $log->documento_campo,
                'documento_nombre' => $log->documento?->file_original_name,
                'relaciones_esperadas' => $log->relaciones_esperadas,
                'relaciones_faltantes' => $log->relaciones_faltantes,
                'created_at' => $log->created_at,
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function duplicadoDe(Documento $documento, User $actor): array
    {
        $coincidencias = $this->duplicados->buscarCoincidencias($documento);

        $mismoCliente = $coincidencias
            ->filter(fn (Documento $d) => $d->user_id === $documento->user_id)
            ->map(fn (Documento $d) => ['forma' => $d->forma, 'campo' => $d->campo])
            ->values();

        $deOtroCliente = $coincidencias->first(fn (Documento $d) => $d->user_id !== $documento->user_id);

        $otroClienteDetalle = $deOtroCliente && $actor->can('view', $deOtroCliente->user)
            ? ['cliente_id' => $deOtroCliente->user_id, 'cliente_nombre' => $deOtroCliente->user->name, 'forma' => $deOtroCliente->forma, 'campo' => $deOtroCliente->campo]
            : null;

        return [
            'posible_duplicado' => $coincidencias->isNotEmpty(),
            'mismo_cliente' => $mismoCliente->isNotEmpty() ? $mismoCliente->all() : null,
            'otro_cliente' => $deOtroCliente !== null,
            'otro_cliente_detalle' => $otroClienteDetalle,
        ];
    }
}
