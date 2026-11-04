# Tareas de desarrollo

28 oct. 2026 · Responsable: Steven Herrera · Wiplitask: empresa **Cupperlab**, proyecto **"GTS — Florida Tax Intake"**

> **Para qué sirve:** llevar el orden del desarrollo. Cada tarea corresponde a una fase o subfase del [documento madre](documento-madre.md) (sección 9.5) y existe igual en Wiplitask, con el mismo checklist. Al avanzar se actualiza el estado **en los dos lugares**.
>
> **Cómo se trabaja cada tarea** (documento madre, 9.8):
> 1. Plan técnico en [`fases/`](fases/), con las skills `laravel-best-practices`, `react-best-practices` y `vercel-react-best-practices`.
> 2. Aprobación de Steven.
> 3. Programar y probar (Pest + `npm run build`).
> 4. Al cerrar, **preguntar a Steven** antes de commit y push. Con su sí, se hace con **`/commit`** (commit + push a la rama actual).
>
> **Estados:** ⏳ Pendiente · 🔄 En progreso · 👀 En revisión · ✅ Hecho · ⛔ Bloqueado

## Resumen

| Tarea | Título | Prioridad | Estado | Depende de |
| --- | --- | --- | --- | --- |
| F0.1 | MFA obligatorio para preparadores y administradores | Alta | ✅ | — |
| F0.2 | Cifrar los archivos de los documentos | Alta | ✅ | — |
| F0.3 | No borrar clientes ni documentos (conservar 3 años) | Alta | ✅ | — |
| F0.4 | Respaldo externo de base de datos y archivos | Alta | ⛔ | Destino del respaldo |
| F0.5 | Eliminar código muerto (Supabase, n8n, Meta, prompts viejos) | Media | ✅ | — |
| F0.6 | Ocultar formas de empresas y 1040-NR | Media | ✅ | — |
| F1 | Catálogo fiscal del año | Alta | ✅ | F0 |
| F2 | Entrevista por WhatsApp y por el portal | Media | ✅ | F1 (L1 y L3 en la tarea de pendientes legales) |
| F3 | Checklist de documentos | Media | 🔄 | F2 |
| F4 | Lectura y validación de documentos | Media | ⏳ | F1, F3 |
| F5 | Expediente, revisión del preparador y hoja para TaxWise | Media | ⏳ | F4 |
| F6 | Cálculo validado y borrador del 1040 | Media | ⏳ | F1, F4 (la validación puede empezar desde F1) |
| F7 | Pendientes para después | Baja | ⏳ | Sección 10 |

Orden sugerido dentro de la fase 0: F0.5 → F0.6 → F0.3 → F0.2 → F0.1 → F0.4 (ver el [plan técnico de la fase 0](fases/fase-0-seguridad-y-limpieza.md)).

---

## Fase 0 — Seguridad y limpieza

Plan técnico: [`fases/fase-0-seguridad-y-limpieza.md`](fases/fase-0-seguridad-y-limpieza.md) · ✅ Aprobado (28 oct.)

### F0.1 — MFA obligatorio para preparadores y administradores
Wiplitask: `cmvskodtw00631420q3excvau` · ✅ (29 oct., commits `c8a9290` y `fe173b1`, verificado en producción)
- [x] Middleware EnsureStaffHasMfa (2FA confirmado o passkey) registrado en el grupo web
- [x] Configuración `security.mfa_roles` (preparer, administrator)
- [x] Aviso en la pantalla de Seguridad
- [x] Pruebas Pest (preparador sin MFA, con 2FA, con passkey, cliente, rutas libres)
- [x] Traducir al español las secciones de 2FA y passkeys (estaban en inglés)
- [ ] Avisar al equipo de GTS (Steven). En producción hay 2 administradores, ninguno con 2FA todavía: al entrar irán a Seguridad

