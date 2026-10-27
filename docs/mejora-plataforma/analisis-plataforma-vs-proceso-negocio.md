# Análisis: plataforma actual vs. proceso de negocio (Persona natural)

21 oct. 2026 · Documento de referencia: `Plataforma de impuestos USA — Proceso de negocio (Persona natural).md` (20 oct. 2026)

> Este análisis compara lo que el código hace hoy con la visión del documento de proceso. El documento es la **referencia** para reorganizar, no reemplaza lo que ya funciona: la idea es reutilizar todo lo que sirva y ordenar alrededor de él.

---

## 1. Resumen ejecutivo

**Lo que el documento pide:** entrevistar al cliente paso a paso, deducir qué documentos pedir y llenar por debajo el Form 1040 con todas sus capas (schedules y formularios intermedios), con un resumen que explique qué se aplicó y por qué, usando irs.gov como fuente de la verdad.

**Lo que la plataforma hace hoy:** recoge datos y documentos del cliente en un catálogo genérico de campos, extrae información con IA y calcula conceptos fiscales con un motor de reglas sólido. Pero:

1. **Está organizada por formularios y no por la persona.** Primero se decide qué "formas" aplican (1040, Schedule C/E/F, 1065, 1120, 990…), luego se piden campos. El documento dice lo contrario: primero quién es el cliente y cómo ganó su dinero, y de ahí sale todo.
2. **No hay modelo de los formularios del IRS.** No existe la estructura casilla por casilla de los documentos que entran (W-2, 1099…) ni línea por línea de lo que sale (1040 y schedules). Por eso **no se puede verificar que estamos tomando el dato correcto ni rastrear de dónde sale cada número del 1040**.
3. **El motor de reglas no termina en el 1040.** Calcula AGI, deducción, impuesto, créditos y liquidación como JSON por concepto, pero no produce las líneas del 1040 ni los schedules.
4. **Hay lógica duplicada.** El orden de las preguntas está en 3 lugares, las condiciones en 2, y el chat y el formulario recogen los mismos datos.
5. **No existe el "caso".** No hay estados (Nuevo → En entrevista → … → Presentado); el avance se infiere de los campos.

**Lo más valioso que ya tenemos:** carga y extracción de documentos, motor de 12 calculadoras con parámetros 2025 en BD, catálogo editable, auditoría y control de datos sensibles.

**Propuesta central:** construir una **capa de formularios IRS** (entrada y salida) como columna vertebral, y reordenar la entrevista según las fases del documento encima de ella.

---

## 2. Qué tenemos hoy (inventario)

### 2.1 Modelo de datos

No hay tablas de contribuyente, cónyuge, dependiente, caso, fuente de ingreso, línea del 1040 ni schedule. Todo dato del cliente es una fila genérica:

| Concepto | Dónde vive hoy |
| --- | --- |
| Cliente / contribuyente | `User` (rol `client`) — mismo modelo que preparadores y admins |
| Qué campos existen | `CampoCatalogo` (`catalogo_campos`): por `forma` + `tax_year` + `clave`; tipo documento/dato/mixto, obligatorio, sensible |
| Valor del cliente | `CampoCliente` (`campos_cliente`): por `user_id`, `forma`, `tax_year`, `campo` |
| Cónyuge | Campo `transversal.info_conyuge` (objeto JSON) |
| Dependientes | Campo `transversal.info_dependientes` (arreglo JSON) |
| Ingresos | `form_1040.ingresos` con subcampos (salarios, intereses_dividendos, ganancias_capital…) |
| Deducciones detalladas | `form_1040.deducciones` con subcampos |
| Documento subido | `Documento` (archivo, hash, método de extracción). Los datos extraídos terminan en `CampoCliente` |
| Documento → campos que "revela" | `RelacionDocumentoCampo` (ej. W-2 → `ingresos.salarios`, `impuestos_retenidos`) |
| Qué formas aplican | `FormaCliente` (`formas_cliente`) + estado de revisión |
| Resultado del motor | `DeterminacionFiscal` (JSON por tipo, con `version_reglas`) |
| Parámetros por año | `ParametroFiscal` (solo 2025) |
| Confirmación del cliente | `ClienteAtestacion` |
| Auditoría | `HistorialCambio`, `CampoReveal`, `BitacoraActividad` |

