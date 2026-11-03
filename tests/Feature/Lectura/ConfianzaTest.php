<?php

namespace Tests\Feature\Lectura;

use App\Enums\Lectura\Legibilidad;
use App\Enums\Lectura\MotivoConfianza;
use App\Services\Lectura\CalculadoraConfianza;
use App\Services\Lectura\UbicadorEnPdf;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Tests\TestCase;

/**
 * F4.3c: la confianza de cada dato (tabla del plan, 4.3) y el recuadro de un
 * valor en un PDF con texto.
 */
class ConfianzaTest extends TestCase
{
    private function calculadora(): CalculadoraConfianza
    {
        return new CalculadoraConfianza;
    }

    private function ubicador(): UbicadorEnPdf
    {
        if ((new ExecutableFinder)->find('pdftotext') === null) {
            $this->markTestSkipped('pdftotext (poppler-utils) no está instalado.');
        }

        return new UbicadorEnPdf;
    }

    private function pdf(string $nombre = 'dos-paginas.pdf'): string
    {
        return base_path("tests/Fixtures/lectura/{$nombre}");
    }

    /**
     * @param  array{pagina: int, x: float, y: float, ancho: float, alto: float}|null  $recuadro
     */
    private function afirmarRecuadroValido(?array $recuadro): void
    {
        $this->assertNotNull($recuadro, 'No se encontró el recuadro.');

        foreach (['x', 'y', 'ancho', 'alto'] as $lado) {
            $this->assertGreaterThanOrEqual(0.0, $recuadro[$lado], $lado);
            $this->assertLessThanOrEqual(1.0, $recuadro[$lado], $lado);
        }

        $this->assertGreaterThan(0.0, $recuadro['ancho']);
        $this->assertGreaterThan(0.0, $recuadro['alto']);
        $this->assertLessThanOrEqual(1.0, $recuadro['x'] + $recuadro['ancho']);
        $this->assertLessThanOrEqual(1.0, $recuadro['y'] + $recuadro['alto']);
    }

    public function test_la_tabla_de_confianza_del_plan(): void
    {
        $monto = ['tipo_dato' => 'monto'];

        // Valor en la capa de texto del PDF → 0,99, también si el modelo dudó.
        $this->assertSame(0.99, $this->calculadora()->calcular($monto, '45120.00', Legibilidad::Claro, true));
        $this->assertSame(0.99, $this->calculadora()->calcular($monto, '45120.00', Legibilidad::Dudoso, true));

        // Claro y con el formato correcto → 0,90.
        $this->assertSame(0.90, $this->calculadora()->calcular($monto, '45120.00', Legibilidad::Claro, false));

        // Dudoso → 0,60 (va a revisión con el umbral de 0,90).
        $this->assertSame(0.60, $this->calculadora()->calcular($monto, '45120.00', Legibilidad::Dudoso, false));

        // No se ve, o sin valor → 0.
        $this->assertSame(0.0, $this->calculadora()->calcular($monto, null, Legibilidad::NoSeVe, false));
        $this->assertSame(0.0, $this->calculadora()->calcular($monto, '45120.00', Legibilidad::NoSeVe, true), 'Si dice que no lo ve, el valor no cuenta.');
        $this->assertSame(0.0, $this->calculadora()->calcular($monto, '', Legibilidad::Claro, true));
        $this->assertSame(0.0, $this->calculadora()->calcular($monto, '   ', Legibilidad::Claro, false));
        $this->assertSame(0.0, $this->calculadora()->calcular(['tipo_dato' => 'texto'], null, Legibilidad::Claro, false));
    }

    public function test_un_valor_mal_formateado_nunca_pasa_de_0_60_aunque_este_en_el_texto(): void
    {
        $this->assertSame(0.60, $this->calculadora()->calcular(['tipo_dato' => 'monto'], '12.3.4', Legibilidad::Claro, false));
        $this->assertSame(0.60, $this->calculadora()->calcular(['tipo_dato' => 'monto'], '12.3.4', Legibilidad::Claro, true), 'El formato se revisa antes que la capa de texto.');
        $this->assertSame(0.60, $this->calculadora()->calcular(['tipo_dato' => 'ssn'], '12345678', Legibilidad::Claro, false));
        $this->assertSame(0.60, $this->calculadora()->calcular(['tipo_dato' => 'ssn'], '12345678', Legibilidad::Claro, true));
    }