### F0.2 — Cifrar los archivos de los documentos
Wiplitask: `cmvskoe2a00681420ct1nuysi` · ✅ (29 oct., commits `522c958` y `3199899`, comando corrido en producción)
- [x] Servicio DocumentoAlmacen (guardar, leer, responder, borrar) con cifrado
- [x] Migración: columna `documentos.cifrado`
- [x] Usar el servicio en EventoRecoleccionService, DocumentoController y ClienteExportService (también en el borrado definitivo de F0.3 y en la limpieza a 3 años)
- [x] Comando `documentos:cifrar-existentes` (con `--dry-run`; se puede correr dos veces)
- [x] Pruebas Pest (cifrado en disco, ver original, documento viejo, duplicados, ZIP) y prueba local con un PDF real
- [x] Correr el comando en producción y verificar (2 documentos cifrados, mismo SHA-256 al descifrar desde frontend y worker)

### F0.3 — No borrar clientes ni documentos (conservar 3 años)
Wiplitask: `cmvskoe88006d1420v8bj0e8g` · ✅ (29 oct., commits `5c32805` y `02459b3`, probado en local y verificado en producción)
- [x] SoftDeletes en User y Documento (migración `deleted_at`)
- [x] "Eliminar cliente" archiva sin borrar archivos ni mensajes
- [x] Reemplazar un documento archiva el anterior
- [x] El cliente no puede borrar su cuenta; acción Restaurar en Usuarios
- [x] Usuarios de prueba: marca `es_prueba` (solo admin, en bitácora) y "Eliminar definitivamente" con opción de borrar la conversación
- [x] Prunable preparado a 3 años (sin programar hasta L12)
- [x] Pruebas Pest y revisión de dashboard y conteos

### F0.4 — Respaldo externo de base de datos y archivos
Wiplitask: `cmvskoeiu006i1420ig6gyty7` · ⛔ Bloqueada: falta definir el destino del respaldo (decisión de Steven, 29 oct.: se hace después)

> Mientras tanto: guardar ya una copia de la `APP_KEY` de producción en un gestor de contraseñas del equipo (sin ella los documentos cifrados no se pueden abrir). Fecha límite: antes de que entren clientes reales en volumen o antes de la temporada de impuestos, lo que llegue primero.

- [ ] Decidir destino del respaldo (proveedor y región en EE. UU.) y quién tiene acceso
- [ ] Respaldo diario de PostgreSQL en Dokploy
- [ ] Respaldo diario del volumen de documentos
- [ ] `APP_KEY` guardada en un gestor de secretos fuera del servidor
- [ ] Prueba de restauración en un ambiente aparte

### F0.5 — Eliminar código muerto
Wiplitask: `cmvskoepo006n1420ykum2tii` · ✅ (28 oct., commits `4b28169` y `20f7ef0`, verificado en producción)
- [x] E1: quitar Supabase (servicio, config y variables). Sin migrar el historial de n8n; las variables del `.env` de producción no se tocan (decisiones de Steven, 28 oct.)
- [ ] ~~E2: quitar ClientIntakeSession (modelo, tabla y uso)~~ ⏸ Se quita junto con E3 (decisión de Steven, 28 oct.): la usa `/api/eventos` para no duplicar clientes
- [x] E4: quitar el proveedor Meta de WhatsApp (código, config, pruebas y webhook)
- [x] E7 y E8: prompts viejos y página de bienvenida
- [x] E15: `laravel/chisel` a require-dev o quitarla (se quitó)
- [x] Suite de Pest completa y `npm run build` sin errores

### F0.6 — Ocultar formas de empresas y 1040-NR
Wiplitask: `cmvskoext006s1420xrxrb3hw` · ✅ (28 oct., commits `33cb991` y `367b5ef`, verificado en producción)
- [x] `TaxForm::enAlcance()` (1040, Schedule C, E y F hasta respuesta de P3)
- [x] Usarlo en ToolDefinitions, ClienteFormasRequest, CatalogoController y filtros de clientes (también en el ToolExecutor y en los campos que se pueden agregar en la ficha del cliente)
- [x] Actualizar y publicar el prompt `determinacion_formas` (versión 2 publicada en producción el 28 oct.; la v1 queda guardada)
- [x] Pruebas Pest (opciones en alcance; clientes viejos se siguen viendo)
- [x] El 1040 se declara siempre junto a cualquier schedule (`AgenteToolService::declararFormas`)

---

## Fases 1 a 7

Su checklist se detalla al escribir el plan técnico de cada fase.

