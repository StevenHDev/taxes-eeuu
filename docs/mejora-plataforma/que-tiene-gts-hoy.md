# Qué tiene GTS hoy — validación de la plataforma

26 oct. 2026 · Tarea Wipli (Cupperlab) "2. Steven: levantar lo que GTS ya tiene hoy"

> **Objetivo:** validar cómo está hoy la plataforma `taxes-eeuu` para mejorarla. Por cada punto del checklist de la tarea se revisa el código y se responde:
> - **Cómo funciona hoy**
> - 🐞 **Qué está mal**: defectos o comportamientos que no sirven
> - 🗑️ **Qué se elimina**: no se usa o no se necesita
> - ➕ **Qué se agrega**: lo que falta
>
> Base: el código de `main` al 26 oct. (último cambio funcional: 16 oct.) y `docs/mejora-plataforma/analisis-plataforma-vs-proceso-negocio.md`.

| # | Punto del checklist | Estado |
| --- | --- | --- |
| 1 | Proceso de captura, de la primera solicitud a la presentación | ✅ Revisado |
| 2 | Herramientas en uso | ✅ Revisado |
| 3 | Volumen de clientes por tipo | ✅ Revisado |
| 4 | Formatos y checklists que se piden al cliente | ✅ Revisado ⭐ prioridad |
| 5 | Horas por declaración y dolores | ✅ Revisado |

---

## 1. Proceso de captura en la plataforma

### 1.1 Cómo funciona hoy

La fase de la conversación no se guarda: se deduce cada vez de los datos del cliente (`EstadoConversacionResolver`). El flujo real es este:

| # | Paso | Canal | Qué pasa | Código |
| --- | --- | --- | --- | --- |
| 1 | Verificación de cuenta | WhatsApp | El agente pide nombre y correo, valida el correo y crea el usuario (rol `client`). Le llega un correo de bienvenida para definir la contraseña del portal | `crear_cliente_taxes`, `BienvenidaClientePortal` |
| 2 | Determinación de formas | WhatsApp | Un árbol de preguntas decide el año fiscal y qué formas aplican (1040, Schedule C/E/F, 1065, 1120, 1120-S, 1041, 990, 1040-NR) | `determinacion_formas.md`, `declarar_formas_cliente` |
| 3 | Entrega del enlace | WhatsApp | El agente envía el enlace al portal una sola vez. Desde el 24 sep. ya no guarda datos ni recibe documentos: hay una circular que prohíbe la información sensible por WhatsApp | fase `HandoffPortal` |
| 4 | Formulario | Portal | Una sola página larga: campos transversales, grupos plegables y una sección por forma. Cada documento subido pasa por extracción con IA (texto del PDF o visión) y llena los campos que "revela". Se detectan duplicados por hash. El cliente puede previsualizar lo que subió | `PortalFormularioController`, `formulario.tsx` |
| 5 | Dudas | Portal | Un chat al lado del formulario que solo responde dudas, no guarda datos. Si el cliente aún no tiene formas declaradas, el portal lo manda al chat | fase `PortalDudas` |
| 6 | Cierre y atestación | **Solo WhatsApp** | Cuando no quedan campos obligatorios, el agente de WhatsApp pide la confirmación "completo y correcto" y la registra | fase `Cierre`, `registrar_atestacion_cliente` |
| 7 | Revisión | Panel del preparador | Lista de clientes → expediente: editar campos (con auditoría), revelar datos sensibles, "marcar revisado" por forma, calcular la determinación fiscal, nivel de riesgo, exportar ZIP, tomar el control de WhatsApp | `clientes/show.tsx` |
| 8 | Salida | — | **No existe.** El preparador digita en TaxWise a mano | — |

Además, el cliente tiene una vista **"Mi información"** (`mi-informacion.tsx`) con sus datos por forma y las determinaciones fiscales calculadas.

### 1.2 🐞 Qué está mal

**1. El cierre se bloquea para un cliente soltero o sin W-2.** *(Grave. Verificado con una prueba temporal contra el catálogo real.)*
- `info_conyuge` y `mas_w2` son campos **obligatorios**.
- El portal los **oculta** cuando no aplican: un soltero no ve cónyuge, y quien no tiene W-2 no ve "¿tienes otro W-2?" (`ocultosPorDependencia`).
- Un campo obligatorio **no se puede marcar "no aplica"** (`EventoValidator`).
- Resultado: siempre queda un obligatorio pendiente. El cliente nunca llega a la fase `Cierre`, no puede hacer la atestación y su forma nunca queda "completa". Esto afecta a **todo independiente puro**, que es el segmento foco.
- Lo mismo pasa con `millaje`, `activos` y `costo_ventas`: son obligatorios en el Schedule C aunque el independiente no tenga vehículo, equipos ni inventario.
- Causa de fondo: las condiciones de "aplica / no aplica" viven solo en el portal. El cálculo de "qué falta" (`TaxFieldCatalog::pendientesPara`) no las conoce. Es el punto 4 del análisis del 21 oct. ("condiciones en dos lugares"), que aquí ya rompe el flujo.

**2. En la práctica, la atestación no se alcanza.**
- Solo existe en WhatsApp, pero el cliente llena todo en el portal.
- Al terminar, el portal dice "Ya completaste todo lo obligatorio" y **no le indica que vuelva a WhatsApp** para confirmar.
- WhatsApp no escribe por su cuenta: solo responde.
- Ni el cliente ni el preparador ven la atestación en ninguna pantalla: no hay ninguna referencia a ella en el frontend.

