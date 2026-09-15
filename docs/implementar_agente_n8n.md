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
- **Soporte de dos proveedores de WhatsApp — Twilio y Meta Cloud API — detrás de una
  interfaz común `App\Services\Whatsapp\WhatsappChannel`, con un switch de
  configuración (`WHATSAPP_PROVIDER=twilio|meta`).** Decisión tomada al conectar el
  webhook de producción: el despacho quiere poder evaluar/migrar entre ambos sin tocar
  código. `ProcesarMensajeWhatsappJob`, `AgenteConversacionalService` y `ToolExecutor`
  nunca conocen el proveedor concreto — reciben un `MensajeEntranteWhatsapp` ya
  normalizado y responden vía `WhatsappChannel::enviarTexto()`, sin importar cuál está
  activo. Cada proveedor difiere en varios puntos que si importan y por eso viven
  detrás de la interfaz, nunca en el job:
  - **Payload del webhook:** form-encoded plano (Twilio) vs. JSON anidado
    (`entry[].changes[].value.messages[]`, Meta).
  - **Firma:** `X-Twilio-Signature` sobre URL+parámetros (Twilio) vs.
    `X-Hub-Signature-256` HMAC-SHA256 sobre el body crudo (Meta).
  - **Handshake de webhook:** Twilio no tiene; Meta exige un `GET` de verificación
    (`hub.mode`/`hub.verify_token`/`hub.challenge`) al configurar la URL en su consola.
  - **Envío:** SDK de Twilio vs. Graph API con Bearer token (Meta).
  - **Media:** Twilio da una URL directa con Basic Auth; Meta resuelve en dos pasos
    (el id da una URL temporal + mime_type, luego se descarga esa URL).
  Ambos proveedores comparten la MISMA URL de webhook (`/api/whatsapp/webhook`, `GET` y
  `POST`) — se registra igual en la consola de Twilio y en el dashboard de Meta; cuál
  interpreta la petición lo decide el switch, no la URL.

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
- [ ] Meta: App Secret, Verify Token (uno propio, inventado por el despacho — Meta solo
      lo repite de vuelta en el handshake), Access Token (permanente, de un usuario de
      sistema — uno temporal de prueba expira en 24h), Phone Number ID. Solo hace falta
      si van a operar con `WHATSAPP_PROVIDER=meta` en algún momento; si el despacho se
      queda solo con Twilio, este punto no bloquea nada.

## Arquitectura

