<?php

namespace App\Jobs;

use App\DataTransferObjects\MensajeEntranteWhatsapp;
use App\Enums\EstadoControlConversacion;
use App\Enums\RolMensajeWhatsapp;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\WhatsappControl;
use App\Models\WhatsappMensaje;
use App\Services\Whatsapp\WhatsappChannel;
use App\Services\WhatsappAgent\AdjuntosWhatsappService;
use App\Services\WhatsappAgent\AgenteConversacionalService;
use App\Support\AgenteWhatsappUser;
use App\Support\ContenidoMensajeConAdjuntos;
use App\Support\RedactorDatosSensibles;
use App\Support\TelefonoWhatsapp;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Procesa un mensaje entrante de WhatsApp: idempotencia por mensajeId,
 * resuelve/crea el estado de control de la conversación, guarda el mensaje,
 * invoca al agente conversacional (si la conversación no está en modo
 * `humano` — ver ESCALAMIENTO A HUMANO en docs/implementar_agente_n8n.md), y
 * envía + persiste la respuesta. Agnóstico de proveedor: el payload crudo ya
 * lo normalizó `WhatsappChannel` al construir `MensajeEntranteWhatsapp`, y el
 * mismo canal se usa para responder.
 *
 * Límite conocido, aceptado por ahora: si el job falla DESPUÉS de guardar el
 * mensaje entrante pero ANTES de terminar de enviar la respuesta (ej. el
 * proveedor caído), un reintento del propio job (no un reintento del
 * proveedor) se detendría en el chequeo de idempotencia de abajo sin generar
 * ni enviar la respuesta pendiente — requeriría un estado explícito de
 * "respuesta ya enviada" (patrón outbox) para cerrarse del todo; se
 * documenta como deuda conocida en vez de resolverse acá.
 */
class ProcesarMensajeWhatsappJob implements ShouldQueue
{
    use Queueable;

    /**
     * Lo que queda en el historial cuando un cliente con la entrevista nueva
     * manda un archivo: el agente lo ve y responde con el enlace a
     * documentos del portal (ver seguimiento.md y entrevista.md).
     */
    public const ARCHIVO_NO_GUARDADO = '[El cliente envió un archivo por WhatsApp; no se guardó. Los documentos se suben solo en el portal.]';

    public function __construct(private readonly MensajeEntranteWhatsapp $mensaje) {}

    public function handle(AgenteConversacionalService $agente, WhatsappChannel $canal, AdjuntosWhatsappService $adjuntosService): void
    {
        if ($this->mensaje->mensajeId === '') {
            return;
        }

        // Reintentos del proveedor (mismo mensajeId) no deben duplicar el
        // mensaje ni procesarse dos veces en paralelo — lock de caché + la
        // columna única whatsapp_mensajes.mensaje_externo_id como respaldo final.
        $lock = Cache::lock("whatsapp-mensaje-procesado:{$this->mensaje->mensajeId}", 10);

        if (! $lock->get()) {
            return;
        }

        try {
            if (WhatsappMensaje::query()->where('mensaje_externo_id', $this->mensaje->mensajeId)->exists()) {
                return;
            }

            // Normalizado de nuevo acá (no solo en el canal): este es el
            // punto real donde el teléfono se usa para comparar/guardar en
            // whatsapp_control/whatsapp_mensajes — ver App\Support\TelefonoWhatsapp
            // para el bug real que motivó esta doble garantía.
            $telefono = TelefonoWhatsapp::normalizar($this->mensaje->telefono) ?? $this->mensaje->telefono;

            $control = WhatsappControl::query()->firstOrCreate(
                ['telefono' => $telefono],
                [
                    'estado' => EstadoControlConversacion::Agente,
                    'cliente_id' => User::query()
                        ->where('role', UserRole::Client)
                        ->where('phone', $telefono)
                        ->value('id'),
                ],
            );

            // Se resuelven ANTES de guardar el mensaje: el texto extraído
            // queda anotado en el propio `contenido` persistido (ver
            // construirContenido), para que el historial re-leído en
            // cualquier turno futuro —no solo este— siga viendo qué decía el
            // documento, sin depender de que el agente lo procese ahora mismo.
            $clienteActual = $control->cliente_id ? User::query()->whereKey($control->cliente_id)->first() : null;

            // F2.8 (9.6, T9): con la entrevista nueva, un archivo por WhatsApp
            // no se descarga ni se lee (nada llega a OpenAI): los documentos
            // van solo por el portal. Y lo sensible escrito en el chat se
            // oculta antes de guardarlo. También antes de que exista la
            // cuenta: todavía no se sabe qué flujo va a usar.
            $entrevistaNueva = $clienteActual?->usaEntrevistaCatalogo() === true;
            $protegido = $entrevistaNueva || $clienteActual === null;

            $adjuntos = $this->mensaje->mediaReferencias !== [] && ! $entrevistaNueva
                ? $adjuntosService->resolver($this->mensaje->mediaReferencias)
                : [];

            $texto = $protegido ? RedactorDatosSensibles::redactar($this->mensaje->texto) : $this->mensaje->texto;
            $contenido = $entrevistaNueva && $this->mensaje->mediaReferencias !== []
                ? trim($texto."\n".self::ARCHIVO_NO_GUARDADO)
                : ContenidoMensajeConAdjuntos::construir($texto, $adjuntos);

            WhatsappMensaje::query()->create([
                'telefono' => $telefono,
                'cliente_id' => $control->cliente_id,
                'rol' => RolMensajeWhatsapp::Cliente,
                'contenido' => $contenido,
                'mensaje_externo_id' => $this->mensaje->mensajeId,
                'proveedor' => $this->mensaje->proveedor,
            ]);

            if ($control->esHumano()) {
                return;
            }

            $cliente = $clienteActual;
            $historial = WhatsappMensaje::query()->where('telefono', $telefono)->orderBy('id')->get();

            $resultado = $agente->responder($cliente, $historial, AgenteWhatsappUser::resolver(), $adjuntos, $telefono);

            // crear_cliente_taxes puede haber corrido a mitad del turno — deja
            // el vínculo de la conversación con el cliente recién creado, en
            // vez de esperar a que un mensaje futuro lo resuelva por teléfono.
            if ($resultado['cliente'] !== null && $resultado['cliente']->id !== $control->cliente_id) {
                $control->update(['cliente_id' => $resultado['cliente']->id]);
            }

            // Condición de carrera: si un preparador tomó control mientras se
            // generaba la respuesta, no se envía por encima de él — se
            // vuelve a leer de la base, no del objeto en memoria de arriba.
            if ($control->fresh()->esHumano()) {
                return;
            }

            $idRespuesta = $canal->enviarTexto($telefono, $resultado['texto']);

            WhatsappMensaje::query()->create([
                'telefono' => $telefono,
                'cliente_id' => $resultado['cliente']?->id,
                'rol' => RolMensajeWhatsapp::Agente,
                'contenido' => $resultado['texto'],
                'mensaje_externo_id' => $idRespuesta,
                'proveedor' => $this->mensaje->proveedor,
                'prompt_version' => $resultado['prompt_version'],
            ]);
        } finally {
            $lock->release();
        }
    }
}