**3. El preparador no se entera de que un cliente terminó.**
- La única notificación del sistema es el correo de bienvenida.
- No existe el estado del caso. El preparador tiene que entrar a cada expediente para ver el avance.

**4. El cliente ve cálculos fiscales sin revisar.**
- "Mi información" le muestra las determinaciones del motor: impuesto, créditos, liquidación.
- El motor nunca se ha comparado contra TaxWise y le faltan reglas (EITC, ganancias de capital, SALT).
- El cliente puede ver un saldo a pagar o a favor equivocado antes de que lo revise una persona.

**5. Las formas se deciden antes de conocer al cliente, y no se pueden cambiar desde el portal.**
- La determinación de formas ocurre en el chat, antes del formulario.
- Si la situación del cliente cambia, el chat del portal le dice que vuelva a WhatsApp, y el formulario no tiene cómo ajustarlo.
- Esto obliga a ir y volver entre los dos canales.

**6. Hay dos vistas del cliente que se solapan.** "Mi información" y "Formulario" muestran los mismos datos con propósitos que no están claros.

**7. Los documentos viven en el disco del servidor compartido, no en S3.** *(Verificado en producción el 26 oct.)*
- `AWS_BUCKET` está vacío y `FILESYSTEM_DISK=local`.
- Los documentos y la base de conocimiento se guardan en el volumen Docker `gts_documentos_shared`, que montan el frontend y el worker.
- Ya no se pierden al reiniciar: el volumen es persistente, y eso corrige el incidente del 21 sep.
- Pero quedan en el mismo servidor que aloja otros proyectos, sin respaldo fuera del servidor. Para SSN, W-2 y extractos bancarios no basta.

**8. A un independiente puro no se le pregunta nada del Form 1040.** El detalle está en 3.2.1.

**Dato de producción que confirma el punto 1:** hay **2 clientes**, **0 atestaciones** y 2 documentos subidos.

### 1.3 🗑️ Qué se elimina

| Qué | Por qué | Dónde |
| --- | --- | --- |
| Lectura de conversaciones en Supabase (n8n) | El agente ya migró de n8n a Laravel; solo queda como historial de lectura | `SupabaseWhatsappConversationService`, `ClienteController` |
| Sesiones del agente externo | Eran del agente de n8n | `ClientIntakeSession` y su uso en `EventoRecoleccionService` |
| API para el agente externo | Nada interno la usa. ❓ Antes de borrarla hay que confirmar que ningún sistema externo tiene un token de Sanctum activo | `POST /api/eventos`, `/api/catalogo/documentos-extra`, `/api/clientes/*`, `docs/api.md`, `docs/openapi.yaml` |
| Mecanismo de "documentos extra" | Solo queda un documento ahí (1098-T), y ese ya se pregunta por `gastos_educacion` | `CampoCatalogo::DOCUMENTOS_EXTRA`, tool `consultar_documentos_extra` |
| Fase de recolección en WhatsApp y `guardar_campo_cliente` en el chat | WhatsApp ya no recolecta: la fase `Recoleccion` siempre se convierte en `HandoffPortal`. La tool solo sigue viva en `Cierre`, donde permite que el cliente mande datos sensibles por WhatsApp, justo lo que prohíbe la circular | `ToolDefinitions::paraFase`, `recoleccion.md` |
| Prompts viejos | Nada en el código los carga | `prompt_actuales/promptBase.md`, `prompt_actuales/especialista_recoleccion.md`, `docs/prompt.md` |
| Página de bienvenida | `/` redirige al login o al dashboard; nadie la muestra | `resources/js/pages/welcome.tsx` |
| Formularios de compañías y 1040-NR en la determinación | El alcance es persona natural. Se pueden ocultar primero y borrar después | 1065, 1120, 1120-S, 1041, 990, 1040-NR en `TaxForm` y en el árbol de determinación |

### 1.4 ➕ Qué se agrega

| # | Qué | Para qué |
| --- | --- | --- |
| 1 | **Una sola definición de "aplica / no aplica"**, que usen el portal, el chat y el cálculo de pendientes | Arregla el bloqueo del cierre (1.2.1) y evita que el portal y el chat vuelvan a divergir |
| 2 | **Atestación en el portal** al terminar el formulario, visible en el expediente del preparador | Que el cierre ocurra donde está el cliente (1.2.2) |
| 3 | **Estado del caso** visible para el cliente y el preparador (Nuevo → En entrevista → Esperando documentos → En revisión → Aprobado → Presentado) | Seguimiento y cola de trabajo |
| 4 | **Aviso al preparador** cuando un cliente termina, y cola filtrada por estado | Que nadie tenga que revisar expedientes uno por uno (1.2.3) |
| 5 | **Acuse de recibo** de cada documento ("recibimos tu W-2 el 12/02"), en el portal o por correo | Evidencia de qué entregó el cliente y cuándo |
| 6 | **Recordatorios de pendientes** al cliente (plantilla de WhatsApp o correo) | Hoy nadie los persigue |
| 7 | **Ajustar la situación desde el portal** (agregar o quitar una fuente de ingreso) sin volver a WhatsApp | Elimina la ida y vuelta (1.2.5) |
| 8 | **Ocultar al cliente los cálculos** hasta que el preparador los apruebe | Evita mostrar montos sin revisar (1.2.4) |
| 9 | **Salida hacia TaxWise**: exportación en el formato que acepte, o al menos una hoja ordenada por formulario y línea | Quitar la digitación manual. Bloqueado hasta definir el mecanismo con el proveedor |

---

## 2. Herramientas en uso

### 2.1 Cómo funciona hoy

**Servicios externos e infraestructura**

