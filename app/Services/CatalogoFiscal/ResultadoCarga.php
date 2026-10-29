<?php

namespace App\Services\CatalogoFiscal;

/**
 * Lo que hizo (o haría, en --dry-run) una carga del catálogo fiscal.
 */
class ResultadoCarga
{
    /** @var array<string, int> */
    public array $creados = [];

    /** @var array<string, int> */
    public array $actualizados = [];

    /** @var array<string, int> */
    public array $sinCambios = [];

    /** @var list<string> */
    public array $saltados = [];

    /** @var list<string> */
    public array $avisos = [];

    public function contar(string $estado, string $pieza): void
    {
        $this->{$estado}[$pieza] = ($this->{$estado}[$pieza] ?? 0) + 1;
    }

    /**
     * @return list<array{0: string, 1: int, 2: int, 3: int}>
     */
    public function filas(): array
    {
        $piezas = array_unique([...array_keys($this->creados), ...array_keys($this->actualizados), ...array_keys($this->sinCambios)]);
        sort($piezas);

        return array_map(fn (string $p) => [
            $p,
            $this->creados[$p] ?? 0,
            $this->actualizados[$p] ?? 0,
            $this->sinCambios[$p] ?? 0,
        ], $piezas);
    }
}
