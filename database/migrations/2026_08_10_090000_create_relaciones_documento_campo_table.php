<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relaciones_documento_campo', function (Blueprint $table) {
            $table->id();
            // Campo-documento fuente (casi siempre 'transversal': w2, form_1099_nec,
            // form_1099_int, etc.) y campo-destino que ese documento puede resolver
            // sin volver a preguntárselo al cliente — ver App\Support\TaxFieldCatalog.
            $table->string('documento_forma');
            $table->string('documento_campo');
            $table->string('campo_destino_forma');
            $table->string('campo_destino');
            // Cuando el destino es un campo compuesto (tipo_dato object/array_object),
            // el subcampo exacto que resuelve — null si el destino es un valor simple.
            $table->string('subcampo_destino')->nullable();
            $table->text('descripcion')->nullable();
            $table->unsignedSmallInteger('tax_year');
            $table->timestamps();

            $table->unique(
                ['documento_campo', 'campo_destino_forma', 'campo_destino', 'subcampo_destino', 'tax_year'],
                'relaciones_doc_campo_unico',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relaciones_documento_campo');
    }
};
