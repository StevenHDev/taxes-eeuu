<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\FirmarConsentimientoRequest;
use App\Services\Consentimiento\Consentimientos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * Consentimiento §7216 en el portal (F2.4): el cliente lo lee y lo firma
 * escribiendo su nombre. Solo para clientes con la entrevista nueva
 * (bandera de F2.2); el resto sigue con el flujo actual.
 */
class ConsentimientoController extends Controller
{
    public function __construct(private readonly Consentimientos $consentimientos) {}

    public function show(Request $request): Response|RedirectResponse
    {
        $cliente = $request->user();
        abort_unless($cliente->usaEntrevistaCatalogo(), 403);

        $taxYear = (int) config('tax.current_tax_year');

        if ($this->consentimientos->firmado($cliente, $taxYear)) {
            return to_route('portal.formulario');
        }

        $texto = $this->consentimientos->vigente();
        abort_if($texto === null, 503, 'Todavía no hay un texto de consentimiento cargado.');

        $ingles = str_starts_with(app()->getLocale(), 'en') && $texto->texto_en !== null;

        return Inertia::render('portal/consentimiento', [
            'texto' => [
                'version' => $texto->version,
                'titulo' => $ingles ? ($texto->titulo_en ?? $texto->titulo_es) : $texto->titulo_es,
                // El texto viene de los archivos del repositorio, no de un
                // usuario; igual se descarta cualquier HTML que traiga.
                'html' => Str::markdown($ingles ? $texto->texto_en : $texto->texto_es, ['html_input' => 'strip', 'allow_unsafe_links' => false]),
                'provisional' => $texto->provisional,
            ],
            'taxYear' => $taxYear,
            'nombreSugerido' => $cliente->name,
        ]);
    }

    public function store(FirmarConsentimientoRequest $request): RedirectResponse
    {
        $cliente = $request->user();
        $taxYear = (int) config('tax.current_tax_year');

        if ($this->consentimientos->soloPruebas() && ! $cliente->es_prueba) {
            abort(403, 'El texto del consentimiento todavía es provisional: solo lo firman usuarios de prueba.');
        }

        try {
            $this->consentimientos->firmar(
                $cliente,
                $taxYear,
                (int) $request->validated('version'),
                trim($request->validated('nombre_escrito')),
                $request->ip(),
                $request->userAgent(),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['version' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Consentimiento firmado.')]);

        return to_route('portal.formulario');
    }
}
