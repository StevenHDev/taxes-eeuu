# Fase 6 — Cálculo validado y borrador del 1040: plan técnico

9 nov. 2026 · **✅ Aprobado por Steven el 9 nov. 2026** (todas las decisiones, ver "Decisiones tomadas"; revisado el mismo día: los montos se **leen de los documentos**, no los teclea el preparador) · Documento madre: [secciones 4, 5, 6, 7.3, 7.7, 9.5 y 10](../documento-madre.md) · Destinos ya verificados: [`verificacion-irs-destinos.md`](../verificacion-irs-destinos.md) · Subtareas para Sonnet 5.5: se escriben en `fase-6-subtareas.md` cuando este plan esté aprobado.

> **Objetivo:** que la plataforma calcule **el 1040 completo como borrador**, con cada línea **igual a la que da el método del IRS** para ese año, que el preparador pueda abrir cualquier línea y ver **cómo se calculó** (entradas con su origen, parámetro con su fuente y página del IRS, operación, destino y estado de validación), y que pueda bajar el **PDF oficial del IRS diligenciado**, marcado "Borrador — no presentar".
>
> **Criterio de terminado (cambia frente al documento madre 9.5):** ya no es "las mismas líneas que TaxWise" (no hay TaxWise disponible ni declaraciones de GTS; L5 y P9 no aplican). Es **"coincide con el cálculo del IRS"**: cada caso de prueba cita la línea, la hoja de cálculo o la tabla y la **página** del PDF del IRS del año, cubre los **límites de las tablas**, y se contrasta puntualmente con las herramientas públicas del IRS. Una línea sin casos que la respalden se muestra **"sin validar"**.
>
> **Piloto:** persona natural + Schedule C, una sola firma. El 1040 es el "front"; los schedules y los documentos lo alimentan.
>
> **Objetivo del producto (Steven):** el cliente aporta documentos, **el sistema extrae** y el preparador **solo valida**; no pasarle al preparador trabajo que el sistema puede hacer. En esta fase: los montos que hoy están en documentos que no se leen (registros del negocio, recibo de cuidado, comprobantes de pagos, útiles del educador…) **se leen como un W-2**, con confianza y motivo en palabras; lo dudoso va a la cola de excepciones; el preparador confirma o corrige. Solo cuando el cliente no tiene ningún documento, el preparador anota el dato como **último recurso**, con motivo e historial.
>
> **Buenas prácticas:** skills `laravel-best-practices`, `react-best-practices` y `vercel-react-best-practices`; cada entrega cita la regla que aplica. Reglas de interfaz de Steven (guía 4.2): skeletons, responsive a 375 px y escritorio, jerarquía, `components/ui`, accesibilidad, estados vacío/error/éxito, estados de interacción, textos en es y en.
>
> **Modelo por tarea:** el diseño, las decisiones fiscales y el oráculo del IRS se fijan en este plan. **Todo lo programa Sonnet 5.5** en subtareas autocontenidas y en paralelo por olas. Cada entrega dice qué se puede repartir y con qué archivos.
>
> **Base:** `main` al 9 nov. (`991111c`): F0 menos F0.4, F1, F2, F3 (menos 3.6), F4 y F5 en producción.

## Decisión ya tomada: lectura con OpenAI (L2)

**Steven, 9 nov.:** los documentos de montos de esta fase (incluidos los **extractos bancarios**, los recibos y los registros del negocio) **se leen igual con OpenAI**, con la **misma política que los demás documentos de F4** (bandera `tax.lectura_automatica`, mismo cliente `OpenAiClient::leerConEsquema`, mismo cifrado, mismos registros sin valores). **No** se agrega una revisión extra ni una excepción para ellos. L2 (uso de OpenAI) sigue pendiente a nivel legal **global** para toda la plataforma y **no bloquea** esta lectura.

## Qué hay hoy (verificado en el código)

### Las 12 calculadoras no calculan nada para los clientes de hoy

- Están en `app/Services/Reglas/` y las orquesta `App\Services\DeterminacionFiscalService` (348 líneas), que guarda el resultado en `determinaciones_fiscales` (`TipoDeterminacion`, `version_reglas = '2025.1'`).
- **Leen `campos_cliente`** (flujo viejo: `transversal.estado_civil`, `form_1040.ingresos`, `schedule_c.ingresos_negocio`…). Desde F2.10 los clientes usan la entrevista del catálogo y `campos_cliente` está **vacía en producción**. Resultado: **hoy ninguna calculadora recibe datos del flujo nuevo** (entrevista, lecturas de F4, decisiones de F5).
- Solo se ven en la pestaña Historial (`FichaAnterior` + `components/determinacion-fiscal-panel.tsx`, con el botón que llama `clientes.determinaciones.store`).
- Pruebas: una por calculadora en `tests/Unit/Services/Reglas/` (12 archivos, ~1.000 líneas) y `tests/Feature/DeterminacionFiscalTest.php` (375 líneas). **Prueban la fórmula, no el IRS**: `TaxableIncomeAndTaxCalculatorTest::test_single_tramo_22_por_ciento` espera **USD 5.914** para un ingreso gravable de USD 50.000 soltero, y la **Tax Table de 2025 (Pub. 1040, p. 8, fila 50,000–50,050) dice USD 5.920**. El IRS exige la tabla por debajo de USD 100.000 (instrucciones de la línea 16); la tabla usa el punto medio de cada franja de USD 50.

| Calculadora | Qué cubre | Huecos frente al IRS (verificado o por verificar) | Parámetros |
| --- | --- | --- | --- |
| `DependentQualificationCalculator` | Hijo calificado / pariente calificado por reglas propias (relación, meses ≥ 6, edad, ingreso) | Duplica la decisión que en F5 toma el preparador (`ClasificacionPersona`); no mira el SSN válido (CTC exige SSN válido para trabajar) | `dependiente_calificado.*`, `credito_cuidado_dependientes.edad_limite_dependiente` |
| `FilingStatusCalculator` | Deriva MFJ, QSS, HOH o Single de `estado_civil` | **Sin MFS**; en F5 el preparador ya decide el estado civil con `EstadoCivilIrs` (incluye MFS) | — |
| `AgiCalculator` | Suma bruta − ajustes | Seguro Social tratado como 0 % gravable; IRA, educador, préstamo estudiantil sin modelar | — |
| `StandardDeductionCalculator` | Estándar vs suma cruda de itemizadas | Sin adicional por 65+ o ciego (1040 l. 12d); sin tope SALT, sin piso médico del 7,5 %; QSS agrupado con MFJ (bien); sin MFS | `deduccion_estandar.*` |
| `QbiCalculator` | 20 % del QBI, tope 20 % del ingreso gravable menos ganancia de capital | Form 8995 no está en el catálogo (pendiente de F5); usa `ganancias_capital` como "net capital gain" (por verificar contra las instrucciones del 8995) | `qbi.umbral_*` |
| `TaxableIncomeAndTaxCalculator` | Tramos marginales | **No usa la Tax Table** (< USD 100.000) ni la hoja QDCG; resultado con centavos | `tax_brackets.single/mfj/hoh` (sin MFS) |
| `SelfEmploymentTaxCalculator` | 92,35 %, 12,4 % hasta el tope, 2,9 % | **No descuenta salarios W-2** (Schedule SE línea 8a: casillas 3 y 7); umbral de USD 400 contra la ganancia y no contra la línea 4c; tasas fijas en el código | `self_employment_tax.tope_salario_social_security` |
| `AdditionalMedicareTaxCalculator` | 0,9 % sobre el exceso | Sin umbral MFS; no resta la retención adicional del W-2 (casilla 6) | `additional_medicare_tax.*` |
| `NiitCalculator` | 3,8 % | Fuera del piloto | `niit.*` |
| `ForeignTaxCreditCalculator` | De minimis sin 1116 | Fuera del piloto | `credito_ftc.*` |
| `CreditEligibilityCalculator` | CTC + ODC con reducción (redondeo hacia arriba por cada USD 1.000, correcto) y 2441 | **2441 interpola lineal**; el Form 2441 de 2025 trae una **tabla escalonada** (35 % hasta USD 15.000, baja 1 punto por cada USD 2.000, 20 % desde USD 43.000); sin tope por ingreso del trabajo de cada cónyuge; **sin ACTC** (parte reembolsable, 8812 Parte II); el CTC no se limita al impuesto (8812 línea 14) | `credito_ctc.*`, `credito_odc.*`, `credito_cuidado_dependientes.*` |
| `SettlementCalculator` | Impuesto − créditos + otros impuestos vs pagos | Sin EITC, ACTC, 8962 ni crédito por exceso de Seguro Social | — |

- **No existen:** EITC, Form 8962, Schedule 1-A, Form 8863, Schedule 3 completo, la Tax Table.

### Parámetros por año

- `parametros_fiscales` (F1.2) con grupo, tipo, fuente y `validado_en/por`. Lector cacheado `App\Support\ParametrosFiscales::valor()` y `valorRequerido()` (lanza `RuntimeException` si falta o está pendiente).
- **2025:** 31 valores en `ParametrosFiscalesSeeder` + 5 en `database/data/catalogo-fiscal/2025/parametros.php`. Metadatos en `parametros-metadatos.php`.
- **2026:** `CatalogoFiscalCopiador` copia los parámetros **pendientes** (`valor = null`) y `2026/ajustes.php` solo fija 4 (pariente calificado USD 5.300, fecha de 65+, vencimientos). **Cualquier cálculo de 2026 hoy lanza la excepción "pendiente".**
- **Constantes fijas en el código** que deben pasar a parámetros: 0,124, 0,029, 0,9235 y USD 400 (SE), 0,009 (8959), 0,038 (8960), 0,20 (QBI).

### El catálogo de líneas tiene errores de destino (verificado contra el Form 1040 de 2025, página 2)

`database/data/catalogo-fiscal/2025/formularios.php`, Form 1040:

| En el catálogo | Según el Form 1040 de 2025 |
| --- | --- |
| Línea `12` "Deducción estándar" | Es la **12e** ("Standard deduction or itemized deductions"); 12a–12d son casillas (dependiente, 65+, ciego) |
| No existe la 13a | **13a** = deducción QBI (Form 8995 o 8995-A); **13b** = Schedule 1-A, línea 38 |
| No existen 11b, 14, 15, 16, 17, 18, 21, 22, 24, 25d, 32, 33, 34 | Existen y son de cálculo (11b repite el AGI en la página 2) |
| Línea `23` = `sch-2:4;sch-2:1a` | La 23 es **Schedule 2, línea 21**. La 1a del Schedule 2 (devolución del 8962) suma en la **línea 3** del Schedule 2, que va a la **línea 17** del 1040 |
| Línea `31` = `sch-3:9;sch-3:10` | La 31 es **Schedule 3, línea 15** (total de la Parte II) |
| `sch-1a` sin líneas (`ficha = pendiente`) | El Schedule 1-A de 2025 tiene Partes I a VI y su línea 38 va al 1040 13b |
| Schedule C `8-27` en una sola fila con origen `respuesta: D15` | El Schedule C de 2025 tiene una línea por categoría (8 a 27b, verificadas en `irs-prior/f1040sc--2025.pdf`): ver 6.3 |

- `catalogo_lineas` ya tiene la columna **`campo_pdf`** (nula), pensada para el PDF. **No** tiene fuente ni validación (esas columnas las tienen formularios, preguntas, documentos y reglas, con `TieneFuenteIrs`).

### El hueco real: los documentos con los montos se piden pero no se leen

La entrevista pregunta **si**, no **cuánto** (G1, G2, E6, E8 y D15 son `si_no`), y está bien: el monto está en los documentos que el cliente ya sube por el portal (F3). Pero esos documentos tienen **0 casillas** en el catálogo, así que el lector de F4 no les saca nada:

| Documento (`catalogo_documentos`) | Formatos | Qué monto trae | Alimenta |
| --- | --- | --- | --- |
| `registros-ingresos-gastos` | pdf, jpg, png, heic (varios archivos) | Extractos del banco y de tarjetas, recibos, cobros sin 1099, millaje, oficina en casa; o la **plantilla opcional de F3.7** (`public/plantillas/registros-negocio-{es,en}.{pdf,xlsx}`, con las categorías del Schedule C) | Schedule C líneas 1–2, 8–27b, 30 |
| `recibo-cuidado` | pdf, jpg, png, heic | Proveedor (nombre, dirección, SSN o EIN) y monto pagado | Form 2441 Partes I–II; R-12 |
| `comprobantes-pagos` | — | Fecha y monto de cada pago estimado o con la extensión | 1040 línea 26; Schedule 3 línea 10 |
| `recibos-utiles-educador` | — | Total de útiles comprados | Schedule 1 línea 11 |
| `constancia-horas-propinas` | — | Horas extra y propinas calificadas | Schedule 1-A Partes II y III |
| `estado-prestamo-vehiculo` | — | Intereses pagados, VIN, fecha del préstamo | Schedule 1-A Parte IV |

- Los formatos de los cuatro últimos se confirman al escribir la subtarea (por verificar en `documentos.php`).
- **F6 revierte una parte de la decisión de F3.7 (Steven, 9 nov.).** En F3.7 (2 nov.) se decidió: "no se le piden totales al cliente; sumar es trabajo del preparador". Lo primero sigue igual (al cliente no se le piden totales); lo segundo cambia: **desde el 9 nov. lo suma el sistema y el preparador valida la suma.**

### Lo que el lector de F4 ya da y sus límites para estos documentos

- **Ya existe:** `EsquemaLectura` arma un esquema estricto **por casilla** del catálogo (una respuesta fija por casilla: valor, legibilidad, página, evidencia); `LectorOpenAi` llama `OpenAiClient::leerConEsquema()`; `CalculadoraConfianza` calcula la confianza (0,99 en el texto del PDF; 0,90 claro; 0,60 dudoso o formato incorrecto; 0 no se ve) con `MotivoConfianza` en palabras; `UbicadorEnPdf::buscar()` encuentra el valor en el texto del PDF y da el recuadro; `PaginasDocumento` pasa a imágenes; `DatosDocumento` es el único camino de escritura (con `documento_dato_cambios`); la cola de excepciones y el visor con evidencia. Cada **archivo** se lee por separado (`documento_lecturas.documento_id`).
- **No sirve tal cual para documentos con renglones** (un extracto con 80 movimientos, un recibo con varias líneas):
  - `documento_datos` tiene `unique(lectura_id, casilla)`: **un valor por casilla**, no una lista.
  - `PaginasDocumento::MAXIMO_PAGINAS = 10` y falla con más ("demasiadas páginas para ser un formulario"). Un extracto anual de una sola cuenta puede pasarse.
  - Los registros no aceptan `xlsx` ni `csv`, aunque la plantilla de F3.7 se descarga en `xlsx`.
- `OrigenDato` solo tiene `lector` y `preparador`. No existe "anotado sin documento".

### Motor de reglas (F4) y lo que falta para R-10, R-11 y R-12

- `App\Services\Lectura\Reglas\MotorReglas` corre la lista fija `REGLAS` (R-01, R-03 a R-09, R-11) solo si la regla está `implementada` en el catálogo del año; cada clase implementa `Regla::evaluar(Entrevista, $datosPorItem): list<Hallazgo>`; una excepción por `entrevista_id + clave`; las aceptadas no se tocan. El texto para personas sale de `ExplicadorExcepcion`.
- `reglas.php`: R-10 y R-12 sin `implementada`; R-11 `implementada` con severidad `revision` ("en F4 solo avisa").
- `R11HijoSinSsn` mira parentesco (C3), menor de 17 (C2) y C9 ≠ `ssn_valido`. **No** mira la tarjeta leída: el catálogo de `tarjeta-ssn` tiene la casilla `no-valido-para-trabajar`.
- R-12 y R-10 no tienen de dónde leer hasta que se lean el recibo de cuidado y los registros del negocio (6.3).

