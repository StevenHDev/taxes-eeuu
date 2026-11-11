<?php

namespace App\Services\Calculo\Nucleo;

use App\Support\ParametrosFiscales;
use InvalidArgumentException;
use RuntimeException;

/**
 * Ayudantes comunes de las calculadoras (F6 6.4z): leer y sumar líneas de la
 * declaración, leer datos de los documentos, copiar una línea, saber qué
 * documentos hay y armar los motivos de "falta". Sin estado: cada método
 * recibe el formulario y la línea de destino.
 *
 * Hay dos formas de leer líneas, con reglas distintas a propósito:
 * - `leerLineas()` suma varias fuentes, cada una marcada como requerida o no;
 *   `falta_dato` manda sobre `no_soportada` y cuenta cuántas líneas entraron.
 * - `leerUna()` / `leerVarias()` / `sumar()` cortan en la primera fuente que
 *   no sirve y heredan su motivo.
 */
final class Apoyo
{
    /** Cómo se nombra cada formulario del catálogo en los textos. */
    private const NOMBRES = [
        '1040' => 'Form 1040',
        'sch-1' => 'Schedule 1',
        'sch-1a' => 'Schedule 1-A',
        'sch-2' => 'Schedule 2',
        'sch-3' => 'Schedule 3',
        'sch-c' => 'Schedule C',
        'sch-se' => 'Schedule SE',
        'sch-8812' => 'Schedule 8812',
        'sch-eic' => 'Schedule EIC',
        'f2441' => 'Form 2441',
        'f8962' => 'Form 8962',
        'f8995' => 'Form 8995',
        'f8959' => 'Form 8959',
    ];

    public static function nombre(string $formulario): string
    {
        return self::NOMBRES[$formulario] ?? $formulario;
    }

    public static function nombreLinea(string $formulario, string $linea, bool $conArticulo = false): string
    {
        $nombre = self::nombre($formulario);

        return $conArticulo
            ? __('la línea :linea del :formulario', ['linea' => $linea, 'formulario' => $nombre])
            : __('Línea :linea del :formulario', ['linea' => $linea, 'formulario' => $nombre]);
    }

    /**
     * Lee líneas de la declaración y las suma. Una línea que falta y es
     * requerida da `falta_dato` (nunca se supone cero); una `falta_dato` o
     * `no_soportada` de origen se hereda; una `no_aplica` cuenta cero. Si hay
     * varios problemas, `falta_dato` manda sobre `no_soportada`.
     *
     * @param  list<array{0: string, 1: string, 2: bool}>  $fuentes  [formulario, línea, requerida]
     * @return array{0: Dinero, 1: Traza, 2: list<string>, 3: int}|Linea total, traza, datos sin validar y cuántas líneas contaron
     */
    public static function leerLineas(Declaracion $d, string $formularioDestino, string $destino, array $fuentes): array|Linea
    {
        $total = Dinero::deCentavos(0);
        $traza = Traza::vacia();
        $sinValidar = [];
        $aplicadas = 0;
        $falta = null;
        $noSoportada = null;

        foreach ($fuentes as [$formulario, $linea, $requerida]) {
            $nombre = self::nombre($formulario);
            $origen = $d->linea($formulario, $linea);

            if ($origen === null) {
                if ($requerida) {
                    $falta ??= __('Falta la línea :linea del :formulario.', ['linea' => $linea, 'formulario' => $nombre]);
                }

                continue;
            }

            if ($origen->estado === EstadoLinea::FaltaDato) {
                $falta ??= __('No se puede calcular: depende de la línea :linea del :formulario, que tiene datos pendientes.', ['linea' => $linea, 'formulario' => $nombre]);

                continue;
            }

            if ($origen->estado === EstadoLinea::NoSoportada) {
                $noSoportada ??= __('No se puede calcular: depende de la línea :linea del :formulario, que el piloto no soporta.', ['linea' => $linea, 'formulario' => $nombre]);

                continue;
            }

            if ($origen->estado === EstadoLinea::NoAplica || ! $origen->valor instanceof Dinero) {
                continue;
            }

            $aplicadas++;
            $total = $total->mas($origen->valor);
            $traza = $traza->conEntrada(__('Línea :linea del :formulario', ['linea' => $linea, 'formulario' => $nombre]), $origen->valor, OrigenTraza::Linea, $formulario.':'.$linea);
            $sinValidar = [...$sinValidar, ...$origen->datosSinValidar];
        }

        if ($falta !== null) {
            return Linea::faltaDato($formularioDestino, $destino, $falta, $traza);
        }

        if ($noSoportada !== null) {
            return Linea::noSoportada($formularioDestino, $destino, $noSoportada, $traza);
        }

        return [$total, $traza, array_values(array_unique($sinValidar)), $aplicadas];
    }

