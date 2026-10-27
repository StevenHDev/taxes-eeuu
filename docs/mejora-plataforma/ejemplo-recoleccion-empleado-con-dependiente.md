# Ejemplo de recolección: empleado con un dependiente (año fiscal 2025)

27 oct. 2026 · Prueba del principio "**el IRS es la fuente de verdad y el 1040 es el formulario front**" (ver `docs/mejora-plataforma/revision-documento-analisis-diseno.md`, observación 3).

> **Caso:** llega María. Vive en Florida todo 2025, es empleada (recibe W-2) y tiene un hijo de 8 años que vive con ella.
>
> **Objetivo:**
> 1. Qué preguntas hacerle para saber **qué formularios y schedules** se necesitan.
> 2. Qué **documentos** pedirle.
> 3. Qué dato de cada documento **va a qué línea del 1040**.
>
> Todo sale del Form 1040 de 2025 y de las páginas e instrucciones del IRS citadas al final. Cifras de **2025** (la declaración que se presenta en 2026).

---

## 1. La idea en una línea

El 1040 tiene casillas y líneas fijas. **Cada pregunta existe para llenar una casilla del 1040 o para decidir si hace falta un anexo** (schedule o formulario) que después vuelca un número en una línea del 1040.

```
Preguntas ──► deciden qué anexos aplican ──► anexos y documentos ──► líneas del 1040
```

---

## 2. Preguntas, en orden

### A. Sobre María (encabezado del 1040)

| # | Pregunta | Para qué sirve | Casilla / regla del IRS |
| --- | --- | --- | --- |
| A1 | ¿Fue ciudadana o residente de EE. UU. **todo** 2025? | Si no, no es 1040 sino 1040-NR o declaración de estatus dual | Requisito de las herramientas del IRS y del crédito por hijos |
| A2 | ¿Tiene **SSN válido para trabajar**? (o ITIN) | **Desde 2025**, para el crédito por hijos (CTC) el contribuyente también necesita SSN válido para trabajar, emitido antes del vencimiento | Encabezado del 1040; Schedule 8812 |
| A3 | Fecha de nacimiento | Si nació antes del 2 de enero de 1961 suma deducción estándar adicional (y deducción para mayores en Schedule 1-A) | 1040 línea 12d |
| A4 | ¿Alguien puede reclamarla **a ella** como dependiente? | Cambia la deducción estándar y le quita créditos | 1040 línea 12a |
| A5 | Dirección | Encabezado | 1040 encabezado |
| A6 | ¿Recibió, vendió o cambió **activos digitales** (cripto)? Sí / No | Pregunta obligatoria en la portada | 1040 "Digital Assets" |

### B. Estado civil (decide la deducción estándar)

| # | Pregunta | Para qué sirve | Casilla / regla |
| --- | --- | --- | --- |
| B1 | ¿Estaba casada el **31 de diciembre de 2025**? | Si no, puede ser *Single* o *Head of household* | 1040 "Filing Status" |
| B2 | (Si no está casada) ¿Pagó **más de la mitad del costo de mantener su casa** (renta o hipoteca, servicios, comida en casa, seguro, reparaciones)? | Junto con un hijo que vivió con ella más de medio año, la hace **Head of household** | Pub. 501: deducción estándar **USD 23.625** (HOH) vs **USD 15.750** (Single) |

### C. El dependiente (sección "Dependents" del 1040)

| # | Pregunta | Para qué sirve | Casilla / regla |
| --- | --- | --- | --- |
| C1 | Nombre y apellido | Columnas (1) y (2) | 1040 Dependents |
| C2 | **SSN** del hijo (¿SSN válido para trabajar, ITIN o ATIN?) | Con **SSN válido** → crédito por hijos (hasta USD 2.200). Con ITIN/ATIN → solo crédito por otros dependientes (USD 500) | Columna (3); Schedule 8812 |
| C3 | Parentesco | Prueba de parentesco (hijo, hijastro, hijo de crianza, hermano o descendiente) | Columna (4) |
| C4 | Fecha de nacimiento | **Menor de 17** al 31 dic. → CTC. **Menor de 13** → crédito por cuidado. **Menor de 19** (o 24 si estudia) → EITC | Schedule 8812, Form 2441, Schedule EIC |
| C5 | ¿Vivió con usted **más de la mitad** de 2025? ¿Y **en EE. UU.**? | Prueba de residencia | Columna (5)(a) y (b) |
| C6 | ¿Es estudiante de tiempo completo? ¿Tiene discapacidad permanente? | Amplía la edad para EITC y para el cuidado | Columna (6) |
| C7 | ¿El hijo pagó más de la mitad de su propio sustento? | Si sí, no es dependiente | Prueba de sustento |
| C8 | ¿Es ciudadano, nacional o residente de EE. UU.? | Requisito del CTC | Schedule 8812 |
| C9 | ¿Otra persona (el otro padre) lo va a reclamar? ¿Hay **Form 8332** o acuerdo de custodia? | Evita que dos personas reclamen al mismo hijo | Form 8332 |

