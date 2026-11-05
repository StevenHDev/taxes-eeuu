<?php

namespace App\Http\Controllers;

use App\Enums\Expediente\ClasificacionPersona;
use App\Enums\Expediente\EstadoCivilIrs;
use App\Enums\Expediente\PuntoRevision;
use App\Http\Requests\Expediente\ClasificarPersonaRequest;
use App\Http\Requests\Expediente\EstadoCivilRequest;
use App\Http\Requests\Expediente\PuntoRevisionRequest;
use App\Http\Requests\Expediente\TransicionExpedienteRequest;
use App\Models\Entrevista\EntrevistaPersona;
use App\Models\Expediente\Expediente;
use App\Models\User;
use App\Services\Entrevista\TransicionNoPermitida;
use App\Services\Expediente\EstadosExpediente;
use App\Services\Expediente\FichaCliente;
use App\Services\Expediente\RevisionExpediente;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La pestaña Revisión de la ficha: decisiones del preparador, checklist 7.2 y
 * transiciones manuales. Las reglas viven en RevisionExpediente.
 */
class RevisionExpedienteController extends Controller
{
    public function __construct(
        private readonly RevisionExpediente $revision,
        private readonly EstadosExpediente $estados,
    ) {}

    public function show(Request $request, User $cliente, FichaCliente $ficha): Response
    {
        $this->authorize('view', $cliente);

        $taxYear = (int) $request->query('tax_year', config('tax.current_tax_year'));

        return Inertia::render('ficha/revision', [
            'ficha' => $ficha->encabezado($cliente, $taxYear, $request->user()),
            'revision' => Inertia::defer(fn () => $this->revision->vista($cliente, $taxYear, app()->getLocale() === 'en'), rescue: true),
        ]);
    }

    public function estadoCivil(EstadoCivilRequest $request, User $cliente): RedirectResponse
    {
        return $this->guardar($request->integer('tax_year'), $cliente, 'accion', __('Guardamos el estado civil para el IRS.'), fn (Expediente $e) => $this->revision->decidirEstadoCivil(
            $e,
            EstadoCivilIrs::from($request->validated('valor')),
            (string) $request->validated('motivo'),
            $request->user(),
        ));
    }

    public function persona(ClasificarPersonaRequest $request, User $cliente, EntrevistaPersona $persona): RedirectResponse
    {
        $expediente = $this->expediente($cliente, $request->integer('tax_year'));

        // Una persona de otro cliente o de otro año no existe para esta ficha.
        abort_unless($expediente->entrevista_id !== null && $persona->entrevista_id === $expediente->entrevista_id, 404);

        return $this->guardar($request->integer('tax_year'), $cliente, 'accion', __('Guardamos la clasificación de la persona.'), fn (Expediente $e) => $this->revision->clasificarPersona(
            $e,
            $persona,
            ClasificacionPersona::from($request->validated('clasificacion')),
            (string) $request->validated('motivo'),
            $request->user(),
        ));
    }

    public function punto(PuntoRevisionRequest $request, User $cliente, PuntoRevision $punto): RedirectResponse
    {
        return $this->guardar($request->integer('tax_year'), $cliente, 'accion', $request->boolean('confirmado') ? __('Confirmaste el punto de la revisión.') : __('Quitaste la confirmación del punto.'), fn (Expediente $e) => $this->revision->confirmarPunto(
            $e,
            $punto,
            $request->boolean('confirmado'),
            $request->validated('nota'),
            $request->user(),
        ));
    }

    public function transicion(TransicionExpedienteRequest $request, User $cliente): RedirectResponse
    {
        $accion = (string) $request->validated('accion');

        return $this->guardar($request->integer('tax_year'), $cliente, 'accion', match ($accion) {
            'empezar' => __('Empezaste la revisión.'),
            'preparado' => __('Marcaste el expediente como preparado en TaxWise.'),
            'a_firma' => __('Marcaste el expediente como enviado a firmar.'),
            'presentado' => __('Marcaste el expediente como presentado.'),
            'aceptado' => __('Marcaste el expediente como aceptado por el IRS.'),
            'rechazado' => __('Marcaste el expediente como rechazado por el IRS.'),
            default => __('Volviste el expediente a revisión.'),
        }, fn (Expediente $e) => $this->revision->transicion(
            $e,
            $accion,
            $request->user(),
            $request->validated('fecha'),
            $request->validated('motivo'),
        ));
    }

    /**
     * El expediente del cliente en el año (se crea si falta: es lo que recalcula).
     */
    private function expediente(User $cliente, int $taxYear): Expediente
    {
        return $this->estados->recalcular($cliente, $taxYear);
    }

    /**
     * @param  Closure(Expediente): void  $escribir
     */
    private function guardar(int $taxYear, User $cliente, string $campo, string $aviso, Closure $escribir): RedirectResponse
    {
        try {
            $escribir($this->expediente($cliente, $taxYear));
        } catch (TransicionNoPermitida $e) {
            throw ValidationException::withMessages([$campo => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $aviso]);

        return back();
    }
}
