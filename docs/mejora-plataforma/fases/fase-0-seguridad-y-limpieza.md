# Fase 0 — Seguridad y limpieza: plan técnico

28 oct. 2026 · **Aprobado** por Steven (28 oct.) · Documento madre: [secciones 8 y 9](../documento-madre.md)

> **Objetivo:** que producción cumpla los mínimos de seguridad de la sección 8.5 (prioridad 1) y que no quede código muerto de n8n ni de Meta. **No cambia el proceso ni las pantallas del cliente.**
>
> **Buenas prácticas:** skills `laravel-best-practices`, `react-best-practices` y `vercel-react-best-practices`. Cada tarea cita la regla que aplica. Esta fase es casi toda backend; en el frontend solo se toca el aviso de la pantalla de Seguridad, el botón de borrar cuenta del Perfil y la página de bienvenida.
>
> **Base:** código de `main` al 28 oct. (Laravel 13.20, Fortify con 2FA y passkeys, Pest).

## Resumen

| # | Tarea | Mínimo (sección 8) | Tamaño | Riesgo |
| --- | --- | --- | --- | --- |
| 0.1 | MFA obligatorio para preparadores y administradores | T1 | Pequeño | Medio: hay que avisar al equipo antes de desplegar |
| 0.2 | Cifrar los archivos de los documentos | T2 | Mediano | Medio: hay que migrar los archivos existentes |
| 0.3 | No borrar clientes ni documentos (conservar) | T4 | Mediano | Medio: cambia el comportamiento de "Eliminar cliente" |
| 0.4 | Respaldo externo de base de datos y archivos | T3 | Pequeño (configuración) | Bajo; necesita una decisión |
| 0.5 | Eliminar código muerto (E1, E2, E4, E7, E8, E15) | — | Pequeño | Bajo |
| 0.6 | Ocultar formas de empresas y 1040-NR (E10) | — | Pequeño | Bajo |

Orden sugerido de commits: 0.5 → 0.6 → 0.3 → 0.2 → 0.1 → 0.4. Primero lo que limpia (menos código que tocar después); el MFA al final para no bloquear al equipo mientras se prueba lo demás.

---

## 0.1 MFA obligatorio para el personal (T1)

**Hoy:** Fortify tiene 2FA (`twoFactorAuthentication` con confirmación) y passkeys activos, pero ningún middleware lo exige. Un preparador puede entrar solo con contraseña.

**Enfoque** (regla `controller-middleware`):
- Nuevo middleware `EnsureStaffHasMfa` en `app/Http/Middleware/`.
  - Aplica si el usuario **no** es cliente.
  - Cuenta como MFA: 2FA confirmado (`two_factor_confirmed_at` no nulo) **o** al menos una passkey registrada.
  - Si no tiene, redirige a `settings/security` con un aviso. Deja pasar las rutas de `settings/*`, la confirmación de contraseña, el 2FA de Fortify y `logout`, para que pueda configurarlo.
- Registrarlo en `bootstrap/app.php` dentro del grupo `web`, después de `auth`.
- Aviso en la pantalla de Seguridad: "Para acceder al panel debes activar la verificación en dos pasos o una passkey". El aviso llega como dato compartido de Inertia y se muestra con un condicional simple: sin `useEffect` ni estado derivado (react-best-practices: *derivar durante el render*; vercel: `rendering-conditional-render`, usar ternario en vez de `&&`).
- **Clientes:** no se exige por ahora (pendiente L9). Se deja preparado con un parámetro de configuración `security.mfa_roles` (por defecto `preparer`, `administrator`).

**Pruebas (Pest):** `tests/Feature/EnsureStaffHasMfaTest.php`
- Preparador sin 2FA → redirigido a seguridad; con 2FA confirmado → entra; con passkey → entra.
- Cliente sin 2FA → entra al portal.
- Rutas de configuración y logout accesibles sin MFA.

**Despliegue:** avisar al equipo de GTS que al entrar tendrán que activar el 2FA (app autenticadora) o una passkey.