### F1 — Catálogo fiscal del año
Plan técnico: [`fases/fase-1-catalogo-fiscal.md`](fases/fase-1-catalogo-fiscal.md) · ✅ Aprobado (29 oct.)

Wiplitask: `cmvskovkw006x1420a9v9fxo5` · ✅ (30 oct.; F1.1–F1.5 en producción. F1.6 pasó a F2, decisión de Steven)
- [x] Plan técnico de la fase 1 escrito y aprobado
- [x] 1.1 Modelo de datos por año (preguntas, documentos y casillas, formularios y líneas, activaciones, reglas, historial de cambios)
- [x] 1.2 Parámetros del año sobre `parametros_fiscales` (fuente, grupo, valor pendiente)
- [x] 1.3 Cargar 2025 y 2026 desde el documento madre (comandos `cargar`, `copiar`, `verificar`; caso María). Cargado en producción el 29 oct. (commits `8d2b138` y `c68e03e`): verificar sin problemas en 2025 y 2026, determinaciones de los clientes sin cambios
- [x] 1.4 Menú "Catálogo fiscal del año" con 5 pestañas (ver: preparador y administrador)
- [x] 1.5 Edición por el administrador (sin borrar, historial, "marcar como validado contra el IRS")
- [ ] ~~1.6 Mapa de equivalencias de `campos_cliente` y reporte; menú viejo como "Campos del formulario actual"~~ → pasa a F2 (30 oct.): el mapa solo sirve para mover los datos cuando F2 reemplace el formulario actual

### F2 — Entrevista por WhatsApp y por el portal
Plan técnico: [`fases/fase-2-entrevista.md`](fases/fase-2-entrevista.md) · ✅ Aprobado (30 oct.)

Wiplitask: `cmvskovqv0072142046gi5vuk` · ✅ Cerrada el 2 nov. (2.1 a 2.11 en producción). L1 y L3 siguen en la tarea "Pendientes legales de F2" (`cmvzdosz104cm1420tiuvcorh`, bloqueada hasta que responda el abogado)
- [x] Plan técnico de la fase 2 escrito y aprobado
- [x] 2.1 Los preparadores también validan piezas del catálogo
- [x] 2.2 Respuestas de la entrevista y motor único (+ bandera por cliente, contenido nuevo del catálogo). En producción y catálogo cargado el 30 oct. (`9da755d`, `d8e7487`): 79 preguntas y 42 documentos en 2025 y 2026, verificar sin problemas
- [x] 2.3 Mapa de equivalencias `campos_cliente` → catálogo y reporte (`catalogo-fiscal:equivalencias --reporte`)
- [x] 2.4 Primera entrada desde el enlace de WhatsApp (crear contraseña sin pasar por el correo) y consentimiento §7216 en el portal (texto provisional; el definitivo espera L1). En producción el 30 oct. (`799f1e4`, `1a19644`), texto provisional cargado con `consentimientos:cargar`
- [x] 2.5 Entrevista en el portal: 8 pantallas en tres niveles. En producción el 30 oct. (`d85e906`, `7e282dd`, `f3dc426`), catálogo recargado: 88 preguntas con inglés en 2025 y 2026, verificar sin problemas
  - Contenido del catálogo visto en la revisión de 2.5: ✅ textos en inglés, B2, B4, C4, C6 y D17 corregidos (30 oct.). ✅ C11, D3, D8, D10, D11, D12, D15, E9 y F8 divididas en pregunta + seguimiento (88 preguntas). El importador desactiva las activaciones (desde F2.5) y, resuelto en F4.1, también las casillas y las líneas que salen del archivo
