<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campos_cliente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('forma');
            $table->string('campo');
            $table->string('tipo_campo');
            $table->string('modo');
            $table->json('valor_texto')->nullable();
            $table->foreignId('documento_id')->nullable()->constrained('documentos')->nullOnDelete();
            $table->string('estado');
            $table->string('source');
            $table->foreignId('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'forma', 'campo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campos_cliente');
    }
};
