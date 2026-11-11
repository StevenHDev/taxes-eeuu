<?php

namespace App\Services\Calculo\Formularios;

use App\Services\Calculo\Formularios\ScheduleC\DuenoNegocio;
use App\Services\Calculo\Nucleo\Apoyo;
use App\Services\Calculo\Nucleo\Calculadora;
use App\Services\Calculo\Nucleo\Declaracion;
use App\Services\Calculo\Nucleo\Dinero;
use App\Services\Calculo\Nucleo\EntradasDeclaracion;
use App\Services\Calculo\Nucleo\EstadoLinea;
use App\Services\Calculo\Nucleo\Linea;
use App\Services\Calculo\Nucleo\OrigenTraza;
use App\Services\Calculo\Nucleo\Traza;
use App\Support\ParametrosFiscales;
use InvalidArgumentException;
use RuntimeException;

/**
 * Schedule SE (Form 1040) de 2025, Parte I, de la persona dueña del negocio del Schedule C.
 *
 * Líneas (Schedule SE de 2025, p. 1): 2 = Schedule C línea 31; 3 = 2 (1a y 1b, agrícolas, no son
 * del piloto); 4a = 3 × 92,35 % si 3 es mayor que cero, si no la 3; 4c = 4a (4b, métodos
 * opcionales, no es del piloto). "If less than $400, stop; you don't owe self-employment tax":
 * con 4c bajo el mínimo, las líneas 2 a 4c quedan escritas (el EIC las lee, hoja B) y de la 6 en
 * adelante no aplican. 6 = 4c (5a y 5b, iglesia, no son del piloto); 7 = tope del Seguro Social;
 * 8a = W-2 casillas 3 y 7 de ESA persona; 8b y 8c (Form 4137 y 8919) no son del piloto;
 * 8d = 8a + 8b + 8c; 9 = 7 − 8d (cero si da menos); 10 = la menor entre 6 y 9 × 12,4 %;
 * 11 = 6 × 2,9 %; 12 = 10 + 11 (→ Schedule 2 línea 4); 13 = 12 × 50 % (→ Schedule 1 línea 15).
 *
 * Debe correr DESPUÉS de `ScheduleC` y ANTES de `Schedule1`, `ImpuestoYSchedule2` y `Eitc`.
 */
final class ScheduleSe implements Calculadora
{
    private const FORMULARIO = 'sch-se';

    /** Las líneas que escribe, en el orden del formulario. */
    public const LINEAS = ['2', '3', '4a', '4c', '6', '7', '8a', '8b', '8c', '8d', '9', '10', '11', '12', '13'];

    /** Las que se escriben aunque 4c quede bajo el mínimo. */
    private const HASTA_4C = ['2', '3', '4a', '4c'];

    public function formulario(): string
    {
        return self::FORMULARIO;
    }

