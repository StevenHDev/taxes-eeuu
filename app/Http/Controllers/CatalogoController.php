<?php

namespace App\Http\Controllers;

use App\Enums\TaxForm;
use App\Http\Requests\CatalogoCampoRequest;
use App\Models\CampoCatalogo;
use App\Support\TaxFieldCatalog;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CatalogoController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', CampoCatalogo::class);

        $campos = CampoCatalogo::query()->orderBy('forma')->orderBy('clave')->get();

        return Inertia::render('catalogo/index', [
            'formas' => [
                ['value' => CampoCatalogo::TRANSVERSAL, 'label' => 'Transversales (todas las formas)'],
                ...array_map(fn (TaxForm $f) => ['value' => $f->value, 'label' => $f->label()], TaxForm::cases()),
            ],
            'campos' => $campos,
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
