<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campo_reveals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campo_cliente_id')->constrained('campos_cliente')->cascadeOnDelete();
            $table->foreignId('revealed_by_id')->constrained('users')->cascadeOnDelete();
            $table->string('ip_address')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campo_reveals');
    }
};
