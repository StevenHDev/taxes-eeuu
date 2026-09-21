<?php

namespace App\Services\WhatsappAgent;

use App\DataTransferObjects\AdjuntoWhatsapp;
use App\Enums\MetodoExtraccionDocumento;
use App\Enums\RolMensajeWhatsapp;
use App\Models\User;
use App\Models\WhatsappMensaje;
use App\Support\AgentePromptVigente;
use Illuminate\Http\UploadedFile;
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
     * Tope de vueltas de tool-calling dentro de un mismo turno — evita un
     * loop infinito si el modelo nunca produce texto final. Subido de 8 a
     * 20: encontrado en la conversación real con 3213445027 (2026-09-21,
     * post-deploy de las Fases 0-4) que 8 ya no alcanza con ACTIVOS en 44
     * pasos — un paso Grupo por sí solo puede necesitar hasta 5 guardados
     * de no_aplica + 1 consulta de pendientes en el mismo turno, y agotar
     * el límite a mitad de esa resolución fuerza un cierre prematuro
     * (ver el bloque de abajo) que deja al agente confundido sobre qué ya
     * se preguntó — contribuyendo a las repreguntas observadas en esa
     * misma conversación.
     */
    private const MAX_ITERACIONES = 20;

    public function __construct(
        private readonly EstadoConversacionResolver $resolver,
        private readonly OpenAiClient $openAi,
        private readonly ToolExecutor $toolExecutor,
    ) {}

    /**
     * @param  Collection<int, WhatsappMensaje>  $historial  orden cronológico ascendente;
     *                                                       ya incluye el mensaje entrante de este turno
     * @param  array<int, AdjuntoWhatsapp>  $adjuntos  ya descargados/extraídos por
     *                                                 AdjuntosWhatsappService para el mensaje de este
     *                                                 turno — el texto ya quedó anotado en el propio
     *                                                 $historial (ver ProcesarMensajeWhatsappJob), esto
     *                                                 solo sirve para resolver el archivo real si el
     *                                                 modelo invoca guardar_campo_cliente sobre uno de
     *                                                 ellos (ver resolverArchivo()).
     * @return array{texto: string, prompt_version: ?int, cliente: ?User}
     */
    public function responder(?User $cliente, Collection $historial, User $actor, array $adjuntos = []): array
    {
        $mensajes = $this->mensajesDesdeHistorial($historial);
        $promptVersion = AgentePromptVigente::version();
        // Todas las filas de $historial son de esta misma conversación (ver
        // ProcesarMensajeWhatsappJob, que las trae con where('telefono', ...))
        // — se deriva de ahí en vez de agregar un parámetro nuevo al método.
        $telefono = $historial->last()?->telefono;

        for ($i = 0; $i < self::MAX_ITERACIONES; $i++) {
            $fase = $this->resolver->resolver($cliente);

            $respuesta = $this->openAi->completarChat(
                mensajes: [
                    ['role' => 'system', 'content' => (string) AgentePromptVigente::paraFase($fase)],
                    ...$mensajes,
                ],
                tools: ToolDefinitions::habilitadasParaFase($fase),
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

                [$archivo, $metodoExtraccion] = $this->resolverArchivo($nombre, $argumentos, $adjuntos);

                $resultado = $this->toolExecutor->ejecutar(
                    $nombre,
                    $argumentos,
                    $cliente,
                    $actor,
                    file: $archivo,
                    metodoExtraccion: $metodoExtraccion,
                    telefono: $telefono,
                );

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

        Log::warning('AgenteConversacionalService: se agotaron las iteraciones de tools sin respuesta final — forzando cierre de turno en texto plano.', [
            'cliente_id' => $cliente?->id,
        ]);

        // Sin esto, el turno terminaba con una frase fija ("Dame un momento,
        // ya te respondo.") que no cumplía lo que prometía: nada volvía a
        // intentar responder — el cliente quedaba esperando hasta que él
        // mismo escribiera de nuevo (encontrado en la conversación real con
        // 3213445027, 2026-09-18). Al quitar `tools` de esta última llamada,
        // el modelo no puede seguir encadenando tool calls — está obligado a
        // cerrar el turno con un mensaje real usando todo lo que ya se sabe.
        $respuestaFinal = $this->openAi->completarChat(
            mensajes: [
                ['role' => 'system', 'content' => (string) AgentePromptVigente::paraFase($fase)],
                ...$mensajes,
                ['role' => 'system', 'content' => 'Ya no puedes invocar ninguna tool más en este turno. Responde ahora mismo al cliente con un mensaje de texto natural, usando lo que ya sabes de esta conversación — nunca digas que vas a revisar algo ni prometas una respuesta posterior, ciérralo ahora.'],
            ],
            tools: [],
        );

        return [
            'texto' => (string) ($respuestaFinal['content'] ?? '¿Me puedes repetir eso último? Quiero asegurarme de entenderlo bien.'),
            'prompt_version' => $promptVersion,
            'cliente' => $cliente,
        ];
    }

    /**
     * Correlaciona un tool call de guardar_campo_cliente (modo="archivo") con
     * el adjunto real que le corresponde: el modelo devuelve en `contenido`
     * el mismo `archivo_url` que se le mostró (ver AdjuntoWhatsapp), así que
     * alcanza con buscarlo por igualdad exacta — sin depender de posición ni
     * de que solo llegue un adjunto por mensaje.
     *
     * @param  array<string, mixed>  $argumentos
     * @param  array<int, AdjuntoWhatsapp>  $adjuntos
     * @return array{0: ?UploadedFile, 1: ?MetodoExtraccionDocumento}
     */
    private function resolverArchivo(string $nombreTool, array $argumentos, array $adjuntos): array
    {
        if ($nombreTool !== 'guardar_campo_cliente' || ($argumentos['modo'] ?? null) !== 'archivo') {
            return [null, null];
        }

        $referencia = (string) ($argumentos['contenido'] ?? '');
        $adjunto = collect($adjuntos)->first(fn (AdjuntoWhatsapp $a) => $a->referencia === $referencia);

        if ($adjunto === null) {
            return [null, null];
        }

        // La extensión decide qué acepta EventoValidator/EventoRecoleccionService
        // (formatos_aceptados) — se deriva del mime_type real ya resuelto por el
        // canal, nunca del nombre del archivo (WhatsApp no manda uno útil).
        $extension = match ($adjunto->mimeType) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'bin',
        };

        return [
            new UploadedFile($adjunto->rutaLocal, "documento.{$extension}", $adjunto->mimeType, test: true),
            $adjunto->metodo,
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