    /**
     * @return array<string, array{string|null, string, bool}>
     */
    public static function formatos(): array
    {
        return [
            'monto entero' => ['monto', '45120', true],
            'monto con 2 decimales' => ['monto', '45120.00', true],
            'monto con 1 decimal' => ['monto', '45120.5', true],
            'monto negativo' => ['monto', '-12.50', true],
            'monto con comas' => ['monto', '45,120.00', false],
            'monto con 3 decimales' => ['monto', '12.345', false],
            'monto con símbolo' => ['monto', '$45', false],
            'monto con dos puntos' => ['monto', '12.3.4', false],
            'monto con texto' => ['monto', 'doce', false],

            'ssn con guiones' => ['ssn', '123-45-6789', true],
            'ssn sin guiones' => ['ssn', '123456789', true],
            'ssn truncado con X' => ['ssn', 'XXX-XX-6789', true],
            'ssn truncado con asteriscos' => ['ssn', '***-**-6789', true],
            'ssn de 8 dígitos' => ['ssn', '12345678', false],
            'ssn con un grupo corto' => ['ssn', '123-45-678', false],
            'ssn con letras' => ['ssn', '12A-45-6789', false],

            'ein' => ['ein', '12-3456789', true],
            'ein sin guion' => ['ein', '123456789', false],
            'ein corto' => ['ein', '12-345678', false],

            'tin que es ssn' => ['tin', '123-45-6789', true],
            'tin que es ein' => ['tin', '12-3456789', true],
            'tin truncado como ssn' => ['tin', '***-**-6789', true],
            'tin truncado como ein' => ['tin', '**-***6789', true],
            'tin inválido' => ['tin', '6789', false],

            'año actual' => ['anio', '2025', true],
            'año muy viejo' => ['anio', '1999', false],
            'año lejano' => ['anio', '2099', false],
            'año con texto' => ['anio', '25', false],

            'fecha' => ['fecha', '2025-02-28', true],
            'fecha imposible' => ['fecha', '2025-02-30', false],
            'fecha con otro formato' => ['fecha', '28/02/2025', false],

            'sí' => ['si_no', 'si', true],
            'no' => ['si_no', 'no', true],
            'yes' => ['si_no', 'yes', false],

            'número entero' => ['numero', '12', true],
            'número con decimales' => ['numero', '33.3333', true],
            'número con texto' => ['numero', 'abc', false],

            'texto' => ['texto', 'Calle 5', true],
            'nombre' => ['nombre', 'María Gómez', true],
            'código' => ['codigo', 'DD', true],
            'sin tipo' => [null, 'lo que sea', true],
        ];
    }

    #[DataProvider('formatos')]
    public function test_cada_tipo_de_dato_tiene_su_formato(?string $tipo, string $valor, bool $esperado): void
    {
        $this->assertSame($esperado, $this->calculadora()->formatoValido($tipo, $valor), "{$tipo}: {$valor}");
        $this->assertSame($esperado ? 0.90 : 0.60, $this->calculadora()->calcular(['tipo_dato' => $tipo], $valor, Legibilidad::Claro, false));
    }

    public function test_cada_caso_dice_por_que_tiene_esa_confianza(): void
    {
        $monto = ['tipo_dato' => 'monto'];
        $evaluar = fn (?string $valor, Legibilidad $l, bool $enTexto = false, array $casilla = ['tipo_dato' => 'monto']) => $this->calculadora()->evaluar($casilla, $valor, $l, $enTexto);

        $this->assertSame(['confianza' => 0.99, 'motivo' => MotivoConfianza::EnTextoDelPdf], $evaluar('45120.00', Legibilidad::Claro, true));
        $this->assertSame(['confianza' => 0.99, 'motivo' => MotivoConfianza::EnTextoDelPdf], $evaluar('45120.00', Legibilidad::Dudoso, true));
        $this->assertSame(['confianza' => 0.90, 'motivo' => MotivoConfianza::Claro], $evaluar('45120.00', Legibilidad::Claro));
        $this->assertSame(['confianza' => 0.60, 'motivo' => MotivoConfianza::Dudoso], $evaluar('45120.00', Legibilidad::Dudoso));
        $this->assertSame(['confianza' => 0.60, 'motivo' => MotivoConfianza::FormatoIncorrecto], $evaluar('12.3.4', Legibilidad::Claro, true));
        $this->assertSame(['confianza' => 0.0, 'motivo' => MotivoConfianza::NoSeVe], $evaluar('45120.00', Legibilidad::NoSeVe, true));
        $this->assertSame(['confianza' => 0.0, 'motivo' => MotivoConfianza::SinValor], $evaluar(null, Legibilidad::Claro));
        $this->assertSame(['confianza' => 0.0, 'motivo' => MotivoConfianza::SinValor], $evaluar('  ', Legibilidad::NoSeVe));

        // El número que da calcular() es el mismo que el de evaluar().
        $this->assertSame(0.90, $this->calculadora()->calcular($monto, '45120.00', Legibilidad::Claro, false));
    }

