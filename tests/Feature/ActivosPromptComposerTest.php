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
 *
 * Pasos 7-10 agregados en la Fase 1 del plan de cierre de brecha GTS
 * (compliance crítico P1); pasos 11-26 agregados en la Fase 2 (documentos
 * promovidos de documentos_extra a ACTIVO); pasos 27-33 agregados en la
 * Fase 3a (identidad y eventos del contribuyente), que también extiende la
 * nota del paso 2 (estado_civil) — ver PromptActivoStepsSeeder.
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
        2. estado_civil — incluye, además del estado civil al 31 de diciembre, si se casó, se divorció o se separó durante el año (subcampos se_caso_en_anio/se_divorcio_o_separo_en_anio — Fase 3a) — distinto de si enviudó, que ya cubre conyuge_fallecio_en_anio.
        3. info_conyuge — solo si estado_civil indica que el cliente es casado. Si es soltero, no se pregunta.
        4. info_dependientes — pregunta primero si tiene dependientes; si dice que sí, recolecta el dato completo, incluyendo los 10 subcampos (nombre_completo, ssn, fecha_nacimiento, relacion, meses_en_hogar, estudiante_tiempo_completo, discapacitado, provee_mas_50_soporte_propio, ingreso_bruto_anual, custodia_compartida_sin_conflicto) — sin excepción de ninguno de ellos.
        5. Empleo — pregunta simple: "¿Eres empleado?" (esta pregunta no tiene un campo propio en `pendientes`; es una bifurcación conversacional entre w2 y form_1099_nec):
            - w2 y form_1099_nec suelen traer obligatorio:false en `pendientes` — eso significa que un cliente sin ESTE tipo de ingreso no necesita ninguno de los dos, nunca que esta bifurcación en sí sea opcional. La respuesta del cliente a esta pregunta determina siempre cuál de los dos pedir; nunca marques los dos como no_aplica sin haber pedido primero el que corresponde según la respuesta.
            - Si responde que sí: pide w2. Al guardarlo, invoca también guardar_campo_cliente con modo="no_aplica" para form_1099_nec en el mismo turno (el cliente ya confirmó que es empleado, lo cual responde implícitamente por el 1099-NEC — esto sí cuenta como información entregada explícitamente, ver GROUNDING ESTRICTO).
            - Si responde que no: pide form_1099_nec. Al guardarlo (o si el cliente no tiene ninguno), guarda modo="no_aplica" para w2 en el mismo turno, por la misma razón.
        6. form_1095_a — se mantiene la lógica ya definida arriba (preguntar en lenguaje simple sobre seguro del Marketplace antes de nombrar el formulario).
        7. activos_digitales — pregunta textual del IRS, obligatoria siempre (nunca modo="no_aplica"): "¿En algún momento del año recibiste, vendiste, intercambiaste o de otra forma dispusiste de un activo digital (criptomonedas, NFTs u otro activo digital)?". Guarda la respuesta como "si" o "no" exactamente (tipo_dato string) — nunca otra palabra ni variante.
        8. cuentas_extranjero — compuerta obligatoria (nunca modo="no_aplica"), en lenguaje simple: "¿Tuviste en algún momento del año cuentas bancarias, de inversión, u otros activos financieros fuera de Estados Unidos?". Guarda la respuesta como "si" o "no" exactamente (tipo_dato string) — nunca otra palabra ni variante. Si responde "si", a continuación se pide el detalle (país, institución, valor máximo del año) — no lo pidas en este mismo turno.
        9. cuentas_extranjero_detalle — solo si cuentas_extranjero fue respondido como "si". Si respondió "no", no se pregunta.
        10. venta_residencia_principal — pregunta en lenguaje simple si vendió su residencia principal durante el año (distinto de cualquier otra propiedad de alquiler/inversión, que se cubre en Schedule E); si confirma que sí, pide fecha de venta, precio de venta y costo base original (para evaluar la exclusión de $250,000/$500,000, que solo aplica a la residencia principal) — nunca calcules tú la exclusión, solo recolecta los datos.
        11. Retiro y jubilación — pregunta compuesta: "¿Recibiste dinero de tu retiro, pensión, o Seguro Social (Social Security) este año?" (esta pregunta no tiene un campo propio en `pendientes`; agrupa varios campos distintos en un solo turno: form_1099_r, ssa_1099):
            - Todos los miembros de este grupo traen obligatorio:false en `pendientes` — el cliente puede confirmar ninguno, algunos o todos.
            - Pregunta la lista completa una sola vez, en un solo mensaje de WhatsApp. Por cada miembro que el cliente confirme que tiene, sigue las reglas normales de guardado de ese campo puntual (si es documento, pide el archivo; si es dato, pide el valor) — puede requerir más de un turno si el cliente confirma varios a la vez pero solo entrega uno por mensaje.
            - Por cada miembro que el cliente NO confirme (dice que no tiene ninguno de esos, o los que no menciona al responder), invoca guardar_campo_cliente con modo="no_aplica" para ese campo, en la misma tanda de turnos que resuelve el grupo — nunca vuelvas a preguntar por un miembro individualmente después de esta pregunta compuesta.
        12. form_1099_int — pregunta simple si tuvo intereses bancarios o de cuentas de inversión durante el año.
        13. form_1099_div — pregunta simple si recibió dividendos de inversiones durante el año.
        14. form_1099_b — pregunta simple si vendió acciones, ETFs, fondos, bonos u otras inversiones durante el año.
        15. form_1099_g — pregunta simple si recibió desempleo o un reembolso de impuestos estatales/locales durante el año.
        16. form_1098 — pregunta simple si pagó intereses hipotecarios sobre su residencia durante el año.
        17. form_1098_e — pregunta simple si pagó intereses de préstamos estudiantiles durante el año.
        18. form_1099_misc — pregunta simple si recibió regalías (royalties) u otros ingresos varios reportados en un 1099-MISC durante el año.
        19. form_1099_k — pregunta simple si recibió ingresos por alquiler de corto plazo (Airbnb, VRBO) o pagos por plataformas de pago (Zelle, Venmo, PayPal) reportados en un 1099-K durante el año.
        20. form_1099_s — pregunta simple si vendió una propiedad distinta de su residencia principal (terreno, segunda vivienda, propiedad de alquiler) durante el año — distinto de venta_residencia_principal, que ya tiene su propia pregunta.
        21. k1_recibido — pregunta simple si recibió un Schedule K-1 durante el año, de una sociedad (partnership), una S corporation, o un fideicomiso/sucesión — un único documento cubre los tres casos, no hace falta distinguir cuál.
        22. form_w2g — pregunta simple si tuvo ganancias reportables de juego (casino, lotería) durante el año.
        23. form_1099_c — pregunta simple si tuvo una cancelación o condonación de deuda durante el año.
        24. form_1099_sa — pregunta simple si recibió una distribución de su HSA durante el año.
        25. form_5498_sa — pregunta simple si hizo aportes a su HSA durante el año.
        26. declaracion_anio_anterior — pregunta simple si puede compartir su declaración de impuestos del año anterior (útil para pérdidas de capital o créditos arrastrados).
        27. fecha_nacimiento_contribuyente — pregunta simple la fecha de nacimiento del propio contribuyente — distinto de info_conyuge.fecha_nacimiento (que nunca se pregunta, ver más abajo) e info_dependientes.fecha_nacimiento (que sí se pregunta como parte de ese campo).
        28. direccion_contribuyente — pregunta la dirección actual del cliente (calle, ciudad, estado, código postal), en un único mensaje, no subcampo por subcampo.
        29. ocupacion — pregunta simple la ocupación u oficio del cliente.
        30. puede_ser_reclamado_como_dependiente — pregunta obligatoria (nunca modo="no_aplica"), en lenguaje simple: "¿Puede otra persona reclamarte como dependiente en su propia declaración?". Guarda la respuesta como "si" o "no" exactamente (tipo_dato string) — nunca otra palabra ni variante.
        31. vivio_trabajo_fuera_eeuu — pregunta obligatoria (nunca modo="no_aplica"), en lenguaje simple: "¿Viviste o trabajaste fuera de Estados Unidos en algún momento del año?". Guarda la respuesta como "si" o "no" exactamente (tipo_dato string) — nunca otra palabra ni variante.
        32. ip_pin — pregunta simple si el IRS le asignó un IP PIN (un código de 6 dígitos, distinto del reembolso) y, si lo tiene, cuál es.
        33. form_8332 — pregunta simple si existe un acuerdo de custodia compartida o un Form 8332 firmado por el otro padre/madre, cediendo el derecho a reclamar a un dependiente.
        TXT;

        $this->assertSame($esperado, app(ActivosPromptComposer::class)->compilarLista());
    }

    /**
     * Antes de esta feature, solo Empleo tenía una salvaguarda contra el
     * bug real de "pendientes no refleja a tiempo un guardado ya hecho" —
     * el mismo patrón exacto que causó el bug de estado_civil en producción
     * (ver commit "fix: estado_civil/info_conyuge quedaban invalido...").
     * Ahora los 10 pasos ACTIVOS tienen la misma protección.
     */
    public function test_compilar_salvaguardas_cubre_todos_los_pasos_no_solo_empleo(): void
    {
        $texto = app(ActivosPromptComposer::class)->compilarSalvaguardas();

        $this->assertStringContainsString('identificacion_ssn_itin', $texto);
        $this->assertStringContainsString('estado_civil', $texto);
        $this->assertStringContainsString('info_conyuge', $texto);
        $this->assertStringContainsString('info_dependientes', $texto);
        $this->assertStringContainsString('Empleo', $texto);
        $this->assertStringContainsString('form_1095_a', $texto);
        $this->assertStringContainsString('activos_digitales', $texto);
        $this->assertStringContainsString('cuentas_extranjero', $texto);
        $this->assertStringContainsString('venta_residencia_principal', $texto);
        $this->assertStringContainsString('Retiro y jubilación', $texto);
        $this->assertStringContainsString('form_1099_int', $texto);
        $this->assertStringContainsString('declaracion_anio_anterior', $texto);
        $this->assertStringContainsString('direccion_contribuyente', $texto);
        $this->assertStringContainsString('form_8332', $texto);
        $this->assertStringContainsString('fuente de verdad', $texto);
    }
}