**Cómo quedó (29 oct.):**
- `EnsureStaffHasMfa` va al final del grupo `web` (después de `HandleInertiaRequests`, para que un redirect de Inertia en PUT/PATCH/DELETE salga como 303). Una petición JSON sin MFA recibe 403. El chequeo vive en `User::debeConfigurarMfa()` y se comparte a Inertia como `auth.debeConfigurarMfa`.
- Mientras falta el MFA, la pantalla de Seguridad muestra el aviso arriba y pone primero la verificación en dos pasos y las passkeys (la acción principal), antes del formulario de contraseña.
- Se tradujeron al español las secciones de 2FA y passkeys (7 componentes), que estaban en inglés y ahora el personal está obligado a usar.
- En las pruebas, `tests/TestCase.php` apaga el requisito (la factory crea personal sin 2FA); `EnsureStaffHasMfaTest` lo vuelve a encender.
- Probado en local: sin 2FA el panel manda a Seguridad; se activa el 2FA desde el modal y el panel vuelve a abrir. Revisado en escritorio y en móvil.

---

## 0.2 Cifrar los archivos de los documentos (T2)

**Hoy:** `EventoRecoleccionService::procesarArchivo()` guarda el archivo con `storeAs` en el disco `Documento::disco()` **sin cifrar**. Se lee en tres lugares: `DocumentoController::show` (ver o descargar), `ClienteExportService` (ZIP) y `ManagesClientes` (borrado). La extracción con IA lee el archivo **temporal** antes de guardarlo, así que no se ve afectada. Decisión vigente (24 sep.): cifrado **a nivel de aplicación** sobre el almacenamiento actual, sin migrar a S3 todavía.

**Enfoque** (regla `arch-service-classes`):
- Nuevo servicio `App\Services\DocumentoAlmacen` como **único punto** para escribir y leer archivos de documentos:
  - `guardar(UploadedFile|string $origen, string $ruta): void` → cifra el contenido con `Crypt::encryptString(base64)` (AES-256 con la `APP_KEY`) y lo escribe.
  - `leer(Documento $documento): string` → lee y descifra. Si el documento es antiguo (no cifrado), lo devuelve tal cual.
  - `responder(Documento $documento, string $disposicion): StreamedResponse` → para `DocumentoController`.
- Nueva columna `documentos.cifrado` (boolean, por defecto `false`) para distinguir archivos viejos de nuevos (regla `eloquent-casts`).
- Cambiar los tres lugares que leen y el que escribe para que usen el servicio. El hash SHA-256 de duplicados se sigue calculando **antes** de cifrar (sobre el archivo original), así que la detección de duplicados no cambia.
- Comando `php artisan documentos:cifrar-existentes` que cifra los archivos con `cifrado = false`, de a uno (regla `eloquent-chunking`), y es seguro de correr dos veces.
- ⚠️ **La `APP_KEY` pasa a ser crítica:** sin ella no se pueden leer los documentos. Debe estar respaldada fuera del servidor (va con la tarea 0.4).

**Pruebas:**
- Un documento subido queda cifrado en disco (el contenido guardado no es el original) y `DocumentoController` devuelve el original.
- Un documento viejo sin cifrar se sigue pudiendo ver.
- El comando cifra los pendientes y no toca los ya cifrados.
- La detección de duplicados sigue funcionando.
- Exportación ZIP con el contenido original.

**Cómo quedó (29 oct.):**
- `DocumentoAlmacen` cifra el contenido binario directo con `Crypt::encryptString` (no hace falta pasarlo antes a base64: `Crypt` ya codifica el resultado). Además de guardar, leer y responder, tiene `borrar`, que usan el borrado definitivo de usuarios de prueba (F0.3) y la limpieza a 3 años.
- La respuesta del documento agrega `X-Content-Type-Options: nosniff` y el nombre original en `Content-Disposition` (con acentos).
- El comando tiene `--dry-run`, escribe primero a un temporal y luego lo mueve, y si una fila quedó en `cifrado = false` con el archivo ya cifrado (corte a mitad), no lo vuelve a cifrar. Los documentos sin archivo en disco se listan y quedan como están.
- Probado en local: 7 documentos existentes cifrados, con el mismo SHA-256 al descifrarlos; un PDF real subido después se guarda cifrado, se ve en el navegador y sale igual en el ZIP.
- **Frontend y worker deben tener la misma `APP_KEY`**: el worker guarda lo que llega por WhatsApp y el frontend lo muestra. Se verifica después del deploy.