### 2.2 Estados existentes

- Por forma: `en_progreso`, `completo` (`FormState`). **No hay estado del caso.**
- Por campo: `pendiente`, `recibido`, `invalido`, `no_aplica` (`FieldState`).
- Conversación: `verificacion_cuenta → determinacion_formas → recoleccion → cierre` + `portal_dudas`, `handoff_portal` (`FaseConversacion`).
- Filing status calculado: `single`, `mfj`, `hoh`, `qss` — **sin MFS** (`FilingStatus`).
- Riesgo: `bajo`, `medio`, `alto`.

### 2.3 Entrevista

Hay **dos canales** que conviven:

- **Chat con IA** (WhatsApp y portal): `app/Services/WhatsappAgent/`. Verifica la cuenta y decide qué formas aplican mediante un árbol de pasos (`prompt_actuales/fases/determinacion_formas.md`). Luego en WhatsApp entrega el enlace al portal y en el portal pasa a modo "solo dudas". El cierre (atestación) sigue ocurriendo en WhatsApp.
- **Formulario del portal**: `PortalFormularioController` + `resources/js/pages/portal/formulario.tsx`. Una sola página larga (no es paso a paso): campos transversales → documentos extra → una sección por forma declarada.

Orden actual de las preguntas del chat (44 pasos, `PromptActivoStepsSeeder`): SSN/ITIN → estado civil → cónyuge → dependientes → empleo (W-2 o 1099-NEC) → 1095-A → activos digitales → cuentas en el exterior → venta de casa → jubilación → 1099-INT/DIV/B/G, 1098, 1098-E, MISC, K, S, K-1, W-2G, C, SA, 5498-SA → **declaración del año anterior (paso 26)** → fecha de nacimiento, dirección, ocupación… → grupos de ajustes, créditos y situaciones especiales.

### 2.4 Documentos y extracción

- Carga desde el portal y desde la API; detección de duplicados por hash.
- Extracción en dos niveles: texto embebido del PDF (`smalot/pdfparser`) y visión de OpenAI para imágenes.
- **No hay parsers por tipo de formulario.** La IA lee el texto y llena los campos "revelados" definidos a mano en `RelacionesDocumentoCampoSeeder`.
- Checklist implícito: campos del catálogo para las formas declaradas, filtrados por obligatorio y por reglas de dependencia.

### 2.5 Motor de reglas

`DeterminacionFiscalService` ejecuta 12 calculadoras (`app/Services/Reglas/`) en este orden:

Dependientes → Filing status → Impuesto de autoempleo (Sched C+F) → AGI (Schedule 1 con netos de C/E/F) → Estándar vs. detallada → QBI (8995) → Impuesto por tramos → Créditos (8812 CTC/ODC, 2441) + crédito extranjero (1116 simplificado) → Medicare adicional (8959) → NIIT (8960) → Liquidación.

- Parámetros 2025 en BD (tramos, deducción estándar OBBBA 15,750 / 31,500 / 23,625, CTC/ODC, cuidado de dependientes).
- Tasas fijas en código: SE 12.4 % / 2.9 % / 0.9235, QBI 20 %, NIIT 3.8 %, Medicare adicional 0.9 %, `VERSION_REGLAS = '2025.1'`.
- Estándar vs. detallada: suma simple de subcampos, **sin tope SALT ni piso de gastos médicos sobre el AGI**.
- Salida: JSON por concepto. **No hay líneas del 1040 ni PDF.**

### 2.6 Roles y pantallas

- Roles: `client`, `preparer`, `administrator`. No hay rol de "equipo interno" separado (el documento lo menciona como actor: Steven).
- Cliente: `portal/formulario`, `portal/chat`, `mi-informacion`.
- Preparador: `dashboard`, `clientes/index` (lo más cercano a una cola de casos), `clientes/show` (expediente, edición, historial, riesgo, panel de determinación fiscal, toma de control de WhatsApp, "marcar revisado" por forma).
- Admin: catálogo, usuarios, bitácora, prompts, tools, base de conocimiento, meta-agente.

### 2.7 Integraciones

