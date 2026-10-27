# Revisión del documento de análisis y diseño

27 oct. 2026 · Tarea Wipli (Cupperlab) "3. Steven: revisar el documento de análisis y diseño"

> **Documento revisado:** "Florida Tax Intake Automation — Análisis legal y modelo de automatización para firmas contables independientes" (Juan Pablo, 19 oct. 2026, 37 páginas). Copia local: `docs/mejora-plataforma/modelo-para-firmas-contables-independientes.pdf`.
>
> **Cómo se revisa:** por cada bloque, qué dice el documento y la evaluación frente a GTS y a la plataforma actual (`taxes-eeuu`):
> - ✅ **Aplica / ya lo tenemos**
> - ❌ **No aplica a GTS** (o no para el piloto)
> - ➕ **Falta** en la plataforma
> - ❓ **Hay que validar** (con GTS, con un CPA o abogado, o con Juan Pablo)
>
> Se cruza con `docs/mejora-plataforma/que-tiene-gts-hoy.md` (validación de la plataforma, tarea 2).

| # | Bloque del checklist | Estado |
| --- | --- | --- |
| 1 | Secciones 1–5: marco legal, clasificación, matriz de obligaciones y requisitos de la firma | ✅ Revisado |
| 2 | Secciones 6–9: proceso, automatización por segmento, documentos y arquitectura | ✅ Revisado |
| 3 | Secciones 10–12: KPIs, roadmap, piloto y módulo F1 | ✅ Revisado (sin sección 12: no está en el PDF) |
| 4 | Puntos legales pendientes de validar (§7216, FTC Safeguards, TPP) y quién los valida | ✅ Revisado |

---

## Idea general del documento

- Propone un **servicio en la nube para muchas firmas contables de Florida** (no solo GTS): convierte documentos y datos del cliente en un **expediente validado** listo para cargar en el software fiscal de cada firma (TaxWise en el caso de GTS).
- **No** calcula ni presenta la declaración: el valor está en la captura, la validación y el control.
- Clasifica a cada cliente en dos ejes: **tipo de sujeto fiscal** (qué declara) × **nivel de formalidad** (cómo entrega la información).
- **MVP propuesto:** 1040 de residentes de Florida **+ 1120/F-1120 de C corporations**. Sales tax (DR-15) y nómina (RT-6) en fase 2.
- El propio documento aclara que **no es una opinión legal**: lo normativo debe validarlo un CPA o abogado de Florida.

**Evaluación general**
- ✅ El enfoque "capturar y validar, no reemplazar TaxWise" coincide con lo que es hoy la plataforma.
- ❓ **Choque de alcance:** el documento mete C corporations en el MVP; en la tarea 2 propusimos quitar las formas de compañías porque el alcance es persona natural. **Decisión para la reunión del 30.**
- ❓ El documento piensa en **multi-firma** (multi-tenant). La plataforma hoy es **de una sola firma** (GTS). Pasar a multi-firma es un cambio grande de arquitectura; hay que decidir si es objetivo del piloto o de después.

### 📝 Observaciones de Steven sobre el alcance (27 oct.)

**1. Alcance: solo personas naturales.**
> "Por el momento debemos enfocarnos únicamente en personas naturales (empleado)."

- Se descartan del piloto las C corporations y demás empresas que el documento pone en el MVP.
- ✅ Precisado por Steven: **persona natural incluye a los independientes con Schedule C** (PN-1 asalariados y PN-2 negocio propio). Es coherente con la demo F1 de Juan Pablo, que es de Schedule C.

**2. Multi-firma: ¿por qué sería un cambio grande?**
> "La funcionalidad de la plataforma sería la misma para todas las firmas, lo único que cambiaría sería el nombre de las firmas."

De acuerdo en que **las funciones serían las mismas** (portal, formulario, extracción, revisión). Lo que cambia no es *qué hace* la plataforma, sino que **cada dato pasa a tener dueño**. Hoy no existe el concepto de "firma" en ninguna tabla. Lo que habría que tocar:

| Qué | Hoy | Con varias firmas |
| --- | --- | --- |
| **Aislamiento de datos** | Ninguna tabla tiene firma; el admin ve todos los clientes | Cada cliente, documento, campo, mensaje y bitácora lleva su firma, y **cada consulta** debe filtrar por ella. Un solo filtro olvidado muestra SSN de una firma a otra (violación de §7216) |
| **Usuarios y roles** | Admin, preparador, cliente, globales | Admin de la plataforma vs. admin de cada firma; un preparador pertenece a una firma |
| **WhatsApp** | Un solo número de Twilio | ¿Un número por firma o uno compartido? Si es compartido, hay que saber a qué firma pertenece quien escribe |
| **Catálogo, prompts y agente** | Uno solo, armado con el cuestionario de GTS | Cada firma tiene su propio intake, tono y reglas; o se comparte una base y cada firma ajusta |
| **Marca** | Logo y textos de GTS en el código | Logo, nombre, colores, correos y dominio por firma |
| **Almacenamiento y cifrado** | Un disco, una llave | El documento pide **cifrado por firma**; carpetas o buckets separados |
| **Cumplimiento** | Un WISP, un consentimiento | Cada firma es responsable ante el IRS de sus clientes: consentimientos, retención y WISP por firma |
| **Negocio** | — | Alta de firmas, planes y cobro |

**Propuesta:** mantener **una sola firma en el piloto**, pero sin cerrar la puerta. Hacerlo después es más caro porque hay que migrar los datos existentes. Mientras tanto se puede:
- no seguir escribiendo "GTS" en el código: nombre, logo y textos van en configuración;
- dejar decidido desde ya si el WhatsApp será un número por firma;
- evaluar agregar el campo `firma_id` temprano, mientras hay pocos datos.

✅ **Decidido por Steven:** primero se prueba la plataforma con **una sola firma (GTS)**. Cuando el proceso esté validado y correcto, se piensa en más firmas.

**3. El IRS es la fuente de verdad; el 1040 es el formulario "front".**
> "Para todos los formularios la fuente de la verdad es el IRS: ahí están todos los formularios que se le deben pedir a las personas dependiendo de qué cumplen o no cumplen. El 1040 podemos llamarlo el formulario front, ya que contiene toda la información de los demás; es como la carta de presentación de la declaración. Los otros formularios son fuente de ayuda para llenar el principal, que es el 1040."

- Qué formularios y documentos se piden lo deciden **las condiciones del IRS** (instrucciones del 1040 y de cada schedule), no un criterio propio.
- **El 1040 siempre** es la base. Schedules (C, E, SE, 1, 2, 3, H…) y documentos fuente (W-2, 1099, 1098, 1095-A…) **alimentan** el 1040.
- 🐞 Hoy la plataforma trata `schedule_c`, `schedule_e` y `schedule_f` como formas al mismo nivel que el 1040; por eso a un independiente puro no se le asigna el 1040 (tarea 2, 3.2.1). Este principio es la regla para corregirlo.