```
Twilio/Meta (webhook) → WhatsappWebhookController  [GET: handshake (solo Meta) — POST:
                          │                          valida firma vía WhatsappChannel
                          │                          vigente, responde 200 de inmediato]
                          ▼
                ProcesarMensajeWhatsappJob          [cola: redis/database, ya configuradas;
                          │                          recibe MensajeEntranteWhatsapp, ya
                          │                          normalizado — no sabe de qué proveedor
                          │                          vino (idempotente por mensajeId)]
                          │
                          ├── si el mensaje trae media ──► WhatsappChannel::descargarMedia()
                          │                                  → DocumentoExtraccionService
                          │                                  1) texto embebido del PDF (gratis)
                          │                                  2) si falla/pobre → visión (fallback)
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
    WhatsappChannel::enviarTexto()  ──────►  Twilio o Meta (según el switch), respuesta al cliente
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

**Canal de WhatsApp (Twilio/Meta, ver decisión de arquitectura)**
- `app/Services/Whatsapp/WhatsappChannel.php` — interfaz: `manejarHandshake()`,
  `validarFirma()`, `normalizarEntrante()`, `enviarTexto()`, `descargarMedia()`.
- `app/DataTransferObjects/MensajeEntranteWhatsapp.php` — mensaje ya normalizado
  (proveedor, mensajeId, teléfono, texto, referencias de media), lo que recibe
  `ProcesarMensajeWhatsappJob` en vez del payload crudo de un proveedor en particular.
- `app/Services/Whatsapp/Twilio/TwilioChannel.php` — implementación para Twilio; la
  validación de firma que antes vivía en un middleware ahora vive acá.
- `app/Services/Whatsapp/Meta/MetaChannel.php` — implementación para Meta Cloud API.
- `app/Http/Controllers/Api/WhatsappWebhookController.php` — agnóstico de proveedor,
  delega todo en el `WhatsappChannel` que `AppServiceProvider` enlace según
  `config('services.whatsapp.provider')`.
- `app/Jobs/ProcesarMensajeWhatsappJob.php` — recibe un `MensajeEntranteWhatsapp`.

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
  texto. **Recalcula la fase (y por tanto las tools disponibles) después de CADA tool
  call dentro del mismo turno**, no solo una vez al principio — sin esto, un tool call
  que cambia de fase a mitad de turno (ej. `declarar_formas_cliente`, que hace que
  `EstadoConversacionResolver` pase de `DeterminacionFormas` a `Recoleccion`) dejaría al
  modelo sin `consultar_pendientes_cliente` disponible en ese mismo turno, perdiendo el
  arranque inmediato de la recolección que hoy logra n8n invocando al especialista como
  tool dentro del mismo turno del orquestador (`mensaje_cliente = "iniciar recolección"`).

**Envío y medios (implementación de Twilio detrás de `WhatsappChannel`)**
- `app/Services/Whatsapp/TwilioWhatsappClient.php` — enviar texto libre (ventana de 24h).
- `app/Services/Whatsapp/TwilioMediaDownloader.php` — descarga un media de Twilio a un
  archivo local (`{ruta_local, mime_type}`) — solo descarga; la extracción de texto y la
  entrega a `EventoRecoleccionService`/`ToolExecutor::guardarCampoCliente` (con archivo
  real) las orquesta `AdjuntosWhatsappService`, invocado desde
  `ProcesarMensajeWhatsappJob` (ver Fase 4, "Punto de integración cerrado").

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
- [x] Redactar el contenido adaptado a las 4 fases (`prompt_actuales/fases/*.md`):
      `verificacion_cuenta.md`, `determinacion_formas.md`, `recoleccion.md`,
      `cierre.md`. Cambios de fondo respecto a los prompts originales, más allá de
      quitar la separación orquestador/especialista: (1) ninguna tool expone
      `cliente_id`/`tax_year` salvo `declarar_formas_cliente` — ver
      `ToolDefinitions`; (2) no existe ya el flag `recoleccion_completa` — la
      transición a Cierre la decide `EstadoConversacionResolver` por datos, nunca
      el modelo (se quitaron las secciones "CIERRE REAL"/"NUNCA ANUNCIES
      COMPLETITUD" de recolección, movidas — simplificadas — a `cierre.md`); (3)
      `Cierre` tiene las mismas tools que `Recoleccion` (ver `ToolDefinitions`), así
      que su prompt explica qué hacer si el cliente aporta algo nuevo después del
      mensaje de cierre, en vez de solo despedirse.
- [x] `AgentePromptsSeeder`: siembra los 4 archivos como `version = 1` publicada en
      `agente_prompts`, leyendo `prompt_actuales/fases/{fase}.md` por cada
      `FaseConversacion`. Idempotente (puede re-ejecutarse si los archivos cambian,
      antes de publicar una version=2 real vía el flujo normal). Encadenado en
      `DatabaseSeeder::run()`. Tests en `AgentePromptsSeederTest`.
- [x] Migración + modelo `whatsapp_mensajes` (teléfono, cliente_id nullable, rol,
      contenido, `twilio_message_sid` único, `prompt_version` nullable, timestamps).
- [x] Migración + modelo `whatsapp_control` (estado agente/humano por teléfono — ver
      ESCALAMIENTO A HUMANO), adelantada desde Fase 3 por ser también persistencia base.
- [x] `AgentePromptVigente` para resolver la versión publicada vigente por fase, igual
      que `ParametrosFiscales` (con tests en `AgentePromptVigenteTest`).

### Fase 2 — Recepción (inbound) — completa
- [x] `WhatsappChannel` (interfaz) + `TwilioChannel`/`MetaChannel` — la validación de
      firma (antes en un middleware `VerifyTwilioSignature` específico de Twilio) ahora
      vive en cada implementación, resuelta según el proveedor vigente (ver decisión de
      arquitectura sobre soportar ambos).
- [x] Ruta pública `GET|POST /api/whatsapp/webhook` + `WhatsappWebhookController`
      (invokable, agnóstico de proveedor) — `GET` es el handshake de Meta (Twilio lo
      ignora, responde 404), `POST` responde 200 de inmediato y despacha el job (nada
      síncrono).
- [x] `ProcesarMensajeWhatsappJob`: idempotencia por `mensajeId` (columna única
      `mensaje_externo_id` + `Cache::lock`) para tolerar reintentos del proveedor sin
      duplicar el procesamiento. Recibe un `MensajeEntranteWhatsapp` ya normalizado, no
      el payload crudo de un proveedor en particular.
- [x] Guarda el mensaje entrante en `whatsapp_mensajes` antes de invocar al agente, y
      resuelve/crea `whatsapp_control` (vinculando `cliente_id` si ya existe un cliente
      con ese teléfono).
- [x] Tests: `TwilioWebhookTest` (6 casos, canal Twilio vía la ruta genérica),
      `MetaWebhookTest` (5 casos: handshake válido/inválido, firma válida/inválida,
      evento sin `messages`), `MetaChannelTest` (4 casos: descarga de media,
      error de resolución, error de envío, payload correcto a la Graph API).
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
- [x] `AgenteConversacionalService`: loop de function-calling (prompt de la fase vigente
      + historial + mensaje nuevo → tool calls → resultado → repetir hasta texto final).
      Recalcula la fase al inicio de CADA iteración del loop (no solo una vez), tal como
      se decidió — verificado con test específico (`test_recalcula_la_fase_entre_tool_calls_dentro_del_mismo_turno`).
      Tope de 8 iteraciones por turno para no quedar en loop infinito si el modelo nunca
      produce texto final (con warning de log si se alcanza). 6 tests
      (`AgenteConversacionalServiceTest`).
- [x] Guardar la respuesta del agente en `whatsapp_mensajes`, incluyendo qué versión de
      prompt la generó — hecho dentro de `ProcesarMensajeWhatsappJob`.
- [x] `WhatsappControl`: migración + modelo, con estado `agente`/`humano` por teléfono —
      hecho en Fase 1 (adelantado por ser también persistencia base).
- [x] Acciones de tomar/devolver control (panel) + botón y caja de envío manual en la
      vista de conversación. `ClienteController::tomarControlWhatsapp`/
      `devolverControlWhatsapp`/`enviarMensajeWhatsapp` (autorización `update`, misma
      que el resto del panel). `conversacionWhatsapp` ahora mezcla el historial de
      Supabase con `whatsapp_mensajes` (ordenado por instante real, no por el string
      crudo de `created_at`) y devuelve el estado de control vigente. Frontend en
      `WhatsappConversationDialog` (`clientes/show.tsx`): barra de estado + botón
      tomar/devolver, caja de envío visible solo en modo `humano`, burbuja propia para
      mensajes de `preparador`. 7 tests (`WhatsappHandoffTest`) + eslint/prettier/tsc
      en verde.
- [x] `ProcesarMensajeWhatsappJob` respeta el estado de control: no invoca al agente en
      modo `humano`, y vuelve a comprobar el estado justo antes de enviar la respuesta
      del agente (`$control->fresh()->esHumano()`, condición de carrera con una toma de
      control a mitad de proceso). El job ahora invoca `AgenteConversacionalService` con
      el historial completo, actualiza `WhatsappControl.cliente_id` si `crear_cliente_taxes`
      corrió a mitad del turno, envía la respuesta vía `TwilioWhatsappClient`, y la
      persiste. **Límite conocido, documentado en el propio job:** si el job falla
      después de guardar el mensaje entrante pero antes de terminar de enviar la
      respuesta, un reintento del job (no de Twilio) no reprocesaría ese mensaje — haría
      falta un patrón outbox (estado explícito "respuesta enviada") para cerrar del todo
      ese caso; se deja como deuda conocida, no se resuelve en esta fase.

### Fase 4 — Extracción de documentos, envío y medios
- [x] `PdfTextExtractorService` (Nivel 1) con `smalot/pdfparser` y heurística de calidad
      (longitud mínima + proporción de caracteres alfanuméricos). No implementa el
      sub-paso opcional "texto_pdf_normalizado" — el plan lo marca como opcional; el
      enum `MetodoExtraccionDocumento::TextoPdfNormalizado` ya existe para cuando se
      implemente.
- [x] `DocumentoVisionExtractorService` (Nivel 2, respaldo), con rasterizado de páginas
      PDF vía `pdftoppm` (poppler-utils) — requisito de infraestructura nuevo: **el host
      que corre la cola necesita `poppler-utils` instalado** (a diferencia del Nivel 1,
      deliberadamente sin binario externo). Lanza una excepción clara si no está
      disponible, en vez de fallar en silencio. Confirmado en producción (2026-09-15) que
      faltaba en el runtime de Dokploy — agregado al `Dockerfile` (stage runtime, mismo
      que sirve web/worker/reverb) y verificado (`pdftoppm -v`) ya disponible en el
      worker tras el rebuild. `OpenAiClient::transcribirImagen()` nuevo,
      reutiliza `completarChat()` (la API de OpenAI acepta `image_url` en el mismo
      endpoint de chat completions).
- [x] `DocumentoExtraccionService` orquesta ambos niveles (Nivel 1 solo si el archivo es
      PDF; si no da texto útil o el archivo ya es imagen, cae a Nivel 2) y devuelve
      `metodo_extraccion` junto con el texto. Migración + cast nuevo en `Documento`
      (`metodo_extraccion`, enum `MetodoExtraccionDocumento`).
- [x] `TwilioMediaDownloader::descargar(string $url)`: descarga un media de Twilio con
      Basic Auth (`services.twilio.account_sid`/`auth_token` — Twilio exige esto para
      `MediaUrlN`, a diferencia del webhook en sí) a un archivo local
      (`{ruta_local, mime_type}`, el mime real de la respuesta, no un campo del payload
      — ver decisión de arquitectura del canal). Solo descarga: la extracción de texto
      es responsabilidad de quien orquesta (`DocumentoExtraccionService`, invocado por
      quien conecte esto al job). Expone además `comoArchivoSubido()` para envolver el
      archivo ya descargado como un `Illuminate\Http\UploadedFile` real (`test: true`,
      evita el chequeo `is_uploaded_file()` — el archivo no llegó por un POST HTTP, ya
      está en disco). Es la implementación Twilio detrás de
      `WhatsappChannel::descargarMedia()` — `MetaChannel::descargarMedia()` hace el
      equivalente para Meta (resolución en dos pasos: id → URL temporal + mime_type →
      descarga).
- [x] `TwilioWhatsappClient::enviarTexto()` — usa el SDK de Twilio inyectado (nuevo binding
      explícito en `AppServiceProvider::register()`, en vez de dejar que el contenedor
      auto-resuelva `Twilio\Rest\Client` por reflexión, que caería a leer variables de
      entorno crudas si no se le pasan credenciales).
- [x] `ToolExecutor::ejecutar()`/`guardarCampoCliente()` extendidos para aceptar un
      `?UploadedFile $file` y un `?MetodoExtraccionDocumento $metodoExtraccion` opcionales
      — cuando `guardar_campo_cliente` llega con modo="archivo", el archivo real ya
      resuelto se pasa acá, y si se guardó correctamente, el `Documento` resultante queda
      con su `metodo_extraccion`.

- [x] **Punto de integración cerrado.** `AdjuntosWhatsappService` (nuevo) resuelve
      `MensajeEntranteWhatsapp::$mediaReferencias` — agnóstico de proveedor, solo conoce
      `WhatsappChannel` — descargando cada una y pasándola por
      `DocumentoExtraccionService::extraer()`; una falla en UNA referencia (proveedor
      caído, media expirado) se registra con `Log::warning()` y se omite, nunca tumba el
      turno completo (mismo principio que el fix de `WhatsappMensajeObserver`).
      `ProcesarMensajeWhatsappJob` la invoca antes de guardar el mensaje entrante y anota
      el texto extraído directamente en `WhatsappMensaje.contenido` con el formato
      `archivo_url`/`texto_extraido` (`prompt_actuales/fases/recoleccion.md`, RECEPCIÓN DE
      DOCUMENTOS) — como ya queda en la fila persistida, cualquier turno futuro que
      re-lea el historial lo sigue viendo, no solo el turno en que llegó.

      La correlación "cuál de los N documentos del mensaje" con el tool call del modelo
      se resolvió por **referencia explícita**, no por posición: `AdjuntoWhatsapp::$referencia`
      es la misma URL/media-id que se le mostró como `archivo_url`, y el modelo la repite
      tal cual en `contenido` al invocar `guardar_campo_cliente` con `modo="archivo"` — así
      que `AgenteConversacionalService::resolverArchivo()` la busca por igualdad exacta
      dentro de `$adjuntos`, construye un `UploadedFile` sintético (extensión derivada del
      `mime_type` real, nunca de un nombre de archivo — WhatsApp no manda uno útil) y lo
      pasa a `ToolExecutor::ejecutar(..., file:, metodoExtraccion:)`.

      Bug real encontrado y corregido de paso: `EventoValidator` no exigía `$file` para
      `modo="archivo"` — si `resolverArchivo()` no encontraba match (adjunto no descargado,
      referencia inventada por el modelo), `EventoRecoleccionService::procesarArchivo()`
      truena con un `TypeError` (espera un `UploadedFile` no nulo) en vez de fallar como
      error de validación recuperable. Ahora `EventoValidator` lo marca como error de
      `file` explícito.

      Tests: `AdjuntosWhatsappServiceTest`, casos nuevos en `AgenteConversacionalServiceTest`
      (correlación exitosa y sin match) y en `EventoValidatorTest`, y un test end-to-end en
      `TwilioWebhookTest` (`NumMedia=1` real → `Documento` creado con `metodo_extraccion`
      correcto) — este último obligó a mover `fakeAgenteConversacional()` de `setUp()` a
      cada test: `Http::fake()` resuelve por orden de REGISTRO, no el último llamado, así
      que un fake genérico en `setUp()` le ganaba siempre a una secuencia armada dentro
      de un test individual.

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
- [x] ~~Panel de administración para publicar nuevas versiones de `agente_prompts` sin
      deploy.~~ Adelantado — ver "Panel de administración del agente" más abajo.
- [ ] "Meta-agente" que sugiere cambios al prompt a partir de conversaciones reales,
      con aprobación humana obligatoria antes de activarlos.

## Panel de administración del agente

Adelantado desde Fase 7 (decisión tomada al conectar el webhook de producción: el
despacho quiere poder ver/gestionar el agente sin depender de un deploy). Sección nueva
del panel, `/agente/*`, exclusiva de administradores (`WhatsappMensajePolicy` y las
policies equivalentes de cada pieza) — mismo patrón de layout con tabs que
`settings/*` (`AgenteLayout`, análogo a `SettingsLayout`).

- [x] **Bandeja de mensajes** (`/agente/mensajes`) — todo lo recibido/enviado por el
      agente, de cualquier cliente, últimos 30 días/500 filas (mismo límite que
      `BitacoraController`) — sirve como log del webhook y para validar comportamiento.
      `AgenteMensajesController`, `WhatsappMensajePolicy`, DataTable con filtro por rol.
      4 tests (`AgenteMensajesTest`).
- [x] **Editor de prompts** (`/agente/prompts`) — ver contenido vigente por fase, editar,
      guardar como borrador, y publicar como nueva versión de `agente_prompts` (mismo
      concepto que `AgentePromptsSeeder`, pero desde la UI en vez de archivo+seeder).
      `AgentePromptController`, `AgentePromptPolicy`. Siempre hay a lo sumo UN borrador
      en curso (la versión inmediatamente siguiente a la vigente, sin publicar) —
      guardar reutiliza esa misma versión en vez de crear una nueva en cada guardado;
      publicar marca las 4 fases de esa versión como vigentes de una sola vez (nunca
      fila por fila, para no dejar una fase huérfana sin contenido). Confirmación
      explícita antes de publicar (cambia el comportamiento en vivo de todas las
      conversaciones en curso). 7 tests (`AgentePromptEditorTest`).
- [x] **Tools por fase** (`/agente/tools`) — catálogo de solo lectura (`ToolDefinitions::
      paraFase()` sigue siendo código, no se crean tools nuevas desde la UI) con un
      interruptor activo/inactivo por fase, respaldado por `agente_tool_estados`
      (`App\Support\AgenteToolEstados`, mismo patrón de caché de una sola clave que
      `AgentePromptVigente`). Sin fila = activa, así que una instalación nueva se
      comporta igual que antes de este panel. Recoleccion/Cierre se togglean siempre
      juntas — nunca pueden divergir, según ya documenta el propio comentario de
      `ToolDefinitions::paraFase()`. `AgenteToolController`, `AgenteToolPolicy`. 10 tests
      (`AgenteToolEstadosTest`, `AgenteToolControllerTest`).
- [x] **Base de conocimiento** (`/agente/base-conocimiento`) — subir PDFs, convertirlos a
      Markdown (más eficiente que mandarle PDF crudo a un modelo), y una tool nueva
      (`consultar_base_conocimiento`) que el agente invoca bajo demanda con búsqueda de
      texto simple sobre esos `.md` — nunca se le agrega todo el contenido al prompt de
      sistema en cada turno (no escala en costo/tamaño a medida que se suben más
      documentos). Descartado por ahora: búsqueda semántica con embeddings/pgvector — más
      trabajo de infraestructura del que se justifica hoy; se puede reevaluar si la
      búsqueda por texto no da resultados suficientemente buenos en la práctica.
      `AgenteBaseConocimientoController`, `BaseConocimientoPolicy` (administrador, igual
      que las 3 piezas anteriores). `BaseConocimientoService` guarda el PDF en disco
      (`base_conocimiento/{uuid}.pdf`, disco `local` privado, misma convención que
      `documentos/`) y extrae su texto con `PdfTextExtractorService` ya existente (solo
      Nivel 1 — es una carga manual de un administrador, no un escaneo de cliente, así
      que no cae a Nivel 2/visión); el resultado se guarda directo en la columna
      `contenido_markdown`, no como segundo archivo en disco (sin I/O de disco en cada
      búsqueda). Un PDF sin capa de texto útil queda `estado = error` con
      `error_mensaje`, nunca se descarta en silencio. `consultar_base_conocimiento` se
      conecta a `ToolDefinitions`/`ToolExecutor`/`AgenteToolEstados` igual que el resto de
      tools (togglea por fase desde el panel de Tools) — solo en Recoleccion/Cierre por
      ahora. Búsqueda: puntaje simple por apariciones del término por párrafo, sobre los
      documentos en `estado=procesado`, sin embeddings (confirma la decisión de arriba).
      14 tests (`BaseConocimientoServiceTest`, `AgenteBaseConocimientoControllerTest`,
      más los ajustes de `ToolDefinitionsTest`/`ToolExecutorTest` para la tool nueva).
