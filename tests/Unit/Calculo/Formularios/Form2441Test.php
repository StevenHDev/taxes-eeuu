<?php

namespace Tests\Unit\Calculo\Formularios;

use App\Enums\Expediente\ClasificacionPersona;
use App\Enums\Expediente\EstadoCivilIrs;
use App\Models\ParametroFiscal;
use App\Services\Calculo\Formularios\Form2441;
use App\Services\Calculo\Formularios\Form2441\Credito2441;
use App\Services\Calculo\Nucleo\DatoEntrada;
use App\Services\Calculo\Nucleo\Declaracion;
use App\Services\Calculo\Nucleo\Dinero;
use App\Services\Calculo\Nucleo\DocumentoEntrada;
use App\Services\Calculo\Nucleo\EntradasDeclaracion;
use App\Services\Calculo\Nucleo\EstadoLinea;
use App\Services\Calculo\Nucleo\Linea;
use App\Services\Calculo\Nucleo\OrigenTraza;
use App\Services\Calculo\Nucleo\PersonaEntrada;
use App\Services\Calculo\Nucleo\RolPersona;
use App\Services\Calculo\Nucleo\Traza;
use App\Support\ParametrosFiscales;
use Database\Seeders\CatalogoFiscalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 6.4f: Form 2441 (crédito por cuidado de hijos y dependientes) y la línea 1e del 1040.
 *
 * Cifras armadas a mano con el Form 2441 de 2025 (p. 1: línea 3 con tope de USD 3.000 y 6.000, tabla de la línea 8;
 * p. 2: líneas 12 a 31) y sus instrucciones (i2441 de 2025: persona calificada p. 1, MFS p. 2, tope de dos personas
 * p. 4, ingreso del trabajo p. 4, hoja del límite p. 5, Parte III pp. 5 y 6, con su máximo de USD 5.000 de la p. 5).
 */
