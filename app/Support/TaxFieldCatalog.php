<?php

namespace App\Support;

use App\Enums\TaxForm;
use App\Models\CampoCatalogo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Catálogo de campos por formulario del IRS (especificación, sección 2).
 * Fuente de verdad para validar eventos y calcular completitud de una forma.
 *
 * Antes era un array estático en código; ahora lee de `catalogo_campos`
 * (administrable desde /catalogo) — la interfaz pública se mantuvo idéntica
 * para no tener que tocar los consumidores (EventoRecoleccionService,
 * EventoRequest, CampoClienteUpdateRequest, etc.) al migrar de array a BD.
 *
 * Cada método recibe `tax_year` como primer parámetro, obligatorio y sin
 * default: los montos, límites y campos del catálogo varían por año fiscal,
 * así que nunca hay un año "implícito" — igual que `forma`, quien llama debe
 * saber para qué año está preguntando.
 */
class TaxFieldCatalog
{
    const CACHE_KEY = 'catalogo_campos';

    /**
     * Todos los campos aplicables a una forma en un año fiscal dado: los
     * transversales + los propios de la forma, ambos de ese mismo año.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function fieldsFor(int $taxYear, TaxForm $forma): array
    {
        return self::todos()
            ->filter(fn (array $c) => $c['tax_year'] === $taxYear
                && ($c['forma'] === CampoCatalogo::TRANSVERSAL || $c['forma'] === $forma->value))
            ->map(fn (array $c) => $c['definicion'])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $taxYear, string $forma, string $campo): ?array
    {
        // 'transversal' es la forma canónica bajo la que se guardan los campos
        // únicos por cliente — no es una TaxForm, así que se resuelve aparte.
        if ($forma === CampoCatalogo::TRANSVERSAL) {
            foreach (self::todos() as $candidato) {
                if ($candidato['tax_year'] === $taxYear
                    && $candidato['forma'] === CampoCatalogo::TRANSVERSAL
                    && $candidato['definicion']['campo'] === $campo) {
                    return $candidato['definicion'];
                }
            }

            return null;
        }

        $taxForm = TaxForm::tryFrom($forma);

        if (! $taxForm) {
            return null;
        }

        foreach (self::fieldsFor($taxYear, $taxForm) as $field) {
            if ($field['campo'] === $campo) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Un campo "único por cliente" es un dato personal del cliente (SSN/ITIN,
     * cónyuge, dependientes) que no pertenece a una forma en particular: se guarda
     * una sola vez y se comparte entre todas sus formas (del mismo año fiscal).
     */
    public static function isUnicoPorCliente(int $taxYear, string $campo): bool
    {
        foreach (self::todos() as $candidato) {
            if ($candidato['tax_year'] === $taxYear && $candidato['definicion']['campo'] === $campo) {
                return (bool) ($candidato['definicion']['unico_por_cliente'] ?? false);
            }
        }

        return false;
    }

    /**
     * Forma bajo la que se debe guardar un campo: 'transversal' si es único por
     * cliente (una sola fila compartida), o la forma solicitada en caso contrario.
     */
    public static function formaAlmacen(int $taxYear, string $campo, string $formaSolicitada): string
    {
        return self::isUnicoPorCliente($taxYear, $campo)
            ? CampoCatalogo::TRANSVERSAL
            : $formaSolicitada;
    }

    public static function isSensible(int $taxYear, string $campo): bool
    {
        foreach (self::todos() as $candidato) {
            if ($candidato['tax_year'] === $taxYear && $candidato['definicion']['campo'] === $campo) {
                return $candidato['definicion']['sensible'];
            }
        }

        return false;
    }

    /**
     * Campos requeridos para considerar una forma completa (excluye obligatorio: false).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function requiredFieldsFor(int $taxYear, TaxForm $forma): array
    {
        return array_values(array_filter(
            self::fieldsFor($taxYear, $forma),
            fn (array $field) => $field['obligatorio'],
        ));
    }

    /**
     * Invalida la caché — se llama desde el CRUD del catálogo en cada escritura.
     * Una sola clave para todos los años (ver `todos()`), así que invalidar no
     * necesita saber de qué año fue la escritura.
     */
    public static function invalidate(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Cachea arrays planos (`forma` + `tax_year` + `definicion`), nunca instancias
     * de Eloquent: cachear modelos vía `Cache::rememberForever` los serializa con
     * `serialize()` en el store real (file/database/redis) y quedan frágiles ante
     * cualquier cambio de forma en el modelo entre el momento en que se cachearon y
     * el momento en que se leen — puede resultar en un `__PHP_Incomplete_Class` al
     * deserializar. Un array de escalares/enums es estable.
     *
     * Todos los años viven en una sola clave de caché (el volumen total del
     * catálogo, sumando todos los años, es trivial — cientos de filas) y se
     * filtra por año en memoria en cada método público; esto evita invalidar
     * N claves cuando se edita un campo de un año cualquiera.
     *
     * @return Collection<int, array{forma: string, tax_year: int, definicion: array<string, mixed>}>
     */
    private static function todos(): Collection
    {
        return collect(Cache::rememberForever(
            self::CACHE_KEY,
            fn () => CampoCatalogo::all()
                ->map(fn (CampoCatalogo $c) => [
                    'forma' => $c->forma,
                    'tax_year' => $c->tax_year,
                    'definicion' => $c->toDefinition(),
                ])
                ->all(),
        ));
    }
}
