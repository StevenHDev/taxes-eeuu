<?php

namespace App\Services\WhatsappAgent;

use App\Contracts\MensajeConversacion;
use App\Enums\FaseConversacion;
use App\Enums\RolMensajeWhatsapp;
use App\Models\User;
use App\Services\Consentimiento\EnlaceActivacion;
use App\Support\AgentePromptVigente;
use App\Support\TelefonoWhatsapp;
use Illuminate\Support\Facades\Log;

/**
 * Corre el turno completo de function-calling para una conversación de
 * WhatsApp: arma el prompt de la fase vigente + historial, ejecuta el loop
 * hasta obtener una respuesta final de texto, y devuelve el resultado listo
 * para que ProcesarMensajeWhatsappJob lo persista/envíe.
 *
 * Recalcula la fase (y por tanto qué tools ve el modelo) al INICIO de cada
 * iteración del loop, no solo una vez al principio del turno: crear la
 * cuenta o guardar la última respuesta pendiente cambian la fase dentro del
 * mismo turno (VerificacionCuenta → Consentimiento, Entrevista → Seguimiento).
 */
class AgenteConversacionalService
{
    /**
     * Tope de vueltas de tool-calling dentro de un mismo turno — evita un
     * loop infinito si el modelo nunca produce texto final. Agotarlo fuerza
     * un cierre en texto plano (ver el final de responder()).
     */
    private const MAX_ITERACIONES = 20;

    public function __construct(
        private readonly EstadoConversacionResolver $resolver,
        private readonly OpenAiClient $openAi,
        private readonly ToolExecutor $toolExecutor,
        private readonly EnlaceActivacion $enlaceActivacion,
    ) {}

