<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SupabaseWhatsappConversationService
{
    /**
     * Trae la conversación de WhatsApp asociada a un teléfono, consultando
     * la tabla de Supabase que alimenta el agente (session_id ~ teléfono).
     *
     * No conocemos el formato exacto en que n8n guarda el session_id (con o
     * sin "+", con "whatsapp:" o el JID de WhatsApp), así que filtramos por
     * los dígitos del teléfono contenidos en session_id en vez de una
     * igualdad exacta.
     *
     * @return array<int, array{role: string, content: string, created_at: ?string}>
     */
    public function paraTelefono(string $telefono): array
    {
        $digitos = preg_replace('/\D+/', '', $telefono) ?? '';

        if ($digitos === '') {
            return [];
        }

        $url = rtrim((string) config('services.supabase.url'), '/')
            .'/rest/v1/'.config('services.supabase.whatsapp_table');

        $response = Http::withHeaders([
            'apikey' => config('services.supabase.key'),
            'Authorization' => 'Bearer '.config('services.supabase.key'),
        ])->get($url, [
            'select' => 'session_id,message,created_at',
            'session_id' => "ilike.*{$digitos}*",
            'order' => 'id.asc',
        ]);

        if ($response->failed()) {
            return [];
        }

        return collect((array) $response->json())
            ->map(fn (array $fila) => $this->normalizarMensaje($fila))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array{role: string, content: string, created_at: ?string}|null
     */
    private function normalizarMensaje(array $fila): ?array
    {
        $message = $fila['message'] ?? null;

        if (! is_array($message)) {
            return null;
        }

        $tipo = $message['type'] ?? $message['role'] ?? 'unknown';
        // n8n guarda el mensaje plano ({type, content, ...}), pero soportamos
        // también un posible wrapper "data" (formato StoredMessage de LangChain).
        $data = is_array($message['data'] ?? null) ? $message['data'] : $message;
        $contenido = $data['content'] ?? $message['content'] ?? $message['text'] ?? '';

        if (is_array($contenido)) {
            $contenido = collect($contenido)
                ->map(fn ($parte) => is_array($parte) ? ($parte['text'] ?? '') : $parte)
                ->filter()
                ->implode("\n");
        }

        $contenido = $this->limpiarRastroDeHerramientas(trim((string) $contenido));

        if ($contenido === '') {
            return null;
        }

        return [
            'role' => $this->normalizarRol((string) $tipo),
            'content' => $contenido,
            'created_at' => $this->normalizarFechaUtc($fila['created_at'] ?? null),
        ];
    }

    /**
     * La columna created_at es "timestamp without time zone" y Supabase la
     * guarda en UTC, pero PostgREST la devuelve sin offset (ej.
     * "2026-08-26T21:04:17.479465"). Sin el "Z", el navegador la interpreta
     * como hora local del dispositivo en vez de UTC, así que la marcamos
     * explícitamente antes de mandarla al frontend.
     */
    private function normalizarFechaUtc(?string $fecha): ?string
    {
        if ($fecha === null || $fecha === '') {
            return null;
        }

        $fecha = str_replace(' ', 'T', $fecha);

        if (! preg_match('/[Zz]|[+-]\d{2}:?\d{2}$/', $fecha)) {
            $fecha .= 'Z';
        }

        return $fecha;
    }

    private function normalizarRol(string $tipo): string
    {
        return match (Str::lower($tipo)) {
            'human', 'user' => 'human',
            'ai', 'assistant', 'bot' => 'ai',
            default => 'system',
        };
    }

    /**
     * El agente de n8n antepone un rastro de herramientas usadas al mensaje
     * real, ej: "[Used tools: Tool: Think1, Input: {}, Result: [...]] Hola...".
     * Ese bloque va entre corchetes balanceados (puede traer arreglos/objetos
     * anidados), así que lo recortamos contando profundidad en vez de con una
     * regex simple, que cortaría en el primer "]" que encuentre.
     */
    private function limpiarRastroDeHerramientas(string $contenido): string
    {
        if (! str_starts_with($contenido, '[Used tools:')) {
            return $contenido;
        }

        $profundidad = 0;

        for ($i = 0; $i < strlen($contenido); $i++) {
            if ($contenido[$i] === '[') {
                $profundidad++;
            } elseif ($contenido[$i] === ']') {
                $profundidad--;

                if ($profundidad === 0) {
                    return ltrim(substr($contenido, $i + 1));
                }
            }
        }

        return $contenido;
    }
}