### Expediente (F5): las decisiones que el cálculo debe usar

- `expedientes.estado_civil_irs` (`EstadoCivilIrs`: single, mfj, **mfs**, hoh, qss) con motivo, quién y cuándo.
- `expediente_personas.clasificacion` (`ClasificacionPersona`: hijo calificado, pariente calificado, no dependiente) con motivo.
- `HojaTaxwise` (1.050 líneas) **"no calcula nada fiscal"**: arma filas por casilla y respuesta, y totales de control (suma de casillas `monto` por línea con origen `casilla`), con `estadoDelDato()` (excepción > pendiente > baja confianza > corregido > validado > sin validar). Lo sensible va nulo con `mascara`; se revela con `RequirePassword` + `throttle:10,1` y bitácora `Revelado`; el CSV deja `Exportado`.

### PDF oficial: qué hay y qué se verificó

- **No hay librería para llenar PDF.** `composer.json` solo tiene `smalot/pdfparser` (lee texto). El `Dockerfile` instala `poppler-utils` (`pdftoppm`, `pdftotext`) y `libheif-examples`. No hay Java, Python ni Node en la imagen de ejecución. No hay corredor de pruebas de JavaScript (sin vitest).
- **Los PDF del IRS son híbridos AcroForm + XFA** (verificado el 9 nov. con `pdfinfo` y `pypdf` sobre los PDF bajados de `irs.gov/pub/irs-pdf/`):

| PDF | Año que sirve hoy esa URL | Campos AcroForm (texto / casillas) |
| --- | --- | --- |
| `f1040.pdf` | 2025 | 126 / 73 |
| `f1040s1.pdf` | 2025 | 68 / 5 |
| `f1040s1a.pdf` | 2025 | 54 / 0 |
| `f1040s2.pdf` | 2025 | 51 / 12 |
| `f1040s3.pdf` | 2025 | 37 / 0 |
| `f1040sc.pdf` | **2026** | 83 / 26 |
| `f1040sse.pdf` | **2026** | 26 / 1 |
| `f1040s8.pdf` | **2026** | 35 / 6 |
| `f2441.pdf` | 2025 | 57 / 15 |
| `f1040sei.pdf` | 2025 | 26 / 12 |
| `f8962.pdf` | 2025 | 131 / 10 |

  - Los nombres de campo son opacos (`topmostSubform[0].Page1[0].f1_29[0]`, `c1_8[0]`) y no traen descripción legible: el mapa línea → campo hay que armarlo **por año**, mirando el PDF.
  - **La URL `irs-pdf/` cambia de año sin aviso** (Schedule C, SE y 8812 ya sirven 2026). Las versiones de 2025 están en `irs.gov/pub/irs-prior/<form>--2025.pdf` (verificado: 200 para `f1040s8--2025`, `f1040sc--2025`, `f1040sse--2025`, `f1040--2025`). Los borradores en `irs-dft/` responden. **Las plantillas se guardan en el repositorio, por año, con su hash.**
  - Al ser híbridos, si se llena solo el AcroForm sin quitar el XFA, un lector que use XFA (Acrobat) puede mostrar el formulario vacío. Hay que **quitar el XFA** y generar las apariencias.

### Datos de prueba disponibles

- `tests/Feature/Entrevista/MariaTest.php` y `tests/Feature/Expediente/MariaExpedienteTest.php` (+ `Concerns/ArmaExpediente`, `ArmaHojaTaxwise`): María, HOH, un hijo de 8 años, W-2, guardería, 1095-A.
- Documentos de prueba de Javier (`~/Downloads/documentos-prueba-javier/`): tarjetas de Javier y de su cónyuge Dennis, W-2 2025, 1099-NEC, 1099-K, 1099-MISC y **dos recibos de útiles de maestro** (`09-recibo-util-1-papeleria-maestro.png`, `10-recibo-util-2-casa-util-escolar.jpg`) → **MFJ + Schedule C + gastos de educador**, el caso más cercano al piloto, y la primera prueba real de la lectura de recibos.
- **Faltan** documentos de prueba de registros del negocio (extracto de banco, extracto de tarjeta, recibos sueltos, plantilla llena) y de recibo de cuidado: se fabrican en 6.3 (sintéticos, sin datos reales).
- Usuarios `es_prueba` de producción (todos lo son al 30 oct.) y las conversaciones que Steven crea.

## Alcance

| Entra en F6 | Sale de |
| --- | --- |
| Corregir los destinos del catálogo (12e, 13a, 13b, 23, 17, 31, Schedule C por línea…) y darle a cada línea calculada su **fuente del IRS con página** y su estado de validación | 7.3, 7.7 |
| Parámetros de 2025 completos y de 2026 cargados, con fuente; tablas como datos (Tax Table por método, 2441, EIC, 8962) | 7.3 condición 2, 7.7 |
| **Oráculo del IRS**: tablas del IRS extraídas del PDF y casos con cita de línea y página | Decisión de Steven (9 nov.) |
| **Lectura de los documentos de montos** (registros del negocio, recibo de cuidado, comprobantes de pagos, útiles del educador, constancia de horas extra y propinas, préstamo del vehículo) con el lector de F4, confianza explicada, cola de excepciones y visor | Decisión de Steven (9 nov.); 6.1 |
| **Motor nuevo de cálculo** sobre el expediente (decisiones de F5, lecturas de F4 y F6, entrevista), formulario por formulario, con traza por línea | 7.3, 7.7 B |
| Validar y rehacer las 12 calculadoras (las del piloto); completar **EITC, 8962 y Schedule 1-A**; agregar el **Form 8995** simple (QBI bajo el umbral, decisión h) al catálogo y al cálculo | tareas F6 |
| **Respaldo manual sin documento**: último recurso cuando el cliente no tiene ningún papel | Decisión de Steven (9 nov.) |
| **R-10**, **R-11 bloqueante** (con el 8812), **R-12** (con el 2441) y reglas nuevas de la lectura de montos | 6.2, decisión del 2 nov. |
| Pestaña **"Borrador del 1040"** con **"cómo se calculó"** por línea | 7.3, 7.7 B |
| **PDF oficial del IRS diligenciado** (borrador con marca de agua) del 1040 y sus schedules | 7.3 |
| Retirar `DeterminacionFiscalService` y las calculadoras viejas | 9.3 |

## Fuera de esta fase (pasa a F7)

- Fichas pendientes y sus cálculos: Schedule A, B, D/8949, E, H, R; Forms 4137, 8829, 8863, 8880, 8889, 8995-A; Seguro Social gravable (hoja de la línea 6b); dividendos calificados y ganancias de capital (hoja QDCG); NIIT, crédito extranjero, AMT. Si el cliente tiene algo de esto, el borrador lo dice ("línea no calculada en esta versión") y queda **parcial** (decisión i).
- Lectura de los documentos de deducciones detalladas (recibos médicos, de donaciones, predial) y de alquileres (`registros-alquiler`): van con el Schedule A y el Schedule E en F7. El diseño de 6.3 (renglones) les sirve igual.
- Depreciación y sección 179 (Schedule C línea 13, Form 4562), costo de la mercancía vendida con inventario (Parte III) y Form 8829 (oficina en casa por el método normal): se leen los montos que el cliente traiga, pero el cálculo de la depreciación y del inventario lo hace el preparador en TaxWise; la línea sale `no_soportada` con el motivo.
- Form 8995-A (QBI por encima del umbral): la línea 13a sale `no_soportada` y la calcula el preparador. El Form 8995 simple sí entra (decisión h).
- Resumen final para el cliente con montos (7.4): el cliente **no ve** el borrador en F6.
- Firma del 8879, presentación electrónica, importación a TaxWise (T-f).
- R-13 (año anterior), transcripciones del IRS.

## Resumen de entregas

| # | Entrega | Tamaño | Depende de | En paralelo con |
| --- | --- | --- | --- | --- |
| 6.0 | Catálogo: destinos corregidos, líneas de cálculo con fuente y validación, Schedule C por línea | Mediano | — | 6.1, 6.2, 6.3a, 6.4a |
| 6.1 | Parámetros 2025 completos, 2026 cargados, tablas como datos | Mediano | — | 6.0, 6.2, 6.3a, 6.4a |
| 6.2 | Oráculo del IRS: tablas extraídas del PDF y formato de casos | Mediano | — | 6.0, 6.1, 6.3a, 6.4a |
| **6.3 tramo 1** | **Lectura de montos, lo claro:** 6.3a documentos de campos fijos + 6.3b hojas `xlsx`/`csv` sin IA, modelo de renglones, contrato `SumasRegistros` y R-14 básica | Grande | 6.0 (líneas del Schedule C) | 6.1, 6.2, 6.4a |
| **6.3 tramo 2** | **Lectura de montos con IA:** 6.3c extractos, recibos y fotos (con medición de costo y tope por cliente) · 6.3d cuadre y conciliación con los 1099 · 6.3e R-14 completa y visor de renglones | **Muy grande** (lo más difícil de la fase) | 6.3 tramo 1 | 6.4i, 6.5, 6.6, **6.7** |
| 6.4 | Motor de cálculo nuevo (núcleo + calculadoras por formulario, Form 8995 incluido + orquestador) | Grande | 6.0, 6.1, 6.2; **6.3 tramo 1** para las calculadoras C, 2441, 1, 1-A y 26 (usan la **interfaz** `SumasRegistros`, simulada en sus pruebas) | 6.3 tramo 2 |
| 6.5 | Respaldo manual sin documento (último recurso) | Pequeño | 6.3 tramo 1 (mismas claves) | 6.4, 6.3 tramo 2 |
| 6.6 | Reglas R-10, R-11 bloqueante, R-12 (y registrar R-15) | Pequeño | 6.3 tramo 1, 6.4, 6.5 | 6.3 tramo 2, 6.7a |
| 6.7 | Pestaña "Borrador del 1040" y "cómo se calculó" | Grande | 6.4 | 6.3 tramo 2, 6.6, 6.8a |
| 6.8 | PDF oficial diligenciado | Grande | 6.0, 6.7a | 6.7b, 6.3 tramo 2 |
| 6.9 | Casos de punta a punta y contraste con el IRS | Mediano | 6.3 (los dos tramos)–6.8 | — |
| 6.10 | Retiro del motor viejo y cierre | Pequeño | 6.9 | — |

**Orden:** primero lo que hace que los números sean correctos y salgan **de los documentos claros** (6.0 a 6.2, 6.3 tramo 1, 6.4 a 6.6), después cómo se ven (6.7) y **el PDF al final** (6.8): llenar un PDF con números sin validar no ayuda a nadie (decisión c). El **tramo 1** de la lectura va **antes** del motor porque el Schedule C, el 2441, el Schedule 1 línea 11, el 1-A y la línea 26 no tienen otra entrada. El **tramo 2** (extractos con IA) va **en paralelo con la pantalla** y no frena el motor: se conecta a `SumasRegistros` sin cambiar su contrato.

---

## Estrategia de validación contra el IRS

Es el corazón de la fase. El riesgo principal no es un error de programación sino **leer mal una instrucción del IRS y repetir el mismo error en el código y en el valor esperado de la prueba**. Por eso hay cinco capas, y ninguna depende de que alguien haya entendido bien la instrucción.

> Esta estrategia valida el **cálculo** (dadas unas entradas, ¿la línea es la del IRS?). La **lectura** de los documentos de montos se valida aparte, con documentos de prueba y la cola de excepciones (6.3): un error de lectura no se arregla con una tabla del IRS.

### 1. Tablas del IRS extraídas del PDF, no tecleadas (oráculo mecánico)

- Las tablas oficiales se sacan **con `pdftotext -layout` del PDF fijado** y se guardan como CSV en `tests/Fixtures/irs/<anio>/`, con un `fuentes.json` (URL, fecha de descarga, SHA-256 del PDF, páginas):
  - **Tax Table** de la Pub. 1040 (2025: páginas 2 a 13; ~2.000 filas × 4 columnas). Verificado el 9 nov.: la fila 50,000–50,050 da 5,920 (soltero), 5,526 (MFJ), 5,920 (MFS), 5,663 (HOH).
  - **EIC Table** de la Pub. 1040 (2025: desde la página 16), por estado civil y número de hijos.
  - **Tabla del Form 2441** (línea 8, "decimal amount").
  - Para el 8962: la tabla de la línea de pobreza y la de "applicable figure" de las instrucciones del 8962 (por verificar la página en `i8962.pdf` de 2025).
- Un script de desarrollo (`scripts/irs/extraer-tablas.php`, fuera de `app/`) hace la extracción; el CSV se **revisa una vez a mano** contra el PDF (diez filas al azar, anotadas en `fuentes.json`) y se commitea. No corre en producción.
- Las pruebas recorren **todas las filas**: el motor debe dar exactamente el valor de la tabla. Así el método (el punto medio de la franja de USD 50 y el redondeo) queda probado contra el IRS completo, y los límites de cada franja salen solos.

### 2. Ejemplos que trae el propio IRS (anclas)

Las instrucciones traen ejemplos resueltos; cada uno es una prueba con su cita. Ya verificados en la Pub. 1040 (2025): **Tax Table**, MFJ con ingreso gravable USD 25.300 → **USD 2.562** (p. 2); **EIC Table**, soltero, un hijo con SSN válido, monto de la hoja USD 2.455 → **USD 842**. Los demás (8812, 2441, 8962, Schedule SE, Schedule 1-A) se buscan en sus instrucciones de 2025 al escribir la subtarea (**por verificar** cuáles traen ejemplo).

### 3. Casos escritos por quien no ve el código (doble lectura)

- Cada caso es un archivo `tests/Fixtures/irs/casos/<anio>/<codigo>.php` con: entradas (hechos, no conclusiones), las **líneas esperadas** y, por cada línea, `fuente` = formulario o instrucción, línea u hoja de cálculo, **página** y URL del PDF fijado.
- **La subtarea que escribe los casos no es la que escribe la calculadora**, y no puede leer `app/Services/Calculo/`. Transcribe la hoja de cálculo del IRS a mano, paso por paso, en el propio caso (`pasos`), como lo haría un preparador en papel. Si la calculadora y el caso no coinciden, el agente principal revisa contra el PDF **antes** de tocar cualquiera de los dos y anota qué estaba mal.
- Una prueba de estructura (`CasosIrsTest::test_cada_caso_cita_linea_pagina_y_fuente`) falla si un caso no trae cita.

### 4. Límites y propiedades

Para cada tabla o tope, casos en el valor exacto, un dólar antes y un dólar después:

| Qué | Límites |
| --- | --- |
| Tax Table / Tax Computation Worksheet | Bordes de franja (USD 5, 25 y 50 según el tramo), cambio de tasa, el paso a la hoja en USD 100.000, ingreso gravable 0 |
| EITC | Fin de la fase de entrada, inicio y fin de la reducción, con 0/1/2/3 hijos, MFJ vs resto, tope de ingreso de inversión (cifra por verificar en las instrucciones de 2025) |
| 8812 | Umbrales de reducción (USD 200.000 / 400.000, redondeo a USD 1.000 hacia arriba), tope por impuesto (línea 14), ACTC con el umbral de ingreso del trabajo (cifra por verificar en las instrucciones del 8812 de 2025) |
| 2441 | Cada escalón de USD 2.000 entre USD 15.000 y 43.000; tope de gastos de 1 y de 2 personas; ingreso del trabajo del cónyuge que gana menos |
| Schedule SE | Línea 4c = USD 399 / 400; tope del Seguro Social con y sin salarios W-2 (línea 8a) |
| Schedule C | Comidas (línea 24b) con el porcentaje deducible (por verificar en las instrucciones del Schedule C de 2025); millaje a la tarifa estándar (por verificar); oficina en casa simplificada con su tope de pies cuadrados (por verificar) |
| Schedule 1 línea 11 | Tope de gastos de educador por persona (por verificar en las instrucciones del Schedule 1 de 2025) |
| Schedule 1-A | Topes (USD 25.000 propinas; USD 12.500/25.000 horas extra; USD 10.000 intereses; USD 6.000 mayores) y umbrales de reducción (USD 150.000/300.000; 100.000/200.000; 75.000/150.000), según el Schedule 1-A de 2025 leído el 9 nov.; el **sentido del redondeo** de las líneas 11, 19 y 28 se confirma en las instrucciones del 1-A (por verificar) |
| 8962 | 100 %, 150 %, 200 %, 250 %, 300 % y 400 % de la línea de pobreza (por verificar qué cortes aplican en 2025 y 2026); tope de devolución |

Además, propiedades baratas que atrapan errores gruesos: el impuesto no baja cuando sube el ingreso gravable; un crédito nunca es negativo; las líneas suman lo que el formulario dice que suman.

### 5. Contraste externo puntual

- **IRS EITC Assistant** (`irs.gov/eitc`): para tres casos de EITC; se anota fecha y resultado en el caso.
- **Interactive Tax Assistant** del IRS para preguntas de calificación (dependiente, estado civil, gastos de educador).
- **Free File Fillable Forms** hace las sumas de las líneas; está disponible en temporada (por verificar si se puede usar en noviembre para 2025).
- El contraste se registra en el caso (`contraste`: herramienta, fecha, resultado). No reemplaza a las capas 1 a 3.

### De validado a "validado contra el IRS" en la pantalla

Una línea calculada pasa a **"validada contra el IRS"** para un año cuando un administrador la marca en el catálogo (6.0), y la marca solo se permite si existe al menos un caso de ese año que la cubre (el botón deshabilitado dice "Falta un caso de prueba que cubra esta línea"). La lista de casos que cubren cada línea sale de los archivos de casos. Mientras no esté marcada, la línea se ve **"sin validar"** en el borrador y en el PDF (7.3, condición 4).

---

## 6.0 Catálogo: destinos corregidos y líneas de cálculo con fuente

**Hoy:** ver "El catálogo de líneas tiene errores de destino". Las líneas no tienen fuente ni validación.

**Enfoque:**
- Migración `agrega_fuente_a_catalogo_lineas`: `fuente_texto`, `fuente_url`, `fuente_pagina` (texto: "p. 2", "Hoja de la línea 16"), `validado_por`, `validado_en`; el modelo `CatalogoLinea` usa `TieneFuenteIrs` (`eloquent-casts`). `campo_pdf` ya existe y se usa en 6.8.
- `formularios.php` (2025):
  - **1040:** corregir 12 → 12e; agregar 11b, 12d (casillas 65+/ciego, origen `respuesta`), 13a, 13b (`sch-1a:38`), 14, 15, 16, 17 (`sch-2:3`), 18, 21, 22, 24, 25d, 32, 33, 34; 23 = `sch-2:21`; 31 = `sch-3:15`.
  - **Schedule C, una línea por categoría** (verificadas en el Schedule C de 2025): 1 (ingresos brutos), 2 (devoluciones), 3, 4, 5, 6, 7, 8 (publicidad), 9 (auto y camioneta), 10 (comisiones y cuotas), 11 (trabajo contratado), 12 (agotamiento), 13 (depreciación), 14 (beneficios de empleados), 15 (seguros que no son de salud), 16a/16b (intereses; subdivisión por verificar), 17 (servicios legales y profesionales), 18 (gastos de oficina), 19 (planes de pensión), 20a (alquiler de vehículos, maquinaria y equipo), 20b (alquiler de otros bienes del negocio), 21 (reparaciones), 22 (materiales), 23 (impuestos y licencias), 24a (viajes), 24b (comidas deducibles), 25 (servicios públicos), 26 (sueldos), 27a (Form 7205), 27b (otros gastos, de la línea 48), 28, 29, 30, 31. Origen: `casilla` hacia las categorías que produce la lectura de registros (6.3) — por ejemplo `registros-ingresos-gastos:gasto-18`.
  - **Schedule 2:** líneas 3 y 21. **Schedule 3:** 8 y 15. **Schedule SE:** 3, 4c, 6, 8a, 10, 11. **Schedule 8812:** las de la Parte I y II que se calculan (números por verificar en el 8812 **de 2025**, `irs-prior/f1040s8--2025.pdf`, porque `irs-pdf/` ya sirve 2026). **2441:** línea 8 (porcentaje) y 9. **EIC:** 1040 27a (y las casillas del 27 que traiga 2025, por verificar). **Schedule 1-A:** ficha `completa`, líneas 1, 3, 4c, 13, 14c, 21, 23, 30, 37, 38. **Form 8995 (nuevo, decisión h):** formulario con ficha `completa`, activado por el Schedule C con ganancia; sus líneas (números por verificar en el Form 8995 de 2025) y la última → 1040 línea 13a; se activa en `preguntas.php`/`activaciones` solo como formulario, sin preguntas nuevas.
- Cada línea `calculo` lleva `fuente_texto`, `fuente_url` y `fuente_pagina`. El importador ya desactiva lo que sale del archivo (F4.1).
- `2026/ajustes.php`: solo las diferencias de línea que muestren los borradores 2026 (por verificar al cargar).
- `CatalogoFiscalVerificador`: nuevo chequeo "toda línea `calculo` o `linea` tiene fuente" y "todo `origen_clave` apunta a una línea o casilla que existe en el mismo año".
- **Pantalla del catálogo, pestaña Formularios:** la línea muestra su fuente (enlace y página) y el botón "Marcar validada contra el IRS" (mismo patrón y permisos de F1.5), deshabilitado con el motivo si no hay casos que la cubran (lista que entrega 6.2).
- `HojaTaxwise::totales()` no cambia de lógica, pero el destino 12 → 12e y las líneas nuevas del Schedule C cambian textos y totales de la hoja: se ajusta `HojaTaxwiseTest`.

**Pruebas:** `catalogo-fiscal:verificar` limpio en 2025 y 2026; cada línea calculada con fuente; 23 → `sch-2:21`; 31 → `sch-3:15`; Schedule C con las líneas 8–27b; `CatalogoRealTest` con los conteos nuevos a propósito; el botón de validar exige caso.

**Archivos:** migración nueva, `CatalogoLinea`, `CatalogoFiscalImportador` (si hace falta pasar las columnas nuevas), `CatalogoFiscalVerificador`, `2025/formularios.php`, `2026/ajustes.php` (solo líneas; ver dueños por ola), `README.md` de datos, la página de Formularios del catálogo y sus textos. **Un solo dueño para `formularios.php` en toda la fase** (esta subtarea; 6.8 guarda `campo_pdf` en archivos aparte).

**Skills:** `eloquent-casts`, `arch-service-classes` (verificador), `controller-form-requests` (marcar validada).

## 6.1 Parámetros por año y tablas como datos

**Enfoque:**
- **2025, completar:** pasar las constantes del código a `parametros_fiscales` con fuente y página: SE (0,124, 0,029, 0,9235, USD 400 sobre la línea 4c), 8959 (0,009, umbral MFS), QBI (0,20), `tax_brackets.mfs` (Rev. Proc. 2024-40, por verificar), deducción estándar MFS (= soltero, según el Form 1040 de 2025: "Single or Married filing separately, $15,750", verificado), adicionales por 65+ y ciego (por verificar en las instrucciones de la línea 12d), 2441 como **tabla** (`credito_cuidado_dependientes.tabla`: lista de `{hasta, decimal}` leída del Form 2441 de 2025, verificada el 9 nov.), EIC (por número de hijos: tasa de entrada, monto máximo, inicio y fin de la reducción, MFJ y resto, tope de ingreso de inversión; fuente: Rev. Proc. 2024-40 y la EIC Table, por verificar cada cifra), 8812 (ACTC: tope reembolsable por hijo y umbral de ingreso del trabajo, por verificar), Schedule 1-A (topes y umbrales leídos del Schedule 1-A de 2025), 8962 (línea de pobreza 2024 para 2025, tabla de "applicable figure", topes de devolución; por verificar en `i8962.pdf` 2025).
- **Nuevos por la lectura de montos (todos por verificar con su fuente):** tarifa estándar de millaje del negocio de 2025 (aviso del IRS del año), porcentaje deducible de comidas (instrucciones del Schedule C, línea 24b), método simplificado de oficina en casa (tarifa por pie cuadrado y tope, instrucciones del Schedule C, línea 30), tope de gastos de educador (instrucciones del Schedule 1, línea 11).
- **2026:** cargar desde la fuente oficial del año (Rev. Proc. de ajustes por inflación para 2026 y los borradores de los formularios 2026; **por verificar** número de Rev. Proc. y cifras) en `2026/ajustes.php`, cada uno con fuente y **sin validar**. Lo que el IRS no haya publicado queda pendiente y el borrador de 2026 dice qué línea no se puede calcular y por qué (en vez de un error 500).
- `ParametrosFiscales::valorRequerido()` se mantiene; el motor nuevo captura la excepción por línea y la convierte en "no se puede calcular: falta el parámetro X de 2026 (Catálogo → Parámetros)".
- **La Tax Table no se guarda como parámetro:** se calcula con el método del IRS (tramos del año sobre el punto medio de la franja, redondeado) y se prueba contra la tabla completa extraída (6.2). Los tramos siguen en `tax_brackets.*`.
- Metadatos (`parametros-metadatos.php`) y migración espejo para producción, como en F1.2.

**Pruebas:** cada parámetro de 2025 que usa el motor existe, no está pendiente y tiene fuente; `verificar` reporta los pendientes de 2026 por nombre; cambiar un parámetro de 2026 no toca 2025.

**Archivos:** `ParametrosFiscalesSeeder`, `2025/parametros.php`, `2025/parametros-metadatos.php`, migración espejo (6.1a); `2026/ajustes.php` solo la sección `parametros` (6.1b).

**Skills:** `arch-value-objects` (las tablas se leen como objetos con su método `buscar()`), `eloquent-casts`.

## 6.2 Oráculo del IRS

**Enfoque:**
- `tests/Fixtures/irs/2025/`: `pdf/` (los PDF fijados: Pub. 1040, f1040, s1, s1a, s2, s3, sc, sse, s8, 2441, sei, 8962 y sus instrucciones, de `irs-prior/` o `irs-pdf/` según el año), `fuentes.json`, `tax-table.csv`, `eic-table.csv`, `f2441-tabla.csv`, `f8962-*.csv`.
- `scripts/irs/extraer-tablas.php` (pdftotext + expresiones regulares; se corre a mano).
- `tests/Fixtures/irs/casos/README.md`: formato de un caso (entradas, `pasos`, `lineas` esperadas con `fuente`, `contraste`) y la regla de doble lectura.
- `tests/Support/CasosIrs.php`: carga los casos y da, por año, **qué líneas cubre cada caso** (lo usa el botón de 6.0, a través de un archivo generado `database/data/catalogo-fiscal/<anio>/cobertura-casos.php` que el comando `calculo:cobertura` reescribe; así producción no lee `tests/`).
- Primeros casos (escritos aquí, sin código): Tax Table (todas las filas), EIC Table (todas), 2441 (todos los escalones), los dos ejemplos de la Pub. 1040.

**Pruebas:** `CasosIrsTest` (estructura y citas); las pruebas que recorren las tablas quedan **marcadas como pendientes** hasta que exista la calculadora (6.4) y se activan ahí.

**Archivos:** solo `tests/Fixtures/irs/**`, `tests/Support/CasosIrs.php`, `scripts/irs/**`. **Esta subtarea no ve `app/Services/Calculo/`.**

---

## 6.3 Lectura de documentos de montos

**Hoy:** ver "El hueco real". Seis documentos que el cliente ya sube tienen 0 casillas y no se leen.

**Principio:** se reutiliza **todo** lo de F4 —`EsquemaLectura`, `OpenAiClient::leerConEsquema`, `CalculadoraConfianza` con `MotivoConfianza` en palabras, `UbicadorEnPdf`, `PaginasDocumento`, `DatosDocumento` (un solo camino de escritura e historial), la cola de excepciones y el visor con evidencia—. Lo único nuevo es que algunos documentos traen **renglones** (una lista de movimientos) en vez de casillas fijas, y que la plataforma **suma** esos renglones por categoría. **La IA lee y propone; la plataforma suma, calcula la confianza y decide qué va a revisión; el preparador valida** (documento madre 6.4).

**Lo que esta primera versión promete y lo que no:** lee bien los casos claros (plantilla de F3.7 en Excel o PDF, hojas `csv`, recibos impresos, comprobantes del IRS, extractos en PDF con texto); suma con trazabilidad al renglón y a la página o fila; **no promete exactitud total** en extractos escaneados, fotos de recibos arrugados o cuentas mezcladas personal/negocio. Lo que no cuadra o se lee con dudas **va a excepciones** y el preparador lo confirma o corrige, igual que un W-2.

**Dos tramos (ajuste de Steven, 9 nov.):**

| Tramo | Qué | Cuándo |
| --- | --- | --- |
| **1. Lo claro, sin IA nueva** | 6.3a documentos de campos fijos (con el lector de F4 tal cual) · 6.3b hojas `xlsx`/`csv` leídas sin IA, modelo de renglones y contrato `SumasRegistros` | **Antes del motor** (6.4): le da al Schedule C, al 2441, al Schedule 1 línea 11, al 1-A y a la línea 26 sus entradas |
| **2. Extractos y fotos con IA** | 6.3c renglones leídos con IA · 6.3d cuadre y conciliación con los 1099 · 6.3e excepciones R-14 completas y visor de renglones | **En paralelo con la pantalla (6.7)**; no frena el motor: las calculadoras usan una implementación **simulada** de `SumasRegistros` en sus pruebas hasta que el tramo 2 la conecta |

### Tramo 1

#### 6.3a Documentos de campos fijos (casillas, como un W-2)

Se agregan casillas al catálogo (`documentos.php`, con `tipo_dato`, `clave`, `sensible` y destino) y el lector de F4 los lee sin cambios de código. Cada **archivo** es una lectura; la plataforma suma los archivos del mismo documento (por ejemplo, cuatro comprobantes de pagos estimados).

| Documento | Casillas (código · tipo) | Destino |
| --- | --- | --- |
| `recibo-cuidado` (uno por proveedor o por recibo) | `proveedor-nombre` · nombre · **sensible**; `proveedor-direccion` · texto · **sensible**; `proveedor-tin` · tin · **sensible** (SSN o EIN tal como está impreso); `monto-pagado` · monto · clave; `periodo-desde`, `periodo-hasta` · fecha; `persona-cuidada` · nombre · **sensible** (para emparejar con la persona del bloque C; si no viene, el preparador la asigna) | Form 2441 Parte I (proveedor y monto) y Parte II (gastos por persona); R-12 |
| `comprobantes-pagos` (uno por pago) | `fecha-pago` · fecha · clave; `monto` · monto · clave; `tipo-pago` · código (`estimado`, `extension`, `otro`) según lo que diga el comprobante (1040-ES, 4868, Direct Pay, EFTPS); `anio-aplicado` · año | 1040 línea 26 (estimados del año); Schedule 3 línea 10 (pago con la extensión) |
| `recibos-utiles-educador` (uno por recibo) | `comercio` · nombre; `fecha` · fecha; `total` · monto · clave; `descripcion` · texto (lo que compró, para que el preparador vea que son útiles) | Schedule 1 línea 11, con el tope del año (6.1) |
| `constancia-horas-propinas` | `horas-extra-calificadas` · monto; `propinas-calificadas` · monto; `ocupacion` · texto | Schedule 1-A Partes II y III (líneas 4 y 14) |
| `estado-prestamo-vehiculo` | `intereses-pagados` · monto · clave; `vin` · texto · **sensible** (por verificar si lo es); `fecha-prestamo` · fecha; `vehiculo-nuevo` · si_no | Schedule 1-A Parte IV (línea 22) |

