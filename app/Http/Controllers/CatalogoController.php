<?php

namespace App\Http\Controllers;

use App\Enums\TaxForm;
use App\Http\Requests\CatalogoCampoRequest;
use App\Models\CampoCatalogo;
use App\Models\CampoDerivationLog;
use App\Models\RelacionDocumentoCampo;
use App\Support\TaxFieldCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogoController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CampoCatalogo::class);

        // Superficie humana (admin navegando el panel): un default de conveniencia
        // es apropiado acá, a diferencia del camino del agente externo.
        $taxYear = (int) $request->query('tax_year', config('tax.current_tax_year'));

        $campos = CampoCatalogo::query()->where('tax_year', $taxYear)->orderBy('forma')->orderBy('clave')->get();

        // Inspector de relaciones (ver RelacionDocumentoCampo): qué campo(s)
        // resuelve cada documento sin que el agente tenga que volver a
        // preguntarlos — mismo texto (`descripcion`) y forma que ya recibe
        // el agente vía `revela`, para que el admin vea EXACTAMENTE lo que
        // el agente ve, no una traducción aparte que pueda desalinearse.
        $relacionesPorDocumento = RelacionDocumentoCampo::query()
            ->where('tax_year', $taxYear)
            ->get()
            ->groupBy('documento_campo')
            ->map(fn ($grupo) => $grupo->map(fn (RelacionDocumentoCampo $r) => $r->toDefinition())->values());

        // Rastro real de uso (ver CampoDerivationLog, Fase 1): cuántos
        // documentos de este tipo se procesaron y a cuántos les faltó
        // cubrir alguna relación declarada — para que el admin vea si la
        // teoría del catálogo se cumple en la práctica.
        $statsPorDocumento = CampoDerivationLog::query()
            ->where('tax_year', $taxYear)
            ->get(['documento_campo', 'relaciones_faltantes'])
            ->groupBy('documento_campo')
            ->map(fn ($grupo) => [
                'total' => $grupo->count(),
                'con_faltantes' => $grupo->filter(fn (CampoDerivationLog $log) => $log->relaciones_faltantes !== [])->count(),
            ]);

        return Inertia::render('catalogo/index', [
            'formas' => [
                ['value' => CampoCatalogo::TRANSVERSAL, 'label' => 'Transversales (todas las formas)'],
                ['value' => CampoCatalogo::DOCUMENTOS_EXTRA, 'label' => 'Documentos extra (siempre se piden)'],
                ...array_map(fn (TaxForm $f) => ['value' => $f->value, 'label' => $f->label()], TaxForm::cases()),
            ],
            'campos' => $campos,
            'taxYearActual' => $taxYear,
            'anosDisponibles' => CampoCatalogo::query()->distinct()->orderByDesc('tax_year')->pluck('tax_year'),
            'relacionesPorDocumento' => $relacionesPorDocumento,
            'statsPorDocumento' => $statsPorDocumento,
        ]);
    }

    public function store(CatalogoCampoRequest $request): RedirectResponse
    {
        CampoCatalogo::query()->create($request->validated());

        TaxFieldCatalog::invalidate();

        return back();
    }

    public function update(CatalogoCampoRequest $request, CampoCatalogo $campo): RedirectResponse
    {
        $campo->update($request->validated());

        TaxFieldCatalog::invalidate();

        return back();
    }

    public function destroy(CampoCatalogo $campo): RedirectResponse
    {
        $this->authorize('delete', CampoCatalogo::class);

        $campo->delete();

        TaxFieldCatalog::invalidate();

        return back();
    }
}