| Para qué | Herramienta | Cómo se usa | Código / config |
| --- | --- | --- | --- |
La columna **Producción** se verificó por SSH el 26 oct., en solo lectura y sin ver credenciales.

| Para qué | Herramienta | Cómo se usa | Producción |
| --- | --- | --- | --- |
| WhatsApp | **Twilio** o **Meta Cloud API** | Están implementados los dos proveedores (`app/Services/Whatsapp/{Twilio,Meta}`); se elige con `WHATSAPP_PROVIDER` | **Twilio**: la variable está vacía, así que usa el valor por defecto. Meta no tiene credenciales |
| Agente conversacional | **OpenAI** (`OPENAI_MODEL`) | Chat de WhatsApp y chat de dudas del portal, con tools por fase | `gpt-5.6-luna` |
| Extracción de documentos | **smalot/pdfparser** → **pdftoppm** + **OpenAI visión** (`OPENAI_VISION_MODEL`) | Si el PDF tiene texto, se lee directo. Un PDF escaneado se convierte a imagen y la IA lo transcribe. Cualquier otro archivo va directo a visión | `gpt-5.6-luna`. Solo hay 2 documentos y **ninguno tiene método de extracción registrado**: la extracción no se ha probado en producción |
| Meta-agente | **OpenAI** (`META_AGENTE_MODEL`) | Todos los días a las 03:00 analiza las conversaciones de WhatsApp y deja hallazgos para mejorar el prompt | Funciona: el alcance está en "actividad" (valor por defecto). Hay 7 reportes; el último es del 17 oct. |
| Correo | Laravel Mail, SMTP | Solo el correo de bienvenida (definir contraseña) | ✅ SMTP `mail.ainoa.app` con credenciales. Un solo envío falló, el 24 sep.: el correo traía caracteres de control. Se corrigió ese mismo día validando el correo (`7c984a8`) |
| Almacenamiento | Disco local o **AWS S3** | S3 se usa cuando existe `AWS_BUCKET`; si no, el disco local | **Disco local** en el volumen persistente `gts_documentos_shared` (ver 1.2.7). S3 sin configurar |
| Tiempo real | **Laravel Reverb** + Echo | El preparador ve llegar en vivo los mensajes de WhatsApp | Contenedor `global-tax-reverb` activo |
| Cola y tareas programadas | Cola + bucle de `schedule:run` | Procesa la extracción y el meta-agente | Cola en **Redis** |
| Autenticación | **Fortify** (contraseña, 2FA, passkeys) + **Sanctum** (tokens de API) | Portal y panel; API del agente externo | — |
| Historial anterior | **Supabase** | Lectura del historial de conversaciones de n8n | Las credenciales de Supabase **siguen configuradas** |

**Paneles internos**

| Panel | Para qué | ¿Se usa? |
| --- | --- | --- |
| Catálogo | Editar campos y documentos por año | Sí. Es el núcleo |
| Usuarios | Alta de preparadores y administradores | Sí |
| Bitácora | Auditoría de acciones | Sí |
| Agente → Mensajes, Prompts, Tools | Ver conversaciones, editar y publicar prompts, encender o apagar tools por fase | Sí |
| Agente → Base de conocimiento | Subir PDFs que el agente consulta para responder dudas | **No.** En producción está **vacía** (0 documentos) |
| Meta-agente | Ver los análisis diarios y lanzarlos a mano | Sí, pero solo sobre WhatsApp (ver 2.2.2). En producción hay 28 mensajes de WhatsApp y 4 del portal |
| Proceso | Diagrama del proceso de GTS (`docs/proceso-global-tax.html`) | Desactualizado (ver 2.2.3) |
| API docs | Documentación de la API del agente externo | Solo si la API sigue viva (ver 1.3) |

**Conectores con herramientas de GTS**

| Herramienta | Estado |
| --- | --- |
| TaxWise | ❌ No existe. Bloqueado: falta definir el mecanismo (API o archivo) con el proveedor |
| QuickBooks | ❌ No existe |
| Excel / CSV | 🟡 Se aceptan como archivo en estados bancarios, gastos y activos, pero no se leen (ver 2.2.1) |

### 2.2 🐞 Qué está mal

**1. Excel, CSV y fotos de iPhone (HEIC) se aceptan, pero la extracción no puede leerlos.**
- `DocumentoExtraccionService` manda a la IA de visión todo lo que no sea PDF.
- La IA de visión de OpenAI solo acepta PNG, JPEG, WEBP y GIF.
- Resultado: el estado bancario en Excel del independiente y la foto del W-2 tomada con iPhone no se extraen.
- Puede que el cliente vea el archivo como "recibido" sin que se haya leído ningún dato.
- ❓ Hay que verificarlo subiendo un `.xlsx` y un `.heic` en un ambiente de prueba. Producción no sirve como evidencia: solo tiene 2 imágenes, ninguna con extracción registrada.

**2. El meta-agente solo analiza WhatsApp.**
- Lee solo `WhatsappMensaje`; nunca lee el chat del portal (`PortalMensaje`).
- Desde el 24 sep., la conversación que importa ocurre en el portal, así que el meta-agente analiza justo la parte que ya casi no tiene contenido.
- En producción el alcance está en "actividad", así que sí corre. Si algún día se cambia a "cierre", no analizaría nada, porque casi nadie llega a `Cierre` (1.2.1).

**3. El diagrama del panel "Proceso" está desactualizado.** Es del 17 sep., antes del portal seguro (24 sep.). Muestra la recolección por WhatsApp, que ya no existe.

**4. ~~El correo puede no estar saliendo~~ → descartado.** Producción usa SMTP (`mail.ainoa.app`). El único fallo (24 sep.) ya está corregido.

