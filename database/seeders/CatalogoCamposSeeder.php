<?php

namespace Database\Seeders;

use App\Enums\FieldDataType;
use App\Enums\FieldKind;
use App\Enums\TaxForm;
use App\Models\CampoCatalogo;
use Illuminate\Database\Seeder;

/**
 * Carga la versión inicial del catálogo editable de campos (sección 2 de la
 * especificación) — a partir de acá el catálogo se administra desde el panel
 * (`/catalogo`), esta es solo la semilla con la que arranca el sistema.
 */
class CatalogoCamposSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->transversales() as $campo) {
            $this->crear(CampoCatalogo::TRANSVERSAL, $campo);
        }

        foreach ($this->porForma() as $forma => $campos) {
            foreach ($campos as $campo) {
                $this->crear($forma, $campo);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $campo
     */
    private function crear(string $forma, array $campo): void
    {
        CampoCatalogo::query()->firstOrCreate(
            ['forma' => $forma, 'clave' => $campo['campo']],
            [
                'tipo_campo' => $campo['tipo'],
                'tipo_dato' => $campo['tipo_dato'] ?? null,
                'formatos_aceptados' => $campo['formatos_aceptados'] ?? null,
                'subcampos' => $campo['subcampos'] ?? null,
                'obligatorio' => $campo['obligatorio'] ?? true,
                'sensible' => $campo['sensible'] ?? false,
            ],
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function transversales(): array
    {
        return [
            $this->campo('identificacion_ssn_itin', FieldKind::Dato, tipoDato: FieldDataType::String, sensible: true),
            $this->campo('info_conyuge', FieldKind::Dato, tipoDato: FieldDataType::Object, subcampos: ['nombre_completo', 'fecha_nacimiento', 'ssn'], sensible: true),
            $this->campo('info_dependientes', FieldKind::Dato, tipoDato: FieldDataType::ArrayObject, subcampos: ['nombre_completo', 'fecha_nacimiento', 'ssn'], sensible: true),
            $this->campo('w2', FieldKind::Documento, formatos: ['pdf', 'jpg', 'png', 'heic']),
            $this->campo('form_1099_nec', FieldKind::Documento, formatos: ['pdf', 'jpg', 'png', 'heic']),
            $this->campo('estados_bancarios', FieldKind::Documento, formatos: ['pdf', 'xlsx', 'csv']),
            $this->campo('pl_balance_general', FieldKind::Mixto, tipoDato: FieldDataType::Number, formatos: ['pdf', 'xlsx']),
            $this->campo('gastos_deducibles', FieldKind::Mixto, tipoDato: FieldDataType::Number, formatos: ['pdf', 'jpg', 'png']),
            $this->campo('activos_depreciacion', FieldKind::Mixto, tipoDato: FieldDataType::Object, formatos: ['pdf', 'xlsx']),
            $this->campo('declaracion_anio_anterior', FieldKind::Documento, formatos: ['pdf'], obligatorio: false),
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function porForma(): array
    {
        return [
            TaxForm::Form1040->value => [
                $this->campo('ingresos', FieldKind::Dato, tipoDato: FieldDataType::Number),
                $this->campo('dependientes', FieldKind::Dato, tipoDato: FieldDataType::ArrayObject),
                $this->campo('deducciones', FieldKind::Mixto, tipoDato: FieldDataType::Number, formatos: ['pdf', 'jpg']),
                $this->campo('creditos', FieldKind::Dato, tipoDato: FieldDataType::ArrayString),
                $this->campo('impuestos_retenidos', FieldKind::Dato, tipoDato: FieldDataType::Number),
                $this->campo('info_bancaria', FieldKind::Dato, tipoDato: FieldDataType::Object, subcampos: ['banco', 'tipo_cuenta', 'numero_cuenta', 'routing_number'], sensible: true),
            ],
            TaxForm::ScheduleC->value => [
                $this->campo('ingresos_negocio', FieldKind::Dato, tipoDato: FieldDataType::Number),
                $this->campo('gastos_deducibles_negocio', FieldKind::Mixto, tipoDato: FieldDataType::Number, formatos: ['pdf', 'jpg', 'csv']),
                $this->campo('millaje', FieldKind::Dato, tipoDato: FieldDataType::Number),
                $this->campo('activos', FieldKind::Mixto, tipoDato: FieldDataType::ArrayObject, formatos: ['pdf', 'xlsx']),
                $this->campo('costo_ventas', FieldKind::Dato, tipoDato: FieldDataType::Number),
            ],
            TaxForm::ScheduleE->value => [
                $this->campo('ingresos_renta', FieldKind::Dato, tipoDato: FieldDataType::Number),
                $this->campo('gastos_propiedad', FieldKind::Mixto, tipoDato: FieldDataType::Number, formatos: ['pdf', 'jpg']),
                $this->campo('depreciacion', FieldKind::Dato, tipoDato: FieldDataType::Number),
                $this->campo('intereses_hipotecarios', FieldKind::Documento, formatos: ['pdf']),
                $this->campo('impuestos_propiedad', FieldKind::Documento, formatos: ['pdf']),
                $this->campo('seguros_propiedad', FieldKind::Documento, formatos: ['pdf']),
            ],
            TaxForm::Form1065->value => [
                $this->campo('ingresos', FieldKind::Dato, tipoDato: FieldDataType::Number),
                $this->campo('gastos', FieldKind::Mixto, tipoDato: FieldDataType::Number, formatos: ['pdf', 'xlsx']),
                $this->campo('activos', FieldKind::Mixto, tipoDato: FieldDataType::ArrayObject, formatos: ['pdf', 'xlsx']),
                $this->campo('pasivos', FieldKind::Mixto, tipoDato: FieldDataType::ArrayObject, formatos: ['pdf', 'xlsx']),
                $this->campo('aportes_socios', FieldKind::Dato, tipoDato: FieldDataType::ArrayObject),
                $this->campo('porcentajes_participacion', FieldKind::Dato, tipoDato: FieldDataType::ArrayObject),
                $this->campo('datos_k1', FieldKind::Documento, formatos: ['pdf']),
            ],
            TaxForm::Form1120->value => [
                $this->campo('estados_financieros', FieldKind::Documento, formatos: ['pdf', 'xlsx']),
                $this->campo('ingresos', FieldKind::Dato, tipoDato: FieldDataType::Number),
                $this->campo('gastos', FieldKind::Mixto, tipoDato: FieldDataType::Number, formatos: ['pdf', 'xlsx']),
                $this->campo('depreciacion', FieldKind::Dato, tipoDato: FieldDataType::Number),
                $this->campo('impuestos_pagados', FieldKind::Dato, tipoDato: FieldDataType::Number),
                $this->campo('activos', FieldKind::Mixto, tipoDato: FieldDataType::ArrayObject, formatos: ['pdf', 'xlsx']),
                $this->campo('pasivos', FieldKind::Mixto, tipoDato: FieldDataType::ArrayObject, formatos: ['pdf', 'xlsx']),
                $this->campo('balance_general', FieldKind::Documento, formatos: ['pdf', 'xlsx']),
            ],
            TaxForm::Form1120S->value => [
                $this->campo('ingresos', FieldKind::Dato, tipoDato: FieldDataType::Number),
                $this->campo('gastos', FieldKind::Mixto, tipoDato: FieldDataType::Number, formatos: ['pdf', 'xlsx']),
                $this->campo('estados_financieros', FieldKind::Documento, formatos: ['pdf', 'xlsx']),
                $this->campo('nomina_compensacion_accionistas', FieldKind::Mixto, tipoDato: FieldDataType::ArrayObject, formatos: ['pdf']),
                $this->campo('depreciacion', FieldKind::Dato, tipoDato: FieldDataType::Number),
                $this->campo('datos_k1', FieldKind::Documento, formatos: ['pdf']),
            ],
            TaxForm::ScheduleF->value => [
                $this->campo('ventas_agricolas', FieldKind::Dato, tipoDato: FieldDataType::Number),
                $this->campo('subsidios', FieldKind::Dato, tipoDato: FieldDataType::Number),
                $this->campo('gastos_operacion', FieldKind::Mixto, tipoDato: FieldDataType::Number, formatos: ['pdf', 'jpg']),
                $this->campo('maquinaria', FieldKind::Mixto, tipoDato: FieldDataType::ArrayObject, formatos: ['pdf', 'xlsx']),
                $this->campo('animales', FieldKind::Dato, tipoDato: FieldDataType::ArrayObject),
                $this->campo('inventario', FieldKind::Mixto, tipoDato: FieldDataType::ArrayObject, formatos: ['pdf', 'xlsx']),
            ],
            TaxForm::Form1041->value => [
                $this->campo('ingresos', FieldKind::Dato, tipoDato: FieldDataType::Number),
                $this->campo('gastos', FieldKind::Mixto, tipoDato: FieldDataType::Number, formatos: ['pdf', 'xlsx']),
                $this->campo('info_beneficiarios', FieldKind::Dato, tipoDato: FieldDataType::ArrayObject, sensible: true),
                $this->campo('distribuciones', FieldKind::Dato, tipoDato: FieldDataType::ArrayObject),
                $this->campo('activos', FieldKind::Mixto, tipoDato: FieldDataType::ArrayObject, formatos: ['pdf', 'xlsx']),
                $this->campo('documentos_fideicomiso', FieldKind::Documento, formatos: ['pdf']),
            ],
            TaxForm::Form990->value => [
                $this->campo('ingresos', FieldKind::Dato, tipoDato: FieldDataType::Number),
                $this->campo('gastos', FieldKind::Mixto, tipoDato: FieldDataType::Number, formatos: ['pdf', 'xlsx']),
                $this->campo('donaciones', FieldKind::Mixto, tipoDato: FieldDataType::Number, formatos: ['pdf', 'xlsx']),
                $this->campo('actividades_programas', FieldKind::Dato, tipoDato: FieldDataType::String),
                $this->campo('compensacion_directivos', FieldKind::Dato, tipoDato: FieldDataType::ArrayObject),
                $this->campo('gobierno_corporativo', FieldKind::Dato, tipoDato: FieldDataType::String),
            ],
            TaxForm::Form1040Nr->value => [
                $this->campo('ingresos_fuente_usa', FieldKind::Dato, tipoDato: FieldDataType::Number),
                $this->campo('formularios_retencion', FieldKind::Documento, formatos: ['pdf']),
                $this->campo('info_migratoria', FieldKind::Dato, tipoDato: FieldDataType::Object, subcampos: ['tipo_visa', 'fecha_entrada_usa', 'estatus_migratorio']),
                $this->campo('tratados_tributarios', FieldKind::Dato, tipoDato: FieldDataType::String),
                $this->campo('deducciones_permitidas', FieldKind::Mixto, tipoDato: FieldDataType::Number, formatos: ['pdf', 'jpg']),
            ],
        ];
    }

    /**
     * @param  array<int, string>|null  $formatos
     * @param  array<int, string>|null  $subcampos
     * @return array<string, mixed>
     */
    private function campo(
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
