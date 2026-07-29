<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Amplía los campos "únicos por cliente" a los documentos básicos que son de la
 * persona, no de una forma: W-2, 1099-NEC y la declaración del año anterior.
 * (Los datos personales —SSN, cónyuge, dependientes— ya se marcaron antes.)
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private array $unicos = [
        'w2',
        'form_1099_nec',
        'declaracion_anio_anterior',
    ];

    public function up(): void
    {
        DB::table('catalogo_campos')
            ->whereIn('clave', $this->unicos)
            ->update(['unico_por_cliente' => true]);

        // Consolida datos ya cargados: por cada cliente y campo, conserva el más
        // reciente re-etiquetándolo a 'transversal' y borra los demás (incluido el
        // documento huérfano que quedaría al eliminar la fila).
        foreach ($this->unicos as $campo) {
            $filas = DB::table('campos_cliente')
                ->where('campo', $campo)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get(['id', 'user_id', 'documento_id']);

            $conservados = [];

            foreach ($filas as $fila) {
                if (in_array($fila->user_id, $conservados, true)) {
                    DB::table('campos_cliente')->where('id', $fila->id)->delete();

                    if ($fila->documento_id) {
                        DB::table('documentos')->where('id', $fila->documento_id)->delete();
                    }

                    continue;
                }

                $conservados[] = $fila->user_id;
                DB::table('campos_cliente')
                    ->where('id', $fila->id)
                    ->update(['forma' => 'transversal']);
            }

            DB::table('historial_cambios')
                ->where('campo', $campo)
                ->update(['forma' => 'transversal']);
        }
    }

    public function down(): void
    {
        DB::table('catalogo_campos')
            ->whereIn('clave', $this->unicos)
            ->update(['unico_por_cliente' => false]);
    }
};
