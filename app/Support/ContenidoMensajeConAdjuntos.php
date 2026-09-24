<?php

namespace App\Support;

use App\DataTransferObjects\AdjuntoWhatsapp;

/**
 * Formato exacto que espera el prompt (prompt_actuales/fases/recoleccion.md,
 * RECEPCIÓN DE DOCUMENTOS) para anotar un adjunto ya resuelto en el propio
 * `contenido` de un mensaje — el modelo nunca "ve" un archivo, solo este
 * texto plano por cada adjunto. Compartido entre canales (WhatsApp, portal)
 * para que el mismo AgenteConversacionalService reciba siempre el mismo
 * shape, sin importar de dónde vino el archivo.
 */
class ContenidoMensajeConAdjuntos
{
    /**
     * @param  array<int, AdjuntoWhatsapp>  $adjuntos
     */
    public static function construir(string $texto, array $adjuntos): string
    {
        if ($adjuntos === []) {
            return $texto;
        }

        $bloques = collect($adjuntos)
            ->map(fn (AdjuntoWhatsapp $a) => "archivo_url: {$a->referencia}\ntexto_extraido: {$a->texto}")
            ->implode("\n\n");

        return $texto === '' ? $bloques : "{$texto}\n\n{$bloques}";
    }
}