- La forma exacta de cada documento y qué casillas son "clave" para R-03 se confirma al escribir la subtarea mirando ejemplos reales (los dos recibos de útiles de Javier, un 1040-ES, un comprobante de Direct Pay). Lo sensible queda cifrado y enmascarado, como hoy. La confianza es la de F4 sin cambios (0,99 si el valor está en el texto del PDF; es una casilla fija).
- **El monto de 2441 por persona:** si el recibo trae la persona, se empareja con el bloque C por nombre (en el servidor, nunca en el prompt); si no, la excepción "¿A quién cuidaron?" deja que el preparador elija la persona con un `Select`, sin teclear montos.
- En `documentos.php` también se marca `registros-ingresos-gastos` con `lectura = renglones` y se agregan sus casillas fijas (clase, período, totales impresos, millas del negocio, pies cuadrados de la casa y de la oficina), que usan los dos tramos.

#### 6.3b Hojas `xlsx`/`csv` sin IA, modelo de renglones y contrato `SumasRegistros` (decisión l)

**Qué cambia para el cliente:** `registros-ingresos-gastos` acepta además **`xlsx` y `csv`** (opcional: nunca se le obliga). El cliente puede subir la **plantilla de F3.7 en Excel** (la misma que ya descarga) o una hoja propia. En el texto "Qué subir" del portal, la plantilla se presenta como **la vía más sencilla**, sin presionar ("Si llevas tus cuentas, la plantilla es lo más rápido; si no, sube tus extractos y recibos"). El agente de WhatsApp puede decir lo mismo cuando explique qué falta, en la próxima versión de los prompts (borrador con `agente:borrador-prompts`, que Steven publica). PDF, fotos y extractos se siguen leyendo con IA (tramo 2).

**Modelo de datos de renglones** (lo usan los dos tramos; no cabe en `documento_datos`, que es un valor por casilla):
- `documento_renglones`: `lectura_id`, `entrevista_documento_id`, `numero` (orden), `pagina` (o número de fila en una hoja), `recuadro` (JSON, del ubicador; nulo en hojas), `fecha`, `descripcion` (**cifrada**: puede traer nombres y números de cuenta), `monto` (**cifrado**, decimal en texto), `sentido` (`ingreso`, `gasto`, `transferencia`, `personal`, `no_se`), `categoria` (código de línea del Schedule C, nula si `no_se`), `categoria_origen` (`documento` si el propio papel o la hoja la dice / `ia` si la propuso el modelo / `preparador`), `confianza`, `motivo_confianza`, `legibilidad`, `evidencia`, `cubierto_por_item_id` (el 1099 que ya lo reporta, ver 6.3d), `origen` (`lector`/`preparador`), `corregido_por`, `corregido_en`, `motivo`. Historial en `documento_renglon_cambios` (mismo patrón que `documento_dato_cambios`).
- `documento_lecturas` agrega `clase_detectada` (`plantilla`, `extracto_banco`, `extracto_tarjeta`, `recibo`, `hoja_calculo`, `millaje`, `otro`) y, para extractos, `periodo_desde`, `periodo_hasta`, `cuenta_ultimos4` (cifrado) y los **totales impresos** (`total_depositos`, `total_retiros`, `saldo_inicial`, `saldo_final`, cifrados) para el cuadre del tramo 2.

**Lector de hojas** (`App\Services\Lectura\LectorHojas`, sin OpenAI; lector `hoja` en `documento_lecturas.lector`):
- **Plantilla de F3.7** (se reconoce por su encabezado y sus etiquetas, en es y en): cada etiqueta de categoría se mapea a su línea del Schedule C con una tabla fija en el catálogo del año (`2025/plantilla-registros.php`); cada monto es un renglón con `categoria_origen = documento`; millas y pies cuadrados van a sus casillas.
- **Hoja propia:** se leen los **encabezados** (fecha, descripción, monto, débito/crédito, categoría; sinónimos en es y en en el mismo archivo del catálogo). Las filas con fecha y monto son renglones; la categoría se toma si su texto coincide con una de la plantilla o del Schedule C; si no coincide, el renglón queda `no_se`. **Lo que no se reconoce va a R-14**: columnas sin identificar, filas sin monto, categorías desconocidas.
- **Confianza:** un valor leído de una celda es exacto: 0,99 con el motivo nuevo **"Leído de la hoja de cálculo (sin IA)"**. La categoría reconocida cuenta como del documento.
- Librería: `openspout/openspout` para `xlsx` (versión por verificar en Packagist); `csv` con `fgetcsv` (separador detectado; UTF-8 o Latin-1). Fórmulas: se toma el valor calculado guardado en el archivo; una celda con fórmula sin valor va a R-14.

**Contrato `SumasRegistros`** (`arch-service-classes`, interfaz + implementación): `para(Expediente): ResultadoSumas` con, por cada línea del Schedule C (1 sin formulario, 2, 8–27b, millas, pies cuadrados), el total en `Dinero`, los ids de los renglones que lo forman, la confianza y su motivo. En el tramo 1 suma los renglones de hojas y plantillas (y los que haya); el tramo 2 le agrega el cuadre y la conciliación sin cambiar el contrato. Las calculadoras de 6.4 dependen **solo de la interfaz** y en sus pruebas usan una implementación simulada.

**R-14 (revisión), primera versión** — "Registros del negocio por revisar": en el tramo 1, columnas o filas no reconocidas de una hoja y renglones sin categoría. El tramo 2 le suma el extracto que no cuadra, el depósito dudoso frente a un 1099 y el archivo que no se pudo leer. Una excepción por archivo o por categoría, nunca por renglón.

### Tramo 2

#### 6.3c Extractos, recibos sueltos y fotos con IA

**Esquema de lectura:** `EsquemaLectura` agrega un modo **lista** para documentos con `lectura = renglones`: además de las casillas fijas del documento, pide `renglones[]` con `pagina`, `fecha`, `descripcion`, `monto`, `sentido`, `categoria` (una de las líneas del Schedule C del año, con su texto en inglés del formulario como opciones cerradas del esquema), `legibilidad` y `evidencia`. Las instrucciones dicen: "no inventes renglones; si no estás seguro de la categoría usa `no_se`; un movimiento entre cuentas propias es `transferencia`".

**Confianza de cada renglón** (la calcula la plataforma, nunca el modelo; misma tabla de `CalculadoraConfianza`, con motivos nuevos en `MotivoConfianza`, cada uno con su texto y explicación, y la tabla de `guia-confianza-de-lectura.md` actualizada):

| Confianza | Motivo | Cuándo |
| --- | --- | --- |
| **0,99** | Está en el texto del PDF | `UbicadorEnPdf` encuentra el monto en el texto **de esa página** y es el **único** movimiento con ese valor en la página. (Las casillas fijas, como hoy.) |
| **0,95** | Está en el texto del PDF, pero se repite en la página | El monto aparece en el texto de esa página **y hay otro movimiento con el mismo valor** en ella: no se puede comprobar cuál de los dos es (motivo nuevo). |
| 0,90 | Leído con claridad | Desde imagen, sin texto del PDF. |
| 0,60 | Leído con dudas / formato incorrecto | Igual que en F4. |
| 0 | No se ve / sin valor | Igual que en F4. |

- **Categoría:** si la dice el documento (recibo con rubro impreso) cuenta como leída; si la **propuso la IA**, el renglón lleva el motivo nuevo **"Categoría propuesta por la IA"** y su suma de categoría va a revisión (6.3d). Un renglón `no_se` lleva **"Sin categoría"**.

**Clases de documento y qué hace cada una** (primera versión realista):

| Clase | Cómo se lee | Qué sale | Riesgo |
| --- | --- | --- | --- |
| Plantilla de F3.7 en `xlsx` | Sin IA (6.3b) | Totales por categoría con `categoria_origen = documento` | Bajo: el caso más claro |
| Hoja propia `xlsx`/`csv` | Sin IA, por encabezados (6.3b) | Renglones; lo no reconocido a R-14 | Bajo |
| Plantilla de F3.7 en PDF o foto | Con IA, casillas fijas, una por categoría | Totales por categoría con `categoria_origen = documento` | Bajo |
| Recibo suelto | Con IA, un renglón (comercio, fecha, total) | Gasto con categoría propuesta | Medio: la categoría |
| Extracto bancario o de tarjeta en PDF con texto | Con IA, renglones + totales impresos | Ingresos y gastos; cuadre contra los totales impresos | Medio: depósitos ambiguos, cuentas mezcladas |
| Extracto escaneado o foto | Igual, sin texto del PDF (confianza ≤ 0,90) | Igual | Alto: legibilidad y renglones omitidos |
| Registro de millaje | Casilla `millas-negocio` | Línea 9 con la tarifa estándar (6.1) | Medio |
| Otro | Se marca `otro` | R-14 "No sé qué documento es" | — |

**Páginas:** `PaginasDocumento::MAXIMO_PAGINAS` (10) se mantiene para formularios; para `lectura = renglones` el límite pasa a un parámetro del catálogo (`lectura.maximo_paginas_renglones`, **40** al empezar, decisión n) y la lectura se hace **por bloques de páginas** (una llamada por bloque, los renglones se juntan). Un archivo más largo queda "no se pudo leer: demasiadas páginas, súbelo por meses" (texto para el cliente y R-14 para el preparador).

**Costo de OpenAI (antes de subir el límite de 40 páginas):**
- **Medición:** un comando local `lectura:medir-costo` lee los documentos sintéticos de `tests/Fixtures/lectura/montos/` (contra OpenAI de verdad, solo en local y con la clave de desarrollo) y anota por documento: páginas, llamadas, tokens de entrada y salida, segundos y costo estimado según la tarifa vigente del modelo (por verificar). El resultado se escribe en el "Cómo quedó" de la entrega.
- **Tope por cliente:** parámetro del año `lectura.maximo_paginas_ia_por_cliente` (páginas leídas con IA por cliente y año, sumando todos sus documentos de renglones). Al llegar al tope, los archivos siguientes no se mandan a OpenAI: quedan "sin leer: tope de lectura alcanzado" con R-14, y el preparador decide (subir el tope en Parámetros o pedir la plantilla). El valor inicial sale de la medición; hasta tenerla, un valor conservador que Steven confirma.
- El límite de 40 páginas por archivo solo se sube después de la medición y con el tope por cliente funcionando.

#### 6.3d Sumas completas: cuadre y conciliación con los 1099

`SumasRegistros` (la misma implementación del tramo 1) agrega:
- **Suma por categoría** de todos los renglones vigentes de todos los archivos, excluyendo `transferencia`, `personal`, `no_se` y los cubiertos por un 1099. Cada suma guarda **qué renglones la forman** (ids), así el "cómo se calculó" llega al renglón y a su página o fila.
- **Confianza de la suma** = la menor de sus renglones, con motivo **"Suma de N renglones; el menos seguro: …"**. Si algún renglón tiene la categoría propuesta por la IA, la suma queda en 0,60 con el motivo "Incluye renglones clasificados por la IA" → **va a revisión** (R-03 sobre la suma, no sobre cada renglón, decisión m). Al validar la suma, sus renglones quedan validados.
- **Cuadre del extracto:** si el extracto trae totales impresos, la suma de los depósitos y retiros leídos debe dar esos totales (y saldo inicial + depósitos − retiros = saldo final). Si no cuadra, **todo el extracto** va a R-14 "Lectura incompleta del extracto: faltan USD X en depósitos", porque es la señal más fuerte de un renglón omitido o inventado.
- **Conciliación con los 1099 (evitar el doble conteo):** un depósito cuyo pagador coincide con el emisor de un 1099-K, 1099-NEC o 1099-MISC del cliente (nombre del emisor leído en F4; comparación en el servidor) se marca `cubierto_por_item_id` y **no** suma como "ingreso sin formulario". Si la coincidencia es dudosa (nombre parecido, monto distinto), R-14 "¿Este depósito ya está en el 1099-K de X?". El 1099-K reporta montos brutos y puede no coincidir con lo depositado (comisiones, reembolsos); por eso la conciliación es **por pagador**, no por monto, y el preparador confirma.
- **Resultado hacia el Schedule C:** línea 1 = 1099 que el preparador mandó a C (F5) + ingresos sin formulario de los registros; líneas 8–27b = sumas por categoría; 9 = millas × tarifa (si el cliente usa la tarifa estándar; por verificar las condiciones); 24b = comidas × porcentaje deducible; 30 = método simplificado con los pies cuadrados (si no, `no_soportada`: Form 8829 en F7).

#### 6.3e Excepciones, visor y lo que ve el cliente

- **R-14** completa (los casos del tramo 2) y **R-15 (informativa)** — "Dato anotado sin documento" (6.5), en `reglas.php` 2025 y 2026, con fuente "Proceso" y el Schedule C de 2025.
- **Visor:** para un documento de renglones, el panel derecho muestra **las sumas por categoría** (con confianza y motivo) y, al abrir una, sus renglones (fecha, descripción enmascarada si es sensible, monto, categoría, confianza); tocar un renglón resalta su recuadro en la página (en una hoja, muestra la fila). El preparador puede: **validar la suma**, **cambiar la categoría de un renglón** (un `Select` con las líneas del Schedule C), **marcarlo personal o transferencia**, o **corregir el monto** con motivo. No teclea totales: corrige renglones y la suma se rehace.
- **Cliente:** ve el documento como "recibido / en revisión / validado" como cualquier otro (F3/F4); si un archivo no se pudo leer, el texto le dice qué subir ("súbelo por meses", "toma la foto con más luz", "si llevas tus cuentas, la plantilla es lo más rápido"). Nunca ve montos ni categorías.

### Pruebas de 6.3

`tests/Feature/Lectura/LecturaMontosTest.php`, `LectorHojasTest.php`, `RenglonesTest.php`, `SumasRegistrosTest.php`; OpenAI simulado con `Http::fake`.
- **Tramo 1:**
  - Recibo de cuidado: casillas leídas, TIN cifrado y enmascarado en props, persona emparejada; sin TIN → R-12 (6.6).
  - Comprobantes de pagos: cuatro archivos suman en la línea 26; uno de extensión va a Schedule 3 línea 10.
  - Útiles del educador con los dos recibos de Javier (lectura sembrada): total y tope.
  - Plantilla de F3.7 en `xlsx` (la del repositorio, llena): cada categoría a su línea, confianza 0,99 "Leído de la hoja de cálculo (sin IA)", sin llamar a OpenAI.
  - Hoja propia `csv` con encabezados en inglés y una columna desconocida: renglones leídos y R-14 por la columna; categoría desconocida → `no_se` y R-14.
  - `SumasRegistros` del tramo 1: suma por categoría con los ids de sus renglones.
  - El portal acepta `xlsx` y `csv` solo en `registros-ingresos-gastos`.
