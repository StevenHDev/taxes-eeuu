<?php

namespace App\Services\WhatsappAgent;

use App\Enums\FaseConversacion;
use App\Enums\TaxForm;
use App\Models\FormaCliente;
use App\Models\User;
use App\Support\TaxFieldCatalog;

/**
 * Deriva la fase vigente de una conversación de WhatsApp a partir de los
 * datos ya existentes del cliente — nunca de memoria de la conversación (ver
 * FaseConversacion). Sin cliente, la fase es siempre VerificacionCuenta.
 *
 * El año fiscal "en curso" para el resto de las fases es el mayor `tax_year`
 * entre las formas ya declaradas de este cliente (`declarar_formas_cliente`
 * es la única tool que recibe tax_year como parámetro y lo persiste, vía
 * FormaCliente) — no se guarda en ningún otro lugar, igual que en el diseño
 * anterior el orquestador de n8n tampoco lo persistía: lo recordaba de su
 * propio contexto de conversación mientras durara la fase DeterminacionFormas.
 */
class EstadoConversacionResolver
{
    public function resolver(?User $cliente): FaseConversacion
    {
        if ($cliente === null) {
            return FaseConversacion::VerificacionCuenta;
        }

        $taxYear = FormaCliente::query()->where('user_id', $cliente->id)->max('tax_year');

        if ($taxYear === null) {
            return FaseConversacion::DeterminacionFormas;
        }

        $formas = FormaCliente::query()
            ->where('user_id', $cliente->id)
            ->where('tax_year', $taxYear)
            ->pluck('forma')
            ->map(fn (string $forma) => TaxForm::tryFrom($forma))
            ->filter()
            ->values()
            ->all();

        $pendientes = TaxFieldCatalog::pendientesPara((int) $taxYear, $formas, $cliente->id);
        $quedaObligatorioPendiente = collect($pendientes)->contains(fn (array $p) => $p['obligatorio']);

        return $quedaObligatorioPendiente ? FaseConversacion::Recoleccion : FaseConversacion::Cierre;
    }
}
