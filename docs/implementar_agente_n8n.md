# Migrar el agente de WhatsApp de n8n a taxes-eeuu

## Contexto

Hoy la conversación con el cliente por WhatsApp corre completamente fuera de este repo,
en n8n, con **dos agentes encadenados** (no uno solo):

- **Orquestador** (`prompt_actuales/promptBase.md`): verifica cuenta, confirma año
  fiscal, corre el árbol de determinación de forma(s), y de ahí en adelante se limita a
  reenviar cada mensaje del cliente a un segundo agente invocado como si fuera una tool
  (`especialista_recoleccion`), reenviando su respuesta al cliente sin modificarla.
- **Especialista de recolección** (`prompt_actuales/especialista_recoleccion.md`): decide
  qué campo pedir y guarda los datos, usando `consultar_pendientes_cliente`,
  `consultar_documentos_extra` (tool nueva, separada, para los documentos opcionales) y
  `guardar_campo_cliente`.

`docs/prompt.md` documenta una versión **anterior y ya reemplazada** de un solo agente —
queda como referencia histórica, igual que los dos prompts de `prompt_actuales/` quedarán
como referencia una vez implementado este plan (ver decisión de arquitectura más abajo).

El historial de conversación se guarda en Supabase (tabla `globaltax_registro_whatsapp`) y
hoy se lee de solo lectura desde el panel (`SupabaseWhatsappConversationService`,
`ClienteController::conversacionWhatsapp`).

El objetivo de este documento es traer esa lógica **adentro** de taxes-eeuu, en un corte
completo (se construye, se valida, y se apaga n8n de una vez — no convive en paralelo).

## Decisiones ya tomadas (no rediscutir sin motivo)

- LLM conversacional: **OpenAI** (function calling), no Claude.
- Canal: se mantiene **Twilio** como proveedor de WhatsApp (ya tienen cuenta y número).
- **Arquitectura conversacional: un solo agente con fases controladas por código**, no los
  dos agentes encadenados de n8n. La razón de que n8n tuviera dos agentes era que la
  única forma de mantener un prompt manejable ahí es partirlo en nodos, y la única forma
  de forzar el orden ("nunca invoques X antes de Y") es escribiéndolo en prosa dentro del
  prompt. En código no hace falta esa restricción: una máquina de estados deriva la fase
  actual de la conversación a partir de datos que ya existen (¿tiene cuenta? ¿tax_year
  confirmado? ¿formas declaradas? ¿quedan pendientes?) y **solo expone al modelo las tools
  válidas para esa fase** — si `crear_cliente_taxes` no existe como función disponible
  durante la recolección, el modelo no puede invocarla por error, sin necesidad de una
  regla en el prompt. Esto además reduce a **una sola llamada al LLM por turno** (hoy son
  mínimo dos: orquestador + especialista), lo cual importa para costo/latencia con el
  volumen de clientes que se busca. El contenido de ambos prompts actuales (árbol de
  determinación, lógica de ACTIVOS/PASIVOS, relaciones documento→campo, grounding
  estricto) se conserva casi íntegro — solo cambia cómo se aplica la secuencia: de reglas
  en prosa a fases en código.
- Se conserva una tool `think` (sin efecto real, solo da espacio de razonamiento al
  modelo antes de actuar) en todas las fases, igual que en los prompts actuales — se
  puede reevaluar más adelante si el modelo elegido ya trae razonamiento nativo
  suficiente.
- **Extracción de documentos en dos niveles** (texto embebido del PDF primero, visión
  como respaldo) — ver sección dedicada más abajo.
- El prompt pasa de archivos estáticos (`docs/prompt.md` / `prompt_actuales/*.md`) a
  **versionado en base de datos**, siguiendo el mismo patrón que
  `parametros_fiscales`/`ParametrosFiscales` — permite publicar una nueva versión sin
  deploy y deja trazabilidad de qué versión atendió cada conversación. Esto también
  habilita, más adelante, un "meta-agente" que sugiera cambios al prompt a partir de
  conversaciones reales (con aprobación humana obligatoria antes de activarlos — nunca
  auto-aplicado).
