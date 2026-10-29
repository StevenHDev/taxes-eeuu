<?php

namespace App\Services\CatalogoFiscal;

use App\Models\CatalogoFiscal\CatalogoDocumento;
use App\Models\CatalogoFiscal\CatalogoFormulario;
use App\Models\CatalogoFiscal\CatalogoPregunta;
use App\Models\CatalogoFiscal\CatalogoRegla;
use App\Models\ParametroFiscal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Lector único del catálogo fiscal de un año (F1.1). Devuelve solo lo
 * activo, como arreglos planos.
 *
 * Igual que TaxFieldCatalog cachea arreglos y no modelos de Eloquent
 * (serializados quedan frágiles ante cambios del modelo). A diferencia de
 * ese, usa una clave por año: el catálogo nuevo es bastante más grande y se
 * edita de a un año. Cada modelo del catálogo invalida su año al guardarse
 * (ver Concerns\EsDelCatalogoFiscal).
 *
 * Devuelve listas, no mapas por código: códigos como "1040" o "8812"
 * quedarían como claves numéricas y un `array_merge` las renumeraría.
 *
 * En esta fase solo lo usa la pantalla; las fases 2 a 4 lo amplían.
 */
class CatalogoFiscal
{
    public static function claveCache(int $taxYear): string
    {
        return "catalogo_fiscal.{$taxYear}";
    }

    public static function invalidar(int $taxYear): void
    {
        Cache::forget(self::claveCache($taxYear));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function preguntas(int $taxYear): Collection
    {
        return collect($this->anio($taxYear)['preguntas']);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function documentos(int $taxYear): Collection
    {
        return collect($this->anio($taxYear)['documentos']);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function formularios(int $taxYear): Collection
    {
        return collect($this->anio($taxYear)['formularios']);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function reglas(int $taxYear): Collection
    {
        return collect($this->anio($taxYear)['reglas']);
    }

    /**
     * Parámetros del año (F1.2), incluidos los pendientes (`valor` nulo).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function parametros(int $taxYear): Collection
    {
        return collect($this->anio($taxYear)['parametros']);
    }

    /**
     * Lo que abren las respuestas de una pregunta (sus activaciones activas).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function activadosPor(int $taxYear, string $codigoPregunta): Collection
    {
        return collect($this->preguntas($taxYear)->firstWhere('codigo', $codigoPregunta)['activaciones'] ?? []);
    }

    /**
     * @return array{preguntas: list<array<string, mixed>>, documentos: list<array<string, mixed>>, formularios: list<array<string, mixed>>, reglas: list<array<string, mixed>>, parametros: list<array<string, mixed>>}
     */
    private function anio(int $taxYear): array
    {
        return Cache::rememberForever(self::claveCache($taxYear), fn () => [
            'preguntas' => CatalogoPregunta::query()->delAnio($taxYear)->activos()
                ->with(['activaciones' => fn ($q) => $q->activos()])
                ->orderBy('bloque')->orderBy('orden')
                ->get()->toArray(),
            'documentos' => CatalogoDocumento::query()->delAnio($taxYear)->activos()
                ->with(['casillas' => fn ($q) => $q->activos()])
                ->orderBy('orden')
                ->get()->toArray(),
            'formularios' => CatalogoFormulario::query()->delAnio($taxYear)->activos()
                ->with(['lineas' => fn ($q) => $q->activos()])
                // El 1040 siempre primero: es el formulario "front".
                ->orderByDesc('siempre')->orderBy('orden')
                ->get()->toArray(),
            'reglas' => CatalogoRegla::query()->delAnio($taxYear)->activos()
                ->orderBy('orden')
                ->get()->toArray(),
            'parametros' => ParametroFiscal::query()->where('tax_year', $taxYear)
                ->orderBy('grupo')->orderBy('orden')
                ->get()->toArray(),
        ]);
    }
}
