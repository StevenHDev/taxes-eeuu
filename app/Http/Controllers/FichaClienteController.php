<?php

namespace App\Http\Controllers;

use App\Models\Expediente\Expediente;
use App\Models\Expediente\ExpedienteEvento;
use App\Models\User;
use App\Services\Entrevista\VistaEntrevistaPreparador;
use App\Services\Expediente\FichaAnterior;
use App\Services\Expediente\FichaCliente;
use App\Services\Lectura\ColaExcepciones;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Las pestañas Entrevista, Documentos, Excepciones e Historial de la ficha del
 * cliente. Solo leen; cada acción reutiliza el servicio que ya arma sus datos.
 */
class FichaClienteController extends Controller
{
    public function __construct(
        private readonly FichaCliente $ficha,
        private readonly VistaEntrevistaPreparador $vista,
    ) {}

    public function entrevista(Request $request, User $cliente): Response
    {
        return $this->pestana($request, $cliente, 'ficha/entrevista', fn (int $anio) => [
            'entrevista' => Inertia::defer(fn () => $this->vista->para($cliente, $anio, app()->getLocale() === 'en'), rescue: true),
        ]);
    }

    public function documentos(Request $request, User $cliente): Response
    {
        return $this->pestana($request, $cliente, 'ficha/documentos', fn (int $anio) => [
            'entrevista' => Inertia::defer(fn () => $this->vista->para($cliente, $anio, app()->getLocale() === 'en'), rescue: true),
        ]);
    }

    public function excepciones(Request $request, User $cliente, ColaExcepciones $cola): Response
    {
        return $this->pestana($request, $cliente, 'ficha/excepciones', fn (int $anio) => [
            'excepciones' => Inertia::defer(fn () => $cola->lista($request->user(), ['cliente' => $cliente->id, 'anio' => $anio]), rescue: true),
        ]);
    }

    public function historial(Request $request, User $cliente, FichaAnterior $anterior): Response
    {
        return $this->pestana($request, $cliente, 'ficha/historial', fn (int $anio) => [
            'eventos' => Inertia::defer(fn () => $this->eventos($cliente, $anio), rescue: true),
            'anterior' => Inertia::defer(fn () => $anterior->para($cliente, $anio, $request->user()), rescue: true),
        ]);
    }

    /**
     * @param  callable(int): array<string, mixed>  $props
     */
    private function pestana(Request $request, User $cliente, string $pagina, callable $props): Response
    {
        $this->authorize('view', $cliente);

        $taxYear = (int) $request->query('tax_year', config('tax.current_tax_year'));

        return Inertia::render($pagina, [
            'ficha' => $this->ficha->encabezado($cliente, $taxYear, $request->user()),
            ...$props($taxYear),
        ]);
    }

    /**
     * Los eventos del expediente del año, del más nuevo al más viejo.
     *
     * @return list<array<string, mixed>>
     */
    private function eventos(User $cliente, int $taxYear): array
    {
        $expediente = Expediente::query()->where('user_id', $cliente->id)->where('tax_year', $taxYear)->first();

        if ($expediente === null) {
            return [];
        }

        return $expediente->eventos()
            ->with(['actor' => fn ($q) => $q->withTrashed()->select('id', 'name')])
            ->reorder()
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (ExpedienteEvento $e) => [
                'id' => $e->id,
                'tipo' => $e->tipo->value,
                'tipo_label' => $e->tipo->label(),
                'estado_anterior' => $e->estado_anterior?->value,
                'estado_nuevo' => $e->estado_nuevo?->value,
                'estado_nuevo_label' => $e->estado_nuevo?->label(),
                'actor' => $e->actor?->name,
                'motivo' => $e->motivo,
                'creado_en' => $e->created_at->toIso8601String(),
            ])
            ->all();
    }
}
