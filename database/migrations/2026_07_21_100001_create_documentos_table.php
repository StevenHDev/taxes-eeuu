<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('forma');
            $table->string('campo');
            $table->string('file_path');
            $table->string('file_original_name');
            $table->string('file_mime_type');
            $table->unsignedInteger('file_size');
            $table->string('formato');
            $table->string('estado_validacion');
            $table->timestamps();

            $table->index(['user_id', 'forma', 'campo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos');
    }
};
