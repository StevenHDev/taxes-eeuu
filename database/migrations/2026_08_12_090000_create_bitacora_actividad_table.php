<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora general de la plataforma (quién hizo qué, cuándo) — distinta de
 * `historial_cambios`, que ya registra el valor anterior/nuevo de un campo
 * puntual de un cliente. Esta tabla NUNCA guarda valores de campos (ver
 * App\Observers\AuditoriaObserver): solo qué acción ocurrió, sobre qué
 * registro, y qué nombres de atributo cambiaron — para no duplicar datos
 * sensibles (SSN, cuentas bancarias) fuera del cifrado/enmascarado que ya
 * tiene `campos_cliente`/`historial_cambios`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitacora_actividad', function (Blueprint $table) {
            $table->id();
            // nullOnDelete (no cascade): un registro de bitácora debe sobrevivir
            // aunque el actor se elimine después — por eso también se guarda
            // el nombre/email como snapshot de texto, no solo la FK.
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_nombre')->nullable();
            $table->string('actor_email')->nullable();
            $table->string('accion');
            // Morphs nullable (login/logout no tienen un "registro afectado"
            // distinto del propio actor) y sin FK — el registro auditado puede
            // eliminarse después y la fila de bitácora debe seguir existiendo.
            $table->nullableMorphs('auditable');
            // Snapshot legible del registro afectado (ej. "Marco Completo
            // (cliente)"), para que la fila siga teniendo sentido aunque el
            // registro real se borre o cambie después.
            $table->string('etiqueta')->nullable();
            // Solo nombres de atributos que cambiaron, nunca sus valores.
            $table->json('campos_afectados')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('accion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora_actividad');
    }
};
