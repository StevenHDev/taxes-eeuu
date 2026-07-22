# Plan de desarrollo — Recolección de datos para declaraciones de impuestos USA

> Fuente: `especificacion_recoleccion_datos_impuestos.md` (2026-07-21).
> Este documento es el tracker de progreso. Se va marcando `[x]` a medida que cada tarea queda implementada y verificada (tests o revisión manual), no solo "escrita".

Leyenda: `[ ]` pendiente · `[~]` en progreso · `[x]` hecho · `[!]` bloqueado/necesita decisión

Plan de implementación detallado (arquitectura, decisiones de diseño): `~/.claude/plans/buzzing-shimmying-bear.md`.

---

## 0. Diagnóstico previo (contexto vs. sistema actual)

- [x] Confirmar qué del sistema actual (`TaxDocumentType`, `TaxDocumentCategory`, CRUD de `tax-documents`) se reemplaza vs. se reutiliza. → Se reemplazó por completo (eliminado).
- [x] Decidir si `TaxDocumentType`/`TaxDocumentCategory` se mapean al nuevo catálogo maestro de campos (sección 2) o se eliminan. → Eliminados; catálogo nuevo en `App\Support\TaxFieldCatalog`.
- [x] Confirmar si existe ya algún concepto de `cliente_id` / usuarios cliente, o hay que crearlo desde cero. → Se reutiliza `User` (`role=client`) en vez de una tabla `clientes` separada.

## 1. Catálogo maestro de campos (sección 2 del spec)

- [x] Modelar el catálogo maestro como fuente de verdad en backend (config, seeder o tabla) — no hardcodeado en el frontend. → `app/Support/TaxFieldCatalog.php`.
- [x] Cargar `campos_transversales` + campos por forma (`form_1040`, `schedule_c`, `schedule_e`, `form_1065`, `form_1120`, `form_1120_s`, `schedule_f`, `form_1041`, `form_990`, `form_1040_nr`).
- [x] Cada campo con sus atributos: `tipo` (`documento`/`dato`/`mixto`), `tipo_dato`, `formatos_aceptados`, `subcampos`, `obligatorio`. → Se agregó además `sensible` (para cifrado/enmascarado, no estaba en el spec original).

## 2. Contrato de datos / Evento de campo (sección 3)

- [x] Definir JSON Schema del evento (`EventoRecoleccionCampoImpuestos`) como validación formal en la API. → Implementado como reglas de `App\Http\Requests\EventoRequest` (estructural) + validación semántica en `EventoRecoleccionService` (no como JSON Schema literal, pero cubre el mismo contrato).
- [x] Endpoint `POST /eventos` que reciba y valide cada evento. → `Api\EventoController::store`.
- [x] Soporte para `valor.modo = "archivo"`. → El archivo se sube directamente en el mismo request (multipart) — no existe un endpoint de subida previo, `archivo_ref` no aplica como campo de entrada.
- [x] Soporte para `valor.modo = "texto"` (tipo_dato, contenido).
- [x] Manejo de `estado`: `recibido` / `pendiente` / `invalido`. → Lo calcula el servidor siempre; el `estado` que venga en el payload se ignora.

## 3. Reglas de negocio (sección 4)

- [x] Un evento = un campo (nunca agrupar varios campos en un solo evento).
- [x] Validación antes de marcar `recibido` (archivo legible + formato aceptado; dato pasa validación mínima de forma — SSN 9 dígitos, fecha válida, número positivo donde aplica).
- [x] Idempotencia: mismo `cliente_id` + `forma` + `campo` sobrescribe, no duplica. → `unique(user_id, forma, campo)` + `updateOrCreate`.
- [x] Soporte multi-forma por cliente (mismo `cliente_id`, varias `forma`).
- [x] Cálculo de completitud por formulario en la API (comparando contra catálogo maestro), no en el LLM.
- [x] Reintento de archivos inválidos: conservar registro para trazabilidad, no contar como campo completo hasta nuevo evento válido.
- [x] `archivo_ref` nunca contiene binario ni credenciales — solo referencia/URL firmada. → Ver sección 8 (URLs firmadas).
- [!] **Campos `array_object`/`array_string`** (ej. `info_dependientes`): el agente debe reenviar el arreglo **acumulado completo** en cada evento — el schema del evento no trae índice/clave natural para hacer merge parcial. Documentado en `docs/api.md`, no resuelto con tabla hija (requeriría extender el evento).

## 4. Modelo de datos (sección 6.4)

- [x] Tabla `clientes` → **no se creó**; se reutiliza `users` (`role=client`), decisión confirmada con el usuario.
- [x] Tabla `formas_cliente` (user_id, forma, estado, revisado_en, revisado_por).
- [x] Tabla `campos_cliente` (user_id, forma, campo, tipo_campo, modo, valor_texto cifrado, documento_id, estado, source, actualizado_por).
- [x] Tabla `documentos` (user_id, forma, campo, file_path, formato, nombre_original, estado_validacion).
- [x] Tabla `historial_cambios` (user_id, forma, campo, valor_anterior, valor_nuevo, source, modificado_por).
- [x] Tabla/ajuste `usuarios` → `App\Enums\UserRole` (client/preparer/administrator) como cast del `role` existente.
- [x] Extra no contemplado en el spec original: `client_intake_sessions` (dedupe de `cliente_id: null` vía `external_ref`) y `campo_reveals` (auditoría de "reveal", sucesor de `tax_document_reveals`).

## 5. Endpoints API REST (sección 6.3)

