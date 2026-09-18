<?php

namespace Tests\Feature;

use App\Services\WhatsappAgent\ActivosPromptComposer;
use Database\Seeders\PromptActivoStepsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El texto compilado por PromptActivoStepsSeeder (config) reproduce la
 * lista ACTIVOS que antes vivía fija en prompt_actuales/fases/recoleccion.md
 * — para que sacarla a config no cambie el comportamiento del agente al
 * publicarla. Dos excepciones deliberadas, ambas para corregir bugs reales
 * encontrados en producción, no solo infraestructura:
 * - La salvaguarda: antes solo cubría Empleo, ahora cubre los 6 pasos.
 * - La bifurcación Empleo: ahora aclara que obligatorio:false en w2/
 *   form_1099_nec no vuelve la bifurcación misma opcional — bug real donde
 *   el agente marcó AMBOS como no_aplica sin pedir ninguno, pese a que el
 *   cliente ya había confirmado ser empleado (ver commit "fix: el agente
 *   no pedía w2 pese a confirmar ser empleado...").
 */
class ActivosPromptComposerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PromptActivoStepsSeeder::class);
    }

    public function test_compilar_lista_reproduce_el_texto_original_salvo_la_aclaracion_de_empleo(): void
    {
        $esperado = <<<'TXT'
        1. identificacion_ssn_itin
        2. estado_civil
        3. info_conyuge — solo si estado_civil indica que el cliente es casado. Si es soltero, no se pregunta.
        4. info_dependientes — pregunta primero si tiene dependientes; si dice que sí, recolecta el dato completo, incluyendo los 10 subcampos (nombre_completo, ssn, fecha_nacimiento, relacion, meses_en_hogar, estudiante_tiempo_completo, discapacitado, provee_mas_50_soporte_propio, ingreso_bruto_anual, custodia_compartida_sin_conflicto) — sin excepción de ninguno de ellos.
        5. Empleo — pregunta simple: "¿Eres empleado?" (esta pregunta no tiene un campo propio en `pendientes`; es una bifurcación conversacional entre w2 y form_1099_nec):
            - w2 y form_1099_nec suelen traer obligatorio:false en `pendientes` — eso significa que un cliente sin ESTE tipo de ingreso no necesita ninguno de los dos, nunca que esta bifurcación en sí sea opcional. La respuesta del cliente a esta pregunta determina siempre cuál de los dos pedir; nunca marques los dos como no_aplica sin haber pedido primero el que corresponde según la respuesta.
            - Si responde que sí: pide w2. Al guardarlo, invoca también guardar_campo_cliente con modo="no_aplica" para form_1099_nec en el mismo turno (el cliente ya confirmó que es empleado, lo cual responde implícitamente por el 1099-NEC — esto sí cuenta como información entregada explícitamente, ver GROUNDING ESTRICTO).
            - Si responde que no: pide form_1099_nec. Al guardarlo (o si el cliente no tiene ninguno), guarda modo="no_aplica" para w2 en el mismo turno, por la misma razón.
        6. form_1095_a — se mantiene la lógica ya definida arriba (preguntar en lenguaje simple sobre seguro del Marketplace antes de nombrar el formulario).
        TXT;

        $this->assertSame($esperado, app(ActivosPromptComposer::class)->compilarLista());
    }

    /**
     * Antes de esta feature, solo Empleo tenía una salvaguarda contra el
     * bug real de "pendientes no refleja a tiempo un guardado ya hecho" —
     * el mismo patrón exacto que causó el bug de estado_civil en producción
     * (ver commit "fix: estado_civil/info_conyuge quedaban invalido...").
     * Ahora los 6 pasos ACTIVOS tienen la misma protección.
     */
    public function test_compilar_salvaguardas_cubre_los_6_pasos_no_solo_empleo(): void
    {
        $texto = app(ActivosPromptComposer::class)->compilarSalvaguardas();

        $this->assertStringContainsString('identificacion_ssn_itin', $texto);
        $this->assertStringContainsString('estado_civil', $texto);
        $this->assertStringContainsString('info_conyuge', $texto);
        $this->assertStringContainsString('info_dependientes', $texto);
        $this->assertStringContainsString('Empleo', $texto);
        $this->assertStringContainsString('form_1095_a', $texto);
        $this->assertStringContainsString('fuente de verdad', $texto);
    }
}
