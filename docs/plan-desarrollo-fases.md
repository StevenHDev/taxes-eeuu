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

## 2. Fase 2 — Ampliación del catálogo con lógica tributaria ✅ COMPLETA (2026-07-31)

Antes de construir el motor de reglas en sí, el catálogo necesita campos que hoy no existen (confirmado por inspección directa del seeder — ver hallazgos 2026-07-31). Diseño cerrado 2026-07-31, implementado el mismo día.

**Cifras del IRS usadas (tax_year 2025), confirmadas por búsqueda web, no inventadas:** Child Tax Credit $2,200/dependiente calificado, phase-out desde $200,000 (soltero/HOH) o $400,000 (MFJ) a $50 por cada $1,000 de exceso; Credit for Other Dependents $500/dependiente, mismo phase-out; límite de ingreso bruto "qualifying relative" $5,200; Form 2441 (cuidado de dependientes) tope $3,000/$6,000, porcentaje 35%→20% entre AGI $15k–$43k (interpolado linealmente — simplificación documentada de la tabla escalonada real del IRS, no el valor exacto). Un CPA debe validar estas cifras antes de usarlas en una declaración real.

### 2.1 Decisiones cerradas

- [x] **Decisión A — Campo `creditos`:** se elimina del catálogo de recolección. Ya no es un dato que el cliente/agente declara — pasa a ser 100% un resultado calculado por el motor de reglas (ver `determinaciones_fiscales` abajo), consistente con la Decisión 2 (la plataforma es quien determina elegibilidad, no el cliente).
- [x] **Decisión B — Dónde vive el resultado calculado:** nunca en `catalogo_campos` (eso es solo para datos que entrega el cliente). Va en una tabla nueva `determinaciones_fiscales` (`user_id`, `tax_year`, `tipo`, `resultado` JSON, `version_reglas`, `calculado_en`) — separa "lo que dijo el cliente" de "lo que calculó el sistema", con trazabilidad de qué versión de reglas produjo cada resultado.

### 2.2 Campos nuevos/ampliados del catálogo

- [x] Nuevo campo transversal `estado_civil` (Dato, Object, obligatorio, único por cliente) — guarda los **hechos**, no la conclusión, para que el motor calcule el filing status:
  `casado_al_31_dic, convivio_conyuge_ultimos_6_meses, costeo_mas_mitad_hogar, existe_persona_calificable, conyuge_fallecio_en_anio, anio_fallecimiento_conyuge`.
- [x] Ampliar subcampos de `info_dependientes` (hoy solo `nombre_completo`, `fecha_nacimiento`, `ssn`): agregar `relacion`, `meses_en_hogar`, `estudiante_tiempo_completo`, `discapacitado`, `provee_mas_50_soporte_propio`, `ingreso_bruto_anual`, `custodia_compartida_sin_conflicto`.
- [x] `ingresos` en `form_1040` pasa de `Number` suelto a `Object` desglosado (mismo patrón que `info_bancaria`/`info_conyuge`): `salarios, intereses_dividendos, ganancias_capital, ingresos_jubilacion, otros_ingresos, ajustes_ingreso`. Sin esto no se puede calcular AGI.
- [x] Nuevo campo `gastos_cuidado_dependientes` en `form_1040` (Mixto, Object, formatos pdf/jpg, **obligatorio: false** — no todos los clientes tienen estos gastos) — alimenta Form 2441/Child and Dependent Care Credit: `proveedor_nombre, proveedor_ssn_ein, monto_anual, dependiente_relacionado`.
- [x] Eliminar el campo `creditos` (`array_string`) del catálogo de `form_1040` — ver Decisión A.

### 2.3 Arquitectura del motor de reglas

Principio central: separar **la forma de la regla** (el árbol de lógica del IRS — estable, casi no cambia de año en año, vive en código) de **los parámetros de la regla** (montos y umbrales en dólares — cambian cada año, viven en tabla versionada). Nunca hardcodear un monto/umbral en el código del motor.

