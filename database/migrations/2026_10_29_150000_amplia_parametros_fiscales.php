<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F1.2 del documento madre: los parámetros del año pasan a verse en el
 * catálogo fiscal (7.7), con su grupo, para qué sirven, dónde se usan y su
 * fuente del IRS.
 *
 * Se amplía la tabla existente en vez de crear otra: `categoria` y `clave`
 * no cambian, así que App\Support\ParametrosFiscales y las calculadoras de
 * app/Services/Reglas siguen igual. `valor` pasa a nullable para poder
 * tener un parámetro "pendiente" (sin cifra del IRS todavía); nunca se
 * inventa una cifra.
 *
 * Los parámetros existentes quedan "sin validar": el seeder dice que
 * salieron de búsqueda web y que un preparador debe validarlos.
 */
return new class extends Migration
{
    /**
     * categoria.clave => [grupo, tipo, nombre, formulario y línea, condición].
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: string|null, 4: string|null}>
     */
    private const EXISTENTES = [
        'deduccion_estandar.monto_soltero' => ['deduccion_estandar', 'monto', 'Deducción estándar: soltero o casado por separado', 'Form 1040, línea 12', null],
        'deduccion_estandar.monto_mfj' => ['deduccion_estandar', 'monto', 'Deducción estándar: casados declarando juntos', 'Form 1040, línea 12', null],
        'deduccion_estandar.monto_hoh' => ['deduccion_estandar', 'monto', 'Deducción estándar: cabeza de familia', 'Form 1040, línea 12', null],

        'credito_ctc.monto_por_dependiente' => ['creditos', 'monto', 'Crédito por hijos: monto por hijo calificado', 'Schedule 8812', 'Hijo menor de 17 con SSN válido'],
        'credito_ctc.phase_out_umbral_soltero' => ['creditos', 'monto', 'Crédito por hijos: AGI desde el que se reduce (no conjunta)', 'Schedule 8812', null],
        'credito_ctc.phase_out_umbral_mfj' => ['creditos', 'monto', 'Crédito por hijos: AGI desde el que se reduce (conjunta)', 'Schedule 8812', null],
        'credito_ctc.reduccion_por_1000_exceso' => ['creditos', 'monto', 'Crédito por hijos: reducción por cada USD 1.000 de exceso', 'Schedule 8812', null],
        'credito_odc.monto_por_dependiente' => ['creditos', 'monto', 'Crédito por otros dependientes: monto por dependiente', 'Schedule 8812', 'Dependiente que no califica para el crédito por hijos'],
        'credito_odc.phase_out_umbral_soltero' => ['creditos', 'monto', 'Crédito por otros dependientes: AGI desde el que se reduce (no conjunta)', 'Schedule 8812', null],
        'credito_odc.phase_out_umbral_mfj' => ['creditos', 'monto', 'Crédito por otros dependientes: AGI desde el que se reduce (conjunta)', 'Schedule 8812', null],
        'credito_odc.reduccion_por_1000_exceso' => ['creditos', 'monto', 'Crédito por otros dependientes: reducción por cada USD 1.000 de exceso', 'Schedule 8812', null],
        'credito_cuidado_dependientes.tope_gastos_1_persona' => ['creditos', 'monto', 'Crédito por cuidado: tope de gastos (1 persona)', 'Form 2441', null],
        'credito_cuidado_dependientes.tope_gastos_2_mas_personas' => ['creditos', 'monto', 'Crédito por cuidado: tope de gastos (2 o más personas)', 'Form 2441', null],
        'credito_cuidado_dependientes.porcentaje_maximo' => ['creditos', 'porcentaje', 'Crédito por cuidado: porcentaje máximo', 'Form 2441', null],
        'credito_cuidado_dependientes.porcentaje_minimo' => ['creditos', 'porcentaje', 'Crédito por cuidado: porcentaje mínimo', 'Form 2441', null],
        'credito_cuidado_dependientes.agi_umbral_porcentaje_maximo' => ['creditos', 'monto', 'Crédito por cuidado: AGI hasta el que aplica el porcentaje máximo', 'Form 2441', null],
        'credito_cuidado_dependientes.agi_umbral_porcentaje_minimo' => ['creditos', 'monto', 'Crédito por cuidado: AGI desde el que aplica el porcentaje mínimo', 'Form 2441', null],
        'credito_cuidado_dependientes.edad_limite_dependiente' => ['creditos', 'numero', 'Crédito por cuidado: edad límite del hijo', 'Form 2441', 'Menor de esta edad'],
        'credito_ftc.umbral_de_minimis_soltero' => ['creditos', 'monto', 'Crédito por impuesto extranjero sin Form 1116 (no conjunta)', 'Schedule 3, línea 1', 'Impuesto extranjero hasta este monto'],
        'credito_ftc.umbral_de_minimis_mfj' => ['creditos', 'monto', 'Crédito por impuesto extranjero sin Form 1116 (conjunta)', 'Schedule 3, línea 1', 'Impuesto extranjero hasta este monto'],

        'dependiente_calificado.limite_ingreso_bruto_pariente_calificado' => ['topes_umbrales', 'monto', 'Pariente calificado: ingreso bruto menor a', 'Form 1040, dependientes', null],
        'self_employment_tax.tope_salario_social_security' => ['topes_umbrales', 'monto', 'Tope del Seguro Social', 'Schedule SE', null],
        'additional_medicare_tax.umbral_soltero' => ['topes_umbrales', 'monto', 'Additional Medicare Tax: umbral (no conjunta)', 'Form 8959', null],
        'additional_medicare_tax.umbral_mfj' => ['topes_umbrales', 'monto', 'Additional Medicare Tax: umbral (conjunta)', 'Form 8959', null],
        'niit.umbral_soltero' => ['topes_umbrales', 'monto', 'Impuesto sobre ingreso neto de inversión: umbral (no conjunta)', 'Form 8960', null],
        'niit.umbral_mfj' => ['topes_umbrales', 'monto', 'Impuesto sobre ingreso neto de inversión: umbral (conjunta)', 'Form 8960', null],
        'qbi.umbral_soltero' => ['topes_umbrales', 'monto', 'Deducción QBI: umbral de ingreso (no conjunta)', 'Form 8995, Form 1040 línea 13', null],
        'qbi.umbral_mfj' => ['topes_umbrales', 'monto', 'Deducción QBI: umbral de ingreso (conjunta)', 'Form 8995, Form 1040 línea 13', null],

        'tax_brackets.single' => ['tablas', 'tabla', 'Tramos del impuesto: soltero', 'Form 1040, línea 16', null],
        'tax_brackets.mfj' => ['tablas', 'tabla', 'Tramos del impuesto: casados declarando juntos', 'Form 1040, línea 16', null],
        'tax_brackets.hoh' => ['tablas', 'tabla', 'Tramos del impuesto: cabeza de familia', 'Form 1040, línea 16', null],
    ];

    public function up(): void
    {
        Schema::table('parametros_fiscales', function (Blueprint $table) {
            $table->json('valor')->nullable()->change();
            $table->string('grupo')->nullable()->after('clave');
            $table->string('nombre_es')->nullable()->after('grupo');
            $table->text('condicion_es')->nullable()->after('nombre_es');
            $table->string('formulario_linea')->nullable()->after('condicion_es');
            $table->string('tipo')->nullable()->after('formulario_linea');
            $table->unsignedSmallInteger('orden')->default(0)->after('tipo');
            $table->text('fuente_texto')->nullable();
            $table->string('fuente_url', 500)->nullable();
            $table->foreignId('validado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validado_en')->nullable();
        });

        $orden = 0;

        foreach (self::EXISTENTES as $codigo => [$grupo, $tipo, $nombre, $formularioLinea, $condicion]) {
            [$categoria, $clave] = explode('.', $codigo, 2);

            // Solo las filas que todavía no tienen grupo: se puede repetir.
            DB::table('parametros_fiscales')
                ->where('categoria', $categoria)
                ->where('clave', $clave)
                ->whereNull('grupo')
                ->update([
                    'grupo' => $grupo,
                    'tipo' => $tipo,
                    'nombre_es' => $nombre,
                    'formulario_linea' => $formularioLinea,
                    'condicion_es' => $condicion,
                    'orden' => ++$orden,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('parametros_fiscales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('validado_por');
            $table->dropColumn(['grupo', 'nombre_es', 'condicion_es', 'formulario_linea', 'tipo', 'orden', 'fuente_texto', 'fuente_url', 'validado_en']);
        });
    }
};
