<?php

namespace App\Services\DocumentoExtraccion;

use App\Services\WhatsappAgent\OpenAiClient;
use App\Support\EventoValidator;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Completa, vía IA, los campos que un documento recién subido en el portal
 * "revela" (ver TaxFieldCatalog::revelaPara()) — mismo mecanismo que ya usa
 * el agente conversacional de WhatsApp al leer el texto extraído de un
 * documento y decidir qué más guardar con guardar_campo_cliente, pero
 * disparado desde el formulario (ver PortalFormularioController::guardarCampo())
 * en vez de un turno de chat. Sin esto, el formulario guardaría el documento
 * a secas y el cliente tendría que volver a escribir a mano datos que el
 * documento ya trae (ej. salarios/retenciones de un W-2).
 *
 * Nunca lanza: una falla acá no debe bloquear que el documento principal
 * quede guardado — el cliente simplemente ve esos campos revelados como
 * pendientes normales, igual que si el documento no hubiera revelado nada.
 */
class RevelacionExtractorService
{
    public function __construct(
        private readonly OpenAiClient $openAi,
        private readonly EventoValidator $eventoValidator,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $revela  shape de TaxFieldCatalog::revelaPara()
     * @return array<int, array<string, mixed>> shape de "revelados" para EventoRecoleccionData
     */
    public function extraer(string $textoDocumento, array $revela): array
    {
        if ($revela === [] || trim($textoDocumento) === '') {
            return [];
        }

        try {
            $respuesta = $this->openAi->completarChat(
                mensajes: [
                    ['role' => 'system', 'content' => $this->prompt($revela)],
                    ['role' => 'user', 'content' => $textoDocumento],
                ],
                tools: [$this->tool()],
                toolChoice: 'required',
            );

            $toolCall = $respuesta['tool_calls'][0] ?? null;

            if (! is_array($toolCall)) {
                return [];
            }

            $argumentos = json_decode((string) ($toolCall['function']['arguments'] ?? '{}'), true);
            $encontrados = is_array($argumentos['revelados'] ?? null) ? $argumentos['revelados'] : [];

            return $this->combinarConMetadata($encontrados, $revela);
        } catch (Throwable $e) {
            Log::warning('RevelacionExtractorService: no se pudo extraer revelados por IA.', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $revela
     */
    private function prompt(array $revela): string
    {
        $lista = collect($revela)
            ->map(fn (array $r) => "- {$r['campo']}: {$r['descripcion']}")
            ->implode("\n");

        return <<<PROMPT
            Vas a leer el texto extraído de un documento fiscal que un cliente ya subió a su expediente. Tu único trabajo es identificar, de la siguiente lista de datos que este tipo de documento normalmente trae, cuáles aparecen REALMENTE en el texto y con qué valor:

            {$lista}

            GROUNDING ESTRICTO: invoca la tool solo con los campos cuyo valor puedas leer literalmente en el texto. Nunca inventes, asumas ni completes un valor que no esté explícito. Si ninguno aparece, invoca la tool con un arreglo vacío.
            PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    private function tool(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'registrar_revelados',
                'description' => 'Registra los valores que el documento realmente revela, según la lista dada.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'revelados' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'campo' => ['type' => 'string'],
                                    'contenido' => [
                                        'type' => 'string',
                                        'description' => 'El valor tal como aparece en el documento, siempre como string (incluso para una estructura compleja, como JSON).',
                                    ],
                                ],
                                'required' => ['campo', 'contenido'],
                            ],
                        ],
                    ],
                    'required' => ['revelados'],
                ],
            ],
        ];
    }

    /**
     * Completa cada hallazgo del modelo (solo campo+contenido) con la
     * metadata real del catálogo (forma, tipo_campo, tipo_dato, subcampo,
     * acumulable) — nunca se confía en que el modelo la reproduzca bien.
     *
     * @param  array<int, array<string, mixed>>  $encontrados
     * @param  array<int, array<string, mixed>>  $revela
     * @return array<int, array<string, mixed>>
     */
    private function combinarConMetadata(array $encontrados, array $revela): array
    {
        $porCampo = collect($revela)->keyBy('campo');
        $resultado = [];

        foreach ($encontrados as $item) {
            $campo = (string) ($item['campo'] ?? '');
            $meta = $porCampo->get($campo);

            if ($meta === null) {
                continue;
            }

            $datos = [
                'forma' => $meta['forma'],
                'campo' => $campo,
                'tipo_campo' => $meta['tipo_campo'],
                'tipo_dato' => $meta['tipo_dato'],
                'contenido' => $item['contenido'] ?? null,
                'acumular' => (bool) ($meta['acumulable'] ?? false),
                'subcampo' => $meta['subcampo'] ?? null,
            ];

            // Mismo paso que EventoRequest/ToolExecutor: el modelo siempre
            // manda `contenido` como string, incluso para un tipo_dato
            // object/array — hay que decodificarlo antes de guardarlo.
            $resultado[] = [...$datos, ...$this->eventoValidator->decodificarContenido($datos)];
        }

        return $resultado;
    }
}
