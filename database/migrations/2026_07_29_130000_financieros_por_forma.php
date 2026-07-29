<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reclasifica los documentos financieros: dejan de ser transversales (aplicaban a
 * todas las formas) porque son por-forma/por-entidad.
 *
 *  - gastos_deducibles, activos_depreciacion, pl_balance_general: se eliminan; cada
 *    forma ya tiene su propio equivalente (gastos/deducciones, activos/depreciacion,
 *    estados_financieros/balance_general).
 *  - estados_bancarios: pasa a ser un campo propio de las formas de negocio/entidad.
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private array $eliminados = [
        'gastos_deducibles',
        'activos_depreciacion',
        'pl_balance_general',
    ];

    /** @var array<int, string> */
    private array $formasNegocio = [
        'schedule_c',
        'schedule_e',
        'schedule_f',
        'form_1065',
        'form_1120',
        'form_1120_s',
    ];

    public function up(): void
    {
        // 1) Quita las definiciones transversales de los 3 genéricos redundantes.
        DB::table('catalogo_campos')
            ->where('forma', 'transversal')
            ->whereIn('clave', $this->eliminados)
            ->delete();

        // Limpia los datos ya cargados de esos 3 (y sus documentos huérfanos). El
        // historial se conserva como traza de auditoría.
        $filas = DB::table('campos_cliente')
            ->whereIn('campo', $this->eliminados)
            ->get(['id', 'documento_id']);

        foreach ($filas as $fila) {
            if ($fila->documento_id) {
                DB::table('documentos')->where('id', $fila->documento_id)->delete();
            }
        }

        DB::table('campos_cliente')->whereIn('campo', $this->eliminados)->delete();

        // 2) estados_bancarios: de transversal a campo propio de cada forma de negocio.
        DB::table('catalogo_campos')
            ->where('forma', 'transversal')
            ->where('clave', 'estados_bancarios')
            ->delete();

        $ahora = now();

        foreach ($this->formasNegocio as $forma) {
            DB::table('catalogo_campos')->updateOrInsert(
                ['forma' => $forma, 'clave' => 'estados_bancarios'],
                [
                    'tipo_campo' => 'documento',
                    'tipo_dato' => null,
                    'formatos_aceptados' => json_encode(['pdf', 'xlsx', 'csv']),
                    'subcampos' => null,
                    'obligatorio' => true,
                    'sensible' => false,
                    'unico_por_cliente' => false,
                    'updated_at' => $ahora,
                    'created_at' => $ahora,
                ],
            );
        }

        // Los datos ya cargados de estados_bancarios se conservan: eran transversales
        // no-únicos, así que ya estaban guardados bajo la forma del evento (por-forma).
    }

    public function down(): void
    {
        $ahora = now();

        // Restaura los transversales (los datos borrados no son recuperables).
        $transversales = [
            ['clave' => 'estados_bancarios', 'tipo_campo' => 'documento', 'tipo_dato' => null, 'formatos' => ['pdf', 'xlsx', 'csv']],
            ['clave' => 'gastos_deducibles', 'tipo_campo' => 'mixto', 'tipo_dato' => 'number', 'formatos' => ['pdf', 'jpg', 'png']],
            ['clave' => 'activos_depreciacion', 'tipo_campo' => 'mixto', 'tipo_dato' => 'object', 'formatos' => ['pdf', 'xlsx']],
            ['clave' => 'pl_balance_general', 'tipo_campo' => 'mixto', 'tipo_dato' => 'number', 'formatos' => ['pdf', 'xlsx']],
        ];

        foreach ($transversales as $c) {
            DB::table('catalogo_campos')->updateOrInsert(
                ['forma' => 'transversal', 'clave' => $c['clave']],
                [
                    'tipo_campo' => $c['tipo_campo'],
                    'tipo_dato' => $c['tipo_dato'],
                    'formatos_aceptados' => json_encode($c['formatos']),
                    'subcampos' => null,
                    'obligatorio' => true,
                    'sensible' => false,
                    'unico_por_cliente' => false,
                    'updated_at' => $ahora,
                    'created_at' => $ahora,
                ],
            );
        }

        DB::table('catalogo_campos')
            ->whereIn('forma', $this->formasNegocio)
            ->where('clave', 'estados_bancarios')
            ->delete();
    }
};
