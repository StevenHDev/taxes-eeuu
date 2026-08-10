<?php

namespace App\Http\Controllers;

use App\Enums\TaxForm;
use App\Enums\UserRole;
use App\Http\Concerns\ManagesClientes;
use App\Models\DeterminacionFiscal;
use App\Models\FormaCliente;
use App\Models\User;
use App\Services\DashboardSummaryService;
use App\Support\TaxFieldCatalog;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use ManagesClientes;

    public function __construct(private readonly DashboardSummaryService $summary) {}

    public function index(): Response
    {
        $user = request()->user();

        if ($user->role === UserRole::Client) {
            return $this->miInformacion($user);
        }

        $taxYear = (int) request()->query('tax_year', config('tax.current_tax_year'));

        $clientes = $this->clientesVisiblesPara($user)
            ->with(['formasCliente' => fn ($query) => $query->where('tax_year', $taxYear)])
            ->get();

        $porEstado = $clientes
            ->map(fn (User $cliente) => $this->estadoGeneralDe($cliente))
            ->countBy();

        return Inertia::render('dashboard', [
            'resumen' => [
                'total' => $clientes->count(),
                'sin_iniciar' => $porEstado->get('sin_iniciar', 0),
                'en_progreso' => $porEstado->get('en_progreso', 0),
                'completo' => $porEstado->get('completo', 0),
                ...$this->summary->resumenPara($clientes, $taxYear),
            ],
            'taxYearActual' => $taxYear,
        ]);
    }

    /**
     * Vista de autoservicio del cliente final (el mismo `User` con el que
     * conversa el agente externo por WhatsApp): todo lo que ya entregó,
     * organizado por forma, más las determinaciones fiscales que ya se hayan
     * calculado. A propósito NO reutiliza `ClienteController::show` (la ficha
     * del preparador) — esa incluye acciones y datos exclusivos del equipo
     * interno (nivel de riesgo, revelar sensibles, editar/eliminar campos,
     * detección de duplicados entre clientes) que un cliente no debe ver ni
     * accionar. `ClientePolicy` sigue negando por completo el acceso de un
     * cliente al panel de preparadores; esta vista es su único acceso propio.
     */
    private function miInformacion(User $cliente): Response
    {
        $taxYear = (int) request()->query('tax_year', config('tax.current_tax_year'));

        $cliente->load([
            'formasCliente' => fn ($query) => $query->where('tax_year', $taxYear),
            'camposCliente' => fn ($query) => $query->where('tax_year', $taxYear)->with('documento')->orderBy('campo'),
            'determinacionesFiscales' => fn ($query) => $query->where('tax_year', $taxYear),
        ]);

        return Inertia::render('mi-informacion', [
            'cliente' => [
                'id' => $cliente->id,
                'name' => $cliente->name,
                'email' => $cliente->email,
                'phone' => $cliente->phone,
            ],
            'taxYearActual' => $taxYear,
            'formas' => $cliente->formasCliente->map(fn (FormaCliente $f) => [
                'forma' => $f->forma,
                'forma_label' => TaxForm::from($f->forma)->label(),
                'estado' => $f->estado,
            ]),
            'campos' => $cliente->camposCliente->map(function ($c) use ($taxYear) {
                $definicion = TaxFieldCatalog::find($taxYear, $c->forma, $c->campo);

                return [
                    'forma' => $c->forma,
                    'campo' => $c->campo,
                    'tipo_campo' => $c->tipo_campo,
                    'tipo_dato' => $definicion['tipo_dato']?->value,
                    'subcampos' => $definicion['subcampos'] ?? null,
                    'modo' => $c->modo,
                    'estado' => $c->estado,
                    'valor' => $c->valor,
                    'es_sensible' => $c->esSensible(),
                    'documento' => $c->documento ? [
                        'id' => $c->documento->id,
                        'file_original_name' => $c->documento->file_original_name,
                        'file_mime_type' => $c->documento->file_mime_type,
                        'formato' => $c->documento->formato,
                        'download_url' => $c->documento->downloadUrl(),
                        'preview_url' => $c->documento->previewUrl(),
                    ] : null,
                    'updated_at' => $c->updated_at,
                ];
            }),
            'determinaciones' => $cliente->determinacionesFiscales->map(fn (DeterminacionFiscal $d) => [
                'tipo' => $d->tipo->value,
                'resultado' => $d->resultado,
                'version_reglas' => $d->version_reglas,
                'calculado_en' => $d->calculado_en,
            ]),
        ]);
    }
}