### D. Empleo

| # | Pregunta | Para qué sirve | Documento |
| --- | --- | --- | --- |
| D1 | ¿Cuántos empleadores tuvo en 2025? | Pedir **un W-2 por cada uno** | W-2 |
| D2 | ¿Recibió **propinas**? ¿Su ocupación es de las que habitualmente reciben propina? | Deducción nueva por propinas (hasta USD 25.000) | Schedule 1-A |
| D3 | ¿Le pagaron **horas extra**? | Deducción nueva por la "mitad" del pago de horas extra (hasta USD 12.500). En 2025 el empleador **no estaba obligado** a separarlas en el W-2, así que puede hacer falta el recibo de pago o una constancia | Schedule 1-A |

### E. Cuidado del hijo

| # | Pregunta | Para qué sirve | Documento |
| --- | --- | --- | --- |
| E1 | ¿Pagó guardería, niñera o campamento de día **para poder trabajar**? | Crédito por cuidado de hijos (gastos hasta USD 3.000 por una persona) | Form 2441 |
| E2 | (Si sí) Nombre, dirección y **SSN o EIN del proveedor**, y cuánto pagó | El Form 2441 no se puede llenar sin estos datos | Factura o recibo del proveedor |
| E3 | ¿La niñera trabajaba **en su casa** para usted? | Puede ser empleada doméstica y generar impuestos de nómina | Schedule H |
| E4 | ¿Su empleador le dio beneficios de cuidado de dependientes? | Se ve en el **W-2 casilla 10**; reduce el gasto que da derecho al crédito | W-2 |

### F. Preguntas de descarte (Sí / No)

Sirven para confirmar que no hay **otras** fuentes. Cada Sí abre su documento:

| Pregunta | Si dice Sí, se pide |
| --- | --- |
| ¿Tuvo seguro médico del **Marketplace** (healthcare.gov)? | **1095-A** → Form 8962 (muy común en familias con hijos) |
| ¿Recibió intereses o dividendos? | 1099-INT / 1099-DIV |
| ¿Trabajó por su cuenta o recibió ingresos sin W-2 (efectivo, Zelle, apps)? | 1099-NEC / 1099-K / registros → Schedule C (ya no sería "solo empleada") |
| ¿Recibió desempleo? | 1099-G |
| ¿Compró un carro **nuevo** con préstamo en 2025? | Estado del préstamo → Schedule 1-A (intereses) |
| ¿Pagó hipoteca o hizo donaciones grandes? | 1098 / recibos → evaluar Schedule A (casi siempre gana la deducción estándar) |
| ¿Hizo pagos estimados al IRS? | Confirmaciones → 1040 línea 26 |

### G. Reembolso

| Pregunta | Casilla del 1040 |
| --- | --- |
| ¿Quiere el reembolso por **depósito directo**? Banco, routing number, número de cuenta, Checking/Savings | Líneas 35a, 35b, 35c y 35d |
| ¿Prefiere aplicar parte al año siguiente? | Línea 36 |

---

## 3. Resultado para María

Supuestos: soltera, pagó más de la mitad de la casa, un solo W-2, el hijo tiene SSN y vivió con ella todo el año, pagó guardería en un centro (no niñera en casa), sin propinas ni horas extra, todo "No" en las preguntas de descarte.

