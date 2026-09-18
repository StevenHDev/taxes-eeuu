<?php

namespace App\Services\MetaAgente;

use App\Enums\FaseConversacion;
use App\Enums\OrigenAnalisisMetaAgente;
use App\Enums\TaxForm;
use App\Models\CampoCliente;
use App\Models\FormaCliente;
use App\Models\MetaAgenteReporte;
use App\Models\User;
use App\Models\WhatsappMensaje;
use App\Services\WhatsappAgent\OpenAiClient;
use App\Support\AgentePromptVigente;
use App\Support\TaxFieldCatalog;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Audita una conversación de WhatsApp YA ocurrida contra el prompt vigente y
 * el catálogo — nunca contra su propio juicio de "cómo debería comportarse
 * el agente", que no existe: la única fuente de verdad que tiene es lo que
 * ya está escrito en prompt_actuales/*.md (vía AgentePromptVigente) y en el
 * catálogo (vía TaxFieldCatalog). Ver discusión de diseño 2026-09-18: esto es
 * deliberado — un meta-agente sin una fuente de verdad explícita contra la
 * cual comparar no tiene manera de distinguir una alucinación propia de un
 * hallazgo real, así que su trabajo se acota a detectar DIVERGENCIAS de
 * reglas que ya existen, no a inventar criterio propio de "buena conversación".
 *
 * Nunca escribe en `campos_cliente` ni en `determinaciones_fiscales` — solo
 * lee, y solo persiste en `meta_agente_reportes`. Nunca aplica un cambio de
 * prompt por su cuenta: eso requiere que un humano lea el reporte y publique
 * una nueva versión, igual que cualquier otro cambio de prompt en este repo.
 */
class ConversacionAnalizadorService
{
    public function __construct(
        private readonly OpenAiClient $openAi,
    ) {}

    /**
     * @return MetaAgenteReporte|null null si no hay mensajes que analizar en el rango pedido
     */
    public function analizar(
        string $telefono,
        OrigenAnalisisMetaAgente $origen,
        ?User $disparadoPor = null,
        ?CarbonInterface $desde = null,
    ): ?MetaAgenteReporte {
        $mensajes = WhatsappMensaje::query()
            ->where('telefono', $telefono)
            ->when($desde, fn ($q) => $q->where('created_at', '>', $desde))
            ->orderBy('created_at')
            ->get();

        if ($mensajes->isEmpty()) {
            return null;
        }

        // ->last() nunca es null acá: ya se descartó $mensajes vacío arriba.
        $cliente = $mensajes->last()->cliente;
        $taxYear = $this->taxYearPara($cliente);

        $respuesta = $this->openAi->completarChat(
            mensajes: [
                ['role' => 'system', 'content' => $this->promptSistema()],
                ['role' => 'user', 'content' => $this->promptUsuario($mensajes, $cliente, $taxYear)],
            ],
            tools: [],
            modelo: (string) config('meta_agente.modelo'),
        );

        $hallazgos = $this->parsearHallazgos((string) ($respuesta['content'] ?? ''), $telefono);

        return MetaAgenteReporte::query()->create([
            'telefono' => $telefono,
            'cliente_id' => $cliente?->id,
            'rango_desde' => $mensajes->first()->created_at,
            'rango_hasta' => $mensajes->last()->created_at,
            'mensajes_analizados' => $mensajes->count(),
            'prompt_version' => AgentePromptVigente::version(),
            'modelo' => (string) config('meta_agente.modelo'),
            'hallazgos' => $hallazgos,
            'origen' => $origen,
            'disparado_por_usuario_id' => $disparadoPor?->id,
        ]);
    }

    private function taxYearPara(?User $cliente): int
    {
        if ($cliente === null) {
            return (int) config('tax.current_tax_year');
        }

        return (int) (FormaCliente::query()->where('user_id', $cliente->id)->max('tax_year')
            ?? config('tax.current_tax_year'));
    }

    private function promptSistema(): string
    {
        return <<<'PROMPT'
            Eres el auditor de calidad del agente conversacional de WhatsApp de GlobalTax
            Services. Tu único trabajo es comparar una conversación YA CERRADA contra las
            reglas que el agente recibió en su propio prompt (te las doy abajo, fase por
            fase) y contra el catálogo vigente de campos — nunca contra tu propio criterio
            de "cómo debería ser una buena conversación".

            Reporta SOLO lo que puedas justificar con una de estas dos fuentes:
            1. Una regla explícita del prompt que la transcripción contradice.
            2. Un dato objetivo (una pregunta sobre un campo que no existe en el catálogo
               dado, un campo que el cliente respondió pero no aparece guardado, una
               promesa del agente sobre algo que el sistema no puede cumplir).

            Nunca inventes un hallazgo basado en preferencia de estilo o en que "te
            parece" que algo pudo hacerse mejor si no puedes señalar la regla o el dato
            concreto que lo respalda. Si no encuentras nada, devuelve una lista vacía —
            no fuerces al menos un hallazgo.

            Responde ÚNICAMENTE con un objeto JSON válido, sin texto antes ni después, sin
            bloque de código markdown, con esta forma exacta:

            {"hallazgos": [{"severidad": "alta"|"media"|"baja", "categoria": "string corta en kebab-case", "resumen": "una frase", "evidencia": "cita o dato concreto de la conversación/datos que lo respalda"}]}
            PROMPT;
    }

