<?php

namespace App\Support;

use App\Models\ParametroFiscal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Montos y umbrales del IRS (créditos, límites de dependientes, deducción
 * estándar), versionados por año fiscal. Mismo diseño de caché que
 * TaxFieldCatalog: una sola clave con todos los años, filtrada en memoria —
 * el volumen total (decenas de filas) no justifica una clave por año, y así
 * invalidate() sigue siendo un solo Cache::forget() sin fan-out.
 */
class ParametrosFiscales
{
    const CACHE_KEY = 'parametros_fiscales';

    /**
     * Sin default silencioso: si el parámetro no existe para ese año, retorna
     * null y quien llama decide si eso significa "no disponible".
     */
    public static function valor(int $taxYear, string $categoria, string $clave): mixed
    {
        foreach (self::todos() as $p) {
            if ($p['tax_year'] === $taxYear && $p['categoria'] === $categoria && $p['clave'] === $clave) {
                return $p['valor'];
            }
        }

        return null;
    }

    /**
     * Igual que `valor()`, pero para parámetros que el motor de reglas
     * necesita sí o sí para poder calcular — su ausencia es un problema de
     * configuración/despliegue (falta sembrar el año), no un dato de cliente
     * incompleto, así que truena en vez de devolver null en silencio.
     */
    public static function valorRequerido(int $taxYear, string $categoria, string $clave): mixed
    {
        $fila = self::todos()->first(fn (array $p) => $p['tax_year'] === $taxYear
            && $p['categoria'] === $categoria && $p['clave'] === $clave);

        throw_if($fila === null, new \RuntimeException(
            "Falta sembrar el parámetro fiscal [{$categoria}.{$clave}] para tax_year={$taxYear}.",
        ));

        // F1.2: un parámetro puede existir "pendiente", sin cifra del IRS todavía.
        throw_if($fila['valor'] === null, new \RuntimeException(
            "El parámetro fiscal [{$categoria}.{$clave}] de tax_year={$taxYear} está pendiente: todavía no tiene la cifra del IRS (ver Catálogo fiscal del año → Parámetros).",
        ));

        return $fila['valor'];
    }

    public static function invalidate(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return Collection<int, array{tax_year: int, categoria: string, clave: string, valor: mixed}>
     */
    private static function todos(): Collection
    {
        return collect(Cache::rememberForever(
            self::CACHE_KEY,
            fn () => ParametroFiscal::all()
                ->map(fn (ParametroFiscal $p) => [
                    'tax_year' => $p->tax_year,
                    'categoria' => $p->categoria,
                    'clave' => $p->clave,
                    'valor' => $p->valor,
                ])
                ->all(),
        ));
    }
}
