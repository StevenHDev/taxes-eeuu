<?php

namespace App\Services\WhatsappAgent;

use App\Enums\RolMensajeWhatsapp;
use App\Models\User;
use App\Models\WhatsappMensaje;
use App\Support\AgentePromptVigente;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Corre el turno completo de function-calling para una conversación de
 * WhatsApp: arma el prompt de la fase vigente + historial, ejecuta el loop
 * hasta obtener una respuesta final de texto, y devuelve el resultado listo
 * para que ProcesarMensajeWhatsappJob lo persista/envíe.
 *
 * Recalcula la fase (y por tanto qué tools ve el modelo) al INICIO de cada
 * iteración del loop, no solo una vez al principio del turno — ver decisión
 * de arquitectura en docs/implementar_agente_n8n.md: sin esto,
 * declarar_formas_cliente no podría encadenar de inmediato con
 * consultar_pendientes_cliente dentro del mismo turno (la fase pasaría de
 * DeterminacionFormas a Recoleccion recién en el turno siguiente, perdiendo
 * el arranque inmediato de la recolección que sí lograba n8n).
 */
class AgenteConversacionalService
{
    /**
     * Tope de vueltas de tool-calling dentro de un mismo turno — nunca
     * debería alcanzarse en un uso normal (2-4 tools por turno como mucho),
     * pero evita un loop infinito si el modelo nunca produce texto final.
     */
    private const MAX_ITERACIONES = 8;

    public function __construct(
        private readonly EstadoConversacionResolver $resolver,
        private readonly OpenAiClient $openAi,
        private readonly ToolExecutor $toolExecutor,
    ) {}

    /**
     * @param  Collection<int, WhatsappMensaje>  $historial  orden cronológico ascendente;
     *                                                       ya incluye el mensaje entrante de este turno
     * @return array{texto: string, prompt_version: ?int, cliente: ?User}
     */
    public function responder(?User $cliente, Collection $historial, User $actor): array
    {
        $mensajes = $this->mensajesDesdeHistorial($historial);
        $promptVersion = AgentePromptVigente::version();

        for ($i = 0; $i < self::MAX_ITERACIONES; $i++) {
            $fase = $this->resolver->resolver($cliente);

            $respuesta = $this->openAi->completarChat(
                mensajes: [
                    ['role' => 'system', 'content' => (string) AgentePromptVigente::paraFase($fase)],
                    ...$mensajes,
                ],
                tools: ToolDefinitions::paraFase($fase),
            );

            $toolCalls = $respuesta['tool_calls'] ?? [];

            if ($toolCalls === []) {
                return [
                    'texto' => (string) ($respuesta['content'] ?? ''),
                    'prompt_version' => $promptVersion,
                    'cliente' => $cliente,
                ];
            }

            $mensajes[] = [
                'role' => 'assistant',
                'content' => $respuesta['content'] ?? null,
                'tool_calls' => $toolCalls,
            ];

            foreach ($toolCalls as $toolCall) {
                $nombre = (string) ($toolCall['function']['name'] ?? '');
                $argumentos = json_decode((string) ($toolCall['function']['arguments'] ?? '{}'), true);
                $argumentos = is_array($argumentos) ? $argumentos : [];

                $resultado = $this->toolExecutor->ejecutar($nombre, $argumentos, $cliente, $actor);

                // crear_cliente_taxes puede correr a mitad de este mismo turno
                // (fase VerificacionCuenta) — el resto del loop, y el propio
                // job que llamó a responder(), necesitan enterarse del nuevo
                // cliente_id de inmediato, no en el turno siguiente.
                if ($nombre === 'crear_cliente_taxes' && isset($resultado['cliente_id'])) {
                    // No User::find(): su tipo de retorno admite Collection (recibe
                    // también un arreglo de ids) — acá siempre es un id suelto.
                    $cliente = User::query()->whereKey($resultado['cliente_id'])->first();
                }

                $mensajes[] = [
                    'role' => 'tool',
                    'tool_call_id' => (string) ($toolCall['id'] ?? ''),
                    'content' => (string) json_encode($resultado),
                ];
            }
        }

        Log::warning('AgenteConversacionalService: se agotaron las iteraciones sin respuesta final de texto.', [
            'cliente_id' => $cliente?->id,
        ]);

        return [
            'texto' => 'Dame un momento, ya te respondo.',
            'prompt_version' => $promptVersion,
            'cliente' => $cliente,
        ];
    }

    /**
     * @param  Collection<int, WhatsappMensaje>  $historial
     * @return array<int, array<string, mixed>>
     */
    private function mensajesDesdeHistorial(Collection $historial): array
    {
        return $historial->map(fn (WhatsappMensaje $m) => match ($m->rol) {
            RolMensajeWhatsapp::Cliente => ['role' => 'user', 'content' => $m->contenido],
            RolMensajeWhatsapp::Agente => ['role' => 'assistant', 'content' => $m->contenido],
            // El modelo ve que esto se le dijo al cliente, pero con su propio
            // rol marcado — nunca debe asumir que él mismo lo dijo ni que
            // implica que algún dato quedó guardado solo por haberse
            // mencionado (ver ESCALAMIENTO A HUMANO en el plan).
            RolMensajeWhatsapp::Preparador => ['role' => 'assistant', 'content' => "[Mensaje enviado por un preparador humano] {$m->contenido}"],
            RolMensajeWhatsapp::Sistema => ['role' => 'assistant', 'content' => "[Nota automática del sistema] {$m->contenido}"],
        })->values()->all();
    }
}