**4. Lo primero que hace la plataforma es entender al cliente.**
> "La primera acción que se quiere de la plataforma es entender al cliente o al usuario, para saber qué tipo de preguntas se le harán, y con eso pedir y llenar los formularios y schedules correctos para poder entregar un buen 1040."

El flujo que se espera es **entender → preguntar → pedir → llenar → 1040**:
1. **Entender:** preguntas de perfil (estado civil, dependientes, empleo, negocio propio…).
2. **Preguntar más según lo que responde:** cada respuesta abre o descarta preguntas y anexos, siguiendo las condiciones del IRS. Ejemplos de [`ejemplo-recoleccion-empleado-con-dependiente.md`](ejemplo-recoleccion-empleado-con-dependiente.md):
   - Hijo de 8 años → aplica el Form 2441 si pagó guardería. Con 15 años no aplica, pero sigue el Schedule 8812.
   - La guardería la pagó a una niñera en su casa → el cliente es empleador → Schedule H.
   - El W-2 trae la casilla 10 (beneficio de cuidado) → va a la Parte III del Form 2441.
3. **Pedir** solo los documentos y datos que soportan los anexos activados. Lo que viene en un documento se lee del documento, no se le pregunta al cliente.
4. **Llenar:** los anexos los llena el preparador (o la plataforma), no el cliente.
5. **Entregar** un 1040 completo y correcto.

🐞 Hoy la plataforma hace casi lo contrario: el portal muestra una lista fija de campos por forma. Solo 7 dependencias entre preguntas están programadas a mano, y los números de ingresos se le piden al cliente aunque ya estén en el W-2 (tarea 2, secciones 4 y 5).

---

## 1. Secciones 1–5

### Sección 1 — Marco federal

**Qué dice:** como Florida no cobra impuesto a la renta personal, la declaración federal (1040) es todo el trabajo de una persona. Lista formularios y vencimientos por tipo de contribuyente, y los cambios de la ley OBBBA (julio 2025) que agregan datos a capturar en el 1040 de 2025 a 2028.

| Punto | Evaluación |
| --- | --- |
| 1040 + schedules como núcleo de la persona natural | ✅ Confirma el defecto de la tarea 2 (3.2.1): el 1040 debe ser **siempre** la base; hoy a un independiente puro no se le asigna |
| **Propinas** (deducción hasta USD 25.000, depende de la ocupación) y **horas extra** (hasta 12.500/25.000, requiere desglose del empleador) | ✅ Parcial: existen `beneficios_2025.propinas_reportadas` / `horas_extra_pagadas` y `ocupacion`. ➕ Falta pedir el desglose de horas extra del empleador y saber si la ocupación califica para propinas |
| **Tope SALT a USD 40.000**: en Florida pesa el impuesto predial | ✅ `deducciones.impuestos_propiedad` existe (aunque hoy se pide como monto; ver tarea 2, 4.2.2) |
| **1099-K vuelve a USD 20.000 y 200 transacciones** | ➕ Refuerza lo de la tarea 2 (3.2.5 y 4.2.4): muchos ingresos por Zelle/Venmo **no** llegarán con 1099-K, así que hay que preguntar por ingresos sin 1099 |
| 1099-NEC sube a USD 2.000 desde pagos de 2026 | ❌ No aplica al año fiscal 2025 del piloto; sí para 2026 (parámetro por año) |
| **Transcripciones del IRS** (Wage & Income) con Form 8821 para conciliar W-2/1099 | ➕ No existe en la plataforma. Muy útil: detecta documentos que el cliente olvidó. ❓ Requiere que GTS tenga acceso a e-Services y el 8821 firmado por cada cliente |
| Cifras OBBBA tomadas de fuentes secundarias | ❓ El documento lo dice: validar contra la ley y la guía del IRS. Revisar también `ParametrosFiscalesSeeder` de la plataforma |

### Sección 2 — Marco legal de Florida

**Qué dice:** Florida cobra por consumo (sales tax), nómina (reemployment tax) y propiedad, no por renta personal. Describe el impuesto corporativo (F-1120, 5,5 %), la ley HB 7031 de 2026 (Florida se separa de parte de la OBBBA para empresas), sales tax (DR-15), reemployment tax (RT-6), bienes muebles de negocio (TPP, DR-405) y otras obligaciones (Annual Report en Sunbiz, documentary stamp, licencia local).

| Punto | Evaluación |
| --- | --- |
| Sin impuesto a la renta personal | ✅ Para GTS (personas) no hay declaración estatal de renta |
| **Declaration of Domicile**: clientes que se mudan a Florida desde estados con impuesto a la renta | ➕ La plataforma solo pregunta "¿vivió o trabajó **fuera de EE. UU.**?". Falta "¿vivió, trabajó o tuvo ingresos **en otro estado**?" (puede haber declaración estatal de residente parcial en el otro estado) |
| Homestead y predial | ✅ Alimenta la deducción SALT (ver sección 1) |
| F-1120, HB 7031, doble base de activos, F-1120X | ❌ Solo empresas. Fuera del alcance de persona natural |
| **DR-15 (sales tax)** y **DR-405 (TPP)** | ❓ Pueden tocar a un **independiente con Schedule C** que vende bienes gravados o tiene equipos. Hay que preguntarle a GTS si hoy le hace eso a sus clientes. Si sí, mínimo una pregunta de "alerta" en el intake; si no, ❌ |
| RT-6, Annual Report Sunbiz, documentary stamp, licencias | ❌ Empresas o empleadores; fuera del piloto |
| Tourist tax del condado por alquiler vacacional | ❓ Igual que DR-15: aplica a quien alquila por Airbnb. La plataforma ni siquiera pregunta por Airbnb (tarea 2, 4.2.4) |

### Sección 3 — Clasificación del sujeto fiscal

**Qué dice:** lo primero es **clasificar** al cliente antes de pedir documentos, en dos ejes:
- **Tipo de sujeto (9 códigos):** PN-1 asalariado/inversiones, PN-2 negocio propio (Schedule C), PN-3 rentas (Schedule E), PN-4 no residente; PJ-1 a PJ-5 empresas.
- **Formalidad (F1 a F4):** F1 informal (fotos, extractos, efectivo), F2 básico (QuickBooks), F3 con contador, F4 corporativo. Se calcula con un **puntaje 0–100** (usa software contable, cuentas separadas, concilia, % en efectivo…).
- Se clasifica con señales automáticas (Sunbiz, IRS, declaración anterior), un cuestionario corto, relaciones entre sujetos (persona y sus empresas) y reclasificación anual.
- El segmento decide la lista de documentos, el canal, las reglas, el equipo y las horas.