- WhatsApp (Twilio o Meta) con agente propio en Laravel (OpenAI).
- API con Sanctum: `POST /api/eventos` (agente externo), `clientes/*`, `catalogo/documentos-extra`.
- Restos del sistema anterior: lectura de la tabla de Supabase de n8n en `ClienteController`, `ClientIntakeSession`.

---

## 3. Comparación con el documento

### 3.1 Por fase de la entrevista

| Fase del documento | Estado | Diferencia con lo que hay hoy |
| --- | --- | --- |
| 0 — Clasificación | 🟡 Parcial | Se clasifica por "qué formas aplican", no por persona natural/compañía ni actividad. No se marca "primera vez que declara". |
| 1 — Identidad | 🟡 Parcial | SSN/ITIN se pide al inicio, pero fecha de nacimiento y dirección quedan en los pasos 27–33. Falta confirmar que se pida la licencia de conducción. |
| 2 — Historial | 🔴 Desalineado | La declaración anterior es opcional y está en el paso 26. El documento la pone al principio y siempre la pide, con ruta alternativa (contador → transcript en línea → solicitud física → primera vez). |
| 3 — Filing status | 🔴 Desalineado | No se pregunta: se calcula. "Casado por separado" está excluido a propósito. No se pregunta fecha de divorcio. |
| 4 — Dependientes | 🟢 Casi completo | Los datos existen (parentesco, meses en el hogar, sostenimiento…). Verificar: estudiante, discapacidad (también del cónyuge), "¿otra persona ya lo reclamó?". |
| 5 — Fuentes de ingreso | 🔴 Desalineado | Se pregunta "¿tiene W-2, 1099-INT, 1099-B…?" en vez de "¿cómo obtuvo su dinero?". No hay reconstrucción para quien no tiene soportes. No se separan gastos personales vs. del negocio. |
| 6 — Ajustes y deducciones | 🟡 Parcial | Existen campos y el cálculo estándar vs. detallada, sin tope SALT ni piso médico. |
| 7 — Créditos | 🟡 Parcial | Se calculan 8812 y 2441. No se calculan 8962 (Marketplace), 8863 (educación), EITC, ahorro para jubilación, energía/vehículo eléctrico. |
| 8 — Exterior | 🟢 Existe | Hay preguntas filtro y crédito extranjero simplificado. Falta 2555, 8938, FBAR (raro en el segmento). |
| 9 — Pagos y resultado | 🟡 Parcial | Retenciones y liquidación calculadas. **No hay alerta de vencimiento ni flujo de extensión.** |
| 10 — Entrega | 🔴 Falta | No hay revisión formal, paquete completo ni resumen explicativo. Solo la atestación del cliente por WhatsApp. |

> Nota sobre el documento: el diagrama dice que las fuentes de ingreso son la "fase 6" y el texto las numera como "Fase 5". Conviene unificarlo.

### 3.2 Por módulo propuesto

| # | Módulo | Estado | Qué hay / qué falta |
| --- | --- | --- | --- |
| 1 | Onboarding y clasificación | 🟡 | Chat de verificación y determinación de formas. Falta clasificar por actividad y primera vez. |
| 2 | Perfil del contribuyente | 🟡 | Cónyuge y dependientes como JSON dentro de campos. No se reutilizan año a año como entidad propia. |
| 3 | Motor de entrevista | 🟡 | Árbol de 44 pasos, pero orden y condiciones repartidos en 3 lugares; el portal no es paso a paso. |
| 4 | Checklist de documentos | 🟡 | Implícito vía catálogo + estado del campo. Falta estado "validado" por el preparador y agrupar por fuente de ingreso. |
| 5 | Carga y extracción | 🟢 | Funciona. Falta reconocer el tipo de formulario y extraer por casilla. |
| 6 | Reconstrucción de ingresos | 🔴 | No existe. Es el caso más común en independientes. |
| 7 | Gestión de historial | 🔴 | Solo un campo de documento. |
| 8 | Motor de reglas fiscales | 🟢 | Lo más sólido. Faltan MFS, SALT/piso médico, parámetros 2026, tasas fijas a BD, enlaces al IRS. |
| 9 | Generador del 1040 | 🔴 | No hay líneas, schedules ni PDF. |
| 10 | Asistente de explicación | 🟡 | Chat de dudas + base de conocimiento. Cero enlaces a irs.gov. No hay "¿para qué me piden esto?" por pregunta. |
| 11 | Revisión del preparador | 🟡 | Lista de clientes, riesgo, "marcar revisado" por forma. No hay validaciones cruzadas entre datos. |
| 12 | Plazos y alertas | 🔴 | No existe. |
| 13 | Entrega | 🔴 | No existe. |
| — | Estados del caso | 🔴 | No existe la entidad caso. |