- **Tramo 2:**
  - Renglones: 0,99 si el monto está en el texto de esa página y es único; **0,95 si otro movimiento de la misma página tiene el mismo valor**; ≤ 0,90 desde imagen; categoría de la IA → suma a 0,60 y a revisión; validar la suma valida sus renglones.
  - Cuadre: extracto sintético con un renglón faltante → R-14; completo → sin excepción.
  - Conciliación: depósito de un pagador con 1099-K → `cubierto` y no suma; pagador parecido → R-14.
  - Archivo de más páginas que el límite → texto para el cliente y R-14, sin error 500; cliente que llega al tope de páginas con IA → los archivos siguientes no se mandan a OpenAI (`Http::assertNothingSent`) y quedan con R-14.
- **Siempre:** corregir la categoría de un renglón rehace las sumas y deja historial; ningún monto ni descripción en `excepciones.detalle`, logs ni bitácora.

**Documentos de prueba:** sintéticos, hechos para la fase (sin datos reales), en `tests/Fixtures/lectura/montos/`: plantilla llena (`xlsx` y PDF), hoja propia `csv`, extracto bancario con texto (3 páginas, con totales impresos y dos movimientos de igual monto en una página), el mismo extracto escaneado, extracto de tarjeta, dos recibos sueltos, recibo de guardería, comprobante 1040-ES y de Direct Pay. Además, a mano en local, con un extracto real **de prueba** de Steven si lo quiere aportar.

### Archivos de 6.3 (repartidos en subtareas)

- **6.3a** (tramo 1): `2025/documentos.php` (casillas de los cinco documentos de campos fijos, `lectura = renglones` y casillas fijas de los registros, formatos `xlsx`/`csv` de los registros), fixtures `tests/Fixtures/lectura/montos/**`. **Dueño único de `documentos.php` en la fase.** Los cambios de 2026 los pasa por escrito a 6.1b.
- **6.3b** (tramo 1): migraciones (`documento_renglones`, cambios, columnas de `documento_lecturas`), modelos `DocumentoRenglon`, `DocumentoRenglonCambio`, `LectorHojas`, `2025/plantilla-registros.php`, interfaz e implementación de `SumasRegistros`, `MotivoConfianza` (motivo "hoja de cálculo"), `DatosDocumento` (escribir y corregir renglones), `R14RegistrosNegocio` (primera versión), su registro en `MotorReglas` y las filas de R-14 y R-15 en `reglas.php` (dueño de esos dos archivos **en su ola**), textos del portal ("Qué subir"), `composer.json` (`openspout/openspout`).
- **6.3c** (tramo 2): `EsquemaLectura` (modo lista), `LectorOpenAi` (renglones y bloques de páginas), `PaginasDocumento` (límite por documento), `MotivoConfianza` (0,95 repetido en la página, categoría de la IA, sin categoría), parámetros `lectura.maximo_paginas_renglones` y `lectura.maximo_paginas_ia_por_cliente` (en `2025/parametros.php` y `2026/ajustes.php`, coordinado con 6.1 por ola), comando `lectura:medir-costo`.
- **6.3d** (tramo 2): `SumasRegistros` (cuadre y conciliación) y su prueba.
- **6.3e** (tramo 2): `R14RegistrosNegocio` (casos del tramo 2), textos en `ExplicadorExcepcion`, visor (servidor y pantalla del panel de sumas y renglones), `CorregirRenglonRequest`, textos del portal.

**Skills:** `arch-service-classes` (`SumasRegistros`, `LectorHojas`), `arch-dto-pattern` (renglón leído, `ResultadoSumas`), `eloquent-casts` (`encrypted` en descripción, monto y totales), `eloquent-relationships` (lectura → renglones), `eloquent-eager-loading` (renglones de todos los archivos en una consulta), `sec-mass-assignment`, `controller-form-requests` (`CorregirRenglonRequest` con `validation-conditional-rules`: motivo obligatorio al cambiar monto). Frontend del visor: `rerender-no-inline-components`, `js-index-maps` (renglones por categoría), `rendering-content-visibility` para listas largas de renglones, `bundle-barrel-imports`.

---

## 6.4 Motor de cálculo nuevo

**Hoy:** 12 calculadoras sobre `campos_cliente` que nadie alimenta.

**Decisión de diseño:** **no** se adaptan las calculadoras viejas: tienen otra entrada (`campos_cliente`), devuelven arreglos sueltos sin traza, usan `float` con centavos y una semántica de estado civil sin MFS. Se escribe un motor nuevo en `app/Services/Calculo/`, **por formulario del IRS** (el mismo orden y los mismos números de línea), reutilizando la lógica que sí está bien (reducción del CTC por cada USD 1.000, QSS con la tabla de MFJ, el orden SE → AGI). Las viejas se borran en 6.10.

### 6.4a Núcleo

- `Dinero` (`arch-value-objects`): enteros en centavos; `aDolares()` redondea como dicen las instrucciones ("Rounding Off to Whole Dollars" de las instrucciones del 1040; **por verificar** el texto de 2025 y si el IRS permite elegir). Nada de `float` en el motor.
- `Linea` (DTO `readonly`, `arch-dto-pattern`): `formulario`, `linea`, `valor` (`Dinero|bool|string|null`), `estado` (`calculada`, `no_aplica`, `falta_dato`, `no_soportada`), `motivo` (texto para personas cuando no se calcula), `traza`.
- `Traza`: lista de pasos `{tipo: entrada|parametro|operacion|resultado, texto, valor, origen}`. **`origen` distingue de dónde salió cada entrada** y es lo que muestra "cómo se calculó":

| Origen | Cuándo | Texto (servidor, `__()`) |
| --- | --- | --- |
| `leido` | Casilla o suma de renglones leída de un documento y no corregida | "Leído del W-2 #1 (Empresa X), casilla 1 · validado por Ana" / "Suma de 14 renglones de 3 extractos · confianza 0,90" |
| `corregido` | El preparador corrigió el dato o un renglón | "Corregido por Ana el 12 nov.: …motivo" |
| `sin_documento` | Respaldo manual de 6.5 | "**Anotado sin documento** por Ana el 12 nov.: …motivo" |
| `respuesta` | Entrevista | "Respondido por WhatsApp el 30 oct." |
| `decision` | Revisión de F5 | "Decidido por Ana el 4 nov." |
| `linea`, `parametro` | Otra línea; parámetro del año | "1040 línea 11b" / "USD 2.200 · instrucciones del 8812 de 2025, p. N" |

  `origen` lleva la referencia (id del dato, del renglón o de la suma, código de pregunta, clave del parámetro), **nunca el valor sensible**.
- `Declaracion`: el conjunto de líneas de un cliente y año, con `linea('1040', '11a')`, `formularios()`, `estadoGeneral()` (`completa`, `parcial`, `incompleta`) y `version_reglas`.
- `EntradasDeclaracion` (DTO) y `ArmadorEntradas` (`arch-service-classes`): junta, para un expediente:
  - estado civil para el IRS (`expedientes.estado_civil_irs`); **sin decisión, el cálculo no corre** y dice "Falta decidir el estado civil en Revisión";
  - personas con su clasificación (`expediente_personas`), fecha de nacimiento (C2), tipo de número (C9) y la casilla `no-valido-para-trabajar` de su tarjeta leída;
  - datos vigentes de cada documento (`DatosDocumento::vigentes()`), **las sumas de registros** (interfaz `SumasRegistros`, 6.3b; cuadre y conciliación en 6.3d) y los datos de campos fijos de 6.3a, ignorando los ítems "no aplica";
  - respuestas de la entrevista que el cálculo usa (fecha de nacimiento para 65+, G3/G4 para 35–37);
  - el respaldo manual (6.5), **solo** para las claves que no tienen documento.
  Una consulta por tipo, con carga anticipada (`eloquent-eager-loading`); los valores sensibles se leen descifrados **solo dentro del motor** y nunca salen en la traza.
- **Un dato leído con excepción abierta o sin validar** entra al cálculo igual (el borrador es para revisar), pero su línea y todas las que dependen de ella llevan la marca "usa datos sin validar" con la lista de cuáles.
- `Calculadora` (interfaz): `formulario(): string`, `calcular(EntradasDeclaracion, Declaracion): void`.

### 6.4b a 6.4h Calculadoras por formulario (una subtarea cada una, en paralelo)

| # | Calculadora | Qué calcula | Fuente que la subtarea lee completa |
| --- | --- | --- | --- |
| 6.4b | `ScheduleC`, `ScheduleSe` | C: 1 (1099-NEC c. 1 / 2026 c. 1a, 1099-K 1a, 1099-MISC 5 y 6 que el preparador mandó a C + ingresos sin formulario de `SumasRegistros`), 2, 3, 4, 5, 6, 7, 8–27b (sumas de `SumasRegistros`), 28, 29, 30 (simplificado), 31. SE: 2, 3, 4a, 4c, 6, 8a–8d, 9, 10, 11, 12, 13, con salarios W-2 (casillas 3 y 7) | Schedule C y SE de 2025 e instrucciones |
| 6.4c | `Schedule1`, `Ingresos1040` | Schedule 1: 3, 8z, 10, 11 (útiles de 6.3a con tope), 15, 26. 1040: 1a (suma W-2 c. 1), 1z, 8, 9, 10, 11a, 11b | Schedule 1 de 2025; 1040 p. 1 |
| 6.4d | `Deducciones` | 12d (65+ y ciego), 12e (estándar; itemizada **no soportada** en F6), 13a (**Form 8995** simple: QBI bajo el umbral, decisión h; por encima, `no_soportada` → 8995-A en F7), Schedule 1-A completo (constancia y préstamo de 6.3a) → 13b, 14, 15 | Form 8995 de 2025 e instrucciones; instrucciones de las líneas 12 y 13; Schedule 1-A e instrucciones |
| 6.4e | `ImpuestoYSchedule2` | 16 (Tax Table por método si < USD 100.000; Tax Computation Worksheet si no), 17, 18; Schedule 2: 3, 4, 21 (8959 solo si supera el umbral; 8960 **no soportado**) | Pub. 1040 (Tax Table p. 2–13, TCW p. 14) |
| 6.4f | `Schedule8812`, `Form2441`, `Schedule3` | 8812 Parte I (crédito limitado al impuesto, línea 14 → 19) y Parte II-A (ACTC → 28); 2441 Partes I–III (recibos de 6.3a, tabla escalonada, tope de ingreso del trabajo de cada cónyuge, W-2 c. 10 → 1e) → Schedule 3 l. 2 → 8 → 20; 10 (extensión de 6.3a); 21, 22, 24 | 8812 de 2025 e instrucciones; 2441 de 2025 e instrucciones |
| 6.4g | `Eitc` | Hoja A o B de las instrucciones de la línea 27 (B con Schedule C), con la EIC Table como método; requisitos: SSN válido del contribuyente (y del cónyuge en MFJ), tope de ingreso de inversión, MFS solo con la excepción de separados (por verificar), hijos calificados con SSN válido; → 27a. Llena el Schedule EIC (líneas 1–6) | Instrucciones del 1040 de 2025, línea 27; Pub. 596; Pub. 1040 EIC Table |
| 6.4h | `Form8962`, `PagosYResultado` | 8962: 1, 2a–5, 7, 8, 11–23 (con los meses del 1095-A), 24–29 → Schedule 3 l. 9 / Schedule 2 l. 1a. 1040: 25a, 25b, 25d, 26 (comprobantes de 6.3a), 27a, 28, 31, 32, 33, 34, 35a, 36, 37 (35b–35d de G3 son sensibles: solo se marca que existen) | 8962 de 2025 e instrucciones |

- Cada calculadora escribe **solo sus líneas** y lee las de otros formularios por `Declaracion::linea()`. Si una línea que necesita no está, marca `falta_dato` con el motivo ("Falta la línea 11b del 1040"; "Faltan los registros del negocio: el cliente no los ha subido") en vez de suponer 0.
- **Lo que no es del piloto no se inventa:** una entrada no soportada (Schedule A, 1099-R, SSA-1099, 1099-B, intereses que exigen Schedule B, depreciación…) marca `no_soportada` en la línea afectada, con el documento que la causó, y la declaración queda **parcial** (decisión i).
- **MFS:** tramos y deducción de MFS; EITC, 2441 y 8962 con MFS siguen las restricciones del IRS (por verificar cada una en sus instrucciones de 2025); si alguna no está verificada al programar, la línea sale `falta_dato` con "Con MFS, este crédito lo revisa el preparador" (decisión d).
- 6.4c, 6.4e y 6.4g no dependen de la lectura de montos y pueden ir antes. 6.4b, 6.4d, 6.4f y 6.4h usan los datos de campos fijos (6.3a) y la **interfaz** `SumasRegistros` (6.3b), con una implementación simulada en sus pruebas; **no esperan al tramo 2** (extractos con IA), que se conecta sin cambiar el contrato.

### 6.4i Orquestador y foto

- `MotorCalculo::para(Expediente): Declaracion`: arma las entradas y corre las calculadoras en el orden del formulario (C → SE → 1 → 1040 9–11 → 1-A → 12–15 → 16–18 → 8812 → 2441 → 3 → 22–24 → 2 → EIC → 8962 → 25–37). Puro: no escribe nada.
- **Foto del borrador** (decisión f): tabla `borradores_1040` (`expediente_id`, `tax_year`, `version_reglas`, `huella_entradas` (hash de las entradas, sin valores), `lineas` con cast `encrypted:array`, `motivo` (`aprobado`, `pdf`, `preparado_en_taxwise`), `creado_por`, `created_at`). Se guarda al aprobar la revisión (`RevisionExpediente`) y al generar el PDF; inmutable. Sirve para el registro del 8867 ("hojas de cálculo de cada crédito", 7.1).
- `version_reglas` = año + versión del catálogo del año (por ejemplo `2025.6`).

**Pruebas (Pest, `tests/Feature/Calculo/` y `tests/Unit/Calculo/`):**
- Cada calculadora contra **sus** casos de `tests/Fixtures/irs/casos/` y las tablas completas (se activan las pruebas pendientes de 6.2).
- `Dinero`: redondeo; no hay `float` en `app/Services/Calculo/` (prueba de arquitectura con `arch()` de Pest).
- `ArmadorEntradas`: ignora "no aplica"; usa la decisión del preparador y no la respuesta del cliente para el estado civil; **prefiere el documento al respaldo manual** (si hay documento para una clave, el manual se ignora y se avisa); la traza distingue `leido`, `corregido` y `sin_documento`; nunca trae un SSN, una cuenta, un TIN ni una fecha de nacimiento (se busca el valor en el JSON).
- Sin estado civil decidido: la declaración no se calcula y dice por qué.
- Un 2026 con parámetros pendientes: líneas `falta_dato` con el nombre del parámetro, sin excepción.
- Cambiar un renglón de categoría cambia la línea del Schedule C, la 31, la 3 del Schedule 1, 11a… y la huella.

**Archivos:** `app/Services/Calculo/**` (núcleo en 6.4a; cada calculadora en su archivo; el orquestador en 6.4i), `app/Models/Expediente/Borrador1040.php`, una migración. Pruebas por calculadora en su propio archivo.

**Skills:** `arch-service-classes`, `arch-dto-pattern`, `arch-value-objects`, `eloquent-eager-loading`, `eloquent-casts` (`encrypted:array`), `arch-feature-folders` (`app/Services/Calculo/Formularios/`).

## 6.5 Respaldo manual sin documento (último recurso)

**Para qué existe:** el caso en que **el cliente no tiene ningún documento** de un monto que sí existe (el independiente que cobró en efectivo y lo cuenta de memoria, el pago estimado del que no guardó comprobante). No es la forma normal de cargar datos: lo normal es leer el documento (6.3).