    public function calcular(EntradasDeclaracion $e, Declaracion $d): void
    {
        $negocio = DuenoNegocio::de($e);

        if ($negocio->dosDuenos) {
            $this->varias($d, self::LINEAS, fn (string $l): Linea => Linea::noSoportada(self::FORMULARIO, $l, DuenoNegocio::motivoDosDuenos()));

            return;
        }

        $k1 = Apoyo::documentosPresentes($e, ['k-1']);

        if ($k1 !== []) {
            $this->varias($d, self::LINEAS, fn (string $l): Linea => Linea::noSoportada(self::FORMULARIO, $l, __('Hay un K-1: puede traer ganancias de autoempleo de una sociedad (casilla 14, código A) que van a la línea 2 del Schedule SE. Lo calcula el preparador.')));

            return;
        }

        if (! $negocio->hayNegocio || $d->linea('sch-c', '31')?->estado === EstadoLinea::NoAplica) {
            $this->varias($d, self::LINEAS, fn (string $l): Linea => Linea::noAplica(self::FORMULARIO, $l, DuenoNegocio::motivoSinNegocio()));

            return;
        }

        Apoyo::sumar($d, self::FORMULARIO, '2', [['sch-c', '31']], Apoyo::nombreLinea(self::FORMULARIO, '2'), copiar: true);
        Apoyo::sumar($d, self::FORMULARIO, '3', [[self::FORMULARIO, '2']], Apoyo::nombreLinea(self::FORMULARIO, '3'), copiar: true);

        $linea3 = $d->linea(self::FORMULARIO, '3');

        if ($linea3 === null || $linea3->estado !== EstadoLinea::Calculada || ! $linea3->valor instanceof Dinero) {
            $this->propagar($d, array_slice(self::LINEAS, 2), $linea3);

            return;
        }

        try {
            $p = $this->parametros($e->taxYear);
        } catch (RuntimeException|InvalidArgumentException $excepcion) {
            $motivo = $excepcion instanceof RuntimeException
                ? Apoyo::motivoSinParametro($e->taxYear, $excepcion)
                : __('No se puede calcular: un parámetro de :anio del Schedule SE no es un número válido (Catálogo → Parámetros).', ['anio' => (string) $e->taxYear]);
            $this->varias($d, array_slice(self::LINEAS, 2), fn (string $l): Linea => Linea::faltaDato(self::FORMULARIO, $l, $motivo));

            return;
        }

        $sinValidar = $linea3->datosSinValidar;
        $l3 = $linea3->valor;
        $cero = Dinero::deCentavos(0);

        // 4a: "If line 3 is more than zero, multiply line 3 by 92.35% (0.9235). Otherwise, enter amount from line 3".
        $l4a = $l3->centavos() > 0 ? $l3->por($p['factor']) : $l3;
        $traza4a = Traza::vacia()
            ->conEntrada(Apoyo::nombreLinea(self::FORMULARIO, '3'), $l3, OrigenTraza::Linea, 'sch-se:3')
            ->conParametro(__('Factor sobre la ganancia neta de autoempleo'), $p['factor'], 'self_employment_tax.factor_ingreso_neto')
            ->conOperacion($l3->centavos() > 0 ? __('La línea 3 es mayor que cero: por el factor.') : __('La línea 3 no es mayor que cero: se copia.'), $l4a);
        $d->poner(Linea::calculada(self::FORMULARIO, '4a', $l4a, $traza4a->conResultado(Apoyo::nombreLinea(self::FORMULARIO, '4a'), $l4a), $sinValidar));

        $traza4c = Traza::vacia()
            ->conEntrada(Apoyo::nombreLinea(self::FORMULARIO, '4a'), $l4a, OrigenTraza::Linea, 'sch-se:4a')
            ->conOperacion(__('La línea 4b (métodos opcionales) no es del piloto: 4c = 4a.'))
            ->conParametro(__('Mínimo de la línea 4c para deber el impuesto de autoempleo'), $p['minimo'], 'self_employment_tax.minimo_ingreso_neto');
        $d->poner(Linea::calculada(self::FORMULARIO, '4c', $l4a, $traza4c->conResultado(Apoyo::nombreLinea(self::FORMULARIO, '4c'), $l4a), $sinValidar));

        if ($l4a->centavos() < $p['minimo']->centavos()) {
            $motivo = __('La línea 4c es menor que USD :minimo: no se debe impuesto de autoempleo y el Schedule SE no se presenta (Schedule SE, línea 4c).', ['minimo' => $p['minimo']->texto()]);
            $this->varias($d, array_slice(self::LINEAS, count(self::HASTA_4C)), fn (string $l): Linea => Linea::noAplica(self::FORMULARIO, $l, $motivo));

            return;
        }

        $l6 = $l4a;
        $d->poner(Linea::calculada(self::FORMULARIO, '6', $l6, Traza::vacia()
            ->conEntrada(Apoyo::nombreLinea(self::FORMULARIO, '4c'), $l6, OrigenTraza::Linea, 'sch-se:4c')
            ->conOperacion(__('Las líneas 5a y 5b (ingresos de empleado de una iglesia) no son del piloto: 6 = 4c.'))
            ->conResultado(Apoyo::nombreLinea(self::FORMULARIO, '6'), $l6), $sinValidar));

        $d->poner(Linea::calculada(self::FORMULARIO, '7', $p['tope'], Traza::vacia()
            ->conParametro(__('Tope de salarios y ganancias de autoempleo sujetos al Seguro Social'), $p['tope'], 'self_employment_tax.tope_salario_social_security')
            ->conResultado(Apoyo::nombreLinea(self::FORMULARIO, '7'), $p['tope'])));

        $d->poner($this->linea8a($e, $negocio->personaId));
        $d->poner(Linea::noAplica(self::FORMULARIO, '8b', __('Propinas no reportadas (Form 4137): no son del piloto.')));
        $d->poner(Linea::noAplica(self::FORMULARIO, '8c', __('Salarios del Form 8919: no son del piloto.')));
        $this->linea8d($d);

        // 9: "Subtract line 8d from line 7. If zero or less, enter -0-" (8d que no aplica cuenta cero).
        $lectura8d = Apoyo::leerUna($d, self::FORMULARIO, '8d', self::FORMULARIO, '9');

        if ($lectura8d instanceof Linea) {
            $d->poner($lectura8d);
            $this->propagar($d, ['10', '12', '13'], $lectura8d);
            $this->linea11($d, $l6, $p, $sinValidar);

            return;
        }

        [$l8d, $sinValidar8d] = $lectura8d;
        $sinValidar = array_values(array_unique([...$sinValidar, ...$sinValidar8d]));
        $l9 = $p['tope']->menos($l8d)->max($cero);
        $d->poner(Linea::calculada(self::FORMULARIO, '9', $l9, Traza::vacia()
            ->conEntrada(Apoyo::nombreLinea(self::FORMULARIO, '7'), $p['tope'], OrigenTraza::Linea, 'sch-se:7')
            ->conEntrada(Apoyo::nombreLinea(self::FORMULARIO, '8d'), $l8d, OrigenTraza::Linea, 'sch-se:8d')
            ->conOperacion(__('Línea 7 menos línea 8d; si da cero o menos, cero.'), $l9)
            ->conResultado(Apoyo::nombreLinea(self::FORMULARIO, '9'), $l9), $sinValidar8d));

        // 10: "Multiply the smaller of line 6 or line 9 by 12.4% (0.124)".
        $base10 = $l6->min($l9);
        $l10 = $base10->por($p['tasa_ss']);
        $d->poner(Linea::calculada(self::FORMULARIO, '10', $l10, Traza::vacia()
            ->conEntrada(Apoyo::nombreLinea(self::FORMULARIO, '6'), $l6, OrigenTraza::Linea, 'sch-se:6')
            ->conEntrada(Apoyo::nombreLinea(self::FORMULARIO, '9'), $l9, OrigenTraza::Linea, 'sch-se:9')
            ->conParametro(__('Tasa del Seguro Social del impuesto de autoempleo'), $p['tasa_ss'], 'self_employment_tax.tasa_seguro_social')
            ->conOperacion(__('La menor entre las líneas 6 y 9, por la tasa.'), $l10)
            ->conResultado(Apoyo::nombreLinea(self::FORMULARIO, '10'), $l10), $sinValidar));

        $this->linea11($d, $l6, $p, $sinValidar);

        Apoyo::sumar($d, self::FORMULARIO, '12', [[self::FORMULARIO, '10'], [self::FORMULARIO, '11']], Apoyo::nombreLinea(self::FORMULARIO, '12'));

        // 13: "Multiply line 12 by 50% (0.50)".
        $linea12 = $d->linea(self::FORMULARIO, '12');
        $l12 = $linea12?->valor instanceof Dinero ? $linea12->valor : $cero;
        $l13 = $l12->por($p['mitad']);
        $d->poner(Linea::calculada(self::FORMULARIO, '13', $l13, Traza::vacia()
            ->conEntrada(Apoyo::nombreLinea(self::FORMULARIO, '12'), $l12, OrigenTraza::Linea, 'sch-se:12')
            ->conParametro(__('Parte deducible del impuesto de autoempleo'), $p['mitad'], 'self_employment_tax.factor_deduccion_mitad')
            ->conOperacion(__('Línea 12 por el factor.'), $l13)
            ->conResultado(Apoyo::nombreLinea(self::FORMULARIO, '13'), $l13), $linea12->datosSinValidar ?? []));
    }