- **Alcance de este esfuerzo: solo la conversación entrante/reactiva** (lo que n8n hace
  hoy). Los mensajes proactivos (recordatorios de documentos faltantes, "tu declaración
  está lista") usando plantillas `ContentSid` quedan como **fase aparte, posterior**,
  aunque la infraestructura de Twilio que se construye aquí ya deja el camino listo.
- El histórico de Supabase se **migra una sola vez** a la tabla local y luego se retira
  la dependencia de Supabase para WhatsApp por completo.
- **Cliente OpenAI: HTTP propio sobre `Illuminate\Support\Facades\Http`, no un SDK de
  terceros.** La superficie que se necesita (chat completions con function calling) es
  pequeña; un wrapper propio (`app/Services/WhatsappAgent/OpenAiClient.php`) da control
  total de timeouts/reintentos/backoff por conversación concurrente, sin depender del
  ritmo de release de un paquete externo — importa más a medida que crece el volumen de
  conversaciones simultáneas (más despachos, más clientes).
- **Cliente Twilio: sí el SDK oficial (`twilio/sdk`)**, a diferencia de OpenAI. La
  validación de firma de webhooks (`X-Twilio-Signature`) es código de seguridad sensible
  ya resuelto y probado en el SDK — reimplementar ese HMAC a mano no vale el riesgo,
  sin importar la escala. También se usa para envío de mensajes y descarga de media.
- **Extracción de texto de PDF (Nivel 1): `smalot/pdfparser`** (PHP puro, sin binario
  externo) en vez de `pdftotext`/poppler-utils. No depender de un binario del sistema
  operativo simplifica desplegar en cualquier entorno (contenedores, distintos hosts)
  sin tener que garantizar que el paquete del SO esté instalado — importa más cuantos
  más entornos/despachos corran la plataforma.
- **`EventoRecoleccionService::procesar()` deja de recibir un `EventoRequest` como único
  punto de entrada.** Hoy `EventoRequest` (FormRequest) mezcla autorización Sanctum
  (`ApiAbility::EventosWrite`, atada al access token del request HTTP) con la validación
  semántica contra el catálogo (`withValidator()`) — un job en cola no tiene request HTTP
  ni token Sanctum activo. En vez de que `ToolExecutor` llame a la API por HTTP interno
  (loopback) para reusar esa validación gratis, se extrae la validación de catálogo a una
  clase invocable compartida (`App\Support\EventoValidator`) y se cambia `procesar()`
  para aceptar un DTO plano (`EventoRecoleccionData`) en vez de un `FormRequest`.
  `EventoController::store` pasa a construir ese DTO desde el `EventoRequest` ya
  validado; `ToolExecutor` lo construye directo desde los argumentos del tool call. Se
  prefiere esto sobre el loopback HTTP porque, con más despachos y más conversaciones
  concurrentes, cada tool call (2-4 por turno) haciendo un round-trip HTTP contra el
  propio servidor agrega carga y latencia que no aporta nada frente a una llamada de
  método en el mismo proceso — y evita depender de que la app pueda alcanzarse a sí
  misma por HTTP solo para hablarse a sí misma.
- **Escalamiento a humano: interrupción y devolución de control**, no solo derivación
  para revisión posterior — ver sección dedicada más abajo.

## Prerrequisitos (credenciales e info a confirmar)

- [ ] Twilio: Account SID, Auth Token (confirmar si usan el Auth Token principal o un
      API Key SID + Secret para autenticar llamadas salientes — el Account SID hace
      falta de todas formas para validar la firma de los webhooks entrantes), número de
      WhatsApp (`whatsapp:+1XXXXXXXXXX`), y el/los `ContentSid` de plantillas ya
      aprobadas.
- [ ] Un número **Sandbox** de Twilio para probar sin tocar el número de producción.
- [ ] API key de OpenAI. Definir **dos** modelos por configuración, no fijados en este
      documento (los precios y modelos disponibles cambian con frecuencia):
      - Modelo conversacional (function calling confiable).
      - Modelo de visión para el respaldo de extracción de documentos.
- [x] Agregar `twilio/sdk` y `smalot/pdfparser` vía Composer (ya evaluados — ver
      decisiones de arquitectura — solo falta instalarlos). El cliente de OpenAI es un
      wrapper propio sobre `Http::`, no un paquete nuevo.

## Arquitectura

```
Twilio (webhook) → TwilioWebhookController        [valida X-Twilio-Signature, responde 200]
                          │
                          ▼
                ProcesarMensajeWhatsappJob          [cola: redis/database, ya configuradas]
                          │  (idempotente por MessageSid)
                          │
                          ├── si el mensaje trae media ──► DocumentoExtraccionService
                          │                                  1) texto embebido del PDF (gratis)
                          │                                  2) si falla/pobre → visión (fallback)
                          │                                  → produce texto_extraido + archivo_url
                          ▼
              EstadoConversacionResolver           [deriva la fase vigente desde datos ya
                          │                          existentes: cuenta, tax_year, formas,
                          │                          pendientes — nunca de memoria]
                          ▼
              AgenteConversacionalService           [1 sola llamada a OpenAI por turno;
                          │                           tools filtradas según la fase]
                          ▼
                    ToolExecutor  ───────────►  Servicios internos existentes
                (5 tools, según fase)             (EventoRecoleccionService, TaxFieldCatalog, etc.)
                          │
                          ▼
                  WhatsappMensaje (BD local)     [reemplaza globaltax_registro_whatsapp]
                          │
                          ▼
                 TwilioWhatsappClient  ──────►  Twilio (respuesta al cliente)
```

## Extracción de documentos

Los documentos que llegan por WhatsApp pueden ser imágenes (foto de un W-2, por ejemplo)
o PDFs — y no todos los PDFs son iguales:

- **PDF generado digitalmente** (1095-A del portal del Marketplace, 1098-T de un portal
  universitario, un 1099 descargado de un banco/broker) trae una capa de texto real.
- **PDF que en realidad es una foto/escaneo** (común cuando el cliente usa una app de
  escanear del celular) no tiene texto real — es una imagen envuelta en un contenedor
  PDF. Intentar extraer texto de este tipo de archivo con una librería normal da vacío o
  basura, sin error explícito.

Por eso la extracción se hace en dos niveles, nunca reemplazando uno al otro:

1. **Nivel 1 — texto embebido (costo ≈ $0, sin LLM):** al recibir un PDF, intentar
   extraer su capa de texto con una librería local. Aplicar una heurística simple
   (cantidad de caracteres legibles, proporción de texto vs. tamaño esperado del
   documento) para decidir si el resultado es utilizable.
   - Opcional: si el texto salió bien pero desordenado (los extractores de PDF no
     siempre preservan el orden visual de lectura de un formulario con casillas), pasarlo
     por un modelo de **texto** económico (sin visión) para normalizarlo al mismo formato
     semántico que espera el flujo de recolección. Esto sigue siendo mucho más barato que
     visión porque no hay tokens de imagen de por medio.
2. **Nivel 2 — visión (respaldo):** si el PDF no tiene texto útil, o si el archivo
   entrante es directamente una imagen, se envía a un modelo de visión con un prompt de
   transcripción estricta: transcribir todo lo visible tal cual, marcar `[no legible]`
   donde no se pueda leer, sin completar ni asumir valores — mismo principio de
   *grounding estricto* que ya rige el resto del sistema. Para PDFs sin texto útil, cada
   página se rasteriza a imagen antes de este paso.

Cada documento procesado debe dejar registrado **qué nivel lo resolvió** (ej. columna o
log `metodo_extraccion`: `texto_pdf` | `texto_pdf_normalizado` | `vision`). Esto permite
medir en producción, con datos reales del propio despacho, qué proporción de documentos
cae en cada camino — necesario porque el costo real depende de esa mezcla y no se puede
estimar de forma confiable de antemano.

**Referencia de costo de la conversación de diseño (septiembre 2026, sujeta a cambios de
precio de OpenAI — no fijar estos números en la implementación):** para 1,000 documentos
(~200 clientes × 5 documentos), visión pura con un modelo tipo GPT-4o ronda los $5.50;
con un modelo de visión más económico tipo GPT-4.1-mini ronda $0.90-$1; los documentos
resueltos por el Nivel 1 (texto embebido) cuestan prácticamente $0. A este volumen el
costo de extracción es marginal frente a otros costos del sistema — la decisión de qué
modelo usar debe priorizar precisión de lectura (SSN, montos exactos) sobre el ahorro.

## Escalamiento a humano (handoff bidireccional)

Ni los prompts actuales de n8n ni este plan, hasta ahora, contemplaban un mecanismo para
que un preparador intervenga directamente en la conversación — vacío ya señalado en
`docs/analisis-cuestionario-descubrimiento.md` (7.3, 7.6). En vez de solo "derivar y
esperar", el mecanismo es una interrupción **bidireccional**: un preparador puede tomar
el control de la conversación con el cliente en cualquier momento, hablar directamente
por WhatsApp, y devolver el control al agente cuando termine — y el agente, al retomar,
debe tener el contexto completo de lo que pasó mientras estuvo fuera.

**Estado de control por conversación** (no por mensaje): cada conversación —
identificada por teléfono, no por `cliente_id`, porque puede no existir `cliente_id`
todavía durante la verificación de cuenta — tiene un estado `agente` | `humano`, quién la
tomó y cuándo.

- `agente` (default): `ProcesarMensajeWhatsappJob` procesa el mensaje entrante
  normalmente y llama a `AgenteConversacionalService`.
- `humano`: el job sigue guardando el mensaje entrante en `whatsapp_mensajes` (nunca se
  pierde), pero **no invoca al agente** — el preparador responde desde el panel, y esa
  respuesta se envía por `TwilioWhatsappClient` y se guarda con `rol = 'preparador'` y
  `autor_user_id`.

**Devolver el control nunca pierde contexto porque no depende de memoria de proceso**:
`AgenteConversacionalService` ya arma su prompt a partir del historial completo en
`whatsapp_mensajes` (no de estado en memoria), así que los mensajes que el preparador
envió durante su intervención quedan naturalmente en el historial que el agente lee la
próxima vez que se le invoca. Dos cuidados concretos:

1. **Los mensajes del preparador se presentan al modelo con su propio rol**, no como si
   los hubiera dicho el agente — así, si más adelante hace falta auditar o ajustar el
   prompt, queda claro qué dijo el sistema automatizado y qué dijo una persona.
2. **Una intervención humana nunca actualiza datos del cliente por sí sola.** Si el
   preparador le confirma algo al cliente por chat (ej. "ya recibimos tu W-2"), eso no
   guarda ningún campo — sigue haciendo falta completarlo por el flujo normal (panel o
   agente). El agente, al retomar, sigue derivando su fase y sus pendientes de
   `EstadoConversacionResolver`/`consultar_pendientes_cliente` — nunca asume que algo
   quedó guardado solo porque se mencionó en el chat durante la intervención humana.
   Mismo principio de *grounding estricto* que ya rige el resto del agente, aplicado
   también al tramo humano de la conversación.

**Condición de carrera a cuidar:** si un preparador toma el control justo cuando un job
ya está a mitad de generar la respuesta del agente para un mensaje anterior, ese job debe
volver a comprobar el estado de control **justo antes de enviar** la respuesta y
descartarla (sin enviar) si para entonces la conversación ya pasó a `humano` — para que
el agente no responda por encima de un preparador que ya está hablando con el cliente.

**Interfaz:** un botón "Tomar control" / "Devolver al agente" en la vista de conversación
del panel (`ClienteController::conversacionWhatsapp`), junto con una caja de envío que
solo aparece en modo `humano`. Opcional para el primer corte: un mensaje automático al
cliente al tomar/devolver control ("Un preparador se unió a la conversación" / "Retomamos
la atención automática"), para que el cambio de tono no lo confunda.

## Archivos y clases a crear

**Persistencia**
- `database/migrations/xxxx_create_agente_prompts_table.php`
- `database/migrations/xxxx_create_whatsapp_mensajes_table.php`
- `app/Models/AgentePrompt.php`
- `app/Models/WhatsappMensaje.php`
- `app/Support/AgentePromptVigente.php` — resuelve la versión activa (mismo patrón que
  `App\Support\ParametrosFiscales`).

**Recepción y validación**
- `app/Http/Middleware/VerifyTwilioSignature.php` (o clase de validación equivalente).
- `app/Http/Controllers/Api/TwilioWebhookController.php` — `handleIncoming(Request $request)`.
- `app/Jobs/ProcesarMensajeWhatsappJob.php`.

**Extracción de documentos**
- `app/Services/DocumentoExtraccion/PdfTextExtractorService.php` — Nivel 1: intenta
  extraer texto embebido y aplica la heurística de calidad.
- `app/Services/DocumentoExtraccion/DocumentoVisionExtractorService.php` — Nivel 2:
  transcripción vía modelo de visión (con rasterizado de páginas PDF cuando aplique).
- `app/Services/DocumentoExtraccion/DocumentoExtraccionService.php` — orquesta ambos
  niveles, decide cuál usar, y registra `metodo_extraccion`.

**El agente**
- `app/Support/EventoValidator.php` — validación estructural + coincidencia con el
  catálogo maestro, extraída de `EventoRequest` para compartirla entre el controlador
  HTTP y `ToolExecutor` (ver decisión de arquitectura sobre desacoplar
  `EventoRecoleccionService::procesar()` de `EventoRequest`).
- `app/DataTransferObjects/EventoRecoleccionData.php` — reemplaza el parámetro
  `EventoRequest` de `EventoRecoleccionService::procesar()`; `EventoController::store` lo
  construye desde el `EventoRequest` ya validado, `ToolExecutor` lo construye directo
  desde los argumentos del tool call.
- `app/Services/WhatsappAgent/OpenAiClient.php` — wrapper propio sobre `Http::` para
  chat completions + function calling, con reintentos/backoff configurables por modelo.
- `app/Support/AgenteWhatsappUser.php` — resuelve/crea el usuario de sistema que actúa
  como `actor` cuando el origen del dato es el agente de WhatsApp.
- `app/Services/AgenteToolService.php` — lógica compartida detrás de
  `crear_cliente_taxes`/`declarar_formas_cliente`/`consultar_pendientes_cliente`/
  `consultar_documentos_extra`, invocada tanto por `Api\ClienteController`/
  `Api\CatalogoController` (HTTP) como por `ToolExecutor` (directo).
- `app/Services/WhatsappAgent/EstadoConversacionResolver.php` — deriva la fase vigente de
  la conversación (verificación de cuenta → determinación de forma(s) → recolección →
  cierre) a partir de los datos ya existentes del cliente, nunca de memoria de la
  conversación. Sin fase separada para "año fiscal": no hay ninguna tool ni dato que
  distinga "año confirmado, forma todavía no" de "nada confirmado" — igual que en
  `prompt_actuales/promptBase.md` (PASO 0.5 y PASO A-D corren seguidos sin tool de por
  medio), el modelo re-deriva el año ya confirmado del propio historial de la
  conversación; `DeterminacionFormas` cubre ambos pasos. El año fiscal "en curso" para
  las fases siguientes es el mayor `tax_year` entre las formas ya declaradas del
  cliente — no se persiste en ningún otro lado.
- `app/Services/WhatsappAgent/ToolDefinitions.php` — esquema de las 5 tools
  (`crear_cliente_taxes`, `declarar_formas_cliente`, `consultar_pendientes_cliente`,
  `consultar_documentos_extra`, `guardar_campo_cliente`) más `think`, con un método
  `paraFase()` que filtra cuáles exponer al modelo según la fase vigente.
- `app/Services/WhatsappAgent/ToolExecutor.php` — despacha cada tool call a los
  servicios internos correspondientes.
- `app/Services/WhatsappAgent/AgenteConversacionalService.php` — arma prompt vigente
  (bloque de fase correspondiente) + historial + mensaje nuevo, corre el loop de
  function-calling con las tools de `paraFase()` hasta obtener una respuesta final de
  texto.

**Envío y medios**
- `app/Services/Whatsapp/TwilioWhatsappClient.php` — enviar texto libre (ventana de 24h).
- `app/Services/Whatsapp/TwilioMediaDownloader.php` — descarga el archivo desde Twilio y
  lo entrega a `DocumentoExtraccionService`; el resultado (texto_extraido + archivo
  almacenado) se procesa con el mismo `EventoRecoleccionService::procesarArchivo` que ya
  usa el panel — heredando detección de duplicados, estados y validación de formato.

**Escalamiento a humano**
- `database/migrations/xxxx_create_whatsapp_control_table.php`
- `app/Models/WhatsappControl.php` — teléfono (único), `cliente_id` nullable, `estado`
  (`agente`|`humano`), `user_id` de quien tomó el control, timestamps.
- Métodos nuevos en `ClienteController` (o un controlador dedicado) —
  `tomarControlWhatsapp(User $cliente)` / `devolverControlWhatsapp(User $cliente)`.
- Modificar `ProcesarMensajeWhatsappJob` para consultar `WhatsappControl` antes de
  invocar al agente, y de nuevo justo antes de enviar la respuesta (condición de carrera
  — ver sección ESCALAMIENTO A HUMANO).
- Vista de conversación del panel: botón de tomar/devolver control + caja de envío
  manual visible solo en modo `humano`.

**Migración de histórico y retiro de Supabase**
- `app/Console/Commands/MigrarHistoricoWhatsappSupabase.php` — comando de una sola
  ejecución que copia todo lo existente en `globaltax_registro_whatsapp` hacia
  `whatsapp_mensajes`.
- Modificar `app/Http/Controllers/ClienteController.php` (método
  `conversacionWhatsapp`) para leer de `WhatsappMensaje` en vez de
  `SupabaseWhatsappConversationService`.
- Eliminar `app/Services/SupabaseWhatsappConversationService.php` y la entrada
  `services.supabase.whatsapp_table` de `config/services.php` una vez confirmado el
  corte (dejar `services.supabase` si Supabase se sigue usando para algo más; si no,
  retirarlo también).

**Configuración**
- `config/services.php`: agregar bloques `twilio` (account_sid, auth_token/api_key,
  whatsapp_from, content_sids) y `openai` (api_key, `model` conversacional, `vision_model`
  para el Nivel 2 de extracción).
- `.env.example`: variables correspondientes, incluyendo `OPENAI_MODEL` y
  `OPENAI_VISION_MODEL` por separado.
- `routes/api.php`: ruta pública (sin Sanctum) para el webhook de Twilio, protegida
  únicamente por la validación de firma.

## Checklist de desarrollo

### Fase 0 — Prerrequisitos
- [ ] Reunir credenciales de Twilio y OpenAI (ver sección de prerrequisitos).
- [ ] Configurar número Sandbox de Twilio para pruebas.
- [ ] Confirmar disponibilidad de librería/binario de extracción de texto de PDF.
- [ ] Confirmar si hace falta extraer lógica de los controladores HTTP actuales a
      servicios invocables directamente.

### Fase 1 — Persistencia
- [x] Migración + modelo `agente_prompts` (versionada por `version`+`fase`, con
      `FaseConversacion` como enum de las 5 fases).
- [ ] Sembrar la versión inicial (`version = 1`, publicada) con el contenido de
      `prompt_actuales/promptBase.md` y `prompt_actuales/especialista_recoleccion.md`
      adaptado a las fases de un solo agente (ver ARQUITECTURA) — pendiente: es
      redacción de prompt, no código de infraestructura.
- [x] Migración + modelo `whatsapp_mensajes` (teléfono, cliente_id nullable, rol,
      contenido, `twilio_message_sid` único, `prompt_version` nullable, timestamps).
- [x] Migración + modelo `whatsapp_control` (estado agente/humano por teléfono — ver
      ESCALAMIENTO A HUMANO), adelantada desde Fase 3 por ser también persistencia base.
- [x] `AgentePromptVigente` para resolver la versión publicada vigente por fase, igual
      que `ParametrosFiscales` (con tests en `AgentePromptVigenteTest`).

### Fase 2 — Recepción (inbound) — completa
- [x] `VerifyTwilioSignature` (middleware) — valida `X-Twilio-Signature` con
      `Twilio\Security\RequestValidator`; reemplaza cualquier auth de sesión/Sanctum en
      esta ruta pública.
- [x] Ruta pública `POST /api/whatsapp/webhook` + `TwilioWebhookController` (invokable,
      un solo `__invoke`) — responde 200 de inmediato y despacha el job (nada síncrono).
- [x] `ProcesarMensajeWhatsappJob`: idempotencia por `MessageSid` (columna única +
      `Cache::lock`) para tolerar reintentos de Twilio sin duplicar el procesamiento.
- [x] Guarda el mensaje entrante en `whatsapp_mensajes` antes de invocar al agente, y
      resuelve/crea `whatsapp_control` (vinculando `cliente_id` si ya existe un cliente
      con ese teléfono). El punto donde Fase 3 conecta `AgenteConversacionalService`
      queda marcado dentro del propio job.
- [x] Tests (`TwilioWebhookTest`, 6 casos): firma válida/inválida/ausente, no duplica
      por reintento, vincula cliente existente por teléfono, guarda igual en modo
      `humano`.
- Nota de escala pendiente de decidir: el job hoy corre en la conexión de cola por
  defecto (`database` en dev, `sync` en test). Enrutarlo a `redis` es un solo
  `Queue::route(ProcesarMensajeWhatsappJob::class, connection: 'redis')` en
  `AppServiceProvider::boot()` (Laravel 13+, ver regla `arch-queue-routing`) cuando
  el volumen lo justifique — no se activó todavía para no forzar Redis como
  dependencia de los tests de esta fase.

### Fase 3 — El agente conversacional
- [x] `EventoRecoleccionService::procesar()` cambiado para recibir
      `EventoRecoleccionData` (DTO) en vez de `EventoRequest`; `EventoController::store`
      actualizado (`EventoRecoleccionData::fromRequest($request)`). Los 38 tests de
      `EventoRecoleccionTest` siguen pasando sin cambios — el contrato HTTP no cambió.
- [x] `AgenteToolService`: extrae `crear_cliente_taxes`/`declarar_formas_cliente`/
      `consultar_pendientes_cliente`/`consultar_documentos_extra` de
      `Api\ClienteController`/`Api\CatalogoController` (antes acopladas a
      `ensureAbility()`/HTTP) — ambos controladores ahora lo invocan, y `ToolExecutor`
      podrá invocarlo igual, directo. 70 tests existentes sin cambios.
- [x] `EventoValidator`: extraído de `EventoRequest::withValidator()` (validación de
      catálogo — existe el campo, calza tipo_campo/tipo_dato, acumular/subcampo
      coherentes, formato de archivo) a una clase plana reutilizable. `EventoRequest`
      ahora delega en ella; los 38 tests de `EventoRecoleccionTest` siguen pasando sin
      cambios, más 8 tests nuevos que ejercitan la clase directo (`EventoValidatorTest`).
- [x] `OpenAiClient`: wrapper sobre `Http::` para chat completions + function calling,
      con timeout/reintentos/backoff configurables (`OPENAI_TIMEOUT`,
      `OPENAI_RETRIES`, `OPENAI_RETRY_BACKOFF_MS`). Tests con `Http::fake()`
      (`OpenAiClientTest`, 6 casos, incluyendo reintento ante error transitorio).
- [x] `EstadoConversacionResolver`: deriva la fase vigente (`FaseConversacion`, 4 casos —
      ver nota sobre por qué no hay fase separada de "año fiscal") desde los datos ya
      existentes del cliente. Tests en `EstadoConversacionResolverTest` (5 casos: sin
      cliente, sin formas, con pendientes, sin pendientes, y que usa el tax_year más
      reciente entre formas de años distintos).
- [x] `ToolDefinitions` con el esquema de las 5 tools + `think`, y `paraFase()`.
      Decisión: ninguna tool expone `cliente_id` (ToolExecutor ya conoce al cliente de
      la conversación) ni `tax_year` salvo `declarar_formas_cliente` (que es quien lo
      establece) — se deriva igual que en `EstadoConversacionResolver`, menos estado
      para que el modelo tenga que recordar y pasar bien turno a turno.
- [x] `ToolExecutor` llamando a los servicios internos directamente (`AgenteToolService`,
      `EventoValidator` + `EventoRecoleccionService::procesar()` con
      `EventoRecoleccionData`, nunca HTTP). Un tool call inválido devuelve
      `{error, detalles}` como resultado de la tool (el modelo puede corregir y
      reintentar), no una excepción.
- [x] `App\Support\AgenteWhatsappUser`: usuario de sistema que actúa como `actor` en
      `EventoRecoleccionService` para el camino de WhatsApp (sin token Sanctum/HTTP) —
      necesario porque `campos_cliente.actualizado_por`/`historial_cambios.modificado_por`
      son FKs reales a `users.id`.
- [ ] `AgenteConversacionalService`: **una sola** llamada de function-calling por turno
      (prompt de la fase vigente + historial + mensaje nuevo → tool calls → resultado →
      repetir dentro de la misma llamada hasta texto final).
- [ ] Guardar la respuesta del agente en `whatsapp_mensajes`, incluyendo qué versión de
      prompt la generó.
- [ ] `WhatsappControl`: migración + modelo, con estado `agente`/`humano` por teléfono.
- [ ] Acciones de tomar/devolver control (panel) + botón y caja de envío manual en la
      vista de conversación.
- [ ] `ProcesarMensajeWhatsappJob` respeta el estado de control: no invoca al agente en
      modo `humano`, y vuelve a comprobar el estado justo antes de enviar la respuesta
      del agente (condición de carrera con una toma de control a mitad de proceso).

### Fase 4 — Extracción de documentos, envío y medios
- [ ] `PdfTextExtractorService` (Nivel 1) con la heurística de calidad del texto
      extraído.
- [ ] `DocumentoVisionExtractorService` (Nivel 2, respaldo) con rasterizado de páginas
      PDF cuando aplique.
- [ ] `DocumentoExtraccionService` orquestando ambos niveles y registrando
      `metodo_extraccion` por documento.
- [ ] `TwilioMediaDownloader`: descarga `MediaUrl0..N` con Basic Auth y entrega a
      `DocumentoExtraccionService`; el resultado se procesa con
      `EventoRecoleccionService::procesarArchivo`.
- [ ] `TwilioWhatsappClient::enviarTexto()` para mandar la respuesta final del job.

### Fase 5 — Pruebas
- [ ] Flujo completo en el Sandbox de Twilio con los tres casos de documento: imagen,
      PDF con texto embebido, y PDF que en realidad es un escaneo/foto — confirmar que
      cada uno cae en el nivel de extracción correcto.
- [ ] Comparar, con los mismos mensajes, el comportamiento nuevo contra el actual de
      n8n (mismas preguntas, mismo orden, sin repetir preguntas ya respondidas — ver
      el hallazgo de `docs/analisis-cuestionario-descubrimiento.md` sección 5).
- [ ] Probar que un mismo `MessageSid` reenviado por Twilio no duplica el
      procesamiento ni la respuesta.
- [ ] Medir, en un lote de prueba, qué proporción de documentos cae en cada nivel de
      extracción, para recalcular el costo real antes del corte a producción.
- [ ] Probar el handoff: tomar control a mitad de conversación, responder manualmente,
      devolver el control, y confirmar que el agente retoma con el historial completo
      (incluyendo los mensajes del preparador) sin repetir preguntas ya resueltas
      manualmente ni asumir que un dato quedó guardado solo por haberse mencionado en el
      chat. Probar también la condición de carrera: tomar control justo mientras el job
      está generando una respuesta del agente para un mensaje anterior.

### Fase 6 — Corte a producción
- [ ] Correr `MigrarHistoricoWhatsappSupabase` para traer el histórico existente.
- [ ] Apuntar el webhook de Twilio (en su consola) al endpoint nuevo en vez de al de
      n8n.
- [ ] Apagar el workflow de n8n.
- [ ] Actualizar `ClienteController::conversacionWhatsapp` para leer de
      `whatsapp_mensajes`.
- [ ] Retirar `SupabaseWhatsappConversationService` y la config de Supabase para
      WhatsApp ya no usada.

### Fase 7 — Fuera de este alcance (dejar anotado para después)
- [ ] Mensajes proactivos con plantillas `ContentSid` (seguimiento de casos, priorizado
      en el análisis del cuestionario de descubrimiento).
- [ ] Panel de administración para publicar nuevas versiones de `agente_prompts` sin
      deploy.
- [ ] "Meta-agente" que sugiere cambios al prompt a partir de conversaciones reales,
      con aprobación humana obligatoria antes de activarlos.