**5. Se mantienen dos proveedores de WhatsApp.** Twilio y Meta duplican el código, las pruebas y la configuración. Producción usa **Twilio**; Meta no tiene credenciales.

**6. La base de conocimiento está vacía.**
- En producción tiene 0 documentos, así que la tool `consultar_base_conocimiento`, que se ofrece en todas las fases, nunca devuelve nada.
- El agente responde dudas fiscales solo con lo que sabe el modelo, sin fuente.
- Además, solo admite PDFs con texto, la búsqueda es simple y no hay enlaces a irs.gov, que el documento de proceso pide como fuente de la verdad.

### 2.3 🗑️ Qué se elimina

| Qué | Por qué | Dónde |
| --- | --- | --- |
| Configuración y credenciales de Supabase | Va junto con la lectura del historial de n8n (1.3). Las credenciales siguen cargadas en producción; hay que quitarlas también de allá | `config/services.php` (`supabase`), `VITE_SUPABASE_*`, `WHATSAPP_SUPABASE_TABLE_PATTERN` |
| Proveedor **Meta** de WhatsApp | Producción usa Twilio y Meta no tiene credenciales | `app/Services/Whatsapp/Meta`, `config/services.php` (`meta`), `META_*` |
| `laravel/chisel` como dependencia de producción | Es una herramienta de *scaffolding* del starter kit (solo marca bloques en `types/auth.ts`). Se pasa a `require-dev` o se quita | `composer.json` |
| Panel "API docs" y tokens de Sanctum | Solo si se elimina la API del agente externo (1.3) | `ApiDocumentationController`, `docs/api.md`, `docs/openapi.yaml` |
| Panel "Proceso" | Se elimina o se regenera; tal como está, confunde | `ProcesoController`, `docs/proceso-global-tax.html` |

### 2.4 ➕ Qué se agrega

| # | Qué | Para qué |
| --- | --- | --- |
| 1 | **Lectura de Excel y CSV** (estados bancarios, gastos) sin pasar por visión | Es la base de la reconstrucción de ingresos de independientes, el segmento foco |
| 2 | **Conversión de HEIC a JPEG** antes de la extracción | Las fotos de iPhone son el caso más común de documento subido desde el celular |
| 3 | **Aviso al preparador cuando una extracción falla**, y un estado "no se pudo leer" visible para el cliente | Que un documento no quede "recibido" sin datos (2.2.1) |
| 4 | **Conector con TaxWise** (exportación) | Quitar la digitación manual. Bloqueado por el proveedor |
| 5 | **Meta-agente sobre el chat del portal** | Analizar la conversación donde realmente está el cliente (2.2.2) |
| 6 | **Plantillas aprobadas de WhatsApp** para mensajes que salen sin que el cliente escriba primero | Recordatorios de pendientes y aviso de "falta tu confirmación" (1.4.6). Fuera de la ventana de 24 h, WhatsApp no permite escribir sin plantilla |
| 7 | **Contenido para la base de conocimiento** (publicaciones del IRS en español, guía de Daniela) con enlaces a irs.gov. Si no se va a cargar, se quita la tool | Respuestas con fuente oficial, como pide el documento de proceso (2.2.6) |
| 8 | **Almacenamiento cifrado fuera del servidor** (S3 u otro) con respaldo | Hoy los documentos sensibles están en el disco de un servidor compartido, sin respaldo externo (1.2.7) |
| 9 | **Importación de reportes de QuickBooks** (P&L exportado) | ❓ Solo si GTS confirma que sus clientes lo usan |

## 3. Volumen de clientes por tipo

### 3.1 Cómo funciona hoy

**Cómo se clasifica a un cliente:** solo por las **formas declaradas** en WhatsApp (`determinacion_formas.md`, pasos A a C). No existe un campo "tipo de cliente".

| Respuesta del cliente (paso A) | Forma que se le asigna |
| --- | --- |
| a) Empleado, jubilado o con inversiones | `form_1040` |
| b) Independiente o negocio propio sin socios | `schedule_c` |
| c) Socio o dueño de empresa | `form_1065`, `form_1120` o `form_1120_s` |
| d) Alquiler, regalías o participación en una sociedad | `schedule_e` |
| e) Agricultura | `schedule_f` |
| f), g), h) Fideicomiso, sin fines de lucro, no residente | `form_1041`, `form_990`, `form_1040_nr` |

El paso C agrega más formas si el cliente tiene otras fuentes ("¿algo más aparte de eso?").

**Qué se puede medir hoy**

| Dónde | Qué muestra |
| --- | --- |
| Dashboard (preparador y admin) | Total de clientes; cuántos sin iniciar, en progreso y completos; % de campos recibidos; % de formas completas; **distribución por forma**; pendientes de revisar; casos de alto riesgo; actividad por día. Por año fiscal; el preparador solo ve sus clientes |
| Lista de clientes | Filtros por estado, nivel de riesgo y forma |

**Producción:** 2 clientes de prueba. **No hay línea base:** el volumen real de GTS hoy solo existe en TaxWise.

### 3.2 🐞 Qué está mal

**1. A un independiente puro no se le asigna el Form 1040.** *(Grave.)*
- La opción b) asigna solo `schedule_c`, y lo mismo pasa con d) → `schedule_e` y e) → `schedule_f`.
- Pero toda persona natural presenta el 1040; los schedules son anexos.
- El formulario solo pregunta los campos de las formas declaradas (`pendientesPara`). Entonces, a un independiente **nunca se le piden los campos del 1040**: cuenta bancaria para el reembolso, deducciones, pagos estimados, distribución del Marketplace, retenciones, cuidado de dependientes, educación, ingresos del extranjero.
- Además, el portal oculta el campo del W-2 si no hay `form_1040`. Un independiente que también fue empleado solo lo ve si en el paso C dijo explícitamente "también soy empleado".
- Este defecto también afecta la captura (ítem 1) y el catálogo (ítem 4).

