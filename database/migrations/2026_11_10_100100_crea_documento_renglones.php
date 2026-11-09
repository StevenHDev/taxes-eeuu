<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F6 6.3b: los renglones (movimientos) que se leen de los registros del
 * negocio. No caben en `documento_datos`, que guarda un valor por casilla.
 *
 * - `documento_renglones`: un movimiento o un monto de la plantilla. La
 *   descripción (puede traer nombres y números de cuenta) y el monto van
 *   cifrados, §7216. Nada se borra: se va con el archivo.
 * - `documento_renglon_cambios`: historial de correcciones; solo se inserta.
 *   Un renglón tiene varios campos corregibles (monto, categoría, sentido),
 *   por eso el historial lleva `campo`.
 * - `documento_lecturas` agrega lo que se detecta del archivo completo: la
 *   clase, el período y los totales impresos de un extracto (cifrados) para
 *   el cuadre del tramo 2, y `observaciones` con lo que no se reconoció de
 *   una hoja (solo conteos y letras de columna, nunca valores).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documento_renglones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lectura_id')->constrained('documento_lecturas')->cascadeOnDelete();
            $table->foreignId('entrevista_documento_id')->nullable()->constrained('entrevista_documentos')->nullOnDelete();
            $table->unsignedInteger('numero');
            $table->unsignedSmallInteger('pagina')->nullable();
            $table->json('recuadro')->nullable();
            $table->date('fecha')->nullable();
            $table->text('descripcion')->nullable();
            $table->text('monto')->nullable();
            $table->string('sentido', 14);
            $table->string('categoria', 20)->nullable();
            $table->string('categoria_origen', 12)->nullable();
            $table->decimal('confianza', 3, 2)->nullable();
            $table->string('motivo_confianza', 30)->nullable();
            $table->string('legibilidad', 12)->nullable();
            $table->text('evidencia')->nullable();
            $table->foreignId('cubierto_por_item_id')->nullable()->constrained('entrevista_documentos')->nullOnDelete();
            $table->string('origen', 12);
            $table->foreignId('corregido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('corregido_en')->nullable();
            $table->text('motivo')->nullable();
            $table->timestamps();

            $table->unique(['lectura_id', 'numero']);
            $table->index('entrevista_documento_id');
        });

        Schema::create('documento_renglon_cambios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('renglon_id')->constrained('documento_renglones')->cascadeOnDelete();
            $table->string('campo', 12);
            $table->text('valor_anterior')->nullable();
            $table->text('valor_nuevo')->nullable();
            $table->text('motivo');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('documento_lecturas', function (Blueprint $table) {
            $table->string('clase_detectada', 20)->nullable()->after('anio_detectado');
            $table->date('periodo_desde')->nullable()->after('clase_detectada');
            $table->date('periodo_hasta')->nullable()->after('periodo_desde');
            $table->text('cuenta_ultimos4')->nullable()->after('periodo_hasta');
            $table->text('total_depositos')->nullable()->after('cuenta_ultimos4');
            $table->text('total_retiros')->nullable()->after('total_depositos');
            $table->text('saldo_inicial')->nullable()->after('total_retiros');
            $table->text('saldo_final')->nullable()->after('saldo_inicial');
            $table->json('observaciones')->nullable()->after('saldo_final');
        });
    }

    public function down(): void
    {
        Schema::table('documento_lecturas', function (Blueprint $table) {
            $table->dropColumn(['clase_detectada', 'periodo_desde', 'periodo_hasta', 'cuenta_ultimos4', 'total_depositos', 'total_retiros', 'saldo_inicial', 'saldo_final', 'observaciones']);
        });

        Schema::dropIfExists('documento_renglon_cambios');
        Schema::dropIfExists('documento_renglones');
    }
};
