<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Override humano del nivel de riesgo de un caso (sección 5 del roadmap,
 * "pendientes menores") — tabla separada a propósito, mismo criterio que
 * `determinaciones_fiscales`: un override es un hecho de auditoría puntual
 * (quién lo puso, cuándo), no una propiedad del cliente ni de una forma. El
 * nivel AUTOMÁTICO (heurística, ver App\Services\RiesgoCasoService) nunca se
 * persiste aquí ni en ningún lado — se calcula en vivo en cada lectura,
 * porque a diferencia del motor de reglas fiscales no tiene ningún
 * requisito de trazabilidad regulatoria (recalcularlo distinto mañana no
 * tergiversa nada del pasado). La mayoría de los casos nunca van a tener
 * fila aquí: solo existe cuando un humano decidió sobreescribir la sugerencia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('niveles_riesgo_manual', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('tax_year');
            $table->string('nivel');
            $table->foreignId('establecido_por')->constrained('users')->cascadeOnDelete();
            $table->timestamp('establecido_en');
            $table->timestamps();

            $table->unique(['user_id', 'tax_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('niveles_riesgo_manual');
    }
};