### 3.3 Mapa documento → intermedio → línea del 1040

Estado de cada fila del mapa del documento frente a lo que se **recoge** y lo que se **calcula** hoy:

| Documento | Va a | ¿Se recoge? | ¿Se calcula? |
| --- | --- | --- | --- |
| W-2 | 1040 línea 1a + retenciones 25a | ✅ | ✅ (como total de salarios) |
| 1099-NEC / MISC + registros | Schedule C → Sch. 1 línea 3 → 1040 línea 8; Schedule SE | ✅ | ✅ (neto de C, SE) |
| LLC de un dueño (estados financieros) | Schedule C + SE | 🟡 sin flujo propio | 🟡 |
| K-1 | Schedule E → Sch. 1 línea 5 | ✅ | 🟡 (neto de E) |
| 1099-INT / 1099-DIV | Schedule B → líneas 2b, 3a/3b | ✅ | 🟡 (suma, sin Schedule B) |
| 1099-B / 1099-S | Form 8949 → Schedule D → línea 7a | ✅ | 🟡 (suma, sin 8949/D) |
| 1099-R | líneas 4a/4b, 5a/5b | ✅ | 🟡 |
| SSA-1099 | líneas 6a/6b | ✅ | Verificar cálculo de la parte gravable |
| 1099-G | Sch. 1 línea 7 | ✅ | 🟡 |
| 1098 (hipoteca) | Schedule A | ✅ | 🟡 (sin tope SALT) |
| 1098-E | Sch. 1 línea 21 | ✅ | 🟡 |
| 1098-T | Form 8863 | ✅ | ❌ |
| 1095-A | Form 8962 → línea 31 | ✅ | ❌ |
| Cuidado de hijos | Form 2441 | ✅ | ✅ |
| Hijos < 17 | Schedule 8812 → líneas 19/28 | ✅ | ✅ |
| EITC | Schedule EIC → 27a | — | ❌ |
| Exterior | 2555 / 1116 / 8938 / FBAR | 🟡 | 🟡 (1116 simplificado) |

"🟡" significa que el valor entra al cálculo como suma de subcampos, pero no existe el formulario intermedio con sus líneas, así que no se puede revisar ni entregar.

---

## 4. Qué está mal o enredado

1. **El eje de la plataforma es el formulario, no la persona.** `TaxForm` decide la entrevista. Esto obliga a que el chat "adivine" formas antes de conocer al cliente, que es lo contrario al principio clave del documento.
2. **Alcance inflado.** El catálogo incluye 1065, 1120, 1120-S, 1041, 990 y 1040-NR. La fase 1 es solo persona natural; esos formularios agregan campos, ramas y mantenimiento sin uso.
3. **Dos canales que recogen lo mismo.** El chat de WhatsApp todavía tiene la herramienta para guardar campos, aunque la recolección ya se pasó al portal. La atestación quedó en WhatsApp mientras los datos están en el portal.
4. **Orden y condiciones definidos en varios lugares.**
   - Orden: pasos del prompt (`PromptActivoStep`), orden del catálogo (el formulario usa este) y el texto de los prompts.
   - Condiciones: lista fija en `PortalFormularioController::ocultosPorDependencia()` y condiciones del árbol del chat (`ActivosResolver`).
   - Consecuencia: cambiar una regla obliga a tocar varios sitios, y el portal y el chat pueden comportarse distinto.
