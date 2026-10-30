<?php

namespace App\Services\Entrevista;

/**
 * Lo que devuelve MotorEntrevista::situacion(): la foto de una entrevista
 * según el catálogo de su año. Es lo único que el portal, el agente y la
 * vista del preparador leen para saber qué mostrar.
 *
 * - `pantallas`: de la 1 a la 8, cada una con sus preguntas visibles
 *   (una por persona en el bloque C), y cuántas faltan.
 * - `documentos` / `formularios`: lo activado, con las preguntas que lo
 *   activaron ("por qué te lo pedimos"). El 1040 va siempre. Son listas y
 *   no mapas por código: "1040" quedaría como clave numérica.
 * - `faltaEnPortal`: preguntas sensibles sin responder; los documentos se
 *   suben siempre por el portal (decisión 1 del plan de la fase 2).
 */
final class SituacionEntrevista
{
    /**
     * @param  array<int, array{numero: int, preguntas: list<array{codigo: string, persona_id: int|null, sensible: bool, respondida: bool}>, total: int, respondidas: int}>  $pantallas
     * @param  list<array{codigo: string, persona_id: int|null, activado_por: list<string>}>  $documentos  uno por persona en el bloque C
     * @param  list<array{codigo: string, activado_por: list<string>}>  $formularios
     * @param  array<string, mixed>  $respuestas  "codigo" o "codigo#persona" → valor, solo de las visibles
     */
    public function __construct(
        public readonly array $pantallas,
        public readonly array $documentos,
        public readonly array $formularios,
        public readonly array $respuestas,
    ) {}

    public function esVisible(string $codigo, ?int $personaId = null): bool
    {
        return collect($this->pantallas)
            ->flatMap(fn (array $p) => $p['preguntas'])
            ->contains(fn (array $p) => $p['codigo'] === $codigo && $p['persona_id'] === $personaId);
    }

    public function tieneDocumento(string $codigo, ?int $personaId = null): bool
    {
        return collect($this->documentos)->contains(fn (array $d) => $d['codigo'] === $codigo && $d['persona_id'] === $personaId);
    }

    public function tieneFormulario(string $codigo): bool
    {
        return in_array($codigo, array_column($this->formularios, 'codigo'), true);
    }

    /**
     * @return list<string>
     */
    public function activadoPor(string $codigo, ?int $personaId = null): array
    {
        $documento = collect($this->documentos)->first(fn (array $d) => $d['codigo'] === $codigo && $d['persona_id'] === $personaId);

        return $documento['activado_por'] ?? collect($this->formularios)->firstWhere('codigo', $codigo)['activado_por'] ?? [];
    }

    /**
     * @return list<array{codigo: string, persona_id: int|null}>
     */
    public function faltaEnPortal(): array
    {
        return collect($this->pantallas)
            ->flatMap(fn (array $p) => $p['preguntas'])
            ->filter(fn (array $p) => $p['sensible'] && ! $p['respondida'])
            ->map(fn (array $p) => ['codigo' => $p['codigo'], 'persona_id' => $p['persona_id']])
            ->values()
            ->all();
    }

    public function completa(): bool
    {
        return collect($this->pantallas)->every(fn (array $p) => $p['respondidas'] === $p['total']);
    }
}