    /**
     * 8a: "Total social security wages and tips (total of boxes 3 and 7 on Form(s) W-2)" de la
     * persona del Schedule SE ("Name of person with self-employment income", p. 1). Los W-2 del
     * otro cónyuge no cuentan: cada cónyuge tiene su propio Schedule SE (instrucciones, p. 2).
     */
    private function linea8a(EntradasDeclaracion $e, ?int $duenoId): Linea
    {
        $w2s = array_values(array_filter(
            $e->documentos('w2'),
            fn ($w2): bool => $w2->casilla('3') !== null || $w2->casilla('7') !== null,
        ));

        if ($w2s === []) {
            return isset($e->datos['w2:3']) || isset($e->datos['w2:7'])
                ? Linea::faltaDato(self::FORMULARIO, '8a', __('Falta el detalle de cada W-2 (casillas 3 y 7) para saber cuáles son de la persona del negocio.'))
                : Linea::noAplica(self::FORMULARIO, '8a', __('No hay W-2 con salarios del Seguro Social.'));
        }

        foreach ($w2s as $w2) {
            if ($w2->personaId === null) {
                return Linea::faltaDato(self::FORMULARIO, '8a', __('Falta saber de quién es el W-2 #:item.', ['item' => (string) $w2->itemId]));
            }
        }

        if ($duenoId === null) {
            return Linea::faltaDato(self::FORMULARIO, '8a', __('Falta saber de quién es el negocio (los 1099 y los registros) para tomar solo sus W-2.'));
        }

        $total = Dinero::deCentavos(0);
        $traza = Traza::vacia();
        $sinValidar = [];
        $contados = 0;

        foreach ($w2s as $w2) {
            if ($w2->personaId !== $duenoId) {
                $traza = $traza->conOperacion(__('El W-2 #:item es de otra persona: no cuenta en este Schedule SE.', ['item' => (string) $w2->itemId]));

                continue;
            }

            foreach (['3' => __('W-2, casilla 3 (salarios del Seguro Social)'), '7' => __('W-2, casilla 7 (propinas del Seguro Social)')] as $casilla => $etiqueta) {
                $dato = $w2->casilla((string) $casilla);

                if ($dato === null) {
                    continue;
                }

                if (! $dato->valor instanceof Dinero) {
                    return Linea::faltaDato(self::FORMULARIO, '8a', __('Falta el monto de la casilla :casilla del W-2 #:item.', ['casilla' => (string) $casilla, 'item' => (string) $w2->itemId]));
                }

                $referencia = $w2->referencia().':'.$casilla;
                $total = $total->mas($dato->valor);
                $traza = $traza->conEntrada($etiqueta, $dato->valor, $dato->origen, $referencia);
                $contados++;

                if ($dato->usaSinValidar()) {
                    $sinValidar[] = $referencia;
                }
            }
        }

        if ($contados === 0) {
            return Linea::noAplica(self::FORMULARIO, '8a', __('La persona del negocio no tiene W-2 con salarios del Seguro Social.'), $traza);
        }

        return Linea::calculada(self::FORMULARIO, '8a', $total, $traza->conResultado(Apoyo::nombreLinea(self::FORMULARIO, '8a'), $total), $sinValidar);
    }

