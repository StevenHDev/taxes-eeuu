<?php

namespace App\Listeners;

use App\Enums\Expediente\EstadoExpediente;
use App\Enums\UserRole;
use App\Events\ExpedienteCambioDeEstado;
use App\Models\User;
use App\Notifications\AvisoExpedientePreparador;
use Illuminate\Support\Facades\Notification;

/**
 * Correo al preparador cuando el expediente queda "Listo para preparar" o el
 * cliente responde una consulta (F5.6). Laravel lo registra solo por el tipo
 * del evento: no se registra a mano (se duplicaría). Se apaga con
 * `tax.avisos_correo_preparador`.
 */
class AvisarPreparadorDeExpediente
{
    public function handle(ExpedienteCambioDeEstado $evento): void
    {
        if (! config('tax.avisos_correo_preparador')) {
            return;
        }

        $listo = $evento->nuevo === EstadoExpediente::ListoParaPreparar;
        $respondio = ! $listo
            && $evento->anterior === EstadoExpediente::EnConsulta
            && ! in_array($evento->nuevo, [EstadoExpediente::EnConsulta, EstadoExpediente::Invitado, EstadoExpediente::EnEntrevista], true);

        if (! $listo && ! $respondio) {
            return;
        }

        $cliente = User::withTrashed()->find($evento->expediente->user_id);

        if ($cliente === null) {
            return;
        }

        $destinatarios = $cliente->preparer_id !== null
            ? User::query()->whereKey($cliente->preparer_id)->get()
            : collect();

        if ($destinatarios->isEmpty()) {
            $destinatarios = User::query()->where('role', UserRole::Administrator)->get();
        }

        Notification::send($destinatarios, new AvisoExpedientePreparador(
            $cliente->name,
            $evento->nuevo->label(),
            $respondio,
            route('clientes.show', ['cliente' => $cliente->id, 'tax_year' => $evento->expediente->tax_year]),
        ));
    }
}
