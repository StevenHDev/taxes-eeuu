<?php

namespace App\Services\CatalogoFiscal;

/**
 * Qué líneas cubre al menos un caso de prueba contra el IRS (F6, 6.0 y 6.2).
 * Una línea solo se marca "validada contra el IRS" si aparece aquí. La lista
 * sale de `database/data/catalogo-fiscal/<año>/cobertura-casos.php`, que
 * escribe `calculo:cobertura` a partir de los casos; así producción no lee
 * `tests/`. Sin el archivo no hay casos y la lista es vacía.
 */
class CoberturaCasos
{
    public function __construct(private readonly ?string $base = null) {}

    /**
     * @return list<string> Claves "formulario:línea", por ejemplo "1040:11a".
     */
    public function lineas(int $anio): array
    {
        $ruta = ($this->base ?? database_path('data/catalogo-fiscal'))."/{$anio}/cobertura-casos.php";

        if (! is_file($ruta)) {
            return [];
        }

        $lineas = require $ruta;

        return is_array($lineas) ? array_values(array_map('strval', $lineas)) : [];
    }

    public function cubre(int $anio, string $formulario, string $linea): bool
    {
        return in_array("{$formulario}:{$linea}", $this->lineas($anio), true);
    }
}