**2. No existe "tipo de cliente". Lo que hay cuenta formas, no personas.**
- La opción a) junta en el mismo `form_1040` a empleados, jubilados e inversionistas, así que no se pueden distinguir.
- La "distribución por forma" cuenta formas: un cliente con 1040 y Schedule C aparece dos veces.
- No hay forma de responder "¿cuántos clientes son solo W-2, cuántos independientes y cuántos mixtos?".

**3. Los indicadores de avance están distorsionados por el bloqueo del cierre (1.2.1).**
- Como las formas de un soltero o de un cliente sin W-2 nunca quedan "completas", el dashboard muestra menos clientes completos y menos % de formas completas de los reales.
- **"Pendientes de revisar" queda vacío**: solo lista formas completas sin revisar, así que el preparador no tiene cola.

**4. El "% de campos recibidos" es aproximado.**
- El numerador cuenta todos los campos recibidos, también los opcionales.
- El denominador solo cuenta los obligatorios, y suma los campos transversales una vez por cada forma.
- El porcentaje se infla y llega al 100 % antes de tiempo.

**5. No se pueden contar los clientes informales.**
- El intake de GTS pregunta "¿recibió ingresos en efectivo?" y "¿tuvo ingresos de negocio aunque no haya recibido un 1099?".
- El catálogo no tiene esas preguntas como campo; solo el paso del 1099-K menciona Zelle y Venmo.

**6. Faltan dimensiones básicas:**
- Cliente nuevo o recurrente: no se marca "primera vez que declara" ni se enlaza con el año anterior.
- Canal de origen: WhatsApp o creado por el preparador.
- Carga por preparador.

### 3.3 🗑️ Qué se elimina

| Qué | Por qué |
| --- | --- |
| Formas de compañías y 1040-NR en el filtro de clientes y en la distribución por forma | Mismo motivo que 1.3: el alcance es persona natural, y hoy ensucian los conteos |

### 3.4 ➕ Qué se agrega

| # | Qué | Para qué |
| --- | --- | --- |
| 1 | **Form 1040 siempre** como base de toda persona natural; Schedule C, E y F como anexos que se suman | Corrige 3.2.1: que al independiente se le pida todo lo del 1040 |
| 2 | **Perfil de ingresos del cliente** derivado de sus fuentes: solo W-2, independiente, mixto, jubilado, inversiones, rentas | Contar personas por tipo, no formas |
| 3 | **Preguntas de ingresos informales** (efectivo, apps de pago, ingresos sin 1099) como campos | Medir a los informales y activar la reconstrucción de ingresos |
| 4 | **Nuevo o recurrente** ("¿primera vez que declara?", enlace con el año anterior) y **canal de origen** | Medir la captación de clientes nuevos, que es el objetivo del dueño |
| 5 | **Carga por preparador** en el dashboard del admin | Repartir el trabajo |
| 6 | **Exportar el resumen** (CSV) | Reportes fuera de la plataforma |
| 7 | **Línea base desde TaxWise**: importar o al menos registrar el listado de clientes 2025 por forma | Comparar el piloto contra lo actual. ❓ Depende de que TaxWise lo exporte |

## 4. Formatos y checklists que se piden al cliente

> ⭐ **Prioridad alta.** Es lo que el cliente ve y llena. Casi todos los defectos de este punto los sufre el cliente directamente.

### 4.1 Cómo funciona hoy

**De dónde sale lo que se pide.** El catálogo de campos (`CatalogoCamposSeeder`, editable en `/catalogo`) se armó a partir del cuestionario de intake de GTS (`cuestionario.txt`, 19 secciones). Está dividido en:

| Grupo | Qué tiene | Cuántos |
| --- | --- | --- |
| Transversales | Identidad, estado civil, cónyuge, dependientes, W-2, 1099-NEC, 1095-A y las preguntas "raras" del intake (cripto, cuentas en el extranjero, bancarrota, alimony, etc.) | 44 |
| Documentos promovidos | 1099-INT/DIV/R/G/B/MISC/K/S/C/SA, 1098, 1098-E, SSA-1099, K-1, W-2G, 5498-SA, declaración del año anterior | 17 |
| Documentos extra | 1098-T | 1 |
| `form_1040` | Ingresos, deducciones, retenciones, salarios Medicare, cuenta bancaria, cuidado de dependientes, educación, Marketplace, extranjero, beneficios 2025, estimados, extensión, reembolso aplicado | 14 |
| `schedule_c` / `schedule_e` / `schedule_f` | Negocio / rentas / agricultura | 7 / 7 / 7 |
| Compañías y 1040-NR | 1065, 1120, 1120-S, 1041, 990, 1040-NR | 5–6 c/u |

**Cómo lo ve el cliente (portal, `portal/formulario.tsx`).**
- Una lista de campos por forma. Cada campo muestra **una etiqueta corta** (ej. "Bancarrota", "Costo de ventas") y un `*` si es obligatorio.
- Documento opcional → primero "¿Tienes este documento?" Sí/No (22 documentos con pregunta propia). Si dice Sí, aparece el selector de archivo.
- Dato → un input según el tipo: número, texto libre, o un grupo de textos libres para los objetos (un input por subcampo).
- Los opcionales de baja frecuencia se agrupan y colapsan (reusa los grupos del agente de WhatsApp).
- Solo 7 dependencias en código (`ocultosPorDependencia`): W-2, 1099-NEC/seguro SE, cónyuge, otro W-2, rollover, K-1, Marketplace.
- "No aplica" solo existe en los **opcionales**; un obligatorio no se puede saltar (`EventoValidator`).
- El formato del archivo se valida contra `formatos_aceptados` por extensión; 20 MB máximo.

