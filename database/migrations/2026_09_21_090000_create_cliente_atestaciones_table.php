<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4 del plan de cierre de brecha GTS (atestación final de cierre):
 * registro de que el cliente confirmó explícitamente, al cerrar la fase de
 * recolección, que la información y los documentos entregados son
 * completos y correctos — decisión de negocio confirmada con Steven
 * (2026-09-21), no solo un campo más de catálogo.
 *
 * Tabla propia, no un CampoCliente más: es un evento legal/de proceso, no
 * un dato de la declaración, y `campos_cliente` tiene
 * unique(user_id, forma, campo, tax_year) — una sola fila por campo, lo que
 * complicaría volver a atestiguar después de agregar información nueva
 * (ver más abajo). Append-only a propósito: nunca se actualiza ni se borra
 * una fila existente, cada confirmación del cliente crea una fila nueva —
 * conserva el registro histórico completo de qué se atestiguó y cuándo,
 * incluso si una atestación queda superada por una más reciente.
 *
 * "¿Sigue vigente la atestación?" se resuelve comparando `confirmado_en` de
 * la fila más reciente contra el `created_at` más reciente de
 * `historial_cambios` para ese mismo cliente/año fiscal (ver
 * AgenteToolService::atestacionVigente()) — si hay un cambio posterior a la
 * atestación, quedó invalidada y hay que volver a pedirla, sin necesidad de
 * una columna aparte para marcarlo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cliente_atestaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('tax_year');
            // Texto literal que el cliente escribió confirmando — registro
            // exacto de la respuesta, no solo un booleano.
            $table->text('respuesta_cliente');
            $table->timestamp('confirmado_en');
            $table->timestamps();

            $table->index(['user_id', 'tax_year', 'confirmado_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_atestaciones');
    }
};