**Enfoque:**
- Tabla `expediente_datos_sin_documento`: `expediente_id`, `clave` (la misma clave de casilla o categoría que produce la lectura, por ejemplo `registros-ingresos-gastos:ingreso-sin-formulario`, `comprobantes-pagos:monto`), `persona_id` nullable, `valor` cifrado (`encrypted`), `motivo` (**obligatorio**: por qué no hay documento), `fuente` (`cliente_dijo` con la fecha y el canal en que lo dijo, o `otro`), `anotado_por`, timestamps; historial en `expediente_dato_sin_documento_cambios`.
- **Solo se permite para una clave sin documento:** si el documento de esa clave tiene un archivo leído, la acción no aparece ("Este dato ya sale del documento; corrígelo en el visor"). Si el documento se sube después, el dato leído **reemplaza** al manual en el cálculo y el manual queda en el historial con la marca "reemplazado por el documento".
- `OrigenDato` no se toca (es del dato leído); el origen `sin_documento` vive en la traza (6.4a) y en la interfaz.
- Cada dato anotado abre **R-15 (informativa)** "Dato anotado sin documento: … (motivo)", que aparece en la cola, en la Revisión y en el resumen de debida diligencia de F5 (es justo el registro de "cómo, cuándo y de quién obtuvo la información" que pide el 8867).
- **Interfaz:** **no** es una sección de formularios. Es una acción secundaria (`variant="ghost"`) **"No hay documento: anotar como último recurso"** en la fila del documento faltante (pestaña Documentos y en la línea `falta_dato` del borrador). Abre un diálogo con un aviso arriba ("Úsalo solo si el cliente no tiene ningún papel. Queda marcado como anotado sin documento en el borrador, en la debida diligencia y en el PDF."), el monto (`inputMode="decimal"`), el motivo (obligatorio), quién lo informó y cuándo; guardar con spinner y sin doble envío; errores con `aria-describedby`.
- Form request `AnotarSinDocumentoRequest` (`controller-form-requests`, `validation-after-hooks`: rechaza si la clave ya tiene documento leído); servicio único `DatosSinDocumento::anotar()` que corre el motor de reglas y reabre la revisión aprobada (regla de F5).

**Pruebas:** anotar exige motivo; no se puede anotar si hay documento leído; subir el documento después reemplaza al manual en el cálculo; R-15 aparece y sale en la debida diligencia; historial; cliente 403; el valor nunca en logs ni bitácora.

**Archivos:** migración, 2 modelos, `DatosSinDocumento`, request, `DatosSinDocumentoController`, `R15SinDocumento`, `routes/expediente-borrador.php` (lo crea esta subtarea; dueño único), diálogo en `resources/js/components/borrador/anotar-sin-documento.tsx`.

**Skills:** `sec-mass-assignment`, `eloquent-casts`, `controller-form-requests`, `validation-after-hooks`, `controller-dependency-injection`.

## 6.6 Reglas R-10, R-11 bloqueante y R-12 (más R-14 y R-15 de 6.3 y 6.5)

**Enfoque (mismo patrón de F4: clase `Regla`, lista `MotorReglas::REGLAS`, `implementada` en `reglas.php`, texto en `ExplicadorExcepcion`):**
- **R-10 (revisión)** `R10ScheduleCContra1099`: si la línea 1 del Schedule C que calcula el motor es **menor** que la suma de los 1099-NEC (c. 1; 2026 c. 1a) y 1099-K (c. 1a) del cliente que no están en "no aplica". Pasa cuando el preparador manda un 1099 a otra línea (8z, sueldos con 8919) o cuando el 1099-K trae pagos personales: la regla pide la nota. Con la conciliación de 6.3d (tramo 2), también detecta el caso inverso que importa: depósitos marcados `cubiertos` por un 1099 que **no** está en la línea 1. El detalle lleva ids, nunca montos.
- **R-11 (bloqueante desde F6, solo con evidencia en contra)** `R11HijoSinSsn` se amplía. Persona **clasificada por el preparador** como `hijo_calificado` y menor de 17 al 31 de diciembre:
  - **Bloquea** si hay **evidencia en contra** del SSN válido: C9 ≠ `ssn_valido`, **o** la tarjeta leída tiene marcada la casilla `no-valido-para-trabajar`. Frena "Listo para preparar" hasta que el preparador corrija (C9 o la tarjeta) o la **acepte con nota**; entonces el 8812 la cuenta como "otro dependiente" (USD 500) y no como hijo para el CTC.
  - **Solo avisa (revisión)** si la tarjeta **falta o no está validada** y no hay evidencia en contra: no bloquea; el 8812 la cuenta como hijo y la línea 19 lleva la marca "usa datos sin validar".
  - `MotorReglas` toma la severidad del catálogo por código, así que son **dos reglas**: `R-11` (bloqueante, `R11HijoSinSsn`) y `R-11a` "Tarjeta del hijo faltante o sin validar" (revisión, `R11aTarjetaSinValidar`), con la lógica compartida en un solo lugar. Antes de la clasificación de F5 sigue el aviso de F4.
- **R-12 (revisión)** `R12ProveedorSinTin`: hay un recibo de cuidado leído (6.3a) con monto y la casilla `proveedor-tin` vacía, ilegible o con formato que no es SSN ni EIN. El Form 2441 pide entonces una explicación (por verificar el texto exacto en las instrucciones del 2441 de 2025); el preparador acepta con esa nota.
- **R-14** ya está desde 6.3b (tramo 1) y se completa en 6.3e (tramo 2). **R-15** se implementa en 6.5; aquí se registra en `MotorReglas` y en `reglas.php`.
- `MotorReglas` ya corre después de cada lectura y corrección; ahora también después de corregir un renglón, de anotar un dato sin documento y de clasificar una persona.

**Pruebas (`tests/Feature/Lectura/ReglasCalculoTest.php`):** cada regla dispara y se cierra sola al corregir; **R-11 con C9 = ITIN bloquea**, y **con la casilla `no-valido-para-trabajar` marcada bloquea**; frena "Listo para preparar" y deja de frenar al aceptar con nota; con R-11 aceptada, el 8812 cuenta USD 500 y no USD 2.200; **sin tarjeta o con la tarjeta sin validar (y C9 = `ssn_valido`) solo abre R-11a de revisión, no frena "Listo para preparar"** y el 8812 cuenta USD 2.200 con la marca "usa datos sin validar"; R-10 no dispara si el 1099 está en "no aplica"; R-12 con TIN ilegible; ningún monto ni SSN en `excepciones.detalle`.

**Archivos:** `R10…`, `R11…` (ampliada), `R11a…`, `R12…`, `MotorReglas` y `2025/reglas.php` (dueño en su ola; 6.3b los tocó en una ola anterior), `ExplicadorExcepcion` (textos de R-10/11/11a/12; los de R-14 los escribió 6.3b y los completa 6.3e en otra ola).

## 6.7 Pestaña "Borrador del 1040" y "cómo se calculó"

**Enfoque (servidor, 6.7a):**
- Ruta `clientes.borrador.show` en `routes/expediente-borrador.php`; `BorradorController@show` (`authorize('view')`), página `ficha/borrador` con `Inertia::defer` para la declaración (`async-defer-await`, `server-serialization`: solo lo que la pantalla usa).
- Props: por formulario activo, sus líneas en el orden del formulario: `linea`, `descripcion`, `valor` (o `null` + `mascara` si es sensible), `estado` (`calculada`, `falta_dato`, `no_soportada`, `no_aplica`), `origen_resumen` (`leido`, `corregido`, `sin_documento`, `mixto`), `usa_sin_validar` (lista de datos sin validar o con excepción que la alimentan), `validacion` (`validada` con quién y cuándo, o `sin_validar` con su motivo), y la `traza` en palabras (textos armados en el servidor con `__()`, una sola fuente para pantalla y PDF).
- Encabezado: estado general (`completa`, `parcial` con lo no soportado, `incompleta` con lo que falta), conteo de líneas con datos **anotados sin documento** y con datos **sin validar**, `version_reglas`, fecha del cálculo, y **"Borrador — no se presenta al IRS"**.
- La pestaña aparece en la ficha para preparador y administrador; nunca para el cliente.

**"Cómo se calculó" (panel por línea):**

| Paso | Ejemplo (Javier, Schedule C línea 18, gastos de oficina) |
| --- | --- |
| Entradas y su origen | **Leído:** 6 renglones de 2 extractos (enero y marzo) y 1 recibo, categoría "gastos de oficina" ↗ (cada renglón abre el visor en su página); **Corregido:** 1 renglón pasado de "materiales" a "oficina" por Ana, 12 nov. |
| Confianza | Suma: 0,90 · "Leído con claridad (desde imagen)"; validada por Ana, 12 nov. [¿Cómo se calcula?] |
| Parámetro y su fuente | — (no usa parámetro) |
| Operación | Suma de 7 renglones = USD 412 |
| Resultado y destino | Schedule C línea 18 → línea 28 → 31 → Schedule 1 línea 3 → 1040 línea 8 |
| Estado | Validada contra el IRS (casos `javier-mfj-schc`) / **Sin validar** |

- Una entrada **anotada sin documento** sale con la etiqueta **"Anotado sin documento"** (ícono y texto, nunca solo color), el motivo y quién; la línea y las que dependen de ella heredan la etiqueta.
- Cada entrada enlaza a su origen como en la hoja de F5 (visor en el dato `?dato=` o en el renglón `?renglon=`, Entrevista, Revisión). Lo sensible sale enmascarado con "Revelar" (contraseña y bitácora).
- Una línea `falta_dato` dice **qué falta y dónde se resuelve**: "El cliente no ha subido los registros del negocio" (enlace a Documentos) o "Hay 3 renglones sin categoría (R-14)" (enlace a la excepción); solo si el documento no existe ofrece, como acción secundaria, "Anotar sin documento". `no_soportada` dice **qué la causó**.

**Interfaz (6.7b):**

```
 Borrador del 1040 · 2025 · calculado el 12 nov. 10:30          [Descargar PDF borrador]
 ⚠ Borrador — no se presenta al IRS · Parcial: 1 línea no calculada (1099-R)
 ⓘ 2 líneas usan datos sin validar · 1 dato anotado sin documento
 [Form 1040] [Schedule 1] [Schedule C] [Schedule SE] [8812] [2441] [EIC]   ← en 375 px, un Select
 ── Schedule C · Gastos ───────────────────────────────────────────────
 9    Auto y camioneta (1.240 millas × tarifa)       868   📄 Leído  ✓ Validada     [Cómo se calculó]
 18   Gastos de oficina (7 renglones)                412   📄 Leído  ○ Sin validar  [Cómo se calculó]
 22   Materiales                                     —     ! Falta dato: 3 renglones sin categoría (R-14) → Revisar
 ── Form 1040 · Pagos ─────────────────────────────────────────────────
 26   Pagos estimados                              2,000   ✎ Anotado sin documento (Ana, 12 nov.) [Cómo se calculó]
```

- Componentes: `BorradorFormulario`, `LineaBorrador`, `EtiquetaOrigen` (leído / corregido / anotado sin documento, con ícono de `lucide-react` y texto), `PanelComoSeCalculo` (`Sheet` de `components/ui`, fuera de `<Deferred>`). Ningún componente definido dentro de otro (`rerender-no-inline-components`); el formulario activo y la línea abierta se derivan de la URL y de props en el render, sin efectos (`rerender-derived-state-no-effect`); búsqueda de líneas con `Map` (`js-index-maps`); importar cada componente de `components/ui` por su archivo (`bundle-barrel-imports`).
- Skeleton por formulario mientras llega la declaración; estados vacío ("Este cliente no tiene formularios activos todavía"), error con "Reintentar", éxito (toast al anotar un dato).
- Estados con **texto e ícono**; filas de 44 px; en el celular cada línea es una tarjeta (línea, descripción, valor, origen, estado) sin scroll horizontal; foco que vuelve a la fila al cerrar el panel; `aria-label` en "Cómo se calculó" con el número de línea.
- Textos en `resources/js/i18n/locales/expediente/borrador.{es,en}.json` (espacio de nombres `borrador`) y en el servidor `lang/expediente/borrador/en.json`.

**Pruebas:** María: 1a, 11a, 12e, 15, 16, 19, 20, 27a con sus valores esperados (del caso de 6.9); props sin SSN, TIN ni cuenta en claro; cliente 403, otro preparador 403; 2026 con parámetros pendientes muestra `falta_dato` y no 500; una línea con un dato anotado sin documento lleva la etiqueta y su motivo; una línea validada y otra sin validar dicen su motivo.

**Archivos:** 6.7a `BorradorController`, `app/Services/Calculo/Presentacion/BorradorVista.php`, `lang/expediente/borrador/en.json`; 6.7b `resources/js/pages/ficha/borrador.tsx`, `resources/js/components/borrador/**` (salvo el diálogo de 6.5), `resources/js/types/borrador.ts`, `locales/expediente/borrador.*`. El registro de la pestaña en el layout de la ficha lo hace **6.7b** (un dueño).

**Skills:** `async-defer-await`, `server-serialization`, `rerender-no-inline-components`, `rerender-derived-state-no-effect`, `js-index-maps`, `bundle-barrel-imports`; `controller-dependency-injection`.

## 6.8 PDF oficial diligenciado (borrador)

**Enfoque:**
- **Plantillas por año en el repositorio:** `resources/irs/<anio>/<form>.pdf` + `resources/irs/<anio>/manifiesto.json` (URL de `irs-prior/` o `irs-dft/`, fecha, SHA-256). Nunca se baja en tiempo de ejecución. Una prueba verifica el SHA-256.
- **Mapa línea → campo:** todo en `database/data/catalogo-fiscal/<anio>/pdf/<form>.php` (las líneas y los campos que no son líneas: nombre, dirección, SSN, casillas de estado civil, filas de dependientes, cuenta bancaria). El importador llena `campo_pdf` de `catalogo_lineas` desde ahí, para no pisar `formularios.php` (dueño 6.0).
- **Calibración** (comando `borrador:calibrar-pdf <anio> <form>`, solo local): llena cada campo con su propio nombre corto y produce un PDF para mirar a ojo qué campo es qué línea. Con eso se arma el mapa (una subtarea por formulario, en paralelo) y una prueba verifica que **todo campo del mapa existe** en la plantilla.
- **Herramienta (decisión h2): `pdftk` (pdftk-java)** en la imagen (`apt-get install pdftk-java`, que trae un JRE sin interfaz; ~ +150–200 MB, por verificar). Flujo: XFDF generado en memoria → `pdftk plantilla.pdf fill_form - output - drop_xfa need_appearances` → `multistamp marca-agua.pdf` (PDF estático "BORRADOR — NO PRESENTAR / DRAFT — DO NOT FILE" commiteado) → `flatten` → `cat` de los formularios en el orden de adjuntos del IRS → la respuesta (`streamDownload`, `Cache-Control: private, no-store`). **Nada se guarda en disco** salvo temporales que se borran en el mismo request. `Process` con tiempo límite, envuelto en `App\Services\Calculo\Pdf\LlenadorPdf` (simulable con `Process::fake`).
- **Qué se llena (decisión a):** los formularios activos del cliente que tienen calculadora en F6: 1040, Schedules 1, 1-A, 2, 3, C, SE, 8812 y EIC, y Forms 2441, 8962 y 8995. Una línea `falta_dato` o `no_soportada` se deja en blanco; la **portada** (hoja extra generada) lista qué líneas quedaron en blanco, cuáles están sin validar, cuáles **usan datos anotados sin documento** y por qué.
- **Lo sensible (decisión b):** SSN, ITIN, TIN del proveedor y cuentas bancarias **enmascarados** (últimos 4); generado **a pedido** con **contraseña reciente** (`RequirePassword`, `throttle`); bitácora `Exportado` (nunca el contenido); se guarda la **foto** del borrador (6.4i), no el PDF.
- **Lo que el PDF no es (decisión e):** marca de agua en cada página, sin firma, sin PIN, sin fecha de presentación; la portada dice "Borrador para revisión del preparador. No es una declaración: no se presenta al IRS ni se entrega al cliente".

