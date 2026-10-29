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
| F0.1 | MFA obligatorio para preparadores y administradores | Alta | 🔄 | — |
| F0.2 | Cifrar los archivos de los documentos | Alta | ✅ | — |
| F0.3 | No borrar clientes ni documentos (conservar 3 años) | Alta | ✅ | — |
| F0.4 | Respaldo externo de base de datos y archivos | Alta | ⏳ | Destino del respaldo |
| F0.5 | Eliminar código muerto (Supabase, n8n, Meta, prompts viejos) | Media | ✅ | — |
| F0.6 | Ocultar formas de empresas y 1040-NR | Media | ✅ | — |
| F1 | Catálogo fiscal del año | Alta | ⏳ | F0 |
| F2 | Entrevista por WhatsApp y por el portal | Media | ⏳ | F1, L1, L3 |
| F3 | Checklist de documentos | Media | ⏳ | F2 |
| F4 | Lectura y validación de documentos | Media | ⏳ | F1, F3 |
| F5 | Expediente, revisión del preparador y hoja para TaxWise | Media | ⏳ | F4 |
| F6 | Cálculo validado y borrador del 1040 | Media | ⏳ | F1, F4 (la validación puede empezar desde F1) |
| F7 | Pendientes para después | Baja | ⏳ | Sección 10 |

Orden sugerido dentro de la fase 0: F0.5 → F0.6 → F0.3 → F0.2 → F0.1 → F0.4 (ver el [plan técnico de la fase 0](fases/fase-0-seguridad-y-limpieza.md)).

---

## Fase 0 — Seguridad y limpieza

Plan técnico: [`fases/fase-0-seguridad-y-limpieza.md`](fases/fase-0-seguridad-y-limpieza.md) · ✅ Aprobado (28 oct.)

### F0.1 — MFA obligatorio para preparadores y administradores
Wiplitask: `cmvskodtw00631420q3excvau` · 🔄
- [x] Middleware EnsureStaffHasMfa (2FA confirmado o passkey) registrado en el grupo web
- [x] Configuración `security.mfa_roles` (preparer, administrator)
- [x] Aviso en la pantalla de Seguridad
- [x] Pruebas Pest (preparador sin MFA, con 2FA, con passkey, cliente, rutas libres)
- [ ] Avisar al equipo de GTS antes de desplegar

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
Wiplitask: `cmvskoeiu006i1420ig6gyty7` · ⏳
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
Wiplitask: `cmvskovkw006x1420a9v9fxo5` · ⏳
- [ ] Plan técnico de la fase 1 escrito y aprobado
- [ ] Modelo por año: preguntas, documentos y casillas, formularios y líneas, parámetros, reglas
- [ ] Menú "Catálogo fiscal del año" con 5 pestañas (ver: preparador; editar: administrador)
- [ ] Cargar 2025 y 2026 desde el documento madre (secciones 3, 4, 6 y 7.7)
- [ ] Migrar datos de `campos_cliente` y eliminar pseudoformas (E12)

### F2 — Entrevista por WhatsApp y por el portal
Wiplitask: `cmvskovqv0072142046gi5vuk` · ⏳
- [ ] Plan técnico de la fase 2 escrito y aprobado
- [ ] Consentimiento §7216 en el portal (requiere L1)
- [ ] Entrevista en tres niveles en el portal
- [ ] Agente de WhatsApp reescrito sobre el catálogo (requiere L3)
- [ ] Confirmación del cliente en el portal; reemplazar E5 y E6
- [ ] Prueba: María empieza por WhatsApp y termina en el portal sin repetir
- [ ] Ingresos en selección múltiple en un solo paso (hoy el árbol pregunta la fuente principal y luego "¿algo más?" una por una)
- [ ] Socio de LLC o S-corp: la empresa no se atiende, pero su K-1 personal sí (1040 + Schedule E). Hoy el prompt le dice "no lo atendemos" (visto al cerrar F0.6)

### F3 — Checklist de documentos
Wiplitask: `cmvskovx900771420nj5xy1du` · ⏳
- [ ] Plan técnico de la fase 3 escrito y aprobado
- [ ] Checklist según respuestas, con estados por documento
- [ ] "No aplica" con motivo, varios archivos por tipo y "otro documento"
- [ ] Recordatorios del agente con plantillas; el agente no acepta documentos

### F4 — Lectura y validación de documentos
Wiplitask: `cmvskow2m007c142002rui266` · ⏳
- [ ] Plan técnico de la fase 4 escrito y aprobado
- [ ] Extracción por casilla con confianza y evidencia
- [ ] Reglas R-01 a R-13 como datos
- [ ] Cola de excepciones (menú nuevo) y consultas al cliente
- [ ] Visor de documento con evidencia

### F5 — Expediente, revisión del preparador y hoja para TaxWise
Wiplitask: `cmvskow9b007h1420if0ijyyu` · ⏳
- [ ] Plan técnico de la fase 5 escrito y aprobado
- [ ] Estados del expediente con fecha; panel y lista de clientes por estado
- [ ] Ficha del cliente en pestañas
- [ ] Checklist del preparador y resumen de debida diligencia (8867)
- [ ] Hoja para TaxWise (pantalla, PDF y Excel)
- [ ] Avisos al preparador; reemplazar "Mi información", el riesgo y el ZIP

### F6 — Cálculo validado y borrador del 1040
Wiplitask: `cmvskowhd007m1420ml6zef8q` · ⏳
- [ ] Plan técnico de la fase 6 escrito y aprobado
- [ ] Validar las 12 calculadoras contra el IRS y por año (puede empezar desde F1)
- [ ] Completar EITC, 8962 y Schedule 1-A; parámetros de 2026
- [ ] Llenar el PDF oficial del 1040 y sus schedules (borrador)
- [ ] Vista "cómo se calculó" por línea
- [ ] Casos de prueba: mismas líneas que TaxWise

### F7 — Pendientes para después
Wiplitask: `cmvskowpm007r1420cq7e3zse` · ⏳
- [ ] Fichas pendientes de formularios (1-A, A, B, D/8949, E, H, R, LEP, 4137, 4684, 8829, 8862, 8863, 8880, 8888, 8889)
- [ ] Transcripciones del IRS con Form 8821
- [ ] Precarga del año anterior para clientes que vuelven
- [ ] Meta-agente sobre el chat del portal y base de conocimiento con fuentes del IRS
- [ ] Decidir E3 (API externa, con E2 `ClientIntakeSession`), E9 (Proceso) y E11 (Schedule F)
- [ ] Varias firmas (cuando el proceso esté validado con GTS)