- [x] 2.6 Documentos que pide la entrevista: lista mínima y carga (solo por el portal). En producción el 30 oct. (`fb25854`, `a7f00c9`), catálogo recargado: 93 preguntas, verificar sin problemas
- [x] 2.7 Revisión y confirmación del cliente en el portal (`41d5b6e`)
- [x] 2.8 Agente de WhatsApp reescrito sobre el catálogo (entrevista por WhatsApp apagada para clientes reales hasta L3). En producción el 30 oct. (`8fed54c`, `71bc020`); borrador 3 de prompts creado en producción con `agente:borrador-prompts --fases=portal_dudas`. Steven lo publicó (versión 4) y lo probó por WhatsApp el 1 nov. con el cliente de prueba #27. De esa prueba salieron 4 ajustes al prompt de la entrevista y a `consultar_entrevista`; borrador 5 creado en producción el 1 nov. con `agente:borrador-prompts --fases=entrevista` y publicado por Steven (versión vigente 5). D17.1 agregado en 2.10
- [x] 2.9 Vista del preparador: entrevista en la ficha y chat del portal en Mensajes. En producción el 1 nov. (`a5e1e7e`, `e42b4ee`, `70de914`, `25708d5`); migración de revelados aplicada, sin errores en el log
- [x] 2.10 Cambio de flujo y migración de `campos_cliente` (ya no espera L1: los clientes reales firman el texto provisional, decisión del 1 nov.; incluye D17.1). En producción el 1 nov. (`34067ef`, `1f25f69`, `8439a5f`, `2bd8454`); catálogo recargado (94 preguntas, verificar sin problemas). La migración no tuvo nada que pasar: `campos_cliente` está vacía en producción
- [x] 2.11 Retiro del flujo anterior (E5, E6, E12, tool y menú viejos). En producción el 2 nov. (`9dbb11c`, `f1eaa39`, `95e85df`, `ec8cdb8`); Steven confirmó el deploy. La API externa queda igual y la ficha vieja en solo lectura (decisiones 14 y 15)
- [x] Consentimiento §7216 en el portal, con texto provisional. El texto definitivo (L1) pasó a la tarea "Pendientes legales de F2" (Wiplitask `cmvzdosz104cm1420tiuvcorh`)
- [x] Entrevista en tres niveles en el portal (2.5)
- [x] Agente de WhatsApp reescrito sobre el catálogo (2.8). Encenderlo para clientes reales (L3) pasó a la tarea "Pendientes legales de F2" (Wiplitask `cmvzdosz104cm1420tiuvcorh`)
- [x] Confirmación del cliente en el portal; reemplazar E5 y E6 (2.7 y 2.11)
- [x] Prueba: María empieza por WhatsApp y termina en el portal sin repetir (`MariaTest` en 2.8 y prueba manual por WhatsApp el 1 nov.)
- [x] Migrar `campos_cliente` a las respuestas nuevas con ese mapa y eliminar las pseudoformas `transversal` y `documentos_extra` (E12), la tool `consultar_documentos_extra` y el menú "Campos del formulario actual" (decisión de Steven, 29 oct.). Hecho en 2.10 y 2.11; las pseudoformas quedan solo para la API externa hasta decidir E3 (decisión 14)
- [x] Ingresos en selección múltiple en un solo paso (hoy el árbol pregunta la fuente principal y luego "¿algo más?" una por una)
- [x] Socio de LLC o S-corp: la empresa no se atiende, pero su K-1 personal sí (1040 + Schedule E). Hoy el prompt le dice "no lo atendemos" (visto al cerrar F0.6). D18 + K-1 → Schedule E en el catálogo (2.2) y el prompt viejo se retiró en 2.11

### F3 — Checklist de documentos
Plan técnico: [`fases/fase-3-documentos.md`](fases/fase-3-documentos.md) · ✅ Aprobado (2 nov.)

Wiplitask: `cmvskovx900771420nj5xy1du` · 🔄
- [x] Plan técnico de la fase 3 escrito y aprobado (2 nov.)
- [x] Checklist según respuestas, con estados por documento (3.1–3.4 en producción el 2 nov.: contenido de cada documento, checklist guardado con estados, pantalla del cliente y revisión del preparador)
- [x] "No aplica" con motivo, varios archivos por tipo y "otro documento" (3.3, en producción el 2 nov.)
- [ ] Recordatorios del agente con plantillas; el agente no acepta documentos (3.5, el agente explica qué falta: en producción el 2 nov., borrador de prompts 6 creado y sin publicar; 3.6 pendiente)
- [x] Qué subir en registros propios, con plantilla opcional (3.7, en producción el 2 nov.; catálogo recargado). Se ajusta con la respuesta de GTS a P5: las preguntas P1–P10 están en la tarea "Preguntas pendientes para GTS (P1–P10)" (Wiplitask `cmvzotuc104e31420br272h7y`)
- [x] Por qué no tiene la declaración del año pasado: seguimiento de la G5 (3.8, hecho el 2 nov.). Se ajusta con la respuesta de GTS a P10
- [x] Temas que faltaban en el catálogo: 529 (1099-Q), vehículo limpio, energía de la vivienda y adopción (3.9, hecho el 3 nov.; F10 y F11 solo en 2025)