    public function test_la_tabla_explicada_coincide_con_el_calculo_y_cubre_todos_los_motivos(): void
    {
        $tabla = collect(CalculadoraConfianza::tabla());

        $this->assertSame(array_map(fn (MotivoConfianza $m) => $m->value, MotivoConfianza::cases()), $tabla->pluck('motivo')->all(), 'Falta un motivo en la tabla.');

        $casos = [
            'en_texto_del_pdf' => ['45120.00', Legibilidad::Claro, true],
            'claro' => ['45120.00', Legibilidad::Claro, false],
            'dudoso' => ['45120.00', Legibilidad::Dudoso, false],
            'formato_incorrecto' => ['12.3.4', Legibilidad::Claro, false],
            'no_se_ve' => ['45120.00', Legibilidad::NoSeVe, false],
            'sin_valor' => [null, Legibilidad::Claro, false],
        ];

        foreach ($casos as $motivo => [$valor, $legibilidad, $enTexto]) {
            $fila = $tabla->firstWhere('motivo', $motivo);
            $evaluado = $this->calculadora()->evaluar(['tipo_dato' => 'monto'], $valor, $legibilidad, $enTexto);

            $this->assertSame($motivo, $evaluado['motivo']->value);
            $this->assertSame($evaluado['confianza'], $fila['confianza'], "{$motivo}: la tabla dice otra confianza que el cálculo.");
            $this->assertNotSame('', $fila['titulo']);
            $this->assertGreaterThan(40, mb_strlen($fila['explicacion']), "{$motivo}: falta la explicación.");
        }
    }

    public function test_la_regla_r03_del_catalogo_explica_los_mismos_numeros_que_usa_el_codigo(): void
    {
        $r03 = collect(require base_path('database/data/catalogo-fiscal/2025/reglas.php'))->firstWhere('codigo', 'R-03');
        $coma = fn (float $n) => number_format($n, 2, ',', '.');

        foreach ([CalculadoraConfianza::EN_TEXTO_DEL_PDF, CalculadoraConfianza::CLARO, CalculadoraConfianza::REVISION] as $numero) {
            $this->assertStringContainsString($coma($numero), $r03['descripcion'], "R-03 no menciona {$coma($numero)}: el texto del catálogo se desfasó del código.");
        }

        $this->assertStringContainsString('menor de 0,90', $r03['descripcion']);
        $this->assertStringContainsString('la calcula la plataforma', $r03['descripcion']);
    }

    public function test_encuentra_un_monto_y_da_un_recuadro_dentro_de_la_hoja(): void
    {
        $recuadro = $this->ubicador()->buscar($this->pdf(), '45120.00');

        $this->afirmarRecuadroValido($recuadro);
        $this->assertSame(1, $recuadro['pagina']);
        // El monto va al final del renglón, cerca del margen superior de la hoja y no ocupa más de un tercio del ancho.
        $this->assertGreaterThan(0.35, $recuadro['x']);
        $this->assertLessThan(0.25, $recuadro['y']);
        $this->assertLessThan(0.3, $recuadro['ancho']);
        $this->assertLessThan(0.05, $recuadro['alto']);
    }

    public function test_un_monto_se_encuentra_aunque_el_pdf_lo_escriba_con_comas(): void
    {
        $this->afirmarRecuadroValido($this->ubicador()->buscar($this->pdf(), '4512.00'));
        $this->afirmarRecuadroValido($this->ubicador()->buscar($this->pdf(), '4512'));
    }

