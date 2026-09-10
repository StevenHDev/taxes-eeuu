<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué nivel de DocumentoExtraccionService resolvió el texto de este documento
 * (`texto_pdf` | `texto_pdf_normalizado` | `vision`) — solo aplica a
 * documentos que llegaron por WhatsApp (ver docs/implementar_agente_n8n.md,
 * sección "Extracción de documentos"); un documento subido por el panel no
 * pasa por ningún nivel de extracción, por eso es nullable sin backfill.
 * Permite medir en producción qué proporción cae en cada camino, necesario
 * porque el costo real depende de esa mezcla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->string('metodo_extraccion')->nullable()->after('hash_contenido');
        });
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropColumn('metodo_extraccion');
        });
    }
};
