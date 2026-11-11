<?php

namespace App\Services\Calculo\Formularios\Creditos;

use App\Services\Calculo\Nucleo\EstadoLinea;
use App\Services\Calculo\Nucleo\Linea;
use App\Services\Calculo\Nucleo\Traza;
use Exception;

/**
 * Corte del cálculo de un crédito (8812, 2441, Schedule 3): la línea no se puede calcular y se sabe por qué. No es
 * un error del sistema: quien lo atrapa escribe la línea con el estado y el motivo (falta_dato, no_soportada o
 * no_aplica). No hereda de RuntimeException para no confundirse con un parámetro que falta.
 */
final class Corte extends Exception
{
    private function __construct(public readonly EstadoLinea $estado, string $motivo, public readonly ?Traza $traza = null)
    {
        parent::__construct($motivo);
    }

    public static function falta(string $motivo): self
    {
        return new self(EstadoLinea::FaltaDato, $motivo);
    }

    public static function noSoportada(string $motivo): self
    {
        return new self(EstadoLinea::NoSoportada, $motivo);
    }

    public static function noAplica(string $motivo): self
    {
        return new self(EstadoLinea::NoAplica, $motivo);
    }

    /** El corte que trae una línea que `Apoyo` devolvió en vez de un valor (falta_dato o no_soportada). */
    public static function deLinea(Linea $linea): self
    {
        return new self($linea->estado, $linea->motivo ?? '', $linea->traza);
    }

    /** La línea de destino con el estado y el motivo del corte. */
    public function linea(string $formulario, string $linea): Linea
    {
        return match ($this->estado) {
            EstadoLinea::NoSoportada => Linea::noSoportada($formulario, $linea, $this->getMessage(), $this->traza),
            EstadoLinea::NoAplica => Linea::noAplica($formulario, $linea, $this->getMessage(), $this->traza),
            default => Linea::faltaDato($formulario, $linea, $this->getMessage(), $this->traza),
        };
    }
}
