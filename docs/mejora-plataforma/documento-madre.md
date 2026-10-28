# Documento madre — cómo debe funcionar la plataforma

28 oct. 2026 · Steven Herrera · **Borrador completo, en revisión**

> **Para qué sirve:** es la referencia única para hacer cambios en la plataforma. Junta lo que se decidió y lo que se aprendió en los demás documentos de esta carpeta. Si un cambio no encaja con lo que dice aquí, se discute primero y se actualiza este documento.
>
> **De dónde sale:**
> - [`analisis-plataforma-vs-proceso-negocio.md`](analisis-plataforma-vs-proceso-negocio.md): plataforma frente al proceso de negocio.
> - [`que-tiene-gts-hoy.md`](que-tiene-gts-hoy.md): qué está mal, qué se elimina y qué se agrega.
> - [`revision-documento-analisis-diseno.md`](revision-documento-analisis-diseno.md): revisión del documento de Juan Pablo, observaciones de Steven y puntos legales L1–L20.
> - [`ejemplo-recoleccion-empleado-con-dependiente.md`](ejemplo-recoleccion-empleado-con-dependiente.md): ejemplo con reglas del IRS.

> **Método (decidido por Steven, 28 oct.):** el documento se arma **desde cero, con el IRS como base**, como si todo lo que hay hoy en la plataforma estuviera mal. No se parte del catálogo ni de las pantallas actuales. La comparación con la plataforma se hace al final, en la sección 9.
>
> **Fuente de cada sección:**
> - **IRS:** qué se pregunta, qué anexos aplican y qué documentos se piden (secciones 3, 4 y 5).
> - **Normas y decisiones:** cómo opera el proceso. Sale de §7216, la FTC Safeguards Rule, Circular 230, las decisiones de Steven y la operación de GTS (secciones 1, 2, 6, 7 y 8). El IRS no define cómo trabaja una firma por dentro.

| # | Sección | Estado |
| --- | --- | --- |
| 1 | Alcance y principios | ✅ Borrador |
| 2 | El flujo objetivo | ✅ Borrador |
| 3 | Paso 1 — Entender al cliente: las preguntas | ✅ Bloques A–G + observaciones (tres niveles aprobados) |
| 4 | Paso 2 — De la respuesta al formulario: el mapa hacia el 1040 | ✅ Inventario completo + 7 fichas; las demás fichas quedan pendientes para después |
| 5 | Paso 3 — Pedir solo lo necesario: checklist de documentos | 🟡 Borrador |
| 6 | Paso 4 — Leer y validar los documentos | 🟡 Borrador |
| 7 | Paso 5 — Revisión del preparador y entrega a TaxWise | 🟡 Borrador |
| 8 | Seguridad y cumplimiento mínimos | 🟡 Borrador |
| 9 | Qué cambia en la plataforma (hoy → objetivo) y en qué orden | 🟡 Borrador |
| 10 | Decisiones pendientes | 🟡 Borrador |

---

## 1. Alcance y principios

### 1.1 Alcance

| | Dentro | Fuera (por ahora) |
| --- | --- | --- |
| **Clientes** | Personas naturales: asalariados (W-2), independientes con **Schedule C**, jubilados, inversiones, rentas (Schedule E) | Empresas (1065, 1120, 1120-S, 1041, 990) y no residentes (1040-NR), pendiente de que GTS confirme |
| **Firmas** | Una sola: **GTS** | Varias firmas: cuando el proceso esté validado y correcto |
| **Año fiscal** | 2025 (y 2026 para la temporada de 2027) | — |
| **Software de preparación** | **TaxWise**: ahí se calcula y se presenta | — |

### 1.2 Principios

1. **El IRS es la fuente de verdad.** Qué se le pregunta y qué se le pide a una persona depende de lo que define el IRS, no de un criterio propio. Cada pregunta y cada regla lleva su referencia al IRS. Se usan tres fuentes:
   - **Qué preguntar:** el Form 13614-C, la hoja de entrevista oficial del IRS.
   - **Qué significa cada respuesta:** las instrucciones del 1040 y de cada anexo, y las publicaciones del IRS (Pub. 501, Pub. 503…).
   - **Dónde termina cada dato:** las casillas y líneas del Form 1040 y de sus anexos.
2. **El 1040 es el formulario "front".** Es la carta de presentación de la declaración. Los anexos (Schedule 1, 2, 3, C, SE, 8812, EIC, 2441…) y los documentos (W-2, 1099, 1098, 1095-A…) sirven para llenarlo. Toda persona natural tiene 1040; los anexos se suman según su situación.
3. **Lo primero es entender al cliente.** Antes de pedir cualquier documento se hacen las preguntas que definen su situación. Cada respuesta abre o descarta preguntas y anexos.
4. **Pedir solo lo necesario.** Al cliente se le piden respuestas y documentos, nunca un anexo. Lo que viene en un documento (salarios, retenciones, montos del 1095-A) **no se le pregunta**: se lee del documento.
5. **La plataforma prepara el expediente y calcula un borrador completo del 1040; TaxWise presenta.** ✅ Decidido por Steven (28 oct.): se llenan **todas** las líneas, también las calculadas. Cada cálculo debe validarse contra el IRS (instrucciones, hojas de cálculo y tablas) y se versiona **por año fiscal**, porque los montos, topes y reglas cambian cada año. El borrador se compara con lo que da TaxWise.
6. **Todo se puede rastrear.** Cada dato sabe de dónde salió (respuesta del cliente o casilla de un documento) y quién lo cambió.
7. **Lenguaje del cliente.** Preguntas completas, en español sencillo, con Sí/No u opciones cuando aplica, y una ayuda que explica por qué se pregunta.
8. **Dos canales, una sola fuente.** El cliente puede responder por WhatsApp con el agente de IA o por el portal. Los dos leen las mismas preguntas y guardan en el mismo lugar. Los documentos y los datos sensibles solo van por el portal (9.6).

---

## 2. El flujo objetivo

```
1. Entender ──► 2. Preguntar según ──► 3. Pedir solo lo ──► 4. Leer y ──► 5. Revisar y
   al cliente      lo que responde        necesario            validar        entregar
   (perfil)        (reglas del IRS)       (checklist)          (documentos)   (hoja para TaxWise)
```

| Paso | Quién | Qué pasa | Resultado |
| --- | --- | --- | --- |
| 1. Entender | Cliente (por WhatsApp con el agente de IA o por el portal) | Responde preguntas de perfil: identidad, estado civil, dependientes, de dónde vino su dinero | Perfil del cliente (no una lista de formas) |
| 2. Preguntar más | Plataforma | Cada respuesta activa o descarta preguntas y anexos según el IRS | Anexos que aplican, cada uno con su motivo |
| 3. Pedir | Cliente | Ve un checklist solo con los documentos que soportan esos anexos; puede marcar "no aplica" con motivo | Documentos recibidos |
| 4. Leer y validar | Plataforma | Extrae los datos de cada documento con su evidencia y corre las reglas; lo que falla va a una cola de excepciones o vuelve al cliente como consulta | Expediente validado |
| 5. Revisar y entregar | Preparador | Revisa con checklist, resuelve excepciones y usa la hoja ordenada como el 1040 para cargar en TaxWise | Declaración lista para preparar y presentar |

**Estados del expediente:** Invitado → En entrevista → En recolección → Con excepciones / En consulta → Listo para preparar → En revisión → Preparado en TaxWise → Presentado. Cada cambio de estado guarda su fecha (sirve para medir horas por declaración).

---

## 3. Paso 1 — Entender al cliente: las preguntas