class Form2441Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('es');
    }

    public function test_un_proveedor_y_una_persona(): void
    {
        // Línea 3 = 2.500; 4 = 5 = 40.000; 6 = 2.500; línea 7 = 40.000 → "39,000—41,000 .22"; 9a = 550; 10 = 3.000; 11 = 550.
        $e = $this->entradas(EstadoCivilIrs::Single, [$this->hijo(10, 5)],
            w2: [$this->w2(1, 1, '40000')],
            recibos: [$this->recibo(20, 10, '2500')]);
        $dec = $this->correr($e, agi: '40000', impuesto: '3000');

        $this->assertSame('2.500,00', $this->valor($dec, '3'));
        $this->assertSame('40.000,00', $this->valor($dec, '4'));
        $this->assertSame('40.000,00', $this->valor($dec, '5'));
        $this->assertSame('2.500,00', $this->valor($dec, '6'));
        $this->assertSame('0.22', $dec->linea('f2441', '8')->valor);
        $this->assertSame('550,00', $this->valor($dec, '9a'));
        $this->assertSame('550,00', $this->valor($dec, '11'));
        $this->assertSame('2.500,00', $this->valor($dec, 'parte-i'));
        $this->assertSame(EstadoLinea::NoAplica, $dec->linea('1040', '1e')->estado);
    }

    public function test_tope_de_una_persona_y_limite_por_el_impuesto(): void
    {
        // Gastos 4.000 → tope 3.000. Línea 7 = 14.000 → ".35"; 9a = 1.050; límite (1040 línea 18) = 500 → 11 = 500.
        $e = $this->entradas(EstadoCivilIrs::Hoh, [$this->hijo(10, 3)],
            w2: [$this->w2(1, 1, '14000')],
            recibos: [$this->recibo(20, 10, '4000')]);
        $dec = $this->correr($e, agi: '14000', impuesto: '500');

        $this->assertSame('3.000,00', $this->valor($dec, '3'));
        $this->assertSame('0.35', $dec->linea('f2441', '8')->valor);
        $this->assertSame('1.050,00', $this->valor($dec, '9a'));
        $this->assertSame('500,00', $this->valor($dec, '10'));
        $this->assertSame('500,00', $this->valor($dec, '11'));
    }

    public function test_dos_proveedores_dos_personas_y_el_menor_ingreso_del_trabajo_en_conjunta(): void
    {
        // Gastos 4.000 + 3.000 = 7.000 → tope de dos personas 6.000. Contribuyente 50.000, cónyuge 4.000 → 6 = 4.000.
        // Línea 7 = 54.000 → ".20" (43,000—No limit); 9a = 800.
        $e = $this->entradas(EstadoCivilIrs::Mfj, [$this->hijo(10, 3), $this->hijo(11, 11)],
            w2: [$this->w2(1, 1, '50000'), $this->w2(2, 2, '4000')],
            recibos: [$this->recibo(20, 10, '4000', proveedor: 'Guardería Sol'), $this->recibo(21, 11, '3000', proveedor: 'Ana Pérez')]);
        $dec = $this->correr($e, agi: '54000', impuesto: '5000');

        $this->assertSame('6.000,00', $this->valor($dec, '3'));
        $this->assertSame('50.000,00', $this->valor($dec, '4'));
        $this->assertSame('4.000,00', $this->valor($dec, '5'));
        $this->assertSame('4.000,00', $this->valor($dec, '6'));
        $this->assertSame('800,00', $this->valor($dec, '11'));
    }

    public function test_dos_personas_calificadas_dan_el_tope_de_6000_aunque_el_gasto_sea_de_una(): void
    {
        // i2441, p. 4: "$6,000 if you have two or more qualifying persons even if you only incurred expenses for just one".
        $e = $this->entradas(EstadoCivilIrs::Single, [$this->hijo(10, 3), $this->hijo(11, 11)],
            w2: [$this->w2(1, 1, '60000')],
            recibos: [$this->recibo(20, 10, '5000')]);
        $dec = $this->correr($e, agi: '60000', impuesto: '5000');

        $this->assertSame('5.000,00', $this->valor($dec, '3'));
        $this->assertSame('1.000,00', $this->valor($dec, '11'));
    }

    public function test_los_recibos_del_mismo_proveedor_se_juntan_sin_escribir_su_nombre_ni_su_tin(): void
    {
        $e = $this->entradas(EstadoCivilIrs::Single, [$this->hijo(10, 3)],
            w2: [$this->w2(1, 1, '60000')],
            recibos: [
                $this->recibo(20, 10, '1000', proveedor: 'Ana Pérez', tin: '123-45-6789'),
                $this->recibo(21, 10, '1500', proveedor: ' ana  pérez ', tin: '123-45-6789'),
            ]);
        $dec = $this->correr($e, agi: '60000', impuesto: '5000');

        $this->assertSame('2.500,00', $this->valor($dec, 'parte-i'));
        $textos = array_map(fn ($p): string => $p->texto, $dec->linea('f2441', 'parte-i')->traza->pasos);
        $this->assertSame(2, count(array_filter($textos, fn (string $t): bool => str_starts_with($t, 'Proveedor 1'))));

        foreach ($dec->lineas('f2441') as $linea) {
            foreach ($linea->traza->pasos as $paso) {
                foreach ([$paso->texto, (string) $paso->referencia, is_string($paso->valor) ? $paso->valor : ''] as $texto) {
                    $this->assertStringNotContainsStringIgnoringCase('pérez', $texto);
                    $this->assertDoesNotMatchRegularExpression('/\d{3}-?\d{2}-?\d{4}/', $texto);
                }
            }
        }
    }

    public function test_un_recibo_sin_tin_sale_igual_con_la_marca(): void
    {
        $e = $this->entradas(EstadoCivilIrs::Single, [$this->hijo(10, 3)],
            w2: [$this->w2(1, 1, '60000')],
            recibos: [$this->recibo(20, 10, '1000', tin: null)]);
        $dec = $this->correr($e, agi: '60000', impuesto: '5000');

        $this->assertSame('200,00', $this->valor($dec, '11'));
        $textos = implode(' | ', array_map(fn ($p): string => $p->texto, $dec->linea('f2441', 'parte-i')->traza->pasos));
        $this->assertStringContainsString('R-12', $textos);
    }

    public function test_el_conyuge_sin_ingreso_del_trabajo_deja_el_credito_pendiente(): void
    {
        // i2441, p. 4: el cónyuge estudiante o incapacitado tiene un ingreso supuesto; hoy no se pregunta.
        $e = $this->entradas(EstadoCivilIrs::Mfj, [$this->hijo(10, 3)],
            w2: [$this->w2(1, 1, '50000')],
            recibos: [$this->recibo(20, 10, '2000')]);
        $dec = $this->correr($e, agi: '50000', impuesto: '4000');

        $this->assertSame(EstadoLinea::FaltaDato, $dec->linea('f2441', '11')->estado);
        $this->assertStringContainsString('El cónyuge no tuvo ingreso del trabajo', $dec->linea('f2441', '11')->motivo);
    }

    public function test_el_conyuge_sin_ingreso_que_no_fue_estudiante_ni_incapacitado_deja_el_credito_en_cero(): void
    {
        // i2441, p. 4: el ingreso supuesto es solo para el estudiante o el incapacitado; si no fue ninguna, su ingreso real (0).
        $e = $this->entradas(EstadoCivilIrs::Mfj, [$this->hijo(10, 3)],
            w2: [$this->w2(1, 1, '50000')],
            recibos: [$this->recibo(20, 10, '2000')]);
        $personas = array_map(fn (PersonaEntrada $p) => $p->rol === RolPersona::Conyuge
            ? new PersonaEntrada($p->id, $p->rol, $p->clasificacion, $p->edadAlFinDeAnio, true, false, esEstudiante: false, incapacitado: false)
            : $p, $e->personas);
        $e = new EntradasDeclaracion(2025, $e->estadoCivil, personas: $personas, documentos: $e->documentos);
        $dec = $this->correr($e, agi: '50000', impuesto: '4000');

        $this->assertSame('0,00', $dec->linea('f2441', '6')->valor->texto());
        $this->assertSame(EstadoLinea::Calculada, $dec->linea('f2441', '11')->estado);
        $this->assertTrue($dec->linea('f2441', '11')->valor->esCero());
    }

    public function test_el_ingreso_del_trabajo_suma_el_autoempleo_neto_de_la_duena_del_negocio(): void
    {
        // i2441, p. 4, punto 2: Schedule SE línea 3 (8.000) menos Schedule 1 línea 15 (565,18) = 7.434,82 para la
        // cónyuge (dueña del 1099-NEC); contribuyente 30.000 de W-2.
        $e = $this->entradas(EstadoCivilIrs::Mfj, [$this->hijo(10, 3)],
            w2: [$this->w2(1, 1, '30000')],
            recibos: [$this->recibo(20, 10, '2000')],
            nec: [new DocumentoEntrada('1099-nec', 30, 2, ['1' => $this->dato('8000', '1099-nec#30:1')])]);
        $dec = new Declaracion(2025, 'prueba');
        $this->poner($dec, 'sch-se', '3', '8000');
        $this->poner($dec, 'sch-1', '15', '565.18');
        $dec = $this->correr($e, agi: '37434.82', impuesto: '1000', dec: $dec);

        $this->assertSame('30.000,00', $this->valor($dec, '4'));
        $this->assertSame('7.434,82', $this->valor($dec, '5'));
    }

    public function test_un_w2_sin_dueno_deja_el_credito_pendiente(): void
    {
        $e = $this->entradas(EstadoCivilIrs::Single, [$this->hijo(10, 3)],
            w2: [$this->w2(1, null, '30000')],
            recibos: [$this->recibo(20, 10, '2000')]);
        $dec = $this->correr($e, agi: '30000', impuesto: '1000');

        $this->assertSame('Falta saber de quién es el W-2 #1.', $dec->linea('f2441', '11')->motivo);
    }

    public function test_un_recibo_sin_la_persona_cuidada_deja_el_credito_pendiente(): void
    {
        $e = $this->entradas(EstadoCivilIrs::Single, [$this->hijo(10, 3)],
            w2: [$this->w2(1, 1, '30000')],
            recibos: [$this->recibo(20, null, '2000')]);
        $dec = $this->correr($e, agi: '30000', impuesto: '1000');

        $this->assertSame(EstadoLinea::FaltaDato, $dec->linea('f2441', '11')->estado);
        $this->assertStringContainsString('#20', $dec->linea('f2441', '11')->motivo);
    }

    public function test_un_hijo_de_13_o_mas_que_puede_cuidarse_no_cuenta(): void
    {
        // i2441, p. 1: hijo calificado menor de 13; el mayor solo si no puede cuidarse solo.
        $e = $this->entradas(EstadoCivilIrs::Single, [$this->hijo(10, 3), $this->hijo(11, 15, incapacitado: false)],
            w2: [$this->w2(1, 1, '30000')],
            recibos: [$this->recibo(20, 10, '1000'), $this->recibo(21, 11, '2000')]);
        $dec = $this->correr($e, agi: '30000', impuesto: '1000');

        $this->assertSame('1.000,00', $this->valor($dec, 'parte-ii'));
        $this->assertSame('1.000,00', $this->valor($dec, '3'));
    }

    public function test_un_hijo_que_cumplio_13_en_el_ano_lo_separa_el_preparador(): void
    {
        $e = $this->entradas(EstadoCivilIrs::Single, [$this->hijo(10, 13)],
            w2: [$this->w2(1, 1, '30000')],
            recibos: [$this->recibo(20, 10, '1000')]);
        $dec = $this->correr($e, agi: '30000', impuesto: '1000');

        $this->assertSame(EstadoLinea::FaltaDato, $dec->linea('f2441', '11')->estado);
    }

    public function test_un_recibo_de_otro_ano_no_lo_soporta_el_piloto(): void
    {
        $e = $this->entradas(EstadoCivilIrs::Single, [$this->hijo(10, 3)],
            w2: [$this->w2(1, 1, '30000')],
            recibos: [$this->recibo(20, 10, '1000', desde: '2024-11-01', hasta: '2024-12-31')]);
        $dec = $this->correr($e, agi: '30000', impuesto: '1000');

        $this->assertSame(EstadoLinea::NoSoportada, $dec->linea('f2441', '11')->estado);
    }

    public function test_beneficios_del_w2_con_exceso_van_a_la_linea_1e(): void
    {
        // Parte III: 12 = 6.000; 15 = 6.000; 16 = 4.000; 17 = 4.000; 18 = 19 = 60.000; 20 = 4.000; 21 = 5.000 (i2441,
        // p. 5); 22 = 0; 23 = 6.000; 24 = 0; 25 = min(20, 21) = 4.000; 26 = 6.000 − 4.000 = 2.000 → 1040 línea 1e.
        // Crédito: 27 = 3.000; 28 = 4.000; 29 = 0 → 31 = 0 → 3 = 0 → 11 = 0.
        $this->sembrarTopeDeBeneficios();
        $e = $this->entradas(EstadoCivilIrs::Single, [$this->hijo(10, 4)],
            w2: [$this->w2(1, 1, '60000', beneficios: '6000')],
            recibos: [$this->recibo(20, 10, '4000')]);
        $dec = $this->correr($e, agi: '62000', impuesto: '5000');

        $this->assertSame('6.000,00', $this->valor($dec, '12'));
        $this->assertSame('4.000,00', $this->valor($dec, '16'));
        $this->assertSame('4.000,00', $this->valor($dec, '20'));
        $this->assertSame('5.000,00', $this->valor($dec, '21'));
        $this->assertSame('0,00', $this->valor($dec, '24'));
        $this->assertSame('4.000,00', $this->valor($dec, '25'));
        $this->assertSame('2.000,00', $this->valor($dec, '26'));
        $this->assertSame('2.000,00', $this->valor($dec, '1040', '1e'));
        $this->assertSame('0,00', $this->valor($dec, '29'));
        $this->assertSame('0,00', $this->valor($dec, '3'));
        // Línea 4: el ingreso del trabajo de la 1z incluye la 1e.
        $this->assertSame('62.000,00', $this->valor($dec, '4'));
        $this->assertSame('0,00', $this->valor($dec, '11'));
    }

    public function test_beneficios_menores_que_los_gastos_dejan_credito_por_lo_que_falta(): void
    {
        // 12 = 2.000; 16 = 5.000; 17 = 2.000; 20 = 2.000; 25 = 2.000; 26 = 0. 27 = 3.000; 28 = 2.000; 29 = 1.000;
        // 30 = 5.000 − 2.000 = 3.000; 31 = 1.000 → 3 = 1.000. Línea 7 = 30.000 → ".27"; 11 = 270.
        $this->sembrarTopeDeBeneficios();
        $e = $this->entradas(EstadoCivilIrs::Single, [$this->hijo(10, 4)],
            w2: [$this->w2(1, 1, '30000', beneficios: '2000')],
            recibos: [$this->recibo(20, 10, '5000')]);
        $dec = $this->correr($e, agi: '30000', impuesto: '2000');

        $this->assertSame('0,00', $this->valor($dec, '1040', '1e'));
        $this->assertSame('1.000,00', $this->valor($dec, '29'));
        $this->assertSame('3.000,00', $this->valor($dec, '30'));
        $this->assertSame('1.000,00', $this->valor($dec, '3'));
        $this->assertSame('270,00', $this->valor($dec, '11'));
    }

    public function test_calcular_beneficios_solo_escribe_la_parte_iii_y_la_linea_1e(): void
    {
        $this->sembrarTopeDeBeneficios();
        $e = $this->entradas(EstadoCivilIrs::Single, [$this->hijo(10, 4)],
            w2: [$this->w2(1, 1, '60000', beneficios: '6000')],
            recibos: [$this->recibo(20, 10, '4000')]);
        $dec = new Declaracion(2025, 'prueba');
        (new Form2441)->calcularBeneficios($e, $dec);

        $this->assertSame('2.000,00', $this->valor($dec, '1040', '1e'));
        $this->assertNull($dec->linea('f2441', '11'));
    }

    public function test_sin_el_parametro_del_maximo_de_beneficios_la_linea_1e_queda_pendiente(): void
    {
        ParametroFiscal::query()->where(['tax_year' => 2025, 'categoria' => 'credito_cuidado_dependientes', 'clave' => 'tope_beneficios_excluibles'])->delete();
        $e = $this->entradas(EstadoCivilIrs::Single, [$this->hijo(10, 4)],
            w2: [$this->w2(1, 1, '60000', beneficios: '6000')],
            recibos: [$this->recibo(20, 10, '4000')]);
        $dec = $this->correr($e, agi: '62000', impuesto: '5000');

        $this->assertSame(EstadoLinea::FaltaDato, $dec->linea('1040', '1e')->estado);
        $this->assertStringContainsString('credito_cuidado_dependientes.tope_beneficios_excluibles', $dec->linea('f2441', '26')->motivo);
        $this->assertSame(EstadoLinea::FaltaDato, $dec->linea('f2441', '11')->estado);
    }

    public function test_beneficios_sin_recibo_del_cuidado_dejan_todo_pendiente(): void
    {
        $e = $this->entradas(EstadoCivilIrs::Single, [$this->hijo(10, 4)], w2: [$this->w2(1, 1, '60000', beneficios: '3000')]);
        $dec = $this->correr($e, agi: '60000', impuesto: '5000');

        $this->assertSame(EstadoLinea::FaltaDato, $dec->linea('1040', '1e')->estado);
        $this->assertSame(EstadoLinea::FaltaDato, $dec->linea('f2441', '11')->estado);
    }

    public function test_sin_recibos_ni_beneficios_no_aplica(): void
    {
        $e = $this->entradas(EstadoCivilIrs::Single, [$this->hijo(10, 4)], w2: [$this->w2(1, 1, '60000')]);
        $dec = $this->correr($e, agi: '60000', impuesto: '5000');

        $this->assertSame(EstadoLinea::NoAplica, $dec->linea('f2441', '11')->estado);
        $this->assertSame(EstadoLinea::NoAplica, $dec->linea('f2441', '26')->estado);
        $this->assertSame(EstadoLinea::NoAplica, $dec->linea('1040', '1e')->estado);
    }

    public function test_casado_por_separado_lo_revisa_el_preparador(): void
    {
        // i2441, p. 2, "Married Persons Filing Separately": requisitos que la entrevista no pregunta.
        $e = $this->entradas(EstadoCivilIrs::Mfs, [$this->hijo(10, 4)],
            w2: [$this->w2(1, 1, '30000')],
            recibos: [$this->recibo(20, 10, '2000')]);
        $dec = $this->correr($e, agi: '30000', impuesto: '2000');

        $this->assertSame('Con MFS, este crédito lo revisa el preparador.', $dec->linea('f2441', '11')->motivo);
    }

    public function test_sin_estado_civil_falta_el_dato(): void
    {
        $e = $this->entradas(null, [$this->hijo(10, 4)], w2: [$this->w2(1, 1, '30000')], recibos: [$this->recibo(20, 10, '2000')]);
        $dec = $this->correr($e, agi: '30000', impuesto: '2000');

        $this->assertSame('Falta decidir el estado civil en Revisión.', $dec->linea('f2441', '11')->motivo);
    }

    public function test_sin_la_linea_18_del_1040_el_credito_queda_pendiente(): void
    {
        $e = $this->entradas(EstadoCivilIrs::Single, [$this->hijo(10, 4)], w2: [$this->w2(1, 1, '30000')], recibos: [$this->recibo(20, 10, '2000')]);
        $dec = new Declaracion(2025, 'prueba');
        $this->poner($dec, '1040', '11a', '30000');
        (new Form2441)->calcular($e, $dec);

        $this->assertSame('Falta la línea 18 del Form 1040.', $dec->linea('f2441', '11')->motivo);
    }

    public function test_los_datos_sin_validar_marcan_el_credito(): void
    {
        $e = $this->entradas(EstadoCivilIrs::Single, [$this->hijo(10, 4)],
            w2: [$this->w2(1, 1, '30000')],
            recibos: [$this->recibo(20, 10, '2000', validado: false)]);
        $dec = $this->correr($e, agi: '30000', impuesto: '2000');

        $this->assertTrue($dec->linea('f2441', '11')->usaSinValidar);
        $this->assertContains('recibo-cuidado#20:monto-pagado', $dec->linea('f2441', '11')->datosSinValidar);
    }

    public function test_en_2026_la_conjunta_usa_su_propia_columna_de_la_tabla(): void
    {
        // Instrucciones del 2441 de 2026 (borrador), p. 6: conjunta hasta 150.000 → .35; las demás, 99.000 a 101.000 → .22.
        $this->seed(CatalogoFiscalSeeder::class);
        ParametrosFiscales::invalidate();

        $this->assertSame('0.35', Credito2441::decimalParaLinea7(Dinero::deDolares(100000), 2026, true));
        $this->assertSame('0.22', Credito2441::decimalParaLinea7(Dinero::deDolares(100000), 2026, false));
    }

    public function test_un_parametro_pendiente_es_falta_dato_con_su_nombre(): void
    {
        ParametroFiscal::query()->where('tax_year', 2025)->where('categoria', 'credito_cuidado_dependientes')->where('clave', 'tabla')->update(['valor' => null]);

        $e = $this->entradas(EstadoCivilIrs::Single, [$this->hijo(10, 4)], w2: [$this->w2(1, 1, '30000')], recibos: [$this->recibo(20, 10, '2000')]);
        $dec = $this->correr($e, agi: '30000', impuesto: '2000');

        $this->assertStringContainsString('credito_cuidado_dependientes.tabla', $dec->linea('f2441', '11')->motivo);
    }

    // ── Ayudantes ──

    private function correr(EntradasDeclaracion $e, string $agi, string $impuesto, ?Declaracion $dec = null): Declaracion
    {
        $dec ??= new Declaracion(2025, 'prueba');
        // Como el orquestador: la Parte III (1e) antes del 1040, después el resto con las líneas 11a y 18.
        (new Form2441)->calcularBeneficios($e, $dec);
        $this->poner($dec, '1040', '11a', $agi);
        $this->poner($dec, '1040', '18', $impuesto);
        (new Form2441)->calcular($e, $dec);

        return $dec;
    }

    /**
     * @param  list<PersonaEntrada>  $dependientes
     * @param  list<DocumentoEntrada>  $w2
     * @param  list<DocumentoEntrada>  $recibos
     * @param  list<DocumentoEntrada>  $nec
     */
    private function entradas(?EstadoCivilIrs $estado, array $dependientes, array $w2 = [], array $recibos = [], array $nec = []): EntradasDeclaracion
    {
        $personas = [new PersonaEntrada(1, RolPersona::Contribuyente, ClasificacionPersona::NoDependiente, 35, true, false)];

        if ($estado === EstadoCivilIrs::Mfj) {
            $personas[] = new PersonaEntrada(2, RolPersona::Conyuge, ClasificacionPersona::NoDependiente, 34, true, false);
        }

        $documentos = array_filter(['w2' => $w2, 'recibo-cuidado' => $recibos, '1099-nec' => $nec]);

        return new EntradasDeclaracion(2025, $estado, personas: [...$personas, ...$dependientes], documentos: $documentos);
    }

    private function hijo(int $id, ?int $edad, ?bool $incapacitado = null): PersonaEntrada
    {
        return new PersonaEntrada($id, RolPersona::Dependiente, ClasificacionPersona::HijoCalificado, $edad, true, false, incapacitado: $incapacitado);
    }

    private function w2(int $item, ?int $persona, string $salario, ?string $beneficios = null): DocumentoEntrada
    {
        $casillas = ['1' => $this->dato($salario, "w2#{$item}:1")];

        if ($beneficios !== null) {
            $casillas['10'] = $this->dato($beneficios, "w2#{$item}:10");
        }

        return new DocumentoEntrada('w2', $item, $persona, $casillas);
    }

    private function recibo(int $item, ?int $persona, string $monto, string $proveedor = 'Guardería Sol', ?string $tin = '12-3456789', string $desde = '2025-01-01', string $hasta = '2025-12-31', bool $validado = true): DocumentoEntrada
    {
        $casillas = [
            'proveedor-nombre' => new DatoEntrada($proveedor, OrigenTraza::Leido, "recibo-cuidado#{$item}:proveedor-nombre"),
            'monto-pagado' => new DatoEntrada(Dinero::deDolares($monto), OrigenTraza::Leido, "recibo-cuidado#{$item}:monto-pagado", validado: $validado),
            'periodo-desde' => new DatoEntrada($desde, OrigenTraza::Leido),
            'periodo-hasta' => new DatoEntrada($hasta, OrigenTraza::Leido),
        ];

        if ($tin !== null) {
            $casillas['proveedor-tin'] = new DatoEntrada($tin, OrigenTraza::Leido);
        }

        return new DocumentoEntrada('recibo-cuidado', $item, $persona, $casillas);
    }

    private function dato(string $dolares, string $referencia): DatoEntrada
    {
        return new DatoEntrada(Dinero::deDolares($dolares), OrigenTraza::Leido, $referencia);
    }

    /** El máximo excluible de la Parte III (i2441 de 2025, p. 5, línea 21: "$5,000"); ya viene en el catálogo. */
    private function sembrarTopeDeBeneficios(): void
    {
        $this->assertTrue(ParametroFiscal::query()->where(['tax_year' => 2025, 'categoria' => 'credito_cuidado_dependientes', 'clave' => 'tope_beneficios_excluibles'])->exists());
    }

    private function poner(Declaracion $dec, string $formulario, string $linea, string $dolares): void
    {
        $dec->poner(Linea::calculada($formulario, $linea, Dinero::deDolares($dolares), Traza::vacia()));
    }

    private function valor(Declaracion $dec, string $formularioOLinea, ?string $linea = null): string
    {
        [$formulario, $linea] = $linea === null ? ['f2441', $formularioOLinea] : [$formularioOLinea, $linea];
        $l = $dec->linea($formulario, $linea);
        $this->assertNotNull($l, "{$formulario}:{$linea} no se escribió");
        $this->assertSame(EstadoLinea::Calculada, $l->estado, "{$formulario}:{$linea}: {$l->motivo}");

        return $l->valor->texto();
    }
}