    public function test_encuentra_un_ssn_truncado_un_ein_y_un_nombre_de_varias_palabras(): void
    {
        $ubicador = $this->ubicador();

        $this->afirmarRecuadroValido($ubicador->buscar($this->pdf(), 'XXX-XX-6789'));
        $this->afirmarRecuadroValido($ubicador->buscar($this->pdf(), 'xxx-xx-6789'), 'Sin importar mayúsculas.');
        $this->afirmarRecuadroValido($ubicador->buscar($this->pdf(), '12-3456789'));

        $nombre = $ubicador->buscar($this->pdf(), 'Formulario de prueba');
        $this->afirmarRecuadroValido($nombre);
        $this->assertGreaterThan(0.2, $nombre['ancho'], 'El recuadro cubre las tres palabras.');
    }

    public function test_si_no_esta_en_la_pagina_que_dijo_el_modelo_lo_busca_en_las_demas(): void
    {
        $ubicador = $this->ubicador();

        $enLaDos = $ubicador->buscar($this->pdf(), 'relleno', 2);
        $this->assertSame(2, $enLaDos['pagina']);

        $equivocada = $ubicador->buscar($this->pdf(), 'relleno', 1);
        $this->assertSame(2, $equivocada['pagina'], 'Dijo la página 1, pero está en la 2.');

        $this->assertSame(1, $ubicador->buscar($this->pdf(), '45120.00', 2)['pagina']);
    }

    public function test_con_el_mismo_monto_en_dos_renglones_gana_el_del_texto_cercano(): void
    {
        $ubicador = $this->ubicador();

        $primero = $ubicador->buscar($this->pdf(), '45120.00');
        $segundo = $ubicador->buscar($this->pdf(), '45120.00', 1, 'Social security wages');
        $conOtroTexto = $ubicador->buscar($this->pdf(), '45120.00', 1, 'Wages, tips, other compensation');
        $sinCoincidencia = $ubicador->buscar($this->pdf(), '45120.00', 1, 'texto que no está');

        $this->assertGreaterThan($primero['y'], $segundo['y'], 'El segundo renglón está más abajo.');
        $this->assertSame($primero, $conOtroTexto);
        $this->assertSame($primero, $sinCoincidencia, 'Sin coincidencia con el texto, la primera.');
    }

    public function test_lo_que_no_aparece_o_no_se_puede_buscar_con_seguridad_da_null(): void
    {
        $ubicador = $this->ubicador();

        $this->assertNull($ubicador->buscar($this->pdf(), '99999.99'), 'Un monto que no está.');
        $this->assertNull($ubicador->buscar($this->pdf(), 'No existe este texto'));
        $this->assertNull($ubicador->buscar($this->pdf(), ''));

        // El 2 aparece como palabra ("página 2") pero un entero tan chico coincidiría con casi todo.
        $this->assertNull($ubicador->buscar($this->pdf(), '2'));
        $this->assertNull($ubicador->buscar($this->pdf(), '100'));
        // Y un texto de menos de 3 caracteres, igual ("de" está en el título).
        $this->assertNull($ubicador->buscar($this->pdf(), 'de'));
    }

    public function test_un_pdf_sin_capa_de_texto_o_dañado_da_null(): void
    {
        $ubicador = $this->ubicador();

        $this->assertNull($ubicador->buscar($this->pdf('solo-imagen.pdf'), '45120.00'), 'Un PDF de imagen no tiene texto: no se inventa un recuadro.');

        $roto = tempnam(sys_get_temp_dir(), 'roto_').'.pdf';
        file_put_contents($roto, 'esto no es un pdf');

        try {
            $this->assertNull($ubicador->buscar($roto, '45120.00'));
        } finally {
            @unlink($roto);
        }
    }

    public function test_sin_pdftotext_lanza_un_error_claro(): void
    {
        if ((new ExecutableFinder)->find('pdftotext') !== null) {
            $this->markTestSkipped('pdftotext sí está instalado.');
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('pdftotext');

        (new UbicadorEnPdf)->buscar($this->pdf(), '45120.00');
    }

    public function test_dos_busquedas_sobre_el_mismo_pdf_dan_lo_mismo(): void
    {
        $ubicador = $this->ubicador();

        $this->assertSame($ubicador->buscar($this->pdf(), '4512.00'), $ubicador->buscar($this->pdf(), '4512.00'));
    }
}
