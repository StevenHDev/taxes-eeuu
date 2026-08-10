<?php

namespace App\Support;

use App\Enums\FieldState;
use App\Enums\TaxForm;
use App\Models\CampoCatalogo;
use App\Models\CampoCliente;
use App\Models\RelacionDocumentoCampo;
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

    const RELACIONES_CACHE_KEY = 'catalogo_relaciones_documento_campo';

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
     * Nombres de los campos obligatorios de una forma que un cliente todavía no
     * tiene con estado Recibido — incluye los transversales/únicos por cliente,
     * que cuentan para la completitud de cualquier forma del mismo año. Única
     * fuente de verdad de "qué falta"; tanto `EventoRecoleccionService` (para
     * decidir si una forma queda completa) como `pendientesPara()` (para el
     * endpoint /pendientes del agente externo) la reutilizan, para que ambos
     * cálculos nunca puedan divergir entre sí.
     *
     * @return Collection<int, string>
     */
    public static function pendientesObligatoriosFor(int $taxYear, TaxForm $forma, int $clienteId): Collection
    {
        $requeridos = collect(self::requiredFieldsFor($taxYear, $forma))->pluck('campo');

        $recibidos = CampoCliente::query()
            ->where('user_id', $clienteId)
            ->where('tax_year', $taxYear)
            ->whereIn('forma', [$forma->value, CampoCatalogo::TRANSVERSAL])
            ->where('estado', FieldState::Recibido)
            ->pluck('campo');

        return $requeridos->diff($recibidos)->values();
    }

    /**
     * Los campos transversales (únicos por cliente) de un año fiscal — no
     * pertenecen a ninguna forma en particular, así que a diferencia de
     * `fieldsFor()` esto no requiere una `TaxForm`.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function transversales(int $taxYear): array
    {
        return self::todos()
            ->filter(fn (array $c) => $c['tax_year'] === $taxYear && $c['forma'] === CampoCatalogo::TRANSVERSAL)
            ->map(fn (array $c) => $c['definicion'])
            ->values()
            ->all();
    }

    /**
     * Checklist pendiente de un cliente a través de varias formas a la vez —
     * lo que alimenta el endpoint `GET /api/clientes/{cliente}/pendientes`, para
     * que el agente conversacional externo sepa qué pedir a continuación sin
     * tener que memorizar el catálogo. Incluye campos obligatorios y opcionales
     * (cada uno con su propio flag `obligatorio`); el llamador decide si
     * considera "completo" solo a partir de los obligatorios.
     *
     * Los campos transversales (únicos por cliente) siempre se incluyen, sin
     * importar si `$formas` viene vacío — son datos del cliente como persona,
     * no de una forma en particular, así que se piden sin importar qué forma(s)
     * termine aplicando (o incluso antes de que se determine ninguna). Los
     * campos propios de cada forma en `$formas` NUNCA se deduplican entre sí —
     * ej. "estados_bancarios" de schedule_c y de schedule_e son dos pendientes
     * distintas, una por forma real, porque son datos genuinamente distintos
     * (contabilidades separadas).
     *
     * @param  array<int, TaxForm>  $formas
     * @return array<int, array<string, mixed>>
     */
    public static function pendientesPara(int $taxYear, array $formas, int $clienteId): array
    {
        // Un campo ya resuelto no vuelve a aparecer como pendiente — "resuelto"
        // incluye tanto un valor recibido como un "no aplica" explícito del
        // cliente (ver FieldState::NoAplica): ambos son una respuesta, no la
        // ausencia de una, así que ninguno debe seguir apareciendo en el
        // checklist que consulta el agente conversacional.
        $recibidos = CampoCliente::query()
            ->where('user_id', $clienteId)
            ->where('tax_year', $taxYear)
            ->whereIn('forma', [...array_map(fn (TaxForm $f) => $f->value, $formas), CampoCatalogo::TRANSVERSAL])
            ->whereIn('estado', [FieldState::Recibido, FieldState::NoAplica])
            ->get(['forma', 'campo'])
            ->map(fn (CampoCliente $c) => "{$c->forma}|{$c->campo}")
            ->flip();

        $pendientes = [];

        foreach (self::transversales($taxYear) as $field) {
            if ($recibidos->has(CampoCatalogo::TRANSVERSAL."|{$field['campo']}")) {
                continue;
            }

            $pendientes[] = [
                'forma' => CampoCatalogo::TRANSVERSAL,
                'campo' => $field['campo'],
                'tipo_campo' => $field['tipo']->value,
                'tipo_dato' => $field['tipo_dato']?->value,
                'subcampos' => $field['subcampos'],
                'formatos_aceptados' => $field['formatos_aceptados'],
                'obligatorio' => $field['obligatorio'],
                'sensible' => $field['sensible'],
                'revela' => self::revelaPara($taxYear, $field['campo']),
            ];
        }

        foreach ($formas as $forma) {
            foreach (self::fieldsFor($taxYear, $forma) as $field) {
                // Los transversales ya se agregaron arriba, una sola vez.
                if ($field['unico_por_cliente'] ?? false) {
                    continue;
                }

                if ($recibidos->has("{$forma->value}|{$field['campo']}")) {
                    continue;
                }

                $pendientes[] = [
                    'forma' => $forma->value,
                    'campo' => $field['campo'],
                    'tipo_campo' => $field['tipo']->value,
                    'tipo_dato' => $field['tipo_dato']?->value,
                    'subcampos' => $field['subcampos'],
                    'formatos_aceptados' => $field['formatos_aceptados'],
                    'obligatorio' => $field['obligatorio'],
                    'sensible' => $field['sensible'],
                    'revela' => self::revelaPara($taxYear, $field['campo']),
                ];
            }
        }

        return $pendientes;
    }

    /**
     * Campos-destino que un campo-documento ya resuelve, si el cliente lo
     * entrega — ej. 'w2' revela form_1040.ingresos (subcampo 'salarios') y
     * form_1040.impuestos_retenidos. Alimenta la clave `revela` de cada
     * pendiente en `pendientesPara()`, para que el agente externo sepa qué
     * campos NO tiene que preguntar aparte si ya leyó el documento — sin
     * tener esa lógica memorizada en su propio prompt (ver
     * RelacionDocumentoCampo y RelacionesDocumentoCampoSeeder).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function revelaPara(int $taxYear, string $campoDocumento): array
    {
        return self::relaciones()
            ->filter(fn (array $r) => $r['tax_year'] === $taxYear && $r['documento_campo'] === $campoDocumento)
            ->map(fn (array $r) => $r['definicion'])
            ->values()
            ->all();
    }

    /**
     * Invalida la caché — se llama desde el CRUD del catálogo en cada escritura.
     * Una sola clave para todos los años (ver `todos()`), así que invalidar no
     * necesita saber de qué año fue la escritura.
     */
    public static function invalidate(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::RELACIONES_CACHE_KEY);
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

    /**
     * @return Collection<int, array{documento_campo: string, tax_year: int, definicion: array<string, mixed>}>
     */
    private static function relaciones(): Collection
    {
        return collect(Cache::rememberForever(
            self::RELACIONES_CACHE_KEY,
            fn () => RelacionDocumentoCampo::all()
                ->map(fn (RelacionDocumentoCampo $r) => [
                    'documento_campo' => $r->documento_campo,
                    'tax_year' => $r->tax_year,
                    'definicion' => $r->toDefinition(),
                ])
                ->all(),
        ));
    }
}
