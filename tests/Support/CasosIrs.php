<?php

namespace Tests\Support;

use InvalidArgumentException;

/**
 * Lee los casos del IRS (tests/Fixtures/irs/casos/<año>/*.php) y las fuentes fijadas
 * (tests/Fixtures/irs/<año>/fuentes.json). Quien escribe los casos no mira ninguna
 * calculadora: ver tests/Fixtures/irs/casos/README.md.
 */
final class CasosIrs
{
    public static function raiz(): string
    {
        return dirname(__DIR__).'/Fixtures/irs';
    }

    /**
     * Casos de un año, indexados por código.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function casos(int $anio): array
    {
        $casos = [];

        foreach (glob(self::raiz()."/casos/{$anio}/*.php") ?: [] as $archivo) {
            $caso = require $archivo;

            if (! is_array($caso) || ! isset($caso['codigo'])) {
                throw new InvalidArgumentException("El caso {$archivo} no devuelve un arreglo con 'codigo'.");
            }

            if (isset($casos[$caso['codigo']])) {
                throw new InvalidArgumentException("El código {$caso['codigo']} está repetido.");
            }

            $casos[$caso['codigo']] = $caso;
        }

        ksort($casos);

        return $casos;
    }

    /**
     * Líneas ("1040:16") cubiertas por al menos un caso del año, ordenadas.
     *
     * @return list<string>
     */
    public static function cubiertas(int $anio): array
    {
        return array_keys(self::cubrenPorLinea($anio));
    }

    /**
     * Para cada línea cubierta, los códigos de los casos que la cubren (lo usa 6.0 para el botón "validada").
     *
     * @return array<string, list<string>>
     */
    public static function cubrenPorLinea(int $anio): array
    {
        $mapa = [];

        foreach (self::casos($anio) as $codigo => $caso) {
            foreach ($caso['cubre'] ?? [] as $linea) {
                $mapa[$linea][] = $codigo;
            }
        }

        ksort($mapa);

        return $mapa;
    }

    /**
     * Fuentes fijadas del año (fuentes.json).
     *
     * @return array<string, mixed>
     */
    public static function fuentes(int $anio): array
    {
        $ruta = self::raiz()."/{$anio}/fuentes.json";

        if (! is_file($ruta)) {
            throw new InvalidArgumentException("No existe {$ruta}.");
        }

        return json_decode((string) file_get_contents($ruta), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Filas de un CSV de tablas del IRS (primera fila = encabezado), como arreglos asociativos de texto.
     *
     * @return list<array<string, string>>
     */
    public static function filasCsv(int $anio, string $archivo): array
    {
        $ruta = self::raiz()."/{$anio}/{$archivo}";
        $gestor = fopen($ruta, 'r');
        $encabezado = fgetcsv($gestor, null, ',', '"', '');
        $filas = [];

        while (($fila = fgetcsv($gestor, null, ',', '"', '')) !== false) {
            $filas[] = array_combine($encabezado, $fila);
        }

        fclose($gestor);

        return $filas;
    }
}
