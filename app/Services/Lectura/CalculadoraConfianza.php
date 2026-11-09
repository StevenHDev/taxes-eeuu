<?php

namespace App\Services\Lectura;

use App\Enums\Lectura\Legibilidad;
use App\Enums\Lectura\MotivoConfianza;

/**
 * La confianza (0 a 1) de un dato leído (F4.3c). OpenAI no da una confianza
 * calibrada, así que la calcula la plataforma con lo que sí puede comprobar:
 *
 * | Caso                                                       | Confianza |
 * | ---------------------------------------------------------- | --------- |
 * | Sin valor, o el modelo dice que no lo ve                   | 0         |
 * | El valor no tiene el formato de su `tipo_dato`             | 0,60      |
 * | El valor aparece en la capa de texto del PDF               | 0,99      |
 * | El modelo lo leyó `claro` y tiene el formato correcto      | 0,90      |
 * | El modelo lo leyó `dudoso`                                 | 0,60      |
 * | Salió de una celda de una hoja de cálculo (sin IA)         | 0,99      |
 *
 * El orden importa: el formato se revisa antes que la capa de texto, así que
 * un valor mal formateado nunca pasa de 0,60 aunque el texto lo contenga (un
 * monto escrito "12.3.4" no es un monto). Con el umbral de 0,90 (R-03), todo
 * lo dudoso y lo mal formateado va a revisión del preparador.
 *
 * Los formatos son los de documento madre 6.2 / plan F4 (4.3): `monto`
 * decimal con hasta 2 decimales, `ssn` de 9 dígitos o truncado, `ein`
 * NN-NNNNNNN, `fecha` AAAA-MM-DD válida, etc. Para lo que no tiene un formato
 * fijo (nombres, textos, códigos) basta con que no esté vacío.
 */
class CalculadoraConfianza
{
    public const SIN_LECTURA = 0.0;

    public const REVISION = 0.60;

    public const CLARO = 0.90;

    public const EN_TEXTO_DEL_PDF = 0.99;

    /**
     * El número de cada motivo.
     *
     * @var array<string, float>
     */
    private const CONFIANZA = [
        'en_texto_del_pdf' => self::EN_TEXTO_DEL_PDF,
        'claro' => self::CLARO,
        'dudoso' => self::REVISION,
        'formato_incorrecto' => self::REVISION,
        'no_se_ve' => self::SIN_LECTURA,
        'sin_valor' => self::SIN_LECTURA,
        'hoja_calculo' => self::EN_TEXTO_DEL_PDF,
    ];

    /**
     * @param  array{tipo_dato?: string|null}  $casilla  la casilla del catálogo
     * @param  bool  $estaEnTextoDelPdf  el valor se encontró en la capa de texto del PDF (UbicadorEnPdf)
     */
    public function calcular(array $casilla, ?string $valor, Legibilidad $legibilidad, bool $estaEnTextoDelPdf): float
    {
        return $this->evaluar($casilla, $valor, $legibilidad, $estaEnTextoDelPdf)['confianza'];
    }

    /**
     * La confianza y el motivo, que es lo que se guarda con el dato y se le
     * muestra al preparador.
     *
     * @param  array{tipo_dato?: string|null}  $casilla  la casilla del catálogo
     * @param  bool  $estaEnTextoDelPdf  el valor se encontró en la capa de texto del PDF (UbicadorEnPdf)
     * @return array{confianza: float, motivo: MotivoConfianza}
     */
    public function evaluar(array $casilla, ?string $valor, Legibilidad $legibilidad, bool $estaEnTextoDelPdf): array
    {
        $valor = $valor === null ? '' : trim($valor);

        $motivo = match (true) {
            $valor === '' => MotivoConfianza::SinValor,
            $legibilidad === Legibilidad::NoSeVe => MotivoConfianza::NoSeVe,
            ! $this->formatoValido($casilla['tipo_dato'] ?? null, $valor) => MotivoConfianza::FormatoIncorrecto,
            $estaEnTextoDelPdf => MotivoConfianza::EnTextoDelPdf,
            $legibilidad === Legibilidad::Claro => MotivoConfianza::Claro,
            default => MotivoConfianza::Dudoso,
        };

        return ['confianza' => self::CONFIANZA[$motivo->value], 'motivo' => $motivo];
    }

    /**
     * La regla explicada, de la más segura a la menos: una fila por motivo, con
     * el número y el texto para una persona. Es lo que lee la pantalla del
     * visor y lo que debe decir la regla R-03 del catálogo (hay una prueba que
     * lo verifica), así que cambiar un número aquí cambia la explicación.
     *
     * @return list<array{motivo: string, confianza: float, titulo: string, explicacion: string}>
     */
    public static function tabla(): array
    {
        return array_map(fn (MotivoConfianza $motivo) => [
            'motivo' => $motivo->value,
            'confianza' => self::CONFIANZA[$motivo->value],
            'titulo' => $motivo->titulo(),
            'explicacion' => $motivo->explicacion(),
        ], MotivoConfianza::cases());
    }

    /**
     * Si el valor tiene la forma que corresponde a su tipo de dato.
     */
    public function formatoValido(?string $tipoDato, string $valor): bool
    {
        $valor = trim($valor);

        return match ($tipoDato) {
            'monto' => (bool) preg_match('/^-?\d+(\.\d{1,2})?$/', $valor),
            'numero' => (bool) preg_match('/^\d+(\.\d+)?$/', $valor),
            'ssn' => $this->esSsn($valor),
            'ein' => $this->esEin($valor),
            'tin' => $this->esSsn($valor) || $this->esEin($valor) || $this->esEinTruncado($valor),
            'anio' => (bool) preg_match('/^\d{4}$/', $valor) && (int) $valor >= 2000 && (int) $valor <= (int) now()->year + 1,
            'fecha' => $this->esFecha($valor),
            'si_no' => in_array($valor, ['si', 'no'], true),
            default => $valor !== '',
        };
    }

    /**
     * Nueve dígitos (con o sin guiones) o truncado como lo imprimen las copias
     * del cliente: XXX-XX-1234 o ***-**-1234.
     */
    private function esSsn(string $valor): bool
    {
        return (bool) preg_match('/^\d{3}-?\d{2}-?\d{4}$/', $valor)
            || (bool) preg_match('/^[X*]{3}-?[X*]{2}-?\d{4}$/i', $valor);
    }

    private function esEin(string $valor): bool
    {
        return (bool) preg_match('/^\d{2}-\d{7}$/', $valor);
    }

    private function esEinTruncado(string $valor): bool
    {
        return (bool) preg_match('/^[X*]{2}-?[X*]{3}\d{4}$/i', $valor);
    }

    private function esFecha(string $valor): bool
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valor, $partes)) {
            return false;
        }

        return checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1]);
    }
}
