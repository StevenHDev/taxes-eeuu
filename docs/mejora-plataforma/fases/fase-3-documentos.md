# Fase 3 — Checklist de documentos: plan técnico

2 nov. 2026 · **Aprobado** por Steven (2 nov.), con las cinco recomendaciones · Documento madre: [secciones 2, 5, 7.5 y 9.6](../documento-madre.md), pendientes P5 y T-e de la [sección 10](../documento-madre.md#10-decisiones-pendientes)

> **Objetivo:** que el cliente vea **solo sus documentos**, uno por cada fuente ("2 empleos" son dos W-2), con su estado, por qué se piden, quién los envía, cuándo llegan y qué hacer si no llegan. El cliente puede subir varios archivos, marcar "no aplica" con un motivo y subir un "otro documento". El preparador ve qué falta, valida cada documento o lo devuelve con una observación, y acepta o rechaza los "no aplica". A quien le falta algo se le recuerda por correo y por WhatsApp (con plantilla aprobada), sin nombrar los documentos fuera del portal.
>
> **Resultado que se busca (9.5):** el cliente ve solo sus documentos y el preparador ve qué falta.
>
> **Buenas prácticas:** skills `laravel-best-practices`, `react-best-practices` y `vercel-react-best-practices`; cada tarea cita la regla que aplica. Reglas de interfaz de Steven (carga con skeleton, responsive, jerarquía, consistencia con `components/ui`, accesibilidad, estados vacío/error/éxito, estados de interacción).
>
> **Modelo por tarea:** cada punto dice si conviene **Opus 5.5** (diseño, datos de clientes, seguridad y §7216, prompts, reglas con muchas dependencias) o **Sonnet 5.5** (pantallas que siguen un patrón ya hecho, contenido con formato conocido, pruebas de comportamiento ya definido, documentación). Si un punto mezcla las dos cosas, se parte.
>
> **Base:** `main` al 2 nov. (F0 menos F0.4, F1 y F2 en producción). Hoy existe la lista mínima de 2.6: una fila por documento activado (y por persona), con "Falta" o "Recibido (N archivos)", carga cifrada y sin estados.

## Resumen

| # | Tarea | Sale de | Tamaño | Riesgo | Modelo |
| --- | --- | --- | --- | --- | --- |
| 3.1 | Contenido de cada documento en el catálogo: quién lo envía, cuándo llega, si no llega (T-e) | 5.2, T-e | Mediano | Bajo | Sonnet 5.5 |
| 3.2 | Checklist guardado: un ítem por documento y por fuente, con estado | 5.1, 5.3 | Grande | Medio: es la base de la fase | Opus 5.5 |
| 3.3 | Checklist del cliente en el portal: estados, varios archivos, "no aplica" y "otro documento" | 5.1–5.3 | Mediano | Bajo | Sonnet 5.5 |
| 3.4 | Documentos en la ficha del preparador: validar, observación, aceptar o rechazar "no aplica" | 5.3, 9.7 | Mediano | Medio | Opus 5.5 (reglas) + Sonnet 5.5 (pantalla) |
| 3.5 | El agente sabe qué falta y lo explica | 9.6 | Pequeño | Medio: prompt | Opus 5.5 |
| 3.6 | Recordatorios de documentos pendientes (correo y WhatsApp con plantilla) | 5.4, 7.5 | Mediano | Medio: §7216 y envío a clientes reales | Opus 5.5 |
| 3.7 | Plantilla de registros propios (efectivo, apps sin 1099-K, alquileres, gastos) | 5.1.6, P5 | Pequeño | Bajo; ver pregunta 2 | Sonnet 5.5 |

Orden sugerido: 3.1 → 3.2 → 3.3 → 3.4 → 3.5 → 3.6 → 3.7. 3.1 y 3.7 no dependen de nada y se pueden adelantar. Cada una se despliega y se revisa en producción antes de la siguiente.

---

## 3.1 Contenido de cada documento en el catálogo (T-e)

**Modelo: Sonnet 5.5** (contenido con formato conocido, con fuente del IRS por dato).

**Hoy:** de los 42 documentos del catálogo 2025, solo el W-2 y el 1095-A traen quién lo envía, cuándo llega y qué hacer si no llega. El resto está en `null`. El texto en inglés de esos tres campos tampoco está.

**Enfoque:**
- Llenar `emisor_es/en`, `fecha_llegada` y `si_no_llega_es/en` de los 40 documentos que faltan, verificando cada fecha en las instrucciones del IRS de 2025 (instrucciones de los 1099, del SSA-1099, del 1098, etc.). Cada dato lleva su fuente en `fuente_texto`/`fuente_url`.
- Los documentos que no "llegan" (identificación, tarjeta del SSN, registros propios, recibos) quedan sin fecha: no se recuerdan por fecha (3.6).
- Se cargan como **sin validar** para revisarlos en "Catálogo fiscal del año → Documentos" (F1/F2.1). 2026 los hereda.
- Recarga en producción con `catalogo-fiscal:cargar 2025` y `2026` (con autorización de Steven).

**Pruebas:** `catalogo-fiscal:verificar` sin problemas; una prueba que exige que todo documento con `fecha_llegada` tenga fuente.

**Cómo quedó (2 nov., subagente Sonnet 5.5 + revisión):**
- 21 documentos con fecha de llegada al cliente, verificada en las [instrucciones generales de 2025 para los formularios de información](https://www.irs.gov/instructions/i1099gi):
  - 2 de febrero de 2026: 1099-NEC, 1099-K, 1099-MISC, 1099-R, 1099-G, 1099-INT, 1099-DIV, W-2G, 1099-C, 1098, 1098-E, 1098-T y 1099-SA;
  - 17 de febrero de 2026: 1099-B, 1099-S y 1099-DA. El 1099-MISC también cae el 17 si solo trae las casillas 8 o 10, y así lo dice su fuente;
  - 1 de junio de 2026: 5498-SA;
  - 16 de marzo de 2026: K-1, según las instrucciones del 1065 y del 1120-S;
  - SSA-1099: 31 de enero de 2026. Es la política de envío del Seguro Social (manual POMS GN 05002.220), no un plazo del IRS, y así quedó escrito;
  - W-2 y 1095-A: se mantienen.
- Sin fecha (no tienen plazo de entrega al cliente): identificación, tarjetas, registros propios, recibos, datos bancarios, carta del IRS, declaración anterior, etc. El estado de cuenta de la IRA (5498) también queda sin fecha: tiene dos plazos (2 de febrero y 1 de junio) según lo que informe.
- Los 42 documentos tienen quién lo envía y qué hacer si no llega, en español y en inglés.
- Prueba nueva en `CatalogoRealTest`: todo documento con fecha dice quién lo envía y de dónde sale la fecha, y todos dicen qué hacer si no llegan.
- **Producción (2 nov.):** catálogo 2025 y 2026 recargado (42 documentos actualizados); la verificación pasa en los dos años.

---

## 3.2 Checklist guardado: un ítem por documento y por fuente

**Modelo: Opus 5.5** (diseño de datos, migración de documentos ya subidos, reglas de sincronización con el motor).

**Hoy:** la lista se calcula en cada visita a partir de `SituacionEntrevista::documentos` y no guarda nada: no hay dónde poner un estado, un motivo de "no aplica" ni una observación. "2 empleos" sigue siendo **un** W-2.

**Enfoque** (reglas `arch-service-classes`, `eloquent-relationships`, `eloquent-casts`, `eloquent-soft-deletes`):
- **Tabla nueva `entrevista_documentos`** (un ítem del checklist): `entrevista_id`, `documento_codigo` (o `otro`), `persona_id`, `numero` (1, 2… dentro del mismo documento y persona), `descripcion` (solo en "otro" o cuando el cliente lo nombra: "W-2 de Walmart"), `estado`, `motivo_no_aplica`, `observacion`, `revisado_por`, `revisado_en`, `retirado_en`, timestamps. Único por `entrevista_id + documento_codigo + persona_id + numero`.
- **`documentos.entrevista_documento_id`**: cada archivo queda ligado a su ítem. Las columnas de 2.6 (`documento_codigo`, `persona_id`) se conservan.
- **Enum `EstadoDocumento`** (5.3): `pendiente`, `subido`, `leido` y `no_se_pudo_leer` (estos dos los usa la fase 4), `con_observacion`, `validado`, `no_aplica` y `no_aplica_rechazado`.
- **`ChecklistDocumentos::sincronizar(Entrevista)`**, llamado por `RespuestaGuardador` después de cada respuesta (donde hoy sincroniza las formas) y al abrir el checklist:
  - crea los ítems que faltan para cada documento activado;
  - **uno por fuente:** el W-2 (`uno_por = empleo`) crea tantos ítems como diga D1.1, y el 1095-A (`poliza`) tantos como F4.1. Los de `fuente` empiezan con uno y el cliente agrega más ("Agregar otro banco");
  - un ítem que deja de estar activado (el cliente cambió una respuesta) **no se borra**: se marca `retirado_en`, sus archivos quedan y vuelve si se activa de nuevo;
  - nunca toca los ítems `otro`.
- **Transiciones** en un solo servicio (`ChecklistDocumentos`), cada una con quién la puede hacer:
  - subir un archivo: `pendiente`, `con_observacion` o `no_aplica_rechazado` → `subido` (cliente);
  - "no aplica" con motivo obligatorio: `pendiente` → `no_aplica` (cliente). Ver la pregunta 4;
  - validar: `subido` → `validado` (preparador);
  - observación con texto obligatorio: `subido` o `validado` → `con_observacion` (preparador);
  - rechazar el "no aplica" con motivo: `no_aplica` → `no_aplica_rechazado` (preparador).
  Cada cambio queda en la bitácora (`AuditoriaObserver`).
- **`completo()`:** todos los ítems vigentes están `validado` o `no_aplica` (no rechazado). Lo usa la fase 5 para "Listo para preparar"; aquí solo lo muestra el preparador.
- **Migración de datos:** por cada entrevista existente se corre `sincronizar()` y los archivos de 2.6 se ligan a su ítem (mismo `documento_codigo` y persona, número 1). Un ítem con archivos queda `subido`.

**Pruebas:** D1.1 = 2 crea dos W-2 y cambiar a 1 retira el segundo sin borrar sus archivos; C0 = sí con dos personas crea dos tarjetas; cada transición permitida funciona y las no permitidas fallan; un cliente no puede validar; "otro" no se retira; la migración liga los archivos viejos a su ítem.

**Cómo quedó (2 nov., Opus 5.5):**
- **Datos:** tabla `entrevista_documentos` (con `agregado_por_cliente` para lo que suma el cliente: "otro banco", "otro empleo" u "otro documento") y `documentos.entrevista_documento_id`. El índice único no frena duplicados con `persona_id` nulo en PostgreSQL; eso lo evita el bloqueo de la entrevista en cada escritura.
- **`EstadoDocumento`** con `resueltoParaElCliente()` (decisión 4: "no aplica" cuenta apenas se marca) y `cerrado()` (validado o "no aplica").
- **`ChecklistDocumentos`:** `sincronizar()`, `vigentes()`, `completo()`, `primero()`, `agregar()` y las transiciones `archivoSubido()`, `marcarNoAplica()`, `validar()` (exige archivos), `observar()` y `rechazarNoAplica()`. Una transición no permitida lanza `TransicionNoPermitida` sin escribir nada. Subir un archivo a un documento validado lo devuelve a "subido" para que el preparador lo vea.
- **Conexiones:** `RespuestaGuardador` sincroniza el checklist junto con las formas después de cada respuesta y al quitar una persona; la página de documentos lo sincroniza al abrirse; la carga de 2.6 sube al primer ítem vigente del documento (3.3 pasa a elegir el ítem). Los ítems van a la bitácora.
- **Comando** `documentos:sincronizar` para las entrevistas que ya existían: arma el checklist y liga los archivos de 2.6 al ítem número 1. Se puede correr de nuevo. En local ligó 2 archivos.
- **Pruebas:** `Entrevista/ChecklistDocumentosTest` (8). Suite: 622 pruebas, 620 pasan y 2 se saltan.
- **Producción (2 nov.):** migración aplicada, sin errores en el log; `documentos:sincronizar` armó el checklist de la única entrevista que hay (no tenía archivos que ligar).

---

## 3.3 Checklist del cliente en el portal

**Modelo: Sonnet 5.5** (pantalla sobre el patrón de 2.6 y las reglas ya definidas en 3.2).

**Hoy:** `portal/entrevista/documentos` y `documento-fila` (2.6) muestran "Falta" o "Recibido".

**Enfoque** (reglas `controller-form-requests`, `server-serialization`, `rerender-memo`, `bundle-barrel-imports`):
- Cada fila muestra (5.2): nombre sencillo y nombre oficial, la persona o el número ("W-2 #2"), por qué se pide, quién lo envía, cuándo debe llegar, qué hacer si no llega, formatos, archivos subidos y la **insignia de estado** con ícono y texto.
- Acciones: **Subir** / **Agregar otro archivo**; **No aplica** (diálogo con motivo obligatorio); **Agregar otro (otro banco, otro empleo)** en los de `fuente` y `empleo`; **Otro documento** al final (descripción + archivo).
- Lo que tiene observación del preparador va **arriba**, con el texto de la observación y "Subir de nuevo".
- Un "no aplica" rechazado vuelve a pedirse, con el motivo del preparador.
- El inicio de la entrevista y "Mis documentos" muestran cuántos faltan; el resumen de 2.7 sigue sin bloquear la confirmación por documentos.
- `SubirDocumentoRequest` pasa a recibir el ítem (`entrevista_documento_id`) en vez de código y persona; `MarcarNoAplicaRequest` y `OtroDocumentoRequest` nuevos.

**Interfaz:** una lista agrupada (identidad primero, luego por grupo), cada fila en una tarjeta que en el celular pasa a una columna; la acción principal de la página es "Volver a la entrevista" o "Revisar y confirmar". Skeleton mientras carga; vacío ("Todavía no hay documentos que pedirte: responde la entrevista"); errores junto a la fila; toast al subir y al marcar "no aplica".

**Pruebas:** cada fila trae su información y estado; subir pasa a `subido`; "no aplica" sin motivo falla; "otro documento" crea un ítem `otro`; agregar otro W-2 crea el número siguiente; un cliente no ve ni toca ítems de otro.

**Cómo quedó (2 nov., subagente Sonnet 5.5 + revisión con Opus 5.5):**
- **Lista por ítem:** `DocumentosEntrevista::lista()` arma una fila por ítem vigente. Primero va lo que el preparador devolvió, después identidad, grupo, orden, persona y número, y los "otro" al final. Cada fila trae nombre sencillo y oficial, `#n` (solo si hay más de uno), por qué, quién lo envía, cuándo llega, qué hacer si no llega, formatos, estado, motivo, observación y archivos. Si la entrevista todavía no tiene ítems, `lista()` sincroniza una vez.
- **Rutas** (con `throttle:30,1`), cada una con su request que carga el ítem solo de la entrevista del cliente (404 si es de otro o ya no está vigente):
  - `POST documentos/{item}/archivos`;
  - `POST documentos/{item}/no-aplica`;
  - `POST documentos/{item}/otro` ("agregar otro de este documento", solo en `fuente`, `empleo` y `poliza`);
  - `POST documentos/otro` (descripción y archivos).
  Se retiró `POST documentos/{codigo}`.
- **Interfaz:** la fila (`documento-fila`, también en la pantalla 1 y en cada persona) muestra la insignia con ícono y texto de los 8 estados, el diálogo de "no aplica" con motivo obligatorio, la nota de lo devuelto por el preparador y la tarjeta "Otro documento". El inicio, "Mis documentos" y el agente cuentan solo lo que el cliente todavía tiene que hacer.
- **Revisión en el navegador (cliente de prueba local con 2 empleos, intereses y una persona):** se corrigieron cuatro detalles:
  - las acciones en el celular quedaban desalineadas;
  - después de "no aplica" el botón decía "Agregar otro archivo" sin haber archivos;
  - había dos botones principales (ahora "Subir otro documento" es secundario);
  - el ejemplo del motivo hablaba de un empleador en cualquier documento.
- **Pruebas:** `Portal/DocumentosTest` (12 nuevas o cambiadas). Suite: 631 pruebas, 629 pasan y 2 se saltan.

---

## 3.4 Documentos en la ficha del preparador

**Modelo: Opus 5.5** para los permisos y las transiciones del lado del preparador; **Sonnet 5.5** para la pantalla (sigue el patrón de la sección Entrevista de 2.9).

**Hoy:** la sección Entrevista de la ficha (2.9) lista los documentos con sus archivos, sin estados ni acciones.

**Enfoque** (reglas `controller-resource-controllers`, `controller-form-requests`, `eloquent-eager-loading`):
- Bloque **Documentos** dentro de la sección Entrevista (la ficha en pestañas es la fase 5), con un resumen arriba: "Faltan 3 · Por revisar 2 · Validados 5 · No aplica 1".
- Por ítem: estado, archivos (vista previa con enlace firmado), motivo de "no aplica", y acciones **Validar**, **Observación** (diálogo con texto obligatorio que el cliente verá) y **Rechazar no aplica** (con motivo).
- Rutas `PATCH clientes/{cliente}/entrevista/documentos/{item}` con `ClientePolicy@update` (solo sus clientes) y requests propios.
- Lista de clientes: columna **Documentos** con "faltan N" o "completo", para ver de un vistazo qué falta.

**Interfaz:** acciones de solo ícono con `aria-label` que nombra el documento y tooltip; diálogos con `processing`; toast al guardar; la observación se ve igual a como la verá el cliente.

**Pruebas:** validar, observar y rechazar cambian el estado y quedan en la bitácora; un preparador no toca documentos de clientes ajenos; la observación aparece en el portal del cliente; la columna de la lista cuenta bien.

**Cómo quedó (2 nov., reglas con Opus 5.5 y pantalla con un subagente Sonnet 5.5, revisado en el navegador):**
- **Reglas:** ruta `PATCH clientes/{cliente}/entrevista/documentos/{item}` con `RevisarDocumentoRequest`, que exige que el ítem sea de ese cliente y la política `update` (solo sus clientes). Las acciones son `validar`, `observar` y `rechazar_no_aplica`; las dos últimas exigen texto. Las reglas de cada cambio son las de `ChecklistDocumentos`, y un cambio no permitido vuelve como error.
- **Datos:** `EstadoDocumento::grupo()` (faltan, por revisar, validados, no aplica) y `ChecklistDocumentos::resumenPorCliente()`, una sola consulta para la ficha y la lista. La ficha recibe por ítem el estado, el motivo, la observación, quién lo revisó y cuándo, además de `documentos_resumen`.
- **Pantalla:**
  - **Ficha:** resumen arriba e insignia de estado (la misma del portal). Acciones de solo ícono según el estado: validar (solo con archivos), observación y rechazar "no aplica", con diálogo, texto obligatorio, avance y aviso al terminar.
  - **Lista de clientes:** columna "Documentos" ("Faltan N · N por revisar", "Completo" o "—"), que se puede ordenar.
- **Visto en el navegador:** después de cada acción la ficha volvía arriba, porque la entrevista llega diferida y pasaba por el esqueleto. Ahora las acciones de la sección (incluido "Corregir" de 2.9) recargan solo `entrevista` y la posición se mantiene.
- **Pruebas:** `Entrevista/RevisionDocumentosTest` (5). Suite: 636 pruebas, 634 pasan y 2 se saltan.

---

## 3.5 El agente sabe qué falta y lo explica

**Modelo: Opus 5.5** (prompts del agente).

**Enfoque:**
- `consultar_entrevista` suma `documentos`: cuántos faltan, cuántos tienen observación y, por cada uno pendiente, el nombre sencillo, por qué se pide, quién lo envía, cuándo llega y qué hacer si no llega. **Nunca** el contenido de los archivos.
- Prompts `seguimiento` y `portal_dudas`: responder "¿qué me falta?", "¿dónde consigo mi SSA-1099?" o "no me llegó el W-2" con esa información y el enlace a "Mis documentos". El agente sigue sin recibir archivos (2.8).
- Borrador nuevo de prompts con `agente:borrador-prompts --fases=seguimiento,portal_dudas` para que Steven lo publique.

**Pruebas:** `consultar_entrevista` trae los pendientes sin valores sensibles; con todo validado dice que no falta nada.

---

## 3.6 Recordatorios de documentos pendientes

**Modelo: Opus 5.5** (reglas de envío a clientes reales, §7216 y la circular de septiembre).

**Hoy:** no hay recordatorios. El agente solo responde cuando el cliente escribe; fuera de las 24 horas WhatsApp exige una plantilla aprobada (`ContentSid` en Twilio) que todavía no existe.

**Enfoque** (reglas `arch-action-classes`, `eloquent-chunking`):
- Comando programado `documentos:recordar` (diario, `onOneServer`, `withoutOverlapping`, como el del meta-agente).
- **A quién:** clientes con ítems `pendiente`, `con_observacion` o `no_aplica_rechazado`, que firmaron el consentimiento y cuya conversación de WhatsApp no está en manos de un preparador ("tomar control").
- **Qué cuenta como pendiente para recordar:** solo los documentos cuya fecha de llegada ya pasó (o que no tienen fecha) (5.4).
- **Frecuencia:** ver la pregunta 1. Propuesta: el primero 3 días después de terminar la entrevista, después cada 7 días, máximo 4; se detiene si el cliente sube algo o si ya no falta nada.
- **Canales:**
  - **correo** (`RecordatorioDocumentos`, notificación de Laravel);
  - **WhatsApp** con plantilla aprobada, solo si existe `ContentSid` en la configuración. Hasta entonces, solo correo.
- **Qué dice:** ni el correo ni el WhatsApp nombran documentos ni datos fiscales (circular de septiembre y §7216): "Te faltan 3 documentos para tu declaración. Súbelos en tu portal seguro: {enlace}". Los nombres solo se ven en el portal.
- **Registro:** tabla `recordatorios` (cliente, canal, cuántos faltaban, enviado_en) para la frecuencia y para medir (7.6).
- **Prueba en producción** primero con usuarios de prueba (`--solo-prueba`).

**Pruebas:** recuerda solo a quien le falta algo con fecha vencida; respeta la frecuencia y el máximo; no recuerda a quien está en manos de un preparador; sin `ContentSid` no manda WhatsApp; el texto no incluye nombres de documentos.

---

## 3.7 Plantilla de registros propios

**Modelo: Sonnet 5.5** (contenido y descarga, sin reglas nuevas).

**Hoy:** cuando no hay formulario (efectivo, apps sin 1099-K, alquileres, gastos del negocio) el catálogo pide `registros-ingresos-gastos` o `registros-alquiler`, pero no hay una plantilla.

**Enfoque:** una plantilla descargable (Excel y PDF) con totales por categoría del Schedule C y del Schedule E, enlazada desde la fila del documento ("Descargar plantilla"). Depende de la pregunta 2 (P5).

**Pruebas:** el enlace aparece solo en esos documentos y descarga el archivo.

---

## Reglas de interfaz comunes

| Regla | Cómo se cumple |
| --- | --- |
| Carga | Skeleton con la forma de la lista (`Deferred` en la ficha) |
| Responsive | Cada ítem es una tarjeta; en el celular las acciones van debajo |
| Jerarquía | Una acción principal por página; lo que tiene observación, arriba |
| Consistencia | `components/ui` (Badge, Dialog, Button, Tooltip), la misma `documento-fila` en el portal y el mismo estilo de la sección Entrevista en la ficha |
| Accesibilidad | Insignias con texto e ícono (no solo color), `aria-label` con el nombre del documento, errores ligados al campo |
| Estados | Vacío, error y éxito (toast) en cada acción; hover, focus, active y disabled en botones |

## Migración de datos existentes

| Qué | Cómo | Se puede repetir |
| --- | --- | --- |
| Contenido del catálogo (3.1) | `catalogo-fiscal:cargar 2025` y `2026` | Sí |
| `entrevista_documentos` (3.2) | Migración que crea la tabla; comando `documentos:sincronizar` que arma los ítems de cada entrevista y liga los archivos de 2.6 | Sí (salta lo que ya existe) |
| `documentos.entrevista_documento_id` (3.2) | Columna nueva nula | — |
| `recordatorios` (3.6) | Tabla nueva | — |

Ningún archivo se borra; los ítems que dejan de aplicar se marcan como retirados.

## Despliegue

Como en F2: push a `main`, revisar las migraciones en el contenedor, recargar el catálogo si cambió, crear el borrador de prompts si cambiaron (Steven lo publica), y probar con un usuario de prueba en el celular. Para 3.6, además: Steven crea la plantilla de WhatsApp en Twilio y la manda a aprobar; mientras tanto solo sale el correo. Confirmar que el `scheduler` corre en producción (ya corre el del meta-agente).

## Listo cuando

- El cliente ve solo sus documentos, uno por fuente, con por qué, quién lo envía, cuándo llega y qué hacer si no llega.
- Puede subir varios archivos, marcar "no aplica" con motivo y subir un "otro documento".
- El preparador valida, devuelve con observación y acepta o rechaza los "no aplica", y ve en la lista qué falta.
- El agente explica qué falta sin recibir documentos.
- A quien le falta algo se le recuerda por correo (y por WhatsApp cuando la plantilla esté aprobada) sin nombrar documentos.
- La suite de Pest pasa, Pint sin cambios, `npm run build` y `types:check` compilan.

## Fuera de esta fase

- Leer los documentos por casilla y los estados `leido` / `no_se_pudo_leer` (fase 4).
- Convertir HEIC a JPG para verlo en el navegador (fase 4, junto con la lectura).
- Estados del expediente ("Listo para preparar") y avisos al preparador por correo (fase 5).
- Cliente que vuelve: mostrar lo que entregó el año pasado (fase 7). Ver la pregunta 5.
- Imagen de ejemplo de cada documento. Ver la pregunta 3.

## Preguntas para Steven

1. **Frecuencia de los recordatorios.** ¿Te sirve: el primero 3 días después de terminar la entrevista, después cada 7 días, máximo 4, y se detiene si sube algo? **Recomiendo eso**, y empezar solo con correo hasta que la plantilla de WhatsApp esté aprobada en Twilio (la crea quien administra la cuenta de Twilio).
2. **Registros propios (P5).** ¿Hacemos ya una plantilla genérica de totales por categoría (Schedule C y E) o esperamos la respuesta de GTS sobre cómo arma hoy el Schedule C de un independiente? **Recomiendo la genérica ahora** y ajustarla con la respuesta.
3. **Imagen de ejemplo de cada documento (5.2).** ¿La hacemos ahora para los cinco más comunes (W-2, 1099-NEC, 1099-K, 1095-A y SSA-1099) o la dejamos para después? **Recomiendo dejarla para la fase 4**, cuando se marquen las casillas que se leen.
4. **"No aplica".** ¿Cuenta como resuelto apenas el cliente lo marca (y el preparador lo puede rechazar después) o necesita que el preparador lo acepte? **Recomiendo que cuente apenas lo marca**, como dice 5.3: el preparador solo interviene si lo rechaza.
5. **Cliente que vuelve.** ¿Lo dejamos para la fase 7 (precarga del año anterior)? **Recomiendo que sí**: todavía no hay en la plataforma un año anterior con documentos de la entrevista nueva.

## Decisiones de Steven (2 nov.)

1. **Recordatorios:** el primero 3 días después de terminar la entrevista, después cada 7 días, máximo 4, y se detienen si el cliente sube algo. Solo por correo hasta que la plantilla de WhatsApp esté aprobada en Twilio.
2. **Registros propios:** plantilla genérica de totales por categoría (Schedule C y E) ahora; se ajusta con la respuesta de GTS a P5.
3. **Imagen de ejemplo:** pasa a la fase 4.
4. **"No aplica":** cuenta como resuelto apenas el cliente lo marca; el preparador lo puede rechazar.
5. **Cliente que vuelve:** pasa a la fase 7.
6. **Modelo por tarea:** las tareas marcadas Sonnet 5.5 las hace un subagente Sonnet; las de Opus 5.5 y la revisión de todo, la sesión principal.