| Formulario | ¿Aplica? | Por qué | Va a la línea del 1040 |
| --- | --- | --- | --- |
| **Form 1040** | ✅ Siempre | Es el formulario **front** | — |
| W-2 | ✅ Documento fuente | Es empleada | Líneas 1a y 25a (ver sección 4) |
| **Schedule 8812** | ✅ | Hijo menor de 17 con SSN válido → crédito por hijos (hasta USD 2.200; parte reembolsable hasta USD 1.700 si ganó al menos USD 2.500) | Línea **19** (crédito) y **28** (parte reembolsable) |
| **Schedule EIC** | ✅ si su AGI ≤ **USD 50.434** (un hijo) | Crédito por ingreso del trabajo (hasta USD 4.328 con un hijo); ingresos de inversión ≤ USD 11.950 | Línea **27a** |
| **Form 2441** | ✅ | Pagó guardería para trabajar | → Schedule 3 → línea **20** |
| Schedule 3 | ✅ | Lleva el crédito del Form 2441 al 1040 | Línea 20 |
| Schedule 1-A | ❌ | Sin propinas, horas extra, préstamo de carro nuevo ni 65+ | (sería línea 13b) |
| Schedule H | ❌ | La guardería es un centro, no empleada doméstica | — |
| Form 8962 | ❌ | Sin 1095-A | — |
| Schedule 1, 2, A, B, C | ❌ | Sin otros ingresos, ajustes, impuestos extra ni deducciones detalladas | — |

**Deducción estándar:** Head of household → **USD 23.625** en la línea 12e.

**Documentos que se le piden a María:**
- Su identificación y tarjeta de Social Security.
- Tarjeta de Social Security del hijo.
- W-2.
- Recibo anual de la guardería con su EIN.
- Datos bancarios para el depósito directo.
- Opcional: la declaración del año anterior.

---

## 3b. Cómo una respuesta activa o descarta anexos

### Quién llena qué

| Al cliente se le pide | Lo llena el preparador (o la plataforma) |
| --- | --- |
| **Respuestas** (estado civil, datos del hijo, ¿pagó guardería?…) | El **1040** |
| **Documentos** (W-2, tarjeta del Seguro Social, recibo de la guardería…) | Los **anexos**: Schedule 8812, Schedule EIC, Form 2441, Schedule 3… |

Al cliente **nunca se le pide un schedule**. Sus respuestas activan el anexo, y los documentos aportan los datos para llenarlo.

### Condiciones del crédito por hijos (CTC, Schedule 8812)

La edad sola no basta: deben cumplirse **todas** estas condiciones.

| Condición del IRS | Pregunta |
| --- | --- |
| Menor de 17 años al 31 dic. 2025 | C4 |
| Es hijo, hijastro, hijo de crianza, hermano o descendiente de alguno | C3 |
| Vivió con el contribuyente más de la mitad del año | C5 |
| No pagó más de la mitad de su propio sustento | C7 |
| Es ciudadano, nacional o residente de EE. UU. | C8 |
| Tiene SSN válido para trabajar (y el contribuyente también, desde 2025) | C2, A2 |
| Se reclama como dependiente en esta declaración | C9 |

Si falla **solo** la edad (17 o más) o el SSN del hijo (tiene ITIN), igual se llena el Schedule 8812, pero con el **crédito por otros dependientes de USD 500**. El 8812 solo se descarta si no hay ningún dependiente.

### Cadenas: una respuesta → un anexo → una línea del 1040

| Si la respuesta es… | …se activa o se descarta | …y llega a |
| --- | --- | --- |
| Hijo **menor de 17** con SSN válido | ✅ Schedule 8812 con CTC (hasta USD 2.200) | Líneas 19 y 28 |
| Hijo **de 17 o más**, o solo con ITIN | ✅ Schedule 8812 con ODC (USD 500) | Línea 19 |
| Hijo **menor de 13** y pagó cuidado para poder trabajar | ✅ Form 2441 | Schedule 3 línea 2 → línea 8 → **1040 línea 20** |
| Hijo **de 13 o más** (ej. 15), sin discapacidad | ❌ Form 2441, aunque pague cuidado | — |
| Hijo **menor de 19** (o 24 si estudia, o cualquier edad si tiene discapacidad) y AGI bajo el límite | ✅ Schedule EIC | Línea 27a |
| El cuidado lo dio una **niñera en la casa** del cliente (E3) | ✅ Posible **Schedule H**: el cliente es empleador de una empleada doméstica | Schedule 2 → 1040 |
| El **W-2 trae la casilla 10** (beneficio de cuidado del empleador, E4) | ✅ Form 2441 **Parte III**: descuenta del tope de gastos, y la parte gravable va a la línea 1e | Líneas 1e y 20 |