- [x] Tabla nueva `parametros_fiscales` (`tax_year`, `categoria`, `clave`, `valor` JSON, `unique(tax_year,categoria,clave)`) + soporte `App\Support\ParametrosFiscales` (mismo diseño de caché que `TaxFieldCatalog`) + `ParametrosFiscalesSeeder` con las cifras 2025.
- [x] Tabla nueva `determinaciones_fiscales` (`user_id`, `tax_year`, `tipo`, `resultado` cifrado, `version_reglas`, `calculado_en`, `unique(user_id,tax_year,tipo)`) + modelo `DeterminacionFiscal` + relación `User::determinacionesFiscales()`.
- [x] `App\Services\Reglas\FilingStatusCalculator` — evalúa `estado_civil` → MFJ/HOH/QSS/Single (MFS por elección del contribuyente queda fuera, documentado como limitación). Recibe además si existe un qualifying child / algún dependiente calificado, **calculado por `DependentQualificationCalculator`** (no el flag auto-reportado del agente, para que agente y cálculo real no queden inconsistentes). Maneja correctamente el año exacto de fallecimiento del cónyuge (el año de la muerte sigue siendo MFJ; QSS solo aplica a los 2 años siguientes).
- [x] `App\Services\Reglas\DependentQualificationCalculator` — corre **primero** (alimenta a FilingStatus). Edad calculada contra el 31/dic real del año (`Carbon::diffInYears`, nunca restando años de nacimiento). Qualifying child / qualifying relative, más los conteos que necesita el cálculo de créditos (`conteo_ctc`, `conteo_odc`, `conteo_cuidado`).
- [x] `App\Services\Reglas\AgiCalculator` — suma los subcampos de `ingresos` menos `ajustes_ingreso`. AGI negativo permitido a propósito (pérdida de capital grande), no se fuerza a 0.
- [x] `App\Services\Reglas\CreditEligibilityCalculator` — **phase-out combinado de CTC+ODC** (no independiente por crédito — un phase-out separado subestimaría el crédito cuando cada tentativo es menor que la reducción pero la suma no), redondeo del exceso **hacia arriba** (ceil, no floor — $1 de exceso ya cuesta la reducción completa de $1,000). Crédito de cuidado de dependientes con interpolación lineal documentada del porcentaje real (escalonado en el IRS).
- [x] `App\Services\DeterminacionFiscalService::calcularPara()` — orquesta las 4 calculadoras, lee `campos_cliente` con `estado=Recibido` únicamente y siempre `valor_texto` (nunca el accessor `->valor`, que enmascara sensibles), persiste 4 filas (`updateOrCreate` por `tipo`) envuelto en `DB::transaction`. Si falta un input, la determinación queda `disponible:false` con `motivo_no_disponible`, sin excepción.
- [x] Cuándo corre el cálculo: **on-demand**, vía botón "Calcular"/"Recalcular" en la ficha del cliente (`POST /clientes/{cliente}/determinaciones`, panel web, `tax_year` requerido en el body) — no se recalcula automáticamente en cada guardado de campo.
- [x] Panel nuevo `resources/js/components/determinacion-fiscal-panel.tsx` en `clientes/show.tsx`: filing status, AGI y total de créditos (reutilizando `Card`/`StatCard`/`Badge` ya existentes, sin inventar una escala de colores nueva), lista de dependientes con badge CTC/ODC/no-calificado, y desglose de créditos.
- [x] Corrección de un gap activado por este cambio: `EventoRequest`/`CampoClienteUpdateRequest` ahora también validan que `tipo_dato` coincida con el catálogo (antes solo validaban `tipo_campo`) — sin esto, una integración desactualizada podría seguir mandando `ingresos` como `number` y corromper el AGI en silencio.
- [x] Tests: 26 casos unitarios nuevos para las 4 calculadoras (`tests/Unit/Services/Reglas/`, primeras pruebas unitarias de un Service en este repo) + 7 casos de feature para el endpoint de cálculo (`tests/Feature/DeterminacionFiscalTest.php`) + actualización de los tests existentes rotos por el cambio de catálogo (`EventoRecoleccionTest`, `ClientePanelTest`). Suite completa: 139 passed, 2 skipped, mismos 2 fallos preexistentes de la Fase 1 (no relacionados).