- [x] `POST /eventos`
- [x] `GET /clientes`
- [x] `GET /clientes/{id}`
- [x] `GET /clientes/{id}/campos/{campo}` → requiere `?forma=` (los nombres de campo se repiten entre formas).
- [x] `PATCH /clientes/{id}/campos/{campo}` (corrección manual) → ídem, requiere `?forma=`.
- [x] `GET /clientes/{id}/documentos`
- [x] `GET /clientes/{id}/export` → ZIP (JSON de campos + archivos). El PDF consolidado queda en backlog (sección 10).
- [x] `POST /clientes/{id}/marcar-revisado`
- [x] Extra no contemplado en el spec original: `POST /clientes/{id}/campos/{campo}/reveal` (revelar campo sensible, solo web con reconfirmación de password).

## 6. Frontend / panel interno CRUD (sección 6.1)

- [x] Listado de clientes con estado general (`resources/js/pages/clientes/index.tsx`). Filtro por formulario aplicable: **no implementado** (prop `formas` disponible pero sin UI de filtro todavía).
- [x] Vista de detalle por cliente: campos + estado + valor/documento (`resources/js/pages/clientes/show.tsx`).
- [~] Visor de documentos integrado: solo enlace de descarga (URL firmada); no hay preview inline de PDF/imagen ni tabular de Excel/CSV — queda en backlog.
- [x] Edición manual de campos por el preparador — editor genérico (JSON crudo para objetos/arrays, no un formulario dedicado por tipo de campo).
- [x] Marcado "revisado por humano" (distinto de "recibido por el agente").
- [x] Exportación ZIP por cliente. PDF consolidado: backlog.
- [x] Historial de cambios visible por campo (modal).

## 7. Autenticación y roles (sección 6.2)

- [x] Rol Cliente: sin acceso al panel interno (403 en todas las rutas de `/clientes/*`).
- [x] Rol Preparador/contador: panel interno, solo clientes asignados (`preparer_id`).
- [x] Rol Administrador (nuevo — no existía): acceso total.
- [x] Permisos mínimos por rol vía `ClientePolicy`. Gestión de usuarios/reasignación preparador↔cliente por parte del admin: **no se construyó UI** (se puede hacer por consola/DB, igual que la asignación preparador↔cliente ya funcionaba antes).

## 8. Seguridad y cumplimiento (sección 6.5)

- [x] Cifrado en reposo para campos sensibles — `campos_cliente.valor_texto` cifrado (`encrypted:array`) para **todas** las filas, no solo las sensibles (más simple y robusto, ver plan de diseño).
- [x] URLs firmadas y temporales para documentos (`Documento::downloadUrl()`, 10 minutos).
- [x] Control de acceso por cliente asignado (salvo admin).
- [ ] Política de retención definida para documentos/datos sensibles. → **No definida, backlog** (requiere decisión de negocio, no solo técnica).
- [x] Registro de auditoría — `historial_cambios` (todo cambio) + `campo_reveals` (quién reveló qué campo sensible, cuándo, desde qué IP).

## 9. Notificaciones y seguimiento (sección 6.6)

- [ ] Aviso al cliente cuando falte un campo por tiempo prolongado. — Backlog.
- [ ] Aviso al preparador cuando el cliente complete todos los campos. — Backlog.
- [ ] Aviso cuando un archivo llegue inválido repetidamente. — Backlog.

## 10. Antes de producción (sección 6.7)

- [~] Casos de prueba: cubiertos por `tests/Feature/EventoRecoleccionTest.php` y `tests/Feature/ClientePanelTest.php` (idempotencia, contenido inválido, completitud, dedupe por `external_ref`, cifrado, formato de archivo inválido, scoping por rol, reveal con reconfirmación de password). **Faltan**: perfil mixto (1040 + Schedule E simultáneo), cliente que manda "todo junto en un solo mensaje" (no aplica tal cual al modelo de eventos — un evento siempre es un campo).
- [ ] Validar con el modelo más económico a usar en producción. — No aplica todavía: el agente conversacional no está construido.
- [ ] Plan de rollback ante cambios de prompt/esquema. — Backlog, depende del agente.

## 11. Agente conversacional (LLM) — fuera del alcance de "API/backend" pero necesario end-to-end

- [x] Definir dónde vive el agente → **decidido: no se construye en este repo**, solo el backend/API que lo consumirá.
- [ ] Lógica de "un campo a la vez", identificación de perfil fiscal y formularios aplicables. — Fuera de este repo.
- [ ] Emisión de eventos hacia `POST /eventos`. — Fuera de este repo; el contrato queda documentado en `docs/api.md`.

---

## Notas de decisiones

- **2026-07-21** — Se reemplazó por completo el CRUD de `TaxDocument` (37 tipos planos) por el modelo de eventos/campos por formulario. Se conservó y generalizó lo reutilizable: cifrado de datos sensibles (patrón `encrypted` cast), auditoría de "reveal" con reconfirmación de password (`tax_document_reveals` → `campo_reveals`), Sanctum con abilities (`TaxDocumentAbility` → `ApiAbility`), y la relación preparador↔cliente (`users.preparer_id`).
- **2026-07-21** — Se agregó `external_ref` como extensión opcional y retrocompatible del evento (no está en el spec original) para deduplicar la creación de clientes cuando `cliente_id` es `null` — sin esto, un agente que pierda el `cliente_id` entre turnos duplicaría clientes, y el catálogo no tiene campo nombre/teléfono para reconciliar after-the-fact.
- **2026-07-21** — El endpoint `POST /eventos` se autentica con un token Sanctum de un usuario de servicio (`role=administrator`) con ability `eventos:write`, sin scoping de propiedad por cliente — es un trade-off aceptado (ver plan de diseño) porque el agente no conoce asignaciones de preparador.
- **Pendiente de decisión futura**: si el agente conversacional llega a construirse y puede enviar un índice/clave natural por elemento de un `array_object`, se puede migrar de "reenviar el arreglo completo" a upsert por elemento (tabla hija `campos_cliente_items`).
