<?php

namespace App\Services\Consentimiento;

use App\Models\Consentimiento\ConsentimientoTexto;
use InvalidArgumentException;

/**
 * Carga los textos de database/data/consentimientos (F2.4). Es idempotente:
 * crea las versiones nuevas y actualiza las que nadie firmó todavía. Si una
 * versión ya tiene firmas y su texto cambió, se detiene: el texto nuevo
 * tiene que ser una versión nueva.
 */
class ConsentimientosCargador
{
    private readonly string $base;

    public function __construct(?string $base = null)
    {
        $this->base = $base ?? database_path('data/consentimientos');
    }

    /**
     * @return array{creados: list<string>, actualizados: list<string>, sin_cambios: list<string>}
     */
    public function cargar(): array
    {
        $resultado = ['creados' => [], 'actualizados' => [], 'sin_cambios' => []];

        foreach (require "{$this->base}/textos.php" as $fila) {
            $nombre = "{$fila['codigo']}-v{$fila['version']}";
            $atributos = [
                ...$fila,
                'texto_es' => $this->leer("{$nombre}.es.md") ?? throw new InvalidArgumentException("Falta {$nombre}.es.md en {$this->base}."),
                'texto_en' => $this->leer("{$nombre}.en.md"),
            ];

            $texto = ConsentimientoTexto::query()->firstOrNew(['codigo' => $fila['codigo'], 'version' => $fila['version']]);
            $texto->fill($atributos);

            if (! $texto->exists) {
                $texto->save();
                $resultado['creados'][] = $nombre;

                continue;
            }

            if (! $texto->isDirty()) {
                $resultado['sin_cambios'][] = $nombre;

                continue;
            }

            if ($texto->firmas()->exists()) {
                throw new InvalidArgumentException("{$nombre} ya tiene firmas y su texto cambió: crea la versión siguiente en lugar de editarla.");
            }

            $texto->save();
            $resultado['actualizados'][] = $nombre;
        }

        return $resultado;
    }

    private function leer(string $archivo): ?string
    {
        $ruta = "{$this->base}/{$archivo}";

        return is_file($ruta) ? trim(file_get_contents($ruta)) : null;
    }
}
