<?php

namespace Tests\Unit;

use App\Support\TextoWhatsapp;
use PHPUnit\Framework\TestCase;

/**
 * Un mensaje de más de 1600 caracteres lo rechaza Twilio (error 21617) y el
 * cliente se queda sin respuesta: el texto largo sale en varios mensajes.
 */
class TextoWhatsappTest extends TestCase
{
    public function test_un_texto_corto_sale_tal_cual(): void
    {
        $this->assertSame(['Hola, ¿cómo estás?'], TextoWhatsapp::partir("  Hola, ¿cómo estás?\n"));
    }

    public function test_un_texto_largo_se_parte_entre_parrafos_sin_perder_nada(): void
    {
        $parrafos = array_map(fn (int $i) => "{$i}. ".str_repeat('pregunta ', 40), range(1, 12));
        $texto = implode("\n\n", array_map('trim', $parrafos));

        $partes = TextoWhatsapp::partir($texto);

        $this->assertGreaterThan(1, count($partes));

        foreach ($partes as $parte) {
            $this->assertLessThanOrEqual(TextoWhatsapp::MAXIMO, mb_strlen($parte));
            $this->assertSame(trim($parte), $parte);
        }

        $this->assertSame($texto, implode("\n\n", $partes), 'Cortar entre párrafos no cambia el texto.');
    }

    public function test_una_lista_numerada_se_corta_entre_renglones(): void
    {
        $renglones = array_map(fn (int $i) => "{$i}. ".str_repeat('¿Tuviste ingresos? ', 8), range(1, 30));
        $texto = implode("\n", array_map('trim', $renglones));

        $partes = TextoWhatsapp::partir($texto);

        foreach ($partes as $parte) {
            $this->assertLessThanOrEqual(TextoWhatsapp::MAXIMO, mb_strlen($parte));
        }

        $this->assertSame($texto, implode("\n", $partes));
    }

    public function test_un_renglon_mas_largo_que_el_limite_se_corta_entre_palabras(): void
    {
        $texto = trim(str_repeat('palabra ', 600));

        $partes = TextoWhatsapp::partir($texto);

        $this->assertGreaterThan(1, count($partes));

        foreach ($partes as $parte) {
            $this->assertLessThanOrEqual(TextoWhatsapp::MAXIMO, mb_strlen($parte));
        }

        $this->assertSame($texto, implode(' ', $partes));
    }

    public function test_una_palabra_sin_espacios_mas_larga_que_el_limite_tambien_se_parte(): void
    {
        $partes = TextoWhatsapp::partir(str_repeat('a', 4000));

        $this->assertCount(3, $partes);
        $this->assertSame(str_repeat('a', 4000), implode('', $partes));
    }

    public function test_un_limite_menor_se_respeta(): void
    {
        foreach (TextoWhatsapp::partir("uno dos tres\ncuatro cinco seis", 12) as $parte) {
            $this->assertLessThanOrEqual(12, mb_strlen($parte));
        }
    }
}
