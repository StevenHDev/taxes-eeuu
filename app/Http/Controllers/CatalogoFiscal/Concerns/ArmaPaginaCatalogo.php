<?php

namespace App\Http\Controllers\CatalogoFiscal\Concerns;

use App\Models\CatalogoFiscal\CatalogoAnio;
use App\Models\User;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lo común de las 5 pestañas del catálogo fiscal (F1.4): permiso, año, años
 * disponibles y la tabla de la pestaña como prop diferida, para que la
 * cabecera y las pestañas se pinten de inmediato y la tabla llegue después
 * (con `rescue`, si falla se muestra el error en la página).
 */
trait ArmaPaginaCatalogo
{
    /**
     * @param  Closure(): array<int, array<string, mixed>>  $datos
     */
    protected function pagina(string $componente, int $anio, string $prop, Closure $datos): Response
    {
        Gate::authorize('viewAny', CatalogoAnio::class);

        $anios = CatalogoAnio::query()->orderByDesc('tax_year')->get(['tax_year', 'estado']);

        abort_unless($anios->contains('tax_year', $anio), 404);

        return Inertia::render($componente, [
            'anio' => $anio,
            'estadoAnio' => $anios->firstWhere('tax_year', $anio)->estado->value,
            'anios' => $anios->pluck('tax_year')->all(),
            'puedeEditar' => Gate::allows('update', CatalogoAnio::class),
            $prop => Inertia::defer($datos, rescue: true),
        ]);
    }

    /**
     * Fuente del IRS y validación de una pieza, como la muestra la pantalla.
     *
     * @param  array<string, mixed>  $pieza
     * @param  Collection<int, string>  $nombres  id de usuario => nombre
     * @return array{fuente_texto: string|null, fuente_url: string|null, validado_en: string|null, validado_por: string|null}
     */
    protected function fuente(array $pieza, Collection $nombres): array
    {
        return [
            'fuente_texto' => $pieza['fuente_texto'] ?? null,
            'fuente_url' => $pieza['fuente_url'] ?? null,
            'validado_en' => $pieza['validado_en'] ?? null,
            'validado_por' => isset($pieza['validado_por']) ? $nombres->get($pieza['validado_por']) : null,
        ];
    }

    /**
     * Nombres de quienes validaron, en una sola consulta.
     *
     * @param  Collection<int, array<string, mixed>>  $piezas
     * @return Collection<int, string>
     */
    protected function nombresValidadores(Collection $piezas): Collection
    {
        $ids = $piezas->pluck('validado_por')->filter()->unique()->values();

        return $ids->isEmpty() ? collect() : User::query()->whereIn('id', $ids)->pluck('name', 'id');
    }

    /**
     * Qué preguntas activan cada documento o formulario del año.
     *
     * @return Collection<string, list<string>> código del objetivo => códigos de pregunta
     */
    protected function activadoPor(CatalogoFiscal $catalogo, int $anio, string $tipo): Collection
    {
        return $catalogo->preguntas($anio)
            ->flatMap(fn (array $p) => collect($p['activaciones'])
                ->where('objetivo_tipo', $tipo)
                ->map(fn (array $a) => ['objetivo' => $a['objetivo_codigo'], 'pregunta' => $p['codigo']]))
            ->groupBy('objetivo')
            ->map(fn (Collection $filas) => $filas->pluck('pregunta')->unique()->values()->all());
    }
}