**Cruce con el cuestionario de GTS.** Cubierto ~85 %. Lo que falta o está a medias se lista en 4.2.4.

### 4.2 🐞 Qué está mal

**1. Las preguntas de sí/no se muestran como cajas de texto libre con un sustantivo.** *(Grave.)*
- El intake de GTS pregunta "¿Recibió, vendió o intercambió criptomonedas? Sí / No". El portal muestra **"Activos digitales (cripto, NFT)"** y un input vacío. Lo mismo con "Bancarrota", "Cuentas bancarias en el extranjero", "Puede ser reclamado como dependiente", "Otro W-2", "Vivió o trabajó fuera de EE. UU.", "Fideicomiso extranjero", "Declaración enmendada", "Venta a plazos", "Rollover".
- "Casado al 31 de diciembre" (subcampo de estado civil) también es texto libre; el sistema lo interpreta después con `RespuestaSiNo::esAfirmativo`. Si el cliente escribe "casada" o "si señor", el cónyuge puede no aparecer.
- El catálogo no tiene un tipo `boolean` ni `enum`: solo `string`, `number`, `object`, `array_*`. Estado civil no tiene opciones (Soltero/Casado/…); el tipo de cuenta tampoco (Checking/Savings).
- No hay texto de ayuda: ni la pregunta completa, ni una explicación, ni un ejemplo.

**2. Se le pide al cliente que digite números que son trabajo del preparador o que ya vienen en un documento.** *(Grave.)*
- **Obligatorios del 1040:** `ingresos` (salarios, intereses/dividendos, ganancias de capital, jubilación, otros, **ajustes al ingreso**, seguridad social), `impuestos_retenidos`, **`salarios_medicare`**. Todo eso sale del W-2, 1099 y SSA-1099 que el cliente ya subió, o lo calcula el preparador. Un cliente promedio no sabe qué es "Salarios de Medicare" o "Ajustes al ingreso".
- `deducciones` (obligatorio): siete montos a digitar en vez de preguntar "¿pagó hipoteca? → suba el 1098".
- `marketplace_seguro`: pide prima mensual, **SLCSP** y APTC, que vienen en el 1095-A que ya subió.
- Schedule E `depreciacion` (obligatorio): es un cálculo, no un dato del cliente.
- Esto duplica trabajo, abre la puerta a errores y es la causa de "demasiada información" que se viene reportando.

**3. Obligatorios que no aplican a todos y no se pueden saltar.** (Amplía 1.2.1.)
- `info_bancaria`: obligatoria, pero el intake ofrece **depósito directo, cheque o aplicar al próximo año**, y quien sale debiendo no la necesita.
- `salarios_medicare`, `impuestos_retenidos`: un jubilado o un independiente sin retención no tiene qué poner.
- Schedule C: `millaje`, `activos`, `costo_ventas` obligatorios. Un contratista sin vehículo, sin equipos ni inventario queda bloqueado.
- Schedule E: `intereses_hipotecarios`, `impuestos_propiedad`, `seguros_propiedad` como documentos obligatorios: una propiedad pagada no tiene 1098.
- Resultado: el cliente escribe "0" o "no tengo" en un número o texto para poder avanzar, y eso queda como dato "recibido".

**4. Lo que el intake de GTS pide y el portal no.**

| Sección del intake | Falta en la plataforma |
| --- | --- |
| 1. General | ¿Es ciudadano o residente fiscal de EE. UU.? (define 1040 vs 1040-NR) |
| 3. Empleo | Propinas no incluidas en el W-2 (solo existe como "beneficio 2025") |
| 4. Negocio | ¿Ingresos en efectivo? ¿Ingresos sin 1099? (ver 3.2.5). Vehículo: solo pide millaje, no fecha de compra ni gastos |
| 7. Rentas | Airbnb/VRBO; mejoras o activos en la propiedad alquilada; **venta de una propiedad de alquiler** |
| 8. K-1 | Un solo campo `k1_recibido`; no distingue Partnership / S-Corp / Trust |
| 11. Ajustes | IRA y SEP/SIMPLE en un solo número (son líneas distintas) |
| 14. Marketplace | ¿La póliza cubrió personas fuera de la declaración? ¿Alguien será reclamado en otra declaración? |
| 15. Pagos | Retención federal en un 1099 |
| 16. Especiales | **¿Tuvo empleados domésticos?** (Schedule H). Hoy solo existe el caso inverso: recibir salario como empleado doméstico |
| 17. Reembolso | Método preferido (depósito / cheque / aplicar al próximo año) |
| 18. Checklist | **Identificación con foto, tarjetas de Social Security / cartas ITIN** de contribuyente, cónyuge y dependientes. No hay dónde subirlas |
| 19. Declaración | Firma del **cónyuge** cuando es conjunta. La del contribuyente solo existe por WhatsApp (1.2.2) |

Y además, a un independiente puro no se le muestra nada de lo que es del 1040 (3.2.1).

