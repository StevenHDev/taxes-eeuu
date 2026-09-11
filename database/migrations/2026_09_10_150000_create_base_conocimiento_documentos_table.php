<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documento cargado a la base de conocimiento del agente (ver
 * docs/implementar_agente_n8n.md, "Base de conocimiento"). El PDF original se
 * guarda en disco (mismo disco/convención que app/Services/
 * EventoRecoleccionService para `documentos/`) solo por trazabilidad; lo que
 * de verdad consulta la tool consultar_base_conocimiento es
 * `contenido_markdown`, ya extraído al momento de subir el archivo —
 * búsqueda de texto simple en una columna, sin tocar disco en cada consulta
 * (ver decisión de descartar embeddings/pgvector por ahora).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('base_conocimiento_documentos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_original');
            $table->string('ruta_pdf');
            $table->longText('contenido_markdown')->nullable();
            $table->unsignedInteger('tamano');
            $table->string('estado');
            $table->string('error_mensaje')->nullable();
            $table->foreignId('subido_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('base_conocimiento_documentos');
    }
};
