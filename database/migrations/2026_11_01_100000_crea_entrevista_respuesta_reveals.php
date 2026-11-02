<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F2.9: cada vez que alguien del equipo revela una respuesta sensible de la
 * entrevista (SSN, ITIN, cuentas, IP PIN…) queda una fila, igual que
 * `campo_reveals` (T7). Solo se inserta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entrevista_respuesta_reveals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('respuesta_id')->constrained('entrevista_respuestas')->cascadeOnDelete();
            $table->foreignId('revealed_by_id')->constrained('users')->cascadeOnDelete();
            $table->string('ip_address')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entrevista_respuesta_reveals');
    }
};
