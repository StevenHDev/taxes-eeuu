<?php

namespace App\Services\Expediente;

/**
 * Una respuesta de la entrevista en palabras, para el preparador ("Sí",
 * "Usted y cónyuge", el texto de la opción). La usan la revisión, la debida
 * diligencia y la hoja. Nunca se llama con una pregunta sensible: quien la use
 * la filtra antes.
 */
class TextoRespuesta
{
    /**
     * @param  array<string, mixed>  $pregunta  pregunta del lector CatalogoFiscal
     */
    public static function de(array $pregunta, mixed $valor, bool $ingles): ?string
    {
        if ($valor === null || $valor === '' || $valor === []) {
            return null;
        }

        $textoOpcion = function (mixed $v) use ($pregunta, $ingles): string {
            $opcion = collect($pregunta['opciones'] ?? [])->firstWhere('valor', $v);

            if ($opcion === null) {
                return (string) $v;
            }

            return ($ingles && ! empty($opcion['texto_en'])) ? $opcion['texto_en'] : (string) ($opcion['texto_es'] ?? $v);
        };

        return match ($pregunta['tipo_respuesta'] ?? null) {
            'si_no' => $valor === 'si' || $valor === true ? self::t('Sí', $ingles) : self::t('No', $ingles),
            'usted_conyuge_no' => self::ustedConyugeNo($valor, $ingles),
            'opciones' => $textoOpcion($valor),
            'casillas' => implode(', ', array_map($textoOpcion, (array) $valor)),
            'fecha' => substr((string) $valor, 0, 10),
            default => is_scalar($valor) ? (string) $valor : null,
        };
    }

    private static function ustedConyugeNo(mixed $valor, bool $ingles): string
    {
        $lista = (array) $valor;
        $usted = in_array('usted', $lista, true);
        $conyuge = in_array('conyuge', $lista, true);

        return match (true) {
            $usted && $conyuge => self::t('Usted y cónyuge', $ingles),
            $usted => self::t('Usted', $ingles),
            $conyuge => self::t('Cónyuge', $ingles),
            default => self::t('No', $ingles),
        };
    }

    /** El texto en el idioma pedido, sin depender del idioma de la petición. */
    private static function t(string $es, bool $ingles): string
    {
        return __($es, [], $ingles ? 'en' : 'es');
    }
}
