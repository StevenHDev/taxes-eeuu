# Fase 2 — Entrevista por WhatsApp y por el portal: plan técnico

30 oct. 2026 · **Aprobado** por Steven (30 oct.) · Documento madre: [secciones 2, 3, 5.1, 8.3 y 9.1–9.7](../documento-madre.md), decisiones L1 y L3 de la [sección 10](../documento-madre.md#10-decisiones-pendientes)

> **Objetivo:** que el cliente responda **la entrevista del catálogo fiscal del año** (preguntas del 13614-C en tres niveles, sección 3) por el **portal** o por **WhatsApp con el agente**, con **una sola fuente**: los dos canales leen las mismas preguntas, guardan en el mismo lugar y comparten el avance, así que nadie repite una respuesta. Antes de la primera pregunta el cliente firma el **consentimiento §7216** en el portal (8.3). Lo sensible y los documentos **solo** van por el portal (9.6). Al final el cliente **revisa y confirma en el portal** (reemplaza la atestación por WhatsApp). Al cerrar la fase se migran los datos de `campos_cliente` y se retira el flujo actual (formulario por formas, árbol de 44 pasos, pseudoformas).
>
> **Buenas prácticas:** skills `laravel-best-practices`, `react-best-practices` y `vercel-react-best-practices`. Cada tarea cita la regla que aplica. Es la fase con más frontend hasta ahora y la primera que usa el **cliente desde el celular**: cada parte de interfaz dice cómo cumple las reglas de UI (carga, responsive, jerarquía, consistencia, accesibilidad, estados).
>
> **Base:** código de `main` al 30 oct. (Laravel 13, Inertia 3 + React 19, Pest 4, PostgreSQL), con F0 (menos F0.4) y F1.1–F1.5 en producción. El catálogo 2025 y 2026 ya está cargado y verificado (75 preguntas, 41 documentos, 26 formularios, 106 activaciones).

> **Lo que espera una decisión legal (no bloquea programar):**
> - **L1 — texto del consentimiento §7216:** se construye todo el mecanismo (2.4) con un texto **provisional** marcado como tal. Lo que espera L1 es **activarlo con clientes reales** (2.10).
> - **L3 — entrevista por WhatsApp:** el agente nuevo (2.8) se construye y se prueba con usuarios de prueba. Lo que espera L3 es **encender la entrevista por WhatsApp para clientes reales** (`tax.entrevista_whatsapp`). Si L3 dice que no, el agente queda en "explica y manda el enlace" sin tocar código.
> - L2/L4 (OpenAI y acceso desde fuera de EE. UU.) afectan también al agente de hoy; esta fase no lo empeora: el agente nuevo manda **menos** datos a OpenAI (nada sensible, ningún documento).

## Resumen

| # | Tarea | Sale de | Tamaño | Riesgo |
| --- | --- | --- | --- | --- |
| 2.1 | Los preparadores también validan piezas del catálogo | Decisión 29 oct. | Pequeño | Bajo |
| 2.2 | Respuestas de la entrevista y motor único (+ bandera por cliente) | 3, 9.6 | Grande | Medio: es la base de todo lo demás |
| 2.3 | Mapa de equivalencias `campos_cliente` → catálogo y reporte (antes F1.6) | 9.3.1 | Pequeño | Bajo: solo lee |
| 2.4 | Consentimiento §7216 en el portal | 8.3 | Mediano | Medio: **espera L1** para clientes reales |
| 2.5 | Entrevista en el portal: 8 pantallas en tres niveles | 3, 9.7 | Grande | Medio |
| 2.6 | Documentos que pide la entrevista (lista mínima y carga) | 5.1, 9.6 | Mediano | Bajo; ver pregunta 1 |
| 2.7 | Revisión y confirmación del cliente en el portal (reemplaza la atestación) | 9.1, 9.3.2 | Mediano | Bajo |
| 2.8 | Agente de WhatsApp reescrito sobre el catálogo; prueba de María | 9.6, 9.7, E5, E6 | Grande | Alto: **espera L3** para clientes reales |
| 2.9 | Vista del preparador: entrevista en la ficha y chat del portal en Mensajes | 9.6, 9.7 | Mediano | Bajo |
| 2.10 | Cambio de flujo y migración de `campos_cliente` | 9.3.1 | Mediano | Medio: datos reales (pocos) |
| 2.11 | Retiro del flujo anterior (E5, E6, E12, tool y menú viejos) | 9.4 | Mediano | Medio: mucho código que se borra |

Orden sugerido de commits: 2.1 → 2.2 → 2.3 → 2.4 → 2.5 → 2.6 → 2.7 → 2.8 → 2.9 → 2.10 → 2.11. Cada una se despliega y se revisa en producción antes de la siguiente. Hasta 2.10, **todo lo nuevo queda detrás de la bandera por cliente** (2.2) y solo se enciende a usuarios de prueba (`es_prueba`, F0.3): los clientes reales siguen con el flujo actual sin ningún cambio. 2.3 no depende de nada y se puede adelantar.

---

## 2.1 Los preparadores también validan piezas del catálogo

**Hoy:** `CatalogoFiscalPolicy::validar()` solo deja al administrador (decisión de Steven, 29 oct.: "más adelante también los preparadores"). Hay una prueba que documenta ese comportamiento.

**Enfoque** (regla `controller-middleware`, la autorización sigue en la policy):
- `validar()` acepta `Preparer` y `Administrator`. `update()` no cambia: editar sigue siendo solo del administrador.
- El frontend ya recibe un permiso calculado con la policy; se agrega `puedeValidar` aparte de `puedeEditar` en `ArmaPaginaCatalogo`, para que el preparador vea "Marcar como validado" en el panel de detalle y no vea "Editar".
- Quién validó ya queda en `validado_por` y en `catalogo_cambios`; no hace falta nada más.

**Cómo quedó (30 oct.):** `CatalogoFiscalPolicy::validar()` acepta preparador y administrador; `ArmaPaginaCatalogo` manda `puedeValidar` aparte de `puedeEditar`. En el panel de detalle, `AccionesPieza` sin `onEditar` muestra solo "Marcar como validado" (como acción principal) y, si falta el enlace a la fuente, le dice al preparador que se lo pida a un administrador. No se probó en el navegador como preparador (el usuario preparador local no tiene 2FA y el MFA obligatorio lo manda a Seguridad); está cubierto por las pruebas.

**Pruebas:** se invierte la prueba actual: el preparador valida (y queda su id en `validado_por`), pero sigue recibiendo 403 al crear, editar o desactivar. El cliente sigue con 403.

---

## 2.2 Respuestas de la entrevista y motor único

**Hoy:**
- Las respuestas viven en `campos_cliente` (`CampoCliente`: `forma`, `campo`, `valor_texto` cifrado, `source`), organizadas **por forma** y con las pseudoformas `transversal` y `documentos_extra`. Qué se pregunta lo decide `TaxFieldCatalog::pendientesPara()` a partir de las formas declaradas (`FormaCliente`), más 7 dependencias fijas en código (`PortalFormularioController::ocultosPorDependencia()`) y el árbol de 44 pasos (`PromptActivoStep`, `ActivosResolver`, `ActivosPromptComposer`).
- La fase de la conversación la calcula `EstadoConversacionResolver` desde `FormaCliente` y `TaxFieldCatalog` (bien: nunca desde la memoria del chat), pero el portal y el agente tienen **reglas distintas** para qué mostrar.
- El historial de cambios es `HistorialCambio` (por `forma` + `campo`, valores cifrados).
- El catálogo nuevo ya tiene `nivel`, `pantalla`, `por_persona`, `sensible`, `condicion` y las activaciones, pero **nadie los evalúa** (F1: "la evalúa la entrevista de la fase 2"). El lector `CatalogoFiscal` tiene `preguntas()` y `activadosPor()`.

**Enfoque — datos** (reglas `arch-feature-folders`, `eloquent-relationships`, `eloquent-casts`, `eloquent-soft-deletes`, `sec-mass-assignment`):

Tablas nuevas; `campos_cliente` no se toca. Modelos en `App\Models\Entrevista\*`, enums en `App\Enums\Entrevista\*`.

| Tabla | Qué guarda | Columnas clave |
| --- | --- | --- |
| `entrevistas` | Una por cliente y año | `user_id`, `tax_year` (únicos juntos), `estado` (`en_curso` / `confirmada` / `reabierta`), `canal_inicio`, `iniciada_en`, `confirmada_en`, `consentimiento_id` |
| `entrevista_personas` | Cada persona del bloque C | `entrevista_id`, `orden`, `etiqueta` (no sensible: "Persona 1", y el parentesco cuando se sepa), `deleted_at` (quitar una persona la archiva, F0.3) |
| `entrevista_respuestas` | Una respuesta por pregunta (y por persona en el bloque C) | `entrevista_id`, `pregunta_codigo`, `persona_id` (nulo fuera del bloque C), `valor` (`encrypted:array`, siempre cifrado como `campos_cliente`), `canal` (`whatsapp` / `portal` / `preparador` / `migracion`), `actor_id`, `respondida_en`. Únicos: dos índices parciales de PostgreSQL, (`entrevista_id`, `pregunta_codigo`) cuando `persona_id` es nulo y (`entrevista_id`, `pregunta_codigo`, `persona_id`) cuando no |
| `entrevista_respuesta_cambios` | Historial (solo se inserta) | `respuesta_id`, `valor_anterior`, `valor_nuevo` (cifrados), `canal`, `actor_id`, `created_at` |

- **Se guarda el código de la pregunta, no su id.** Las activaciones y las condiciones ya trabajan por código del año (F1.1), y una pregunta desactivada nunca deja una respuesta huérfana (las piezas no se borran).
- **Forma del valor según `tipo_respuesta`:** `si_no` → `"si"`/`"no"`; `usted_conyuge_no` → `["usted"]`, `["conyuge"]`, `["usted","conyuge"]` o `"no"`; `opciones` → el `valor` de la opción; `casillas` → lista de valores; `dato`/`fecha`/`numero` → texto, fecha ISO o número. Un value object `ValorRespuesta` lo normaliza y lo valida contra la pregunta (regla `arch-value-objects`).
- **Historial:** un observer escribe `entrevista_respuesta_cambios` en cada `updated` (regla `eloquent-model-events`). Los modelos se registran también en `AuditoriaObserver` (solo nombres de atributos, como hoy).
- **Bandera por cliente:** columna `users.entrevista_catalogo` (booleano, falso por defecto) y `config('tax.entrevista_catalogo_por_defecto')` para los clientes nuevos (falso hasta 2.10). La cambia solo el administrador, desde Usuarios o la ficha, y queda en la bitácora (mismo patrón que `es_prueba`). `User::usaEntrevistaCatalogo()` es el único lugar que la lee. No se instala Pennant: es una sola bandera temporal que se borra en 2.11.
- **El año de la entrevista** es `config('tax.current_tax_year')` (ver pregunta 5). Ya no sale del mayor `tax_year` de `FormaCliente`.

**Enfoque — motor único** (reglas `arch-service-classes`, `arch-dto-pattern`, `eloquent-eager-loading`):

Todo vive en `App\Services\Entrevista\`. **El portal, el agente y la vista del preparador solo usan estas clases**; ninguna tiene sus propias reglas de qué mostrar.

- `EvaluadorCondicion`: evalúa el JSON de `condicion` (`pregunta`/`es`, `todas`, `alguna`) contra las respuestas. `es` con lista = "alguna de"; con `usted_conyuge_no` basta con que se cruce ("usted o cónyuge"). Si la pregunta referida es `por_persona`, se evalúa **dentro de la misma persona** (así C12–C14 miran el C3 de esa persona). Se agrega un operador `hasta` para números (`{"pregunta":"C4","hasta":6}`), que pide la observación de Steven sobre C11–C14 ("quien vivió 6 meses o menos"); `CondicionCatalogoValida` se amplía igual (regla `validation-custom-rules`).
- `MotorEntrevista::estado(Entrevista)` devuelve un DTO `EstadoEntrevista` con, por pantalla (1–8): preguntas **visibles**, respondidas, pendientes, cuáles son sensibles, y el avance. Además: documentos y formularios **activados** (con la pregunta que los activó, para el "por qué te lo pedimos") y "lo que falta en el portal" (sensibles y documentos).
- **Regla de visibilidad** (una sola, escrita y probada aquí): una pregunta se muestra si no es `no_se_pregunta`, su `condicion` (si tiene) se cumple, y además es `entrada` **o** una respuesta la activa (`catalogo_activaciones` con objetivo `pregunta`) **o** tiene `condicion` propia. Así un administrador puede sumar un seguimiento desde la pantalla del catálogo (con su condición) sin tocar código, aunque las activaciones todavía no se editen en pantalla (F1.5).
- **Respuestas que quedan ocultas:** si el cliente cambia B1 de "casado" a "soltero", las respuestas de B2–B4 **no se borran** (conservar, F0.3); quedan fuera del estado y del resumen, y vuelven si la condición se cumple otra vez.
- `RespuestaGuardador::guardar(Entrevista, RespuestaData, Canal, User $actor)`: **único camino de escritura** para portal, agente, preparador y migración. En una transacción: valida que la pregunta exista en el año, que sea visible, que el valor corresponda al tipo y a las opciones, y que **un canal `whatsapp` nunca guarde una pregunta `sensible`** (la regla está en código, no solo en el prompt). Si la entrevista estaba `confirmada`, pasa a `reabierta` (2.7). Al final sincroniza `FormaCliente` (abajo).
- **Compatibilidad con lo que lee `FormaCliente`:** la lista de clientes, sus filtros, el dashboard y el motor de cálculo leen las formas declaradas. El guardador mantiene `FormaCliente` al día desde los formularios activados (1040 siempre, `sch-c` → `schedule_c`, `sch-e` → `schedule_e`), así esas pantallas siguen funcionando hasta que la fase 5 las pase a expedientes.
- **Caché:** el motor usa el lector `CatalogoFiscal` (ya cacheado por año) y arma índices por código una vez por petición (`keyBy`); las respuestas de la entrevista se cargan con una sola consulta con `with('personas')`.

**Contenido nuevo del catálogo** (en los archivos de datos de 2025 y 2026 y con `catalogo-fiscal:cargar`, como en F1.3; quedan "sin validar"). Ver pregunta 9:
- `C0` (pantalla 4, entrada): "¿Alguien vivió contigo en {anio} o mantuviste a alguien?" — la pregunta de entrada del bloque C, que no está en el catálogo. "No" salta todo el bloque.
- `D1.1` (seguimiento de D1): "¿En cuántos empleos?" y `F4.1` (seguimiento de F4): "¿Cuántas pólizas del Mercado?". Dan el "uno por fuente" de 5.1.
- `D18` (pantalla 5): "¿Eres socio o accionista de una LLC, sociedad o S-corp y recibiste un Schedule K-1?" → documento nuevo `k-1` (1065 o 1120-S) y formulario `sch-e` (parte II). Su ayuda dice que la declaración de la **empresa** la hace otra firma, y su efecto es una alerta al preparador. Así se corrige lo que hoy hace `determinacion_formas.md` ("no lo atendemos"), visto al cerrar F0.6.
- A5 tiene `pantalla` nula: pasa a la pantalla 2 (es "¿alguna aplica a ti?"). A14 y A15 siguen como `no_se_pregunta`.
- La condición de C11–C14 se amplía con `C4 hasta 6`.

**Pruebas (Pest):** `tests/Feature/Entrevista/MotorTest.php` y `GuardadorTest.php`
- Condiciones: B2–B4 solo con B1 = casado; A7 solo con A6 = no; C12–C14 por persona (un hijo no los ve; un padre sí; un hijo que vivió 5 meses sí).
- Activaciones: D1 = sí abre `D1.1` y activa el documento `w2`; D15 activa `sch-c` y `FormaCliente` queda con `schedule_c`.
- Cambiar B1 conserva B2–B4 pero los saca del estado; volver a casado los recupera.
- El canal `whatsapp` no puede guardar A3, B4, C9, C10, G3, G4 ni A12 (error, nada escrito).
- Un valor fuera de las opciones o de otro tipo falla; una pregunta no visible falla.
- Cada cambio deja una fila de historial con el valor anterior; responder una entrevista confirmada la reabre.
- El valor queda cifrado en la base.

---

## 2.3 Mapa de equivalencias `campos_cliente` → catálogo y reporte (antes F1.6)

**Hoy:** `catalogo_campos` 2025 tiene 138 campos (incluidas las pseudoformas). `campos_cliente` guarda lo que respondió cada cliente; en producción hay 3 clientes con formulario. El diseño de este mapa está en el [plan de F1, sección 1.6](fase-1-catalogo-fiscal.md#16-equivalencias-de-campos_cliente-y-retiro-de-las-pseudoformas-e12).

**Enfoque** (reglas `eloquent-chunking`, `arch-service-classes`):
- `database/data/catalogo-fiscal/equivalencias-campos-cliente.php`: para cada `forma` + `campo` (+ subcampo) de `catalogo_campos` 2025, el destino en el catálogo nuevo: **pregunta** (con cómo se transforma el valor), **persona del bloque C** (por ejemplo `info_dependientes[*].nombre → C1`), **documento** (el archivo pasa a ese código) o **sin equivalente** con el motivo. Ejemplos: `transversal.estado_civil.casado_al_31_dic → B1`; `info_conyuge → B4`; `w2 → documento w2` + `D1 = sí`; `form_1095_a → documento 1095-a` + `F4 = sí`; `form_1040.ingresos` → sin equivalente ("se lee del documento", 9.3.2).
- `App\Services\Entrevista\EquivalenciasCamposCliente` lee el archivo; el comando `php artisan catalogo-fiscal:equivalencias --reporte` recorre `campos_cliente` por partes y muestra: cuántos datos tienen destino, cuántos no, y qué claves del catálogo viejo faltan en el archivo. **Nunca muestra valores.** No escribe nada.
- El menú viejo "Catálogo" **no se renombra** (F1 lo iba a dejar como "Campos del formulario actual"): como se retira en 2.11, renombrarlo dos semanas antes no aporta.

**Pruebas:** toda clave en alcance de `catalogo_campos` 2025 está en el archivo; todo destino existe en el catálogo 2025 (incluidos los códigos nuevos de 2.2); el reporte cuenta bien con datos de prueba y no imprime valores.

---

## 2.4 Consentimiento §7216 en el portal (espera L1 para clientes reales)

> **Primera entrada sin pasar por el correo (decisión de Steven, 30 oct.).** Hoy, al crear la cuenta, `BienvenidaClientePortal` manda un **correo** con "Definir mi contraseña" (token de restablecimiento de un solo uso, 60 min, `config/auth.php`), y los enlaces que manda el agente (`AgenteConversacionalService::linkPortal()`) llevan al inicio de sesión. En F2:
> - Al crear la cuenta, el **primer enlace que manda el agente por WhatsApp sirve para crear la contraseña**: lo arma el código (nunca el modelo) con el mismo token de un solo uso (`Password::createToken`), y al guardarla el cliente **queda con la sesión iniciada** y entra directo a `portal/consentimiento` (respuesta propia de Fortify para el restablecimiento, solo en este caso). El correo de bienvenida se sigue mandando como respaldo.
> - Columna `users.contrasena_definida_en`: mientras sea nula, el agente puede volver a mandar un enlace nuevo (si venció) **solo al mismo número de WhatsApp de la cuenta**, con una tool `enviar_enlace_activacion`. Una vez definida, el agente **nunca** manda enlaces para cambiar la contraseña: "¿Olvidaste tu contraseña?" sigue yendo por correo (si alguien toma el WhatsApp del cliente, no puede cambiarle la contraseña).
> - El enlace vence a los 60 minutos, es de un solo uso y no muestra datos del cliente hasta que se crea la contraseña.
> - **Verificación en dos pasos para clientes (L9, decisión de Steven, 30 oct.):** no es obligatoria para clientes, pero la pueden activar en Ajustes → Seguridad (ya funciona hoy; F0.1 solo la exige al personal, con `security.mfa_roles`). La revisión de seguridad (2.7) sugiere activarla, sin obligar. Queda sujeta a lo que diga el abogado sobre L9.
> - Pruebas: el enlace del agente crea la contraseña, inicia sesión y lleva al consentimiento; un token usado o vencido no sirve; con la contraseña ya definida, `enviar_enlace_activacion` no manda nada y el agente indica "¿Olvidaste tu contraseña?"; un número distinto al de la cuenta no recibe enlaces; un cliente puede activar y desactivar la verificación en dos pasos y no se le exige.

**Hoy:** no existe ningún consentimiento en el código (no hay tabla, ruta ni texto). El cliente creado por el agente recibe `BienvenidaClientePortal` para definir su contraseña y entra directo a `portal/formulario`.

**Enfoque** (reglas `controller-middleware`, `controller-form-requests`, `controller-single-action`, `eloquent-casts`):
- Tablas:
  - `consentimiento_textos`: `codigo` (por ejemplo `uso_7216`; L1 dice si son uno o dos), `version`, `texto_es`, `texto_en`, `provisional` (booleano), `vigente_desde`. Los textos viven en `database/data/consentimientos/{codigo}-v{n}.{es,en}.md` y se cargan con `php artisan consentimientos:cargar` (idempotente, como el catálogo). Mientras no llegue L1 hay una versión `provisional = true`.
  - `consentimientos`: `user_id`, `tax_year`, `consentimiento_texto_id`, `nombre_escrito` (el cliente escribe su nombre como firma), `aceptado_en`, `ip`, `user_agent`. **Solo se inserta** (8.3: texto, versión, fecha y hora). Un consentimiento firmado no se edita ni se borra.
- Middleware `EnsureConsentimiento7216` en las rutas de la entrevista y de documentos del portal: si al cliente le falta un consentimiento vigente para el año, lo manda a `portal/consentimiento`. El chat de dudas y la configuración de la cuenta no lo exigen.
- `ConsentimientoController` (`show` y `store`) con `FirmarConsentimientoRequest`: casilla "Leí y acepto", nombre escrito (obligatorio) y la versión que se mostró (si cambió entre mostrar y firmar, se vuelve a mostrar).
- **Formato y vigencia** (letra, firma, si vence): lo dice L1. El diseño ya separa texto, versión y año; si L1 pide renovar cada año o al cambiar el texto, el middleware solo compara la versión.
- **Freno para clientes reales:** `catalogo-fiscal:verificar` (o un chequeo en 2.10) falla si el texto vigente es provisional. Los usuarios de prueba sí pueden firmar el provisional.
- El agente explica qué es y manda el enlace (2.8); nunca recibe la firma.

**Interfaz** (`resources/js/pages/portal/consentimiento.tsx`): una pantalla de lectura con el texto (con encabezados), la casilla, el nombre y un solo botón principal "Firmar y continuar". Si el texto es provisional, se ve una franja "Texto provisional (solo pruebas)". Cumple las reglas comunes del portal (tabla después de 2.7), más: el texto va en un contenedor con `max-w-prose` y tamaño de letra normal (no hay que leer letra chica en el celular); el botón está deshabilitado hasta marcar la casilla y escribir el nombre, y el motivo se lee junto al botón ("Marca la casilla para continuar"), no solo por el color.

**Pruebas:** sin consentimiento, la entrevista redirige a la firma; con firma, entra; la firma guarda texto, versión, IP y hora; un texto nuevo vuelve a pedir la firma; no hay ruta para editar ni borrar; un cliente real no puede activarse con texto provisional; el preparador ve la versión y la fecha en la ficha (2.9).

---

## 2.5 Entrevista en el portal: 8 pantallas en tres niveles

**Hoy:** `portal/formulario.tsx` (788 líneas) muestra en una sola página larga los campos de las formas declaradas, con los grupos de `PromptActivoStep` y las 7 dependencias en código; guarda campo por campo con `PortalFormularioController::guardarCampo` → `EventoRecoleccionService::registrarDesdePortal()`. Si el cliente no tiene formas declaradas, `index()` lo manda al chat (`portal/chat`) para que el agente las determine. El chat de dudas (`PortalChatPanel`) va al lado.

**Enfoque backend** (reglas `controller-form-requests`, `validation-array-validation`, `validation-conditional-rules`, `validation-after-hooks`, `controller-middleware`, `controller-dependency-injection`):
- Rutas nuevas en `routes/portal.php`, con middleware `auth`, `verified`, `EnsureCliente` (nuevo: reemplaza el `clienteAutenticado()` repetido en los dos controladores de hoy) y `EnsureConsentimiento7216`:
  - `GET portal` → `portal.inicio` si el cliente usa la entrevista nueva; si no, igual que hoy.
  - `GET portal/entrevista` (inicio: avance por pantalla).
  - `GET portal/entrevista/{pantalla}/{paso?}` donde `paso` es `entrada` o `seguimiento` (o `persona/{persona}` en la pantalla 4).
  - `PUT portal/entrevista/{pantalla}/{paso}` guarda **todas las respuestas del paso** de una vez.
  - `POST` y `DELETE portal/entrevista/personas` (agregar o quitar una persona del bloque C).
- Controladores en `App\Http\Controllers\Portal\` (`EntrevistaController`, `PersonaController`). Cada `show` manda **solo las preguntas del paso** y sus respuestas, ya filtradas por el motor (vercel: `server-serialization`).
- **Cada pantalla tiene dos pasos y el servidor decide el segundo:** primero las preguntas de entrada; al guardar, el motor calcula qué seguimientos abrieron esas respuestas y el cliente pasa al paso "seguimiento" (o a la pantalla siguiente si no hay ninguno). **El navegador no evalúa condiciones**: así la regla de qué mostrar existe una sola vez (en PHP) y es la misma para el agente. El costo es un toque más ("Continuar") cuando hay seguimiento, que en el celular es lo natural.
- `GuardarPasoRequest`: arma las reglas desde las preguntas visibles del paso (por tipo y opciones). En un `after` rechaza códigos que no son de ese paso o que no son visibles. Guarda con `RespuestaGuardador` (canal `portal`) y devuelve `redirect` al paso siguiente con un toast.
- **Los tres niveles:**
  1. Entrada: las 8 pantallas de la observación de Steven. Las pantallas 2, 5, 6 y 7 son **una sola lista de casillas** con "Ninguna" (excluyente). Marcar = `si` (o `usted`/`conyuge`), no marcar = `no` al guardar el paso. **El bloque D (ingresos) se contesta así en un solo paso** (D1–D18 juntos), en lugar del "¿algo más?" de a uno del árbol actual.
  2. Seguimiento: el paso que abre el servidor (B2–B6, `D1.1`, `F4.1`, C11–C14 según la persona…).
  3. No se pregunta: lo `no_se_pregunta` no aparece nunca.
- **Pantalla 1 (identidad):** A1 y A2 vienen precargados de la cuenta (nombre, correo, teléfono) y el cliente confirma o corrige; A3 es una opción; las tarjetas e identificación se suben en la misma pantalla con el componente de 2.6. La lectura automática de la identificación ("solo se confirman") es de la fase 4.
- **Pantalla 4 (personas):** `C0`; si es "Sí", una lista de personas con "Agregar persona" y, por cada una, su propio paso con C1–C11 y luego sus seguimientos. Quitar una persona la archiva.
- **Lo que llegó por WhatsApp se ve y se corrige:** cada respuesta trae su canal y fecha; en el paso aparece "Respondiste por WhatsApp el 30 oct." junto a la pregunta, ya marcada, y el cliente la puede cambiar (9.6). La pantalla de inicio dice qué pantallas ya están completas, sin importar el canal.
- **"Mi información" para estos clientes:** se esconde del menú (muestra cálculos del motor sin revisar, 9.7) y el inicio del portal pasa a ser el avance de la entrevista. El reemplazo completo ("Mi declaración") es de la fase 5.
- **Chat de dudas:** `PortalChatPanel` sigue al lado (sin carga de archivos: los documentos van por 2.6) y responde con la fase `PortalDudas` (2.8).

**Enfoque frontend** (react-best-practices: *derivar durante el render*, *resetear con `key`*, sin `useEffect` para estado derivado; vercel: `rerender-no-inline-components`, `rerender-derived-state-no-effect`, `rendering-conditional-render`, `js-set-map-lookups`, `bundle-preload`, `bundle-dynamic-imports`, `bundle-barrel-imports`):
- Layout `resources/js/layouts/portal/entrevista-layout.tsx`: cabecera con el paso ("Pantalla 5 de 8") y una barra de avance; contenido en una columna; barra inferior fija con la acción principal.
- Páginas: `portal/entrevista/inicio.tsx`, `portal/entrevista/paso.tsx` (sirve para todos los pasos) y componentes por tipo de respuesta en `resources/js/components/entrevista/` (`pregunta-si-no.tsx`, `pregunta-usted-conyuge.tsx`, `pregunta-opciones.tsx`, `lista-casillas.tsx`, `pregunta-dato.tsx`, `persona-tarjeta.tsx`), definidos fuera de la página. Un componente por tipo y no un componente con muchos booleanos.
- El formulario usa `useForm` de Inertia con las respuestas del paso; la página recibe `key={pantalla + paso + persona}` para empezar limpia en cada paso. "Ninguna" se resuelve en el manejador del clic (desmarca las demás), no en un efecto. Las respuestas se buscan en un `Map` por código.
- Los enlaces a la pantalla siguiente y anterior se precargan (`prefetch` de Inertia). El panel de chat se carga con `React.lazy` solo cuando el cliente lo abre en el celular.
- Textos de las preguntas: `texto_es` / `texto_en` del catálogo según el idioma elegido (si falta el inglés, se usa el español); el resto de los textos en `es.json` y `en.json`.

**Pruebas:**
- `tests/Feature/Portal/EntrevistaTest.php`: un cliente sin la bandera sigue en el formulario de hoy; con la bandera y el consentimiento ve las 8 pantallas; el paso solo trae sus preguntas (no las de otras pantallas ni las ocultas); guardar la pantalla 5 con D1 y D15 marcados guarda `si` en esos y `no` en las demás y lleva al seguimiento con `D1.1`; "Ninguna" guarda todo en `no` y salta el seguimiento; un código ajeno al paso devuelve error; una persona del bloque C con C3 = padre ve C12–C14; quitar una persona la archiva.
- `npm run build` y `npm run types:check` sin errores; revisión en el navegador a 375 px de ancho.

---

## 2.6 Documentos que pide la entrevista (lista mínima y carga)

**Hoy:** los documentos se suben como un "campo" del formulario (`modo = archivo`) y se guardan en `documentos` con `forma` y `campo` obligatorios, cifrados con `DocumentoAlmacen` (F0.2) y nunca borrados (F0.3). Al subir un W-2, `RevelacionExtractorService` llena con IA otros campos (montos) del formulario.

**Problema:** las pantallas de la entrevista piden archivos (identidad en la pantalla 1, tarjeta de cada persona) y, al cambiar de flujo (2.10), el cliente necesita dónde subir el W-2 y lo demás. El checklist completo es la fase 3. Ver la pregunta 1.

**Enfoque mínimo** (reglas `arch-service-classes`, `controller-form-requests`; la fase 3 lo amplía, no lo reescribe):
- Migración: `documentos.forma` y `documentos.campo` pasan a nullable; columnas nuevas `documento_codigo` (código del catálogo del año), `entrevista_id` y `persona_id` (nulos para los documentos viejos).
- `MotorEntrevista::documentosActivados()` (2.2) da la lista: los de siempre primero (identificación y tarjetas de cada persona), luego uno por cada activación, con el nombre sencillo y el "por qué" (la pregunta que lo activó).
- Página `portal/documentos.tsx`: la lista con un estado simple (falta / recibido, con cantidad de archivos) y el botón para subir uno o varios archivos por documento. El mismo componente de carga se usa dentro de la pantalla 1 y de cada persona.
- `DocumentoEntrevistaController@store` con `SubirDocumentoRequest` (formatos del documento del catálogo, 20 MB como hoy) guarda con `DocumentoAlmacen` y `DocumentoDuplicadoService`. **No** corre la revelación con IA: la lectura por casilla es la fase 4 (los montos no se le piden al cliente, principio 4).
- Fuera de esta lista mínima (fase 3): "no aplica" con motivo, "otro documento", estados por documento, recordatorios, cliente que vuelve.

**Interfaz:** cada documento es una fila con nombre sencillo, "por qué te lo pedimos" en una línea, insignia de estado (con texto e ícono) y un botón "Subir" (o "Agregar otro"). La acción principal de la página es "Volver a la entrevista" o "Revisar y confirmar" si ya no falta nada de la entrevista. Más reglas en la tabla común después de 2.7.

**Pruebas:** D1 = sí hace aparecer el W-2 y D1 = no lo saca; una persona con C9 hace aparecer su tarjeta; subir guarda cifrado con `documento_codigo` y la persona; formato no permitido falla con el error junto a la fila; un cliente no ve ni sube documentos de otro.

---

## 2.7 Revisión y confirmación del cliente en el portal

**Hoy:** la atestación final ocurre **solo por WhatsApp**: en la fase `Cierre`, la tool `registrar_atestacion_cliente` guarda en `cliente_atestaciones` (solo se inserta) el texto literal del cliente; `AgenteToolService::atestacionVigente()` decide si sigue vigente. Casi nunca se alcanza (9.1).

**Enfoque** (reglas `controller-single-action`, `eloquent-casts`; 9.3.2: "se conserva la tabla"):
- `GET portal/entrevista/resumen`: todas las respuestas visibles por pantalla (las sensibles enmascaradas con "Mostrar", que solo ve el propio cliente), las personas y los documentos con su estado, cada respuesta con "Cambiar" que lleva a su paso.
- `POST portal/entrevista/confirmar` (`ConfirmarEntrevistaController`, invocable): exige que no falte ninguna pregunta visible (los documentos pendientes **no** bloquean: se avisa "puedes subirlos después"). Inserta en `cliente_atestaciones` (columnas nuevas `canal` = `portal`, `entrevista_id` y `resumen_hash`, un hash de las respuestas confirmadas) y pone la entrevista en `confirmada`.
- Si después cambia cualquier respuesta (por cualquier canal), el guardador la pasa a `reabierta` y el resumen pide confirmar de nuevo (mismo criterio que `atestacionVigente()` hoy).
- El agente ya no registra atestaciones: avisa y manda el enlace (2.8).

**Interfaz:** una sola acción principal, "Confirmar que mi información es correcta", al final y en la barra fija; si falta algo, ese botón se reemplaza por "Completar lo que falta" y la lista de pendientes aparece arriba con enlaces. Tras confirmar: estado de éxito en la misma página ("Recibimos tu información el 30 oct. Tu preparador la revisará").

**Pruebas:** no se confirma con preguntas pendientes; con documentos pendientes sí, con aviso; confirmar inserta la atestación con canal y hash; cambiar una respuesta reabre y vuelve a pedir confirmación; la confirmación vieja se conserva.

### Reglas de interfaz del portal (2.4 a 2.7)

| Regla | Cómo se cumple |
| --- | --- |
| **Carga (skeleton)** | El resumen (2.7), la lista de documentos (2.6) y el historial del chat llegan como props diferidas (`Inertia::defer`) con `<Deferred fallback>` hecho con `ui/skeleton` del mismo alto que el contenido. Los pasos de la entrevista son chicos y llegan con la página (sin skeleton, sin saltos). Al pasar de un paso a otro, el botón muestra "Guardando…" y la página no se vacía |
| **Responsive (mobile-first)** | Se diseña primero a 375 px: una columna, `max-w-xl` centrada en escritorio; casillas y opciones como filas completas tocables de al menos 44 px de alto; barra inferior fija con la acción principal a todo el ancho en el celular (con `pb` de área segura); el chat de dudas en un `ui/sheet` que se abre con un botón en el celular y va al costado desde `lg`. Sin scroll horizontal |
| **Jerarquía visual** | Una sola acción principal por pantalla (`Button` primario: "Continuar", "Firmar y continuar", "Confirmar"); "Atrás" y "Guardar y salir" como `variant="ghost"`. Arriba, el número de pantalla y la barra de avance; luego la pregunta como título y la ayuda debajo en `text-muted-foreground`. En el inicio, cada pantalla es una fila con estado (completa, en curso, sin empezar) para escanear de un vistazo |
| **Consistencia** | Solo componentes de `resources/js/components/ui` (`button`, `checkbox`, `label`, `input`, `select`, `toggle-group`, `card`, `badge`, `sheet`, `dialog`, `skeleton`, `spinner`, `alert`, `separator`) y `alert-error`, `input-error`, `heading`; íconos de `lucide-react` como el resto; toasts con `use-flash-toast` |
| **Accesibilidad** | Cada lista de casillas y cada pregunta de opciones es un `fieldset` con `legend` (la pregunta); cada input con `Label` asociado; "Usted / Cónyuge" con nombres completos ("Yo", "Mi cónyuge"). Errores con `input-error` enlazado por `aria-describedby` y `aria-invalid`; si el envío falla, un resumen de errores con `role="alert"` arriba y el foco va ahí. Barra de avance con `role="progressbar"` y `aria-valuenow`/`aria-valuetext` ("Pantalla 5 de 8"). Botones de solo ícono con `aria-label` que dice qué hacen ("Quitar a Persona 2", "Abrir chat de dudas", "Mostrar número de cuenta"). Foco visible (anillo de los `ui`, sin quitar `outline`). Contraste AA en claro y oscuro; los estados nunca solo por color (insignia con texto e ícono). `lang` del documento según el idioma |
| **Estados de la interfaz** | **Vacío:** pantalla 4 sin personas ("Nadie vivió contigo: puedes seguir"); documentos sin activar ("Todavía no hay documentos que pedirte: responde la entrevista"). **Error:** `alert-error` si falla un guardado o una carga diferida, con "Reintentar"; errores por campo junto al campo. **Éxito:** toast al guardar un paso; página de confirmación al terminar; insignia "Completa" en cada pantalla terminada |
| **Estados de interacción** | Hover, foco y activo de los `ui`; la fila completa de una casilla responde al toque. `disabled` cuando no aplica (por ejemplo "Firmar" sin aceptar, "Confirmar" con pendientes, con el motivo en texto). Mientras se guarda: botón deshabilitado con `ui/spinner` y "Guardando…" (`processing` de `useForm`), sin doble envío; la carga de un archivo muestra el avance (`progress` de `useForm`) |

---

## 2.8 Agente de WhatsApp reescrito sobre el catálogo (espera L3 para clientes reales)

**Hoy:**
- Fases (`FaseConversacion`): `VerificacionCuenta`, `DeterminacionFormas`, `Recoleccion`, `Cierre`, `PortalDudas` y `HandoffPortal`. `AgenteConversacionalService::responder()` **fuerza** `Recoleccion` → `HandoffPortal` en WhatsApp y `Recoleccion`/`Cierre` → `PortalDudas` en el portal, y agrega una nota efímera con el enlace (`notaLinkPortal()`).
- Tools (`ToolDefinitions`, `ToolExecutor`): `crear_cliente_taxes`, `declarar_formas_cliente`, `consultar_pendientes_cliente`, `consultar_documentos_extra`, `guardar_campo_cliente`, `consultar_base_conocimiento`, `registrar_atestacion_cliente` y `think`.
- Prompts en `prompt_actuales/fases/*.md`, publicados por versión en `agente_prompts` (una fila por fase) desde "Agente → Prompts"; `AgentePromptVigente` los cachea. El árbol de 44 pasos se compone en `determinacion_formas.md` con `ActivosPromptComposer`.
- Adjuntos: `ProcesarMensajeWhatsappJob` descarga cada archivo (`AdjuntosWhatsappService`), lo extrae con `DocumentoExtraccionService` (OpenAI) y el texto queda en el mensaje guardado; el agente puede guardarlo con `guardar_campo_cliente`. Esto contradice 9.6 ("no los recibe").

**Enfoque** (reglas `arch-service-classes`, `controller-dependency-injection`; mismo principio de hoy: **el código deriva el estado, el modelo no lo recuerda**):

- **Fases nuevas** (se agregan al enum; las viejas siguen hasta 2.11 para los clientes sin la bandera):

| Fase | Cuándo (la decide `EstadoConversacionResolver`) | Qué hace el agente | Tools |
| --- | --- | --- | --- |
| `VerificacionCuenta` | Sin cliente | Igual que hoy | `crear_cliente_taxes`, `think` |
| `Consentimiento` | Cliente con la bandera y sin consentimiento vigente | Explica qué es el §7216 y manda el enlace a `portal/consentimiento` | `consultar_base_conocimiento`, `think` |
| `Entrevista` | Consentimiento firmado, `tax.entrevista_whatsapp` encendido y quedan preguntas **no sensibles** visibles de las pantallas 2–8 | Hace la entrevista conversacional (9.6, nivel 1 y seguimientos no sensibles) | `consultar_entrevista`, `guardar_respuestas`, `agregar_persona`, `consultar_base_conocimiento`, `think` |
| `Seguimiento` | Lo demás (solo falta lo sensible o documentos, o ya terminó) | Manda el enlace a lo que falta en el portal, avisa que confirme, responde "¿cómo va lo mío?" y dudas | `consultar_entrevista`, `consultar_base_conocimiento`, `think` |
| `PortalDudas` | Chat del portal | Solo dudas, igual que hoy (sin guardar) | `consultar_entrevista`, `consultar_base_conocimiento`, `think` |

  Para un cliente **sin** la bandera, `EstadoConversacionResolver` sigue con la lógica de hoy. Con la bandera, ya no hay fases forzadas en `responder()`: el resolver recibe el canal y decide.
- **Tools nuevas** (en `ToolDefinitions` y `ToolExecutor`; todas pasan por el motor y el guardador de 2.2):
  - `consultar_entrevista`: devuelve las próximas preguntas **no sensibles** visibles (código, texto, tipo, opciones, persona), el avance por pantalla, "lo que falta en el portal" (cantidad de preguntas sensibles y documentos, nunca sus valores) y el **enlace exacto** a la pantalla del portal que corresponde. El enlace lo arma el código (como `linkPortal()` hoy), nunca el modelo.
  - `guardar_respuestas`: una lista `[{codigo, persona, valor}]`, para que "trabajo en Uber y mi esposa en Walmart" se guarde en un solo llamado (D1, D16…). El guardador rechaza lo sensible, lo no visible y lo que no corresponde a las opciones, y devuelve el error para que el modelo **pregunte de nuevo** en vez de adivinar (9.6).
  - `agregar_persona`: crea una persona del bloque C con su parentesco (C3) y una etiqueta; el nombre y la fecha de nacimiento van por el portal si se marcan como sensibles (pregunta 2).
- **Documentos por WhatsApp** (9.6, T9): para un cliente con la bandera, `ProcesarMensajeWhatsappJob` **no descarga ni extrae** el adjunto; guarda el mensaje como "[El cliente envió un archivo; no se guardó]" y el agente responde con el enlace a `portal/documentos`. Nada del archivo llega a OpenAI.
- **Datos sensibles escritos en el chat:** un `RedactorDatosSensibles` enmascara en el texto entrante los patrones de SSN/ITIN (9 dígitos con o sin guiones) y de números de cuenta largos **antes** de guardarlo en `whatsapp_mensajes` y de mandarlo a OpenAI; el agente responde que eso va en el portal.
- **Canal y hora:** cada respuesta del agente se guarda con `canal = whatsapp`, el actor del agente (`AgenteWhatsappUser`) y la hora: es el registro de la entrevista para el 8867 (7.1).
- **Prompts nuevos:** `prompt_actuales/fases/consentimiento.md`, `entrevista.md` y `seguimiento.md`; se ajustan `verificacion_cuenta.md` (al crear la cuenta pasa al consentimiento) y `portal_dudas.md`. `entrevista.md` incluye: preguntar por hechos con palabras sencillas, agrupar las casillas de una pantalla en un solo mensaje, nunca pedir lo sensible ni documentos, no decidir posiciones fiscales, y el caso del **socio de LLC o S-corp** (se atiende su K-1 personal con D18; la declaración de la empresa no). Se publican como una versión nueva desde "Agente → Prompts"; esa versión lleva también los prompts viejos sin cambios mientras conviven los dos flujos.
- **Panel del agente:** "Agente → Prompts" y "Agente → Tools" recorren `FaseConversacion::cases()`, así que muestran las fases nuevas solas. Se actualiza `AgenteToolController::FASES_ACOPLADAS` (hoy `Recoleccion` y `Cierre`) y `AnalizarConversacionesAgenteCommand` (hoy busca `Cierre`: pasa a "entrevista confirmada").
- **Si L3 dice que no:** `tax.entrevista_whatsapp = false` y el resolver nunca da `Entrevista`; el agente queda en `Seguimiento` (explica, manda enlaces, dudas, estado). No hace falta otro código.

**Pruebas (Pest, con OpenAI simulado como en las pruebas actuales del agente):**
- El resolver da cada fase en su caso; sin la bandera da las fases de hoy.
- `guardar_respuestas` guarda varias respuestas con canal `whatsapp`; rechaza A3, G3 y un código no visible sin escribir nada.
- Un adjunto de un cliente con la bandera no se descarga ni se guarda y la respuesta lleva el enlace a documentos.
- Un SSN escrito queda enmascarado en `whatsapp_mensajes` y en lo que se manda a OpenAI.
- Con `tax.entrevista_whatsapp = false` nunca se ofrece `guardar_respuestas`.
- **Prueba de María** (`tests/Feature/Entrevista/MariaTest.php`, sección 3 y 4.8): por WhatsApp responde las pantallas 2, 3, 5, 6, 7 y G1/G2/G5 (simulando las tool calls); el agente le manda el enlace; en el portal **las pantallas 2, 3, 5, 6 y 7 ya aparecen completas** y solo le quedan la pantalla 1, los datos sensibles del hijo (C9 y lo que se marque sensible), G3/G4 y los documentos; confirma. Se verifica que **ninguna respuesta se pidió dos veces** (cada código tiene una sola respuesta y ningún cambio en el historial salvo los que ella hace), que el total ronda las **21 respuestas**, y que se activaron W-2, tarjeta del hijo, recibo de la guardería, Schedule 8812, Form 2441 y EIC.
- Prueba manual con un usuario de prueba en producción (WhatsApp real) antes de pedir L3.

---

## 2.9 Vista del preparador: entrevista en la ficha y chat del portal en Mensajes

**Hoy:** la ficha (`resources/js/pages/clientes/show.tsx`, ~2.350 líneas, `ClienteController@show`) muestra `campos_cliente` por forma, con revelar SSN (`CampoReveal`, con contraseña), historial y edición del preparador (`CampoClienteController`). "Agente → Mensajes" (`AgenteMensajesController`) solo lista WhatsApp; las conversaciones del chat del portal (`PortalMensaje`) no se ven en ningún lado.

**Enfoque** (reglas `controller-resource-controllers`, `controller-form-requests`, `eloquent-eager-loading`; vercel: `server-serialization`):
- **Sección "Entrevista" en la ficha** (solo para clientes con la bandera; la ficha en pestañas es la fase 5): prop diferida `entrevista` con estado, consentimiento (versión y fecha), confirmación, respuestas por pantalla con **canal y fecha**, personas y documentos activados. Las sensibles van enmascaradas.
- **Revelar una respuesta sensible:** `POST clientes/{cliente}/respuestas/{respuesta}/revelar` con `password.confirm`, registrado en una tabla nueva `entrevista_respuesta_reveals` (`respuesta_id`, `revealed_by_id`, `ip_address`), igual que `CampoReveal` (T7).
- **Corregir una respuesta:** el preparador puede cambiarla (canal `preparador`) con `CorregirRespuestaRequest` y `ClientePolicy` (solo sus clientes); queda en el historial y reabre la confirmación. El historial de cada respuesta se abre en un `ui/sheet`.
- **Bandera:** el administrador la ve y la cambia desde la ficha (con confirmación).
- **Mensajes:** "Agente → Mensajes" suma las conversaciones del chat del portal, con un filtro por canal (WhatsApp o portal). Con esto se cumple la parte de fase 2 de 9.7 para Agente → Mensajes, Prompts y Tools.

**Interfaz:** la sección va en una `Card` con el estado arriba (insignia "En curso / Confirmada / Reabierta" y fechas) y la lista agrupada por pantalla con el código en monoespaciado, la respuesta, el canal (insignia "WhatsApp" o "Portal") y la fecha. Skeleton con `<Deferred>` mientras carga. En el celular, cada respuesta pasa a dos líneas (pregunta arriba, respuesta y canal abajo). Acciones de solo ícono con `aria-label` que nombra la pregunta ("Revelar B4", "Corregir D1", "Ver historial de G3") y `tooltip`. Vacío: "El cliente todavía no empezó la entrevista". Error: `alert-error`. Éxito: toast al corregir. Corregir abre un `ui/dialog` con el mismo componente de pregunta del portal (consistencia) y `processing` con spinner.

**Pruebas:** el preparador ve la entrevista de sus clientes y no la de otros; las sensibles van enmascaradas y revelar exige contraseña y queda registrado; corregir guarda con canal `preparador` y reabre; Mensajes lista las conversaciones del portal y filtra por canal.

---

## 2.10 Cambio de flujo y migración de `campos_cliente`

**Hoy:** 3 clientes en producción con datos en `campos_cliente` y formas declaradas; el resto son usuarios de prueba o sin datos.

**Requisitos para empezar:** 2.2–2.9 en producción y probadas con usuarios de prueba; **texto definitivo del consentimiento (L1)**. L3 **no** hace falta: si no llegó, se cambia el flujo con `tax.entrevista_whatsapp = false`.

**Enfoque** (reglas `eloquent-chunking`, `arch-service-classes`):
- Comando `php artisan catalogo-fiscal:equivalencias --migrar [--dry-run] [--cliente=]`, con el mapa de 2.3. Por cliente y año, en una transacción: crea la entrevista; escribe las respuestas con canal `migracion` vía `RespuestaGuardador`; crea las personas desde `info_dependientes`; pone `documento_codigo` (y la persona) a los `documentos` que tienen equivalente; prende la bandera. Es idempotente (salta clientes ya migrados) y **nunca imprime valores**; muestra conteos por cliente y lo que quedó sin equivalente.
- `campos_cliente`, `catalogo_campos`, `historial_cambios` y `cliente_atestaciones` **no se borran**: quedan como histórico (L12). La ficha sigue mostrando los datos viejos en solo lectura para esos clientes.
- La entrevista migrada queda `en_curso`: el cliente firma el consentimiento y **revisa y confirma** en el portal (pregunta 6).
- Al final: `tax.entrevista_catalogo_por_defecto = true` (todos los clientes nuevos entran a la entrevista) y se publica la versión del prompt sin depender de las fases viejas.
- Aviso a los 3 clientes (por WhatsApp, lo manda el agente al siguiente mensaje o el preparador): que entren al portal a revisar.

**Pruebas:** con datos de prueba de los tres tipos (empleado con dependiente, independiente, con Marketplace), la migración crea las respuestas, personas y documentos esperados; correrla dos veces no duplica; `--dry-run` no escribe; un cliente migrado ve sus respuestas en el portal con canal "migración" y su ficha conserva el histórico.

---

## 2.11 Retiro del flujo anterior (E5, E6, E12, tool y menú viejos)

**Se hace en un commit aparte, después de verificar 2.10 en producción.**

| Qué se borra | Dónde | Ajustes |
| --- | --- | --- |
| E5: prompts `recoleccion.md`, `determinacion_formas.md`, `cierre.md` y `handoff_portal.md`; tools `guardar_campo_cliente`, `declarar_formas_cliente`, `consultar_pendientes_cliente` y `registrar_atestacion_cliente` | `prompt_actuales/fases`, `ToolDefinitions`, `ToolExecutor`, `AgenteToolService` (queda `crearCliente`) | Fases `DeterminacionFormas`, `Recoleccion`, `Cierre` y `HandoffPortal` salen del enum. **Antes**, una migración borra sus filas en `agente_tool_estados` (`AgenteToolEstados` hace `FaseConversacion::from()` y fallaría con un valor viejo) y se revisa cualquier otro `FaseConversacion::from()` sobre datos guardados (pasa a `tryFrom`). Las filas viejas de `agente_prompts` quedan como historial |
| E6: árbol de 44 pasos | `PromptActivoStep`, `PromptActivoStepsSeeder`, `ActivosResolver`, `ActivosPromptComposer`, tabla `prompt_activo_steps` | Es configuración, no datos de clientes: se borra la tabla |
| E12: pseudoformas y `consultar_documentos_extra` | `CampoCatalogo::pseudoFormas()`, `TaxFieldCatalog::formaAlmacen()`, rutas de escritura de `EventoRecoleccionService`, `EventoRequest`, `CampoClienteUpdateRequest`, `AgenteToolService::documentosExtra()` | Lo que **lee** el histórico (ficha, exportación, motor de cálculo) sigue funcionando. `/api/catalogo/documentos-extra` se va con E3 (espera T-a) |
| Formulario del portal | `PortalFormularioController`, `portal/formulario.tsx`, `ocultosPorDependencia()`, `RevelacionExtractorService` en el portal, carga de archivos del chat | `GET portal` va siempre a la entrevista |
| Menú viejo "Catálogo" | `CatalogoController`, `CatalogoPolicy`, `routes/catalogo.php`, `pages/catalogo/index.tsx`, entrada del sidebar | El inspector de `CampoDerivationLog` se va con él |
| Bandera | `users.entrevista_catalogo`, `tax.entrevista_catalogo_por_defecto`, los `if` de convivencia | Todos usan la entrevista |

**Pruebas:** la suite completa pasa (se borran las pruebas del flujo viejo y se ajustan las que lo usaban); `npm run build` y `types:check`; Agente → Prompts y Tools abren sin errores con las filas viejas en la base; la ficha de un cliente migrado sigue mostrando su histórico.

---

## Migración de datos existentes

| Qué | Cómo | Se puede repetir |
| --- | --- | --- |
| Tablas nuevas (2.2, 2.4, 2.9) | Migraciones que solo crean tablas | — |
| `users.entrevista_catalogo` (2.2) | Columna con falso por defecto | — |
| Contenido nuevo del catálogo (2.2) | Archivos de datos + `catalogo-fiscal:cargar 2025` y `2026` | Sí (idempotente) |
| Textos del consentimiento (2.4) | `consentimientos:cargar` | Sí |
| `documentos` (2.6) | `forma` y `campo` a nullable; columnas nuevas nulas | — |
| `cliente_atestaciones` (2.7) | Columnas nuevas nulas (`canal`, `entrevista_id`, `resumen_hash`) | — |
| `campos_cliente` → respuestas (2.10) | `catalogo-fiscal:equivalencias --migrar`, corrido a mano | Sí (salta lo migrado) |
| Retiro (2.11) | Borrar `agente_tool_estados` de fases viejas y la tabla `prompt_activo_steps` | — |

Ningún dato de clientes se borra en esta fase.

## Despliegue

Por cada subtarea:
1. Push a `main` y **verificar las migraciones** (`php artisan migrate:status` en el contenedor del frontend: el entrypoint se traga los errores).
2. Si cambió el catálogo: `catalogo-fiscal:cargar 2025 --dry-run`, luego sin `--dry-run`; lo mismo para 2026; `catalogo-fiscal:verificar 2025` y `2026` sin errores.
3. Si cambiaron los prompts: publicar la versión nueva desde "Agente → Prompts" y revisar una conversación de prueba.
4. **Frontend y worker con el mismo código** (el worker corre el agente y los adjuntos de WhatsApp).
5. Probar con un usuario de prueba con la bandera (portal en el celular y WhatsApp real) y confirmar que un cliente real sin la bandera sigue igual.

Para 2.10, además: `consentimientos:cargar` con el texto de L1; `equivalencias --reporte`; `--migrar --dry-run`; `--migrar`; revisar en la ficha los 3 clientes; cambiar `tax.entrevista_catalogo_por_defecto`. Para 2.11: verificar Agente → Prompts y Tools después del deploy.

## Listo cuando

- Un cliente firma el consentimiento §7216 en el portal y queda guardado con texto, versión, fecha y hora (con el texto de L1 para clientes reales).
- La entrevista se responde en el portal en las 8 pantallas, con ingresos en un solo paso de casillas y seguimientos solo cuando aplican; las condiciones y activaciones salen del catálogo.
- **María** completa la entrevista en ≈ 21 respuestas, empezando por WhatsApp y terminando en el portal sin repetir nada (prueba automática y prueba manual).
- El agente nunca guarda una pregunta sensible ni un documento, y lleva al portal con el enlace correcto.
- El cliente confirma en el portal; un cambio posterior pide confirmar de nuevo.
- El preparador ve las respuestas con canal y fecha, revela las sensibles con registro y puede corregirlas.
- Un socio de LLC o S-corp con K-1 se atiende (D18 → Schedule E), sin prometer la declaración de la empresa.
- Los preparadores pueden marcar piezas del catálogo como validadas.
- Los clientes de `campos_cliente` están migrados y el flujo viejo (E5, E6, E12, tool y menú viejos) ya no existe.
- La suite de Pest pasa completa, Pint sin cambios, `npm run build` y `types:check` compilan.

## Fuera de esta fase

- Checklist completo de documentos: estados, "no aplica" con motivo, "otro documento", recordatorios con plantillas, cliente que vuelve (fase 3; 5.1).
- Leer documentos por casilla y prellenar la pantalla 1 desde la identificación (fase 4).
- Conclusiones de nivel 3 (estado civil para el IRS, quién es dependiente, qué créditos aplican): las saca el preparador; la plataforma las calcula en las fases 5 y 6. El motor de cálculo (`DeterminacionFiscalService`) sigue leyendo `campos_cliente`: para los clientes nuevos no tendrá datos hasta la fase 4/6 (ver pregunta 8).
- "Mi declaración" del cliente, ficha en pestañas, expedientes por estado y reemplazo de "Mi información" (fase 5).
- Precarga del año anterior ("¿cambió algo?") y meta-agente sobre el chat del portal (fase 7).
- Editar activaciones desde la pantalla del catálogo (se suman cuando haga falta; la entrevista ya acepta seguimientos con solo su condición).
- E3 (API externa, con `/api/catalogo/documentos-extra` y `ClientIntakeSession`): espera T-a.
- MFA para clientes: espera L9.

## Preguntas para Steven

1. **Documentos antes del checklist (2.6).** Las pantallas piden archivos y, al cambiar de flujo, el cliente necesita dónde subir el W-2. ¿Hacemos en F2 una **lista mínima** de documentos activados con carga (sin "no aplica", estados ni recordatorios), o el cambio de flujo (2.10) espera a la fase 3? **Recomiendo la lista mínima**: es chica, la fase 3 la amplía sobre las mismas columnas y F2 se puede cerrar como pide el checklist.
2. **Qué es sensible.** El catálogo marca A3, A12, B4, C9, C10, G3 y G4, pero 9.6 también nombra **nombres y fechas de nacimiento de los dependientes**. ¿Marcamos C1 y C2 como sensibles? **Recomiendo que sí** (se cambia desde la pantalla del catálogo, sin código): el agente pregunta cuántas personas, parentesco y meses (C3, C4) y el portal pide nombre, fecha y número. La pantalla 1 (A1–A4) ya va solo por el portal.
3. **Orden de las pantallas 2 y 3.** La pantalla 2 pregunta "a ti o a tu cónyuge" antes de saber si está casado (pantalla 3). ¿Ponemos primero el estado civil? **Recomiendo invertirlas** (es cambiar `pantalla` en los datos): así "Mi cónyuge" solo aparece si está casado y el soltero ve una lista más simple.
4. **Bandera por cliente.** ¿Convivencia con una bandera por cliente (encendida solo a usuarios de prueba hasta 2.10) o cambio de una vez para todos? **Recomiendo la bandera**: deja probar en producción con WhatsApp real sin tocar a los clientes reales.
5. **Año de la entrevista.** Hoy el agente confirma el año con el cliente. ¿La entrevista usa siempre `tax.current_tax_year` (2025 ahora) y otros años los abre un preparador más adelante? **Recomiendo que sí**: una pregunta menos y no hay clientes de años atrasados en el piloto.
6. **Clientes migrados.** ¿Deben firmar el consentimiento y **confirmar de nuevo** en el portal, aunque ya hayan dado sus datos? **Recomiendo que sí**: sus datos se dieron sin consentimiento §7216 y la confirmación por WhatsApp casi nunca ocurrió.
7. **WhatsApp antes de L3.** El agente de hoy ya pregunta por WhatsApp. Mientras L3 no responda, ¿la entrevista por WhatsApp queda **apagada para clientes reales** (`tax.entrevista_whatsapp = false`: el agente explica y manda el enlace) y encendida solo para pruebas? **Recomiendo apagada** para clientes reales: es la opción sin riesgo legal y no frena el resto.
8. **Cálculos para clientes nuevos.** Con la entrevista nueva, el panel de determinación fiscal (que lee `campos_cliente`) queda vacío para los clientes nuevos hasta las fases 4 y 6, y "Mi información" se esconde para ellos. ¿Aceptable? **Recomiendo aceptarlo**: los cálculos de hoy no están validados (fase 6) y el preparador calcula en TaxWise.
9. **Contenido nuevo del catálogo.** ¿Apruebas sumar `C0` (¿alguien vivió contigo?), `D1.1` (cuántos empleos), `F4.1` (cuántas pólizas), `D18` + documento `k-1` (socio con K-1) y el operador `hasta` para que C11–C14 aparezcan también a quien vivió 6 meses o menos? **Recomiendo que sí**, cargados como "sin validar" para revisarlos en la pantalla del catálogo.

## Decisiones de Steven (30 oct.)

1. **Documentos (2.6):** sí, lista mínima de documentos activados con carga en F2. **Todos los documentos se piden y se suben solo por el portal, nunca por WhatsApp** (por lo sensible y por §7216): un archivo enviado por WhatsApp no se descarga, no se guarda y no llega a OpenAI; el agente responde con el enlace a los documentos del portal.
2. **Sensibles:** sí, C1 y C2 (nombre y fecha de nacimiento de los dependientes) se marcan como sensibles.
3. **Pantallas 2 y 3:** sí, se invierten (primero el estado civil).
4. **Bandera por cliente:** sí; solo usuarios de prueba hasta 2.10.
5. **Año de la entrevista:** siempre `tax.current_tax_year`; otros años los abre un preparador más adelante.
6. **Clientes migrados:** firman el consentimiento y confirman de nuevo en el portal.
7. **Entrevista por WhatsApp:** **apagada para clientes reales hasta que responda L3** (`tax.entrevista_whatsapp = false`). Los usuarios de prueba sí la tienen, para construirla y probarla.
8. **Cálculos:** se acepta el panel de cálculos vacío para los clientes del flujo nuevo hasta F4/F6, y "Mi información" escondida para ellos.
9. **Contenido nuevo del catálogo:** sí a C0, D1.1, F4.1, D18 con el documento `k-1` y el operador `hasta`, cargados como "sin validar".
10. **Primera entrada:** el primer enlace del agente por WhatsApp sirve para crear la contraseña (sin pasar por el correo, que queda de respaldo); después, cambiar la contraseña solo por correo. Va en 2.4.
11. **Verificación en dos pasos para clientes:** opcional (la pueden activar en Ajustes → Seguridad), no obligatoria. Sujeta a la respuesta del abogado sobre L9.