5. **Datos de la persona como JSON dentro de campos.** Cónyuge y dependientes son blobs; cuesta validarlos, reutilizarlos el año siguiente y usarlos en reglas (ej. edad del dependiente para el crédito por hijo).
6. **Sin trazabilidad hacia el IRS.** No hay modelo de casillas ni de líneas, ni enlaces a irs.gov. No se puede responder "¿de dónde salió este número?" ni "¿por qué me piden esto?".
7. **Sin entidad caso.** El preparador no tiene una cola por estado; el progreso se deduce de muchos lados.
8. **Restos de sistemas anteriores:** lectura de Supabase/n8n en `ClienteController`, `ClientIntakeSession`, `POST /api/eventos` para el agente externo, copias viejas de prompts (`docs/prompt.md`, `prompt_actuales/promptBase.md`, `prompt_actuales/especialista_recoleccion.md`).
9. **Dos vistas del cliente con datos solapados:** `mi-informacion` y `portal/formulario`.
10. **Archivo ajeno en la raíz:** `agentes-subagentes-flujos-motor-decision.md` describe otro proyecto (agente de seguros de salud). Solo se tomó su patrón; no es especificación de esta plataforma.

---

## 5. Análisis clave: los formularios del IRS como columna vertebral

El 1040 es el frente de todo: resume lo que viene de los schedules, que a su vez resumen lo que viene de los documentos del cliente. Para que la plataforma haga lo que se quiere, **cada número del 1040 debe poder rastrearse hacia atrás** hasta la casilla del documento que lo originó, y cada regla debe enlazar a su página del IRS.

### 5.1 Lo que falta hoy

| Capa | Hoy | Lo que se necesita |
| --- | --- | --- |
| Documentos de entrada (W-2, 1099…) | La IA llena campos definidos a mano | Estructura oficial **casilla por casilla** por año |
| Formularios intermedios (Sch. 1, 2, 3, A, B, C, D, E, SE, 8812, 8995, 2441, 8962, 8863) | Sumas de subcampos dentro de calculadoras | Cada formulario **línea por línea**, con su fórmula u origen |
| Portada (1040) | No existe | Cada línea con su origen: casilla, línea de otro formulario o cálculo |
| Explicación | No hay enlaces | Cada línea, regla y parámetro con su enlace a irs.gov |

### 5.2 Propuesta: capa de formularios IRS

Tres definiciones, versionadas por año fiscal y guardadas como datos (no en código):

1. **Formularios de entrada:** W-2, 1099-NEC, 1099-MISC, 1099-INT, 1099-DIV, 1099-B, 1099-R, 1099-G, SSA-1099, K-1, 1098, 1098-E, 1098-T, 1095-A. Cada casilla con clave, etiqueta oficial, tipo y descripción. La extracción pasa a llenar **casillas**, y de ahí se mapea a los formularios de salida.
2. **Formularios de salida:** 1040 y sus schedules. Cada línea con:
   - su origen (`casilla W-2 box 1`, `línea 3 del Schedule 1`, `calculadora X`);
   - si se suma desde varios documentos (ej. varios W-2);
   - el enlace a la instrucción del IRS.
3. **Mapa respuesta → documento → línea:** la tabla del documento convertida en configuración. Alimenta el checklist (qué pedir), el motor (dónde va) y el asistente (por qué se pide).

Las 12 calculadoras actuales **se conservan**: pasan de escribir JSON por concepto a escribir líneas de formularios concretos.

### 5.3 De dónde sacar la estructura

- **PDFs llenables del IRS:** traen los campos internos de cada formulario; se pueden extraer de forma automática para cada año.
- **Instrucciones de cada formulario** (ej. `i1040gi`): dicen qué va en cada línea y de dónde sale.
- **Esquemas XML de e-file (MeF):** la definición más rigurosa de cada línea. Probablemente requieren registro como proveedor de e-file — **por confirmar**. Solo serían necesarios si se decide presentar electrónicamente.

### 5.4 Cómo se verifica

- **Auditoría inicial:** tomar el W-2, el 1099-NEC y el 1040 2025 oficiales y comparar casilla por casilla y línea por línea con lo que hoy se extrae y calcula. Resultado: lista de lo que se toma bien, mal o no se toma.
- **Casos de prueba con Daniela:** 3–5 declaraciones reales anonimizadas (independiente simple, independiente con hijos, empleado + independiente, casado conjunto). Se cargan en la plataforma y se compara línea por línea con la declaración que ella preparó. Se vuelven pruebas automáticas.
- **Pruebas por año:** al cargar los parámetros 2026 se corren los mismos casos para detectar cambios.

---

## 6. Plataforma objetivo

