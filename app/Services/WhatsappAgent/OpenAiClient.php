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
     * @param  ?string  $toolChoice  'required' obliga al modelo a invocar alguna tool en esta
     *                               llamada — nunca se manda si $tools está vacío (la API lo rechaza).
     * @return array<string, mixed> choices[0].message
     */
    public function completarChat(array $mensajes, array $tools, ?string $modelo = null, ?string $toolChoice = null): array
    {
        $modeloResuelto = $modelo ?? (string) config('services.openai.model');

        $respuesta = Http::withToken((string) config('services.openai.api_key'))
            ->timeout((int) config('services.openai.timeout', 30))
            ->retry(
                (int) config('services.openai.retries', 3),
                (int) config('services.openai.retry_backoff_ms', 500),
                throw: false,
            )
            ->post(self::URL_CHAT_COMPLETIONS, array_filter([
                'model' => $modeloResuelto,
                'messages' => $mensajes,
                'tools' => $tools,
                'tool_choice' => $tools !== [] ? $toolChoice : null,
                // Un modelo de razonamiento (o1/o3/o4, gpt-5.x) rechaza tool
                // calling en /v1/chat/completions con 400 a menos que esto
                // se mande explícitamente en "none" — encontrado en
                // producción al cambiar a gpt-5.6-luna: cada turno con
                // tools fallaba (400 "Function tools with reasoning_effort
                // are not supported... set reasoning_effort to 'none'"), y
                // por el límite ya documentado de este job (reintento
                // silencioso vía el chequeo de idempotencia) el cliente
                // nunca recibía respuesta. Un modelo NO razonador (ej.
                // gpt-4.1-mini) hace lo contrario — RECHAZA este parámetro
                // si no lo reconoce (probado contra la API real) — por eso
                // solo se manda cuando el propio modelo lo requiere.
                'reasoning_effort' => $tools !== [] && $this->esModeloDeRazonamiento($modeloResuelto) ? 'none' : null,
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

    /**
     * Heurística sobre el nombre del modelo, no una lista fija: la familia
     * "o" (o1/o3/o4/...) y toda gpt-5.x son modelos de razonamiento — las
     * familias anteriores (gpt-4.x, gpt-3.5) no lo son. Si OpenAI lanza una
     * familia nueva que rompa este patrón, hay que actualizar esto — no hay
     * forma de detectarlo desde la propia respuesta de la API sin haber
     * hecho ya la llamada.
     */
    private function esModeloDeRazonamiento(string $modelo): bool
    {
        return (bool) preg_match('/^(o\d|gpt-5)/', $modelo);
    }

    /**
     * Transcripción estricta de una imagen (Nivel 2 de extracción de
     * documentos — ver docs/implementar_agente_n8n.md) reutilizando
     * completarChat() para no duplicar timeout/reintentos: la API de chat
     * completions de OpenAI acepta contenido `image_url` dentro de un
     * mensaje `user` en el mismo endpoint, no hace falta uno separado.
     *
     * @param  string  $imagenDataUrl  data URL completa (`data:image/png;base64,...`) —
     *                                 nunca se expone el archivo por una URL pública para esto.
     */
    public function transcribirImagen(string $prompt, string $imagenDataUrl): string
    {
        $mensaje = $this->completarChat(
            mensajes: [[
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $prompt],
                    ['type' => 'image_url', 'image_url' => ['url' => $imagenDataUrl]],
                ],
            ]],
            tools: [],
            modelo: (string) config('services.openai.vision_model'),
        );

        return (string) ($mensaje['content'] ?? '');
    }
}