**Fuera de esta fase, documentado como limitación conocida (no bug):** MFS por elección del contribuyente; tope de "earned income" por cónyuge en el crédito de cuidado de dependientes; `custodia_compartida_sin_conflicto` es no-op (capturado, sin lógica todavía).

## 3. Fase 3 — Integración TaxWise (diferida, no se trabaja todavía)

> En pausa a propósito (2026-07-31): se retoma mucho más adelante, después de las fases 1 y 2. Se deja documentado para no perder el contexto, no como trabajo activo.

- [!] Confirmar con Wolters Kluwer/TaxWise el mecanismo real de integración (API vs. archivo/formato de importación) — bloqueado, requiere contacto con el proveedor, no se puede resolver por investigación interna.
- [ ] Definir formato de exportación desde la plataforma hacia TaxWise una vez se conozca el mecanismo real.

## 4. Fase 4 — Catálogo dinámico vía API para el agente conversacional ✅ COMPLETA (2026-08-02)

El prompt (`docs/prompt.md`) tenía el catálogo completo y el árbol de determinación de forma(s) hardcodeados como texto estático — y de hecho ya estaba desincronizado en tres frentes distintos al momento de esta fase: nunca había recibido el parámetro `tax_year` de la Fase 1, nunca reflejó los cambios de catálogo de la Fase 2 (`estado_civil`, `ingresos` desglosado, `gastos_cuidado_dependientes`, `creditos` eliminado), y su sección "CAMPOS POR FORMA DE NEGOCIO" describía 3 campos que nunca existieron de verdad en el catálogo (`pl_balance_general`, `gastos_deducibles`, `activos_depreciacion` — solo `estados_bancarios` es real). Se resolvió de raíz reemplazando el catálogo-como-texto por dos endpoints que el agente consulta en vivo.

- [x] Agregar parámetro `tax_year` a la tool `guardar_campo_cliente` (aplicado en `docs/prompt.md`, nunca se había hecho pese a que la Fase 1 ya lo requería del lado de la plataforma).
- [x] Nuevo paso en el árbol de determinación para confirmar para qué año fiscal es la declaración (PASO 0.5 en `docs/prompt.md`).
- [x] Reemplazar el catálogo hardcodeado del prompt por una tool que lo consulte dinámicamente: `POST /api/clientes/{cliente}/formas` (declara `formas_aplicables`, idempotente vía `firstOrCreate`) y `GET /api/clientes/{cliente}/pendientes` (checklist de lo que falta, con `tipo_campo`/`tipo_dato`/`subcampos`/`formatos_aceptados` resueltos por campo). Ambas comparten la misma respuesta (`App\Support\TaxFieldCatalog::pendientesPara()`), con `siguiente` como puntero de conveniencia al próximo campo obligatorio.
- [x] `TaxFieldCatalog::pendientesObligatoriosFor()` extraído como única fuente de verdad de "qué falta", reutilizado tanto por `EventoRecoleccionService::recalcularCompletitud()` (completitud de una forma) como por el nuevo `pendientesPara()` (checklist multi-forma) — evita que ambos cálculos puedan divergir.
- [x] `docs/prompt.md` reescrito: se eliminaron las secciones "CAMPOS ÚNICOS GLOBALES", "CAMPOS POR FORMA DE NEGOCIO" y "CONTEXTO — CATÁLOGO" (texto estático); se agregaron las tools `declarar_formas_cliente`/`consultar_pendientes_cliente`; de paso quedó al día con `tax_year` (Fase 1) y con el catálogo real de la Fase 2, ya que ninguno de los dos se había aplicado nunca a este archivo.
- [x] Tests: 16 casos nuevos (`ClienteFormasApiTest`, `ClientePendientesApiTest`, `TaxFieldCatalogPendientesTest`) — idempotencia de `/formas`, deduplicación de transversales, no-deduplicación de campos por forma de negocio (`estados_bancarios` de `schedule_c` y `schedule_e` como pendientes separadas), `completo` calculado solo sobre obligatorios, autorización consistente con el resto de la API. Suite completa: 157 passed, 2 skipped, mismos 2 fallos preexistentes de fases anteriores (no relacionados).

