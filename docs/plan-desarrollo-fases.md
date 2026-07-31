# Plan de desarrollo — Motor de reglas tributarias y evolución de plataforma

> Fuente: `comparativo-guia-vs-plataforma.html` (comparación guía funcional vs. plataforma) +
> respuestas del cliente a las tres decisiones ahí planteadas, confirmadas 2026-07-31.
> Este documento es el tracker de progreso de las fases que siguen a ese comparativo.
> Se va marcando `[x]` a medida que cada tarea queda implementada y verificada, no solo "escrita".

Leyenda: `[ ]` pendiente · `[~]` en progreso · `[x]` hecho · `[!]` bloqueado/necesita decisión

---

## 0. Decisiones del cliente (ya confirmadas)

- [x] **Decisión 1 — Versionado por tax year:** sí, el catálogo y las reglas deben soportar múltiples años fiscales desde ya.
- [x] **Decisión 2 — Dónde vive la lógica tributaria:** en la plataforma (filing status, dependientes, créditos), no en el agente conversacional externo.
- [x] **Decisión 3 — Integración TaxWise:** el proveedor es TaxWise (Wolters Kluwer) y sí permite integración programática; falta confirmar el mecanismo exacto (API vs. archivo).

## 1. Fase 1 — Versionado por tax year (fundación) ✅ COMPLETA (2026-07-31)

Prerrequisito técnico de las fases 2 y 3: los límites de créditos y reglas de dependientes cambian cada año fiscal, así que el motor de reglas no debe construirse sobre un schema sin `tax_year`.

- [x] Agregar columna `tax_year` a `catalogo_campos`; cambiar unique key de `(forma, clave)` a `(forma, clave, tax_year)`.
- [x] Agregar `tax_year` a `campos_cliente`, `formas_cliente`, `documentos` (y también `historial_cambios`, no contemplado originalmente pero necesario para no mezclar auditoría entre años).
- [x] Actualizar `TaxFieldCatalog` para servir el catálogo por año (cache única, filtrada en memoria por año — ver plan de implementación).
- [x] Actualizar el panel admin `/catalogo` para poder crear/editar campos por año (selector de año).
- [x] Estrategia de migración de datos existentes: `DEFAULT 2025` a nivel de columna (datos de prueba únicamente, riesgo mínimo).
- [x] Extra no planeado originalmente pero necesario para que el versionado sea coherente de punta a punta: `EventoRequest`/`CampoClienteUpdateRequest`/`CatalogoCampoRequest` (validación), `ClienteController`/`CampoClienteController`/`DashboardController` (web y API, escopeo por año), `DashboardSummaryService`, `ClienteExportService`, `config/tax.php`, y `clientes/show.tsx` (selector de año).
- [x] Tests: payloads existentes actualizados + 6 casos nuevos (independencia de filas/completitud por año, no-contaminación de `unico_por_cliente` entre años, rechazo de evento sin `tax_year`, duplicado de catálogo entre años). Suite completa: 103 passed, 2 skipped, 2 fallos preexistentes **no relacionados** (confirmados por diff — `ExampleTest` y el buscador de `clientes.index` — ver nota abajo).
- [!] Pendiente, fuera de esta fase a propósito: acción "duplicar catálogo a un año nuevo" en el panel admin (hoy se hace campo por campo).

> **Nota — 2 fallos preexistentes encontrados, no introducidos por esta fase:** `ExampleTest::test_returns_a_successful_response` (la ruta `home` redirige — probablemente requiere sesión) y `ClientePanelTest::test_el_buscador_filtra_clientes_por_nombre_email_o_telefono` (el `ClienteController::index` web nunca implementó el filtro por `search` que ese test espera — el filtrado real hoy es client-side). Ninguno de los dos toca código de esta fase; quedan para revisar aparte.

## 2. Fase 2 — Ampliación del catálogo con lógica tributaria

Antes de construir el motor de reglas en sí, el catálogo necesita campos que hoy no existen (confirmado por inspección directa del seeder — ver hallazgos 2026-07-31). Diseño cerrado 2026-07-31.

### 2.1 Decisiones cerradas

- [x] **Decisión A — Campo `creditos`:** se elimina del catálogo de recolección. Ya no es un dato que el cliente/agente declara — pasa a ser 100% un resultado calculado por el motor de reglas (ver `determinaciones_fiscales` abajo), consistente con la Decisión 2 (la plataforma es quien determina elegibilidad, no el cliente).
- [x] **Decisión B — Dónde vive el resultado calculado:** nunca en `catalogo_campos` (eso es solo para datos que entrega el cliente). Va en una tabla nueva `determinaciones_fiscales` (`user_id`, `tax_year`, `tipo`, `resultado` JSON, `version_reglas`, `calculado_en`) — separa "lo que dijo el cliente" de "lo que calculó el sistema", con trazabilidad de qué versión de reglas produjo cada resultado.

### 2.2 Campos nuevos/ampliados del catálogo

- [ ] Nuevo campo transversal `estado_civil` (Dato, Object, obligatorio, único por cliente) — guarda los **hechos**, no la conclusión, para que el motor calcule el filing status:
  `casado_al_31_dic, convivio_conyuge_ultimos_6_meses, costeo_mas_mitad_hogar, existe_persona_calificable, conyuge_fallecio_en_anio, anio_fallecimiento_conyuge`.
