<?php

namespace App\Services\Calculo\Formularios\Eitc;

use App\Services\Calculo\Nucleo\Dinero;
use App\Services\Calculo\Nucleo\EstadoLinea;
use App\Services\Calculo\Nucleo\Traza;

/** Lo que concluyó la hoja de trabajo del EIC: un crédito o la razón de que no haya (en palabras para personas). */
final readonly class ResultadoEic
{
    /** @param list<string> $datosSinValidar referencias (no valores) de los datos sin validar de los que depende */
    private function __construct(
        public EstadoLinea $estado,
        public ?Dinero $credito,
        public ?string $motivo,
        public Traza $traza,
        public array $datosSinValidar = [],
    ) {}

    /** @param list<string> $datosSinValidar */
    public static function credito(Dinero $credito, Traza $traza, array $datosSinValidar = []): self
    {
        return new self(EstadoLinea::Calculada, $credito, null, $traza, $datosSinValidar);
    }

    /** No cumple un requisito: sin crédito (la línea 27a queda en blanco). */
    public static function noAplica(string $motivo, ?Traza $traza = null): self
    {
        return new self(EstadoLinea::NoAplica, null, $motivo, $traza ?? Traza::vacia());
    }

    public static function faltaDato(string $motivo, ?Traza $traza = null): self
    {
        return new self(EstadoLinea::FaltaDato, null, $motivo, $traza ?? Traza::vacia());
    }

    public static function noSoportada(string $motivo, ?Traza $traza = null): self
    {
        return new self(EstadoLinea::NoSoportada, null, $motivo, $traza ?? Traza::vacia());
    }
}