| Punto | Evaluación |
| --- | --- |
| **Eje "tipo de sujeto"** | ✅ La plataforma tiene algo parecido: el árbol de determinación de formas por WhatsApp (pasos A a C). 🐞 Pero clasifica por **formas**, no por **persona** (tarea 2, 3.2.2). ➕ Los códigos PN-1 a PN-3 son justo el "perfil de ingresos" que propusimos (tarea 2, 3.4.2) |
| PN-1, PN-2, PN-3 | ✅ Es el corazón de GTS |
| **PN-4 no residente** ("muy común en Florida") | ❓ En la tarea 2 propusimos quitar el 1040-NR. El documento dice lo contrario. Preguntar a GTS cuántos no residentes atiende |
| PJ-1 a PJ-5 | ❌ Empresas, fuera del piloto (salvo que la reunión decida lo contrario) |
| **Eje "formalidad"** | ➕ No existe en la plataforma. Para personas naturales casi todo es **F1 o F2**. Las preguntas que alimentan el puntaje (efectivo, cuentas separadas, software contable) son las mismas "preguntas de ingresos informales" que faltan (tarea 2, 3.4.3) |
| Puntaje 0–100 calibrado | ❓ Para el piloto basta con 3–5 preguntas y una regla simple (F1/F2). El puntaje completo es para después |
| Señales automáticas (Sunbiz, transcripciones IRS) | ❌ Sunbiz no aplica a personas. Transcripciones: ver sección 1 |
| Persona + sus empresas como grupo | ❌ Fuera del piloto |
| **Reclasificación anual y por eventos** | ➕ La plataforma no tiene "cliente recurrente" ni enlace con el año anterior (tarea 2, 3.2.6) |
| **Excepción bloqueante** si lo declarado contradice la evidencia | ➕ No existe. Hoy hay nivel de riesgo, pero nada bloquea ni obliga al preparador a resolver |

### Sección 4 — Matriz de obligaciones

**Qué dice:** con el segmento y 10–15 preguntas de onboarding, el sistema **activa las obligaciones** (federales, estatales, de condado) y arma la lista de documentos. Incluye un calendario base de vencimientos y pide que las fechas se calculen por cliente (feriados, prórrogas por huracán).

| Punto | Evaluación |
| --- | --- |
| Persona residente → 1040 | ✅ |
| Persona con Schedule C → 1040 + C/SE (+ DR-15, DR-405 si aplica) | ✅ 1040 + C (con el defecto del 1040 ya señalado). ❓ DR-15/DR-405 según lo que responda GTS (sección 2) |
| Persona con rentas → 1040 + E (+ DR-15 y tourist tax si es corto plazo) | ✅ Schedule E existe. ➕ Falta la pregunta de alquiler de corto plazo |
| **Empleador doméstico → Schedule H** | ➕ Coincide con la tarea 2 (4.2.4): falta "¿tuvo empleados domésticos?" |
| Partnership, S corp, C corp, exenta | ❌ Fuera del piloto |
| "Las obligaciones activan la lista de documentos" | ✅ Es la misma idea que el modelo "pregunta → documento" y el checklist que propusimos (tarea 2, 4.4.3 y 4.4.5) |
| **Calendario por cliente** con prórrogas por desastre | ➕ La plataforma no maneja vencimientos. Para personas basta: 15 de abril, prórroga con 4868 (hay campo `pago_con_extension`) y prórrogas por huracán por condado. Va junto a los "estados del caso con fecha" (tarea 2, 5.4.1) |

### Sección 5 — Requisitos regulatorios de la firma

**Qué dice:** reglas que no cambian qué se declara, pero sí **dónde se guardan los datos, quién los ve y qué evidencia queda**. Son requisitos "desde el día uno".

| Norma | Qué exige (en simple) | Evaluación frente a la plataforma |
| --- | --- | --- |
| **IRC §7216** | No se puede usar ni revelar información de una declaración sin **consentimiento firmado** del cliente. Revelarla a **personas fuera de EE. UU.** (incluido el SSN) exige un consentimiento específico | ➕ **No hay ningún consentimiento §7216** en la plataforma; la única firma es la atestación (y solo por WhatsApp, tarea 2, 1.2.2). ❓ Dos puntos delicados: (1) los datos y documentos se envían a **OpenAI** para la extracción y el agente; (2) quién tiene acceso a producción y desde qué país (equipo de desarrollo y soporte). Ambos pueden requerir consentimiento |
| **Circular 230** | Diligencia debida; evidencia de revisión por un profesional; "maker-checker" (uno prepara, otro revisa) | ✅ Parcial: `marcarRevisado` guarda quién y cuándo revisó. 🐞 Una sola persona, sin segundo revisor, y la revisión no se reabre si algo cambia (tarea 2, 5.2.3) |
| **PTIN, EFIN, Form 8879** | Credenciales del preparador y firma electrónica del cliente para presentar | ❓ Lo cubre TaxWise hoy. ➕ Si se quiere que el cliente firme el 8879 en el portal, no existe |
| **IRC §6107** | Guardar copia o listado de declaraciones preparadas **3 años** | 🐞 **La plataforma permite borrar un cliente con todos sus archivos** (`ClienteController::destroy`) y borrar campos. No hay política de retención ni archivo inmutable |
| **FTC Safeguards Rule + Pub. 4557** | **Programa escrito de seguridad (WISP)**, responsable, evaluación de riesgos, cifrado, MFA, respuesta a incidentes | ✅ Valores de los campos, historial y cálculos **cifrados** en la base (`encrypted:array`). ✅ Bitácora de accesos y registro de "revelar" datos sensibles (`CampoReveal`). ✅ Existen 2FA y passkeys. 🐞 **El 2FA no es obligatorio** (no hay middleware que lo exija). 🐞 **Los archivos (W-2, 1099…) se guardan sin cifrar** en el disco de un servidor compartido y sin respaldo (tarea 2, 1.2.7). ➕ No hay WISP ni plan de respuesta a incidentes |
| **Florida Information Protection Act** | Avisar a los afectados por una brecha en 30 días (y al Fiscal General si son 500 o más) | ➕ Requiere saber qué datos personales hay de cada cliente: se puede sacar del catálogo (`sensible`), pero no hay procedimiento |
| **Poderes 2848 / 8821** | Necesarios para pedir transcripciones y atender avisos del IRS | ➕ No existe repositorio de poderes. Solo hace falta si se usan transcripciones (sección 1) |
| Licencias y credenciales | Registro de la firma y de cada preparador | ❓ Para una sola firma (GTS) es un dato administrativo, no una función del piloto |
| **Retención de registros** (3, 4, 5 y 7 años) | Política preconfigurable | ➕ No existe (ver §6107) |
| **Decisión de arquitectura:** datos en nube de EE. UU., aislados por firma, SSN y EIN **tokenizados**, consentimiento §7216 antes de dar acceso a personal fuera de EE. UU. | | ❓ **¿Dónde está el servidor de producción?** (Dokploy, compartido con otros proyectos). Hay que confirmarlo. ✅ El SSN se cifra y se revela con registro, que se parece a lo pedido. ❌ El aislamiento por firma no aplica mientras sea una sola firma |

### Resumen del bloque 1

**Lo que el documento confirma de la tarea 2**
- El 1040 siempre como base.
- Preguntar por ingresos sin 1099 (más ahora con el 1099-K a USD 20.000).
- Empleados domésticos (Schedule H) y alquiler de corto plazo.
- Estados del caso con fechas y vencimientos.
- Clasificar por persona, no por forma.