**5. No hay un checklist de documentos.**
- El intake de GTS termina con un checklist de documentos (sección 18). El portal no: los documentos están mezclados con los datos, campo por campo.
- No se puede subir **un documento que no está en la lista** (ej. un 1099-Q, una carta de un banco extranjero, un recibo cualquiera). Tampoco **varios archivos para el mismo campo** (dos W-2, tres 1099-NEC): `w2` y `form_1099_nec` son únicos por cliente, y "otro W-2" es un texto.

**6. Los formatos aceptados no son coherentes con lo que el cliente tiene en el teléfono.**
- Varios mixtos aceptan solo `pdf, jpg, jpeg` (gastos del negocio, deducciones, cuidado de dependientes, educación, oficina en casa, mejoras de eficiencia, demanda legal): **una captura de pantalla PNG se rechaza**.
- K-1 y declaración del año anterior aceptan **solo PDF**: una foto no se puede subir.
- Al revés, se aceptan `heic`, `xlsx` y `csv`, que la extracción no sabe leer (2.2.1).
- No se valida el contenido del archivo, solo la extensión.

**7. Las etiquetas son de jerga fiscal o en inglés**: "SLCSP", "APTC recibido", "Routing number", "Basis", "Rollover o conversión Roth", "K-1", "Salarios de Medicare". El intake de GTS los explica con preguntas.

### 4.3 🗑️ Qué se elimina

| Qué | Por qué |
| --- | --- |
| Como datos que digita el cliente: `ingresos` (objeto), `impuestos_retenidos`, `salarios_medicare`, `depreciacion`, los montos de `marketplace_seguro` | Salen de los documentos o los calcula el preparador (4.2.2). Se pueden mantener como campos internos del preparador o derivados de la extracción |
| Montos en `deducciones` como pregunta obligatoria | Se reemplazan por preguntas sí/no que piden el documento (1098, recibos) |
| Obligatoriedad de `millaje`, `activos`, `costo_ventas`, documentos de Schedule E e `info_bancaria` | Pasan a condicionales (4.2.3) |
| `documentos_extra` como grupo aparte para un solo documento (1098-T) | Ya estaba en 1.3; el 1098-T va con los demás documentos |
| Campos de compañías y 1040-NR en el catálogo | Fuera de alcance (1.3) |

### 4.4 ➕ Qué se agrega

| # | Qué | Para qué |
| --- | --- | --- |
| 1 | **Tipos `boolean` y `enum` en el catálogo**, con botones Sí/No y opciones (estado civil, tipo de cuenta, método de reembolso) | Corrige 4.2.1; las dependencias dejan de depender de interpretar texto |
| 2 | **Pregunta completa y texto de ayuda por campo** (columnas `pregunta`, `ayuda` en el catálogo, editables en `/catalogo`) | El cliente ve "¿Vendió o cambió criptomonedas durante 2025?" y no "Activos digitales" |
| 3 | **Modelo "pregunta → documento"**: cada sección del intake es una pregunta sí/no; el Sí abre el documento (o el dato mínimo) | Elimina la digitación de montos (4.2.2) y sigue el orden del intake de GTS |
| 4 | **Dependencias como dato del catálogo** (`depende_de`, `cuando`), no lista fija en código | Que todo condicional (vehículo, inventario, hipoteca, cónyuge, banco) se pueda definir sin programar; una sola regla para portal, WhatsApp y cierre (1.4.1) |
| 5 | **Checklist de documentos** al final, derivado de las respuestas (sección 18 del intake) con estado por documento | El cliente sabe qué falta; el preparador también |
| 6 | **Varios archivos por campo** (dos W-2, varios 1099) y **"Otro documento"** libre | Corrige 4.2.5 |
| 7 | **Identificación y tarjetas SSN/ITIN** de contribuyente, cónyuge y dependientes | Sección 18 del intake |
| 8 | **Preguntas faltantes** de 4.2.4: residencia fiscal, efectivo e ingresos sin 1099, empleados domésticos, venta de propiedad alquilada, tipo de K-1, preguntas del 1095-A, método de reembolso | Cobertura completa del intake |
| 9 | **Formatos uniformes**: `pdf, jpg, jpeg, png` en todo documento; `heic` convertido a JPEG al subir; `xlsx/csv` solo donde haya lector (2.4.1); validar tipo real (MIME) | Corrige 4.2.6 |
| 10 | **Firma del cónyuge** en la atestación cuando la declaración es conjunta, en el portal | Sección 19 del intake (y 1.4.2) |

## 5. Horas por declaración y dolores

### 5.1 Cómo funciona hoy

**Dónde la plataforma ya ahorra tiempo**

| Qué | Cómo | Dónde |
| --- | --- | --- |
| Recolección sin cita | El cliente llena el portal o conversa por WhatsApp a cualquier hora | Portal, agente de WhatsApp |
| Lectura de documentos | La IA extrae los datos del W-2, 1099, etc. y llena los campos que "revela"; suma cuando varios documentos revelan el mismo campo | `DocumentoExtraccionService`, `RelacionDocumentoCampo` |
| Duplicados | Detecta un mismo archivo subido dos veces (hash) | `DocumentoDuplicadoService` |
| Pre-cálculo | 12 calculadoras: filing status, dependientes, AGI, deducción, QBI, impuesto, autoempleo, Medicare adicional, NIIT, crédito extranjero, créditos, liquidación | `DeterminacionFiscalService`, `app/Services/Reglas` |
| Riesgo | Nivel de riesgo automático (campos/documentos inválidos, etc.) con ajuste manual | `RiesgoCasoService` |
| Trazabilidad | Historial por campo (quién, cuándo, valor anterior y nuevo) y bitácora de acciones | `HistorialCambio`, `BitacoraActividad` |
| Intervención | El preparador edita campos, sube documentos, marca "no aplica", toma el control del WhatsApp | Ficha del cliente (`clientes/show.tsx`) |
| Exportación | ZIP con `campos.json` y los documentos | `ClienteExportService` |

