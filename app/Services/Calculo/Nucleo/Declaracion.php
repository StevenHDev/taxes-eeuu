<?php

namespace App\Services\Calculo\Nucleo;

/** Mutable solo para que las calculadoras escriban sus líneas. */
final class Declaracion
{
    /** @var array<string, array<string, Linea>> formulario => línea => Linea */
    private array $lineas = [];

    public function __construct(public readonly int $taxYear, public readonly string $versionReglas) {}

    /** Escribe la línea; si ya existía una igual, la reemplaza. */
    public function poner(Linea $l): void
    {
        $this->lineas[$l->formulario][$l->linea] = $l;
    }

    public function linea(string $formulario, string $linea): ?Linea
    {
        return $this->lineas[$formulario][$linea] ?? null;
    }

    /** @return list<string> */
    public function formularios(): array
    {
        return array_map('strval', array_keys($this->lineas));
    }

    /** @return list<Linea> */
    public function lineas(?string $formulario = null): array
    {
        if ($formulario !== null) {
            return array_values($this->lineas[$formulario] ?? []);
        }

        return array_merge(...array_map(array_values(...), array_values($this->lineas)));
    }

    /**
     * 'incompleta' si alguna línea es falta_dato (o no hay líneas: nada se
     * calculó); si no, 'parcial' si alguna es no_soportada; si no, 'completa'
     * (todas calculada o no_aplica).
     */
    public function estadoGeneral(): string
    {
        $lineas = $this->lineas();

        if ($lineas === []) {
            return 'incompleta';
        }

        $estados = array_map(fn (Linea $l): EstadoLinea => $l->estado, $lineas);

        return match (true) {
            in_array(EstadoLinea::FaltaDato, $estados, true) => 'incompleta',
            in_array(EstadoLinea::NoSoportada, $estados, true) => 'parcial',
            default => 'completa',
        };
    }
}