**Lo nuevo que aporta**
1. **Cumplimiento de la firma (sección 5):** es lo más serio. La plataforma ya tiene datos reales de clientes y le falta:
   - consentimiento §7216, sobre todo por el uso de OpenAI y el acceso desde fuera de EE. UU.;
   - retención de 3 años (hoy se puede borrar todo);
   - 2FA obligatorio y archivos cifrados con respaldo;
   - un WISP.
2. **Eje de formalidad (F1/F2):** útil y barato si se reduce a unas pocas preguntas.
3. **Transcripciones del IRS** para conciliar documentos.
4. **Pregunta de "otro estado"** (mudanzas a Florida).

**Lo que no aplica al piloto de GTS:** todo lo de empresas (F-1120, HB 7031, RT-6, Sunbiz, PJ-1 a PJ-5, grupos de empresas) y la multi-firma.

**Para decidir o validar**
- ~~¿El MVP incluye C corporations?~~ Decidido por Steven: solo persona natural, incluidos los independientes con Schedule C.
- ¿GTS atiende no residentes (1040-NR)?
- ¿GTS maneja sales tax (DR-15) y TPP (DR-405) de sus independientes?
- ¿Dónde está alojado el servidor de producción?
- ¿El uso de OpenAI y el acceso del equipo de desarrollo requieren consentimiento §7216?

---

## 2. Secciones 6–9

### Sección 6 — Proceso de captura (12 pasos)

**Qué dice:** el proceso tiene 12 pasos y la plataforma ejecuta siete sola. El humano interviene en tres puntos: resolver excepciones, la revisión profesional (Circular 230) y la firma del cliente. Un documento que pasa las reglas va directo al expediente. Uno que falla entra a una cola de excepciones y, si falta información, vuelve al cliente como consulta en el portal.

| Paso | Evaluación frente a la plataforma |
| --- | --- |
| 1. **Onboarding:** conflictos, carta de encargo, consentimiento §7216, poderes (8821/2848, DR-835) | ✅ Verificación de cuenta y atestación. ➕ Faltan el consentimiento §7216 y la carta de encargo (bloque 1). ❌ DR-835 es un poder de Florida para impuestos de empresa. El 8821 solo hace falta si se usan transcripciones |
| 2. **Clasificación y perfil:** segmento + cuestionario corto + precarga del año anterior | ✅ Es el paso de **"entender al cliente"** (observación 4). Hoy lo cubre el árbol de determinación de formas por WhatsApp. 🐞 Clasifica por forma, no por persona. ➕ No hay precarga del año anterior |
| 3. **Lista PBC** (documentos por cliente, con fecha límite) | 🟡 Existe una lista de campos y documentos por forma. 🐞 Es **fija por forma**, no se arma según las respuestas. ➕ Sin fechas límite |
| 4. **Recepción:** portal, correo por cliente, conectores, transcripciones IRS | ✅ Portal web y WhatsApp (con traspaso al portal). ➕ Transcripciones IRS. ❌ Correo por cliente y conectores (nómina, brókers) no hacen falta en el piloto |
| 5. **Clasificar y extraer** con confianza y evidencia (página y coordenadas) | ✅ Extracción con OpenAI (texto del PDF o visión), y el W-2 "revela" sus campos (salarios, retenciones) sin que el cliente los escriba. 🐞 Sin nivel de confianza ni evidencia. Si la extracción falla, los campos quedan pendientes y el cliente los escribe a mano, sin aviso (tarea 2, 5.2.6) |
| 6. **Validar** con reglas; baja confianza → revisión | 🐞 Solo se valida la extensión del archivo. No hay reglas sobre el contenido (sección 8) |
| 7. **Excepciones:** el analista corrige, justifica o consulta al cliente | 🐞 No hay cola de excepciones ni ciclo de corrección con el cliente (tarea 2, 5.2.4). Existe un nivel de riesgo, pero no bloquea nada |
| 8. **Expediente** congelado, versionado y con trazabilidad campo → documento | ✅ `HistorialCambio` guarda quién cambió qué. 🐞 No se congela: la revisión no se reabre si algo cambia (tarea 2, 5.2.3). ➕ No hay enlace campo → página del documento |
| 9. **Carga** al software de preparación | 🐞 La exportación (zip con `campos.json`) no sirve para TaxWise. Hoy el preparador digita a mano (tarea 2, 5.2.2) |
| 10. **Revisión** con checklist y comparación con el año anterior | ✅ `marcarRevisado`. ➕ Faltan el checklist y la comparación con el año anterior |
| 11. **Firma y e-file** (8879) | ❌ Para el piloto sigue en TaxWise. ❓ ¿GTS quiere que el cliente firme el 8879 en el portal? |
| 12. **Archivo:** acuses, arrastres al año siguiente, retención | ➕ No existe: no hay arrastres (pérdidas del Schedule C, pérdidas de capital) ni política de retención (bloque 1, §6107) |
| **Matriz RACI** (cliente, plataforma, asistente, preparador, dueño) | ✅ Útil para definir con GTS quién hace qué. Hoy hay 3 roles (cliente, preparador, administrador); falta saber si GTS tiene "asistente" y si preparador y dueño son la misma persona |

### Sección 7 — Automatización según el tipo de cliente

**Qué dice:** no tratar a todos los clientes igual; cada segmento tiene su "receta" de captura. Cinco principios, una receta por nivel de formalidad (F1 a F4), automatizaciones por tipo de sujeto, qué comprar y qué construir, y por dónde empezar.

**7.1 Cinco principios**

| Principio | Evaluación |
| --- | --- |
| 1. **Ir a la fuente antes que al documento:** API → transcripción IRS → plantilla → documento → entrevista | ✅ Es lo mismo que "lo que viene en el W-2 no se le pregunta al cliente". La plataforma lo hace a medias con la "revelación" del W-2 |
| 2. **Comprar lo resuelto, construir lo diferencial** | ❓ Hoy la extracción se construyó con OpenAI. Comprar un lector de W-2/1099 (Azure Document Intelligence, etc.) daría confianza por campo. Decisión técnica a evaluar |
| 3. **Pedir solo lo que el segmento necesita** ("nunca un cuestionario de 200 preguntas") | ✅ Es la **observación 4** de Steven. 🐞 Hoy el portal muestra una lista fija por forma |
| 4. **Automatizar por excepción:** el humano ve solo lo que falló | ➕ Requiere reglas y confianza (pasos 5–7) |
| 5. **Aprender cada año:** el segundo año cuesta menos | ➕ No hay cliente recurrente ni precarga (tarea 2, 3.2.6) |

**7.2 a 7.6 Recetas, tipos de sujeto, comprar o construir, adopción**