**Límite conocido:** el cifrado con `Crypt` carga el archivo en memoria (máximo 20 MB por archivo hoy), así que es aceptable. Si más adelante se suben archivos más grandes, se cambia a cifrado por partes.

---

## 0.3 No borrar clientes ni documentos (T4)

**Hoy se puede borrar:**
- Un cliente con todo (`ClienteController::destroy` → `ManagesClientes` borra campos, documentos y archivos, y también sus mensajes de WhatsApp).
- Un documento (`EventoRecoleccionService`, línea 472).
- La propia cuenta desde Perfil (`ProfileController::destroy`), también un cliente.
- Un usuario desde Usuarios (`UsuarioController::destroy`), que puede ser un cliente.

El IRS exige conservar 3 años (§6107 y Form 8867, L12).

**Enfoque** (reglas `eloquent-soft-deletes` y `eloquent-pruning`):
- `SoftDeletes` en `User` y en `Documento` (migración: columna `deleted_at`). "Eliminar cliente" pasa a **archivar**: el cliente desaparece de las listas y no puede iniciar sesión, pero sus datos y archivos se conservan.
- `ManagesClientes` deja de borrar archivos y mensajes; solo archiva.
- Reemplazar un documento (subir uno nuevo en lugar del anterior) **archiva** el anterior en vez de borrarlo.
- `ProfileController::destroy`: deshabilitado para clientes (se quita el botón y la ruta responde 403). Para el personal sigue igual.
- Preparar la limpieza automática con `Prunable` (regla `eloquent-pruning`) para borrar definitivamente lo archivado hace más de **3 años**, pero **sin programarla todavía**: el plazo exacto lo confirma L12.
- Correo único: un cliente archivado sigue ocupando su correo. Si vuelve, se **restaura** en lugar de crear otro (acción "Restaurar" en Usuarios).

**Usuarios de prueba (decisión de Steven, 28 oct.):** para probar el proceso sin dejar registro, existe una excepción controlada.
- Columna `users.es_prueba` (booleano, falso por defecto). Solo el **administrador** la pone o la quita, desde Usuarios o desde la ficha del cliente. Se puede marcar a usuarios creados a mano o por el agente de WhatsApp. Cada cambio queda en la bitácora.
- Un usuario de prueba muestra la acción **"Eliminar definitivamente"** (`forceDelete`), que hace lo de hoy: borra datos, documentos y archivos, con la casilla para borrar también su conversación de WhatsApp. Queda en la bitácora quién lo borró y cuándo.
- Un usuario que **no** es de prueba solo se puede **archivar**. Para borrarlo del todo, primero hay que marcarlo como prueba (queda registrado).
- El agente nunca marca usuarios de prueba; tampoco el cliente desde su perfil.
- Los usuarios de prueba se ven con una etiqueta "Prueba" en las listas, para no confundirlos con clientes reales.

**Pruebas:**
- Eliminar un cliente lo archiva: no aparece en la lista, sus documentos y archivos siguen existiendo, no puede iniciar sesión.
- Restaurar lo devuelve.
- Solo el administrador marca o desmarca un usuario de prueba, y queda en la bitácora.
- Un usuario de prueba se elimina definitivamente (con o sin su conversación); uno que no es de prueba no se puede eliminar definitivamente.
- Un cliente no puede borrar su cuenta.
- Reemplazar un documento conserva el anterior archivado.

**Revisar:** todas las consultas que listan usuarios o documentos ya excluyen los archivados (comportamiento por defecto de `SoftDeletes`); verificar el dashboard y los conteos.

---

## 0.4 Respaldo externo (T3)

**Hoy:** la base de datos y el volumen `gts_documentos_shared` están solo en el servidor compartido, sin respaldo fuera de él.

**Enfoque** (configuración en Dokploy, sin código):
- Respaldo diario de PostgreSQL (`global-tax-db`) con la función de backups de Dokploy hacia un destino externo cifrado (por ejemplo, un bucket S3 en EE. UU.).
- Respaldo diario del volumen de documentos (ya cifrados por 0.2) al mismo destino.
- Copia de la `APP_KEY` en un gestor de secretos, fuera del servidor.
- **Prueba de restauración** en un ambiente aparte antes de dar la tarea por cerrada.

