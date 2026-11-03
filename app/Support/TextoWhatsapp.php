<?php

namespace App\Support;

/**
 * Parte un mensaje largo en varios que caben en WhatsApp. Twilio rechaza un
 * cuerpo de más de 1600 caracteres (error 21617) y, si eso pasa, el cliente
 * se queda sin respuesta: un bloque de preguntas de la entrevista puede
 * pasarse fácil. Se corta donde menos estorba: entre párrafos, luego entre
 * renglones y, solo si un renglón es más largo que el límite, entre palabras.
 */
class TextoWhatsapp
{
    /** Por debajo del límite de Twilio (1600), con margen. */
    public const MAXIMO = 1500;

    /**
     * @return list<string> al menos uno; ninguno pasa de `$maximo` caracteres
     */
    public static function partir(string $texto, int $maximo = self::MAXIMO): array
    {
        $texto = trim($texto);

        if (mb_strlen($texto) <= $maximo) {
            return [$texto];
        }

        $partes = [];
        $actual = '';

        foreach (self::unidades($texto, $maximo) as [$unidad, $union]) {
            if ($actual === '') {
                $actual = $unidad;
            } elseif (mb_strlen($actual.$union.$unidad) <= $maximo) {
                $actual .= $union.$unidad;
            } else {
                $partes[] = $actual;
                $actual = $unidad;
            }
        }

        if ($actual !== '') {
            $partes[] = $actual;
        }

        return $partes;
    }

    /**
     * Los pedazos del texto en orden, cada uno con lo que lo une al anterior
     * (párrafo, renglón o espacio). Ninguno pasa del máximo.
     *
     * @return list<array{string, string}>
     */
    private static function unidades(string $texto, int $maximo): array
    {
        $unidades = [];

        foreach (preg_split('/\n{2,}/', $texto) ?: [] as $i => $parrafo) {
            foreach (explode("\n", trim($parrafo)) as $j => $renglon) {
                foreach (self::enTrozos(trim($renglon), $maximo) as $k => $trozo) {
                    $union = $k > 0 ? ' ' : ($j > 0 ? "\n" : ($i > 0 ? "\n\n" : ''));
                    $unidades[] = [$trozo, $union];
                }
            }
        }

        return $unidades;
    }

    /**
     * Un renglón que cabe va entero; uno más largo se corta entre palabras.
     *
     * @return list<string>
     */
    private static function enTrozos(string $renglon, int $maximo): array
    {
        if (mb_strlen($renglon) <= $maximo) {
            return [$renglon];
        }

        $trozos = [];
        $actual = '';

        foreach (preg_split('/\s+/', $renglon) ?: [] as $palabra) {
            while (mb_strlen($palabra) > $maximo) {
                if ($actual !== '') {
                    $trozos[] = $actual;
                    $actual = '';
                }

                $trozos[] = mb_substr($palabra, 0, $maximo);
                $palabra = mb_substr($palabra, $maximo);
            }

            if ($actual === '') {
                $actual = $palabra;
            } elseif (mb_strlen("{$actual} {$palabra}") <= $maximo) {
                $actual .= " {$palabra}";
            } else {
                $trozos[] = $actual;
                $actual = $palabra;
            }
        }

        if ($actual !== '') {
            $trozos[] = $actual;
        }

        return $trozos;
    }
}