### F4 — Lectura y validación de documentos
Plan técnico: [`fases/fase-4-lectura-validacion.md`](fases/fase-4-lectura-validacion.md) · ✅ Aprobado (2 nov.) · Subtareas para Sonnet 5.5: [`fases/fase-4-subtareas.md`](fases/fase-4-subtareas.md) (3 nov.)

Wiplitask: `cmvskow2m007c142002rui266` · ✅ (3 nov.)
- [x] Plan técnico de la fase 4 escrito y aprobado (2 nov.)
- [x] Extracción por casilla con confianza y evidencia (20 documentos más la tarjeta del Seguro Social; lectura para todos los usuarios, con bandera `tax.lectura_automatica` para apagarla)
- [x] Reglas R-01, R-03 a R-09 y R-11 como aviso (R-02 en F5; R-10, R-11 bloqueante y R-12 en F6; R-13 en F7)
- [x] Cola de excepciones (menú nuevo) y consultas al cliente
- [x] Visor de documento con evidencia
- [x] Lo que ve el cliente y el agente, e imagen de ejemplo de cada formulario
- [ ] Pendientes menores: publicar el borrador de prompts 7 (Steven, desde Agente → Prompts); la hoja del visor no queda fija al bajar; probar "Revelar" en el navegador; una acción propia "Revelado" en la bitácora

### F5 — Expediente, revisión del preparador y hoja para TaxWise
Wiplitask: `cmvskow9b007h1420if0ijyyu` · ⏳
- [ ] Plan técnico de la fase 5 escrito y aprobado
- [ ] Regla R-02 (todo documento validado o en "no aplica"), que pasó de F4 (decisión del 2 nov.)
- [ ] Estados del expediente con fecha; panel y lista de clientes por estado
- [ ] Ficha del cliente en pestañas
- [ ] Checklist del preparador y resumen de debida diligencia (8867)
- [ ] Hoja para TaxWise (pantalla, PDF y Excel)
- [ ] Avisos al preparador; reemplazar "Mi información", el riesgo y el ZIP

### F6 — Cálculo validado y borrador del 1040
Wiplitask: `cmvskowhd007m1420ml6zef8q` · ⏳
- [ ] Plan técnico de la fase 6 escrito y aprobado
- [ ] Reglas que pasaron de F4 (decisión del 2 nov.): R-10 (Schedule C contra 1099), R-11 como bloqueante (con el 8812) y R-12 (proveedor de cuidado, con el 2441)
- [ ] Validar las 12 calculadoras contra el IRS y por año (puede empezar desde F1)
- [ ] Completar EITC, 8962 y Schedule 1-A; parámetros de 2026
- [ ] Llenar el PDF oficial del 1040 y sus schedules (borrador)
- [ ] Vista "cómo se calculó" por línea
- [ ] Casos de prueba: mismas líneas que TaxWise

### F7 — Pendientes para después
Wiplitask: `cmvskowpm007r1420cq7e3zse` · ⏳
- [ ] Fichas pendientes de formularios (1-A, A, B, D/8949, E, H, R, LEP, 4137, 4684, 8829, 8862, 8863, 8880, 8888, 8889)
- [ ] Transcripciones del IRS con Form 8821
- [ ] Regla R-13 (variación frente al año anterior), que pasó de F4 (decisión del 2 nov.)
- [ ] Precarga del año anterior para clientes que vuelven
- [ ] Meta-agente sobre el chat del portal y base de conocimiento con fuentes del IRS
- [ ] Decidir E3 (API externa, con E2 `ClientIntakeSession`), E9 (Proceso) y E11 (Schedule F)
- [ ] Varias firmas (cuando el proceso esté validado con GTS)

---

## Ajustes de la entrevista vistos en las pruebas reales (3 y 4 nov.)

Salen de las conversaciones de prueba con el agente y el portal. Los marcados ✅ ya están en producción o en el repo; los demás esperan.