    /**
     * Copia una línea de otro formulario (o del mismo) sin cambiarla. Si la de
     * origen es "no aplica", la de destino también, con el mismo motivo.
     */
    public static function copiar(Declaracion $d, string $formularioDestino, string $destino, string $formularioOrigen, string $lineaOrigen): void
    {
        $resultado = self::leerLineas($d, $formularioDestino, $destino, [[$formularioOrigen, $lineaOrigen, true]]);

        if ($resultado instanceof Linea) {
            $d->poner($resultado);

            return;
        }

        [$total, $traza, $sinValidar, $aplicadas] = $resultado;

        if ($aplicadas === 0) {
            $d->poner(Linea::noAplica($formularioDestino, $destino, $d->linea($formularioOrigen, $lineaOrigen)?->motivo));

            return;
        }

        $d->poner(Linea::calculada($formularioDestino, $destino, $total, $traza->conResultado(self::nombreLinea($formularioDestino, $destino), $total), $sinValidar));
    }

    /**
     * Suma datos de la declaración por su clave "documento:casilla". `null` si
     * no hay ninguno; `falta_dato` si alguno no trae monto.
     *
     * @param  array<string, string>  $etiquetas  clave => texto para la traza
     * @return array{0: Dinero, 1: Traza, 2: list<string>}|Linea|null
     */
    public static function leerDatos(EntradasDeclaracion $e, string $formularioDestino, string $destino, array $etiquetas): array|Linea|null
    {
        $total = Dinero::deCentavos(0);
        $traza = Traza::vacia();
        $presentes = [];

        foreach ($etiquetas as $clave => $etiqueta) {
            $dato = $e->datos[$clave] ?? null;

            if ($dato === null) {
                continue;
            }

            if (! $dato->valor instanceof Dinero) {
                return Linea::faltaDato($formularioDestino, $destino, __('Falta el monto de :casilla.', ['casilla' => $etiqueta]), $traza);
            }

            $presentes[$clave] = $dato;
            $total = $total->mas($dato->valor);
            $traza = $traza->conEntrada($etiqueta, $dato->valor, $dato->origen, $dato->referencia ?? $clave);
        }

        return $presentes === [] ? null : [$total, $traza, self::sinValidar($presentes)];
    }

    /**
     * Una línea de otro formulario: su valor, o la línea destino con falta_dato / no_soportada
     * (heredando el motivo de origen). "No aplica" cuenta como cero.
     *
     * @return array{0: Dinero, 1: list<string>}|Linea
     */
    public static function leerUna(Declaracion $d, string $formulario, string $linea, string $destinoFormulario, string $destinoLinea): array|Linea
    {
        $origen = $d->linea($formulario, $linea);

        if ($origen === null) {
            return Linea::faltaDato($destinoFormulario, $destinoLinea, __('Falta :linea.', ['linea' => self::nombreLinea($formulario, $linea, true)]));
        }

        return match ($origen->estado) {
            EstadoLinea::Calculada => $origen->valor instanceof Dinero
                ? [$origen->valor, $origen->datosSinValidar]
                : Linea::faltaDato($destinoFormulario, $destinoLinea, __(':linea no tiene un monto.', ['linea' => ucfirst(self::nombreLinea($formulario, $linea, true))])),
            EstadoLinea::NoAplica => [Dinero::deCentavos(0), []],
            EstadoLinea::FaltaDato => Linea::faltaDato($destinoFormulario, $destinoLinea, $origen->motivo ?? __('Falta :linea.', ['linea' => self::nombreLinea($formulario, $linea, true)])),
            EstadoLinea::NoSoportada => Linea::noSoportada($destinoFormulario, $destinoLinea, $origen->motivo ?? __(':linea no está soportada.', ['linea' => ucfirst(self::nombreLinea($formulario, $linea, true))])),
        };
    }