❓ **Decisión necesaria:** destino del respaldo (proveedor y región en EE. UU.) y quién tiene acceso. Va junto con T-b de la sección 10.

---

## 0.5 Eliminar código muerto

| Ítem | Qué se borra | Ajustes |
| --- | --- | --- |
| E1 | **Sin migrar el historial de n8n (decisión de Steven, 28 oct.).** `SupabaseWhatsappConversationService`; config `services.supabase`; variables `SUPABASE_*`, `VITE_SUPABASE_*`, `WHATSAPP_SUPABASE_TABLE_PATTERN` | `ClienteController` deja de inyectarlo y la conversación se arma solo con `WhatsappMensaje`; actualizar `WhatsappConversacionTest`. Las variables del `.env` de producción no se tocan (decisión de Steven) |
| ~~E2~~ | ~~`ClientIntakeSession`~~ | **Pospuesto (28 oct.):** la usa `/api/eventos` (E3) para no duplicar clientes por `external_ref`. Se quita junto con E3 |
| E4 | `app/Services/Whatsapp/Meta/*`, config `services.meta` y `services.whatsapp.provider`, variables `META_*` y `WHATSAPP_PROVIDER` | `WhatsappChannel` se enlaza directo a Twilio; se quita `manejarHandshake` y el GET del webhook (solo lo usaba Meta); borrar `MetaChannelTest` y `MetaWebhookTest`; ajustar comentarios |
| E7 | `prompt_actuales/promptBase.md`, `prompt_actuales/especialista_recoleccion.md`, `docs/prompt.md` | — |
| E8 | `resources/js/pages/welcome.tsx` y sus textos en `es.json`/`en.json` | — |
| E15 | `laravel/chisel` | Pasarla a `require-dev` (la usan los marcadores de `resources/js/types/auth.ts`) o quitar los marcadores y la dependencia |

**Pruebas:** la suite completa pasa; `npm run build` sin errores.

---

## 0.6 Ocultar formas de empresas y 1040-NR (E10)

**Enfoque:**
- Nuevo método `TaxForm::enAlcance()` con las formas de persona natural: 1040, Schedule C, Schedule E y Schedule F (este último hasta que GTS responda P3).
- Usarlo en lo que **ofrece** opciones: el enum de la tool `declarar_formas_cliente` (`ToolDefinitions`), `ClienteFormasRequest`, las opciones del catálogo (`CatalogoController`) y los filtros de clientes (`ClienteController`).
- **No** se cambia la validación de lo que ya existe (`EventoRequest`, `CampoClienteUpdateRequest`), para no romper datos guardados.
- Quitar del prompt `determinacion_formas.md` las opciones c), f), g) y h), y publicar la nueva versión.

**Pruebas:** la tool solo ofrece formas en alcance; un cliente viejo con una forma fuera de alcance se sigue pudiendo ver.

---

## Despliegue

1. Avisar al equipo de GTS del MFA obligatorio (0.1).
2. Desplegar el código.
3. Correr migraciones (`deleted_at`, `cifrado`, eliminación de `client_intake_sessions`).
4. Correr `php artisan documentos:cifrar-existentes`.
5. Publicar el prompt actualizado (0.6).
6. ~~Quitar del `.env` de producción las variables de Supabase y Meta~~. Decisión de Steven (28 oct.): no se tocan; el código ya no las lee.
7. Configurar y **probar la restauración** del respaldo (0.4).

## Listo cuando

- La suite de Pest pasa completa y `npm run build` compila.
- Un preparador sin MFA no puede entrar al panel.
- Los archivos en disco están cifrados y se ven bien desde la plataforma.
- No hay forma de borrar un cliente ni un documento desde la plataforma.
- Hay un respaldo externo y se probó restaurarlo.
- No queda código de Supabase, Meta ni de las sesiones de n8n.

## Fuera de esta fase

- E3 (API del agente externo) y E2 (`ClientIntakeSession`, que la usa): esperan a confirmar que nadie usa un token (T-a).
- E9 (panel "Proceso"): espera la decisión T-g.
- E11 (Schedule F): espera P3.
- MFA para clientes: espera L9.