**Form 2441 en 2025:** gastos máximos de USD 3.000 (una persona) o USD 6.000 (dos o más). El crédito es entre el **20 % y el 35 %** de esos gastos según el ingreso. No es reembolsable: reduce el impuesto, pero no devuelve dinero si no hay impuesto. El SSN o EIN del proveedor es obligatorio (ver la Pub. 503).

---

## 4. Qué dato de cada documento llena qué línea del 1040

### W-2 → 1040

| Casilla del W-2 | Qué es | Dónde se usa |
| --- | --- | --- |
| **1** Salarios | Ingreso gravable | **1040 línea 1a** (suma de todos los W-2) |
| **2** Impuesto federal retenido | Lo que ya pagó | **1040 línea 25a** |
| 3 y 5 Salarios para Social Security / Medicare | Base de esos impuestos | No van directo al 1040. Sirven para revisar exceso de Social Security con varios empleadores (Schedule 3) o Medicare adicional si pasa de USD 200.000 (Form 8959) |
| 4 y 6 SS y Medicare retenidos | Ya pagados | Control |
| 7 Propinas de Social Security | Propinas reportadas | Schedule 1-A (si aplica la deducción) |
| 8 Propinas asignadas | Propinas no incluidas en la casilla 1 | 1040 línea 1c (vía Form 4137) |
| **10** Beneficios de cuidado de dependientes | Ayuda del empleador | **Form 2441 Parte III**; la parte gravable va a la línea 1e |
| 12 (códigos) | D/E = aportes a 401(k)/403(b); W = aporte a HSA; DD = costo del seguro médico | D/E informativos; W → Form 8889; DD solo informativo |
| 13 "Retirement plan" | Tiene plan de retiro en el trabajo | Limita la deducción por aportes a IRA |
| 14 Otros | El empleador puede reportar aquí horas extra o propinas calificadas | Schedule 1-A |
| 15–17 Estado | Florida no tiene impuesto estatal | Solo importa si trabajó en otro estado |

### Datos de la entrevista → 1040

| Dato | Línea o sección del 1040 |
| --- | --- |
| Nombre, SSN, dirección | Encabezado |
| Estado civil (HOH) | Filing Status |
| Cripto Sí/No | Digital Assets |
| Datos del hijo (C1 a C6) | Sección Dependents, columnas (1) a (7) |
| ¿Alguien la puede reclamar? / nacida antes de 1961 | Líneas 12a y 12d |
| Depósito directo | Líneas 35a a 35d |

### Anexos → 1040

| Anexo | Datos que necesita | Línea del 1040 |
| --- | --- | --- |
| Schedule 8812 | Hijo (edad, SSN, residencia), ingreso del trabajo (W-2 casilla 1), AGI | 19 y 28 |
| Schedule EIC | Nombre, SSN, año de nacimiento, parentesco y meses en EE. UU. del hijo, más ingreso del trabajo | 27a |
| Form 2441 | Proveedor (nombre, dirección, SSN/EIN, monto), hijo (nombre, SSN), ingreso del trabajo, W-2 casilla 10 | Schedule 3 → 20 |

---

## 5. Qué nos enseña sobre la plataforma

