# Análisis del Cuestionario de Descubrimiento — Global Tax Services

Este documento analiza las respuestas que el dueño de Global Tax Services dio en
`Cuestionario_Descubrimiento_GlobalTax.docx.md`, las contrasta con lo que la plataforma
`taxes-eeuu` implementa hoy, y propone **respuestas hipotéticas** para las preguntas que el
dueño dejó sin contestar (secciones 6 a 15), inferidas de:

1. Lo que él mismo describió del negocio en las secciones 1-5 (sí respondidas).
2. Lo que ya existe construido en la plataforma (catálogo, agente de WhatsApp, motor de
   reglas, riesgo, permisos, documentos).

**Todo lo marcado como "Inferencia" es una hipótesis de trabajo, no una respuesta confirmada
por el dueño.** El objetivo es tener un borrador que el dueño solo tenga que corregir o
validar, en lugar de partir de una hoja en blanco.

---

## 0. Lectura general del tipo de negocio

De las secciones 1-5 se desprende un perfil claro:

- Despacho **pequeño, presencial, de alto contacto humano**: el cliente llama para pedir
  cita, entrega documentos en persona, y se le explica el resultado cara a cara antes de
  entregarle el folder impreso.
- **Mayoría de clientes "sencillos"** (W-2, a veces 1099-NEC, a veces 1095-A), con una
  **cola de casos más complejos** (negocio propio, corporación, inversiones/cripto, casa
  propia, estudiantes) que son la minoría pero consumen desproporcionadamente el tiempo.
