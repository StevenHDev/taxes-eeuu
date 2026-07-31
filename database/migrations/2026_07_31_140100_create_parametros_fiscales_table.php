<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Montos y umbrales del IRS (créditos, límites de dependientes, deducción
 * estándar), versionados por año fiscal — el motor de reglas nunca hardcodea
 * un monto en código, siempre lo lee de acá. `valor` es JSON plano, no
 * cifrado (a diferencia de `campos_cliente.valor_texto`): no es dato del
 * cliente, así que la validación de sintaxis JSON de Postgres es una ventaja,
 * no un problema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parametros_fiscales', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tax_year');
            $table->string('categoria');
            $table->string('clave');
            $table->json('valor');
            $table->timestamps();

            $table->unique(['tax_year', 'categoria', 'clave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametros_fiscales');
    }
};
