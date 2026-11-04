<?php

namespace App\Services\Lectura;

use App\Enums\Lectura\MotivoConfianza;
use App\Models\Lectura\Excepcion;
use App\Services\CatalogoFiscal\CatalogoFiscal;

/**
 * Dice en palabras por qué saltó una excepción en este caso (no qué revisa la
 * regla: eso ya lo dice la descripción del catálogo) y, en las que más
 * trabajo dan, qué hacer. Sale del `detalle` que dejó la regla, que nunca
 * lleva montos, SSN ni nombres: la frase usa casillas, años, códigos de
 * documento y cantidades. Es el único lugar donde se arman estos textos;
 * la cola, el visor y el diálogo de resolver lo usan.
 */
class ExplicadorExcepcion
{
    public function __construct(private readonly CatalogoFiscal $catalogo) {}

    /**
     * @return array{caso: string, que_hacer: string|null}
     */
    public function explicar(Excepcion $excepcion): array
    {
        $detalle = is_array($excepcion->detalle) ? $excepcion->detalle : [];
        $anio = $excepcion->entrevista->tax_year;

        return match ($excepcion->regla_codigo) {
            'R-01' => [
                'caso' => __('Un número del documento (casilla :casilla) termina en cuatro dígitos que no coinciden con los de ninguna persona de la declaración.', ['casilla' => $this->texto($detalle['casilla'] ?? '?')]),
                'que_hacer' => __('Revisa que la tarjeta del Seguro Social de cada persona esté subida; si el número se leyó mal, corrígelo en el visor.'),
            ],
            'R-03' => ['caso' => $this->confianza($detalle), 'que_hacer' => null],
            'R-04' => ['caso' => $this->tope($detalle), 'que_hacer' => null],
            'R-05' => [
                'caso' => __('Los :w2 W-2 leídos de una misma persona suman más Seguro Social retenido que el máximo del año: puede haber un crédito por exceso.', ['w2' => $this->texto($detalle['w2'] ?? '')]),
                'que_hacer' => null,
            ],
            'R-06' => $this->anioOTipo($detalle, $anio),
            'R-07' => ['caso' => $this->repetido($detalle), 'que_hacer' => null],
            'R-08', 'R-09', 'R-11' => ['caso' => is_string($detalle['problema'] ?? null) ? __($detalle['problema']) : __('La regla :regla encontró algo que revisar.', ['regla' => $excepcion->regla_codigo]), 'que_hacer' => null],
            default => ['caso' => __('La regla :regla encontró algo que revisar.', ['regla' => $excepcion->regla_codigo]), 'que_hacer' => null],
        };
    }

    /**
     * @param  array<string, mixed>  $detalle
     */
    private function confianza(array $detalle): string
    {
        $casilla = $this->texto($detalle['casilla'] ?? '?');

        if (($detalle['motivo_confianza'] ?? null) === MotivoConfianza::SinValor->value) {
            return __('No se pudo leer la casilla :casilla: no salió ningún valor.', ['casilla' => $casilla]);
        }

        return __('La casilla :casilla se leyó con una confianza de :confianza %, por debajo del mínimo de :umbral %.', [
            'casilla' => $casilla,
            'confianza' => (int) round(((float) ($detalle['confianza'] ?? 0)) * 100),
            'umbral' => (int) round(((float) ($detalle['umbral'] ?? 0)) * 100),
        ]);
    }

    /**
     * @param  array<string, mixed>  $detalle
     */
    private function tope(array $detalle): string
    {
        return ($detalle['casilla'] ?? null) === '2'
            ? __('En el W-2, la casilla 2 (impuesto federal retenido) es mayor que la casilla 1 (salarios).')
            : __('En el W-2, la casilla 3 más la 7 (propinas) pasan del tope del Seguro Social del año.');
    }

    /**
     * @param  array<string, mixed>  $detalle
     * @return array{caso: string, que_hacer: string|null}
     */
    private function anioOTipo(array $detalle, int $anio): array
    {
        if (($detalle['campo'] ?? null) === 'anio') {
            return [
                'caso' => __('Este documento dice que es del :detectado, pero la declaración es del :esperado.', ['detectado' => $this->texto($detalle['detectado'] ?? '?'), 'esperado' => $this->texto($detalle['esperado'] ?? $anio)]),
                'que_hacer' => __('Pídele al cliente el documento del :esperado; si este sí es el correcto, acepta la excepción con una nota.', ['esperado' => $this->texto($detalle['esperado'] ?? $anio)]),
            ];
        }

        return [
            'caso' => __('Este archivo parece un :detectado, pero se subió como :esperado.', [
                'detectado' => $this->nombreDeDocumento((string) ($detalle['detectado'] ?? ''), $anio),
                'esperado' => $this->nombreDeDocumento((string) ($detalle['esperado'] ?? ''), $anio),
            ]),
            'que_hacer' => __('Dile al cliente que lo suba donde corresponde; si el documento sí está bien ubicado, acepta la excepción con una nota.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $detalle
     */
    private function repetido(array $detalle): string
    {
        $otras = (int) ($detalle['coincidencias'] ?? 1);

        return match (true) {
            ($detalle['de_otro_cliente'] ?? false) === true => __('Este mismo archivo ya lo subió otro cliente.'),
            $otras > 1 => __('Este mismo archivo ya está subido en otros :n documentos de esta declaración.', ['n' => $otras]),
            default => __('Este mismo archivo ya está subido en otro documento de esta declaración.'),
        };
    }

    /**
     * El nombre sencillo del documento en el idioma del preparador; si el
     * código no está en el catálogo (por ejemplo, "desconocido"), el código.
     */
    private function nombreDeDocumento(string $codigo, int $anio): string
    {
        $documento = $this->catalogo->documentos($anio)->firstWhere('codigo', $codigo);

        if ($documento === null) {
            return $codigo === 'desconocido' ? __('documento no reconocido') : $codigo;
        }

        return (string) (str_starts_with(app()->getLocale(), 'en')
            ? ($documento['nombre_oficial'] ?? $documento['nombre_sencillo_en'] ?? $codigo)
            : ($documento['nombre_oficial'] ?? $documento['nombre_sencillo_es'] ?? $codigo));
    }

    private function texto(mixed $valor): string
    {
        return is_scalar($valor) ? (string) $valor : '?';
    }
}
