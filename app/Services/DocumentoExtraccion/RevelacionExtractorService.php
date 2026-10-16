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

        // Reindexado 0..n-1 a propósito: el id que arma prompt()/tool() es
        // justamente la posición acá, y combinarConMetadata() busca por esa
        // misma posición — ver el porqué del esquema completo en el docblock
        // de la clase.
        $revela = array_values($revela);

        try {
            $respuesta = $this->openAi->completarChat(
                mensajes: [
                    ['role' => 'system', 'content' => $this->prompt($revela)],
                    ['role' => 'user', 'content' => $textoDocumento],
                ],
                tools: [$this->tool($revela)],
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
     * Cada dato esperado se numera (0, 1, 2...) y el modelo debe referirse a
     * él por ese id, nunca por el nombre del campo — ver el porqué en el
     * docblock de tool().
     *
     * @param  array<int, array<string, mixed>>  $revela  ya reindexado 0..n-1 por extraer()
     */
    private function prompt(array $revela): string
    {
        $lista = collect($revela)
            ->map(fn (array $r, int $id) => "- id \"{$id}\": {$r['descripcion']}")
            ->implode("\n");

        return <<<PROMPT
            Vas a leer el texto extraído de un documento fiscal que un cliente ya subió a su expediente. Tu único trabajo es identificar, de la siguiente lista de datos que este tipo de documento normalmente trae, cuáles aparecen REALMENTE en el texto y con qué valor:

            {$lista}

            GROUNDING ESTRICTO: invoca la tool solo con los id cuyo valor puedas leer literalmente en el texto, usando el id EXACTO de la lista (nunca el nombre de un campo, ni un id que no esté en la lista). Nunca inventes, asumas ni completes un valor que no esté explícito. Si ninguno aparece, invoca la tool con un arreglo vacío. Si dos ids de la lista describen el mismo dato bajo nombres distintos (ej. dos columnas de una misma tabla), repórtalos por separado, cada uno con su propio id y su propio valor — nunca mezcles ni promedies sus valores en uno solo.
            PROMPT;
    }

    /**
     * El modelo identifica cada hallazgo por `id` (la posición en la lista
     * de prompt(), restringida acá vía `enum`) en vez de por el nombre del
     * campo (`campo`, como antes).
     *
     * Motivo del cambio: un documento puede declarar varias relaciones hacia
     * el MISMO campo-destino pero distinto subcampo (ej. 1095-A → las 3
     * columnas de `marketplace_seguro`: premium_mensual, slcsp,
     * aptc_recibido). Pedirle al modelo que devuelva solo el nombre del
     * campo es ambiguo por construcción en ese caso — y
     * combinarConMetadata() no tenía forma de desambiguar, así que
     * terminaba quedándose con una sola relación de las tres (ver
     * incidente en producción, 2026-10-16: 2 de las 3 columnas del 1095-A
     * se perdían en silencio). Un `enum` de ids por posición es imposible de
     * confundir entre sí, sin importar cuántas relaciones compartan el mismo
     * campo-destino, y además deja que la propia API rechace cualquier id
     * fuera de rango en vez de que el código tenga que validarlo a mano.
     *
     * @param  array<int, array<string, mixed>>  $revela  ya reindexado 0..n-1 por extraer()
     * @return array<string, mixed>
     */
    private function tool(array $revela): array
    {
        $ids = array_map(strval(...), array_keys($revela));

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
                                    'id' => [
                                        'type' => 'string',
                                        'enum' => $ids,
                                        'description' => 'El id EXACTO del dato de la lista (nunca el nombre del campo ni su descripción).',
                                    ],
                                    'contenido' => [
                                        'type' => 'string',
                                        'description' => 'El valor tal como aparece en el documento, siempre como string (incluso para una estructura compleja, como JSON).',
                                    ],
                                ],
                                'required' => ['id', 'contenido'],
                            ],
                        ],
                    ],
                    'required' => ['revelados'],
                ],
            ],
        ];
    }

    /**
     * Completa cada hallazgo del modelo (solo id+contenido) con la metadata
     * real del catálogo (forma, campo, tipo_campo, tipo_dato, subcampo,
     * acumulable) — nunca se confía en que el modelo la reproduzca bien.
     *
     * Descarta (sin lanzar) un id fuera de rango o repetido: un id repetido
     * solo puede significar que el modelo no siguió la instrucción de
     * reportar cada dato una sola vez — contarlo dos veces arruinaría un
     * campo acumulable (ej. sumaría la retención del W-2 dos veces), así
     * que ante la duda se descarta el duplicado en vez de arriesgar el
     * valor guardado.
     *
     * @param  array<int, array<string, mixed>>  $encontrados
     * @param  array<int, array<string, mixed>>  $revela  ya reindexado 0..n-1 por extraer()
     * @return array<int, array<string, mixed>>
     */
    private function combinarConMetadata(array $encontrados, array $revela): array
    {
        $resultado = [];
        $idsUsados = [];

        foreach ($encontrados as $item) {
            $idCrudo = $item['id'] ?? null;

            if (! is_numeric($idCrudo)) {
                continue;
            }

            $id = (int) $idCrudo;

            if (! array_key_exists($id, $revela) || isset($idsUsados[$id])) {
                continue;
            }

            $idsUsados[$id] = true;
            $meta = $revela[$id];

            $datos = [
                'forma' => $meta['forma'],
                'campo' => $meta['campo'],
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