    /**
     * @param  iterable<int, MensajeConversacion>  $historial  orden cronológico ascendente;
     *                                                         ya incluye el mensaje entrante de este turno
     * @param  ?string  $telefono  de la conversación de WhatsApp, si el canal es ese — null
     *                             para el chat del portal. Se pasa explícito en vez de derivarse
     *                             del historial, para que este método no dependa de
     *                             WhatsappMensaje (ver App\Contracts\MensajeConversacion).
     * @param  bool  $canalPortal  true cuando este turno viene del chat del portal (ver
     *                             PortalChatController): el resolver da PortalDudas.
     * @return array{texto: string, prompt_version: ?int, cliente: ?User}
     */
    public function responder(?User $cliente, iterable $historial, User $actor, ?string $telefono = null, bool $canalPortal = false): array
    {
        $mensajes = $this->mensajesDesdeHistorial($historial);
        $promptVersion = AgentePromptVigente::version();

        for ($i = 0; $i < self::MAX_ITERACIONES; $i++) {
            $fase = $this->resolver->resolver($cliente, $canalPortal);

            $mensajesDeEsteTurno = $mensajes;

            if ($fase === FaseConversacion::Consentimiento && $cliente !== null) {
                $mensajesDeEsteTurno[] = ['role' => 'system', 'content' => $this->notaConsentimiento($mensajes, $cliente, $telefono)];
            }

            $respuesta = $this->openAi->completarChat(
                mensajes: [
                    ['role' => 'system', 'content' => (string) AgentePromptVigente::paraFase($fase)],
                    ...$mensajesDeEsteTurno,
                ],
                tools: ToolDefinitions::habilitadasParaFase($fase),
                // Solo en la primera llamada del turno: sin esto, el modelo a
                // veces respondía directo con texto sin invocar NINGUNA tool
                // — ni siquiera think, mucho menos la que guardaba —
                // como si contestara "de memoria" en vez de seguir el
                // protocolo. Encontrado en producción (2026-09-21,
                // conversación real con 3213445027): una racha de 9 minutos
                // y más de diez respuestas del cliente ("no", "si", etc.) se
                // perdieron así, una detrás de otra, sin ningún error — el
                // agente simplemente iba preguntando lo siguiente sin
                // guardar nada. 'required' obliga a invocar alguna tool en
                // esta llamada puntual (verificado contra la API real);
                // llamadas siguientes del mismo turno vuelven a 'auto' para
                // que el modelo pueda cerrar con texto una vez ya guardó lo
                // que correspondía.
                toolChoice: $i === 0 ? 'required' : null,
            );

            $toolCalls = $respuesta['tool_calls'] ?? [];

            if ($toolCalls === []) {
                return [
                    'texto' => self::sinRazonamientoFiltrado((string) ($respuesta['content'] ?? '')),
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

                $resultado = $this->toolExecutor->ejecutar($nombre, $argumentos, $cliente, $actor, telefono: $telefono);

                // Sin esto, una tool que falló con un error recuperable no
                // deja rastro: ToolExecutor nunca lanza para eso, así que el
                // job siempre queda "DONE" en el log del worker. Nunca se
                // loguean argumentos ni valores (pueden traer datos del
                // cliente): solo qué tool y si falló.
                Log::info('AgenteConversacionalService: tool call ejecutado.', [
                    'cliente_id' => $cliente?->id,
                    'tool' => $nombre,
                    'resultado_error' => $resultado['error'] ?? null,
                ]);

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
            'texto' => self::sinRazonamientoFiltrado(
                (string) ($respuestaFinal['content'] ?? '¿Me puedes repetir eso último? Quiero asegurarme de entenderlo bien.'),
            ),
            'prompt_version' => $promptVersion,
            'cliente' => $cliente,
        ];
    }

    /**
     * Red de seguridad contra una falla de tool-calling ya vista en
     * producción (2026-09-21, conversación real con 3213445027): el modelo
     * a veces escribe el razonamiento como un objeto JSON al principio del
     * mensaje final — con el mismo shape que los argumentos de la tool
     * `think` (ver ToolDefinitions::think(), `{"razonamiento": "..."}) — en
     * vez de invocarla como tool call. El prompt ya instruye explícitamente
     * que el razonamiento vive solo en `think` y nunca en el mensaje final,
     * pero esa instrucción sola
     * no bastó para evitarlo: el razonamiento del agente NUNCA puede
     * llegarle al cliente por WhatsApp, así que acá se corta en código como
     * última línea de defensa, no solo se le pide al modelo que no lo haga.
     */
    private static function sinRazonamientoFiltrado(string $texto): string
    {
        $recortado = ltrim($texto);
        $inicio = strlen($texto) - strlen($recortado);

        if (! str_starts_with($recortado, '{')) {
            return $texto;
        }

        foreach (self::posicionesDeCierre($texto, $inicio) as $fin) {
            $bloque = substr($texto, $inicio, $fin - $inicio + 1);
            $decodificado = json_decode($bloque, true);

            if (
                json_last_error() === JSON_ERROR_NONE
                && is_array($decodificado)
                && (\array_key_exists('razonamiento', $decodificado) || \array_key_exists('reasoning', $decodificado))
            ) {
                $resto = trim(substr($texto, $fin + 1));

                return $resto !== '' ? $resto : $texto;
            }
        }

        return $texto;
    }

    /**
     * Todas las posiciones de '}' desde $desde en adelante — probar
     * json_decode() en cada substring hasta ahí (en vez de contar llaves a
     * mano) es lo que evita romperse con una llave dentro de un string del
     * propio JSON (ej. el razonamiento mencionando "{" en su texto libre).
     *
     * @return list<int>
     */
    private static function posicionesDeCierre(string $texto, int $desde): array
    {
        $posiciones = [];

        for ($i = $desde; $i < \strlen($texto); $i++) {
            if ($texto[$i] === '}') {
                $posiciones[] = $i;
            }
        }

        return $posiciones;
    }

    /**
     * @param  iterable<int, MensajeConversacion>  $historial
     * @return array<int, array<string, mixed>>
     */
    private function mensajesDesdeHistorial(iterable $historial): array
    {
        $mensajes = [];

        foreach ($historial as $m) {
            $mensajes[] = match ($m->rolConversacion()) {
                RolMensajeWhatsapp::Cliente => ['role' => 'user', 'content' => $m->contenidoConversacion()],
                RolMensajeWhatsapp::Agente => ['role' => 'assistant', 'content' => $m->contenidoConversacion()],
                // El modelo ve que esto se le dijo al cliente, pero con su propio
                // rol marcado — nunca debe asumir que él mismo lo dijo ni que
                // implica que algún dato quedó guardado solo por haberse
                // mencionado (ver ESCALAMIENTO A HUMANO en el plan).
                RolMensajeWhatsapp::Preparador => ['role' => 'assistant', 'content' => "[Mensaje enviado por un preparador humano] {$m->contenidoConversacion()}"],
                RolMensajeWhatsapp::Sistema => ['role' => 'assistant', 'content' => "[Nota automática del sistema] {$m->contenidoConversacion()}"],
            };
        }

        return $mensajes;
    }

    /**
     * F2.8: fase Consentimiento. Sin contraseña, el enlace de activación
     * (F2.4: crea la contraseña y lleva al consentimiento); con contraseña,
     * el enlace al consentimiento, que pide iniciar sesión. Lo arma el
     * código, una vez por conversación.
     *
     * @param  array<int, array<string, mixed>>  $mensajes
     */
    private function notaConsentimiento(array $mensajes, User $cliente, ?string $telefono): string
    {
        if ($this->enlaceActivacion->pendiente($cliente) && $this->esSuTelefono($cliente, $telefono)) {
            return $this->notaEnlaceActivacion($mensajes, $cliente);
        }

        $enlace = route('portal.consentimiento');

        $yaCompartido = collect($mensajes)->contains(
            fn (array $m) => ($m['role'] ?? null) === 'assistant' && str_contains((string) ($m['content'] ?? ''), $enlace),
        );

        return $yaCompartido
            ? 'Ya le mandaste el enlace del consentimiento en esta conversación: no lo repitas salvo que te lo pida.'
            : "El cliente todavía no firmó su consentimiento. Explícale en pocas palabras qué es (nos autoriza a usar su información para preparar su declaración) y mándale este enlace tal cual; entra con su correo y su contraseña: {$enlace}";
    }

    /**
     * F2.4: a un cliente sin contraseña, el primer
     * enlace crea la contraseña (EnlaceActivacion), lo deja con la sesión
     * iniciada y lo lleva al consentimiento. Se arma una sola vez por
     * conversación: si ya se mandó y venció, el modelo pide otro con la tool
     * `enviar_enlace_activacion`.
     *
     * @param  array<int, array<string, mixed>>  $mensajes
     */
    private function notaEnlaceActivacion(array $mensajes, User $cliente): string
    {
        $prefijo = EnlaceActivacion::prefijo();

        $yaCompartido = collect($mensajes)->contains(
            fn (array $m) => ($m['role'] ?? null) === 'assistant'
                && str_contains((string) ($m['content'] ?? ''), $prefijo),
        );

        if ($yaCompartido) {
            return 'Ya le mandaste el enlace para crear su contraseña en esta conversación: no lo repitas. Si el cliente dice que venció o no le funciona, pide uno nuevo con la tool enviar_enlace_activacion.';
        }

        $enlace = $this->enlaceActivacion->para($cliente);

        return 'Todavía no le has mandado el enlace del portal seguro. Inclúyelo tal cual en tu respuesta de este turno, con una explicación breve y cálida: con ese enlace crea su contraseña y entra directo al portal, donde primero firma su consentimiento. Dile que el enlace vence en '.EnlaceActivacion::MINUTOS.' minutos y que, si vence, te pida otro. No le pidas que revise el correo: '.$enlace;
    }

    private function esSuTelefono(User $cliente, ?string $telefono): bool
    {
        return $telefono !== null && $cliente->phone !== null && TelefonoWhatsapp::normalizar($telefono) === $cliente->phone;
    }
}