```
Cliente ──► Entrevista por fases (portal, paso a paso)
              │  una sola definición del árbol (datos, por año)
              ▼
          Perfil (persona, cónyuge, dependientes)   Fuentes de ingreso
              │                                          │
              └──────────────► Checklist ◄───────────────┘
                                 │  (pendiente / recibido / validado)
                                 ▼
                     Carga y extracción por casilla
                     (formularios de entrada IRS)
                                 │
                                 ▼
                     Motor de reglas (12 calculadoras)
                     + parámetros por año
                                 │
                                 ▼
             Formularios de salida: schedules → 1040
             (cada línea con origen y enlace al IRS)
                                 │
                                 ▼
          Revisión del preparador ──► Paquete + resumen ──► Entrega

Caso: Nuevo → En entrevista → Esperando documentos → En preparación
      → En revisión → Aprobado → Presentado   (lateral: Extensión solicitada)

Chat (portal/WhatsApp): explicar y ayudar ("¿para qué me piden esto?"),
                        avisos y recordatorios. No recoge datos.
```

Principios:

- **Una sola fuente** para el orden y las condiciones de la entrevista; el portal y el chat la leen.
- **Un dato se guarda una vez** y se recalcula la portada (línea 9 ingresos, 11a AGI, 15 base gravable, 24 impuesto total, 33 pagos).
- **Todo monto, regla y línea enlaza al IRS.**
- **Persona natural primero;** compañías después, sin borrar lo que hay.

---

## 7. Qué vamos a cambiar y cómo (plan por etapas)

Cada etapa deja la plataforma funcionando. Se cierra con commit y prueba.

### Etapa 1 — Ordenar (sin funciones nuevas)

- Ocultar del flujo los formularios de compañías y 1040-NR (no borrar todavía).
- Mover las condiciones de la entrevista a un solo lugar y que el portal y el chat las lean de ahí.
- Quitar la lectura de Supabase/n8n y archivar los prompts viejos.
- Decidir el papel del chat (ver decisiones) y retirar la recolección por WhatsApp si corresponde.
- Unificar `mi-informacion` y `portal/formulario`, o definir bien qué muestra cada una.

**Listo cuando:** cambiar una condición se hace en un solo sitio y el portal y el chat se comportan igual.

### Etapa 2 — Capa de formularios IRS (núcleo del MVP de independientes)

- Auditoría W-2 / 1099-NEC / 1040 frente a lo actual (sección 5.4).
- Modelar formularios de entrada: W-2, 1099-NEC, 1099-MISC.
- Modelar formularios de salida: 1040, Schedule 1, Schedule 2, Schedule C, Schedule SE.
- Conectar las calculadoras a líneas reales.
- Enlace al IRS en cada línea y parámetro.

**Listo cuando:** un caso de independiente de Daniela produce las mismas líneas del 1040, Schedule C y SE que su declaración real.

### Etapa 3 — Entrevista por fases

- Portal paso a paso en el orden del documento: clasificación → identidad → historial → filing status → dependientes → fuentes de ingreso → ajustes → créditos → exterior → resultado.
- Pregunta de ingresos por "cómo obtuvo su dinero"; cada respuesta abre su módulo y sus documentos.
- Historial al inicio con la ruta alternativa (contador → transcript → solicitud física → primera vez).
- Preguntar el filing status con las 5 opciones (incluye casado por separado) y la fecha de divorcio.
- Atestación en el portal.

**Listo cuando:** un cliente soltero e independiente completa la entrevista sin ver preguntas que no le aplican.

### Etapa 4 — Caso y checklist

- Entidad caso con los estados del documento.
- Checklist agrupado por fuente de ingreso con estado "validado" por el preparador.
- Cola del preparador filtrada por estado.

### Etapa 5 — Borrador del 1040 y entrega

- Vista del 1040 y schedules en pantalla (botón "generar borrador").
- Resumen explicativo para el cliente: qué deducciones y créditos se aplicaron y por qué, con enlaces al IRS.
- Validaciones cruzadas para el preparador (ej. retenciones mayores que el ingreso, dependiente sin SSN, 1099 sin Schedule C).

### Etapa 6 — Después del MVP

