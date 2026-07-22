<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogo_campos', function (Blueprint $table) {
            $table->id();
            // 'forma' vale uno de los 10 valores de App\Enums\TaxForm, o el literal
            // 'transversal' para los campos compartidos por todas las formas.
            $table->string('forma');
            $table->string('clave');
            $table->string('tipo_campo');
            $table->string('tipo_dato')->nullable();
            $table->json('formatos_aceptados')->nullable();
            $table->json('subcampos')->nullable();
            $table->boolean('obligatorio')->default(true);
            $table->boolean('sensible')->default(false);
            $table->timestamps();

            $table->unique(['forma', 'clave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogo_campos');
    }
};
