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
 */
class TaxFieldCatalog
{
    const CACHE_KEY = 'catalogo_campos';

    /**
     * Todos los campos aplicables a una forma: los transversales + los propios de la forma.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function fieldsFor(TaxForm $forma): array
    {
        return self::todos()
            ->filter(fn (array $c) => $c['forma'] === CampoCatalogo::TRANSVERSAL || $c['forma'] === $forma->value)
            ->map(fn (array $c) => $c['definicion'])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $forma, string $campo): ?array
    {
        $taxForm = TaxForm::tryFrom($forma);

        if (! $taxForm) {
            return null;
        }

        foreach (self::fieldsFor($taxForm) as $field) {
            if ($field['campo'] === $campo) {
                return $field;
            }
        }

        return null;
    }

    public static function isSensible(string $campo): bool
    {
        foreach (self::todos() as $candidato) {
            if ($candidato['definicion']['campo'] === $campo) {
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
    public static function requiredFieldsFor(TaxForm $forma): array
    {
        return array_values(array_filter(
            self::fieldsFor($forma),
            fn (array $field) => $field['obligatorio'],
        ));
    }

    /**
     * Invalida la caché — se llama desde el CRUD del catálogo en cada escritura.
     */
    public static function invalidate(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Cachea arrays planos (`forma` + `definicion`), nunca instancias de Eloquent:
     * cachear modelos vía `Cache::rememberForever` los serializa con `serialize()`
     * en el store real (file/database/redis) y quedan frágiles ante cualquier
     * cambio de forma en el modelo entre el momento en que se cachearon y el
     * momento en que se leen — puede resultar en un `__PHP_Incomplete_Class` al
     * deserializar. Un array de escalares/enums es estable.
     *
     * @return Collection<int, array{forma: string, definicion: array<string, mixed>}>
     */
    private static function todos(): Collection
    {
        return collect(Cache::rememberForever(
            self::CACHE_KEY,
            fn () => CampoCatalogo::all()
                ->map(fn (CampoCatalogo $c) => ['forma' => $c->forma, 'definicion' => $c->toDefinition()])
                ->all(),
        ));
    }
}