**Dónde sigue costando tiempo**
- **Digitación en TaxWise:** 100 % manual (no hay conector, 2.4.4).
- **Revisión:** campo por campo en la ficha del cliente (una página de ~2.400 líneas de código), por forma.
- **Correcciones:** el preparador corrige él mismo o le escribe al cliente por WhatsApp a mano.
- **Datos mal pedidos:** lo que el cliente digita sin saber (4.2.2) hay que verificarlo contra el documento.

**Qué se registra sobre tiempos.** Hay marcas de tiempo sueltas, pero ninguna métrica:
- Alta del cliente, mensajes de WhatsApp y del portal, `updated_at` de cada campo, historial de cambios.
- `revisado_en` / `revisado_por` por forma.
- Fecha de la atestación (que hoy no ocurre, 1.2.2).

**Producción:** 2 clientes de prueba; no hay datos reales de tiempo.

### 5.2 🐞 Qué está mal

**1. No se pueden medir las horas por declaración.** *(Grave para el piloto.)*
- No hay estados del caso con fecha (ver 1.4.3): "recolección completa", "en preparación", "presentada", "aceptada por el IRS". La fecha de presentación vive solo en TaxWise.
- No se registra el tiempo que el preparador dedica a cada cliente (ni manual ni automático).
- Sin esto no hay forma de demostrar que la plataforma ahorra horas frente al proceso actual, que es justamente lo que el piloto tiene que probar.

**2. La exportación no sirve para digitar en TaxWise.**
- `campos.json` usa las claves internas (`salarios_medicare`, `info_conyuge.ssn`, `form_1040`) y no sigue el orden de las pantallas de TaxWise ni del 1040.
- No hay un resumen legible (PDF o pantalla) para tener al lado mientras se digita. El preparador vuelve a abrir cada documento.

**3. Una forma "revisada" sigue revisada aunque el cliente cambie algo después.**
- `marcarRevisado` guarda `revisado_en`, y nada lo borra: ni una edición del cliente, ni un documento nuevo.
- El preparador no se entera de que lo que aprobó cambió. Tampoco hay marca de revisión por campo ni vista "qué cambió desde la última revisión"; solo el historial campo por campo.

**4. No hay un circuito para pedir correcciones al cliente.**
- Si el preparador ve algo mal, no puede marcar el campo con "corrige esto porque…" y que le llegue al cliente.
- Cuando el sistema marca un campo como inválido, el portal muestra un mensaje genérico ("Este dato no quedó válido, corrígelo.") y no muestra el motivo, aunque el motivo se guarda (`advertencia`).
- La comunicación queda en WhatsApp manual, que fuera de la ventana de 24 h necesita plantilla (2.4.6).

**5. El pre-cálculo depende de los números que digita el cliente.**
- Las calculadoras leen `ingresos`, `impuestos_retenidos`, `salarios_medicare` y `deducciones`, los mismos campos que en 4.2.2 se propone dejar de pedirle al cliente.
- Si el cliente los llenó mal o con "0" para poder avanzar (4.2.3), el cálculo sale mal y el riesgo también.
- Y nadie compara el resultado con el de TaxWise, así que no se sabe si acierta.

**6. Los errores de extracción no avisan** (2.2.1): un documento que no se pudo leer obliga al preparador a digitar desde la imagen sin que nadie le haya avisado.

### 5.3 🗑️ Qué se elimina

| Qué | Por qué |
| --- | --- |
| Pre-cálculo visible para el cliente | Ya en 1.4.8: no mostrarlo hasta que el preparador lo apruebe |
| Las calculadoras que no se usen en la revisión | ❓ Decidir con GTS: si el preparador no las mira porque TaxWise calcula todo, son mantenimiento sin retorno. Si sirven como control previo, se quedan (ver 5.4.6) |

### 5.4 ➕ Qué se agrega

| # | Qué | Para qué |
| --- | --- | --- |
| 1 | **Estados del caso con fecha** (recolectando → listo para preparar → en preparación → presentada → aceptada/rechazada) | Medir tiempos de punta a punta (5.2.1); es el mismo "estado del caso" de 1.4.3 |
| 2 | **Registro de tiempo del preparador** (iniciar/pausar en la ficha, o al menos minutos al cerrar la preparación) | Horas por declaración reales |
| 3 | **Panel de tiempos** en el dashboard: días en recolección, días hasta presentar, horas de preparación por tipo de cliente (3.4.2) y por preparador | Comparar contra el proceso actual |
| 4 | **Hoja de digitación para TaxWise**: resumen ordenado como las pantallas de TaxWise / el 1040, con cada valor y el documento de origen | Bajar el tiempo de digitación mientras no haya conector (5.2.2) |
| 5 | **Revisión que se invalida sola** cuando cambia algo después de aprobado, con vista "cambios desde la última revisión" | Corrige 5.2.3 |
| 6 | **Pedir corrección**: el preparador marca un campo con un motivo, el cliente lo ve en el portal y recibe aviso; mostrar también el motivo de los inválidos automáticos | Corrige 5.2.4 |
| 7 | **Pre-cálculo alimentado por la extracción** de documentos (no por lo que digita el cliente) y comparado con el resultado final de TaxWise | Que el control previo sea confiable (5.2.5) |
| 8 | **Contador de correcciones** por caso (cuántos campos corrigió el preparador, cuántas idas y vueltas con el cliente) | Saber dónde se pierde el tiempo y qué pregunta está mal hecha |