| Punto | Evaluación |
| --- | --- |
| **F1 informal:** extractos bancarios, fotos, IA que categoriza y arma un P&L | ❓ Es el caso típico del **independiente con Schedule C** que no lleva contabilidad. Muy valioso, pero grande. Preguntar a GTS cómo arma hoy el Schedule C de esos clientes |
| **F2 básico:** conexión a QuickBooks Online como conector central | ❓ El documento lo pone en el centro pensando en empresas. Para personas, preguntar a GTS cuántos de sus Schedule C usan QuickBooks antes de construirlo |
| F3 y F4 (balance de comprobación, ERP) | ❌ Empresas; fuera del piloto |
| **PN-1:** la transcripción Wage & Income precarga W-2 y 1099; el cliente solo confirma | ➕ Lo de mayor impacto para asalariados. Depende del 8821 y del acceso de GTS a e-Services (bloque 1) |
| **PN-2:** P&L desde extractos o QuickBooks, cruce con 1099-NEC/K | ➕ Ver F1 y F2 |
| **PN-3:** ficha por inmueble que se arrastra cada año (base, depreciación, días de alquiler) | ➕ Buena idea; hoy no hay arrastre entre años |
| PN-4 no residente | ❓ Pendiente de GTS (bloque 1) |
| PJ-1 a PJ-5 | ❌ Fuera del piloto |
| **Comprar o construir (7.4):** lectura de W-2/1099, conectores, Plaid, firma del 8879 con DocuSign | ❓ Hoy todo se construyó (OpenAI). El documento propone **Claude vía AWS Bedrock o Vertex en región EE. UU.** por §7216; la plataforma usa la API de OpenAI. Validar retención de datos y región (bloque 4) |
| **Por dónde empezar (7.5):** PN-1 primero; F1 en la segunda mitad | ✅ PN-1 primero coincide con el piloto. ❌ PJ-3/PJ-4 con QuickBooks queda fuera. ❓ F1 (Schedule C informal) es justo lo que más horas le cuesta a GTS |
| **Adopción (7.6):** marca blanca, bilingüe, importar clientes, precio | ✅ Portal en español e inglés. ❌ Marca blanca y precio no aplican con una sola firma (pero no seguir escribiendo "GTS" en el código). ➕ Importar la lista de clientes de GTS (CSV) puede servir para arrancar la temporada |

### Sección 8 — Documentos y reglas de validación

**Qué dice:** cada documento se clasifica, se extrae a campos y pasa por reglas antes de que lo vea un humano. Lista las familias de documentos con sus campos críticos y 8 reglas de ejemplo. Cada regla tiene código, año, severidad y referencia normativa, para que el equipo de impuestos las mantenga sin pasar por desarrollo.

| Punto | Evaluación |
| --- | --- |
| **W-2** (casillas 1, 2, 3, 5, 12 y 14), **1099** (NEC, MISC, K, INT, DIV, B, R, G, SSA), **1098 / 1098-T / 1098-E / 1095-A**, predial, donaciones | ✅ Casi todos están en el catálogo. ➕ Al W-2 le falta la **casilla 10** (beneficio de cuidado → Form 2441; ver el ejemplo de recolección) |
| K-1 (de sociedades o fideicomisos) | ❓ Puede llegarle a una persona natural. Preguntar a GTS si tiene clientes con K-1 |
| Contabilidad de empresa, activos fijos, nómina, ventas, factores, documentos legales | ❌ Empresas. Para el Schedule C basta con extractos o un P&L (ver F1) |
| Regla 1. **Integridad:** cada documento esperado está recibido o marcado "no aplica" | 🟡 Existen los estados recibido/pendiente/no aplica. 🐞 "No aplica" solo se permite en campos opcionales |
| Regla 2. **Formato:** SSN/EIN válidos, sumas internas (casilla 3 del W-2 ≤ tope del Seguro Social) | ➕ No hay reglas sobre el contenido |
| Regla 3. **Contra el año anterior:** pagador que falta, variación > 25 % | ➕ Requiere año anterior |
| Regla 4. **Contra el IRS:** W-2 y 1099 vs. transcripción | ➕ Requiere transcripciones |
| Reglas 5 a 8 (balance, RT-6, DR-15, HB 7031) | ❌ Empresas |
| **Reglas como datos** (código, año, severidad, referencia), editables por el equipo de impuestos | ➕ Hoy hay parámetros fiscales por año (`ParametroFiscal`), pero las reglas y las 7 dependencias entre preguntas están en el código. Encaja con el principio "el IRS es la fuente de verdad": cada regla con su referencia al IRS |

### Sección 9 — Arquitectura

**Qué dice:** la plataforma va **antes** del software de preparación: captura, valida y entrega un expediente limpio. **Calcular y presentar sigue en el software certificado**, así que la firma no cambia de herramienta. Describe 8 módulos, un modelo de datos mínimo y 4 principios de uso de IA.

| Punto | Evaluación |
| --- | --- |
| **"La plataforma no calcula la declaración"** | 🐞❓ **Choque importante.** La plataforma tiene un motor de cálculo con 12 calculadoras (`app/Services/Reglas`): AGI, impuesto, autoempleo, NIIT, QBI, liquidación final… Hace el trabajo de TaxWise, se alimenta de números que escribe el cliente y nunca se comparó con TaxWise (tarea 2, 5.2.5). **Propuesta:** quedarse solo con lo que sirve para **entender al cliente y decidir qué pedir** (estado civil, si el dependiente califica, a qué créditos podría tener derecho) y dejar el cálculo del impuesto a TaxWise. A decidir con GTS y Juan Pablo |
| **Portal** con cuestionario dinámico, lista PBC, carga, consultas, firma | ✅ Portal y carga. 🐞 El cuestionario no es dinámico. ➕ Consultas y firma |
| **Ingesta y bóveda:** antivirus, deduplicar por hash, cifrar, retener (WORM) | ✅ Hash SHA-256 para detectar el mismo documento en dos clientes. 🐞 Archivos sin cifrar y sin respaldo (bloque 1). ➕ Antivirus y archivo inmutable |
| **IDP + IA:** extracción con confianza y coordenadas; LLM en región EE. UU. | ✅ Hay extracción. ➕ Confianza y evidencia. ❓ Región y retención de OpenAI (§7216) |
| **Motor de reglas** versionado por año | 🟡 Parámetros por año sí, reglas como datos no (sección 8) |
| **Workflow:** estados del expediente, colas, SLA, maker-checker, consultas | ➕ El estado de la forma solo es "en progreso" o "completo". Faltan estados del caso con fechas (tarea 2, 5.4.1) |
| **Modelo de datos:** PostgreSQL + capa analítica (Snowflake/Databricks) | ✅ PostgreSQL. ❌ La capa analítica sobra para el piloto |
| **Salidas:** conectores a Drake, UltraTax, Lacerte, ProSeries, CCH, ATX | 🐞 **El documento no menciona TaxWise**, que es el software que usa GTS. ➕ Agregarlo: es el conector que más tiempo le ahorraría a GTS (tarea 2, bloqueado por el proveedor) |
| **Seguridad:** tokenización, aislamiento por firma, SIEM, SOC 2 | ✅ SSN cifrado y "revelar" con registro. ❌ Aislamiento por firma y SOC 2 no aplican al piloto |
| **Modelo de datos mínimo (9.2):** segmento, encargo por año, obligación con vencimiento, documento con hash, dato extraído con confianza y evidencia, regla y resultado, arrastres | ✅ Año fiscal en formas y documentos; hash; historial de cambios. ➕ Segmento del cliente, vencimientos, confianza/evidencia, resultados de reglas, arrastres. ❌ Activos fijos con base de Florida y tenant |
| **Principios de IA (9.3):** 1) nada llega a la declaración sin evidencia o validación humana; 2) umbrales de confianza; 3) la IA no decide posiciones fiscales; 4) región EE. UU., sin retención | ✅ 3 se cumple: la IA propone y el preparador revisa. 🐞 1 y 2: lo extraído queda como dato recibido sin evidencia ni confianza. ❓ 4: OpenAI (bloque 4) |

