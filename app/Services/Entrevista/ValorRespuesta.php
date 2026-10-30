<?php

namespace App\Services\Entrevista;

use App\Enums\CatalogoFiscal\TipoRespuesta;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Forma de una respuesta según el `tipo_respuesta` de la pregunta (F2.2):
 *
 *   si_no             "si" | "no"
 *   usted_conyuge_no  ["usted"] | ["conyuge"] | ["usted","conyuge"] | "no"
 *   opciones          el `valor` de una opción
 *   casillas          lista de valores de las opciones
 *   dato              texto
 *   fecha             fecha ISO (Y-m-d)
 *   numero            número (entero si no tiene decimales), 0 o más
 *
 * Es la única regla de qué se guarda; el portal, el agente y la migración
 * de 2.10 pasan por aquí.
 */
final class ValorRespuesta
{
    private const LARGO_MAXIMO_DATO = 500;

    /**
     * @param  string|int|float|list<string>  $valor
     */
    private function __construct(public readonly string|int|float|array $valor) {}

    /**
     * @param  array<string, mixed>  $pregunta  pregunta del lector CatalogoFiscal
     *
     * @throws RespuestaRechazada
     */
    public static function desde(array $pregunta, mixed $crudo): self
    {
        $codigo = $pregunta['codigo'];
        $tipo = TipoRespuesta::from($pregunta['tipo_respuesta']);
        $opciones = array_column($pregunta['opciones'] ?? [], 'valor');
        $rechazo = fn (string $motivo) => new RespuestaRechazada($codigo, "La respuesta de {$codigo} {$motivo}.");

        return new self(match ($tipo) {
            TipoRespuesta::SiNo => match (true) {
                $crudo === true, $crudo === 'si' => 'si',
                $crudo === false, $crudo === 'no' => 'no',
                default => throw $rechazo('debe ser "si" o "no"'),
            },
            TipoRespuesta::UstedConyugeNo => self::ustedConyugeNo($crudo) ?? throw $rechazo('debe ser "no", "usted", "conyuge" o ambos'),
            TipoRespuesta::Opciones => is_string($crudo) && in_array($crudo, $opciones, true)
                ? $crudo
                : throw $rechazo('no es una de sus opciones'),
            TipoRespuesta::Casillas => is_array($crudo) && array_is_list($crudo) && array_diff($crudo, $opciones) === []
                ? array_values(array_intersect($opciones, $crudo))
                : throw $rechazo('debe ser una lista de sus opciones'),
            TipoRespuesta::Dato => is_scalar($crudo) && ($texto = trim((string) $crudo)) !== '' && mb_strlen($texto) <= self::LARGO_MAXIMO_DATO
                ? $texto
                : throw $rechazo('debe ser un texto de hasta '.self::LARGO_MAXIMO_DATO.' caracteres'),
            TipoRespuesta::Fecha => self::fecha($crudo) ?? throw $rechazo('debe ser una fecha AAAA-MM-DD'),
            TipoRespuesta::Numero => is_numeric($crudo) && $crudo >= 0
                ? $crudo + 0
                : throw $rechazo('debe ser un número de 0 o más'),
        });
    }

    /**
     * Si una respuesta calza con lo esperado por una condición (`es`) o una
     * activación (`respuesta`). Las dos pueden ser un valor o una lista
     * ("alguna de"); con `usted_conyuge_no` y `casillas` basta con que se
     * crucen (["usted","conyuge"] calza con ["conyuge"]).
     */
    public static function coincide(mixed $valor, mixed $esperado): bool
    {
        if ($valor === null) {
            return false;
        }

        $comoTexto = fn (mixed $v) => array_map('strval', is_array($v) ? $v : [$v]);

        return array_intersect($comoTexto($valor), $comoTexto($esperado)) !== [];
    }

    /**
     * @return list<string>|'no'|null
     */
    private static function ustedConyugeNo(mixed $crudo): array|string|null
    {
        if ($crudo === 'no') {
            return 'no';
        }

        $lista = is_string($crudo) ? [$crudo] : $crudo;

        if (! is_array($lista) || $lista === [] || array_diff($lista, ['usted', 'conyuge']) !== []) {
            return null;
        }

        return array_values(array_intersect(['usted', 'conyuge'], $lista));
    }

    private static function fecha(mixed $crudo): ?string
    {
        if (! is_string($crudo) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $crudo) !== 1) {
            return null;
        }

        try {
            return Carbon::createFromFormat('!Y-m-d', $crudo)->format('Y-m-d') === $crudo ? $crudo : null;
        } catch (Throwable) {
            return null;
        }
    }
}
