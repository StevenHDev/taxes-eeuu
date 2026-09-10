<?php

namespace App\Services\WhatsappAgent;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Wrapper propio sobre Http:: para chat completions + function calling de
 * OpenAI — no un SDK de terceros (ver decisión de arquitectura en
 * docs/implementar_agente_n8n.md): la superficie que se necesita es chica, y
 * esto da control total de timeout/reintentos por conversación concurrente
 * sin depender del ritmo de release de un paquete externo.
 */
class OpenAiClient
{
    private const URL_CHAT_COMPLETIONS = 'https://api.openai.com/v1/chat/completions';

    /**
     * Un solo turno de function-calling: envía el historial + las tools
     * disponibles para la fase vigente, y devuelve el mensaje del asistente
     * tal cual lo entrega la API (`content` y/o `tool_calls`) — el loop de
     * "ejecutar tool call → volver a llamar" vive en AgenteConversacionalService,
     * no acá.
     *
     * @param  array<int, array<string, mixed>>  $mensajes  Historial en formato chat de OpenAI (role/content/tool_calls/tool_call_id).
     * @param  array<int, array<string, mixed>>  $tools  Definiciones de tools en formato function-calling de OpenAI.
     * @return array<string, mixed> choices[0].message
     */
    public function completarChat(array $mensajes, array $tools, ?string $modelo = null): array
    {
        $respuesta = Http::withToken((string) config('services.openai.api_key'))
            ->timeout((int) config('services.openai.timeout', 30))
            ->retry(
                (int) config('services.openai.retries', 3),
                (int) config('services.openai.retry_backoff_ms', 500),
                throw: false,
            )
            ->post(self::URL_CHAT_COMPLETIONS, array_filter([
                'model' => $modelo ?? config('services.openai.model'),
                'messages' => $mensajes,
                'tools' => $tools,
            ]));

        if ($respuesta->failed()) {
            throw new RuntimeException(
                "Fallo la llamada a OpenAI (chat completions): HTTP {$respuesta->status()} — {$respuesta->body()}",
            );
        }

        $mensaje = $respuesta->json('choices.0.message');

        if (! is_array($mensaje)) {
            throw new RuntimeException('Respuesta de OpenAI sin choices[0].message: '.$respuesta->body());
        }

        return $mensaje;
    }
}