### Resumen del bloque 2

**Lo que el documento confirma**
- El proceso empieza por **clasificar y entender al cliente** (paso 2) y **pedir solo lo que su segmento necesita** (principio 3): es la observación 4.
- **Ir a la fuente:** lo que trae un documento o el IRS no se le pregunta al cliente.
- Lo que ya señaló la tarea 2: la lista fija por forma, la falta de un ciclo de corrección con el cliente, la revisión que no se reabre, la exportación inútil para TaxWise y el cliente sin año anterior.

**Lo nuevo que aporta**
1. **La plataforma no debería calcular la declaración.** Es el choque más grande del bloque: hoy tiene un motor de cálculo completo que duplica TaxWise.
2. **Confianza y evidencia en la extracción:** cada dato con su nivel de confianza y la página del documento de donde salió. Sin eso, el preparador tiene que revisar todo.
3. **Reglas de validación como datos**, cada una con su referencia al IRS.
4. **Transcripciones del IRS** para precargar W-2 y 1099 de los asalariados (PN-1).
5. **Arrastres entre años:** pérdidas, depreciación de inmuebles.

**Lo que no aplica al piloto:** QuickBooks como centro del producto (pensado para empresas), recetas F3/F4, reglas de empresa (RT-6, DR-15, HB 7031), activos fijos, marca blanca, capa analítica y SOC 2.

**Para decidir o validar**
- ¿Se elimina el cálculo del impuesto y la plataforma se queda en capturar, validar y entregar? (GTS y Juan Pablo)
- ¿Cómo arma hoy GTS el Schedule C de un independiente informal (F1)? ¿Cuántos usan QuickBooks?
- ¿GTS tiene clientes con K-1?
- ¿GTS quiere que el cliente firme el 8879 en el portal?
- ¿Se sigue extrayendo con OpenAI o se evalúa un lector de formularios comprado (con confianza por campo)?
- Agregar **TaxWise** a los conectores de salida del documento.

---

## 3. Secciones 10–12

> ⚠️ **El PDF que tenemos no trae sección 12.** Termina en la 11 (página 37 de 37). El "módulo F1" solo aparece repartido: la receta F1 (7.2), la regla R-09 y la entrada de F1 en la segunda mitad del piloto (11.8). ❓ Pedirle a Juan Pablo la versión con la sección 12 (puede estar solo en el artifact, que no pudimos abrir).

### Sección 10 — KPIs, roadmap y riesgos

**Qué dice:** la temporada 2027 (año fiscal 2026) empieza en tres meses, así que propone diseñar ahora, construir en 2027, pilotar con las declaraciones prorrogadas (septiembre y octubre de 2027) y operar a escala en 2028. Define 7 KPIs, 8 riesgos y 6 próximos pasos.

| Punto | Evaluación |
| --- | --- |
| **Calendario:** no usar en producción en la temporada 2027; pilotar con prorrogadas | ❓ **Choque con la realidad de GTS:** la plataforma ya está en producción con clientes reales. Hay que decidir si GTS la usa en la temporada de enero–abril de 2027 o si se limita a las prorrogadas, como propone el documento. 🐞 En cualquier caso, los parámetros fiscales solo están cargados para **2025**. La temporada 2027 es el **año fiscal 2026**: hay que cargar sus cifras (deducción estándar, OBBBA, 1099-NEC a USD 2.000) |
| **KPIs:** STP, precisión de extracción ≥ 98 %, completitud a tiempo, toques por declaración, tiempo de ciclo, presentación a tiempo, reprocesos < 5 % | ✅ Buenos para GTS. 🐞 La plataforma no mide ninguno (tarea 2, 5.2.1). El dashboard muestra campos recibidos, formas completas, riesgo alto y pendientes de revisión, pero no tiempos ni toques. ➕ Antes de medir hay que levantar la **línea base** de GTS: horas por declaración hoy |
| **Riesgos** (§7216/FTC, adopción del portal, errores de IA, cambios de ley, huracanes) | ✅ Los mismos del bloque 1. ❌ Precio y firmas sin tiempo para implementar no aplican con una sola firma |
| **Próximos pasos:** validar con CPA/abogado, reclutar 3–5 firmas, línea base, software de las firmas, 300–500 documentos anonimizados, precio | ✅ Validación legal (bloque 4) y **línea base de horas de GTS**. ✅ El software ya se sabe: **TaxWise**. ✅ Documentos anonimizados para calibrar la extracción (❓ si salen de clientes de GTS, requiere consentimiento §7216). ❌ Reclutar firmas y precio |

### Sección 11 — Diseño funcional del piloto

**Qué dice:** piloto con 3–5 firmas, 7 épicas, 22 historias de usuario, 12 pantallas, 11 estados del expediente, 17 reglas y criterios de éxito.

**11.1 Alcance y 11.2 roles**

| Punto | Evaluación |
| --- | --- |
| Dentro: 1040 de residentes (PN-1; PN-2 y PN-3 de bajo volumen) | ✅ Coincide con el alcance de Steven. ❓ Para GTS el PN-2 (Schedule C) **no** es de bajo volumen: entra completo |
| Dentro: 1120-S y 1120 + F-1120; QuickBooks; F3 | ❌ Fuera por decisión de Steven (solo persona natural) |
| Fuera: 1040-NR y multiestado | ✅ Coincide con lo propuesto en la tarea 2 (quitar el 1040-NR), pendiente de que GTS confirme |
| **Roles:** dueño, preparador (solo sus clientes), **asistente** (sin SSN completo, sin exportar), cliente, **soporte del proveedor** (sin acceso salvo autorización temporal registrada) | 🟡 Hay 3 roles (cliente, preparador, administrador). ➕ No hay **asignación de clientes a un preparador**: todos ven a todos. ➕ El rol de soporte "sin acceso salvo autorización" es justo lo que pide §7216 para el equipo de desarrollo (bloque 4). ❓ ¿GTS tiene asistentes? |

**11.3 Las 22 historias de usuario frente a la plataforma**