- [ ] **Personas que vivieron con el cliente pero no son sus dependientes.** Si el cliente dice que alguien vivió con él pero no estaba a su cargo (por ejemplo, sus papás), esa persona se descarta como dependiente y no se le debe pedir nada más. Hoy se abren 29 respuestas por 2 personas (nombre, nacimiento, SSN o ITIN, IP PIN, parentesco, estudiante, incapacidad…) y dos tarjetas del Seguro Social, y solo después se ve que no califican. Propuesta: mover C12 (¿pagó más de la mitad de su propia manutención?), C13 (ingresos menores de USD 5.200) y C14 (¿el cliente pagó más de la mitad de la suya?) para que se pregunten primero, y condicionar el resto de las preguntas de la persona y su tarjeta del Seguro Social a que siga pudiendo ser dependiente (C12 = no, o C13 = sí o C14 = sí, según la regla de pariente calificado; confirmar la regla exacta con la Pub. 501 antes de programar). Visto el 4 nov. con el usuario #37 (Steven): papás con ingresos altos que se mantienen solos.
- [ ] **Residencia fiscal.** A un cliente que dice no ser ciudadano y no haber estado con visa (o que estuvo con visa) no se le pregunta el tipo de visa ni si es residente (green card, prueba de presencia sustancial). De eso depende si es 1040 o 1040-NR. Llevarlo a las preguntas P1–P10 para GTS o a una pregunta de seguimiento (usuarios #34 y #36).
- [ ] **1099 al declarar trabajo por cuenta propia.** D15 = sí pide de golpe 1099-NEC, 1099-K y 1099-MISC. Preguntar antes de qué pagadores recibió pagos (usuario #36, manualidades).
- [ ] **Declaración sin ningún ingreso.** Un cliente que contesta "no" a todos los ingresos termina sin aviso. Una regla informativa para el preparador (F5).
- [ ] **Reenvío de respuestas no enviadas** (outbox): un job que falla después de guardar el mensaje del cliente pierde la respuesta pendiente (error 21617 de Twilio visto el 3 nov.).
- [ ] **Firma del consentimiento por WhatsApp.** El salto al portal para firmar el §7216 sigue siendo necesario. Quitarlo es una decisión legal (L1 y L3 de la tarea "Pendientes legales de F2").
- [x] El agente guardaba respuestas con códigos equivocados (A1 y A2 con "nunca casado" y "no"); `guardar_respuestas` rechaza por WhatsApp cualquier código de la pantalla 1 (4 nov.).
- [x] El agente inventaba, repetía y reordenaba preguntas; el prompt exige una por una, en el mismo orden y con el mismo alcance que `proximas_preguntas` (4 nov.; publicar borrador de prompts).
- [x] El agente arranca solo al firmar el consentimiento en el portal, sin que el cliente avise (4 nov.).
- [x] Nombre y fecha de nacimiento del cónyuge solo en el portal; SSN o ITIN del cónyuge con formato; documento de la tarjeta del cónyuge; ciudadanía obligatoria sin cónyuge (4 nov.).

---

## Pendientes técnicos sueltos

Wiplitask: `cmvtv0tag015u1420rmbglh9g` · ⏳

Cosas vistas al desarrollar que no son de una fase. Se resuelven cuando haya un hueco o al tocar ese código.

- [ ] `composer audit` reporta 24 avisos de seguridad en dependencias: revisar y actualizar (visto en F0.5, 28 oct.)
- [ ] Tabla compartida (`data-table-pagination.tsx`): al pasar a la página 2 el paginador sigue diciendo "Página 1 de N" (visto en F0.3, 29 oct.)
- [ ] Usuarios: después de restaurar un usuario, la tabla vuelve a la página 1 (visto en F0.3)
- [ ] Passkeys: el nombre que se propone por defecto dice "Chrome on Mac"; pasarlo a "Chrome en Mac" (visto en F0.1)
- [x] Local: `.env` con `APP_LOCALE=en` hace que los errores de validación salgan en inglés (producción está en `es`); además el idioma que elige el usuario (cookie) no cambia el idioma del servidor (visto en F1.5) → resuelto en F2.5: el servidor usa el idioma del selector (`AplicarIdioma`)