- [ ] Ampliar subcampos de `info_dependientes` (hoy solo `nombre_completo`, `fecha_nacimiento`, `ssn`): agregar `relacion`, `meses_en_hogar`, `estudiante_tiempo_completo`, `discapacitado`, `provee_mas_50_soporte_propio`, `ingreso_bruto_anual`, `custodia_compartida_sin_conflicto`.
- [ ] `ingresos` en `form_1040` pasa de `Number` suelto a `Object` desglosado (mismo patrón que `info_bancaria`/`info_conyuge`): `salarios, intereses_dividendos, ganancias_capital, ingresos_jubilacion, otros_ingresos, ajustes_ingreso`. Sin esto no se puede calcular AGI.
- [ ] Nuevo campo `gastos_cuidado_dependientes` en `form_1040` (Mixto, Object, formatos pdf/jpg) — alimenta Form 2441/Child and Dependent Care Credit: `proveedor_nombre, proveedor_ssn_ein, monto_anual, dependiente_relacionado`.
- [ ] Eliminar el campo `creditos` (`array_string`) del catálogo de `form_1040` — ver Decisión A.

### 2.3 Arquitectura del motor de reglas

Principio central: separar **la forma de la regla** (el árbol de lógica del IRS — estable, casi no cambia de año en año, vive en código) de **los parámetros de la regla** (montos y umbrales en dólares — cambian cada año, viven en tabla versionada). Nunca hardcodear un monto/umbral en el código del motor.

- [ ] Tabla nueva `parametros_fiscales` (`tax_year`, `categoria` — ej. `credito_ctc`, `deduccion_estandar`, `limite_ingreso_qualifying_relative` —, `clave`, `valor` JSON). Una tabla genérica versionada en vez de una tabla dedicada por crédito, para no migrar el schema cada vez que se agregue un crédito nuevo.
- [ ] Tabla nueva `determinaciones_fiscales` (ver Decisión B) para los resultados calculados.
- [ ] `FilingStatusCalculator` — evalúa `estado_civil` (los 6 hechos) → Single/MFJ/MFS/HOH/QSS. Lógica fija en código, sin parámetros por año.
- [ ] `DependentQualificationCalculator` — corre la prueba de qualifying child / qualifying relative por cada dependiente, usando los umbrales de edad/ingreso del `tax_year` desde `parametros_fiscales`.
- [ ] `AgiCalculator` — suma los subcampos de `ingresos` menos `ajustes_ingreso`.
- [ ] `CreditEligibilityCalculator` — por cada crédito definido en `parametros_fiscales` para el `tax_year`, evalúa elegibilidad (filing status, dependientes calificados, AGI) y aplica la fórmula de phase-out para el monto final.
- [ ] Definir cuándo corre el cálculo: **on-demand** (el preparador lo dispara, o se dispara al marcar una forma como completa) — no recalcular en cada guardado de campo individual, por costo y ruido. Si algo descalifica a un dependiente o no cuadra, generar alerta para revisión humana en vez de fallar en silencio.

## 3. Fase 3 — Integración TaxWise (diferida, no se trabaja todavía)

> En pausa a propósito (2026-07-31): se retoma mucho más adelante, después de las fases 1 y 2. Se deja documentado para no perder el contexto, no como trabajo activo.

- [!] Confirmar con Wolters Kluwer/TaxWise el mecanismo real de integración (API vs. archivo/formato de importación) — bloqueado, requiere contacto con el proveedor, no se puede resolver por investigación interna.
- [ ] Definir formato de exportación desde la plataforma hacia TaxWise una vez se conozca el mecanismo real.

## 4. Agente conversacional (prompt externo) — mejoras propuestas

El prompt hoy tiene el catálogo completo hardcodeado como texto estático, y la tool `guardar_campo_cliente` no tiene concepto de año fiscal. Ambas cosas quedan obsoletas apenas avancen las fases 1 y 2.

- [ ] Agregar parámetro `tax_year` a la tool `guardar_campo_cliente` (depende de Fase 1).
- [ ] Nuevo paso en el árbol de determinación para confirmar para qué año fiscal es la declaración (no asumirlo por default).
- [ ] Reemplazar el catálogo hardcodeado del prompt por una tool que lo consulte dinámicamente contra un endpoint real — hoy la ruta `catalogo` es web/sesión (`routes/catalogo.php`), **no existe expuesta bajo `/api` (Sanctum)** para que un agente externo la consuma.
- [ ] Endpoint nuevo, ej. `GET /api/clientes/{id}/pendientes`, que combine catálogo vigente + datos ya guardados del cliente y devuelva directamente qué campos faltan — así el agente no mantiene el checklist por su cuenta y queda sincronizado automáticamente ante cualquier campo nuevo que se agregue al catálogo en el futuro.
- [ ] Nueva sección en el prompt: extracción estructurada por tipo de documento (especialmente `declaracion_anio_anterior`, que puede traer filing status, dependientes, AGI y créditos del año pasado ya estructurados).
- [ ] Regla de confirmación: todo dato "de situación" extraído de un documento de años anteriores (estado civil, dependientes, cuenta bancaria) debe confirmarse con una pregunta corta antes de guardarse — porque son justo los datos que más cambian de un año a otro. Los datos históricos puramente financieros (AGI, créditos usados) sí se pueden guardar como referencia sin confirmación.
- [ ] Priorizar la solicitud de `declaracion_anio_anterior` más temprano en el flujo (hoy es casi lo último y opcional) porque resolverlo primero reduce cuántas preguntas nuevas hacen falta después.
- [ ] Regla de precedencia explícita: si el cliente responde algo directamente en el chat y un documento dice algo distinto, siempre gana la respuesta directa del cliente.

## 5. Pendientes menores (del comparativo original, no bloquean lo anterior)

- [ ] Detección de duplicados de documentos por contenido/hash (hoy solo se detecta por campo marcado `unico_por_cliente`).
- [ ] Nivel de riesgo del caso (bajo/medio/alto) para priorizar la cola de revisión humana.
