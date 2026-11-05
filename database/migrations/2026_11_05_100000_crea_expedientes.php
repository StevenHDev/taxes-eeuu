<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expedientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('tax_year');
            $table->foreignId('entrevista_id')->nullable()->constrained('entrevistas')->nullOnDelete();
            $table->string('estado', 30)->index();
            $table->timestamp('estado_desde');
            // Decisión del preparador (7.2, punto 2): el estado civil para el IRS.
            $table->string('estado_civil_irs', 10)->nullable();
            $table->text('estado_civil_motivo')->nullable();
            $table->foreignId('estado_civil_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('estado_civil_en')->nullable();
            // Créditos que piden la debida diligencia del 8867 (7.1): lista de códigos.
            $table->json('creditos_8867')->nullable();
            $table->foreignId('creditos_8867_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('creditos_8867_en')->nullable();
            $table->date('presentado_en')->nullable();
            $table->timestamp('cambios_tras_presentar_en')->nullable();
            // Tipos de ingreso y situación (para filtrar la lista); nunca montos ni datos sensibles.
            $table->json('perfil')->nullable();
            $table->date('vence_en')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'tax_year']);
        });

        Schema::create('expediente_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('expedientes')->cascadeOnDelete();
            $table->string('tipo', 30)->index();
            $table->string('estado_anterior', 30)->nullable();
            $table->string('estado_nuevo', 30)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('motivo')->nullable();
            $table->json('datos')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['expediente_id', 'created_at']);
        });

        Schema::create('expediente_personas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('expedientes')->cascadeOnDelete();
            $table->foreignId('persona_id')->constrained('entrevista_personas')->cascadeOnDelete();
            $table->string('clasificacion', 30);
            $table->text('motivo');
            $table->foreignId('decidido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decidido_en');
            $table->timestamps();

            $table->unique(['expediente_id', 'persona_id']);
        });

        Schema::create('expediente_revision_puntos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('expedientes')->cascadeOnDelete();
            $table->string('punto', 30);
            $table->foreignId('confirmado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmado_en')->nullable();
            $table->text('nota')->nullable();
            $table->timestamps();

            $table->unique(['expediente_id', 'punto']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expediente_revision_puntos');
        Schema::dropIfExists('expediente_personas');
        Schema::dropIfExists('expediente_eventos');
        Schema::dropIfExists('expedientes');
    }
};
