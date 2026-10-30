<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F2.4:
 * - `consentimiento_textos`: cada versión del texto de un consentimiento
 *   (por ahora `uso_7216`; L1 dirá si son uno o dos). Mientras no llegue el
 *   texto definitivo, la versión vigente es provisional.
 * - `consentimientos`: la firma del cliente por año. Solo se inserta (8.3:
 *   texto, versión, fecha y hora); no se edita ni se borra.
 * - `users.contrasena_definida_en`: mientras sea nula, el agente puede
 *   mandar por WhatsApp el enlace para crear la contraseña. Las cuentas que
 *   ya existen se marcan como definidas: el enlace es solo para cuentas
 *   nuevas, y quien no la tenga usa "¿Olvidaste tu contraseña?".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consentimiento_textos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40);
            $table->unsignedSmallInteger('version');
            $table->string('titulo_es');
            $table->string('titulo_en')->nullable();
            $table->text('texto_es');
            $table->text('texto_en')->nullable();
            $table->boolean('provisional')->default(false);
            $table->date('vigente_desde');
            $table->timestamps();

            $table->unique(['codigo', 'version']);
        });

        Schema::create('consentimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('tax_year');
            $table->foreignId('consentimiento_texto_id')->constrained('consentimiento_textos')->restrictOnDelete();
            $table->string('nombre_escrito');
            $table->timestamp('aceptado_en');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at');

            $table->index(['user_id', 'tax_year']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('contrasena_definida_en')->nullable()->after('password');
        });

        DB::table('users')->update(['contrasena_definida_en' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('contrasena_definida_en');
        });

        Schema::dropIfExists('consentimientos');
        Schema::dropIfExists('consentimiento_textos');
    }
};
