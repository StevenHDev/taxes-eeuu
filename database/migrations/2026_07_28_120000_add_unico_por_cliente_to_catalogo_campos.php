<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modela los campos "únicos por cliente": datos personales que son del cliente,
 * no de una forma en particular (SSN/ITIN, cónyuge, dependientes). Antes se
 * guardaban con la forma del evento, así que el mismo dato quedaba duplicado si
 * llegaba en eventos de dos formas distintas. A partir de acá se guardan una sola
 * vez bajo la forma canónica 'transversal'.
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private array $unicos = [
        'identificacion_ssn_itin',
        'info_conyuge',
        'info_dependientes',
    ];

    public function up(): void
    {
        Schema::table('catalogo_campos', function (Blueprint $table) {
            $table->boolean('unico_por_cliente')->default(false);
        });

        // Marca en el catálogo los campos personales como únicos por cliente.
        DB::table('catalogo_campos')
            ->whereIn('clave', $this->unicos)
            ->update(['unico_por_cliente' => true]);

        // Quita el campo 'dependientes' propio del form_1040: se solapaba con el
        // transversal 'info_dependientes' y se veía como duplicado.
        DB::table('catalogo_campos')
            ->where('forma', 'form_1040')
            ->where('clave', 'dependientes')
            ->delete();
        DB::table('campos_cliente')
            ->where('forma', 'form_1040')
            ->where('campo', 'dependientes')
            ->delete();

        // Consolida datos ya cargados: por cada cliente y campo único, conserva el
        // valor más reciente re-etiquetándolo a 'transversal' y borra los demás.
        foreach ($this->unicos as $campo) {
            $filas = DB::table('campos_cliente')
                ->where('campo', $campo)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get(['id', 'user_id']);

            $conservados = [];

            foreach ($filas as $fila) {
                if (in_array($fila->user_id, $conservados, true)) {
                    DB::table('campos_cliente')->where('id', $fila->id)->delete();

                    continue;
                }

                $conservados[] = $fila->user_id;
                DB::table('campos_cliente')
                    ->where('id', $fila->id)
                    ->update(['forma' => 'transversal']);
            }

            // El historial se llavea por (user, forma, campo); re-etiquétalo también
            // para que siga visible bajo el registro consolidado.
            DB::table('historial_cambios')
                ->where('campo', $campo)
                ->update(['forma' => 'transversal']);
        }
    }

    public function down(): void
    {
        Schema::table('catalogo_campos', function (Blueprint $table) {
            $table->dropColumn('unico_por_cliente');
        });
    }
};