**Pruebas:** con `pdftk` disponible (se salta con motivo si no está; corre en Docker): llenar María y leer de vuelta con `pdftk dump_data_fields` que 1a, 11a, 19 y la casilla HOH tienen el valor; la marca de agua en cada página (`pdftotext`); ningún SSN completo en el texto del PDF; la portada lista el dato anotado sin documento. Siempre: el mapa apunta a campos que existen; el SHA-256 de cada plantilla; `Process::fake` para la ruta (contraseña exigida, bitácora `Exportado`, 403 a cliente y a otro preparador, `no-store`).

**Archivos:** `resources/irs/**`, `database/data/catalogo-fiscal/2025/pdf/*.php` (uno por formulario: **una subtarea por archivo**), `LlenadorPdf`, `BorradorPdf` (arma el XFDF y la portada desde la `Declaracion`), comando de calibración, método `pdf` y su ruta (6.8c), `Dockerfile` (dueño único).

**Skills:** `arch-service-classes`, `controller-middleware` (`RequirePassword`, `throttle`), `controller-dependency-injection`.

## 6.9 Casos de punta a punta y contraste con el IRS

- **Casos mínimos 2025** (cada uno con su archivo de caso, citas y, si aplica, contraste):
  1. `maria-hoh`: HOH, hijo de 8 con SSN válido, W-2, **recibo de guardería leído** (2441), EITC, 1095-A (8962).
  2. `javier-mfj-schc`: MFJ, W-2, 1099-NEC/K/MISC, **registros del negocio leídos** (extracto sintético + plantilla) con conciliación contra el 1099-K, Schedule SE con salarios W-2, **los dos recibos de útiles reales de Javier** (Schedule 1 l. 11).
  3. `soltero-schc-perdida`: Schedule C con pérdida (línea 4c < 400).
  4. `schc-extracto-no-cuadra`: R-14 bloquea la confianza de la suma; el preparador corrige el renglón omitido y la línea se rehace.
  5. `schc-sin-documento`: ingresos en efectivo anotados sin documento → R-15, etiqueta en la línea y en la portada.
  6. `hijo-sin-ssn`: C9 = ITIN → R-11 bloquea; aceptada → ODC. `hijo-tarjeta-sin-validar`: C9 = SSN válido sin tarjeta validada → solo R-11a (aviso), el 8812 cuenta el CTC con la marca "usa datos sin validar".
  7. `eitc-sin-hijos`, `eitc-tres-hijos-limite`: bordes de la tabla.
  8. `8962-devolucion` y `8962-credito-neto`.
  9. `1a-propinas-horas-extra`: Schedule 1-A con la constancia leída y la reducción.
  10. `mfs-basico`: MFS con tramos propios y créditos restringidos.
  11. `pagos-estimados`: cuatro comprobantes 1040-ES y uno de extensión.
  12. Un caso 2026 con los parámetros cargados (se revalida con los formularios finales).
- **Usuarios de prueba reales:** una conversación de Steven (o un usuario `es_prueba`) recorrida **en local**; el resultado se compara a mano con las hojas del IRS y se convierte en caso si aporta algo nuevo.
- **Contraste externo** registrado en los casos de EITC (EITC Assistant).
- `MariaExpedienteTest` ampliado y un `JavierBorradorTest` de punta a punta: entrevista → subida → lectura (simulada) → excepciones → revisión → borrador → PDF.

## 6.10 Retiro del motor viejo y cierre

- Borrar `DeterminacionFiscalService`, `app/Services/Reglas/*` (las 12), `FilingStatus`, `TipoDeterminacion` si queda sin uso, el controlador y la ruta `clientes.determinaciones.store`, y sus pruebas. **La tabla `determinaciones_fiscales` se conserva** (retención de 3 años) y Historial sigue mostrando lo guardado, sin el botón "Calcular".
- Cierre de fase: Wayfinder, `pint`, `types:check`, suite completa, `npm run build`, revisión en el navegador (375 px y escritorio, es y en, vacío/error/éxito/procesando; visor con renglones; PDF abierto en Acrobat Reader, Vista Previa de macOS y Chrome), "Cómo quedó", `tareas-desarrollo.md`, Wipli.
- Actualizar `verificacion-irs-destinos.md` con las líneas nuevas y `guia-confianza-de-lectura.md` con los motivos nuevos ("Leído de la hoja de cálculo (sin IA)", "Está en el texto del PDF, pero se repite en la página" con 0,95, "Categoría propuesta por la IA", "Sin categoría") y la confianza de una suma.

---

## Olas para Sonnet 5.5 (qué se reparte y con qué archivos)

| Ola | Subtareas en paralelo | Archivos de cada una (sin pisarse) |
| --- | --- | --- |
| 1 | 6.0 · 6.1a (2025) · 6.2 · 6.4a (núcleo) · 6.3a (campos fijos; **tramo 1**) | 6.0: `formularios.php` (incluye el Form 8995), migración de líneas, verificador, página Formularios. 6.1a: seeder, `parametros.php`, metadatos, migración espejo. 6.2: `tests/Fixtures/irs/**`, `scripts/irs/**` (no lee `app/Services/Calculo`). 6.4a: `app/Services/Calculo/Nucleo/**`. 6.3a: `documentos.php` (casillas, `lectura = renglones`, formatos `xlsx`/`csv`), fixtures `tests/Fixtures/lectura/montos/**` |
| 2 | 6.3b (hojas sin IA, renglones, `SumasRegistros`, R-14 básica; **tramo 1**) · 6.4c · 6.4e · 6.4g · 6.1b (2026) · escritor de casos de 6.9 (sin ver el código) | 6.3b: migraciones y modelos de renglones, `LectorHojas`, `plantilla-registros.php`, `SumasRegistros` (interfaz e implementación), `MotivoConfianza` (motivo "hoja de cálculo"), `DatosDocumento`, `R14RegistrosNegocio`, `MotorReglas` y `reglas.php` (R-14 y R-15; dueño en esta ola), `composer.json`, textos del portal. Calculadoras: su archivo en `Calculo/Formularios/` y su prueba. 6.1b: `2026/ajustes.php` (secciones `parametros` y `lineas`). Casos: `tests/Fixtures/irs/casos/**` |
| 3 | 6.4b · 6.4d (con el 8995) · 6.4f · 6.4h · 6.5 (respaldo manual, servidor) · **6.3c (extractos con IA; tramo 2)** | Calculadoras: su archivo y su prueba (datos de 6.3a e **interfaz** `SumasRegistros` simulada). 6.5: modelos, servicio, request, controlador, `routes/expediente-borrador.php` (lo crea), `R15SinDocumento` (sin registrarla en el motor). 6.3c: `EsquemaLectura`, `LectorOpenAi`, `PaginasDocumento`, `MotivoConfianza` (0,95, categoría IA, sin categoría), parámetros de páginas y tope por cliente (en su propio archivo de ajustes pasado a 6.1b por escrito si es 2026), comando `lectura:medir-costo` |
| 4 | 6.4i (orquestador + foto) · 6.7a · **6.3d (cuadre y conciliación; tramo 2)** | 6.4i: `MotorCalculo`, `ArmadorEntradas`, `Borrador1040`, migración. 6.7a: `BorradorController@show`, `BorradorVista`. 6.3d: `SumasRegistros` (solo la implementación; la interfaz no cambia) y su prueba |
| 5 | 6.6 (R-10, R-11, R-11a, R-12 y registrar R-15) · 6.7b (pantalla, incluye el diálogo de 6.5) · 6.8a (llenador, calibración, Dockerfile) · 6.8b-1…n (un mapa `pdf/<form>.php` por subtarea, el 8995 incluido) · **6.3e (R-14 completa y visor de renglones; tramo 2)** | 6.6: reglas, `MotorReglas`, `reglas.php`, textos de R-10/11/11a/12 en `ExplicadorExcepcion`. 6.7b: `pages/ficha/borrador.tsx`, `components/borrador/**`, `types/borrador.ts`, locales `borrador`. 6.8a: `Calculo/Pdf/**`, comando, `resources/irs/**`, `Dockerfile`. 6.8b: solo su `pdf/<form>.php`. 6.3e: `R14RegistrosNegocio`, panel del visor, `CorregirRenglonRequest`, textos de R-14 en `lang/lectura/montos/en.json` (**no** toca `ExplicadorExcepcion`: le pasa a 6.6 sus frases por escrito, o va en la ola 6 si 6.6 ya cerró) |
| 6 | 6.8c (ruta del PDF y portada) · 6.9 (pruebas de punta a punta) | 6.8c: método `pdf` y su ruta. 6.9: `tests/Feature/Calculo/*EndToEnd*`, `JavierBorradorTest` |
| 7 | Cierre (agente principal) + 6.10 | — |

**El tramo 2 no frena a nadie:** 6.3c, 6.3d y 6.3e van en las olas 3 a 5, en paralelo con el motor, la pantalla y el PDF. Si se atrasa, el motor y la pantalla se cierran con lo del tramo 1 (campos fijos y hojas), y los extractos se conectan después a `SumasRegistros` sin tocar las calculadoras.

**Dueños únicos (por ola, nunca dos en la misma):** `formularios.php` (6.0), `documentos.php` (6.3a), `reglas.php` y `MotorReglas` (6.3b en la ola 2, 6.6 en la ola 5), `ExplicadorExcepcion` (6.3b en la ola 2, 6.6 en la ola 5), `MotivoConfianza` (6.3b en la ola 2, 6.3c en la ola 3), `SumasRegistros` (6.3b en la ola 2, 6.3d en la ola 4), `2026/ajustes.php` (6.1b; los demás le pasan sus cambios de 2026 por escrito), `routes/expediente-borrador.php` (6.5 lo crea; 6.7a y 6.8c agregan rutas en olas posteriores), `Dockerfile` (6.8a), `composer.json` (6.3b), layout de la ficha (6.7b).

Reglas comunes (las mismas de `fase-5-subtareas.md`): solo los archivos permitidos; `pint` solo sobre los propios; sus pruebas y no la suite completa; nunca commit, push, navegador ni producción; OpenAI siempre simulado (salvo `lectura:medir-costo`, que corre solo el agente principal, en local).

## Permisos, §7216 y bitácora

- Borrador, renglones, respaldo manual y PDF: preparador y administrador con `ClientePolicy` (`view` para ver, `update` para escribir); el cliente nunca (403). Un ítem, renglón o persona de otro cliente da 404.
- Ningún valor (monto, descripción de un renglón, SSN, TIN, cuenta, fecha de nacimiento) va en logs, `expediente_eventos`, `excepciones.detalle`, bitácora o en la traza en claro; la foto va cifrada; la descripción y el monto de cada renglón van cifrados.
- Lectura con OpenAI: la misma política de F4 (decisión ya tomada, ver arriba). Las hojas `xlsx`/`csv` no pasan por OpenAI.
- Bitácora: `Revelado` (con contraseña), `Exportado` (PDF), `Actualizado` (renglón corregido o dato anotado sin documento, sin valor).

## Riesgos

| Riesgo | Mitigación |
| --- | --- |
| **Leer gastos e ingresos desde extractos es difícil**: depósitos ambiguos (transferencias propias, préstamos, reembolsos, pagos personales), cuentas mezcladas personal/negocio, extractos escaneados o fotos, categorías mal propuestas | Va en el **tramo 2**, que no frena el motor; lo que no es claro va a R-14 y no se suma; las categorías de la IA nunca pasan sin validación (suma a 0,60); el preparador valida **totales por categoría**, no renglón por renglón; primera versión **sin prometer exactitud total**; la plantilla de F3.7 (en Excel, sin IA) se sugiere como la vía más sencilla |
| **Renglones omitidos o inventados** por el modelo | Cuadre contra los totales impresos del extracto (R-14 si no cuadra); 0,99 solo si el ubicador encuentra el monto en el texto de esa página **y es único**; 0,95 si se repite en la página; instrucciones "no inventes renglones" |
| **Doble conteo** del ingreso que ya viene en un 1099-K/NEC (o lo contrario: omitirlo) | Conciliación por pagador (6.3d); R-14 cuando es dudosa; R-10 en los dos sentidos |
| Hojas propias con formatos raros (encabezados distintos, fórmulas sin valor, varias hojas) | Lectura por encabezados con sinónimos en el catálogo; lo no reconocido a R-14; la plantilla de F3.7 como camino fácil |
| Extractos largos (> 10 páginas) y **costo o demora de OpenAI** | Lectura por bloques; límite de 40 páginas por archivo; **medición del costo con los documentos sintéticos** y **tope de páginas con IA por cliente** antes de subir el límite; mensaje al cliente "súbelo por meses" |
| Se abusa del respaldo manual y el preparador vuelve a teclear | Solo aparece cuando el documento no existe, exige motivo, abre R-15, se ve en el borrador, en la portada y en la debida diligencia; el documento leído lo reemplaza |
| R-11 frena expedientes por una tarjeta que el cliente aún no sube | Solo bloquea con evidencia en contra (C9 o la casilla "no válido para trabajar"); la tarjeta faltante o sin validar es R-11a, de revisión |
| Leer mal una instrucción y repetir el error en código y prueba | Tablas extraídas del PDF y probadas completas; casos escritos por otra subtarea sin ver el código; ejemplos del IRS; contraste externo |
| Categorías del Schedule C mal entendidas (comidas, millaje, oficina en casa) | Cada una con su fuente y página, marcadas por verificar hasta leer las instrucciones de 2025; lo que no se modela (depreciación, 8829) sale `no_soportada` |
| El IRS cambia la URL o el PDF de un año | Plantillas y PDF de referencia fijados en el repositorio con SHA-256 |
| Parámetros de 2026 incompletos en enero (P1) | Líneas `falta_dato` con el nombre del parámetro; `verificar` lista los pendientes; nada lanza 500 |
| Un número del borrador se toma como definitivo | Marca de agua, portada, "sin validar" y "usa datos sin validar" por línea, el cliente no lo ve, foto con versión de reglas |
| Mapa de campos del PDF equivocado / XFA | Calibración visual + prueba de que todo campo existe + lectura de vuelta; `drop_xfa` + `need_appearances` + `flatten`; revisión en tres lectores |
| Imagen Docker más pesada (JRE de `pdftk-java`) | Aceptado (decisión h2); se mide el tamaño en el primer build |
| Centavos y redondeo | `Dinero` en centavos, redondeo en un solo lugar, prueba de arquitectura sin `float` |
| SQLite en pruebas vs PostgreSQL | Sin consultas nuevas raras; revisión en el navegador contra PostgreSQL local |
| Subtareas que se pisan | Archivos repartidos por ola; dueños por ola listados arriba |

## Orden de commits sugerido

1. 6.0 + 6.1a — `fix(catalogo): correct 1040 line destinations and cite IRS pages` · `feat(catalogo): add Form 8995 and complete 2025 tax parameters and tables` · `docs: …`
   - Después del deploy (con autorización): `catalogo-fiscal:cargar 2025 --forzar` solo para líneas y parámetros, `cargar 2026`, `verificar`.
2. 6.2 — `test(irs): add IRS tables and case format as the oracle`
3. 6.3 tramo 1 — `feat(catalogo): add boxes to amount documents` · `feat(lectura): read business record spreadsheets without AI` · `docs: …`
   - Después del deploy: recargar el catálogo (casillas, formatos, R-14/R-15) y `verificar`.
