<?php

namespace App\Services\WhatsappAgent;

use App\Contracts\MensajeConversacion;
use App\DataTransferObjects\AdjuntoWhatsapp;
use App\Enums\FaseConversacion;
use App\Enums\MetodoExtraccionDocumento;
use App\Enums\RolMensajeWhatsapp;
use App\Models\User;
use App\Support\AgentePromptVigente;
use Illuminate\Http\UploadedFile;
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
     * @param  iterable<int, MensajeConversacion>  $historial  orden cronológico ascendente;
     *                                                         ya incluye el mensaje entrante de este turno
     * @param  array<int, AdjuntoWhatsapp>  $adjuntos  ya descargados/extraídos por
     *                                                 AdjuntosWhatsappService para el mensaje de este
     *                                                 turno — el texto ya quedó anotado en el propio
     *                                                 $historial (ver ProcesarMensajeWhatsappJob), esto
     *                                                 solo sirve para resolver el archivo real si el
     *                                                 modelo invoca guardar_campo_cliente sobre uno de
     *                                                 ellos (ver resolverArchivo()).
     * @param  ?string  $telefono  de la conversación de WhatsApp, si el canal es ese — null
     *                             para otros canales (ej. portal web). Se pasa explícito en vez
     *                             de derivarse del historial, para que este método no dependa de
     *                             WhatsappMensaje (ver App\Contracts\MensajeConversacion).
     * @param  bool  $canalPortal  true cuando este turno viene del chat del portal web (ver
     *                             PortalChatController), nunca de WhatsApp. Mientras el cliente
     *                             ya tenga forma(s) declarada(s) (fase Recoleccion/Cierre según
     *                             los datos), este chat NO es quien recolecta — el formulario del
     *                             portal sí — así que se fuerza FaseConversacion::PortalDudas en
     *                             vez de la fase real, para exponer un prompt/tools de solo-dudas
     *                             (sin guardar_campo_cliente). Antes de que existan formas
     *                             declaradas (VerificacionCuenta/DeterminacionFormas) el chat sigue
     *                             siendo quien resuelve eso — ahí no se fuerza nada.
     *
     *                             El canal de WhatsApp (canalPortal=false) recibe el downgrade
     *                             simétrico opuesto: fase Recoleccion se fuerza a HandoffPortal —
     *                             el formulario del portal es quien recolecta ahora, este canal solo
     *                             entrega el link (una vez, ver notaLinkPortal()) y responde dudas.
     *                             Cierre NUNCA se fuerza a HandoffPortal, ni acá ni en canalPortal:
     *                             la atestación final sigue ocurriendo por WhatsApp (ver cierre.md,
     *                             pendiente Fase 6 del plan del portal seguro).
     * @return array{texto: string, prompt_version: ?int, cliente: ?User}
     */
    public function responder(?User $cliente, iterable $historial, User $actor, array $adjuntos = [], ?string $telefono = null, bool $canalPortal = false): array
    {
        $mensajes = $this->mensajesDesdeHistorial($historial);
        $promptVersion = AgentePromptVigente::version();

        for ($i = 0; $i < self::MAX_ITERACIONES; $i++) {
            $fase = $this->resolver->resolver($cliente);

            if ($canalPortal && in_array($fase, [FaseConversacion::Recoleccion, FaseConversacion::Cierre], true)) {
                $fase = FaseConversacion::PortalDudas;
            } elseif (! $canalPortal && $fase === FaseConversacion::Recoleccion) {
                $fase = FaseConversacion::HandoffPortal;
            }

            $mensajesDeEsteTurno = $mensajes;

            if ($fase === FaseConversacion::HandoffPortal) {
                // Nota efímera, nunca persistida: solo para esta llamada puntual a
                // la API, no se agrega a $mensajes (que sigue acumulando el resto
                // del turno) — ver notaLinkPortal().
                $mensajesDeEsteTurno[] = ['role' => 'system', 'content' => $this->notaLinkPortal($mensajes)];
            }

            $respuesta = $this->openAi->completarChat(
                mensajes: [
                    ['role' => 'system', 'content' => (string) AgentePromptVigente::paraFase($fase)],
                    ...$mensajesDeEsteTurno,
                ],
                tools: ToolDefinitions::habilitadasParaFase($fase),
                // Solo en la primera llamada del turno: sin esto, el modelo a
                // veces respondía directo con texto sin invocar NINGUNA tool
                // — ni siquiera think, mucho menos guardar_campo_cliente —
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

                // Sin esto, un turno donde el modelo debía llamar a
                // guardar_campo_cliente pero no lo hizo (o lo hizo con datos
                // contradictorios — ej. modo="no_aplica" en el mismo turno
                // en que el cliente acababa de confirmar lo contrario) no
                // deja ningún rastro: ToolExecutor nunca lanza para un error
                // de validación recuperable, así que el job siempre queda
                // "DONE" en el log del worker, sin pista de qué pasó
                // realmente. Encontrado en producción (2026-09-21,
                // conversación real con 3213445027): un Form 1095-A y una
                // respuesta a cuentas_extranjero se dieron por resueltos en
                // el texto sin quedar guardados, y no hubo forma de
                // confirmarlo sin desencriptar la base de datos a mano. Nunca
                // se loguea `contenido` (puede traer SSN, fecha de
                // nacimiento, etc.) — solo metadatos de qué se intentó
                // guardar y con qué resultado.
                Log::info('AgenteConversacionalService: tool call ejecutado.', [
                    'cliente_id' => $cliente?->id,
                    'tool' => $nombre,
                    'forma' => $argumentos['forma'] ?? null,
                    'campo' => $argumentos['campo'] ?? null,
                    'modo' => $argumentos['modo'] ?? null,
                    'tuvo_archivo' => $archivo !== null,
                    'resultado_error' => $resultado['error'] ?? null,
                    'resultado_estado' => $resultado['estado'] ?? null,
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
     * que el razonamiento vive solo en `think` y nunca en el mensaje final
     * (ver prompt_actuales/fases/recoleccion.md), pero esa instrucción sola
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

        // Si no hubo match exacto pero este turno trajo un único adjunto, no
        // existe ninguna otra opción a la que el modelo pudiera estar
        // refiriéndose — usarlo igual evita depender de que reproduzca sin
        // ningún error de un carácter una URL de media de Twilio larga
        // dentro de los argumentos de la tool call. Encontrado en producción
        // (2026-09-21, conversación real con 3213445027): un Form 1095-A que
        // sí se subió y sí se extrajo bien no quedó guardado en el primer
        // intento porque este match exacto falló — EventoValidator lo trató
        // como "sin archivo" (error de validación recuperable, no un crash),
        // y el cliente tuvo que preguntar explícitamente si se había
        // guardado para que el agente se diera cuenta y pidiera reenviarlo.
        if ($adjunto === null && count($adjuntos) === 1) {
            $adjunto = $adjuntos[0];
        }

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
     * Decide, de forma determinística (nunca a criterio del modelo), si el
     * link del portal ya se compartió antes en esta conversación — buscando
     * el link literal entre los mensajes que el agente ya le envió al
     * cliente. Encontrado el patrón útil en sinRazonamientoFiltrado(): una
     * regla de negocio verificable en código es más confiable que una
     * instrucción de prompt que dependa de que el modelo "recuerde" haberlo
     * mandado antes.
     *
     * @param  array<int, array<string, mixed>>  $mensajes  ya construidos por mensajesDesdeHistorial(),
     *                                                      antes de agregar la nota de este método
     */
    private function notaLinkPortal(array $mensajes): string
    {
        $link = self::linkPortal();

        $yaCompartido = collect($mensajes)->contains(
            fn (array $m) => ($m['role'] ?? null) === 'assistant'
                && str_contains((string) ($m['content'] ?? ''), $link),
        );

        return $yaCompartido
            ? 'Ya le compartiste el link del portal antes en esta conversación — no lo repitas de nuevo salvo que el cliente lo pida explícitamente.'
            : "Todavía no le has compartido el link del portal seguro en esta conversación. Inclúyelo tal cual en tu respuesta de este turno, junto con una explicación breve y cálida de qué es y qué debe hacer ahí: {$link}";
    }

    /**
     * Punto de entrada único del portal seguro (ver routes/portal.php) —
     * PortalFormularioController::index() ya redirige a portal.chat si el
     * cliente todavía no tiene ninguna forma declarada, así que este mismo
     * link sirve sin importar en qué punto del formulario esté. No requiere
     * sesión iniciada: si el cliente no está logueado, el middleware `auth`
     * lo manda a /login y, tras autenticarse, Laravel lo devuelve acá solo
     * (redirect()->intended()) — nunca hace falta armar un link firmado.
     */
    private static function linkPortal(): string
    {
        return url('/portal');
    }
}
