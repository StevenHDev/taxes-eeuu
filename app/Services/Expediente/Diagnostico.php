<?php

namespace App\Services\Expediente;

use App\Enums\Expediente\EstadoExpediente;

/**
 * Lo que dice DiagnosticoExpediente de un cliente y un año: el estado
 * derivado, si cumple R-02 y qué falta, y a quién le toca cada cosa. Solo
 * lleva conteos, nombres de documentos y motivos en palabras: nunca valores
 * leídos, SSN ni montos.
 */
final readonly class Diagnostico
{
    /**
     * @param  list<array{id: int, codigo: string, nombre: string, numero: int|null, persona_orden: int|null, estado: string}>  $faltan
     * @param  list<array{id: int, nombre: string, motivo: string|null}>  $noAplica
     * @param  list<string>  $frases  los motivos en palabras (ya traducidos)
     */
    public function __construct(
        public EstadoExpediente $estado,
        public bool $consentimiento,
        public bool $entrevistaConfirmada,
        public bool $r02Activa,
        public bool $r02Cumple,
        public array $faltan,
        public array $noAplica,
        public int $excepcionesQueFrenan,
        public int $informativas,
        public int $consultas,
        public int $itemsCliente,
        public int $itemsPreparador,
        public array $frases = [],
    ) {}

    public function listo(): bool
    {
        return $this->estado === EstadoExpediente::ListoParaPreparar;
    }

    /**
     * @return list<string>
     */
    public function motivos(): array
    {
        return $this->frases;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'estado' => $this->estado->value,
            'estado_label' => $this->estado->label(),
            'consentimiento' => $this->consentimiento,
            'entrevista_confirmada' => $this->entrevistaConfirmada,
            'r02' => [
                'activa' => $this->r02Activa,
                'cumple' => $this->r02Cumple,
                'faltan' => $this->faltan,
                'no_aplica' => $this->noAplica,
            ],
            'excepciones_que_frenan' => $this->excepcionesQueFrenan,
            'informativas' => $this->informativas,
            'consultas' => $this->consultas,
            'items_cliente' => $this->itemsCliente,
            'items_preparador' => $this->itemsPreparador,
            'motivos' => $this->motivos(),
        ];
    }
}
