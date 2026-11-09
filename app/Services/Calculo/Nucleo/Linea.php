<?php

namespace App\Services\Calculo\Nucleo;

use InvalidArgumentException;

final readonly class Linea
{
    /** @param list<string> $datosSinValidar referencias (no valores) de los datos sin validar de los que depende */
    public function __construct(
        public string $formulario,
        public string $linea,
        public Dinero|bool|string|null $valor,
        public EstadoLinea $estado,
        public ?string $motivo,
        public Traza $traza,
        public bool $usaSinValidar = false,
        public array $datosSinValidar = [],
    ) {
        if ($estado === EstadoLinea::Calculada && $valor === null) {
            throw new InvalidArgumentException('Una línea calculada necesita valor.');
        }

        if ($estado !== EstadoLinea::Calculada && $valor !== null) {
            throw new InvalidArgumentException('Solo una línea calculada puede tener valor.');
        }
    }

    /** @param list<string> $datosSinValidar */
    public static function calculada(string $formulario, string $linea, Dinero|bool|string $valor, Traza $traza, array $datosSinValidar = []): self
    {
        return new self($formulario, $linea, $valor, EstadoLinea::Calculada, null, $traza, $datosSinValidar !== [], $datosSinValidar);
    }

    public static function faltaDato(string $formulario, string $linea, string $motivo, ?Traza $traza = null): self
    {
        return new self($formulario, $linea, null, EstadoLinea::FaltaDato, $motivo, $traza ?? Traza::vacia());
    }

    public static function noSoportada(string $formulario, string $linea, string $motivo, ?Traza $traza = null): self
    {
        return new self($formulario, $linea, null, EstadoLinea::NoSoportada, $motivo, $traza ?? Traza::vacia());
    }

    public static function noAplica(string $formulario, string $linea, ?string $motivo = null, ?Traza $traza = null): self
    {
        return new self($formulario, $linea, null, EstadoLinea::NoAplica, $motivo, $traza ?? Traza::vacia());
    }
}