**Fuera de esta fase, documentado como limitación conocida:** no hay estado persistido de "cliente confirmó que esto no aplica" para campos opcionales — si la conversación se reinicia sin memoria del LLM, `/pendientes` puede volver a mostrar un opcional ya declinado (limitación ya existente, no introducida aquí).

**Pendiente, fuera de esta fase (independiente, no bloquea lo anterior):**

- [ ] Nueva sección en el prompt: extracción estructurada por tipo de documento (especialmente `declaracion_anio_anterior`, que puede traer filing status, dependientes, AGI y créditos del año pasado ya estructurados).
- [ ] Regla de confirmación: todo dato "de situación" extraído de un documento de años anteriores (estado civil, dependientes, cuenta bancaria) debe confirmarse con una pregunta corta antes de guardarse — porque son justo los datos que más cambian de un año a otro. Los datos históricos puramente financieros (AGI, créditos usados) sí se pueden guardar como referencia sin confirmación.
- [ ] Priorizar la solicitud de `declaracion_anio_anterior` más temprano en el flujo (hoy es casi lo último y opcional) porque resolverlo primero reduce cuántas preguntas nuevas hacen falta después.
- [ ] Regla de precedencia explícita: si el cliente responde algo directamente en el chat y un documento dice algo distinto, siempre gana la respuesta directa del cliente.

## 5. Pendientes menores (del comparativo original, no bloquean lo anterior) ✅ COMPLETA (2026-08-03)

Dos features independientes entre sí, ambas exclusivas del panel web (sesión) — sin superficie nueva bajo `/api` para el agente externo, así que no aplica la regla de actualizar `docs/api.md`/`docs/openapi.yaml`.

- [x] **Detección de duplicados de documentos por contenido/hash** — cruzada entre todos los clientes (no solo dentro de uno mismo), decisión confirmada con el cliente. Hash sha256 (`documentos.hash_contenido`, nullable, indexado) calculado en `EventoRecoleccionService::procesarArchivo()` sobre el archivo temporal de subida, antes de moverlo — nunca bloquea la subida, solo se guarda; la búsqueda de coincidencias (`App\Services\DocumentoDuplicadoService::buscarCoincidencias()`) corre al **mostrar** el documento, no al recibirlo. Expuesto en `ClienteController::show()` respetando el mismo límite de privacidad que ya separa a los preparadores entre sí (`ClientePolicy::view()`): un preparador solo ve el nombre/forma/campo del otro cliente si también tiene acceso a él; si no, solo un flag booleano sin identidad. Badge en `clientes/show.tsx`.
- [x] **Nivel de riesgo del caso (bajo/medio/alto)** — híbrido, decisión confirmada con el cliente: heurística automática (`App\Services\RiesgoCasoService::nivelAutomatico()` — alto si hay campos/documentos inválidos; medio si hay formas incompletas, falta la determinación fiscal, o el caso es complejo (3+ formas); bajo en cualquier otro caso) más override manual persistido (`niveles_riesgo_manual`, tabla separada a propósito — solo existe fila cuando un humano sobreescribió la sugerencia; el nivel automático nunca se persiste, se recalcula en vivo). El override manda mientras exista (`POST/DELETE clientes/{cliente}/nivel-riesgo`, mismo patrón que `marcarRevisado`). Expuesto en los tres puntos confirmados con el cliente: columna nueva y filtrable en `clientes/index.tsx`, tarjeta KPI "Alto riesgo: N" en el dashboard, y badge + control de override en `clientes/show.tsx`.
- Tests: 12 casos nuevos (`DocumentoDuplicadoTest`, `RiesgoCasoTest`). Suite completa: 169 passed, 2 skipped, mismos 2 fallos preexistentes de fases anteriores (no relacionados).