| Lo que pide el IRS en este caso | Plataforma hoy | Comentario |
| --- | --- | --- |
| Ciudadana/residente todo el año (A1) | ❌ No se pregunta | Ya señalado (tarea 2, 4.2.4) |
| **SSN válido para trabajar** del contribuyente y del hijo, distinguiendo SSN vs ITIN (A2, C2) | 🟡 Pide `identificacion_ssn_itin` y SSN del dependiente, pero **no distingue SSN de ITIN** | Decide entre USD 2.200 y USD 500 por hijo |
| Fecha de nacimiento y "alguien la puede reclamar" (A3, A4) | ✅ `fecha_nacimiento_contribuyente`, `puede_ser_reclamado_como_dependiente` | Este último es texto libre (tarea 2, 4.2.1) |
| Cripto Sí/No (A6) | ✅ `activos_digitales` | Texto libre |
| Pagó más de la mitad de la casa (B2) | ✅ `estado_civil.costeo_mas_mitad_hogar` | Texto libre |
| Datos del dependiente (C1–C7) | ✅ `info_dependientes`: nombre, fecha, SSN, relación, meses en el hogar, estudiante, discapacitado, sustento propio, ingreso | ➕ Falta "**en EE. UU.**" (C5b) y ciudadanía del hijo (C8) |
| Form 8332 (C9) | ✅ `form_8332` | — |
| W-2 de cada empleador (D1) | 🟡 Un solo archivo `w2` + texto "otro W-2" | Tarea 2, 4.2.5 |
| Propinas y horas extra (D2, D3) | 🟡 `beneficios_2025` | Falta la ocupación calificada y la constancia de horas extra |
| Cuidado: proveedor con SSN/EIN y monto (E1, E2) | ✅ `gastos_cuidado_dependientes` | — |
| ¿Niñera en casa? (E3) | ❌ | Schedule H (tarea 2, 4.2.4) |
| **Ingresos, retenciones** | 🐞 Se le piden al cliente como números (`ingresos`, `impuestos_retenidos`, `salarios_medicare`) | **Salen del W-2** (casillas 1 y 2). Confirma la tarea 2, 4.2.2 |
| Depósito directo | ✅ `info_bancaria` | Obligatorio aunque no aplique (tarea 2, 4.2.3) |

**Conclusión:** el principio funciona. Si el catálogo se arma **desde las casillas del 1040 hacia atrás**, cada pregunta tiene una razón clara (llena una casilla o activa un anexo), y lo que viene en un documento **no se le pregunta al cliente**: se lee del documento.

---

## Fuentes (IRS)

- [Form 1040 (2025)](https://www.irs.gov/pub/irs-pdf/f1040.pdf): líneas, sección de dependientes y deducciones estándar.
- [Instrucciones del Form 1040 (2025)](https://www.irs.gov/instructions/i1040gi)
- [Crédito tributario por hijos (español)](https://www.irs.gov/es/credits-deductions/individuals/child-tax-credit): USD 2.200, USD 1.700 reembolsable, SSN válido, ODC de USD 500.
- [Schedule 8812 (2025)](https://www.irs.gov/pub/irs-pdf/f1040s8.pdf) y [sus instrucciones](https://www.irs.gov/pub/irs-pdf/i1040s8.pdf)
- [Tablas del EITC](https://www.irs.gov/credits-deductions/individuals/earned-income-tax-credit/earned-income-and-earned-income-tax-credit-eitc-tables): límites y montos 2025.
- [¿Quién califica para el EITC? (español)](https://www.irs.gov/es/credits-deductions/individuals/earned-income-tax-credit/who-qualifies-for-the-earned-income-tax-credit-eitc)
- [Form 2441 (2025)](https://www.irs.gov/pub/irs-pdf/f2441.pdf): proveedor, USD 3.000 / 6.000, Parte III.
- [Publicación 503](https://www.irs.gov/publications/p503): gastos de cuidado de hijos y dependientes (menor de 13, porcentaje del crédito, empleados domésticos).
- [Schedule H](https://www.irs.gov/forms-pubs/about-schedule-h-form-1040): impuestos de empleados domésticos.
- [Publicación 501 (2025)](https://www.irs.gov/publications/p501): cabeza de familia, dependientes, deducción estándar.
- [Schedule 1-A: qué saber](https://www.irs.gov/newsroom/schedule-1-a-additional-deductions-what-to-know-about-the-new-form) y [preguntas sobre horas extra](https://www.irs.gov/newsroom/questions-and-answers-about-the-new-deduction-for-qualified-overtime-compensation)
- [¿A quién puedo reclamar como dependiente? (herramienta)](https://www.irs.gov/es/help/ita/whom-may-i-claim-as-a-dependent)

> ⚠️ Ejemplo para entender el flujo, no asesoría fiscal: las reglas completas (casos de custodia, desempate entre padres, estatus dual) están en la Pub. 501 y las instrucciones de cada anexo. Lo debe validar el preparador de GTS.
