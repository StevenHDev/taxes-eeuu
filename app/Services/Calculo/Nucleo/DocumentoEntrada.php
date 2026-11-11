<?php

namespace App\Services\Calculo\Nucleo;

/**
 * Un documento de la declaración con sus casillas (F6 6.4z): un W-2, un
 * recibo de cuidado, un 1095-A, un comprobante de pago… Lo usan las
 * calculadoras que necesitan cada documento por separado (por persona, por
 * proveedor, por póliza o por tipo de pago); las que solo necesitan el total
 * usan `EntradasDeclaracion::$datos`. Lo llena ArmadorEntradas (6.4i).
 */
final readonly class DocumentoEntrada
{
    /**
     * @param  string  $codigo  código del documento en el catálogo ('w2', '1095-a'…)
     * @param  int  $itemId  el ítem del checklist (referencia para la traza)
     * @param  int|null  $personaId  de quién es (`PersonaEntrada::$id`), si se sabe
     * @param  array<string, DatoEntrada>  $casillas  por casilla del catálogo
     */
    public function __construct(
        public string $codigo,
        public int $itemId,
        public ?int $personaId,
        public array $casillas,
    ) {}

    public function casilla(string $casilla): ?DatoEntrada
    {
        return $this->casillas[$casilla] ?? null;
    }

    /** El monto de una casilla, o null si no está o no es un monto. */
    public function monto(string $casilla): ?Dinero
    {
        $valor = $this->casilla($casilla)?->valor;

        return $valor instanceof Dinero ? $valor : null;
    }

    /** Referencia para la traza: código e ítem, nunca un valor. */
    public function referencia(): string
    {
        return "{$this->codigo}#{$this->itemId}";
    }
}