- El dolor principal no es el cálculo (eso "ya lo hace el sistema preestablecido" —
  TaxWise), sino todo lo que pasa **antes** del cálculo: recolectar información y
  documentos, y luego digitarlos. El seguimiento de casos es hoy **inexistente** ("NULO,
  no hay tiempo para eso").
- El objetivo de negocio declarado es explícito: **mismo equipo/infraestructura, más
  volumen de clientes**, liberando tiempo humano de los casos simples para poder atender
  más clientes nuevos.
- Confía en automatizar **todo** el proceso de un cliente sencillo, pero exige que
  **siempre** exista una auditoría humana final — no quiere una automatización sin
  supervisión, incluso en el escenario ideal.

Esto es coherente con cómo está construida la plataforma hoy: catálogo reactivo por
situación, motor de reglas separado del registro de datos, sistema de riesgo para
priorizar revisión humana, y ningún cálculo se marca como definitivo sin poder ser
recalculado/revisado.

---

## 1-5. Repaso de lo ya respondido (con cruces relevantes)

No se repite todo, solo lo que conviene tener presente al comparar con la plataforma:

- **1.3** (error más frecuente: disputas sobre qué documento entregó el cliente) es la
  motivación de negocio real detrás de tener un registro auditable de qué se pidió, qué se
  recibió y cuándo — algo que la plataforma ya cubre parcialmente vía `historial_cambios`,
  pero que **no se comunica hoy al cliente** (no hay una confirmación tipo "recibimos tu
  W-2 el 12/02"). Es un candidato fuerte para evitar la disputa de raíz.
- **3.3** ya responde de facto la pregunta 6.7 ("¿puede continuar sin un documento?"): sí,
  se presenta y luego se enmienda con 1040X. Esto es una regla de negocio real y ya
  utilizable para diseñar el flujo de "declaración incompleta pero presentable".
- **5.1/5.2** ("Parcialmente... preguntando varias veces lo mismo") es, en la práctica, ya
  una respuesta a la pregunta abierta **15.3** ("¿qué no funciona como esperabas?"). Vale
  la pena no tratarla como pendiente: el problema concreto reportado es **repetición de
  preguntas ya respondidas**, lo cual apunta a un bug/gap de lectura de estado ya
  registrado (el agente/prompt debería consultar `consultar_pendientes_cliente` antes de
  volver a preguntar, y si ya lo hace, el problema puede estar en cómo interpreta esa
  respuesta o en el manejo de la conversación en n8n).
- **5.6** confirma explícitamente reglas condicionales que ya están modeladas como
  "documentos extra" reactivos: seguro de salud → 1095-A, independiente → 1099-NEC,
  cripto → extractos, dueño de casa → 1098. La plataforma ya soporta esta forma de
  trabajar (bucket `documentos_extra`); lo que falta validar es la **cobertura completa**
  de esas ramas en el prompt del agente, no el modelo de datos.

---

## 6. Documentos

**6.1 ¿Qué tipos de documentos puede recibir normalmente el despacho de un cliente?**
*Inferencia:* Con base en 4.1/4.3 y el catálogo ya construido: identificación y SSN/ITIN de
cada persona en la declaración, W-2, 1099-NEC, 1095-A (los tres "siempre relevantes"), y de
forma condicional: 1098 (hipoteca), 1098-T (educación), 1099-INT/DIV/B (inversiones),
extractos de cripto, K-1, SSA-1099, declaración del año anterior. Probablemente también
fotos/copias de documentos de identidad de dependientes cuando es la primera vez que
aparecen en una declaración (el cuestionario no lo menciona, pero es habitual y debería
confirmarse).

**6.2 ¿Cómo determina el preparador si un documento es válido?**
*Inferencia:* Revisión visual en persona: que el nombre y SSN coincidan con el cliente/
dependiente registrado, que sea del año fiscal correcto, que esté completo (todas las
páginas) y legible, y que el tipo de formulario corresponda a lo que se le pidió. Hoy esto
lo hace el preparador "a ojo" frente al cliente — la plataforma solo valida técnicamente
que el archivo se subió correctamente y el formato es aceptado, **no** que el contenido
coincida con el cliente o el año.

**6.3 ¿Qué ocurre cuando un cliente entrega un documento incorrecto?**
*Inferencia:* Hoy, ad hoc — al ser todo presencial, se detecta en el momento y se le pide
el correcto o se reprograma. No hay un proceso formal descrito; probablemente **"no lo
hemos definido todavía"** sea la respuesta honesta del dueño, y valdría la pena
preguntárselo directamente en vez de asumir más.

**6.4 ¿Qué ocurre con un documento duplicado?**
*Inferencia:* Al ser hoy proceso 100% presencial, probablemente no es un problema
frecuente (se descarta la copia extra en el momento). Se vuelve relevante **cuando se migre
más recolección a WhatsApp** (que es justo el escenario para el que ya existe detección de
duplicados por hash en la plataforma) — dato importante para la discusión de arquitectura
del agente.

**6.5 ¿Qué ocurre cuando un documento está incompleto o es ilegible?**
*Inferencia:* Se le pide al cliente que lo traiga de nuevo o consiga una copia mejor (p.ej.
reimprimir el W-2 desde el portal del empleador, pedir un transcript al IRS). No hay
evidencia de un flujo definido; recomendable preguntar directamente.

**6.6 ¿Qué documentos requieren obligatoriamente revisión humana?**
*Inferencia, con alta confianza:* todos los documentos de **ingreso** (W-2, 1099-NEC,
1099-MISC/K, K-1, 1099-B/DIV/INT) porque determinan directamente el monto de la
declaración (esto es literalmente lo que dice 4.6: "revisar bien los números... ahí se
refleja el ingreso total del año"). También 1095-A, porque su reconciliación con la prima
del Marketplace es una fuente común de errores en la industria.

**6.7 ¿Puede continuar sin un documento?**
Ya respondido indirectamente en 3.3: sí, con enmienda posterior (1040X).

---

## 7. Recolección mediante WhatsApp e IA

*Nota: esta sección se cruza directamente con el nuevo tema de migrar el agente de n8n —
ver la sección final de este documento.*

**7.1 ¿Qué debería poder hacer el cliente completamente por WhatsApp?**
*Inferencia:* Toda la recolección de información personal, determinación de qué formas
aplican, y envío de documentos (fotos/PDF) — que es exactamente el cuello de botella
señalado en 1.2. **No** parece que deba incluir agendar cita (hoy es telefónico y el dueño
no lo marcó como problema prioritario), ni la explicación final de resultados ni la entrega
del folder (ambas descritas como presenciales en 2.1).

**7.2 ¿Qué NO debe hacer el agente de IA?**
*Inferencia:* No debe inventar o estimar montos que no vengan de un documento o de una
respuesta explícita del cliente (esto ya es una regla explícita del prompt actual — "grounding
estricto"). No debe dar asesoría fiscal, prometer montos de reembolso, decidir filing status
o elegibilidad de dependientes (eso es decisión del preparador con el motor de reglas), ni
compartir información de un cliente con otro.

**7.3 ¿En qué momentos debe intervenir un empleado?**
*Inferencia:* Cuando hay ambigüedad de tipo de entidad/negocio (ya contemplado en el
prompt actual), cuando el cliente da información contradictoria (ver 7.4), cuando un
documento se marca inválido más de una vez, y en cualquier caso que el sistema de riesgo
(sección 10) marque como alto.

**7.4 ¿Qué debería ocurrir si el cliente da información contradictoria?**
**Esto es una decisión de negocio real y pendiente, no solo inferible** — está identificada
como pendiente también en `docs/plan-desarrollo-fases.md`. Hoy el sistema simplemente
sobreescribe el valor anterior sin detectar la contradicción. Propuesta razonable a validar
con el dueño: si la contradicción es entre lo que dice el cliente y un documento ya
recibido, **debería prevalecer el documento** (es evidencia), salvo que el cliente aporte un
documento corregido/más reciente. Si es entre dos respuestas verbales, la más reciente
debería marcar el caso para confirmación humana en vez de sobreescribir en silencio.

**7.5 ¿Qué pasa si el cliente cambia una respuesta anterior?**
*Inferencia:* Debería permitirse (es normal que un cliente recuerde algo distinto), pero
quedando registro completo de la corrección — esto ya existe (`historial_cambios`).

**7.6 ¿Qué pasa si el agente no puede determinar qué información necesita?**
*Inferencia:* Debería derivar el caso a un humano en vez de adivinar o dejar de preguntar
— hoy no hay un mecanismo explícito de "escalamiento", solo el registro de la conversación
para revisión posterior.

---

## 8. Preparadores y revisión humana

**8.1 ¿Qué puede modificar un preparador?**
Ya reflejado en el sistema actual: valores de campos de sus propios clientes, marcar
documentos/formas como revisadas, y el nivel de riesgo. No puede tocar catálogo ni
usuarios (eso es exclusivo de admin). *Confirmar con el dueño si este reparto de permisos
coincide con la realidad operativa (p.ej., si un preparador senior debería poder ver/ayudar
en casos de otro preparador).*

**8.2 ¿Qué nunca debería modificarse sin dejar registro?**
*Inferencia:* Cualquier dato que afecte el monto de la declaración (ingresos, dependientes,
estado civil) y el nivel de riesgo. Hoy, en la práctica, **todo** cambio de campo ya queda
auditado (`historial_cambios`), así que la respuesta real podría ser "todo, y ya se
cumple" — vale la pena confirmárselo al dueño como una fortaleza a conservar.

**8.3 ¿Qué decisiones son exclusivas del preparador?**
*Inferencia, con base en 3.2/3.3:* aprobar el resultado final antes de imprimir, decidir si
se presenta incompleta y se enmienda después, y clasificar casos ambiguos de dependiente
calificado o de tipo de entidad de negocio.

**8.4 ¿Qué resultados calculados deben revisarse antes de ser definitivos?**
*Inferencia:* El monto final de pago/devolución en todos los casos (4.6), con énfasis
adicional en: QBI en ingresos altos (el propio motor ya se auto-marca `requiere_revision_manual`
en ese caso), cualquier caso con autoempleo, y la reconciliación del 1095-A.

**8.5 ¿Situaciones que deberían requerir revisión especial?**
*Inferencia:* Alto riesgo (sección 10), más de una forma de negocio, primer año de un
cliente nuevo, dependientes que podrían estar siendo reclamados por otro contribuyente
(custodia compartida), ingresos de cripto, ITIN en vez de SSN.

---

## 9. Cálculo y determinación fiscal

**9.1 ¿El resultado coincide con lo que espera Global Tax Services?**
**No es inferible — requiere validación directa.** Nadie fuera del despacho puede
confirmar esto sin comparar casos reales calculados por la plataforma contra el resultado
real en TaxWise. Es, además, la pregunta más importante de toda la sección 9 y no debería
quedar sin respuesta: recomendaría correr un lote de casos históricos ya presentados y
comparar salida por salida antes de confiar el cálculo a producción.

**9.2 / 9.4 ¿Qué debe revisarse siempre / qué genera alerta?**
Ver 8.4/8.5 — mismas situaciones.

**9.5 ¿Hay reglas importantes que no estén contempladas?**
*Hallazgo importante, no solo inferencia:* revisando el motor de reglas actual, detecto dos
ausencias que probablemente **sí importan** para este negocio en particular:

1. **Crédito por Ingreso del Trabajo (EITC)** no aparece entre las reglas implementadas.
   Es potencialmente uno de los créditos más relevantes precisamente para el perfil de
   "clientes pequeños" con dependientes que el dueño describe como el grueso del negocio.
2. **Tasa preferencial para ganancias de capital de largo plazo y dividendos calificados**
   no está implementada (el motor grava todo a tasas marginales ordinarias). El dueño
   mencionó explícitamente clientes con inversiones en bolsa/cripto (4.3, 5.6) — para esos
   clientes el cálculo actual **sobreestimaría el impuesto**, lo cual no es un detalle
   menor.

Estos dos puntos merecen confirmarse con el dueño y priorizarse antes de confiar el motor
para esos perfiles de cliente, sin importar qué tan bien funcione para W-2 simples.

---

## 10. Riesgo y casos especiales

**10.1 ¿Qué hace que un cliente sea de alto riesgo?**
*Inferencia:* Documentos inconsistentes o inválidos repetidos, múltiples fuentes de
ingreso/negocios, reclamos de EITC (estadísticamente el más auditado por el IRS), ITIN,
casos con historial de auditoría previa.

**10.2 ¿Qué debería generar alerta automática?**
*Inferencia:* Discrepancias entre lo declarado por el cliente y lo que dice un documento,
documentos pendientes por más de X días, cambios grandes de ingreso año a año. Ya
implementado de forma simple hoy (documento inválido → alto; forma en progreso o ≥3 formas
→ medio) — es un buen punto de partida pero no cubre discrepancia cliente-vs-documento
todavía.

**10.3 ¿Qué debería hacer el despacho ante un caso de alto riesgo?**
*Inferencia:* Revisión obligatoria por el preparador (o el dueño) antes de presentar,
posiblemente solicitando documentación de respaldo adicional.

**10.4 ¿Quién puede cambiar el nivel de riesgo manualmente?**
Ya implementado: preparador (sobre sus clientes) y admin. *Confirmar que este alcance es
correcto* — por ejemplo, si un preparador debería poder *bajar* el riesgo de un caso marcado
alto automáticamente, o si eso debería requerir aprobación del dueño/admin.

---

## 11. TaxWise

El dueño ya fue explícito: **"pendiente de definir con el proveedor"**. No es un vacío de
respuesta sino un bloqueador de negocio real y ya documentado como tal en el plan de fases.
No conviene inventar respuestas detalladas aquí más allá de una hipótesis de partida para
cuando se retome la conversación con el proveedor: probablemente el envío no debería ser
automático (requiere aprobación explícita del preparador antes de enviar), y el sistema
debería quedar en capacidad de detectar si TaxWise fue quien generó el resultado final o si
sigue habiendo digitación manual doble. Esto sigue siendo, en esencia, **"no lo hemos
definido todavía"** y debería tratarse así.

---

## 12. Administración del despacho

**12.1-12.3 (funciones de admin/preparador, qué puede consultar cada uno)**
Ya reflejado en el sistema: admin ve y administra todo (usuarios, catálogo, bitácora,
clientes); preparador ve y opera solo sobre sus clientes asignados. *Confirmar con el dueño
si esto coincide con la operación real del despacho.*

**12.4 ¿Puede un cliente ser atendido por más de un preparador?**
El modelo actual asume **un preparador por cliente** (`preparer_id` es 1 a 1). Esto es una
suposición ya incorporada al diseño, no solo una pregunta pendiente — vale la pena
confirmarla explícitamente, sobre todo pensando en temporada alta, donde más de un
empleado podría atender al mismo cliente.

**12.5 ¿Cómo se asignan hoy los clientes a los preparadores?**
*Inferencia:* Probablemente por continuidad informal (el mismo preparador que atendió al
cliente en años anteriores, reforzado por el hecho de que TaxWise ya trae al cliente
antiguo por su SSN) o por quien esté disponible el día de la cita — no parece haber una
regla de asignación formal.

**12.6 ¿Qué ocurre cuando un cliente cambia de preparador?**
*Inferencia:* No definido operativamente hoy; en la plataforma, el historial completo del
cliente permanecería visible para el nuevo preparador asignado.

---

## 13. Futuro del producto

**13.1 En 1 año / 13.2 en 3 años**
*Inferencia, directamente de 1.1 y 1.4:* en 1 año, automatizar de punta a punta el flujo de
clientes "sencillos" (solo W-2, con o sin 1095-A/1099-NEC), con intervención humana
reducida a una auditoría final; en 3 años, sostener un crecimiento de volumen de clientes
con la misma infraestructura y equipo — es literalmente el objetivo que el dueño enunció en
1.1 ("mayor volumen de clientes" con "misma infraestructura").

**13.3 ¿Exclusiva para Global Tax o para otros despachos?**
**No inferible con confianza — es una decisión estratégica, no operativa.** Todas las
respuestas del dueño están enmarcadas en las necesidades internas de su propio despacho,
sin ninguna mención a un producto multi-cliente. No debería asumirse ninguna de las dos
respuestas porque cambia decisiones de arquitectura (multi-tenant o no) — recomendaría
preguntarla directamente y de forma explícita.

**13.4 ¿Otros tipos de declaración además de las actuales?**
**Hallazgo relevante:** el catálogo ya construido soporta 10 tipos de formulario (incluyendo
Schedule C/E/F, 1065, 1120, 1120-S, 1041, 990, 1040-NR), mientras que todas las respuestas
del dueño en las secciones 1-5 hablan casi exclusivamente de declaraciones individuales
(W-2/1099-NEC/1095-A). Sin embargo, en 5.7 el propio dueño menciona "¿el negocio está con
una corporación?" como parte del razonamiento de un preparador — lo cual confirma que sí
manejan negocios/corporaciones, solo que no fue el foco de sus respuestas. Vale la pena
preguntar directamente qué proporción de clientes son negocios vs. personas naturales,
porque eso cambia bastante la prioridad de qué parte del catálogo pulir primero.

**13.5 / 13.6 (qué automatizar / funcionalidad no contemplada)**
*Inferencia, directa de 1.2:* agendamiento de citas (hoy 100% telefónico) y seguimiento de
casos (hoy "NULO") son los dos automatismos más obvios que el dueño no pidió explícitamente
en el cuestionario pero que él mismo describió como puntos débiles del proceso actual.

---

## 14. Prioridades (propuesta de borrador)

El dueño dejó esta sección en blanco. Con base en todo lo anterior, una propuesta de las 5
prioridades **más defendibles con lo que él mismo dijo**, para que la valide o la corrija:

1. **Automatizar de punta a punta la recolección de información/documentos de clientes
   sencillos** (mencionado 3 veces como el mayor consumidor de tiempo: 1.2, 1.4, 2.4).
2. **Un mecanismo confiable de solicitud y confirmación de documentos**, con evidencia de
   qué se pidió y qué se recibió, para eliminar la disputa "él dice que entregó algo que no
   tenemos" (1.3).
3. **Seguimiento de casos (hoy inexistente)** — mínimo viable: saber en qué estado está
   cada cliente sin tener que preguntarle al preparador.
4. **Validar el motor de cálculo contra resultados reales de TaxWise** antes de confiar en
   él para casos con inversiones/autoempleo — sección 9.1 no contestada es en sí misma una
   señal de que esto no se ha hecho todavía, y hay gaps concretos ya identificados (EITC,
   ganancias de capital preferenciales).
5. **Cerrar la definición de la integración con TaxWise** — bloqueador declarado en el
   propio cuestionario (sección 11).

**14.1/14.2 ¿Cuál es la más importante y por qué?**
*Inferencia:* la #1, porque es la única mencionada de forma repetida en tres secciones
distintas y conecta directamente con el objetivo de negocio explícito de 1.1: mismo equipo,
más clientes.

---

## 15. Preguntas abiertas

- **15.3** ya tiene, en la práctica, una respuesta parcial vía 5.1/5.2 (ver sección 0/1-5
  de este documento): el catálogo repite preguntas ya respondidas. Vale la pena tratarlo
  como un hallazgo a resolver, no como pregunta pendiente.
- **15.1, 15.2, 15.4, 15.5** son genuinamente abiertas y no deberían inferirse — dependen de
  matices operativos que solo el dueño conoce (qué parte del sistema actual valora, qué
  decisión manual de un preparador querría que el sistema entendiera algún día). Mejor
  preguntarlas directamente en una conversación de seguimiento corta.

---

## Resumen de hallazgos que merecen atención antes de seguir desarrollando

1. **EITC y tasa preferencial de ganancias de capital no están en el motor de reglas**,
   pero el perfil de cliente descrito por el dueño sugiere que ambos importan.
2. **No hay regla de precedencia cliente-vs-documento** ante contradicciones — ya
   identificado como pendiente en el plan de fases, pero vale la pena resolverlo antes de
   escalar el volumen de conversación por WhatsApp.
3. **El catálogo ya soporta más tipos de entidad (negocios/corporaciones) de lo que el
   cuestionario deja ver** — confirmar qué proporción real de clientes lo necesita.
4. **9.1 (¿el cálculo coincide con lo esperado?) sigue sin respuesta** y es la pregunta más
   crítica de todo el cuestionario para poder confiar el motor de reglas a producción.
5. **13.3 (¿producto exclusivo o multi-despacho?)** es una decisión estratégica que cambia
   arquitectura y no debería asumirse en ninguna dirección.
