<?php

namespace Tests\Feature;

use App\Support\EventoValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventoValidatorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function datosValidos(array $overrides = []): array
    {
        return array_merge([
            'forma' => 'form_1040',
            'tax_year' => 2025,
            'campo' => 'ingresos',
            'tipo_campo' => 'dato',
            'modo' => 'texto',
            'tipo_dato' => 'object',
            'contenido' => ['salarios' => 1000],
        ], $overrides);
    }

    public function test_un_evento_valido_no_produce_errores(): void
    {
        $errores = (new EventoValidator)->validar($this->datosValidos());

        $this->assertSame([], $errores);
    }

    public function test_un_campo_que_no_existe_en_el_catalogo_produce_error(): void
    {
        $errores = (new EventoValidator)->validar($this->datosValidos(['campo' => 'campo_inventado']));

        $this->assertArrayHasKey('campo', $errores);
    }

    public function test_tipo_campo_que_no_coincide_con_el_catalogo_produce_error(): void
    {
        $errores = (new EventoValidator)->validar($this->datosValidos(['tipo_campo' => 'documento']));

        $this->assertArrayHasKey('tipo_campo', $errores);
    }

    public function test_tipo_dato_que_no_coincide_con_el_catalogo_produce_error(): void
    {
        $errores = (new EventoValidator)->validar($this->datosValidos(['tipo_dato' => 'number']));

        $this->assertArrayHasKey('tipo_dato', $errores);
    }

    public function test_modo_archivo_en_un_campo_tipo_dato_produce_error(): void
    {
        $errores = (new EventoValidator)->validar($this->datosValidos(['modo' => 'archivo']));

        $this->assertArrayHasKey('modo', $errores);
    }

    public function test_modo_archivo_sin_archivo_produce_error(): void
    {
        // Sin este chequeo, esto pasaba la validación sin error y tronaba
        // después con un TypeError dentro de
        // EventoRecoleccionService::procesarArchivo() (exige un UploadedFile
        // no nulo) — ver el comentario en EventoValidator.
        $errores = (new EventoValidator)->validar($this->datosValidos([
            'campo' => 'w2',
            'tipo_campo' => 'documento',
            'modo' => 'archivo',
            'tipo_dato' => null,
            'contenido' => null,
        ]));

        $this->assertArrayHasKey('file', $errores);
    }

    public function test_no_aplica_en_un_campo_obligatorio_produce_error(): void
    {
        // 'ingresos' es obligatorio en form_1040.
        $errores = (new EventoValidator)->validar($this->datosValidos(['modo' => 'no_aplica', 'tipo_dato' => null, 'contenido' => null]));

        $this->assertArrayHasKey('modo', $errores);
    }

    public function test_un_revelado_con_campo_inexistente_produce_error_con_prefijo(): void
    {
        $errores = (new EventoValidator)->validar($this->datosValidos([
            'revelados' => [
                ['forma' => 'form_1040', 'campo' => 'campo_inventado', 'tipo_campo' => 'dato', 'tipo_dato' => 'number', 'contenido' => '1'],
            ],
        ]));

        $this->assertArrayHasKey('revelados.0.campo', $errores);
    }

    public function test_una_forma_desconocida_no_truena_y_no_produce_errores(): void
    {
        // Una forma inválida ya la rechaza la regla estructural (Rule::in) —
        // EventoValidator no debe intentar cruzarla contra el catálogo.
        $errores = (new EventoValidator)->validar($this->datosValidos(['forma' => 'forma_que_no_existe']));

        $this->assertSame([], $errores);
    }
}
