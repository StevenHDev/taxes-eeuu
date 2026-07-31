<?php

use App\Support\TaxFieldCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fase 2 — amplía el catálogo 2025 con lo que necesita el motor de reglas:
 * estado_civil (nuevo), info_dependientes (subcampos ampliados), ingresos
 * (number -> object desglosado), gastos_cuidado_dependientes (nuevo), y
 * elimina creditos (pasa a ser resultado calculado, no dato de entrada —
 * Decisión A). Migración de datos, no de schema — mismo estilo que
 * `2026_07_29_130000_financieros_por_forma.php`.
 */
return new class extends Migration
{
    private const TAX_YEAR = 2025;

    public function up(): void
    {
        $ahora = now();

        DB::table('catalogo_campos')->updateOrInsert(
            ['forma' => 'transversal', 'clave' => 'estado_civil', 'tax_year' => self::TAX_YEAR],
            [
                'tipo_campo' => 'dato',
                'tipo_dato' => 'object',
                'formatos_aceptados' => null,
                'subcampos' => json_encode([
                    'casado_al_31_dic',
                    'convivio_conyuge_ultimos_6_meses',
                    'costeo_mas_mitad_hogar',
                    'existe_persona_calificable',
                    'conyuge_fallecio_en_anio',
                    'anio_fallecimiento_conyuge',
                ]),
                'obligatorio' => true,
                'sensible' => false,
                'unico_por_cliente' => true,
                'updated_at' => $ahora,
                'created_at' => $ahora,
            ],
        );

        DB::table('catalogo_campos')
            ->where('forma', 'transversal')->where('clave', 'info_dependientes')->where('tax_year', self::TAX_YEAR)
            ->update(['subcampos' => json_encode([
                'nombre_completo', 'fecha_nacimiento', 'ssn',
                'relacion', 'meses_en_hogar', 'estudiante_tiempo_completo', 'discapacitado',
                'provee_mas_50_soporte_propio', 'ingreso_bruto_anual', 'custodia_compartida_sin_conflicto',
            ]), 'updated_at' => $ahora]);

        DB::table('catalogo_campos')
            ->where('forma', 'form_1040')->where('clave', 'ingresos')->where('tax_year', self::TAX_YEAR)
            ->update([
                'tipo_dato' => 'object',
                'subcampos' => json_encode([
                    'salarios', 'intereses_dividendos', 'ganancias_capital',
                    'ingresos_jubilacion', 'otros_ingresos', 'ajustes_ingreso',
                ]),
                'updated_at' => $ahora,
            ]);

        // creditos se elimina del catálogo de recolección (Decisión A): pasa a
        // ser 100% un resultado calculado por el motor de reglas, nunca un dato
        // que se le pide al cliente. Se borran los campos_cliente ya cargados
        // (datos de desarrollo triviales); el historial se conserva como traza.
        DB::table('campos_cliente')->where('campo', 'creditos')->where('tax_year', self::TAX_YEAR)->delete();
        DB::table('catalogo_campos')
            ->where('forma', 'form_1040')->where('clave', 'creditos')->where('tax_year', self::TAX_YEAR)
            ->delete();

        DB::table('catalogo_campos')->updateOrInsert(
            ['forma' => 'form_1040', 'clave' => 'gastos_cuidado_dependientes', 'tax_year' => self::TAX_YEAR],
            [
                'tipo_campo' => 'mixto',
                'tipo_dato' => 'object',
                'formatos_aceptados' => json_encode(['pdf', 'jpg']),
                'subcampos' => json_encode(['proveedor_nombre', 'proveedor_ssn_ein', 'monto_anual', 'dependiente_relacionado']),
                // No todos los clientes tienen gastos de cuidado de dependientes.
                'obligatorio' => false,
                // Trae SSN/EIN del proveedor — identificador sensible.
                'sensible' => true,
                'unico_por_cliente' => false,
                'updated_at' => $ahora,
                'created_at' => $ahora,
            ],
        );

        TaxFieldCatalog::invalidate();
    }

    public function down(): void
    {
        DB::table('catalogo_campos')
            ->where('forma', 'form_1040')->where('clave', 'gastos_cuidado_dependientes')->where('tax_year', self::TAX_YEAR)
            ->delete();

        $ahora = now();

        DB::table('catalogo_campos')->updateOrInsert(
            ['forma' => 'form_1040', 'clave' => 'creditos', 'tax_year' => self::TAX_YEAR],
            [
                'tipo_campo' => 'dato',
                'tipo_dato' => 'array_string',
                'formatos_aceptados' => null,
                'subcampos' => null,
                'obligatorio' => true,
                'sensible' => false,
                'unico_por_cliente' => false,
                'updated_at' => $ahora,
                'created_at' => $ahora,
            ],
        );

        DB::table('catalogo_campos')
            ->where('forma', 'form_1040')->where('clave', 'ingresos')->where('tax_year', self::TAX_YEAR)
            ->update(['tipo_dato' => 'number', 'subcampos' => null, 'updated_at' => $ahora]);

        DB::table('catalogo_campos')
            ->where('forma', 'transversal')->where('clave', 'info_dependientes')->where('tax_year', self::TAX_YEAR)
            ->update(['subcampos' => json_encode(['nombre_completo', 'fecha_nacimiento', 'ssn']), 'updated_at' => $ahora]);

        DB::table('catalogo_campos')
            ->where('forma', 'transversal')->where('clave', 'estado_civil')->where('tax_year', self::TAX_YEAR)
            ->delete();

        TaxFieldCatalog::invalidate();
    }
};