**Base:** el [Form 13614-C](https://www.irs.gov/pub/irs-pdf/f13614c.pdf) ([en español](https://www.irs.gov/pub/irs-pdf/f13614cs.pdf)), *Hoja de Admisión/Entrevista y Verificación de Calidad*. Es la hoja con la que los voluntarios del IRS (VITA) entrevistan a la persona antes de preparar su declaración. Se usó la revisión de octubre de 2026 (año fiscal 2026). Cada pregunta se completa con su regla en las instrucciones del 1040, la [Publicación 501](https://www.irs.gov/publications/p501) o el anexo que corresponda.

**Cómo se lee cada tabla:**
- **Pregunta:** cómo se le pregunta al cliente. El contenido es del 13614-C; la redacción es nuestra.
- **Respuesta:** Sí/No, opciones o dato.
- **Por qué (IRS):** la regla o casilla del IRS que la justifica.
- **Activa / descarta:** qué abre o qué cierra la respuesta.

**Tres ideas que deja el 13614-C:**
1. **No se le pregunta al cliente "¿cuál es tu estado civil para el IRS?" ni "¿tienes dependientes?".** Se le preguntan **hechos** (casado al 31 de diciembre, quién vivió contigo, quién pagó qué), y el estado civil para el IRS y los dependientes los determina el preparador o la plataforma con las reglas de la Pub. 501.
2. **Se pregunta por todas las personas que vivieron contigo o que mantuviste**, no solo por los "hijos". Así aparecen padres, sobrinos o hermanos que pueden calificar como dependientes.
3. **Casi todo se pregunta para el contribuyente y el cónyuge a la vez** (Usted / Cónyuge / No).

**Valores que cambian cada año** (se guardan como parámetros, no en el texto de la pregunta):

| Parámetro | Año fiscal 2025 | Año fiscal 2026 |
| --- | --- | --- |
| Mayor de 65 (deducción adicional) | Nació antes del 2 ene. 1961 | Nació antes del 2 ene. 1962 |
| Ingreso máximo de un pariente calificado (prueba de ingreso bruto) | USD 5.200 | USD 5.300 |

### Bloque A — Identidad y situación del contribuyente (y del cónyuge)

| # | Pregunta | Respuesta | Por qué (IRS) | Activa / descarta |
| --- | --- | --- | --- | --- |
| A1 | Nombre, fecha de nacimiento y ocupación | Dato | Encabezado y firma del 1040 (la firma pide la ocupación). 1040 línea 12d: "nació antes del 2 de enero de 1961" | Edad ≥ 65 → deducción estándar adicional (línea 12d) y **deducción mejorada para mayores** del Schedule 1-A (requiere SSN válido; se reduce si el ingreso pasa de USD 75.000, o 150.000 en conjunta). La ocupación sirve para la deducción de propinas (Schedule 1-A) |
| A2 | Dirección postal, teléfono y correo | Dato | Encabezado del 1040 | — |
| A3 | ¿Tienes número de Seguro Social (SSN) o ITIN? ¿Tu tarjeta dice "no válida para trabajar"? Sube la tarjeta o la carta | Opciones + documento | Encabezado del 1040. El 13614-C exige tarjeta SSN o carta ITIN de **todas** las personas de la declaración. **Crédito por hijos:** el contribuyente necesita SSN válido para trabajar; en conjunta basta con que **uno** de los cónyuges lo tenga y el otro tenga SSN o ITIN (instrucciones del Schedule 8812). **EITC:** el contribuyente **y** el cónyuge (si es conjunta) necesitan SSN válido (Pub. 596, regla 2) | Sin SSN válido → sin EITC. Sin SSN válido en ninguno de los cónyuges → sin crédito por hijos |
| A4 | Identificación con foto (licencia de conducir) | Documento | Lo pide el 13614-C para verificar identidad | — |
| A5 | ¿Otra persona puede reclamarte como dependiente en su declaración? | Sí / No | 1040 línea 12a: "Someone can claim you as a dependent". Pub. 596, reglas 10 y 12 | Sí → deducción estándar limitada y, en general, sin EITC |
| A6 | En 2025, ¿eras ciudadano de EE. UU.? | Usted / Cónyuge / No | 1040 vs. 1040-NR (Pub. 519) | No → A7 |
| A7 | ¿Estabas en EE. UU. con visa? | Usted / Cónyuge / No | Prueba de presencia sustancial (Pub. 519) | Visa → posible no residente: **alerta**, fuera del alcance actual |
| A8 | ¿Viviste o trabajaste en dos o más estados en 2025? | Sí / No | Declaraciones estatales (Florida no cobra renta personal) | Sí → **alerta** al preparador: posible declaración en otro estado |
| A9 | ¿Eras legalmente ciego? | Usted / Cónyuge / No | 1040 línea 12d ("Are blind") | Sí → deducción estándar adicional |
| A10 | ¿Estabas incapacitado total y permanentemente? | Usted / Cónyuge / No | Schedule R: aplica si tienes 65 o más, o si eres menor de 65, te retiraste por incapacidad total y permanente y recibiste ingresos gravables por incapacidad | Sí (o 65+) → evaluar Schedule R |
| A11 | ¿Eras estudiante a tiempo completo? | Usted / Cónyuge / No | Pub. 501 (puedes ser dependiente de tus padres) y créditos educativos | Sí → revisar A5 y la pregunta de educación (bloque E) |
| A12 | ¿El IRS te dio un PIN de protección de identidad (IP PIN)? | Usted / Cónyuge / No | 1040, sección de firma: casilla "Identity Protection PIN". Instrucciones del 1040: sin el IP PIN la firma electrónica es inválida y la declaración se rechaza; es nuevo cada año | Sí → pedir el número del año (se recupera en IRS.gov/IPPIN o al 800-908-4490). En conjunta, el de los dos |
| A13 | En 2025, ¿tuviste, recibiste, vendiste o cambiaste activos digitales (cripto, NFT)? | Usted / Cónyuge / No | 1040: pregunta obligatoria "Digital Assets" | Sí → pedir 1099-DA o registros → Form 8949 / Schedule D |
| A14 | ¿Quieres recibir las cartas del IRS en otro idioma? ¿Cuál? | Usted / Cónyuge / No + idioma | Schedule LEP del 1040 | Sí → Schedule LEP |
| A15 | ¿Quieres que USD 3 de tus impuestos vayan al Fondo de Campaña Electoral Presidencial? | Usted / Cónyuge / No | 1040: casilla "Presidential Election Campaign" (no cambia el impuesto) | — |

### Bloque B — Estado civil

| # | Pregunta | Respuesta | Por qué (IRS) | Activa / descarta |
| --- | --- | --- | --- | --- |
| B1 | Al 31 de diciembre de 2025, ¿cuál era tu estado civil? | Nunca me casé / Casado / Divorciado / Separado legalmente / Viudo | Pub. 501: el estado civil se decide por el **último día del año** | Casado → B2–B4. Divorciado → fecha del decreto final. Separado legalmente → fecha del decreto de manutención por separación. Viudo → año del fallecimiento del cónyuge |
| B2 | (Si casado) ¿Estabas casado el último día del año? | Sí / No | Pub. 501 | — |
| B3 | (Si casado) ¿Viviste separado de tu cónyuge **todos** los últimos 6 meses del año? | Sí / No | Pub. 501, "considerado no casado": declaras por separado, pagaste más de la mitad del hogar (B5), tu cónyuge no vivió en tu casa los últimos 6 meses y tu casa fue la vivienda principal de tu hijo, hijastro o hijo de crianza más de medio año | Se cumplen todas → puede declarar como **cabeza de familia** |
| B4 | (Si casado) Datos del cónyuge: nombre, fecha de nacimiento, ocupación, SSN o ITIN, y sus respuestas de A6–A13 | Dato | Encabezado del 1040 y casillas del cónyuge | Habilita la declaración conjunta o por separado |
| B5 | ¿Pagaste más de la mitad del costo de mantener tu hogar en 2025 (renta, intereses de la hipoteca, impuesto predial, seguro de la casa, reparaciones, servicios y comida consumida en casa)? | Sí / No | Pub. 501: requisito de cabeza de familia y de cónyuge sobreviviente calificado (hoja de trabajo 1) | Sí + persona calificada que vivió contigo más de medio año (bloque C) → cabeza de familia |
| B6 | (Si viudo) ¿En qué año falleció tu cónyuge? | Año | Pub. 501: falleció en 2025 → puede declarar conjunta; en 2023 o 2024 con hijo dependiente → cónyuge sobreviviente calificado | Define la opción de estado civil |

**Resultado del bloque B (lo determina el preparador o la plataforma, no el cliente):** el estado civil para el IRS, una de las 5 casillas del 1040: Soltero, Casado declarando conjuntamente, Casado declarando por separado, Cabeza de familia o Cónyuge sobreviviente calificado. Si es casado, la elección entre conjunta o por separado la decide el preparador con el cliente.

### Bloque C — Personas que vivieron contigo o que mantuviste

> **Pregunta de entrada:** "Escribe el nombre de **todas las personas que vivieron contigo** en 2025 (menos tu cónyuge) **y de toda persona que mantuviste** aunque no viviera contigo."

Por cada persona:

| # | Pregunta | Respuesta | Por qué (IRS) | Activa / descarta |
| --- | --- | --- | --- | --- |
| C1 | Nombre y apellido | Dato | 1040, sección Dependents, columna (1) | — |
| C2 | Fecha de nacimiento | Fecha | Pruebas de edad (Pub. 501 y anexos) | < 17 → crédito por hijos (Schedule 8812). < 13 → crédito por cuidado (Form 2441). Hijo calificado: < 19, o < 24 si estudia, y **menor que tú** (o que tu cónyuge si es conjunta); sin límite si está incapacitado |
| C3 | Parentesco contigo (hijo, hijastro, hijo de crianza, hermano, nieto, padre, ninguno…) | Opciones | Pub. 501: prueba de parentesco | Hijo, hermano o sus descendientes → posible **hijo calificado**. Otros → posible **pariente calificado** |
| C4 | ¿Cuántos meses vivió en tu casa en 2025? ¿Vivió en EE. UU.? | Número + Sí / No | Prueba de residencia (más de medio año; las ausencias temporales, como la escuela, cuentan como tiempo en casa). 1040, columna "vivió con usted más de la mitad de 2025… y en EE. UU." | ≤ 6 meses → no es hijo calificado (salvo excepciones de la Pub. 501) |
| C5 | ¿Estaba casado al 31 de diciembre? | Sí / No | Prueba de declaración conjunta | Si presentó declaración conjunta (salvo solo para reembolso) → no es dependiente |
| C6 | ¿Es ciudadano de EE. UU.? ¿Vive en EE. UU., Canadá o México? | Sí / No | Pub. 501: ciudadano, nacional o residente de EE. UU., o residente de Canadá o México (excepción para ciertos hijos adoptados). Schedule 8812: el crédito por otros dependientes exige ciudadano, nacional o residente de EE. UU. | Ninguna → no es dependiente. Solo residente de Canadá o México → dependiente, pero **sin** crédito por otros dependientes |
| C7 | ¿Era estudiante a tiempo completo? | Sí / No | Pub. 501: extiende la edad del hijo calificado hasta 24 | 1040, columna (6) |
| C8 | ¿Está incapacitado total y permanentemente? | Sí / No | Pub. 501: sin límite de edad | 1040, columna (6) |
| C9 | Número de identificación: ¿SSN válido para trabajar, ITIN o ATIN? Sube la tarjeta o la carta | Opciones + documento | 1040, columna (2). Schedule 8812: el crédito por hijos exige SSN válido para trabajar, emitido antes del vencimiento de la declaración. Pub. 596, regla 2: sin SSN válido del hijo, el EITC es solo el de "sin hijos" | SSN válido → crédito por hijos (hasta USD 2.200) y EITC con hijo. ITIN o ATIN → crédito por otros dependientes (USD 500) |
| C10 | ¿Se le emitió un IP PIN? | Sí / No | El 13614-C lo pregunta por cada persona | Sí → pedir el número del año |
| C11 | ¿Otra persona (por ejemplo, el otro padre) también puede reclamarlo o lo va a reclamar? ¿Tienes un Form 8332 o un acuerdo de custodia? | Sí / No + documento | Pub. 501: reglas de desempate y padres divorciados o separados (Form 8332) | Sí → **alerta** al preparador |
| C12 | ¿Esta persona pagó más de la mitad de su propia manutención? | Sí / No | Prueba de manutención del hijo calificado | Sí → no es hijo calificado |
| C13 | ¿Esta persona tuvo ingresos de menos de USD 5.200 en 2025? | Sí / No | Prueba de ingreso bruto del pariente calificado | No → no es pariente calificado |
| C14 | ¿Tú pagaste más de la mitad de la manutención de esta persona? | Sí / No | Prueba de manutención del pariente calificado | No → no es pariente calificado |

**Resultado del bloque C (lo determina el preparador o la plataforma):**
- Por persona: **hijo calificado**, **pariente calificado** o **no es dependiente**.
- Por dependiente: si es candidato a crédito por hijos o por otros dependientes (Schedule 8812), a EITC (Schedule EIC) y a crédito por cuidado (Form 2441, se confirma en el bloque E).
- Junto con B5: si el cliente puede ser **cabeza de familia**.

En el 13614-C, las columnas de C11 a C14 y la de "pagó más de la mitad del costo del hogar" las completa el **voluntario**, no la persona. En la plataforma se le preguntan al cliente con palabras simples, y la conclusión la saca el preparador.

**Documentos que salen de los bloques A a C:** tarjeta SSN o carta ITIN de cada persona de la declaración, identificación con foto del contribuyente y del cónyuge, carta del IP PIN (si aplica) y Form 8332 o acuerdo de custodia (si aplica).

**Verificación (28 oct.):** las reglas de los bloques A a C se contrastaron contra la [Pub. 501 de 2025](https://www.irs.gov/pub/irs-pdf/p501.pdf), las [instrucciones del 1040 de 2025](https://www.irs.gov/pub/irs-pdf/i1040gi.pdf), el [Form 1040 de 2025](https://www.irs.gov/pub/irs-pdf/f1040.pdf), las [instrucciones del Schedule 8812](https://www.irs.gov/pub/irs-pdf/i1040s8.pdf), la [Pub. 596 (EITC)](https://www.irs.gov/pub/irs-pdf/p596.pdf), el [Schedule R](https://www.irs.gov/pub/irs-pdf/f1040sr.pdf) y la [Pub. 519](https://www.irs.gov/pub/irs-pdf/p519.pdf).

### Bloque D — ¿De dónde recibiste dinero en 2025?

**Base:** página 2 del 13614-C (*"¿Recibió dinero de alguno de los siguientes en 2026?"*). El IRS pregunta por **fuentes de dinero**, no por formularios: es la misma idea de "¿cómo obtuviste tu dinero?" del documento de proceso.

**Regla de oro del bloque:** el cliente solo dice **Sí / No** y sube el documento. Los montos **no se le preguntan**: se leen del documento (W-2, 1099…). Si no hay documento (efectivo, apps de pago), se le pide el total y sus registros.

| # | Pregunta | Respuesta | Documento | Por qué (IRS) | Va a |
| --- | --- | --- | --- | --- | --- |
| D1 | ¿Recibiste salario como empleado (tiempo parcial o completo)? ¿En cuántos empleos? | Sí / No + número | Un **W-2 por empleo** | 1040 línea 1a: suma de la casilla 1 de todos los W-2 | 1040 líneas 1a y 25a (retención) |
| D2 | ¿Te pagaron horas extra? | Sí / No | W-2 (casilla 14) o constancia del empleador | Deducción por horas extra (Schedule 1-A). En 2025 el empleador **no estaba obligado** a separarlas en el W-2 | Schedule 1-A → 1040 línea 13b |
| D3 | ¿Recibiste propinas? ¿Le reportaste todas a tu empleador? | Sí / No + Sí / No | W-2 (casillas 7 y 8) y registro de propinas | Propinas no reportadas → Form 4137 → 1040 línea 1c. Deducción por propinas (Schedule 1-A) si la ocupación califica (A1) | 1040 línea 1c; Schedule 1-A |
| D4 | ¿Recibiste dinero de una cuenta de jubilación (IRA, 401(k)), pensión o anualidad? | Sí / No | 1099-R | IRA → líneas 4a/4b. Pensiones y anualidades → 5a/5b | 1040 líneas 4a–5b |
| D5 | ¿Recibiste beneficios por incapacidad (seguro, compensación por accidente de trabajo)? | Sí / No | 1099-R o W-2 | Instrucciones del 1040: pensión por incapacidad antes de la edad mínima de retiro → línea 1h; después → 5a/5b | 1040 línea 1h o 5a/5b |
| D6 | ¿Recibiste Seguro Social o jubilación ferroviaria? | Sí / No | SSA-1099 o RRB-1099 | Parte gravable según la hoja de las instrucciones | 1040 líneas 6a/6b |
| D7 | ¿Recibiste beneficios por desempleo? | Sí / No | 1099-G | Schedule 1 línea 7 | Schedule 1 → 1040 línea 8 |
| D8 | ¿Recibiste un reembolso de impuesto estatal o local sobre ingresos? ¿Detallaste deducciones el año pasado? | Sí / No + Sí / No | 1099-G | Solo es ingreso si el año anterior detallaste deducciones y dedujiste ese impuesto (Schedule 1 línea 1). Florida no cobra renta personal: aplica a quien vino de otro estado | Schedule 1 línea 1 |
| D9 | ¿Recibiste intereses o dividendos (cuenta bancaria, bonos, acciones)? | Sí / No | 1099-INT, 1099-DIV | Schedule B si el total pasa de USD 1.500 | 1040 líneas 2b, 3a, 3b |
| D10 | ¿Vendiste acciones, bonos, cripto o bienes inmuebles? ¿Declaraste una pérdida el año pasado? | Sí / No + Sí / No | 1099-B (con el estado del bróker), 1099-S, 1099-DA; declaración del año anterior | Form 8949 y Schedule D. La pérdida no usada del año pasado se arrastra | 1040 línea 7a |
| D11 | ¿Recibiste pensión de tu excónyuge (no manutención de hijos)? ¿En qué fecha se firmó el acuerdo de divorcio o separación? | Sí / No + fecha | Acuerdo o sentencia | Solo es ingreso si el acuerdo es del **31 dic. 2018 o antes** | Schedule 1 línea 2a |
| D12 | ¿Alquilaste tu casa o una habitación? ¿La usaste como vivienda y la alquilaste menos de 15 días? | Sí / No + Sí / No | Registro de ingresos y gastos; 1099-K o 1099-MISC si los hay | Pub. 527: si es tu vivienda y la alquilaste **menos de 15 días**, no se declara. Si no, Schedule E | Schedule E → Schedule 1 línea 5 |
| D13 | ¿Alquilaste bienes personales, como un vehículo? | Sí / No | Registros | Schedule 1 línea 8l (si no es un negocio) | Schedule 1 línea 8l |
| D14 | ¿Ganaste en juegos de azar o lotería? | Sí / No | W-2G | Schedule 1 línea 8b. Las pérdidas solo cuentan si se detallan deducciones (13614-C) | Schedule 1 línea 8b |
| D15 | ¿Te pagaron por trabajos por contrato o por cuenta propia? ¿Declaraste pérdida en ese negocio el año pasado? | Sí / No + Sí / No | 1099-NEC, 1099-K, 1099-MISC; registros de ingresos y gastos | Schedule C. Schedule SE si la ganancia neta es de **USD 400 o más** | Schedule C → Schedule 1 línea 3; Schedule SE |
| D16 | ¿Ganaste dinero con apps de transporte o entregas (Uber, DoorDash…)? | Sí / No | 1099-K o 1099-NEC si llegan; resumen anual de la app | Es negocio propio (Schedule C). En 2025 la app solo manda el 1099-K si pasaste de **USD 20.000 y 200 transacciones**: el ingreso se declara aunque no llegue | Schedule C (D15) |
| D17 | ¿Recibiste otro dinero? (pagos en efectivo, servicio de jurado, premios, activos digitales, regalías, beneficios por huelga) | Sí / No + cuál y cuánto | El que exista; si no, registro propio | Efectivo de un negocio → Schedule C. Jurado → 8h. Premios → 8i. Activos digitales recibidos como ingreso → 8v. Regalías → Schedule E. Otros → 8z | Schedule 1 o Schedule C/E |

**Resultado del bloque D:** la lista de **fuentes de ingreso** del cliente (su perfil: asalariado, independiente, mixto, jubilado, inversionista, arrendador) y los anexos que se activan: Schedule 1, B, C, SE, D/8949, E, 1-A y Form 4137.

**Documentos que salen del bloque D:** uno por cada fuente marcada (W-2 de cada empleo, cada 1099, SSA-1099, W-2G…), más registros propios cuando no hay formulario (efectivo, apps, alquileres) y la declaración del año anterior si hubo pérdidas que se arrastran.

**Verificación (28 oct.), bloque D:** líneas del [Form 1040 de 2025](https://www.irs.gov/pub/irs-pdf/f1040.pdf) y del [Schedule 1 de 2025](https://www.irs.gov/pub/irs-pdf/f1040s1.pdf); [instrucciones del 1040 de 2025](https://www.irs.gov/pub/irs-pdf/i1040gi.pdf) (1099-K USD 20.000 y 200, Schedule B > USD 1.500, Schedule SE ≥ USD 400, Form 4137, pensiones por incapacidad, pensión del excónyuge hasta 2018, reembolso estatal); [Pub. 527](https://www.irs.gov/pub/irs-pdf/p527.pdf) (alquiler < 15 días). La regla de horas extra de 2025 se verificó antes en la [página del IRS sobre horas extra](https://www.irs.gov/newsroom/questions-and-answers-about-the-new-deduction-for-qualified-overtime-compensation).

### Bloque E — Gastos que pagaste en 2025

**Base:** página 3 del 13614-C (*"¿Pagó alguno de los siguientes gastos…?"*). El 13614-C separa dos grupos:
- **E1–E4 solo sirven si conviene detallar deducciones (Schedule A).** Casi siempre gana la deducción estándar de 2025 (USD 15.750 soltero, 31.500 conjunta, 23.625 cabeza de familia). Se preguntan para que el preparador compare; si no hay hipoteca ni gastos grandes, no hace falta pedir los soportes.
- **E5–E9 cuentan aunque no se detalle.**

| # | Pregunta | Respuesta | Documento | Por qué (IRS) | Va a |
| --- | --- | --- | --- | --- | --- |
| E1 | ¿Pagaste intereses de hipoteca? | Sí / No | 1098 | Schedule A | Schedule A → 1040 línea 12e |
| E2 | ¿Pagaste impuestos estatales o locales: predial, impuesto sobre las ventas, etc.? | Sí / No | Recibo del predial; registros | Schedule A: el total de impuestos estatales y locales tiene tope de **USD 40.000** (20.000 casado por separado) en 2025. En Florida no hay impuesto estatal a la renta, así que se puede usar el impuesto sobre las ventas | Schedule A |
| E3 | ¿Pagaste gastos médicos, dentales o de medicamentos recetados? | Sí / No | Facturas y recibos | Schedule A: solo cuenta lo que pasa del **7,5 % del AGI** | Schedule A |
| E4 | ¿Hiciste donaciones a organizaciones benéficas? | Sí / No | Recibos de la organización | Schedule A | Schedule A |
| E5 | ¿Pagaste intereses de un préstamo estudiantil? | Sí / No | 1098-E | Deducción de hasta USD 2.500; no aplica a casados por separado, ni si otra persona te puede reclamar (A5), ni por encima del límite de ingresos | Schedule 1 línea 21 |
| E6 | ¿Pagaste el cuidado de un hijo o dependiente para poder trabajar? | Sí / No | Recibo del proveedor con su **nombre, dirección y SSN o EIN**, y el monto | Form 2441 (dependiente < 13, bloque C). Si el proveedor trabajaba en tu casa, revisar Schedule H | Form 2441 → Schedule 3 línea 2 → 1040 línea 20 |
| E7 | ¿Hiciste aportes a una cuenta de jubilación (IRA tradicional, Roth IRA, 401(k))? | Sí / No + tipo | Estado de cuenta o 5498; el 401(k) ya sale en el W-2 (casilla 12) | IRA tradicional → deducción (limitada si tienes plan en el trabajo: W-2 casilla 13). Cualquier aporte puede dar el crédito por ahorro para la jubilación | Schedule 1 línea 20; Form 8880 → Schedule 3 línea 4 |
| E8 | ¿Eres maestro, ayudante de maestro u otro educador y compraste útiles escolares? | Sí / No | Recibos | Deducción de hasta USD 300 | Schedule 1 línea 11 |
| E9 | ¿Pagaste pensión a tu excónyuge (no manutención de hijos)? ¿En qué fecha se firmó el acuerdo? | Sí / No + fecha | Acuerdo; SSN del excónyuge | Solo se deduce si el acuerdo es del 31 dic. 2018 o antes; pide el SSN de quien la recibe | Schedule 1 líneas 19a–19c |

### Bloque F — Situaciones de 2025

**Base:** página 3 del 13614-C (*"¿Ocurrió alguna de las siguientes situaciones durante 2026?"*).

| # | Pregunta | Respuesta | Documento | Por qué (IRS) | Va a |
| --- | --- | --- | --- | --- | --- |
| F1 | ¿Tú o alguien de tu familia tomó clases (universidad, escuela técnica, cursos del trabajo)? | Sí / No + quién | 1098-T; estado de cuenta de la escuela | Créditos educativos (Form 8863). Una beca no usada en matrícula puede ser ingreso | Schedule 3 línea 3; Schedule 1 línea 8r |
| F2 | ¿Vendiste tu casa? | Sí / No | 1099-S; datos de compra y venta | Pub. 523: se puede excluir hasta **USD 250.000** de ganancia (500.000 en conjunta) si se cumplen las condiciones | Form 8949 / Schedule D si no se excluye todo |
| F3 | ¿Tuviste una cuenta de ahorros para gastos médicos (HSA)? | Sí / No | 5498-SA, 1099-SA; aportes del empleador en el W-2 (casilla 12, código W) | Form 8889 | Schedule 1 líneas 13 y 8f |
| F4 | ¿Compraste un seguro médico en el Mercado (healthcare.gov)? | Sí / No | **1095-A** de cada póliza | Form 8962: concilia el crédito por prima. Puede dar más crédito o exigir devolver parte del adelanto. Muy común en familias de GTS | Schedule 3 línea 9 o Schedule 2 línea 1a |
| F5 | ¿Compraste un vehículo **nuevo** en 2025, con préstamo, para uso personal? | Sí / No | Estado del préstamo con los intereses pagados (el 13614-C lo llama 1098-VLI) y el VIN | Schedule 1-A: deducción de hasta **USD 10.000** de intereses; el vehículo debe tener ensamblaje final en EE. UU. y se limita si el ingreso pasa de USD 100.000 (200.000 en conjunta) | Schedule 1-A → 1040 línea 13b |
| F6 | ¿Un banco o prestamista te canceló o perdonó una deuda (tarjeta, hipoteca, otra)? | Sí / No | 1099-C | Ingreso por deuda cancelada, salvo exclusiones como insolvencia (Pub. 4681) | Schedule 1 línea 8c |
| F7 | ¿Tuviste pérdidas por un desastre declarado por el gobierno federal (por ejemplo, un huracán)? | Sí / No | Registros de la pérdida y del seguro | Form 4684. Muy relevante en Florida | Form 4684 → Schedule A |
| F8 | ¿El IRS te negó en un año anterior un crédito (EITC, crédito por hijos, crédito educativo o cabeza de familia)? ¿Qué año y por qué? | Sí / No + año y motivo | La carta del IRS | Puede exigir el **Form 8862** para volver a pedir el crédito | Form 8862 |
| F9 | ¿Recibiste alguna carta o factura del IRS? | Sí / No | La carta | El preparador debe revisarla antes de preparar | Revisión del preparador |

**Verificación (28 oct.), bloques E y F:** [instrucciones del Schedule A de 2025](https://www.irs.gov/pub/irs-pdf/i1040sca.pdf) (tope de USD 40.000, 7,5 % del AGI); [instrucciones del 1040 de 2025](https://www.irs.gov/pub/irs-pdf/i1040gi.pdf) (préstamo estudiantil, educadores USD 300, Form 8880, intereses del vehículo USD 10.000 y ensamblaje en EE. UU., deuda cancelada, Form 8862); [Schedule 2](https://www.irs.gov/pub/irs-pdf/f1040s2.pdf) y [Schedule 3](https://www.irs.gov/pub/irs-pdf/f1040s3.pdf) de 2025 (líneas de 2441, 8863, 8880 y 8962); [Pub. 523](https://www.irs.gov/pub/irs-pdf/p523.pdf) (venta de la casa).

### Bloque G — Pagos, reembolso y año anterior

**Base:** página 1 del 13614-C (cómo recibir el reembolso o pagar el saldo) y final de la página 3 (pagos estimados, declaración del año pasado).

| # | Pregunta | Respuesta | Documento | Por qué (IRS) | Va a |
| --- | --- | --- | --- | --- | --- |
| G1 | ¿Hiciste pagos estimados al IRS en 2025, o aplicaste a 2025 parte del reembolso del año pasado? | Sí / No | Comprobantes de pago (fechas y montos); declaración de 2024 | Se suman como pagos ya hechos | 1040 línea 26 |
| G2 | ¿Pediste una extensión (Form 4868) y pagaste algo con ella? | Sí / No | Comprobante del pago | Pago con la solicitud de extensión | Schedule 3 línea 10 → 1040 línea 31 |
| G3 | Si te toca reembolso, ¿cómo lo quieres? **Depósito directo** / Dividir entre varias cuentas / Aplicarlo a 2026 / Otro | Opciones | Datos bancarios: número de ruta, número de cuenta, tipo (Checking / Savings) | Desde octubre de 2025 el IRS **dejó de emitir, por lo general, cheques de papel** para reembolsos: el depósito directo es la vía normal. Dividir entre cuentas → Form 8888 | 1040 líneas 35a–35d o 36 |
| G4 | Si te toca pagar, ¿cómo quieres pagar? Débito de cuenta bancaria / Otro | Opciones | Datos bancarios si es débito | Saldo a pagar | 1040 línea 37 |
| G5 | ¿Tienes la declaración del año pasado (2024)? | Sí / No | Copia de la declaración de 2024 | La pide el 13614-C. Sirve para arrastres (pérdidas de D10 y D15), el reembolso aplicado (G1), la comparación con el año anterior y si el cliente detalló deducciones (D8) | Revisión del preparador |

**Resultado del bloque G:** los pagos ya hechos, el destino del reembolso o la forma de pago, y la declaración anterior para comparar.

**Verificación (28 oct.), bloque G:** [Form 1040 de 2025](https://www.irs.gov/pub/irs-pdf/f1040.pdf) (líneas 26, 31, 35a–37), [Schedule 3](https://www.irs.gov/pub/irs-pdf/f1040s3.pdf) (línea 10) e [instrucciones del 1040 de 2025](https://www.irs.gov/pub/irs-pdf/i1040gi.pdf) (Form 8888 y fin de los cheques de papel desde octubre de 2025).

### Observaciones de Steven sobre la sección 3

**1. Entender al cliente no puede ser un proceso largo.**
> "Son alrededor de 30 preguntas o más… no sé si todas las preguntas son necesarias… hay preguntas que harán que el usuario no responda otras… no quiero que el proceso de entender al usuario sea algo extremadamente largo."

Si se hicieran una por una, las tablas A–G suman **61 preguntas, más 14 por cada persona del bloque C**. Las tablas son el **inventario completo** de lo que el IRS puede necesitar; **no son el orden ni la cantidad de pantallas** que ve el cliente.

✅ **Aprobado por Steven (28 oct.): los tres niveles.** El mismo 13614-C ya resuelve esto. No es un cuestionario pregunta por pregunta: es una hoja de **casillas** ("marque solo las casillas que le correspondan"). Se propone hacer lo mismo en tres niveles:

| Nivel | Qué es | Quién lo ve |
| --- | --- | --- |
| **1. Preguntas de entrada** | Unas **8 pantallas** que todos responden, casi todas de casillas o de opciones | Todos |
| **2. Preguntas de seguimiento** | Se abren solo si la respuesta del nivel 1 las activa | Solo a quien le aplica |
| **3. Lo que no se pregunta** | Lo que se lee de un documento, se toma del año anterior o lo concluye el preparador | Nadie: el cliente no lo ve |

**Las 8 pantallas de entrada:**
1. **Sube tus documentos de identidad.** La identificación con foto y la tarjeta del Seguro Social o la carta ITIN ya traen el nombre, la fecha de nacimiento, el número y la dirección (A1–A4). Solo se confirman.
2. **¿Alguna de estas aplica a ti o a tu cónyuge?** Una sola lista con "Ninguna": no ciudadano o con visa, viviste en otro estado, ciego, incapacitado, estudiante, tienes IP PIN, tuviste cripto (A6–A13).
3. **¿Cuál era tu estado civil al 31 de diciembre?** (B1). Las preguntas B2–B6 solo aparecen si estaba casado o es viudo.
4. **¿Alguien vivió contigo o mantuviste a alguien en 2025?** Si la respuesta es No, se salta todo el bloque C.
5. **¿De dónde recibiste dinero en 2025?** Una sola lista de casillas (D1–D17). Solo lo que se marca abre su seguimiento: "¿cuántos empleos?", "sube tu W-2".
6. **¿Pagaste alguno de estos gastos?** Una lista (E1–E9). Hipoteca, impuestos, gastos médicos y donaciones van en una sola casilla ("gastos grandes de casa, salud o donaciones"), porque solo importan si conviene detallar.
7. **¿Te pasó algo de esto en 2025?** Una lista (F1–F9).
8. **Pagos y reembolso** (G1–G5), al final.

**Seguimientos que se recortan dentro del bloque C:**
- Para un **hijo** que vivió contigo todo el año, se preguntan nombre, fecha de nacimiento, parentesco, número de identificación y si es estudiante o tiene una incapacidad. Las pruebas de manutención e ingreso (C11–C14) **no se preguntan**.
- C11–C14 solo aparecen para **otros parientes**, para quien vivió con el cliente 6 meses o menos, o cuando hay otro padre.

**Lo que pasa a nivel 3 (no se le pregunta al cliente):**
- Datos que trae un documento: nombre, número, dirección, montos de ingresos y retenciones.
- Para un cliente que vuelve: lo que ya está en la declaración del año anterior. Se le pregunta solo "¿cambió algo?".
- Conclusiones: estado civil para el IRS, quién es dependiente, qué créditos aplican.
- Preguntas de baja importancia con valor por defecto: idioma de las cartas del IRS (A14) y el fondo presidencial (A15).

**Ejemplo (María, empleada soltera con un hijo de 8 años que va a guardería; ver el ejemplo de recolección):**

| Pantalla | Respuestas | Archivos |
| --- | --- | --- |
| 1. Documentos de identidad | 1 (confirma los datos leídos) | Identificación con foto, tarjeta del Seguro Social |
| 2. ¿Alguna aplica? | 1 (marca "Ninguna") | — |
| 3. Estado civil | 2 (soltera; pagó más de la mitad del hogar) | — |
| 4. Personas que vivieron contigo | 1 + 7 del hijo (nombre, fecha de nacimiento, parentesco, meses en casa y en EE. UU., número, estudiante o incapacidad, ¿lo reclama el otro padre?) | Tarjeta del Seguro Social del hijo |
| 5. ¿De dónde recibiste dinero? | 2 (marca "salario"; ¿cuántos empleos?) | W-2 |
| 6. ¿Pagaste alguno de estos gastos? | 2 (marca "guardería"; confirma los datos del proveedor) | Recibo de la guardería |
| 7. ¿Te pasó algo de esto? | 1 (marca "Ninguna") | — |
| 8. Pagos y reembolso | 4 (pagos estimados, cómo quiere el reembolso, cuenta bancaria, ¿tiene la declaración de 2024?) | Declaración de 2024 |
| **Total** | **≈ 21 respuestas** | **≈ 6 archivos** |

- Unas 10 respuestas son de **un toque**: Sí/No, una opción o "Ninguna". Unas 11 son **datos cortos**: nombre, fecha, un número.
- Para un **cliente que vuelve**, los datos del hijo y la cuenta bancaria vienen del año anterior y solo se confirman: quedan **≈ 12 respuestas**.
- Comparación: **≈ 21 respuestas** frente a 75 preguntas si se hicieran todas una por una.

**2. Entender al cliente sirve para saber qué formularios pedir y cuáles llenar.**
> "La idea de identificar al usuario también es para entender qué tipo de forms y/o schedules se le deben pedir o qué forms y/o schedules se deben llenar. Con esto se pide el archivo que se necesita y se va llenando el 1040, o con los datos se va llenando el archivo para con eso llenar el 1040."

Hay dos tipos de formularios, y conviene no mezclarlos:

| Tipo | Ejemplos | Quién lo produce | Qué se hace |
| --- | --- | --- | --- |
| **Documentos que se le piden al cliente** | W-2, 1099, 1098, 1095-A, SSA-1099 | Un tercero (empleador, banco, Marketplace…) | Se **piden** y se **leen** |
| **Formularios que se llenan** | Schedule 1, 2, 3, A, B, C, D, E, SE, 1-A, 8812, EIC, Form 2441, 8962, 8863, 8889 | El preparador o la plataforma | Se **llenan** con los datos de los documentos y las respuestas |

La cadena completa es:

```
Respuesta ──► activa un formulario que se llena ──► que necesita documentos ──► cuyos datos llenan el formulario ──► que lleva un total a una línea del 1040
```

Ejemplo: "pagué guardería" + hijo de 8 años → se activa el **Form 2441** → se pide el **recibo del proveedor** (y el W-2 por la casilla 10) → con esos datos se llena el 2441 → su línea 11 pasa al Schedule 3, línea 2 → y de ahí al **1040, línea 20**.

Las tablas A–G ya traen las columnas "Activa / descarta" y "Va a". La **sección 4** las junta en un solo mapa ordenado por formulario: para cada uno, qué respuestas lo activan, qué documentos lo alimentan, qué datos se toman y a qué línea del 1040 llega. La **sección 5** saca de ese mapa el checklist de documentos que ve el cliente.

## 4. Paso 2 — De la respuesta al formulario: el mapa hacia el 1040

**Para qué sirve:** es la columna vertebral de la plataforma. Para cada formulario que se llena dice **qué respuestas lo activan, qué documentos lo alimentan, qué dato se toma de cada casilla y a qué línea del 1040 llega**. De aquí salen el checklist del cliente (sección 5), la extracción de documentos (sección 6) y la hoja para TaxWise (sección 7).

**Cómo se leen las fichas:**
- **Se activa si:** las respuestas de la sección 3 que lo abren (A1, C2, D15…).
- **Documentos:** lo que se le pide al cliente.
- **Datos:** la casilla del documento → la línea del formulario.
- **Llega al 1040:** el camino del total hasta el 1040.

```
                       ┌─► Schedule 1 (ingresos y ajustes) ──► 1040 líneas 8 y 10
Documentos ─► Formularios que se llenan ─┼─► Schedule 2 (otros impuestos) ──► 1040 línea 23
(W-2, 1099,   (C, SE, 8812, 2441, EIC,   ├─► Schedule 3 (créditos y pagos) ──► 1040 líneas 20 y 31
 1095-A…)      8962…)                    └─► directo al 1040 (8812 → 19 y 28; EIC → 27a)
```

> ⚠️ **Las casillas cambian de un año a otro.** Ejemplo: en el 1099-NEC de 2025 la compensación va en la **casilla 1**; en la revisión de diciembre de 2026 pasa a la **casilla 1a**. Por eso este mapa se guarda **por año fiscal**, igual que los parámetros.

### 4.0 Inventario: todo lo que se pide y todo lo que se llena para una persona natural

Sale de las preguntas A–G de la sección 3. La columna **Ficha** indica si el formulario ya tiene su detalle casilla → línea en esta sección (✅) o si queda **pendiente para después** (⏳).

**A. Documentos que se le pueden pedir al cliente**

| Grupo | Documento | Lo activa |
| --- | --- | --- |
| Identidad | Identificación con foto (contribuyente y cónyuge) | Siempre (A4) |
| | Tarjeta del Seguro Social, carta ITIN o ATIN de cada persona de la declaración | Siempre (A3, C9) |
| | Número del IP PIN del año | A12, C10 |
| | Form 8332 o acuerdo de custodia | C11 |
| Trabajo | W-2 (uno por empleo) | D1 |
| | Constancia de horas extra o de propinas | D2, D3 |
| Negocio propio | 1099-NEC, 1099-K, 1099-MISC | D15, D16 |
| | Registros de ingresos y gastos (extractos, recibos, millaje, oficina en casa) | D15–D17 |
| Jubilación y beneficios | 1099-R | D4, D5 |
| | SSA-1099 o RRB-1099 | D6 |
| | 1099-G (desempleo o reembolso estatal) | D7, D8 |
| Inversiones y ventas | 1099-INT, 1099-DIV | D9 |
| | 1099-B (con el estado del bróker), 1099-S, 1099-DA | D10, A13 |
| Otros ingresos | Acuerdo de divorcio o separación (pensión del excónyuge) | D11, E9 |
| | Registros de alquiler | D12, D13 |
| | W-2G | D14 |
| | 1099-C | F6 |
| Gastos y deducciones | 1098 (hipoteca), recibo del impuesto predial, recibos médicos, recibos de donaciones | E1–E4 |
| | 1098-E | E5 |
| | Recibo del proveedor de cuidado (nombre, dirección, SSN o EIN, monto) | E6 |
| | Estado de cuenta o 5498 de la IRA | E7 |
| | Recibos de útiles del educador | E8 |
| Situaciones del año | 1098-T y estado de cuenta de la escuela | F1 |
| | 1099-S y datos de compra y venta de la casa | F2 |
| | 5498-SA, 1099-SA | F3 |
| | **1095-A (uno por póliza)** | F4 |
| | Estado del préstamo del vehículo con los intereses, y el VIN | F5 |
| | Registros de pérdidas por desastre y del seguro | F7 |
| | Carta del IRS | F8, F9 |
| Pagos y año anterior | Comprobantes de pagos estimados y del pago con la extensión | G1, G2 |
| | Datos bancarios (número de ruta y de cuenta) | G3, G4 |
| | Declaración del año anterior | G5 |

**B. Formularios que se llenan (preparador o plataforma)**

| Formulario | Para qué | Lo activa | Ficha |
| --- | --- | --- | --- |
| **Form 1040** | La declaración ("front") | Siempre | — |
| Schedule 1 | Ingresos adicionales y ajustes | D7–D17, E5, E7–E9, F3, F6 | Conector |
| Schedule 2 | Otros impuestos | Schedule SE, devolución del 8962 | Conector |
| Schedule 3 | Otros créditos y pagos | 2441, 8863, 8880, 8962, extensión | Conector |
| Schedule C | Negocio propio | D15–D17 | ✅ 4.2 |
| Schedule SE | Impuesto de autoempleo | Schedule C ≥ USD 400 | ✅ 4.3 |
| Schedule 8812 | Crédito por hijos y otros dependientes | Bloque C | ✅ 4.4 |
| Form 2441 | Crédito por cuidado | E6, W-2 casilla 10 | ✅ 4.5 |
| Schedule EIC | Crédito por ingreso del trabajo | Ingreso del trabajo + bloque C | ✅ 4.6 |
| Form 8962 | Crédito por prima del Marketplace | F4 (1095-A) | ✅ 4.7 |
| Schedule 1-A | Propinas, horas extra, intereses del vehículo, deducción para mayores | D2, D3, F5, A1 | ⏳ |
| Schedule A | Deducciones detalladas | E1–E4, F7 | ⏳ |
| Schedule B | Intereses y dividendos (> USD 1.500) | D9 | ⏳ |
| Schedule D y Form 8949 | Ventas de acciones, cripto o inmuebles | D10, A13, F2 | ⏳ |
| Schedule E | Alquileres y regalías | D12, D17 | ⏳ |
| Schedule H | Empleados domésticos | E6 (proveedor en casa) | ⏳ |
| Schedule R | Crédito para mayores o incapacitados | A1, A10 | ⏳ |
| Schedule LEP | Idioma de las cartas del IRS | A14 | ⏳ |
| Form 4137 | Propinas no reportadas al empleador | D3, W-2 casilla 8 | ⏳ |
| Form 4684 | Pérdidas por desastre | F7 | ⏳ |
| Form 8829 | Oficina en casa | D15 | ⏳ |
| Form 8862 | Volver a pedir un crédito negado | F8 | ⏳ |
| Form 8863 | Créditos educativos | F1 | ⏳ |
| Form 8880 | Crédito por ahorro para la jubilación | E7 | ⏳ |
| Form 8888 | Dividir el reembolso entre cuentas | G3 | ⏳ |
| Form 8889 | Cuenta de ahorros médicos (HSA) | F3, W-2 casilla 12 código W | ⏳ |

### 4.1 W-2 → 1040 (directo)

El W-2 no activa un formulario: sus datos van directo al 1040, y algunas casillas abren otros formularios.

- **Se activa si:** D1 (salario).
- **Documentos:** un W-2 por empleo.

| Casilla del W-2 (2025) | Va a |
| --- | --- |
| 1 — Salarios, propinas y otra compensación | 1040 línea 1a (suma de todos los W-2) |
| 2 — Impuesto federal retenido | 1040 línea 25a |
| 3 y 5 — Salarios para Seguro Social y Medicare | Control: casilla 3 ≤ tope del Seguro Social (regla R-04). Medicare adicional si se pasa del umbral |
| 7 — Propinas sujetas al Seguro Social | Deducción de propinas (Schedule 1-A), si la ocupación califica |
| 8 — Propinas asignadas | Form 4137 → 1040 línea 1c |
| 10 — Beneficios de cuidado de dependientes | Form 2441, Parte III → la parte gravable va a la línea 1e |
| 12 — Códigos (D, E, W, DD…) | W → Form 8889 (HSA). D y E son aportes al 401(k)/403(b): informativos |
| 13 — Plan de retiro | Limita la deducción del IRA (E7) |
| 14 — Otros | Puede traer horas extra o propinas calificadas → Schedule 1-A |

### 4.2 Schedule C — Ganancia o pérdida de un negocio

- **Se activa si:** D15 (trabajo por contrato o por cuenta propia), D16 (apps) o D17 (efectivo de un negocio).
- **Documentos:** 1099-NEC, 1099-K y 1099-MISC, más los registros de ingresos y gastos (extractos, recibos). Si hay vehículo, el millaje; si hay oficina en casa, sus medidas y gastos.

| Dato | Línea del Schedule C |
| --- | --- |
| Actividad, nombre y dirección del negocio, método contable | Encabezado A–F |
| ¿Participó materialmente? ¿Hizo pagos que exigen 1099? | Preguntas G, I y J |
| 1099-NEC (2025: casilla 1) + 1099-K (casilla 1a) + ingresos sin formulario | Línea 1, ingresos brutos |
| Costo de la mercancía vendida (si vende productos) | Línea 4 (Parte III) |
| Gastos por categoría: vehículo, contratistas, seguros, renta, materiales, servicios, etc. | Líneas 8 a 27, total en la 28 |
| Oficina en casa | Línea 30 (Form 8829 o método simplificado) |
| **Ganancia o pérdida neta** | **Línea 31** |

- **Llega al 1040:** línea 31 → Schedule 1, línea 3 → 1040, línea 8. También abre el **Schedule SE** (4.3).

### 4.3 Schedule SE — Impuesto de autoempleo

- **Se activa si:** la línea 31 del Schedule C es de **USD 400 o más**.
- **Documentos:** ninguno nuevo; usa el Schedule C.

| Dato | Línea |
| --- | --- |
| Ganancia neta del Schedule C, línea 31 | Schedule SE, línea 2 |
| Base: línea 3 × 92,35 % | Línea 4a |
| **Impuesto de autoempleo** | **Línea 12** → Schedule 2, línea 4 → 1040, línea 23 |
| **Mitad del impuesto, deducible** | **Línea 13** → Schedule 1, línea 15 (baja el ingreso ajustado) |

### 4.4 Schedule 8812 — Crédito por hijos y por otros dependientes

- **Se activa si:** hay al menos un dependiente (bloque C).
- **Documentos:** tarjeta del Seguro Social, ITIN o ATIN de cada dependiente; los W-2 o el Schedule C para el ingreso del trabajo.

| Dato | Línea del 8812 |
| --- | --- |
| Ingreso ajustado (1040, línea 11a) | Línea 1 |
| Número de hijos **menores de 17 con SSN válido** (C2, C9) | Línea 4 → × USD 2.200 en la línea 5 |
| Número de otros dependientes (con ITIN o ATIN, o de 17 años o más) | Línea 6 → crédito de USD 500 |
| **Crédito por hijos y otros dependientes** | **Línea 14** → 1040, línea 19 |
| Ingreso del trabajo (W-2 casilla 1 + ganancia del Schedule C) | Línea 18a |
| **Parte reembolsable (ACTC)** | **Línea 27** → 1040, línea 28 |

### 4.5 Form 2441 — Crédito por cuidado de hijos y dependientes

- **Se activa si:** E6 (pagó cuidado para poder trabajar) + un dependiente menor de 13 o incapacitado (C2, C8). También si el W-2 trae la casilla 10.
- **Documentos:** recibo del proveedor; W-2 (casilla 10).

| Dato | Parte del 2441 |
| --- | --- |
| Nombre, dirección y **SSN o EIN del proveedor**, y el monto pagado | Parte I |
| Nombre y SSN de cada persona cuidada, y los gastos | Parte II |
| W-2 casilla 10 | Parte III |
| **Crédito** | **Línea 11** → Schedule 3, línea 2 → línea 8 → 1040, línea 20 |

### 4.6 Schedule EIC — Crédito por ingreso del trabajo

- **Se activa si:** hay ingreso del trabajo (W-2 o Schedule C), SSN válido del contribuyente (y del cónyuge en conjunta), y el ingreso está por debajo del límite. El Schedule EIC se llena si hay **hijos calificados**; sin hijos, el crédito igual puede aplicar, solo con la hoja de las instrucciones.
- **Documentos:** ninguno nuevo; usa los datos del bloque C.

| Dato | Línea del Schedule EIC |
| --- | --- |
| Nombre del hijo (C1) | Línea 1 |
| SSN del hijo (C9) | Línea 2 |
| Año de nacimiento (C2) | Línea 3 |
| Estudiante o incapacitado (C7, C8), si es mayor | Línea 4 |
| Parentesco (C3) | Línea 5 |
| Meses que vivió contigo en EE. UU. (C4) | Línea 6 |
| **Crédito** (se calcula con la hoja de las instrucciones) | → 1040, línea 27a |

### 4.7 1095-A → Form 8962 — Crédito por prima del Marketplace

El **1095-A** es el documento que manda el Marketplace; el **8962** es el formulario que se llena con él. Es de los casos más comunes en GTS.

- **Se activa si:** F4 (compró seguro en el Marketplace). **Es obligatorio** si hubo adelantos del crédito (columna C del 1095-A).
- **Documentos:** **un 1095-A por cada póliza.**

| Casilla del 1095-A (2025) | Va a |
| --- | --- |
| Parte I: póliza, aseguradora, fechas de inicio y fin | Identifica la póliza (8962, Parte IV si se comparte) |
| Parte II: personas cubiertas | Debe cuadrar con el tamaño de la familia fiscal (8962, línea 1) |
| Parte III, columna **A** — Primas mensuales | 8962, columna (a) |
| Parte III, columna **B** — Prima del plan de referencia (SLCSP) | 8962, columna (b) |
| Parte III, columna **C** — Adelanto mensual del crédito | 8962, columna (f) |
| Línea 33 — Totales del año | 8962, línea 11 (si todos los meses son iguales) |

| Dato que no viene en el 1095-A | Línea del 8962 |
| --- | --- |
| Tamaño de la familia fiscal (bloques B y C) | Línea 1 |
| Ingreso ajustado modificado (del 1040) | Líneas 2a–3 |
| Línea de pobreza federal (tabla de las instrucciones) | Líneas 4–5 |

- **Llega al 1040:**
  - Si el crédito final es mayor que lo adelantado → **línea 26** (crédito neto) → Schedule 3, línea 9 → 1040, línea 31.
  - Si se adelantó de más → **línea 29** (devolución) → Schedule 2, línea 1a → 1040.

### 4.8 Ejemplo completo: María

| Respuesta | Formulario que se llena | Documento | Llega al 1040 |
| --- | --- | --- | --- |
| Salario, 1 empleo (D1) | — (directo) | W-2 | Línea 1a (casilla 1) y 25a (casilla 2) |
| Hijo de 8 años con SSN (C2, C9) | Schedule 8812 | Tarjeta del Seguro Social del hijo | Líneas 19 y 28 |
| Ingreso bajo el límite + hijo calificado | Schedule EIC | — | Línea 27a |
| Pagó guardería (E6) + hijo < 13 | Form 2441 | Recibo del proveedor | Schedule 3 → línea 20 |
| Soltera + pagó más de la mitad del hogar (B1, B5) + hijo | — (estado civil) | — | Casilla "Head of household" y deducción estándar de USD 23.625 |

**Verificación (28 oct.):** [Form 1040 de 2025](https://www.irs.gov/pub/irs-pdf/f1040.pdf) (líneas 1a, 11a, 19, 20, 23, 25a, 27a, 28); [W-2 de 2025](https://www.irs.gov/pub/irs-prior/fw2--2025.pdf); [1099-NEC de 2025](https://www.irs.gov/pub/irs-prior/f1099nec--2025.pdf) y [revisión de diciembre de 2026](https://www.irs.gov/pub/irs-pdf/f1099nec.pdf); [1099-K](https://www.irs.gov/pub/irs-pdf/f1099k.pdf); [Schedule C](https://www.irs.gov/pub/irs-pdf/f1040sc.pdf), [Schedule SE](https://www.irs.gov/pub/irs-pdf/f1040sse.pdf), [Schedule 8812](https://www.irs.gov/pub/irs-pdf/f1040s8.pdf), [Form 2441](https://www.irs.gov/pub/irs-pdf/f2441.pdf), [Schedule EIC](https://www.irs.gov/pub/irs-pdf/f1040sei.pdf) y [Form 8962](https://www.irs.gov/pub/irs-pdf/f8962.pdf) de 2025; [1095-A de 2025](https://www.irs.gov/pub/irs-prior/f1095a--2025.pdf); Schedules 1, 2 y 3 de 2025.

⏳ **Pendiente para después** (decidido por Steven, 28 oct.): las fichas de los formularios marcados con ⏳ en el inventario 4.0. Ya están identificados (qué los activa y qué documentos piden); falta el detalle casilla → línea. Se completan cuando el proceso llegue a ellos.

## 5. Paso 3 — Pedir solo lo necesario: checklist de documentos

**Para qué sirve:** es lo que ve el cliente después de responder las preguntas: **la lista de sus documentos, y solo de los suyos**. Sale de la lista A del inventario (4.0), filtrada por sus respuestas.

> **Fuente:** qué documento se pide y por qué sale del IRS (secciones 3 y 4). Cómo se muestra la lista, los estados y los recordatorios son decisiones de proceso.

### 5.1 Cómo se arma

1. **Solo lo activado.** Cada respuesta "Sí" de la sección 3 agrega sus documentos (columna "Lo activa" del inventario). Si no hay respuesta que lo active, el documento **no aparece**.
2. **Lo de siempre va primero:** identificación con foto y tarjeta del Seguro Social, ITIN o ATIN de cada persona de la declaración.
3. **Uno por fuente.** Si dijo "2 empleos", aparecen **dos** W-2. Si tiene dos pólizas del Marketplace, **dos** 1095-A. Siempre se puede agregar otro del mismo tipo.
4. **Cliente que vuelve:** se le muestra lo que entregó el año pasado ("el año pasado entregaste un 1099-INT de tu banco"). Si no le llega este año, lo marca "ya no aplica". Si su identificación y sus tarjetas no cambiaron, no las vuelve a subir.
5. **"Otro documento":** siempre hay un espacio libre para lo que no está en la lista (una carta, un formulario raro). El preparador decide qué es.
6. **Cuando no hay formulario** (efectivo, apps sin 1099-K, alquileres, gastos del negocio), se pide un **registro propio**, con una plantilla simple de totales por categoría o los extractos.

### 5.2 Qué muestra cada documento

| Campo | Ejemplo (W-2) |
| --- | --- |
| Nombre sencillo + nombre oficial | "Comprobante de salario de tu empleador (W-2)" |
| Por qué se pide | "Porque dijiste que trabajaste como empleado" (la pregunta que lo activó) |
| Quién lo envía y cuándo llega | "Lo envía tu empleador. Debe llegarte a más tardar el 2 de febrero de 2026" |
| Si no te llega | "Pídeselo a tu empleador. Igual tienes que declarar ese ingreso" |
| Imagen de ejemplo | Un W-2 con las casillas que importan resaltadas |
| Formatos | PDF, JPG, PNG o foto del teléfono (HEIC se convierte) |
| Estado | Ver 5.3 |

**Fechas del IRS que se muestran** (año fiscal 2025):

| Documento | Debe llegar | Fuente |
| --- | --- | --- |
| W-2 | A más tardar el **2 de febrero de 2026** | Instrucciones del 1040 de 2025 |
| 1095-A | A más tardar el **31 de enero de 2026** | Instrucciones del 1040 y del Form 8962 de 2025 |
| Otros (1099, SSA-1099…) | ⏳ Por verificar en las instrucciones de cada formulario | — |

### 5.3 Estados de cada documento

| Estado | Quién lo pone | Qué significa |
| --- | --- | --- |
| **Pendiente** | Plataforma | Se activó y no se ha subido |
| **Subido** | Cliente | El archivo llegó |
| **Leído** | Plataforma | Se extrajeron los datos, con su evidencia (sección 6) |
| **No se pudo leer** | Plataforma | La extracción falló: se avisa al cliente y al preparador |
| **Con observación** | Preparador | Algo no cuadra: vuelve al cliente como consulta, con el motivo |
| **Validado** | Preparador | Revisado y correcto |
| **No aplica** | Cliente | Lo marca con un **motivo** obligatorio (por ejemplo, "ese banco ya no me pagó intereses"). El preparador puede rechazarlo |

El expediente pasa a **Listo para preparar** (sección 2) cuando todos los documentos están **validados** o en **no aplica** aceptado.

### 5.4 Recordatorios

- Se le recuerda al cliente **solo lo que falta**, con el nombre sencillo de cada documento.
- Si un documento todavía no puede haber llegado (antes de su fecha del IRS), no se le recuerda aún.
- ⏳ El canal (correo, WhatsApp con plantilla) y la frecuencia se definen en la sección 7.

### 5.5 Ejemplo: checklist de María

| # | Documento | Por qué | Estado inicial |
| --- | --- | --- | --- |
| 1 | Tu identificación con foto | Siempre | Pendiente |
| 2 | Tu tarjeta del Seguro Social | Siempre | Pendiente |
| 3 | Tarjeta del Seguro Social de tu hijo | Dijiste que tu hijo vivió contigo | Pendiente |
| 4 | Comprobante de salario (W-2) | Dijiste que tuviste 1 empleo | Pendiente |
| 5 | Recibo de la guardería (con el EIN del proveedor) | Dijiste que pagaste guardería | Pendiente |
| 6 | Tu declaración de 2024 | Para comparar con el año pasado | Pendiente |
| + | Otro documento (opcional) | — | — |

Son **6 documentos**. Si María vuelve en 2027, los números 1–3 ya están y solo se confirman.

**Verificación (28 oct.):** [instrucciones del 1040 de 2025](https://www.irs.gov/pub/irs-pdf/i1040gi.pdf) (W-2 a más tardar el 2 de febrero de 2026; 1095-A a más tardar el 31 de enero de 2026; si el W-2 no llega, igual se declara el ingreso) e [instrucciones del Form 8962 de 2025](https://www.irs.gov/pub/irs-pdf/i8962.pdf).

## 6. Paso 4 — Leer y validar los documentos

**Para qué sirve:** que el preparador **no revise documento por documento**, sino solo lo que falló o es dudoso. La plataforma lee cada documento, guarda de dónde salió cada dato y corre reglas; lo que pasa las reglas queda listo y lo que falla va a una cola.

> **Fuente:** las reglas sobre el contenido (topes, casillas, quién califica) salen del IRS y cada una lleva su referencia. Cómo se lee, la confianza y la cola son decisiones de proceso; varias vienen del documento de Juan Pablo (reglas R-01 a R-06, umbral 0,90, visor con evidencia).

### 6.1 Cómo se lee un documento

1. **Identificar el tipo y el año:** W-2 2025, 1099-NEC 2025, 1095-A 2025… Si el año no es el fiscal que se declara, se avisa.
2. **Extraer por casilla, según el mapa de la sección 4 del año que corresponde.** No se buscan "campos sueltos": se leen las casillas del formulario oficial (W-2 casilla 1, casilla 2…).
3. **Cada dato guarda:**
   - el valor;
   - la **confianza** de la lectura (0 a 1);
   - la **evidencia**: página y recuadro del documento donde está;
   - quién lo cambió después y por qué.
4. **Formatos:** PDF con texto, PDF escaneado y fotos (JPG, PNG; HEIC se convierte a JPG). Excel y CSV solo donde haya un lector propio (registros de ingresos y gastos).
5. **Si no se puede leer**, el documento pasa a "No se pudo leer" (sección 5.3) y se avisa. **Nunca** queda como "recibido" sin datos ni sin aviso.

### 6.2 Reglas de validación

Cada regla tiene un **código**, un **año fiscal**, una **severidad** y una **referencia** (IRS o decisión de proceso). Se guardan como **datos**, no en el código, para poder cambiarlas cada año.

**Severidades:**
- **Bloqueante:** el expediente no avanza hasta resolverla.
- **Revisión:** el preparador la tiene que mirar.
- **Informativa:** se muestra, no detiene nada.

**Reglas para una persona natural (año fiscal 2025):**

| Código | Regla | Severidad | Referencia |
| --- | --- | --- | --- |
| R-01 | El SSN o ITIN del documento coincide con el de la persona (contribuyente, cónyuge o dependiente) y tiene formato válido | Bloqueante | Encabezado del 1040 |
| R-02 | Todo documento activado está **validado** o en **no aplica** con motivo aceptado | Bloqueante | Proceso (sección 5) |
| R-03 | Dato de una casilla clave con confianza **menor de 0,90** | Revisión | Proceso (umbral propuesto, a calibrar) |
| R-04 | W-2: casilla 2 ≤ casilla 1; casilla 3 ≤ **USD 176.100** (tope del Seguro Social 2025) | Bloqueante | Instrucciones del 1040 de 2025 |
| R-05 | Dos o más W-2 con Seguro Social retenido (casilla 4) que suma más de **USD 10.918,20** → posible crédito por exceso retenido | Informativa | Instrucciones del 1040; Schedule 3 línea 11 |
| R-06 | El año del documento es el año fiscal que se declara | Bloqueante | Proceso |
| R-07 | El mismo archivo ya se subió (para este cliente o para otro) | Revisión | Proceso |
| R-08 | **Lo respondido contradice el documento.** Ejemplos: dijo que no tuvo salario y subió un W-2; el W-2 trae casilla 10 y no marcó guardería; la persona del 1095-A no está en la declaración | Revisión | Sección 3 vs. sección 4 |
| R-09 | 1095-A con adelanto del crédito (columna C > 0) → el Form 8962 es **obligatorio** | Bloqueante | Instrucciones del Form 8962 |
| R-10 | Ingresos del Schedule C menores que la suma de los 1099-NEC y 1099-K recibidos | Revisión | Schedule C, línea 1 |
| R-11 | Hijo declarado para el crédito por hijos sin **SSN válido para trabajar** | Bloqueante | Instrucciones del Schedule 8812 |
| R-12 | Proveedor de cuidado sin SSN o EIN | Revisión | Form 2441, Parte I |
| R-13 | Variación de más de 25 % frente al año anterior por fuente de ingreso, o una fuente del año pasado que no aparece | Informativa | Proceso (necesita la declaración anterior) |

⏳ **Pendiente para después:** comparación con la transcripción del IRS (Wage & Income), cuando se defina el acceso con el Form 8821.

### 6.3 Qué pasa cuando una regla falla

| Caso | Qué pasa |
| --- | --- |
| El preparador puede resolverlo (dato mal leído) | Lo corrige en el visor; queda registrado quién, cuándo y el motivo |
| Falta información del cliente | Se abre una **consulta al cliente**: pregunta en lenguaje sencillo, con el motivo; el documento pasa a "Con observación". Al responder, vuelve a la cola |
| Es una decisión fiscal (¿califica este dependiente?) | La **decide el preparador**; la plataforma solo la señala |

**Cola de excepciones:** una lista para el preparador, ordenada por severidad y por la fecha de vencimiento del cliente. Cada excepción tiene tres salidas: **corregir**, **aceptar con nota** o **consultar al cliente**.

**Visor de documento:** el documento a la izquierda y los datos leídos a la derecha; al tocar un dato se resalta su recuadro en el documento. Es la pantalla donde el preparador pasa más tiempo.

### 6.4 Principios para el uso de IA

1. La IA **lee y propone**; no decide posiciones fiscales (quién es dependiente, qué estado civil, qué crédito). Eso lo decide el preparador con las reglas del IRS.
2. Ningún dato leído por IA llega a la hoja final sin **evidencia** y sin pasar las reglas o la revisión del preparador.
3. Si algo se cambia después de validado, la validación se **reabre**.
4. ⏳ Dónde corre la IA y si guarda datos depende de los puntos legales L2 y L4 (sección 8).

**Verificación (28 oct.):** [instrucciones del 1040 de 2025](https://www.irs.gov/pub/irs-pdf/i1040gi.pdf) (tope del Seguro Social USD 176.100 y exceso retenido USD 10.918,20), [Schedule 3 de 2025](https://www.irs.gov/pub/irs-pdf/f1040s3.pdf) (línea 11), [instrucciones del Form 8962](https://www.irs.gov/pub/irs-pdf/i8962.pdf), [instrucciones del Schedule 8812](https://www.irs.gov/pub/irs-pdf/i1040s8.pdf) y [Form 2441](https://www.irs.gov/pub/irs-pdf/f2441.pdf).

## 7. Paso 5 — Revisión del preparador y entrega a TaxWise

**Para qué sirve:** que el preparador revise con un checklist, deje constancia de lo que exige el IRS y tenga todo ordenado para cargar en TaxWise sin volver a abrir cada documento.

> **Fuente:** la debida diligencia sale del IRS (Form 8867 y sus instrucciones). La hoja para TaxWise, los estados y los avisos son decisiones de proceso.

### 7.1 Debida diligencia: el Form 8867 (IRS)

Todo **preparador pagado** que presente una declaración con **EITC, crédito por hijos u otros dependientes (CTC/ACTC/ODC), crédito educativo AOTC o estado civil de cabeza de familia** debe cumplir la debida diligencia del **Form 8867**. Según las instrucciones del IRS, eso exige:

1. **Entrevistar** al contribuyente, hacer las preguntas adecuadas y **documentar en el momento las preguntas y sus respuestas**.
2. Llenar el Form 8867 con la verdad y enviarlo con la declaración.
3. **Guardar 3 años:** copia del 8867, las hojas de cálculo de cada crédito, copias de los documentos en los que se basó, **un registro de cómo, cuándo y de quién obtuvo la información**, y cualquier información adicional usada.

**Multa:** USD 650 por cada crédito sin debida diligencia en una declaración presentada en 2026; hasta USD 2.600 si fallan los cuatro.

**Lo que esto significa para la plataforma:** las preguntas de la sección 3, con sus respuestas, su fecha y quién respondió, **son** el registro que pide el IRS. La plataforma debe:
- guardar cada respuesta con fecha, hora y quién respondió, sin poder borrarla (si cambia, se guarda la versión nueva y la anterior);
- guardar los documentos en los que se basó cada crédito;
- generar un **resumen de debida diligencia por cliente** que el preparador use para llenar el 8867;
- conservar todo **3 años** (ver L12 en la sección 8).

Casi todos los clientes de GTS con hijos activan el 8867 (crédito por hijos, EITC o cabeza de familia), así que esto le ahorra trabajo y riesgo directamente al preparador.

### 7.2 Checklist de revisión del preparador

El expediente llega en estado **Listo para preparar** (sin bloqueantes, ver 6.2). El preparador confirma:

| # | Revisión | Sale de |
| --- | --- | --- |
| 1 | La identidad está verificada con la identificación con foto y las tarjetas de cada persona | Sección 3, bloque A; 13614-C |
| 2 | El **estado civil** para el IRS está decidido, con su motivo | Bloque B; Pub. 501 |
| 3 | Cada persona del bloque C está clasificada (hijo calificado, pariente calificado o no es dependiente), con su motivo | Bloque C; Pub. 501 |
| 4 | Cada formulario activado tiene sus documentos validados | Secciones 4 y 5 |
| 5 | No quedan excepciones abiertas; las aceptadas tienen nota | Sección 6.3 |
| 6 | Si aplica EITC, CTC/ACTC/ODC, AOTC o cabeza de familia: el resumen de debida diligencia está completo | 7.1; Form 8867 |
| 7 | Comparación con el año anterior revisada (si hay declaración de 2024) | Regla R-13 |

- ⏳ Si hace falta un **segundo revisor** o basta un checklist con registro depende de la pregunta legal L13 (Circular 230).
- Si algo cambia después de aprobado, la revisión **se reabre sola**.

### 7.3 Hoja para TaxWise

Mientras no exista una importación directa a TaxWise (bloqueada por el proveedor), la plataforma entrega una **hoja ordenada como el 1040 y sus formularios**:

| Columna | Ejemplo |
| --- | --- |
| Formulario y línea | 1040, línea 1a |
| Descripción | Salarios (W-2, casilla 1) |
| Valor | USD 38.500 |
| Origen | W-2 de "Empresa X", casilla 1 (enlace al recuadro del documento) |
| Estado | Validado por [preparador], [fecha] |

- Se ve en pantalla y se descarga en PDF o Excel.
- Va en el **orden en que se cargan los datos**: encabezado y estado civil → dependientes → W-2 → 1099 → Schedule C → créditos → pagos → banco.
- ⏳ Ajustar el orden al de las pantallas de TaxWise cuando GTS lo muestre.

**Observación de Steven (28 oct.): mostrar el 1040 oficial ya diligenciado.**
> "Lo que podemos hacer es tener el 1040 en la plataforma para mostrarlo de esa manera ya diligenciado con todos los datos que se recogieron."

- **Es viable:** el [Form 1040 de 2025](https://www.irs.gov/pub/irs-pdf/f1040.pdf) del IRS es un PDF **llenable**: tiene 199 campos (126 de texto y 73 casillas de marcar). Los de años anteriores están en `irs.gov/pub/irs-prior/` (por ejemplo, `f1040--2024.pdf`). Los schedules (C, SE, 8812…) también son llenables.
- **Cómo:** se llena cada campo del PDF con el dato de su línea, usando el mapa de la sección 4. Los nombres internos de los campos cambian de un año a otro, así que la correspondencia "línea → campo del PDF" también se guarda **por año fiscal**.
- **Las dos vistas se complementan:**
  - El **1040 diligenciado** muestra la declaración tal como la verá el IRS: es la vista "front".
  - La **hoja** (tabla de arriba) muestra **de dónde salió** cada número, con enlace al documento. El PDF no puede mostrar eso.
  - En la plataforma, al tocar una línea del 1040 se puede abrir su origen.
- **Cuidados:**
  - Es un **borrador** para revisar y para cargar en TaxWise, no para presentar. Lleva una marca de agua "Borrador — no presentar".
  - El cliente no lo ve hasta que el preparador lo apruebe (7.4).
  - ✅ **Decidido por Steven (28 oct.): se llenan todas las líneas**, también las calculadas (ingreso ajustado, impuesto, créditos, reembolso), para ver el borrador del 1040 completo.
    > "Lo mejor es llenar todo. Debemos analizar y comparar con el IRS si los cálculos que se hacen actualmente están correctos, y también tener en cuenta que los cálculos cambian por año fiscal."
  - **Condiciones de la decisión:**
    1. **Validar el motor actual contra el IRS.** Cada una de las 12 calculadoras se compara con la instrucción, hoja de cálculo o tabla del IRS que le corresponde, y se corrige lo que no cuadre. Huecos ya conocidos: no aplica el tope de impuestos estatales y locales, no calcula EITC, Form 8962, Form 8863 ni el Schedule 1-A, y algunas tasas están fijas en el código.
    2. **Todo cálculo se versiona por año fiscal:** montos, topes, porcentajes, tablas de impuesto y líneas del formulario. Hoy solo hay parámetros de 2025.
    3. **Casos de prueba:** declaraciones reales de GTS (anonimizadas) y los ejemplos de las instrucciones del IRS. El borrador debe dar las mismas líneas que TaxWise; cada diferencia se explica o se corrige.
    4. Mientras un cálculo no esté validado, su línea se muestra marcada como **"sin validar"**.

### 7.4 Después de preparar

| Estado | Qué pasa | Quién |
| --- | --- | --- |
| **Preparado en TaxWise** | El preparador lo marca al terminar de cargar | Preparador |
| **Firma del cliente** | El cliente firma el Form 8879 (por ahora, en el proceso actual de TaxWise) | Cliente |
| **Presentado** | Se registra la fecha de envío | Preparador |
| **Aceptado / Rechazado** | Se registra el acuse del IRS. Si se rechaza, vuelve a "Con excepciones" con el motivo | Preparador |

- ⏳ Firmar el 8879 en el portal depende de la pregunta L14.
- **El cliente no ve montos** (impuesto, reembolso) hasta que el preparador los apruebe.
- Al final, el cliente recibe un **resumen sencillo**: qué formularios se presentaron, qué créditos le aplicaron y por qué, y cuándo esperar el reembolso. Si reclama EITC o crédito por hijos, el IRS no emite el reembolso antes de mediados de febrero.

### 7.5 Avisos y recordatorios

| Para quién | Cuándo | Canal |
| --- | --- | --- |
| Cliente | Documentos pendientes (sección 5.4), consultas abiertas, firma pendiente, declaración presentada | Correo y WhatsApp con plantilla aprobada (fuera de la ventana de 24 horas, WhatsApp exige plantilla) |
| Preparador | Un cliente terminó la entrevista, subió documentos o respondió una consulta; un expediente está "Listo para preparar" | Panel y correo |

### 7.6 Medición

Cada cambio de estado (sección 2) guarda su fecha. Con eso se mide, sin trabajo extra:
- días en recolección;
- días hasta "Listo para preparar";
- horas de preparación (desde "En revisión" hasta "Preparado en TaxWise");
- número de consultas al cliente y de correcciones por expediente.

Sirve para comparar contra el proceso actual de GTS (línea base) y para saber qué preguntas o documentos generan más vueltas.

### 7.7 Vista única de reglas, deducciones y cálculos del año

**Observación de Steven (28 oct.):**
> "Actualmente los cálculos, o cómo se calcula, no se muestran en la plataforma, ni tampoco las deducciones que existen para el año fiscal. Toda esta información es buena tenerla en una vista única, para que todos menos los clientes puedan validarla, y con eso, si quieren corroborar la información con el IRS, lo puedan hacer."

**Cómo está hoy:** la ficha del cliente muestra los **resultados** de las 12 calculadoras (estado civil, AGI, impuesto, créditos, liquidación…), pero **no cómo se llegó a ellos**. Los parámetros del año (`ParametroFiscal`) no se ven en ninguna pantalla.

**Qué se propone:** una vista para **preparadores y administradores** (nunca para clientes), con dos partes.

**A. Catálogo del año fiscal (igual para todos los clientes)**

Se elige el año (2025, 2026…) y se ve todo lo que usa la plataforma para ese año:

| Grupo | Ejemplos (2025) |
| --- | --- |
| Deducción estándar | USD 15.750 soltero / 31.500 conjunta / 23.625 cabeza de familia; adicional por 65+ o ceguera |
| Deducciones detalladas | Tope de impuestos estatales y locales USD 40.000; gastos médicos sobre el 7,5 % del AGI |
| Ajustes al ingreso (Schedule 1) | Préstamo estudiantil hasta USD 2.500; educadores hasta USD 300; mitad del impuesto de autoempleo |
| Deducciones nuevas (Schedule 1-A) | Propinas, horas extra, intereses del vehículo (hasta USD 10.000), deducción mejorada para mayores |
| Créditos | Crédito por hijos USD 2.200; otros dependientes USD 500; EITC (tabla por número de hijos); cuidado (tope USD 3.000 / 6.000) |
| Topes y umbrales | Tope del Seguro Social USD 176.100; Schedule SE desde USD 400; Schedule B desde USD 1.500; pariente calificado < USD 5.200 |
| Tablas | Tramos del impuesto; tabla del EITC; línea de pobreza federal (8962) |

Cada fila muestra:
- el **valor**;
- la **condición** para que aplique;
- el **formulario y la línea** donde se usa;
- el **enlace a la fuente del IRS** (instrucción, publicación o tabla) para que cualquiera lo pueda corroborar;
- el **estado de validación**: quién lo comparó con el IRS y cuándo, o "sin validar".

Los valores **solo los cambia un administrador**, quedan registrados (quién, cuándo, valor anterior) y se guardan por año: cambiar 2026 no toca 2025.

**B. "Cómo se calculó", por cliente**

En el borrador del 1040 (7.3), cada línea calculada se puede abrir y muestra el cálculo paso a paso:

| Paso | Ejemplo: María, Schedule 8812 |
| --- | --- |
| Datos de entrada y su origen | 1 hijo menor de 17 con SSN válido (bloque C, tarjeta del Seguro Social subida); AGI de la línea 11a |
| Parámetro usado y su fuente | USD 2.200 por hijo (instrucciones del Schedule 8812 de 2025, con enlace) |
| Operación | Línea 4: 1 × USD 2.200 = USD 2.200 (línea 5) |
| Resultado y destino | Línea 14 → 1040, línea 19 |
| Estado | Validado contra el IRS / sin validar |

Así el preparador puede seguir cualquier número del 1040 hasta su documento y su regla del IRS, y la debida diligencia del 8867 (7.1) queda respaldada.

**Verificación (28 oct.):** [Form 8867](https://www.irs.gov/pub/irs-pdf/f8867.pdf) y sus [instrucciones (rev. noviembre de 2025)](https://www.irs.gov/pub/irs-pdf/i8867.pdf): créditos cubiertos, requisito de entrevista y documentación, registros por 3 años y multa de USD 650 por crédito; [instrucciones del 1040 de 2025](https://www.irs.gov/pub/irs-pdf/i1040gi.pdf): reembolsos con EITC o ACTC no antes de mediados de febrero de 2026.

## 8. Seguridad y cumplimiento mínimos

**Para qué sirve:** la plataforma guarda SSN, W-2, 1095-A y cuentas bancarias de clientes reales. Esta sección fija lo **mínimo** que debe cumplir antes y durante la temporada.

> **Fuente:** normas, no formularios. IRS [Pub. 4557](https://www.irs.gov/pub/irs-pdf/p4557.pdf) (*Safeguarding Taxpayer Data*, incluye los requisitos de la FTC Safeguards Rule) y [Pub. 5708](https://www.irs.gov/pub/irs-pdf/p5708.pdf) (cómo crear el plan escrito de seguridad, WISP); IRC §7216 (consentimientos), §6107 (conservar declaraciones) e [instrucciones del Form 8867](https://www.irs.gov/pub/irs-pdf/i8867.pdf) (registros por 3 años). Lo que queda por interpretar está en las preguntas legales L1–L20 de [`revision-documento-analisis-diseno.md`](revision-documento-analisis-diseno.md), sección 4.

### 8.1 Lo que exige la FTC Safeguards Rule (según la Pub. 4557 del IRS)

Toda firma que prepara declaraciones debe tener un **plan escrito de seguridad** acorde a su tamaño. Como parte del plan, debe:

| # | Requisito | Qué le toca a la plataforma |
| --- | --- | --- |
| S1 | Nombrar un **responsable calificado** del programa de seguridad | GTS lo nombra; la plataforma le da los reportes y la bitácora |
| S2 | **Autenticación de varios factores (MFA)** para **todo el que acceda** a información de clientes. Obligatorio sin importar el tamaño | MFA **obligatorio** para preparadores y administradores. ❓ Para clientes, según L9 |
| S3 | Identificar y evaluar los riesgos de cada área | Inventario de qué datos sensibles hay y dónde (ver 8.3) |
| S4 | Diseñar los controles, monitorearlos y probarlos | Bitácora, alertas de acceso, pruebas de seguridad periódicas |
| S5 | Elegir **proveedores** que protejan los datos, exigirlo por contrato y supervisarlos | Contratos y retención de datos con OpenAI, Twilio y el hosting (L2, L3, L10) |
| S6 | Revisar y ajustar el programa cuando cambie la operación | Revisión anual antes de cada temporada |
| S7 | Capacitar al personal en seguridad, con repasos | Fuera de la plataforma (GTS) |

La Pub. 4557 también recomienda **cifrar todos los archivos con datos personales** y **respaldarlos en un lugar externo** que no esté conectado todo el tiempo a la red.

### 8.2 Mínimos técnicos de la plataforma

| # | Mínimo | Por qué |
| --- | --- | --- |
| T1 | **MFA obligatorio** para preparadores y administradores | S2 |
| T2 | **Archivos cifrados** en reposo y en tránsito | Pub. 4557 |
| T3 | **Respaldo externo** de base de datos y archivos, cifrado y probado | Pub. 4557 |
| T4 | **No se puede borrar** un cliente, sus respuestas ni sus documentos mientras no venza su plazo de conservación | §6107 y Form 8867: 3 años (L12) |
| T5 | **Bitácora que no se puede modificar**: accesos, cambios, vistas de datos sensibles, exportaciones | S4; registro de debida diligencia (7.1) |
| T6 | **Acceso por rol:** el preparador ve sus clientes; el soporte técnico no ve datos de contribuyentes salvo con autorización temporal y registrada | S4; §7216 (L4) |
| T7 | SSN y números de cuenta **ocultos por defecto**; revelarlos queda registrado | Ya existe (`CampoReveal`) |
| T8 | **Revisión de virus** de cada archivo subido | S4 |
| T9 | **Nada sensible por WhatsApp**: el chat solo avisa y lleva al portal | Decisión del 24 sep.; L3 |
| T10 | Datos y respaldos alojados en **EE. UU.**, y proveedores de IA sin retención ni entrenamiento con los datos | L2, L6 |

### 8.3 Consentimiento del cliente (§7216)

- Antes de la primera pregunta de la sección 3, el cliente firma en el portal el **consentimiento §7216** que corresponda.
- Se guardan el **texto, la versión, la fecha y la hora** de lo que firmó.
- ⏳ El texto lo define el abogado (L1), y también si hace falta un consentimiento aparte para el uso de IA o el acceso desde fuera de EE. UU. (L2, L4).

### 8.4 Si hay una pérdida o robo de datos

Según la Pub. 4557, el preparador debe **avisar de inmediato**:
- al **IRS**, a través de su *Stakeholder Liaison* local;
- a la **policía local** (y al FBI si el IRS lo indica);
- a los **estados** donde prepara declaraciones.

Además pueden aplicar el aviso a la FTC y a los afectados según la ley de Florida (L11).

La plataforma debe tener listo:
- el **inventario de datos sensibles por cliente** (sale del catálogo: qué campos son sensibles);
- la **bitácora** para saber qué se vio y quién lo vio;
- un **plan de respuesta** escrito, que forma parte del WISP de GTS (Pub. 5708).

### 8.5 Antes de la temporada de 2027

| Prioridad | Qué | Quién |
| --- | --- | --- |
| 1 | T1, T2, T3 y T4: MFA, cifrado, respaldo y no borrar | Equipo técnico (no depende del abogado) |
| 2 | Respuestas de L2 y L4: uso de IA y acceso desde fuera de EE. UU. | Abogado + equipo técnico |
| 3 | Texto del consentimiento §7216 en el portal (L1) | Abogado |
| 4 | WISP de GTS con su plan de respuesta | GTS (+ abogado o CPA) |
| 5 | T5–T8 y T10 | Equipo técnico |

**Verificación (28 oct.):** [Pub. 4557](https://www.irs.gov/pub/irs-pdf/p4557.pdf) (rev. mayo de 2024): requisitos de la FTC Safeguards Rule, MFA obligatorio para todos los que accedan sin importar el tamaño, cifrado y respaldo externo, aviso al IRS por medio del *Stakeholder Liaison*; [Pub. 5708](https://www.irs.gov/pub/irs-pdf/p5708.pdf) (rev. agosto de 2024): WISP; [instrucciones del Form 8867](https://www.irs.gov/pub/irs-pdf/i8867.pdf): registros por 3 años.

## 9. Qué cambia en la plataforma (hoy → objetivo) y en qué orden

**Para qué sirve:** convertir las secciones 1–8 en trabajo concreto. Dice qué se conserva, qué se reemplaza, qué se elimina y **en qué orden** se hace. Aquí, por primera vez, se compara el documento con el código actual (rama `main`, 28 oct.). El detalle de cada defecto está en [`que-tiene-gts-hoy.md`](que-tiene-gts-hoy.md).

### 9.1 El proceso cambia

| Paso | Hoy | Objetivo |
| --- | --- | --- |
| Inicio | El cliente escribe por WhatsApp; el agente crea la cuenta | Igual, o lo crea el preparador. El agente explica el consentimiento §7216 y **el cliente lo firma en el portal** antes de empezar (8.3) |
| Entender al cliente | El agente de WhatsApp decide **qué formas aplican** con un árbol de 44 pasos | **Entrevista en tres niveles basada en el 13614-C** (sección 3), **por WhatsApp con el agente o por el portal**, a elección del cliente y con las mismas preguntas. Los datos sensibles siempre van por el portal (9.6) |
| Qué se pide | Una página larga con **campos fijos por forma**; se le piden montos al cliente | **Checklist** solo con los documentos que activaron sus respuestas; los montos se leen de los documentos (sección 5) |
| Leer documentos | La IA llena campos sueltos; si falla, nadie se entera | Lectura **por casilla**, con confianza y evidencia; reglas y cola de excepciones (sección 6) |
| Correcciones | El preparador escribe al cliente por WhatsApp a mano | **Consultas** desde la plataforma, con motivo. El agente se las hace al cliente por WhatsApp en lenguaje sencillo; si piden un documento, el cliente lo sube al portal (6.3, 9.6) |
| Cierre del cliente | Atestación **solo por WhatsApp**; casi nunca se alcanza | El agente avisa; el cliente **confirma en el portal**, donde ve el resumen |
| Revisión | Campo por campo, por forma; "revisado" no se reabre | Checklist del preparador + resumen de debida diligencia (8867); se reabre si algo cambia (7.2) |
| Entrega | El preparador digita todo en TaxWise; exporta un ZIP con claves internas | **Borrador del 1040 oficial** diligenciado + hoja con el origen de cada línea (7.3) |
| Seguimiento | No hay estado del caso | **Estados del expediente** con fecha y avisos (sección 2, 7.5, 7.6) |

### 9.2 Qué se conserva (se reutiliza)

| Pieza | Código | Ajuste |
| --- | --- | --- |
| Usuarios, roles, inicio de sesión, 2FA y passkeys | Fortify, `User`, `UsuarioController` | Hacer el MFA **obligatorio** para el personal (T1). La asignación de clientes a preparadores ya existe (`preparer_id`) |
| Carga de documentos y detección de duplicados | `Documento`, `DocumentoController`, `DocumentoDuplicadoService` | Cifrar los archivos (T2); varios archivos por tipo de documento |
| Extracción (texto del PDF y visión) | `DocumentoExtraccion/*` | Que extraiga **por casilla** según el mapa del año, con confianza y evidencia (6.1); convertir HEIC |
| Motor de cálculo (12 calculadoras) y parámetros por año | `app/Services/Reglas`, `DeterminacionFiscalService`, `ParametroFiscal` | **Validar contra el IRS**, completar (EITC, 8962, 1-A…) y versionar por año (7.3); escribir líneas del 1040 |
| Auditoría | `HistorialCambio`, `CampoReveal`, `BitacoraActividad` | Hacer la bitácora no modificable (T5) |
| Tiempo real y cola | Reverb, Redis | Sin cambios |
| WhatsApp (Twilio) | `app/Services/Whatsapp/Twilio` | Canal del agente (9.6) + avisos con plantillas aprobadas (7.5) |
| Agente conversacional y chat del portal | `AgenteConversacionalService`, `EstadoConversacionResolver`, `PortalChatController` | El agente se **reescribe** para leer la pestaña "Preguntas" del catálogo y guardar en el mismo lugar que el formulario; responde dudas con fuentes del IRS (9.6) |
| Dashboard | `DashboardSummaryService` | Pasar a contar **expedientes por estado** y personas por tipo de ingreso |
| Meta-agente | `MetaAgente*` | Analizar también el chat del portal, no solo WhatsApp |
| Base de conocimiento | `BaseConocimientoService` | Cargarla con las fuentes del IRS de este documento (13614-C, Pub. 501, instrucciones del 1040 y de cada anexo) |

### 9.3 Qué se reemplaza

#### 9.3.1 El menú "Catálogo" se rediseña por completo

**Hoy:** una lista de **campos sueltos por forma** (`catalogo_campos`): clave interna, tipo (documento, dato o mixto), tipo de dato (texto, número, objeto o lista), formatos, subcampos, obligatorio y sensible. No hay preguntas, ni casillas de documentos, ni líneas del 1040, ni parámetros, ni reglas. Cada forma (incluidas las de empresas) tiene su lista fija.

**Objetivo:** un **"Catálogo fiscal del año"**. Se elige el año (2025, 2026…) y tiene cinco pestañas, una por pieza del documento madre:

| Pestaña | Qué guarda | Sale de |
| --- | --- | --- |
| **1. Preguntas** | Código (A1…G5), texto para el cliente (español e inglés), ayuda, tipo de respuesta (Sí/No, opciones, casillas, dato), nivel (entrada o seguimiento), **cuándo se muestra**, qué activa y su referencia al IRS | Sección 3 |
| **2. Documentos** | Tipo (W-2, 1099-NEC…), nombre sencillo, quién lo envía y cuándo llega, qué preguntas lo activan, formatos, y sus **casillas del año** | Secciones 4.0, 4.1 y 5 |
| **3. Formularios y líneas** | Cada formulario que se llena (1040, C, SE, 8812…), sus líneas, el **origen** de cada línea (casilla, otra línea o cálculo) y el campo del PDF oficial que le corresponde | Sección 4 y 7.3 |
| **4. Parámetros y deducciones** | Montos, topes, porcentajes y tablas del año, con su fuente del IRS y su estado de validación | 7.7 |
| **5. Reglas de validación** | Código, regla, severidad, año y referencia | 6.2 |

- Este catálogo **es** la vista única que pidió Steven en 7.7.
- Lo edita solo un administrador; cada cambio queda registrado; cada año es independiente (copiar 2025 → 2026 y ajustar).
- Desaparecen los conceptos de "forma" como eje, las pseudoformas `transversal` y `documentos_extra`, y los campos de empresas.
- Los datos que ya respondieron los clientes (`campos_cliente`) se **migran** a las nuevas preguntas y documentos donde exista equivalente.

#### 9.3.2 Otras piezas que se reemplazan

| Hoy | Se reemplaza por |
| --- | --- |
| Determinación de formas por WhatsApp (`determinacion_formas.md`, árbol de 44 pasos `PromptActivoStep`, `ActivosResolver`, `ActivosPromptComposer`) | Entrevista por WhatsApp (agente) y por el portal, las dos leídas de la pestaña "Preguntas" |
| Formulario del portal con campos fijos y 7 dependencias en código (`formulario.tsx`, `ocultosPorDependencia`) | Pantallas de la entrevista (tres niveles) + checklist de documentos |
| "Mi información" (vista del cliente con cálculos sin revisar) | Resumen para el cliente **después** de la aprobación del preparador (7.4) |
| Estado por forma (`FormaCliente`: en progreso / completo; `revisado_en` que no se reabre) | **Expediente** con estados y fechas (sección 2) |
| Nivel de riesgo automático (`RiesgoCasoService`, `NivelRiesgoManual`) | Reglas de validación con severidad y **cola de excepciones** (6.2–6.3) |
| Atestación por WhatsApp (`ClienteAtestacion` vía la fase `Cierre`) | Confirmación en el portal; el agente avisa. Se conserva la tabla |
| Exportación ZIP con `campos.json` (`ClienteExportService`) | Borrador del 1040 en PDF + hoja para TaxWise (7.3) |
| Campos de montos que digita el cliente (`ingresos`, `impuestos_retenidos`, `salarios_medicare`, montos de `deducciones` y de `marketplace_seguro`, `depreciacion`) | Datos leídos de los documentos |

### 9.4 Qué se elimina (código que no se usa o que queda sin uso)

| # | Qué | Por qué | Antes de borrar |
| --- | --- | --- | --- |
| E1 | Lectura del historial de Supabase/n8n (`SupabaseWhatsappConversationService`, config `supabase`, `VITE_SUPABASE_*`) y sus credenciales en producción | El agente ya migró a Laravel | — |
| E2 | Sesiones del agente externo (`ClientIntakeSession`) | Eran del agente de n8n | — |
| E3 | API del agente externo (`/api/eventos`, `/api/catalogo/documentos-extra`, `/api/clientes/*`), el panel "API docs", `docs/api.md`, `docs/openapi.yaml` y la pantalla de tokens (`settings/api-tokens`) | Nada interno la usa | ❓ Confirmar que ningún sistema externo tiene un token activo |
| E4 | Proveedor **Meta** de WhatsApp (`app/Services/Whatsapp/Meta`, config `meta`) | Producción usa Twilio; Meta no tiene credenciales | — |
| E5 | Prompts `recoleccion.md` y `determinacion_formas.md`, y la versión actual de `guardar_campo_cliente` | El agente se reescribe sobre el catálogo (9.6): pregunta y guarda respuestas **no sensibles**; nunca recibe documentos ni datos sensibles | Con la fase 2 (9.5) |
| E6 | Árbol de 44 pasos (`PromptActivoStep`, `PromptActivoStepsSeeder`, `ActivosResolver`, `ActivosPromptComposer`) | Lo reemplaza la pestaña "Preguntas", que leen el agente y el formulario | Con la fase 2 |
| E7 | Prompts viejos sin uso (`prompt_actuales/promptBase.md`, `prompt_actuales/especialista_recoleccion.md`, `docs/prompt.md`) | Nada los carga | — |
| E8 | Página de bienvenida (`welcome.tsx`) | `/` redirige al login o al dashboard | — |
| E9 | Panel "Proceso" (`ProcesoController`, `docs/proceso-global-tax.html`) | Desactualizado; el proceso vigente es este documento | O se regenera desde este documento |
| E10 | Formas de empresas y no residentes en `TaxForm` y en el catálogo: 1065, 1120, 1120-S, 1041, 990, 1040-NR | Fuera del alcance (1.1) | Primero ocultar; borrar cuando GTS confirme |
| E11 | Schedule F (agricultura) | No aparece en el 13614-C ni en el inventario de persona natural de GTS | ❓ Confirmar con GTS que no tiene clientes agrícolas |
| E12 | Pseudoformas `transversal` y `documentos_extra`, la tool `consultar_documentos_extra` | Las reemplaza el nuevo catálogo | Con la fase 1 |
| ~~E13~~ | ~~Meta-agente~~ | ✅ **Se conserva** (9.6): se amplía al chat del portal (ver 9.2) | — |
| ~~E14~~ | ~~Base de conocimiento~~ | ✅ **Se conserva** (9.6): se carga con las fuentes del IRS (ver 9.2) | — |
| E15 | `laravel/chisel` como dependencia de producción | Es una herramienta de *scaffolding* | Pasarla a `require-dev` o quitarla |

### 9.6 El agente de IA por WhatsApp: qué procesos puede hacer

**Observación de Steven (28 oct.):**
> "Una de las ideas de este proceso es que haya cosas que realice el agente; por eso existe un prompt para WhatsApp. Quiero analizar qué procesos se pueden adaptar para que se hagan por WhatsApp por medio del agente. Creo que sí es necesario tener el formulario. Se quiere implementar IA, y por eso tenemos lo de WhatsApp."

✅ **Aprobado por Steven (28 oct.).** El agente **sí** hace parte del proceso, dentro de tres límites. Las tablas 9.1 a 9.5 ya están ajustadas a esta decisión.

**Límites:**
1. **La circular de septiembre:** prohíbe mandar información sensible de impuestos por WhatsApp (W-2, 1099…). Por eso los **documentos y los datos sensibles** (SSN o ITIN, cuentas bancarias, montos) **solo van por el portal**.
2. **§7216 (L3):** cualquier dato que el cliente da para su declaración es información protegida, aunque sea un "sí" o un "no". Hoy el agente ya pregunta por WhatsApp para determinar las formas; el abogado debe confirmar que se puede seguir haciendo y en qué condiciones.
3. **Una sola fuente:** el agente y el formulario leen **las mismas preguntas** del catálogo fiscal (pestaña "Preguntas", 9.3.1) y guardan las respuestas **en el mismo lugar**. El cliente puede empezar en WhatsApp y terminar en el portal, o al revés, sin repetir nada. Hoy ya funciona así a medias: el estado de la conversación se calcula desde los datos guardados, no desde la memoria del chat.

**Qué hace cada canal:**

| Proceso | Agente por WhatsApp | Portal | IA sin chat |
| --- | --- | --- | --- |
| Crear la cuenta y verificar al cliente | ✅ Ya existe | ✅ | — |
| Consentimiento §7216 | Explica qué es y manda el enlace | ✅ **Firma** (con texto, versión y fecha) | — |
| **Entrevista, nivel 1** (pantallas 2–8 de la sección 3: situaciones especiales, estado civil, quién vivió contigo, de dónde recibiste dinero, gastos, situaciones del año) | ✅ **Conversacional.** El cliente responde con sus palabras ("trabajo en Uber y mi esposa en Walmart") y la IA marca las casillas que corresponden (D1, D16, cónyuge) | ✅ Las mismas preguntas como pantallas | — |
| Entrevista, nivel 2: seguimientos **no sensibles** ("¿cuántos empleos?", "¿cuántos meses vivió contigo?", parentesco) | ✅ | ✅ | — |
| Entrevista, nivel 2: datos **sensibles** (SSN o ITIN, fechas de nacimiento, nombres de los dependientes, cuenta bancaria) | ❌ Manda el enlace al portal | ✅ | — |
| **Documentos** (W-2, 1099, 1095-A, tarjetas, identificación) | ❌ No los recibe. Si el cliente manda uno, responde que lo suba al portal (y no lo guarda) | ✅ Checklist y carga | — |
| Dudas ("¿por qué me piden esto?", "¿dónde consigo mi SSA-1099?", "¿qué es el IP PIN?") | ✅ Con fuentes del IRS | ✅ Chat de dudas | — |
| Recordatorios de lo que falta | ✅ Con plantillas aprobadas (fuera de las 24 horas) | Se ve en el checklist | — |
| Consultas del preparador al cliente | ✅ Le hace la pregunta en lenguaje sencillo y guarda la respuesta si no es sensible; si hace falta un documento, manda el enlace | ✅ | — |
| Estado de la declaración ("¿cómo va lo mío?") | ✅ | ✅ | — |
| Confirmación final del cliente y firma del 8879 | Avisa y manda el enlace | ✅ Ve el resumen (con montos) y confirma | — |
| Aviso de presentada y aceptada | ✅ | ✅ | — |
| Leer documentos, correr reglas, señalar contradicciones | — | — | ✅ Extracción por casilla y reglas (sección 6) |
| Ayudas para el preparador: resumen del expediente, borrador de la consulta al cliente, resumen de debida diligencia (8867), explicación de cada cálculo | — | — | ✅ |
| Mejorar el agente con lo que preguntan los clientes | — | — | ✅ Meta-agente, sobre WhatsApp **y** el chat del portal |

**Requisitos para que funcione:**
- El agente **no decide posiciones fiscales**: marca respuestas y explica; el preparador decide (6.4).
- Cada respuesta dada por WhatsApp se guarda con fecha, hora y canal. Sirve como registro de la entrevista para el Form 8867 (7.1).
- Si la IA no está segura de cómo interpretar lo que dijo el cliente, **pregunta de nuevo** en vez de adivinar.
- El cliente ve en el portal todo lo que respondió por WhatsApp y lo puede corregir.

### 9.7 Revisión de cada menú de hoy

**Pedido de Steven (28 oct.):** revisar cada menú, porque son las vistas que se van a usar, y decidir qué se **conserva**, qué se **mejora**, qué se **reemplaza** y qué se **elimina**. Base: `app-sidebar.tsx` y las páginas de `resources/js/pages` en `main` (28 oct.).

**Cliente**

| Menú | Hoy | Decisión | Qué cambia | Fase |
| --- | --- | --- | --- | --- |
| **Panel** | Para el cliente es "Mi información": sus datos por forma y **los cálculos del motor sin revisar** (impuesto, créditos, liquidación) | 🔄 Reemplazar | **"Mi declaración":** estado del expediente, avance de la entrevista y del checklist, consultas abiertas y, solo después de la aprobación del preparador, el resumen y el borrador | 2 y 5 |
| **Formulario** | Una página larga con campos fijos por forma y el chat de dudas al lado | 🔄 Reemplazar | **Entrevista** en tres niveles (las mismas preguntas que el agente) + **checklist de documentos** + chat de dudas al lado | 2 y 3 |
| Chat (sin entrada en el menú) | Se usa para que el agente determine las formas antes del formulario | 🛠️ Mejorar | Queda solo como chat de dudas dentro de la entrevista; la determinación de formas desaparece | 2 |
| Configuración → Perfil y Seguridad | Datos de la cuenta, contraseña, 2FA y passkeys | ✅ Conservar | — | — |
| Configuración → Tokens de API | Visible para todos los usuarios | 🗑️ Eliminar | Ver E3 | 0 |

**Preparador (y administrador)**

| Menú | Hoy | Decisión | Qué cambia | Fase |
| --- | --- | --- | --- | --- |
| **Panel** | Indicadores por forma: formas completas, % de campos recibidos, alto riesgo, pendientes de revisar, actividad. "Pendientes de revisar" casi siempre queda vacío | 🛠️ Mejorar | Expedientes **por estado** (sección 2); colas: "Listo para preparar", **excepciones abiertas**, consultas sin respuesta, próximos vencimientos; tiempos (7.6) | 5 |
| **Clientes** (lista) | Filtros por estado, nivel de riesgo y forma (incluye formas de empresas). El preparador ve solo sus clientes asignados | 🛠️ Mejorar | Filtros por **estado del expediente**, tipo de ingreso (asalariado, independiente…), excepciones y canal (WhatsApp o portal); quitar las formas de empresas | 5 |
| **Ficha del cliente** | Una sola página muy larga (~2.400 líneas de código): campos por forma, editar, subir documentos, "no aplica", historial, revelar SSN, nivel de riesgo, "marcar revisado", determinación fiscal, exportar ZIP, conversación de WhatsApp con "tomar control" | 🔄 Reemplazar (reutilizando piezas) | Pestañas: **Resumen** (estado y fechas) · **Entrevista** (respuestas con canal y fecha) · **Documentos** (checklist + visor con evidencia) · **Excepciones y consultas** · **Borrador del 1040** (PDF, hoja para TaxWise y "cómo se calculó") · **Debida diligencia** (8867) · **Conversación** (WhatsApp y portal, con "tomar control") · **Historial**. Se conservan el historial, revelar SSN y tomar el control de WhatsApp | 3 a 6 |
| **Proceso** | Diagrama del proceso del 17 sep., anterior al portal | 🗑️ Eliminar o regenerar | Ver E9 | 0 |
| **API docs** | Documentación de la API del agente externo; la ve **también el preparador** | 🗑️ Eliminar | Ver E3 | 0 |

**Solo administrador**

| Menú | Hoy | Decisión | Qué cambia | Fase |
| --- | --- | --- | --- | --- |
| **Catálogo** | Campos sueltos por forma (9.3.1) | 🔄 Reemplazar | **"Catálogo fiscal del año"** con 5 pestañas: Preguntas, Documentos, Formularios y líneas, Parámetros y deducciones, Reglas (9.3.1 y 7.7). Lo pueden **ver** también los preparadores; solo lo **edita** el administrador | 1 |
| **Usuarios** | Alta de usuarios, rol y asignación de clientes a un preparador | 🛠️ Mejorar | Ver y exigir el MFA (T1); reasignar clientes en bloque; acceso de soporte temporal y registrado (T6); ❓ rol "asistente" si GTS lo tiene | 0 y 5 |
| **Bitácora** | Registro de acciones (crear, actualizar, eliminar, inicio y cierre de sesión) | 🛠️ Mejorar | No modificable y exportable (T5); filtros por cliente, usuario y tipo de acción; incluir las vistas de datos sensibles | 0 |
| **Agente → Mensajes** | Conversaciones de WhatsApp | 🛠️ Mejorar | Incluir también las conversaciones del chat del portal | 2 |
| **Agente → Prompts** | Editar y publicar el prompt de cada fase de la conversación | 🛠️ Mejorar | Las fases cambian: el agente hace la entrevista leyendo el catálogo (9.6); se quitan las fases de recolección y determinación de formas | 2 |
| **Agente → Tools** | Encender o apagar herramientas por fase | ✅ Conservar | Ajustar a las nuevas fases y herramientas (guardar respuestas no sensibles, consultar el checklist, estado del caso) | 2 |
| **Agente → Base de conocimiento** | Subir PDFs para que el agente responda; vacía en producción | 🛠️ Mejorar | Cargar las fuentes del IRS de este documento | 7 |
| **Meta-agente** | Análisis diario de las conversaciones de WhatsApp para mejorar el prompt | 🛠️ Mejorar | Analizar también el chat del portal | 7 |

**Menús nuevos**

| Menú | Para quién | Qué muestra | Fase |
| --- | --- | --- | --- |
| **Excepciones** | Preparador | La cola de excepciones de todos sus clientes, por severidad y vencimiento, con las tres acciones (6.3) | 4 |
| **Catálogo fiscal del año** (en lugar de "Catálogo") | Ver: preparador y administrador. Editar: administrador | Ver arriba | 1 |

**Resumen:** de los 18 menús y vistas de hoy, se conservan 2, se mejoran 9, se reemplazan 4 (Panel y Formulario del cliente, Ficha del cliente y Catálogo) y se eliminan 3 (Tokens de API, Proceso y API docs; Proceso se puede regenerar). Se agrega 1 menú nuevo (Excepciones), y el Catálogo pasa a ser el Catálogo fiscal del año.

### 9.5 Orden de los cambios

Cada fase deja la plataforma **funcionando** y se cierra con pruebas y commit. El orden sigue tres criterios: **primero proteger los datos reales**, después **la base** (el catálogo fiscal, del que depende todo) y al final lo que se apoya en ella.

| Fase | Qué incluye | Listo cuando | Depende de |
| --- | --- | --- | --- |
| **0. Seguridad y limpieza** | T1–T4 (MFA del personal, cifrado de archivos, respaldo externo, no borrar); eliminar E1, E2, E4, E7, E8, E15; ocultar E10 | Producción cumple los mínimos de 8.5 (prioridad 1) y no hay código muerto de n8n ni de Meta | — |
| **1. Catálogo fiscal del año** | Nuevo modelo y menú de 5 pestañas (9.3.1); cargar 2025 y 2026 con las secciones 3, 4, 6 y 7.7; el 1040 siempre presente; migrar datos de `campos_cliente`; eliminar E12 | Un administrador ve y edita preguntas, documentos, líneas, parámetros y reglas de un año, cada uno con su fuente del IRS | Fase 0 |
| **2. Entrevista por WhatsApp y por el portal** | Consentimiento §7216 en el portal; entrevista en tres niveles en los **dos canales**, leída del catálogo; agente reescrito (respuestas no sensibles por WhatsApp, enlace al portal para lo sensible y los documentos); confirmación del cliente en el portal; reemplazar E5 y E6 | María completa la entrevista en ≈ 21 respuestas, empezando por WhatsApp y terminando en el portal sin repetir nada | Fase 1; texto del consentimiento (L1); respuesta de L3 |
| **3. Checklist de documentos** | Checklist según respuestas; estados por documento; "no aplica" con motivo; varios archivos por tipo; "otro documento"; recordatorios del agente por WhatsApp con plantillas; el agente no acepta documentos y manda al portal | El cliente ve solo sus documentos y el preparador ve qué falta | Fase 2 |
| **4. Lectura y validación** | Extracción por casilla con confianza y evidencia; reglas R-01 a R-13; cola de excepciones; consultas al cliente (también por el agente); visor de documento | Un W-2 y un 1095-A se leen con evidencia y las reglas detectan los errores de prueba | Fases 1 y 3 |
| **5. Expediente y revisión** | Estados del expediente con fecha; checklist del preparador; resumen de debida diligencia (8867) y del expediente hechos por la IA; hoja para TaxWise; avisos al preparador; el agente responde "¿cómo va lo mío?"; reemplazar "Mi información", el riesgo y el ZIP | Un preparador revisa y carga en TaxWise sin abrir cada documento | Fase 4 |
| **6. Cálculo y borrador del 1040** | Validar las 12 calculadoras contra el IRS y por año; completar EITC, 8962, 1-A; llenar el PDF oficial del 1040 y sus schedules; vista "cómo se calculó" | El borrador de los casos de prueba da las mismas líneas que TaxWise | Fases 1 y 4. **La validación de las calculadoras puede empezar en paralelo desde la fase 1** |
| **7. Después** | Fichas pendientes (1-A, A, B, D, E, H…); transcripciones del IRS; precarga del año anterior; métricas avanzadas; meta-agente sobre el chat del portal; carga de la base de conocimiento; decidir E3, E9 y E11; varias firmas | — | Decisiones de la sección 10 |

### 9.8 Cómo se programa cada fase

✅ **Decidido por Steven (28 oct.):** antes de programar una fase se escribe su **plan técnico** ("cómo programarlo"), se aprueba y recién después se programa.

- **Dónde:** un archivo por fase en [`fases/`](fases/), por ejemplo `fases/fase-0-seguridad-y-limpieza.md`.
- **Qué contiene:** tareas, archivos que se tocan, enfoque técnico, migración de datos existentes, pruebas, despliegue y riesgos.
- **Buenas prácticas:** cada plan y cada cambio aplica las skills **`laravel-best-practices`** (backend), **`react-best-practices`** (componentes React) y **`vercel-react-best-practices`** (rendimiento del frontend: tamaño del bundle, renders, carga de datos), citando la regla que se usa (por ejemplo `eloquent-soft-deletes`, `controller-form-requests`, `bundle-barrel-imports`).
- **Pruebas:** Pest; la suite completa debe pasar antes de cerrar la fase. Formato con Pint.
- **Cierre:** al terminar la fase (o una subfase) se le **pregunta a Steven** antes de hacer commit y push. Con su sí, el commit se hace con **`/commit`**, que ofrece commit + push a la rama en la que se trabaja. Un push a `main` despliega a producción (Dokploy).

| Fase | Plan técnico | Estado |
| --- | --- | --- |
| 0 | [`fases/fase-0-seguridad-y-limpieza.md`](fases/fase-0-seguridad-y-limpieza.md) | 🟡 En revisión |
| 1–7 | Se escriben al iniciar cada fase | ⏳ |

⏳ **El calendario de las fases depende de una decisión pendiente:** si GTS usa la plataforma en la temporada de enero–abril de 2027 o solo con las declaraciones prorrogadas (sección 10). La fase 0 no depende de eso y conviene hacerla ya.

## 10. Decisiones pendientes

### Decisiones ya tomadas

| Fecha | Decisión | Dónde |
| --- | --- | --- |
| 27 oct. | Alcance: persona natural, incluidos los independientes con Schedule C; una sola firma (GTS) | 1.1 |
| 27 oct. | El IRS es la fuente de verdad y el 1040 es el formulario "front" | 1.2 |
| 28 oct. | El documento se arma desde cero, con el IRS como base | Método |
| 28 oct. | Entender al cliente en tres niveles (entrada, seguimiento, lo que no se pregunta) | 3, observación 1 |
| 28 oct. | Las fichas pendientes de la sección 4 se completan después | 4.0 |
| 28 oct. | **La plataforma calcula y llena el 1040 completo como borrador**, validando cada cálculo contra el IRS y por año fiscal | 1.2 (principio 5) y 7.3 |
| 28 oct. | Vista única (solo preparadores y administradores) con las reglas, deducciones y parámetros del año, y el "cómo se calculó" de cada línea, con enlace al IRS | 7.7 |
| 28 oct. | El agente de IA por WhatsApp hace la entrevista (respuestas no sensibles), dudas, recordatorios, consultas y estado del caso; documentos y datos sensibles solo por el portal; una sola fuente de preguntas para los dos canales | 1.2 (principio 8) y 9.6 |

### Pendientes

Ordenadas por **lo que bloquean**. Las preguntas legales L1–L20 están completas en [`revision-documento-analisis-diseno.md`](revision-documento-analisis-diseno.md), sección 4; aquí solo aparecen las que frenan alguna fase.

**Con GTS**

| # | Decisión | Por qué importa | Bloquea |
| --- | --- | --- | --- |
| P1 | **Calendario:** ¿se usa la plataforma en la temporada de enero–abril de 2027 o solo con las declaraciones prorrogadas? | Define el ritmo de las fases 1–6 y si hay que tener listos los parámetros de 2026 para enero | Plan de las fases 1–6 |
| P2 | ¿Atiende no residentes (1040-NR)? | Si sí, la pregunta A7 deja de ser solo una alerta | Alcance (1.1) |
| P3 | ¿Tiene clientes agrícolas (Schedule F)? | Define si se borra (E11) | Fase 0 |
| P4 | ¿Maneja sales tax (DR-15), bienes muebles (DR-405) o tourist tax de sus independientes? | Si sí, se agregan preguntas de alerta | Fase 1 (catálogo) |
| P5 | ¿Cómo arma hoy el Schedule C de un independiente sin contabilidad? ¿Cuántos usan QuickBooks? ¿Tiene clientes con K-1? | Define los registros propios que se piden (5.1) y si hace falta otro conector | Fase 3 |
| P6 | Orden de las pantallas de TaxWise | La hoja para TaxWise (7.3) se ordena igual | Fase 5 |
| P7 | ¿Tiene asistentes? ¿Quiere que el cliente firme el 8879 en el portal? | Rol "asistente" (9.7) y firma en el portal (7.4, L14) | Fase 5 |
| P8 | **Línea base:** horas por declaración hoy, por tipo de cliente | Sin ella no se puede medir el ahorro (7.6) | Medición |
| P9 | Declaraciones reales anonimizadas para los casos de prueba, y un preparador que valide el ejemplo de recolección | Validar el motor de cálculo contra TaxWise (7.3) | Fase 6 |

**Con un abogado o CPA (preguntas legales que frenan fases)**

| # | Pregunta | Bloquea |
| --- | --- | --- |
| L1 | Texto del consentimiento §7216 | Fase 2 |
| L2 / L4 | Uso de OpenAI y acceso del equipo desde fuera de EE. UU. | Fases 2 y 4 (y el uso actual) |
| L3 | ¿Se puede seguir haciendo la entrevista por WhatsApp? ¿En qué condiciones? | Fase 2 (entrevista por WhatsApp) |
| L5 | ¿Usar declaraciones anonimizadas de clientes para pruebas requiere consentimiento? | P9 y fase 6 |
| L9 | ¿El MFA es obligatorio también para los clientes? | Fase 0 |
| L12 | Qué se conserva y por cuánto tiempo (§6107, Form 8867) | Fase 0 (no borrar) |
| L13 | ¿Hace falta un segundo revisor? | Fase 5 |
| L14 | Verificación de identidad para firmar el 8879 en el portal | Fase 5, si P7 es "sí" |

**Técnicas (equipo)**

| # | Decisión | Bloquea |
| --- | --- | --- |
| T-a | Confirmar que ningún sistema externo usa la API del agente (tokens activos) | Borrar E3 |
| T-b | País del servidor y de los respaldos; retención y región de OpenAI (insumo para L2 y L6) | Respuesta legal L2 |
| T-c | ¿Se sigue extrayendo con OpenAI o se prueba un lector de formularios comprado, con confianza por campo? | Fase 4 |
| T-d | Umbral de confianza inicial (0,90 propuesto) y quién lo aprueba | Fase 4 |
| T-e | Fechas de llegada de los 1099 y del SSA-1099, verificadas en el IRS | Fase 3 (5.2) |
| T-f | Mecanismo de importación a TaxWise (bloqueado por el proveedor) | Mejora de la fase 5 |
| T-g | Panel "Proceso": ¿eliminar o regenerar desde este documento? | E9 |

**Con Juan Pablo**

| # | Pendiente |
| --- | --- |
| J1 | La sección 12 (módulo F1) de su documento, que no está en el PDF |

### Para después (no son decisiones, son trabajo identificado)

- Fichas casilla → línea de los formularios marcados con ⏳ en el inventario 4.0 (Schedule 1-A, A, B, D/8949, E, H, R, LEP; Forms 4137, 4684, 8829, 8862, 8863, 8880, 8888, 8889).
- Transcripciones del IRS (Wage & Income) con el Form 8821, y la regla que las compara (6.2).
- Precarga del año anterior para clientes que vuelven.
- Varias firmas (cuando el proceso esté validado con GTS).
