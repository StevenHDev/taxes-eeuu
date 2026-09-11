<?php

namespace App\DataTransferObjects;

use App\Enums\MetodoExtraccionDocumento;

/**
 * Un adjunto de WhatsApp ya resuelto: descargado y con su texto extraído —
 * ver App\Services\WhatsappAgent\AdjuntosWhatsappService. `referencia` es la
 * misma que traía MensajeEntranteWhatsapp::$mediaReferencias (la URL de
 * Twilio o el media id de Meta); se reutiliza tal cual como el `archivo_url`
 * que ve el modelo (prompt_actuales/fases/recoleccion.md, RECEPCIÓN DE
 * DOCUMENTOS) — así, cuando el modelo invoca guardar_campo_cliente con
 * modo="archivo" y contenido=ese mismo valor, AgenteConversacionalService
 * puede encontrar de vuelta el archivo local correspondiente sin necesitar
 * ningún otro mecanismo de correlación.
 */
final readonly class AdjuntoWhatsapp
{
    public function __construct(
        public string $referencia,
        public string $rutaLocal,
        public string $mimeType,
        public string $texto,
        public MetodoExtraccionDocumento $metodo,
    ) {}
}