| Historia | Estado | Comentario |
| --- | --- | --- |
| US-01 Importar clientes desde CSV | ➕ | Útil para arrancar la temporada con la cartera de GTS |
| US-02 Marca, carta de encargo y **consentimiento §7216** (con texto, versión y fecha) | ➕ / ❌ | Consentimiento ➕ (bloque 1). Marca ❌ con una sola firma |
| US-03 Cliente preclasificado con evidencia | ➕ | Requiere año anterior |
| US-04 Cuestionario corto: **máximo 15 preguntas para PN-1**, según segmento, guardado parcial, móvil, español e inglés | 🟡 | ✅ Guardado parcial, móvil, bilingüe. 🐞 No depende del perfil: lista fija (observación 4) |
| US-05 Corregir el segmento con motivo; la lista se regenera | ➕ | |
| US-06 Lista de documentos con ejemplos y "no aplica" con motivo | 🟡 | 🐞 Lista fija; "no aplica" solo en opcionales y sin motivo |
| US-07 Subir fotos o PDF desde el teléfono, acuse con el tipo reconocido | ✅ | Portal y WhatsApp. ❌ Correo por cliente no hace falta |
| US-08 Conexión a QuickBooks Online | ❓ | Depende de cuántos Schedule C de GTS usan QuickBooks |
| US-09 Plantilla de balance F3 | ❌ | Empresas |
| US-10 **Recordatorios automáticos** escalonados | ➕ | No existen. Muy útil: GTS hoy persigue documentos a mano |
| US-11 Transcripción del IRS (el preparador la sube y la plataforma marca diferencias) | ➕ | Versión simple y realista: no requiere API del IRS |
| US-12 Extracción con confianza, página y recuadro | 🟡 | Hay extracción, sin confianza ni evidencia |
| US-13 Reglas automáticas; sin alertas → directo al expediente | ➕ | |
| US-14 Mapeo de cuentas F2 | ❌ | Empresas / QuickBooks |
| US-15 **Cola de excepciones** por vencimiento y severidad | ➕ | |
| US-16 Consultas al cliente en el portal | ➕ | Hoy el portal tiene chat de dudas del cliente → agente, pero no consultas de la firma → cliente |
| US-17 Exportar al software fiscal; **alternativa: hoja de trabajo por formulario (PDF/Excel)** | 🐞 | La alternativa es justo lo propuesto en la tarea 2 mientras TaxWise no defina su mecanismo |
| US-18 **Checklist de revisión** obligatorio con diferencias contra el año anterior | ➕ | |
| US-19 Firma del 8879 | ❓ | Pendiente de GTS |
| US-20 Tablero por estado, segmento y vencimiento | 🟡 | Hay dashboard (formas completas, campos recibidos, riesgo, pendientes de revisión). Faltan estados del caso y vencimientos |
| US-21 Medir STP, precisión y horas | ➕ | Tarea 2, 5.2.1 |
| US-22 Bitácora inmutable y exportable | 🟡 | ✅ Hay bitácora (creado, actualizado, eliminado, sesiones) y registro de "revelar". ❓ No es inmutable ni exportable |

**11.4 a 11.7 Pantallas, estados, reglas y criterios de éxito**

| Punto | Evaluación |
| --- | --- |
| **P9 Visor de documento:** documento a la izquierda, campos extraídos a la derecha, con el recuadro de evidencia ("donde se pasa más tiempo") | ➕ La pantalla que más tiempo le ahorraría al preparador. Depende de la evidencia en la extracción |
| P1–P8, P10–P12 | Mismo estado que las historias: portal y ficha ✅; consentimientos, consultas, excepciones, firma y tablero por estado ➕; marca ❌ |
| **11 estados del expediente:** invitado → clasificado → en recolección → en validación → con excepciones / en consulta → listo → exportado → en revisión → listo para firma → presentado → archivado | ➕ Es lo que pedía la tarea 2 (5.4.1). Hoy solo hay "en progreso" y "completo". Para GTS se pueden simplificar los últimos (firma y presentación pasan en TaxWise) |
| **Reglas para personas:** R-01 SSN válido; R-02 documento del año anterior faltante; R-03 confianza < 0,90 → revisión; R-04 W-2 casilla 2 ≤ casilla 1 y casilla 3 ≤ tope de Seguro Social; R-05 vs. transcripción IRS; R-06 variación > 25 % | ✅ **Adoptar las 6** como primeras reglas (con referencia al IRS). R-02, R-05 y R-06 dependen del año anterior y de las transcripciones |
| **Reglas para Schedule C:** R-08 ingresos del libro < suma de 1099-K/NEC; R-09 gasto ≥ USD 2.500 → posible activo; R-12 señales de DR-15; R-17 proveedores sin 1099 | ❓ Útiles si GTS recibe libros o P&L de sus independientes. R-08 sirve aunque el P&L sea simple |
| Reglas de empresa y QuickBooks (R-07, R-10, R-11, R-13 a R-16) | ❌ |
| **Criterios de éxito:** precisión ≥ 98 %, horas −40 % vs. línea base, ≥ 70 % de clientes usan el portal, 0 incidentes | ✅ Sirven para GTS. ❌ "3 de 5 firmas quieren pagar" no aplica |
| **Plan 2027** (construir ene–may, pilotar jul–oct con prorrogadas) | ❓ Ver el choque de calendario en la sección 10 |
| **Decisiones abiertas:** software y portal (→ TaxWise), quién opera la captura F1, proveedor de lectura y LLM, precio, umbral de confianza | ✅ Software resuelto. ❓ Captura F1, proveedor de extracción y umbral. ❌ Precio |

### Resumen del bloque 3

**Lo más útil para GTS**
1. **Los 11 estados del expediente** y el **tablero por estado**: ordenan el trabajo de la temporada.
2. **Las 6 reglas para personas (R-01 a R-06)**, como primer motor de validación.
3. **Visor de documento con evidencia (P9)** y **cola de excepciones**.
4. **Recordatorios automáticos** al cliente.
5. **Hoja de trabajo por formulario** mientras no exista el conector con TaxWise.
6. **Medir:** línea base de horas de GTS hoy y después horas, toques y reprocesos por declaración.

**Lo que no aplica:** empresas y QuickBooks (US-08, US-09, US-14, reglas de libros), marca blanca, precio, reclutar firmas.

**Para decidir o validar**
- ❓ **Calendario:** ¿GTS usa la plataforma en la temporada de enero–abril de 2027 o solo con las prorrogadas, como propone el documento? En cualquier caso hay que cargar los **parámetros fiscales de 2026**.
- ❓ ¿El Schedule C es de "bajo volumen" en GTS? Para el documento sí; para GTS probablemente no.
- ❓ ¿GTS tiene asistentes? ¿Cada preparador ve solo sus clientes?
- ❓ Pedirle a Juan Pablo la **sección 12 (módulo F1)**, que no está en el PDF.

---

## 4. Puntos legales pendientes de validar y quién los valida

> El documento de Juan Pablo dice que es "un diseño de solución, no una opinión legal" y que debe validarse con un **CPA o abogado tributario de Florida**. Esta revisión tampoco es opinión legal: junta en un solo lugar las preguntas que salieron en los bloques 1 a 3, para llevarlas a quien corresponde.

