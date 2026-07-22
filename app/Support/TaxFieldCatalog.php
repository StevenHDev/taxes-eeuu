<?php

namespace App\Support;

use App\Enums\FieldDataType;
use App\Enums\FieldKind;
use App\Enums\TaxForm;

/**
 * Catálogo maestro de campos por formulario del IRS (especificación, sección 2).
 * Fuente de verdad para validar eventos y calcular completitud de una forma.
 *
 * @phpstan-type CatalogField array{
 *     campo: string,
 *     tipo: FieldKind,
 *     tipo_dato?: FieldDataType,
 *     formatos_aceptados?: array<int, string>,
 *     subcampos?: array<int, string>,
 *     obligatorio: bool,
 *     sensible: bool,
 * }
 */
class TaxFieldCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function camposTransversales(): array
    {
        return [
            self::field('identificacion_ssn_itin', FieldKind::Dato, tipoDato: FieldDataType::String, sensible: true),
            self::field('info_conyuge', FieldKind::Dato, tipoDato: FieldDataType::Object, subcampos: ['nombre_completo', 'fecha_nacimiento', 'ssn'], sensible: true),
            self::field('info_dependientes', FieldKind::Dato, tipoDato: FieldDataType::ArrayObject, subcampos: ['nombre_completo', 'fecha_nacimiento', 'ssn'], sensible: true),
            self::field('w2', FieldKind::Documento, formatos: ['pdf', 'jpg', 'png', 'heic']),
            self::field('form_1099_nec', FieldKind::Documento, formatos: ['pdf', 'jpg', 'png', 'heic']),
            self::field('estados_bancarios', FieldKind::Documento, formatos: ['pdf', 'xlsx', 'csv']),
            self::field('pl_balance_general', FieldKind::Mixto, tipoDato: FieldDataType::Number, formatos: ['pdf', 'xlsx']),
            self::field('gastos_deducibles', FieldKind::Mixto, tipoDato: FieldDataType::Number, formatos: ['pdf', 'jpg', 'png']),
            self::field('activos_depreciacion', FieldKind::Mixto, tipoDato: FieldDataType::Object, formatos: ['pdf', 'xlsx']),
            self::field('declaracion_anio_anterior', FieldKind::Documento, formatos: ['pdf'], obligatorio: false),
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function camposPorForma(): array
    {
        return [
            TaxForm::Form1040->value => [
                self::field('ingresos', FieldKind::Dato, tipoDato: FieldDataType::Number),
                self::field('dependientes', FieldKind::Dato, tipoDato: FieldDataType::ArrayObject),
                self::field('deducciones', FieldKind::Mixto, tipoDato: FieldDataType::Number, formatos: ['pdf', 'jpg']),
                self::field('creditos', FieldKind::Dato, tipoDato: FieldDataType::ArrayString),
                self::field('impuestos_retenidos', FieldKind::Dato, tipoDato: FieldDataType::Number),
                self::field('info_bancaria', FieldKind::Dato, tipoDato: FieldDataType::Object, subcampos: ['banco', 'tipo_cuenta', 'numero_cuenta', 'routing_number'], sensible: true),
            ],
            TaxForm::ScheduleC->value => [
                self::field('ingresos_negocio', FieldKind::Dato, tipoDato: FieldDataType::Number),
                self::field('gastos_deducibles_negocio', FieldKind::Mixto, tipoDato: FieldDataType::Number, formatos: ['pdf', 'jpg', 'csv']),
                self::field('millaje', FieldKind::Dato, tipoDato: FieldDataType::Number),
                self::field('activos', FieldKind::Mixto, tipoDato: FieldDataType::ArrayObject, formatos: ['pdf', 'xlsx']),
                self::field('costo_ventas', FieldKind::Dato, tipoDato: FieldDataType::Number),
            ],
            TaxForm::ScheduleE->value => [
                self::field('ingresos_renta', FieldKind::Dato, tipoDato: FieldDataType::Number),
                self::field('gastos_propiedad', FieldKind::Mixto, tipoDato: FieldDataType::Number, formatos: ['pdf', 'jpg']),
                self::field('depreciacion', FieldKind::Dato, tipoDato: FieldDataType::Number),
                self::field('intereses_hipotecarios', FieldKind::Documento, formatos: ['pdf']),
                self::field('impuestos_propiedad', FieldKind::Documento, formatos: ['pdf']),
                self::field('seguros_propiedad', FieldKind::Documento, formatos: ['pdf']),
            ],
            TaxForm::Form1065->value => [
                self::field('ingresos', FieldKind::Dato, tipoDato: FieldDataType::Number),
                self::field('gastos', FieldKind::Mixto, tipoDato: FieldDataType::Number, formatos: ['pdf', 'xlsx']),
                self::field('activos', FieldKind::Mixto, tipoDato: FieldDataType::ArrayObject, formatos: ['pdf', 'xlsx']),
                self::field('pasivos', FieldKind::Mixto, tipoDato: FieldDataType::ArrayObject, formatos: ['pdf', 'xlsx']),
                self::field('aportes_socios', FieldKind::Dato, tipoDato: FieldDataType::ArrayObject),
                self::field('porcentajes_participacion', FieldKind::Dato, tipoDato: FieldDataType::ArrayObject),
                self::field('datos_k1', FieldKind::Documento, formatos: ['pdf']),
            ],
            TaxForm::Form1120->value => [
                self::field('estados_financieros', FieldKind::Documento, formatos: ['pdf', 'xlsx']),
                self::field('ingresos', FieldKind::Dato, tipoDato: FieldDataType::Number),
                self::field('gastos', FieldKind::Mixto, tipoDato: FieldDataType::Number, formatos: ['pdf', 'xlsx']),
                self::field('depreciacion', FieldKind::Dato, tipoDato: FieldDataType::Number),
                self::field('impuestos_pagados', FieldKind::Dato, tipoDato: FieldDataType::Number),
                self::field('activos', FieldKind::Mixto, tipoDato: FieldDataType::ArrayObject, formatos: ['pdf', 'xlsx']),
                self::field('pasivos', FieldKind::Mixto, tipoDato: FieldDataType::ArrayObject, formatos: ['pdf', 'xlsx']),
                self::field('balance_general', FieldKind::Documento, formatos: ['pdf', 'xlsx']),
            ],
            TaxForm::Form1120S->value => [
                self::field('ingresos', FieldKind::Dato, tipoDato: FieldDataType::Number),
                self::field('gastos', FieldKind::Mixto, tipoDato: FieldDataType::Number, formatos: ['pdf', 'xlsx']),
                self::field('estados_financieros', FieldKind::Documento, formatos: ['pdf', 'xlsx']),
                self::field('nomina_compensacion_accionistas', FieldKind::Mixto, tipoDato: FieldDataType::ArrayObject, formatos: ['pdf']),
                self::field('depreciacion', FieldKind::Dato, tipoDato: FieldDataType::Number),
                self::field('datos_k1', FieldKind::Documento, formatos: ['pdf']),
            ],
            TaxForm::ScheduleF->value => [
                self::field('ventas_agricolas', FieldKind::Dato, tipoDato: FieldDataType::Number),
                self::field('subsidios', FieldKind::Dato, tipoDato: FieldDataType::Number),
                self::field('gastos_operacion', FieldKind::Mixto, tipoDato: FieldDataType::Number, formatos: ['pdf', 'jpg']),
                self::field('maquinaria', FieldKind::Mixto, tipoDato: FieldDataType::ArrayObject, formatos: ['pdf', 'xlsx']),
                self::field('animales', FieldKind::Dato, tipoDato: FieldDataType::ArrayObject),
                self::field('inventario', FieldKind::Mixto, tipoDato: FieldDataType::ArrayObject, formatos: ['pdf', 'xlsx']),
            ],
            TaxForm::Form1041->value => [
                self::field('ingresos', FieldKind::Dato, tipoDato: FieldDataType::Number),
                self::field('gastos', FieldKind::Mixto, tipoDato: FieldDataType::Number, formatos: ['pdf', 'xlsx']),
                self::field('info_beneficiarios', FieldKind::Dato, tipoDato: FieldDataType::ArrayObject, sensible: true),
                self::field('distribuciones', FieldKind::Dato, tipoDato: FieldDataType::ArrayObject),
                self::field('activos', FieldKind::Mixto, tipoDato: FieldDataType::ArrayObject, formatos: ['pdf', 'xlsx']),
                self::field('documentos_fideicomiso', FieldKind::Documento, formatos: ['pdf']),
            ],
            TaxForm::Form990->value => [
                self::field('ingresos', FieldKind::Dato, tipoDato: FieldDataType::Number),
                self::field('gastos', FieldKind::Mixto, tipoDato: FieldDataType::Number, formatos: ['pdf', 'xlsx']),
                self::field('donaciones', FieldKind::Mixto, tipoDato: FieldDataType::Number, formatos: ['pdf', 'xlsx']),
                self::field('actividades_programas', FieldKind::Dato, tipoDato: FieldDataType::String),
                self::field('compensacion_directivos', FieldKind::Dato, tipoDato: FieldDataType::ArrayObject),
                self::field('gobierno_corporativo', FieldKind::Dato, tipoDato: FieldDataType::String),
            ],
            TaxForm::Form1040Nr->value => [
                self::field('ingresos_fuente_usa', FieldKind::Dato, tipoDato: FieldDataType::Number),
                self::field('formularios_retencion', FieldKind::Documento, formatos: ['pdf']),
                self::field('info_migratoria', FieldKind::Dato, tipoDato: FieldDataType::Object, subcampos: ['tipo_visa', 'fecha_entrada_usa', 'estatus_migratorio']),
                self::field('tratados_tributarios', FieldKind::Dato, tipoDato: FieldDataType::String),
                self::field('deducciones_permitidas', FieldKind::Mixto, tipoDato: FieldDataType::Number, formatos: ['pdf', 'jpg']),
            ],
        ];
    }

    /**
     * Todos los campos aplicables a una forma: los transversales + los propios de la forma.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function fieldsFor(TaxForm $forma): array
    {
        return [
            ...self::camposTransversales(),
            ...(self::camposPorForma()[$forma->value] ?? []),
        ];
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
        foreach (self::camposTransversales() as $field) {
            if ($field['campo'] === $campo) {
                return $field['sensible'];
            }
        }

        foreach (self::camposPorForma() as $campos) {
            foreach ($campos as $field) {
                if ($field['campo'] === $campo) {
                    return $field['sensible'];
                }
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
     * @param  array<int, string>|null  $formatos
     * @param  array<int, string>|null  $subcampos
     * @return array<string, mixed>
     */
    private static function field(
        string $campo,
        FieldKind $tipo,
        ?FieldDataType $tipoDato = null,
        ?array $formatos = null,
        ?array $subcampos = null,
        bool $obligatorio = true,
        bool $sensible = false,
    ): array {
        return [
            'campo' => $campo,
            'tipo' => $tipo,
            'tipo_dato' => $tipoDato,
            'formatos_aceptados' => $formatos,
            'subcampos' => $subcampos,
            'obligatorio' => $obligatorio,
            'sensible' => $sensible,
        ];
    }
}