    /** 8d = 8a + 8b + 8c; sin salarios (8a no aplica), no aplica. */
    private function linea8d(Declaracion $d): void
    {
        $linea8a = $d->linea(self::FORMULARIO, '8a');

        if ($linea8a?->estado === EstadoLinea::NoAplica) {
            $d->poner(Linea::noAplica(self::FORMULARIO, '8d', $linea8a->motivo));

            return;
        }

        Apoyo::sumar($d, self::FORMULARIO, '8d', [[self::FORMULARIO, '8a'], [self::FORMULARIO, '8b'], [self::FORMULARIO, '8c']], Apoyo::nombreLinea(self::FORMULARIO, '8d'));
    }

    /**
     * 11: "Multiply line 6 by 2.9% (0.029)".
     *
     * @param  array{tasa_medicare: string}  $p
     * @param  list<string>  $sinValidar
     */
    private function linea11(Declaracion $d, Dinero $l6, array $p, array $sinValidar): void
    {
        $l11 = $l6->por($p['tasa_medicare']);
        $d->poner(Linea::calculada(self::FORMULARIO, '11', $l11, Traza::vacia()
            ->conEntrada(Apoyo::nombreLinea(self::FORMULARIO, '6'), $l6, OrigenTraza::Linea, 'sch-se:6')
            ->conParametro(__('Tasa de Medicare del impuesto de autoempleo'), $p['tasa_medicare'], 'self_employment_tax.tasa_medicare')
            ->conOperacion(__('Línea 6 por la tasa.'), $l11)
            ->conResultado(Apoyo::nombreLinea(self::FORMULARIO, '11'), $l11), $sinValidar));
    }

    /**
     * @return array{factor: string, minimo: Dinero, tope: Dinero, tasa_ss: string, tasa_medicare: string, mitad: string}
     *
     * @throws RuntimeException si falta un parámetro o está pendiente
     */
    private function parametros(int $anio): array
    {
        $texto = fn (string $clave): string => (string) ParametrosFiscales::valorRequerido($anio, 'self_employment_tax', $clave);

        return [
            'factor' => $texto('factor_ingreso_neto'),
            'minimo' => Apoyo::parametroEnDolares($anio, 'self_employment_tax', 'minimo_ingreso_neto'),
            'tope' => Apoyo::parametroEnDolares($anio, 'self_employment_tax', 'tope_salario_social_security'),
            'tasa_ss' => $texto('tasa_seguro_social'),
            'tasa_medicare' => $texto('tasa_medicare'),
            'mitad' => $texto('factor_deduccion_mitad'),
        ];
    }

    /**
     * @param  list<string>  $lineas
     * @param  callable(string): Linea  $linea
     */
    private function varias(Declaracion $d, array $lineas, callable $linea): void
    {
        foreach ($lineas as $l) {
            $d->poner($linea($l));
        }
    }

    /**
     * Las líneas que siguen heredan el estado y el motivo de la que no se pudo calcular.
     *
     * @param  list<string>  $lineas
     */
    private function propagar(Declaracion $d, array $lineas, ?Linea $origen): void
    {
        $motivo = $origen?->motivo ?? __('Falta la línea 31 del Schedule C.');
        $noSoportada = $origen?->estado === EstadoLinea::NoSoportada;

        $this->varias($d, $lineas, fn (string $l): Linea => $noSoportada
            ? Linea::noSoportada(self::FORMULARIO, $l, $motivo)
            : Linea::faltaDato(self::FORMULARIO, $l, $motivo));
    }
}
