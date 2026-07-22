<?php

namespace App\Services;

use App\Enums\EventSource;
use App\Enums\FieldState;
use App\Enums\FormState;
use App\Enums\TaxForm;
use App\Models\CampoCliente;
use App\Models\FormaCliente;
use App\Models\HistorialCambio;
use App\Models\User;
use App\Support\TaxFieldCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Agrega los datos reales que alimentan el Dashboard (sección "Panel de administración").
 * Recibe los clientes ya scopeados por rol (ManagesClientes::clientesVisiblesPara) — no
 * vuelve a resolver el alcance, solo agrega sobre lo que el controller ya cargó.
 */
class DashboardSummaryService
{
    /**
     * @param  Collection<int, User>  $clientes  con `formasCliente` ya cargada
     * @return array<string, mixed>
     */
    public function resumenPara(Collection $clientes): array
    {
        $clienteIds = $clientes->pluck('id');
        $todasFormasCliente = $clientes->pluck('formasCliente')->flatten();

        return [
            'actividad_por_dia' => $this->actividadPorDia($clienteIds),
            'campos_recibidos_porcentaje' => $this->camposRecibidosPorcentaje($clienteIds, $todasFormasCliente),
            'formas_completas_porcentaje' => $this->formasCompletasPorcentaje($todasFormasCliente),
            'distribucion_por_forma' => $this->distribucionPorForma($todasFormasCliente),
            'pendientes_revisar' => $this->pendientesRevisar($clienteIds),
            'actividad_reciente' => $this->actividadReciente($clienteIds),
            'ultimos_clientes' => $clientes->sortByDesc('created_at')->take(6)->values()
                ->map(fn (User $c) => ['id' => $c->id, 'name' => $c->name]),
        ];
    }

    /**
     * @param  Collection<int, int>  $clienteIds
     * @return array<int, array{fecha: string, cantidad: int}>
     */
    private function actividadPorDia(Collection $clienteIds): array
    {
        $conteos = HistorialCambio::query()
            ->whereIn('user_id', $clienteIds)
            ->where('created_at', '>=', Carbon::now()->subDays(6)->startOfDay())
            ->selectRaw('DATE(created_at) as dia, COUNT(*) as cantidad')
            ->groupBy('dia')
            ->pluck('cantidad', 'dia');

        return collect(range(6, 0))
            ->map(function (int $haceDias) use ($conteos) {
                $fecha = Carbon::now()->subDays($haceDias)->format('Y-m-d');

                return ['fecha' => $fecha, 'cantidad' => (int) ($conteos[$fecha] ?? 0)];
            })
            ->all();
    }

    /**
     * Progreso granular: campos individuales recibidos sobre el total esperado
     * en todas las formas iniciadas por los clientes visibles.
     *
     * @param  Collection<int, int>  $clienteIds
     * @param  Collection<int, FormaCliente>  $todasFormasCliente
     */
    private function camposRecibidosPorcentaje(Collection $clienteIds, Collection $todasFormasCliente): int
    {
        $esperados = $todasFormasCliente->sum(
            fn (FormaCliente $f) => \count(TaxFieldCatalog::requiredFieldsFor(TaxForm::from($f->forma))),
        );

        if ($esperados === 0) {
            return 0;
        }

        $recibidos = CampoCliente::query()
            ->whereIn('user_id', $clienteIds)
            ->where('estado', FieldState::Recibido)
            ->count();

        return (int) round(min($recibidos, $esperados) / $esperados * 100);
    }

    /**
     * Progreso por resultado: cuántas formas iniciadas llegaron a completarse
     * del todo, sobre el total de formas iniciadas — distinto del porcentaje
     * de campos individuales: una forma con 9 de 10 campos no suma acá.
     *
     * @param  Collection<int, FormaCliente>  $todasFormasCliente
     */
    private function formasCompletasPorcentaje(Collection $todasFormasCliente): int
    {
        if ($todasFormasCliente->isEmpty()) {
            return 0;
        }

        $completas = $todasFormasCliente->where('estado', FormState::Completo)->count();

        return (int) round($completas / $todasFormasCliente->count() * 100);
    }

    /**
     * @param  Collection<int, FormaCliente>  $todasFormasCliente
     * @return array<int, array{forma: string, forma_label: string, cantidad: int}>
     */
    private function distribucionPorForma(Collection $todasFormasCliente): array
    {
        return $todasFormasCliente
            ->countBy('forma')
            ->map(fn (int $cantidad, string $forma) => [
                'forma' => $forma,
                'forma_label' => TaxForm::from($forma)->label(),
                'cantidad' => $cantidad,
            ])
            ->sortByDesc('cantidad')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, int>  $clienteIds
     * @return array<int, array{cliente_id: int, cliente_nombre: string, forma: string, forma_label: string}>
     */
    private function pendientesRevisar(Collection $clienteIds): array
    {
        return FormaCliente::query()
            ->whereIn('user_id', $clienteIds)
            ->where('estado', FormState::Completo)
            ->whereNull('revisado_en')
            ->with('user:id,name')
            ->latest('updated_at')
            ->take(5)
            ->get()
            ->map(fn (FormaCliente $f) => [
                'cliente_id' => $f->user_id,
                'cliente_nombre' => $f->user->name,
                'forma' => $f->forma,
                'forma_label' => TaxForm::from($f->forma)->label(),
            ])
            ->all();
    }

    /**
     * @param  Collection<int, int>  $clienteIds
     * @return array<int, array{campo: string, forma_label: string, cliente_nombre: string, source: EventSource, created_at: Carbon|null}>
     */
    private function actividadReciente(Collection $clienteIds): array
    {
        return HistorialCambio::query()
            ->whereIn('user_id', $clienteIds)
            ->with('user:id,name')
            ->latest('created_at')
            ->take(6)
            ->get()
            ->map(fn (HistorialCambio $h) => [
                'campo' => $h->campo,
                'forma_label' => TaxForm::from($h->forma)->label(),
                'cliente_nombre' => $h->user->name,
                'source' => $h->source,
                'created_at' => $h->created_at,
            ])
            ->all();
    }
}