4. 6.4 + 6.5 + 6.1b — `feat(calculo): add the new 1040 calculation engine` · `feat(expediente): record an amount without a document as a last resort` · `feat(catalogo): load 2026 parameters`
5. 6.6 — `feat(reglas): add R-10, R-11 blocking on evidence, and R-12`
6. 6.3 tramo 2 (cuando esté; puede ir antes o después de 7 y 8) — `feat(lectura): read line items from statements and receipts with AI` · `feat(lectura): reconcile records with statement totals and 1099s` · `feat(visor): review record totals and line items`
   - Antes del deploy: el resultado de `lectura:medir-costo` y el tope por cliente, aprobados por Steven.
7. 6.7 — `feat(borrador): add the 1040 draft tab with how each line was calculated`
8. 6.8 — `feat(borrador): download the official IRS PDF as a watermarked draft` (incluye `Dockerfile`)
9. 6.9 + 6.10 — `test(calculo): add end-to-end IRS cases` · `refactor: retire the old determination engine` · `docs: record phase 6 outcome`

Siempre preguntándole a Steven antes de cada commit; con `/commit` (push a `main` = deploy).

## Migración de datos y despliegue

- Migraciones nuevas: columnas de fuente en `catalogo_lineas`; `documento_renglones` + cambios y columnas nuevas de `documento_lecturas`; `expediente_datos_sin_documento` + cambios; `borradores_1040`. Sin datos que mover (`campos_cliente` vacía; `determinaciones_fiscales` se conserva).
- **Documentos ya subidos** de registros, cuidado, pagos y útiles (de usuarios de prueba): un comando `lectura:releer --documento=<codigo> --dry-run` los manda a leer después de cargar el catálogo (con autorización de Steven; usa OpenAI salvo las hojas).
- Recargar catálogo 2025 y 2026 (líneas, Form 8995, parámetros, casillas, formatos, reglas, mapas PDF) y `verificar`.
- `composer.json` agrega `openspout/openspout`; la imagen agrega `pdftk-java` (decisión h2). Verificación por SSH, solo lectura: `pdftk --version` en el contenedor `global-tax-*`, migraciones corridas, rutas `clientes.borrador.*`, sin errores nuevos en el log.

## Listo cuando

- **Tramo 1:** los documentos de campos fijos se leen como un W-2 (recibo de cuidado, comprobantes de pagos, útiles del educador, constancia de horas extra y propinas, préstamo del vehículo), y la plantilla de F3.7 en Excel y las hojas `csv`/`xlsx` se leen **sin IA**; lo no reconocido va a R-14.
- **Tramo 2:** los extractos en PDF con texto, los recibos sueltos y las fotos se leen con IA, con la confianza explicada (0,99 único en la página; 0,95 repetido; 0,90 desde imagen); el costo está medido y el tope por cliente funciona; el extracto que no cuadra y el depósito que quizá ya está en un 1099 van a R-14. (Si el tramo 2 se atrasa, la fase puede cerrar con él pendiente y anotado, decisión de Steven en su momento.)
- Lo dudoso va a la cola y el preparador solo confirma o corrige; las sumas por categoría del Schedule C se pueden seguir hasta el renglón y su página o fila.
- El respaldo manual solo existe sin documento, con motivo, R-15 e historial, y se ve como "anotado sin documento" en la pantalla, la traza y el PDF.
- Cada línea que el borrador calcula para el piloto (1040, Schedules 1, 1-A, 2, 3, C, SE, 8812 y EIC, y Forms 2441, 8962 y 8995) coincide con el método del IRS en todos los casos de 2025, incluidas **todas las filas** de la Tax Table, de la EIC Table y de la tabla del 2441, y cada caso cita línea y página.
- Las líneas cubiertas están marcadas "validada contra el IRS"; las demás dicen "sin validar" y por qué.
- 2026 calcula con sus parámetros o dice qué parámetro falta, sin errores.
- R-10, R-12, R-14 y R-15 funcionan y se explican en palabras; **R-11 bloquea solo con evidencia en contra** (C9 distinto de SSN válido o la casilla "no válido para trabajar") y la tarjeta faltante o sin validar es un aviso (R-11a) que no frena el expediente.
- El PDF oficial (los mismos formularios, con el 8995) sale diligenciado, con marca de agua, enmascarado, a pedido y sin guardarse en el servidor.
- El motor viejo ya no está y la suite pasa completa.


## Reglas de las skills que se aplican

| Skill | Regla | Dónde |
| --- | --- | --- |
| laravel-best-practices | `arch-service-classes` | `SumasRegistros`, `MotorCalculo`, `ArmadorEntradas`, cada calculadora, `DatosSinDocumento`, `BorradorVista`, `LlenadorPdf`, verificador |
| | `arch-dto-pattern`, `arch-value-objects` | Renglón leído, `Linea`, `Traza`, `EntradasDeclaracion` (DTO `readonly`); `Dinero`, tablas de parámetros |
| | `arch-feature-folders` | `app/Services/Calculo/{Nucleo,Formularios,Presentacion,Pdf}` |
| | `eloquent-casts` | `encrypted` en renglones, totales de extractos y datos sin documento; `encrypted:array` en la foto; `TieneFuenteIrs` en líneas |
| | `eloquent-relationships`, `eloquent-eager-loading` | Lectura → renglones; `ArmadorEntradas` y `SumasRegistros` (una consulta por tipo) |
| | `controller-form-requests`, `validation-conditional-rules`, `validation-after-hooks` | `CorregirRenglonRequest`, `AnotarSinDocumentoRequest` (rechaza si hay documento), marcar línea validada |
| | `controller-middleware` | `RequirePassword` + `throttle` en revelar y en el PDF |
| | `controller-dependency-injection` | Servicios inyectados en `BorradorController`, `DatosSinDocumentoController` y el visor |
| | `sec-mass-assignment` | `#[Fillable]` explícito en los modelos nuevos |
| react-best-practices | Estado derivado en el render; `key` para reiniciar formularios; sin efectos para lo que se calcula | Formulario y línea abiertos desde la URL; diálogo de anotar sin documento; renglón en edición |
| | Composición sobre props booleanas | `EtiquetaOrigen`, `BorradorFormulario` y el panel de renglones separados, no un `modo` |
| vercel-react-best-practices | `async-defer-await`, `server-serialization` | Declaración diferida; solo lo que se muestra |
| | `rerender-no-inline-components`, `rerender-derived-state-no-effect` | Fila, panel, renglón y diálogo fuera del padre |
| | `js-index-maps` | Líneas por formulario; renglones por categoría |
| | `rendering-content-visibility` | Listas largas de renglones de un extracto en el visor |
| | `bundle-barrel-imports` | Imports directos de `components/ui` |

## Decisiones tomadas (9 nov.)

**Steven aprobó el plan y todas las decisiones el 9 nov. 2026.** Lo que sigue es la decisión final de cada una.

**L2. Lectura con OpenAI de los documentos de montos.** Se leen igual que los demás documentos de F4, extractos bancarios incluidos, sin revisión extra ni excepción. L2 sigue pendiente a nivel legal global y no bloquea esta lectura. Las hojas `xlsx`/`csv` no pasan por OpenAI.

**a. Alcance del PDF.** Se llenan **solo los formularios que el motor calcula en F6**: 1040, Schedules 1, 1-A, 2, 3, C, SE, 8812 y EIC, y Forms 2441, 8962 y 8995. Lo demás (A, B, D, E, 8863, 8995-A…) queda en F7 junto con su cálculo; si el cliente lo tiene, el borrador sale "parcial" y la portada lo dice.

**b. Lo sensible en el borrador.** **En pantalla**, igual que la hoja de F5 (enmascarado y "Revelar" con contraseña y bitácora). **El PDF**, siempre **enmascarado** (SSN, ITIN, TIN del proveedor y cuentas con los últimos 4), **generado a pedido con contraseña reciente**, bitácora `Exportado`, **sin guardarse en el servidor** (solo la foto cifrada de los números).

**c. Orden de las entregas.** **Validar y leer primero, el PDF al final**: 6.0–6.2 (catálogo, parámetros, oráculo) → 6.3 tramo 1 (lo claro) → 6.4–6.6 (motor, respaldo manual, reglas) → 6.7 (pantalla) → 6.8 (PDF). El 6.3 tramo 2 (extractos con IA) va en paralelo con 6.4 a 6.8.

**d. El `FilingStatus` viejo sin MFS.** El motor nuevo usa **solo la decisión del preparador** de F5 (`EstadoCivilIrs`, con MFS); `FilingStatus` y `FilingStatusCalculator` se borran con el motor viejo (6.10). Sin estado civil decidido, el borrador no se calcula y lo dice. MFS se calcula con sus tramos y su deducción; los créditos con reglas especiales para MFS se calculan solo donde la restricción esté verificada; si no, "con MFS, este crédito lo revisa el preparador".

**e. Lo que el borrador NO es.** Queda escrito en pantalla, en la portada del PDF y en la marca de agua de cada página ("Borrador — no presentar / Draft — do not file"): es un **borrador para que el preparador revise y compare**; **no se presenta al IRS**, no lleva firma ni PIN, **no se le muestra ni se le envía al cliente**, y no reemplaza el cálculo de TaxWise ni el criterio del preparador.

**f. Guardar el cálculo.** Se **calcula al vuelo** cada vez que se abre la pestaña y se guarda una **foto cifrada** solo al aprobar la revisión y al generar el PDF (registro del 8867).

**g. De dónde salen los montos que no vienen de un W-2 o un 1099.** Se **leen del documento que el cliente ya sube** (6.3), con el mismo lector, confianza y cola de excepciones de F4: el sistema extrae y suma, el preparador solo valida. El **respaldo manual** (6.5) queda **solo** para cuando el cliente no tiene ningún documento, marcado como último recurso, con motivo, historial y R-15; el documento leído lo reemplaza si llega después.

**h. QBI (Form 8995).** Se **agrega el Form 8995 simple (bajo el umbral) en F6**: al catálogo (6.0), al cálculo (6.4d) y al PDF (6.8), con sus instrucciones de 2025 como fuente. El 8995-A queda "no soportado" (F7).

**h2. Herramienta para llenar el PDF.** **`pdftk` (pdftk-java) en el servidor**: quita el XFA, genera apariencias, estampa la marca de agua, aplana y se prueba con Pest leyendo los campos de vuelta. Se acepta el JRE en la imagen.

**i. Lo que el piloto no calcula.** El borrador **calcula todo lo que soporta y marca "parcial"** cuando el cliente tiene algo fuera de F6, diciendo qué documento lo causa y qué líneas no son confiables.

**j. 2026.** Se cargan ya los parámetros de 2026 publicados por el IRS, **sin validar**, y se revalidan con las versiones finales cuando salgan. La respuesta de GTS a **P1** define si eso tiene que estar antes de enero.

**k. Las calculadoras que no son del piloto** (NIIT, crédito extranjero). **No se portan**: se borran con el motor viejo y vuelven en F7 con su caso del IRS si GTS las necesita.

**l. Hojas `xlsx` y `csv` en los registros del negocio.** El cliente **puede** subir la plantilla de F3.7 **en Excel** o un **`csv`** (opcional, nunca se le obliga). Se leen **sin IA** (6.3b): la plantilla, por sus etiquetas; una hoja propia, por sus **encabezados**, y **lo no reconocido va a R-14**. PDF, fotos y extractos se siguen leyendo con IA. El portal y el agente pueden **sugerir la plantilla como la vía más sencilla, sin presionar** (el agente, en la próxima versión de prompts que publique Steven).

**m. Qué valida el preparador en los registros.** **Cada total por categoría** (con sus renglones a la vista y la posibilidad de mover o corregir uno), no cada renglón.

**n. Límite de páginas para extractos.** **40 páginas por archivo**, en bloques. Antes de subirlo: medición del costo con los documentos sintéticos y tope de páginas con IA por cliente (6.3c). Más allá, el cliente lo sube por meses.

**Ajustes de Steven al aprobar (9 nov.):**
1. 6.3 en dos tramos: lo claro (campos fijos y hojas sin IA) antes del motor; los extractos con IA en paralelo con la pantalla, sin frenar el motor.
2. R-11 bloquea solo con evidencia en contra (C9 distinto de `ssn_valido` o la casilla `no-valido-para-trabajar` marcada); la tarjeta faltante o sin validar es un aviso (R-11a).
3. El Form 8995 entra en el PDF, en las calculadoras y en "Listo cuando".
4. Confianza de renglones: 0,95 cuando el monto se repite en la misma página; 0,99 solo si es único o es una casilla fija.
5. F6 revierte la parte de F3.7 que decía "sumar es trabajo del preparador": lo suma el sistema y el preparador valida.
6. Costo de OpenAI medido y tope por cliente antes de subir el límite de 40 páginas.

## Por verificar (fuente a consultar al escribir cada subtarea)

| Qué | Dónde |
| --- | --- |
| Números de línea del Schedule 8812 de **2025** (Partes I y II-A), umbral de ingreso del trabajo y tope del ACTC | `irs-prior/f1040s8--2025.pdf` e `i1040s8.pdf` (2025) |
| Parámetros del EITC 2025 (tasas, máximos, inicio y fin de la reducción, tope de ingreso de inversión) y reglas MFS | Rev. Proc. 2024-40; instrucciones del 1040 de 2025, línea 27; Pub. 596 |
| Tramos de MFS 2025 | Rev. Proc. 2024-40 |
| Adicional de la deducción estándar por 65+ o ciego | Instrucciones del 1040 de 2025, línea 12 |
| Redondeo a dólares enteros (texto y si es opcional) | Instrucciones del 1040 de 2025, "Rounding Off to Whole Dollars" |
| Sentido del redondeo de las líneas 11, 19 y 28 del Schedule 1-A | Instrucciones del Schedule 1-A de 2025 |
| Qué va en cada categoría de gastos del Schedule C (líneas 8–27b), subdivisión de la 16 (intereses), porcentaje deducible de comidas (24b), condiciones y tarifa del millaje (9), método simplificado de oficina en casa (30) | Instrucciones del Schedule C de 2025 (`i1040sc.pdf`); aviso del IRS con la tarifa de millaje de 2025 |
| Tope de gastos de educador de 2025 | Instrucciones del Schedule 1 de 2025, línea 11 |
| Qué tipos de comprobante cuentan para la línea 26 (1040-ES, Direct Pay, EFTPS, aplicado del año anterior) y para Schedule 3 línea 10 | Instrucciones del 1040 de 2025, línea 26; Schedule 3 de 2025 |
| Datos del préstamo del vehículo que exige el Schedule 1-A (VIN, vehículo nuevo, ensamblado en EE. UU.) | Instrucciones del Schedule 1-A de 2025, Parte IV |
| Explicación cuando el proveedor de cuidado no da su número | Instrucciones del Form 2441 de 2025 |
| Tabla de la línea de pobreza, "applicable figure" y tope de devolución del 8962 para 2025 y 2026 | `i8962.pdf` de cada año |
| Formatos aceptados hoy por `comprobantes-pagos`, `recibos-utiles-educador`, `constancia-horas-propinas` y `estado-prestamo-vehiculo` | `database/data/catalogo-fiscal/2025/documentos.php` |
| Rev. Proc. de los ajustes por inflación de 2026 y sus cifras | irs.gov (newsroom y Rev. Proc. del año) |
| Disponibilidad de Free File Fillable Forms para contrastar 2025 | irs.gov/freefile |
| Tamaño de `pdftk-java` en Debian bookworm; versión de `openspout/openspout` | `apt-cache show pdftk-java default-jre-headless`; Packagist |