**Quién valida:**
- **Abogado** (tributario o de privacidad, de Florida): interpretación de normas y textos de consentimientos y contratos.
- **CPA / EA:** práctica profesional del preparador (Circular 230, retención, 8879, cifras fiscales, obligaciones de Florida).
- **GTS (dueño):** hechos de la firma que el abogado necesita para responder.
- **Equipo técnico (nosotros):** hechos de la plataforma que el abogado necesita para responder.

### 4.1 IRC §7216 — uso y revelación de información de la declaración

| # | Pregunta | Por qué importa en la plataforma | Quién |
| --- | --- | --- | --- |
| L1 | ¿Qué consentimientos firmados debe dar el cliente y con qué texto, versión y fecha? | ➕ No hay ningún consentimiento en la plataforma (bloque 1, sección 5) | Abogado |
| L2 | ¿Enviar documentos y datos a **OpenAI** (extracción y agente) cuenta como revelación que requiere consentimiento, o como proveedor auxiliar de la preparación? ¿Qué contrato y qué retención de datos exige? | La plataforma llama a `api.openai.com/v1/chat/completions` con W-2, 1099 y respuestas del cliente. El documento propone un LLM en región EE. UU. sin retención | Abogado. Equipo técnico: confirmar región, retención y si aplica *zero data retention* |
| L3 | ¿Twilio / WhatsApp (conversaciones con datos fiscales) tienen el mismo tratamiento? | El agente de WhatsApp recibe datos personales y fiscales del cliente | Abogado. Equipo técnico: qué datos pasan hoy por WhatsApp |
| L4 | **Acceso desde fuera de EE. UU.:** el equipo de desarrollo y soporte puede ver datos de producción. ¿Qué consentimiento hace falta? ¿Aplica la regla especial del **SSN** revelado fuera de EE. UU.? | Hoy el acceso a producción no tiene restricción ni registro de autorización. El documento propone un rol de soporte sin acceso salvo autorización temporal registrada | Abogado. GTS y equipo técnico: quién accede y desde qué país |
| L5 | ¿Usar documentos de clientes de GTS (anonimizados) para calibrar la extracción requiere consentimiento? | El documento propone reunir 300–500 documentos | Abogado |
| L6 | ¿En qué país está el servidor de producción y dónde están los respaldos? | Requisito de "datos en EE. UU." del documento | Equipo técnico |

### 4.2 FTC Safeguards Rule (16 CFR 314) y seguridad

| # | Pregunta | Por qué importa en la plataforma | Quién |
| --- | --- | --- | --- |
| L7 | ¿Qué exige la regla a GTS según su tamaño? (las firmas con menos de 5.000 clientes tienen excepciones) ¿Quién es el "responsable calificado"? | Define cuánto del WISP, la evaluación de riesgos y el plan de incidentes es obligatorio | Abogado o CPA. GTS: número de clientes |
| L8 | ¿GTS tiene **WISP** (programa escrito de seguridad, IRS Pub. 4557 y 5708)? ¿La plataforma tiene que darle uno base? | ➕ No hay WISP ni plan de incidentes | GTS. Abogado o CPA para el contenido |
| L9 | ¿Qué controles técnicos exige? **Cifrado** en tránsito y en reposo, **MFA** para todo el que acceda a datos de clientes, bitácora, borrado seguro | 🐞 Archivos (W-2, 1099) **sin cifrar** y sin respaldo. 🐞 **2FA no obligatorio**. 🟡 Bitácora no inmutable | Equipo técnico (cumplir); abogado (confirmar alcance) |
| L10 | Como proveedor de GTS, ¿qué contrato y supervisión exige la regla (*service provider oversight*)? | La plataforma es proveedor de servicios de GTS | Abogado |
| L11 | Aviso de brechas: ¿a la FTC (si son 500 o más afectados) y, por la **Florida Information Protection Act**, a los afectados en 30 días y al Fiscal General? ¿En qué plazo debemos avisarle a GTS como proveedor? | ➕ No hay procedimiento ni inventario de qué datos sensibles hay por cliente | Abogado. Equipo técnico: inventario desde el catálogo (`sensible`) |

### 4.3 Práctica del preparador

| # | Pregunta | Por qué importa en la plataforma | Quién |
| --- | --- | --- | --- |
| L12 | **§6107:** ¿qué debe conservar GTS 3 años (copia o lista de declaraciones)? ¿Aplica a los documentos de la plataforma o solo a lo que está en TaxWise? | 🐞 Se puede borrar un cliente con todos sus archivos | CPA |
| L13 | **Circular 230:** ¿la revisión exige un segundo revisor (*maker-checker*) o basta con un checklist y registro de quién revisó? | 🟡 Hay `marcarRevisado` (una persona) y la revisión no se reabre si algo cambia | CPA o EA |
| L14 | **Form 8879:** si el cliente lo firma en el portal, ¿qué verificación de identidad exige el IRS (Pub. 1345)? | ❓ Pendiente de que GTS lo quiera | CPA o EA; GTS decide si lo quiere |
| L15 | **Form 8821 y transcripciones:** ¿GTS tiene acceso a e-Services? ¿Se puede firmar el 8821 en el portal? | ➕ Las transcripciones son de lo más valioso para asalariados (PN-1) | GTS; CPA o EA |
| L16 | **Cifras OBBBA** (propinas, horas extra, SALT, 1099-K, 1099-NEC) y parámetros del **año fiscal 2026** | El documento usa fuentes secundarias; la plataforma solo tiene parámetros de 2025 | CPA, contra la ley y la guía del IRS |

### 4.4 Florida (para independientes con Schedule C)

| # | Pregunta | Por qué importa en la plataforma | Quién |
| --- | --- | --- | --- |
| L17 | **TPP (DR-405):** ¿los independientes de GTS con equipos de negocio deben presentarlo ante el condado (exención de USD 25.000)? ¿GTS lo hace hoy? | Si sí, falta una pregunta de alerta en el intake; si no, se descarta | GTS; CPA |
| L18 | **Sales tax (DR-15):** ¿algún independiente de GTS vende bienes o servicios gravados? ¿GTS lo atiende? | Igual: alerta en el intake o se descarta | GTS; CPA |
| L19 | **Tourist tax** por alquiler de corto plazo (Airbnb) | La plataforma no pregunta por alquiler de corto plazo | GTS; CPA |
| L20 | **Declaration of Domicile** y declaración en otro estado para quien se mudó a Florida | Falta la pregunta "¿vivió o tuvo ingresos en otro estado?" | CPA |

### Resumen del bloque 4

**Lo más urgente** (la plataforma ya tiene datos reales de clientes):
1. **L2 y L4 (§7216):** OpenAI y el acceso del equipo desde fuera de EE. UU.
2. **L9 (Safeguards):** cifrar los archivos, respaldarlos y hacer obligatorio el 2FA. No hace falta esperar al abogado para esto.
3. **L1:** texto del consentimiento §7216 para ponerlo en el portal.
4. **L12:** no permitir borrar clientes mientras no haya política de retención.

**Lo que el equipo técnico puede responder ya:** L2 (región y retención de OpenAI), L3 (qué pasa por WhatsApp), L6 (país del servidor) y el inventario de datos sensibles de L11.