    /**
     * @param  Collection<int, WhatsappMensaje>  $mensajes
     */
    private function promptUsuario(Collection $mensajes, ?User $cliente, int $taxYear): string
    {
        $partes = [];

        $partes[] = 'REGLAS VIGENTES DEL AGENTE (fuente de verdad — cualquier divergencia de '
            .'esto en la transcripción de abajo es un hallazgo potencial):';

        foreach (FaseConversacion::cases() as $fase) {
            $contenido = AgentePromptVigente::paraFase($fase);

            if ($contenido !== null && $contenido !== '') {
                $partes[] = "--- FASE: {$fase->label()} ---\n{$contenido}";
            }
        }

        if ($cliente !== null) {
            $partes[] = "--- CAMPOS DEL CATÁLOGO VIGENTES PARA ESTE CLIENTE (tax_year {$taxYear}) — "
                .'cualquier pregunta del agente sobre un campo que NO esté en esta lista es una '
                .'pregunta fuera de catálogo ---';
            $partes[] = $this->listadoCatalogo($cliente, $taxYear);

            $partes[] = '--- ESTADO ACTUAL DE LOS CAMPOS DE ESTE CLIENTE (valores sensibles '
                .'enmascarados — no evalúes si el VALOR es correcto, solo si lo que se '
                .'preguntó/guardó corresponde a la conversación) ---';
            $partes[] = $this->listadoCampos($cliente, $taxYear);
        } else {
            $partes[] = '--- Esta conversación no tiene todavía una cuenta de cliente asociada '
                .'(fase de verificación de cuenta) — no hay catálogo ni campos que listar. ---';
        }

        $partes[] = '--- TRANSCRIPCIÓN DE LA CONVERSACIÓN (orden cronológico) ---';
        $partes[] = $this->formatearTranscripcion($mensajes);

        return implode("\n\n", $partes);
    }

    private function listadoCatalogo(User $cliente, int $taxYear): string
    {
        $campos = collect(TaxFieldCatalog::transversales($taxYear))->pluck('campo')->all();

        $formasDeclaradas = FormaCliente::query()
            ->where('user_id', $cliente->id)
            ->where('tax_year', $taxYear)
            ->pluck('forma')
            ->map(fn (string $forma) => TaxForm::tryFrom($forma))
            ->filter();

        $lineas = ['transversal: '.implode(', ', $campos)];

        foreach ($formasDeclaradas as $forma) {
            $camposForma = collect(TaxFieldCatalog::fieldsFor($taxYear, $forma))
                ->reject(fn (array $f) => $f['unico_por_cliente'] ?? false)
                ->pluck('campo')
                ->all();
            $lineas[] = "{$forma->value}: ".implode(', ', $camposForma);
        }

        return implode("\n", $lineas);
    }

    private function listadoCampos(User $cliente, int $taxYear): string
    {
        $campos = CampoCliente::query()
            ->where('user_id', $cliente->id)
            ->where('tax_year', $taxYear)
            ->orderBy('forma')
            ->orderBy('campo')
            ->get();

        if ($campos->isEmpty()) {
            return '(sin campos guardados todavía)';
        }

        return $campos
            ->map(function (CampoCliente $c) {
                $valor = $c->maskedValue();
                $valorTexto = is_array($valor) ? (string) json_encode($valor) : (string) ($valor ?? '');

                return "{$c->forma}.{$c->campo} | estado={$c->estado->value} | ".mb_substr($valorTexto, 0, 120);
            })
            ->implode("\n");
    }

    /**
     * @param  Collection<int, WhatsappMensaje>  $mensajes
     */
    private function formatearTranscripcion(Collection $mensajes): string
    {
        // `contenido` es siempre string en la columna (a veces JSON serializado
        // como texto, nunca un array nativo — ver WhatsappMensaje) — a
        // diferencia de AgenteConversacionalService::mensajesDesdeHistorial(),
        // acá no hace falta decodificar nada, solo mostrarlo tal cual.
        return $mensajes
            ->map(fn (WhatsappMensaje $m) => "[{$m->created_at}] {$m->rol->value}: {$m->contenido}")
            ->implode("\n");
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parsearHallazgos(string $contenido, string $telefono): array
    {
        $limpio = trim($contenido);
        $limpio = preg_replace('/^```(?:json)?|```$/m', '', $limpio) ?? $limpio;

        $decodificado = json_decode(trim($limpio), true);
        $hallazgos = $decodificado['hallazgos'] ?? null;

        if (! is_array($hallazgos)) {
            Log::error('ConversacionAnalizadorService: la respuesta del meta-agente no se pudo interpretar como JSON.', [
                'telefono' => $telefono,
                'contenido' => $contenido,
            ]);

            return [[
                'severidad' => 'baja',
                'categoria' => 'error-interno',
                'resumen' => 'El meta-agente no devolvió un JSON interpretable — revisar logs.',
                'evidencia' => mb_substr($contenido, 0, 300),
            ]];
        }

        return array_values(array_filter(
            $hallazgos,
            fn ($h) => is_array($h) && isset($h['resumen']),
        ));
    }
}