    /**
     * Lee varias líneas ya escritas; si alguna no sirve devuelve la línea destino con el motivo.
     *
     * @param  list<array{0: string, 1: string}>  $partes
     * @return array{0: list<Dinero>, 1: Traza, 2: list<string>}|Linea
     */
    public static function leerVarias(Declaracion $d, array $partes, string $formulario, string $linea): array|Linea
    {
        $valores = [];
        $traza = Traza::vacia();
        $sinValidar = [];

        foreach ($partes as [$f, $l]) {
            $lectura = self::leerUna($d, $f, $l, $formulario, $linea);

            if ($lectura instanceof Linea) {
                return $lectura;
            }

            [$valor, $pendientes] = $lectura;
            $valores[] = $valor;
            $sinValidar = [...$sinValidar, ...$pendientes];
            $traza = $traza->conEntrada(self::nombreLinea($f, $l), $valor, OrigenTraza::Linea, "{$f}:{$l}");
        }

        return [$valores, $traza, array_values(array_unique($sinValidar))];
    }

    /**
     * Suma (o copia) líneas ya escritas y la deja como calculada, o propaga lo que falta.
     *
     * @param  list<array{0: string, 1: string}>  $partes  [formulario, línea]
     */
    public static function sumar(Declaracion $d, string $formulario, string $linea, array $partes, string $titulo, bool $copiar = false): void
    {
        $lectura = self::leerVarias($d, $partes, $formulario, $linea);

        if ($lectura instanceof Linea) {
            $d->poner($lectura);

            return;
        }

        [$valores, $traza, $sinValidar] = $lectura;
        $total = array_reduce($valores, fn (Dinero $a, Dinero $b): Dinero => $a->mas($b), Dinero::deCentavos(0));

        if (! $copiar) {
            $traza = $traza->conOperacion(__('Suma de las líneas anteriores.'));
        }

        $d->poner(Linea::calculada($formulario, $linea, $total, $traza->conResultado($titulo, $total), $sinValidar));
    }

    /**
     * Códigos de documento (de la lista) que tienen algún dato en la declaración
     * (la clave de un dato es "documento:casilla").
     *
     * @param  list<string>  $documentos
     * @return list<string>
     */
    public static function documentosPresentes(EntradasDeclaracion $e, array $documentos): array
    {
        return array_values(array_filter(
            $documentos,
            fn (string $documento): bool => self::hayDatos($e, $documento),
        ));
    }

    public static function hayDatos(EntradasDeclaracion $e, string $documento): bool
    {
        foreach (array_keys($e->datos) as $clave) {
            if (str_starts_with((string) $clave, $documento.':')) {
                return true;
            }
        }

        return $e->documentos($documento) !== [];
    }

    /**
     * Referencias (no valores) de los datos que entran sin validar o con excepción.
     *
     * @param  array<array-key, DatoEntrada>  $datos
     * @return list<string>
     */
    public static function sinValidar(array $datos): array
    {
        $referencias = [];

        foreach ($datos as $clave => $dato) {
            if ($dato->usaSinValidar()) {
                $referencias[] = $dato->referencia ?? (string) $clave;
            }
        }

        return $referencias;
    }

    /**
     * Un parámetro de monto (dólares enteros) como `Dinero`.
     *
     * @throws RuntimeException si falta sembrarlo o está pendiente
     * @throws InvalidArgumentException si no es un monto
     */
    public static function parametroEnDolares(int $anio, string $categoria, string $clave): Dinero
    {
        $valor = ParametrosFiscales::valorRequerido($anio, $categoria, $clave);

        return Dinero::deDolares(is_int($valor) ? $valor : (string) $valor);
    }

    public static function persona(EntradasDeclaracion $e, RolPersona $rol): ?PersonaEntrada
    {
        foreach ($e->personas as $p) {
            if ($p->rol === $rol) {
                return $p;
            }
        }

        return null;
    }

    public static function personaPorId(EntradasDeclaracion $e, ?int $id): ?PersonaEntrada
    {
        foreach ($e->personas as $p) {
            if ($p->id === $id) {
                return $p;
            }
        }

        return null;
    }

    public static function motivoSinEstadoCivil(): string
    {
        return __('Falta decidir el estado civil en Revisión.');
    }

    /** El motivo cuando falta un parámetro del año; toma su clave del mensaje de `ParametrosFiscales` si viene. */
    public static function motivoSinParametro(int $taxYear, RuntimeException $excepcion): string
    {
        if (preg_match('/\[([a-z0-9_.]+)\]/', $excepcion->getMessage(), $m) === 1) {
            return __('No se puede calcular: falta el parámetro :parametro de :anio (Catálogo → Parámetros).', ['parametro' => $m[1], 'anio' => (string) $taxYear]);
        }

        return __('No se puede calcular: falta un parámetro de :anio (Catálogo → Parámetros).', ['anio' => (string) $taxYear]);
    }
}