- Reconstrucción de ingresos (extractos, Excel, separación negocio/personal).
- Gestión de historial y transcripts.
- Plazos, alertas y extensión (Form 4868).
- Resto de formularios: Schedule A completo (SALT, piso médico), B, D/8949, E, 8962, 8863, EITC.
- PDF del paquete completo.
- Parámetros 2026 y pasar a BD las tasas que hoy están en código.
- Perfil de persona, cónyuge y dependientes como entidades reutilizables año a año.

---

## 8. Qué conservar tal cual

- Carga de documentos, detección de duplicados y extracción en dos niveles.
- Las 12 calculadoras del motor y `ParametroFiscal` por año.
- `campos_cliente` como almacenamiento (se reorganiza alrededor, no se reescribe en el MVP).
- Auditoría: historial de cambios, revelado de datos sensibles, bitácora.
- Políticas de acceso por preparador.
- Catálogo editable desde el admin.

---

## 9. Riesgos

| Riesgo | Mitigación |
| --- | --- |
| Reescribir demasiado y romper lo que funciona | Etapas cortas, conservar almacenamiento y calculadoras; casos de prueba antes de cambiar el motor |
| La meta de 1–2 semanas no alcanza para todo | MVP = etapas 1–3 enfocadas en independiente soltero y casado conjunto; el resto después |
| Formularios del IRS cambian cada año | Definiciones versionadas por año, extraídas de los PDFs oficiales |
| Datos sensibles (SSN, licencias, extractos bancarios) | Mantener cifrado y revelado auditado; decidir antes de pedir acceso bancario |
| El chat y el portal vuelven a divergir | Una sola definición de la entrevista; el chat no recoge datos |
| Extracción con IA equivocada sin que nadie lo note | Extraer por casilla, mostrar al preparador el origen de cada valor, validaciones cruzadas |

---

## 10. Decisiones pendientes

**Del equipo técnico:**

- [ ] Papel de WhatsApp: ¿solo avisos y enlace al portal, o sigue recogiendo datos?
- [ ] Formularios de compañías y 1040-NR: ¿ocultar o borrar del catálogo?
- [ ] ¿Se modela persona/cónyuge/dependientes como entidades en el MVP o después?

**Del documento de proceso (siguen abiertas):**

- [ ] ¿La plataforma presenta (e-file) o solo genera el paquete? Define si hacen falta los esquemas MeF.
- [ ] Acceso a cuenta bancaria: ¿extractos subidos o conexión directa?
- [ ] Declaraciones estatales: ¿fuera de la fase 1?
- [ ] Depreciación de activos y arrastre del año anterior.
- [ ] Umbral de donaciones con soporte y porcentajes por tipo de dependiente.
- [ ] ¿Quién solicita los transcripts y cómo se guía al cliente fuera de EE. UU.?
- [ ] Revisar con Daniela la tabla documento → schedule → línea y los casos frecuentes.

---

## Anexo — Archivos clave

| Tema | Archivo |
| --- | --- |
| Catálogo de campos y documentos | `database/seeders/CatalogoCamposSeeder.php`, `app/Support/TaxFieldCatalog.php` |
| Documento → campos revelados | `database/seeders/RelacionesDocumentoCampoSeeder.php`, `RevelacionExtractorService` |
| Árbol de preguntas del chat | `database/seeders/PromptActivoStepsSeeder.php`, `ActivosResolver`, `ActivosPromptComposer` |
| Condiciones del formulario | `app/Http/Controllers/PortalFormularioController.php` (`ocultosPorDependencia`) |
| Agente conversacional | `app/Services/WhatsappAgent/` |
| Prompts | `prompt_actuales/fases/*.md`, `AgentePromptsSeeder` |
| Extracción | `app/Services/DocumentoExtraccion/` |
| Ingesta central | `EventoRecoleccionService` |
| Motor de reglas | `app/Services/Reglas/`, `DeterminacionFiscalService` |
| Parámetros por año | `ParametrosFiscalesSeeder`, `app/Support/ParametrosFiscales.php` |
| Enums de estado | `app/Enums/` (`FormState`, `FieldState`, `FaseConversacion`, `FilingStatus`, `TaxForm`) |
| Formulario del cliente | `resources/js/pages/portal/formulario.tsx` |
| Expediente del preparador | `resources/js/pages/clientes/show.tsx` |
| Legado n8n/Supabase | `SupabaseWhatsappConversationService`, `ClientIntakeSession` |
