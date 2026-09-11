<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;

/**
 * Backfill: normaliza `users.phone` al mismo formato que ya usa
 * `User::phone` (ver el mutador en el modelo) — antes de esto, un teléfono
 * tecleado a mano sin "+" o con separadores no calzaba con
 * `WhatsappMensaje.telefono`, y ClienteController::conversacionWhatsapp veía
 * la conversación vacía pese a existir en whatsapp_mensajes.
 */
return new class extends Migration
{
    public function up(): void
    {
        User::query()
            ->whereNotNull('phone')
            ->get(['id', 'phone'])
            ->each(function (User $usuario) {
                $original = $usuario->getRawOriginal('phone');

                // Reasignar el mismo valor dispara el mutador de
                // normalización (ver User::phone()) — se guarda solo si
                // realmente cambió, para no tocar updated_at de filas que ya
                // estaban bien.
                $usuario->phone = $original;

                if ($usuario->phone !== $original) {
                    $usuario->saveQuietly();
                }
            });
    }

    public function down(): void
    {
        // Backfill de datos, no de esquema — no hay nada que revertir.
    }
};
