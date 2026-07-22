# API de Recolección de Datos para Declaraciones de Impuestos

API REST que recibe eventos de recolección de datos fiscales **un campo a la vez** desde un agente conversacional externo, y expone el panel interno de clientes/formularios/campos para preparadores y administradores. Reemplaza el antiguo CRUD de "documentos fiscales" por un modelo de eventos incrementales por campo, alineado al catálogo maestro de formularios del IRS.

## Autenticación

La API usa [Laravel Sanctum](https://laravel.com/docs/sanctum) con **tokens personales** (Bearer tokens).

1. Inicia sesión en la aplicación web.
2. Ve a **Settings → API Tokens**.
3. Crea un token: dale un nombre y marca los permisos ("abilities") que necesita.
4. Copia el valor del token — **solo se muestra una vez**. Si lo pierdes, revócalo y crea uno nuevo.

Envíalo en cada request como un header `Authorization`:

```
Authorization: Bearer 1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

Todas las rutas están bajo el prefijo `/api` y requieren este header. Sin un token válido, cualquier endpoint responde `401 Unauthorized`.

## Permisos (abilities)

| Ability | Qué habilita |
|---|---|
| `eventos:write` | Emitir eventos de recolección de campos (`POST /api/eventos`). Pensado para un **token de servicio** dedicado al agente conversacional, no para un preparador individual — ver [Emitir eventos](#emitir-un-evento-post-apieventos). |
| `clientes:read` | Listar clientes, ver su detalle, historial de campos y documentos, y exportar su paquete. |
| `clientes:write` | Corregir campos manualmente y marcar formularios como revisados. |
| `clientes:reveal-sensitive` | Reservado para uso futuro sobre API — hoy el "reveal" de campos sensibles solo está disponible desde el panel web (sesión), no sobre token, para poder exigir reconfirmación de contraseña. |

Un endpoint que requiera un permiso que el token no tiene responde `403 Forbidden`.

## Alcance de los datos

- Un preparador (`role = preparer`) solo ve/gestiona los clientes que tiene asignados (`preparer_id`).
- Un administrador (`role = administrator`) ve y gestiona todos los clientes.
- Un cliente (`role = client`) no tiene acceso a estos endpoints — el panel es exclusivamente interno.
- El endpoint `POST /eventos` es la excepción: el token del agente conversacional puede escribir sobre **cualquier** `cliente_id`, porque el agente no conoce asignaciones de preparador. Por eso ese token debe ser de un solo propósito (`eventos:write` únicamente) y no compartirse con un preparador.

## Catálogo maestro de campos

Cada campo pertenece a **`campos_transversales`** (se aplican a cualquier `forma`) o a una **forma específica**: `form_1040`, `schedule_c`, `schedule_e`, `form_1065`, `form_1120`, `form_1120_s`, `schedule_f`, `form_1041`, `form_990`, `form_1040_nr`. El catálogo completo (fuente de verdad) vive en `App\Support\TaxFieldCatalog`; las tablas de abajo son su documentación exhaustiva, campo por campo — si cambia el catálogo, hay que actualizar esta sección también.

Qué significa cada columna:

- **`tipo_campo`**: `documento` (el campo **solo** admite `modo: archivo`), `dato` (**solo** admite `modo: texto`), o `mixto` (admite cualquiera de los dos — el agente elige según lo que el cliente entregue realmente).
- **`tipo_dato`**: solo aplica cuando `modo: texto`. Uno de `string`, `number`, `object`, `array_string`, `array_object`.
- **`formatos_aceptados`**: solo aplica cuando `modo: archivo`. Extensiones de archivo válidas para ese campo — cualquier otra extensión hace que el evento se guarde con `estado: "invalido"`.
- **`obligatorio`**: si es `no`, ese campo no cuenta para que la API marque la forma como `completo` (ver [Emitir eventos](#emitir-un-evento-post-apieventos)).
- **`sensible`**: si es `sí`, el valor se cifra en la base de datos, se muestra enmascarado en el panel/API, y revelarlo exige el flujo de [Revelar campos sensibles](#revelar-campos-sensibles).

**Nota importante:** varios nombres de campo se repiten en formas distintas con significado distinto (ej. `gastos` existe en `form_1065`, `form_1120`, `form_1120_s`, `form_1041` y `form_990`). Por eso todo endpoint que identifique un campo específico exige también `forma` — nunca alcanza con el nombre del campo solo.

### `campos_transversales` (aplican a cualquier `forma`)

| Campo | `tipo_campo` | `tipo_dato` / subcampos | `formatos_aceptados` | Obligatorio | Sensible |
|---|---|---|---|---|---|
| `identificacion_ssn_itin` | dato | `string` (SSN/ITIN, 9 dígitos) | — | sí | sí |
| `info_conyuge` | dato | `object` (`nombre_completo`, `fecha_nacimiento`, `ssn`) | — | sí | sí |
| `info_dependientes` | dato | `array_object` (`nombre_completo`, `fecha_nacimiento`, `ssn`) | — | sí | sí |
| `w2` | documento | — | `pdf`, `jpg`, `png`, `heic` | sí | no |
| `form_1099_nec` | documento | — | `pdf`, `jpg`, `png`, `heic` | sí | no |
| `estados_bancarios` | documento | — | `pdf`, `xlsx`, `csv` | sí | no |
| `pl_balance_general` | mixto | `number` | `pdf`, `xlsx` | sí | no |
| `gastos_deducibles` | mixto | `number` | `pdf`, `jpg`, `png` | sí | no |
| `activos_depreciacion` | mixto | `object` | `pdf`, `xlsx` | sí | no |
| `declaracion_anio_anterior` | documento | — | `pdf` | **no** | no |

### `form_1040`

| Campo | `tipo_campo` | `tipo_dato` / subcampos | `formatos_aceptados` | Obligatorio | Sensible |
|---|---|---|---|---|---|
| `ingresos` | dato | `number` | — | sí | no |
| `dependientes` | dato | `array_object` | — | sí | no |
| `deducciones` | mixto | `number` | `pdf`, `jpg` | sí | no |
| `creditos` | dato | `array_string` | — | sí | no |
| `impuestos_retenidos` | dato | `number` | — | sí | no |
| `info_bancaria` | dato | `object` (`banco`, `tipo_cuenta`, `numero_cuenta`, `routing_number`) | — | sí | sí |

### `schedule_c`

| Campo | `tipo_campo` | `tipo_dato` | `formatos_aceptados` | Obligatorio | Sensible |
|---|---|---|---|---|---|
| `ingresos_negocio` | dato | `number` | — | sí | no |
| `gastos_deducibles_negocio` | mixto | `number` | `pdf`, `jpg`, `csv` | sí | no |
| `millaje` | dato | `number` | — | sí | no |
| `activos` | mixto | `array_object` | `pdf`, `xlsx` | sí | no |
| `costo_ventas` | dato | `number` | — | sí | no |

### `schedule_e`

| Campo | `tipo_campo` | `tipo_dato` | `formatos_aceptados` | Obligatorio | Sensible |
|---|---|---|---|---|---|
| `ingresos_renta` | dato | `number` | — | sí | no |
| `gastos_propiedad` | mixto | `number` | `pdf`, `jpg` | sí | no |
| `depreciacion` | dato | `number` | — | sí | no |
| `intereses_hipotecarios` | documento | — | `pdf` | sí | no |
| `impuestos_propiedad` | documento | — | `pdf` | sí | no |
| `seguros_propiedad` | documento | — | `pdf` | sí | no |

### `form_1065`

| Campo | `tipo_campo` | `tipo_dato` | `formatos_aceptados` | Obligatorio | Sensible |
|---|---|---|---|---|---|
| `ingresos` | dato | `number` | — | sí | no |
| `gastos` | mixto | `number` | `pdf`, `xlsx` | sí | no |
| `activos` | mixto | `array_object` | `pdf`, `xlsx` | sí | no |
| `pasivos` | mixto | `array_object` | `pdf`, `xlsx` | sí | no |
| `aportes_socios` | dato | `array_object` | — | sí | no |
| `porcentajes_participacion` | dato | `array_object` | — | sí | no |
| `datos_k1` | documento | — | `pdf` | sí | no |

### `form_1120`

| Campo | `tipo_campo` | `tipo_dato` | `formatos_aceptados` | Obligatorio | Sensible |
|---|---|---|---|---|---|
| `estados_financieros` | documento | — | `pdf`, `xlsx` | sí | no |
| `ingresos` | dato | `number` | — | sí | no |
| `gastos` | mixto | `number` | `pdf`, `xlsx` | sí | no |
| `depreciacion` | dato | `number` | — | sí | no |
| `impuestos_pagados` | dato | `number` | — | sí | no |
| `activos` | mixto | `array_object` | `pdf`, `xlsx` | sí | no |
| `pasivos` | mixto | `array_object` | `pdf`, `xlsx` | sí | no |
| `balance_general` | documento | — | `pdf`, `xlsx` | sí | no |

### `form_1120_s`

| Campo | `tipo_campo` | `tipo_dato` | `formatos_aceptados` | Obligatorio | Sensible |
|---|---|---|---|---|---|
| `ingresos` | dato | `number` | — | sí | no |
| `gastos` | mixto | `number` | `pdf`, `xlsx` | sí | no |
| `estados_financieros` | documento | — | `pdf`, `xlsx` | sí | no |
| `nomina_compensacion_accionistas` | mixto | `array_object` | `pdf` | sí | no |
| `depreciacion` | dato | `number` | — | sí | no |
| `datos_k1` | documento | — | `pdf` | sí | no |

### `schedule_f`

| Campo | `tipo_campo` | `tipo_dato` | `formatos_aceptados` | Obligatorio | Sensible |
|---|---|---|---|---|---|
| `ventas_agricolas` | dato | `number` | — | sí | no |
| `subsidios` | dato | `number` | — | sí | no |
| `gastos_operacion` | mixto | `number` | `pdf`, `jpg` | sí | no |
| `maquinaria` | mixto | `array_object` | `pdf`, `xlsx` | sí | no |
| `animales` | dato | `array_object` | — | sí | no |
| `inventario` | mixto | `array_object` | `pdf`, `xlsx` | sí | no |

### `form_1041`

| Campo | `tipo_campo` | `tipo_dato` | `formatos_aceptados` | Obligatorio | Sensible |
|---|---|---|---|---|---|
| `ingresos` | dato | `number` | — | sí | no |
| `gastos` | mixto | `number` | `pdf`, `xlsx` | sí | no |
| `info_beneficiarios` | dato | `array_object` | — | sí | sí |
| `distribuciones` | dato | `array_object` | — | sí | no |
| `activos` | mixto | `array_object` | `pdf`, `xlsx` | sí | no |
| `documentos_fideicomiso` | documento | — | `pdf` | sí | no |

### `form_990`

| Campo | `tipo_campo` | `tipo_dato` | `formatos_aceptados` | Obligatorio | Sensible |
|---|---|---|---|---|---|
| `ingresos` | dato | `number` | — | sí | no |
| `gastos` | mixto | `number` | `pdf`, `xlsx` | sí | no |
| `donaciones` | mixto | `number` | `pdf`, `xlsx` | sí | no |
| `actividades_programas` | dato | `string` | — | sí | no |
| `compensacion_directivos` | dato | `array_object` | — | sí | no |
| `gobierno_corporativo` | dato | `string` | — | sí | no |

### `form_1040_nr`

| Campo | `tipo_campo` | `tipo_dato` / subcampos | `formatos_aceptados` | Obligatorio | Sensible |
|---|---|---|---|---|---|
| `ingresos_fuente_usa` | dato | `number` | — | sí | no |
| `formularios_retencion` | documento | — | `pdf` | sí | no |
| `info_migratoria` | dato | `object` (`tipo_visa`, `fecha_entrada_usa`, `estatus_migratorio`) | — | sí | no |
| `tratados_tributarios` | dato | `string` | — | sí | no |
| `deducciones_permitidas` | mixto | `number` | `pdf`, `jpg` | sí | no |

## Emitir un evento (`POST /api/eventos`)

Requiere ability `eventos:write`. Un evento = un solo campo, nunca varios juntos.

Para `modo: "texto"` (campos `string`, `number`, `object`, `array_string`, `array_object`) el body se puede mandar como **JSON puro** (`Content-Type: application/json`) — Laravel lee los campos igual, sea form-data o JSON. Para `modo: "archivo"` **no hay opción**: un archivo binario no entra en un JSON, así que ese caso siempre va como `multipart/form-data` (el archivo se sube directo en el mismo request — no existe un endpoint de subida separado).

### Ejemplos por tipo de dato

**`string`** — ej. `identificacion_ssn_itin`:

```json
{
  "cliente_id": 42,
  "forma": "form_1040",
  "campo": "identificacion_ssn_itin",
  "tipo_campo": "dato",
  "modo": "texto",
  "tipo_dato": "string",
  "contenido": "123-45-6789"
}
```

**`number`** — ej. `ingresos`:

```json
{
  "cliente_id": 42,
  "forma": "form_1040",
  "campo": "ingresos",
  "tipo_campo": "dato",
  "modo": "texto",
  "tipo_dato": "number",
  "contenido": 52000
}
```

**`object`** — ej. `info_conyuge` (subcampos `nombre_completo`, `fecha_nacimiento`, `ssn`):

```json
{
  "cliente_id": 42,
  "forma": "form_1040",
  "campo": "info_conyuge",
  "tipo_campo": "dato",
  "modo": "texto",
  "tipo_dato": "object",
  "contenido": {
    "nombre_completo": "Jane Doe",
    "fecha_nacimiento": "1990-05-14",
    "ssn": "987-65-4321"
  }
}
```

**`array_string`** — ej. `creditos`:

```json
{
  "cliente_id": 42,
  "forma": "form_1040",
  "campo": "creditos",
  "tipo_campo": "dato",
  "modo": "texto",
  "tipo_dato": "array_string",
  "contenido": ["child_tax_credit", "education_credit"]
}
```

**`array_object`** — ej. `info_dependientes` (siempre con el **arreglo acumulado completo**, no solo el elemento nuevo — ver nota más abajo):

```json
{
  "cliente_id": 42,
  "forma": "form_1040",
  "campo": "info_dependientes",
  "tipo_campo": "dato",
  "modo": "texto",
  "tipo_dato": "array_object",
  "contenido": [
    { "nombre_completo": "Kid One", "fecha_nacimiento": "2015-03-01", "ssn": "111-22-3333" },
    { "nombre_completo": "Kid Two", "fecha_nacimiento": "2018-09-20", "ssn": "444-55-6666" }
  ]
}
```

Cualquiera de los cinco de arriba se envía así con curl:

```bash
curl -X POST https://tu-dominio/api/eventos \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{ ... el json de arriba ... }'
```

**`documento`** (siempre archivo, sin `tipo_dato`) — ej. `w2`, y **`mixto`** cuando llega como archivo en vez de dato — únicos casos que van sí o sí como multipart:

```bash
curl -X POST https://tu-dominio/api/eventos \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -F "cliente_id=42" \
  -F "forma=form_1040" \
  -F "campo=w2" \
  -F "tipo_campo=documento" \
  -F "modo=archivo" \
  -F "file=@w2_2025.pdf"
```

Respuesta `201` (misma forma para cualquiera de los casos anteriores):

```json
{
  "cliente_id": 42,
  "forma": "form_1040",
  "forma_estado": "en_progreso",
  "campo": "w2",
  "estado": "recibido"
}
```

Notas:

- **`cliente_id` vacío/null** = primer contacto: la API crea un cliente nuevo (placeholder, sin nombre) y lo devuelve en la respuesta. Guarda ese `cliente_id` para los siguientes eventos de la misma persona.
- **`external_ref`** (opcional, extensión sobre el contrato original): identificador estable de la conversación externa (ej. el número de WhatsApp o el id de sesión del agente). Si lo envías la primera vez que `cliente_id` es null, y luego lo repites, la API reconoce que es el mismo cliente en vez de crear uno duplicado — protección recomendada si tu agente puede perder el `cliente_id` entre turnos.
- El `estado` que envíes (si lo envías) se ignora: **la API siempre calcula `estado` del lado del servidor** validando el contenido (SSN de 9 dígitos, fecha válida, número ≥ 0, formato de archivo aceptado, archivo legible). Un evento con contenido inválido igual se acepta y persiste con `estado: "invalido"` — no se rechaza con 422, salvo que la forma del evento esté mal (campo inexistente, `tipo_campo`/`modo` inconsistente con el catálogo, etc.).
- Reenviar el mismo `(cliente_id, forma, campo)` sobrescribe el valor anterior (idempotencia) y queda registrado en el historial de cambios.
- Para campos `array_object`/`array_string` (ej. `info_dependientes`), reenvía siempre el **arreglo acumulado completo** — la API sobrescribe, no hace merge parcial.

## Endpoints del panel

Requieren ability `clientes:read` (lectura) o `clientes:write` (escritura).

```
GET   /api/clientes                              — lista clientes visibles para el token, con estado general
GET   /api/clientes/{id}                         — detalle: formas aplicables + todos los campos y su estado
GET   /api/clientes/{id}/documentos               — documentos subidos, con URL de descarga firmada y temporal
GET   /api/clientes/{id}/export                  — descarga un ZIP con documentos + JSON de campos
GET    /api/clientes/{id}/campos/{campo}?forma=   — historial de cambios de un campo (forma es obligatoria)
PATCH  /api/clientes/{id}/campos/{campo}?forma=   — corrección manual de un campo por un preparador/administrador
DELETE /api/clientes/{id}/campos/{campo}?forma=   — elimina un campo cargado por error (conserva el historial)
POST   /api/clientes/{id}/marcar-revisado/{forma} — marca una forma como revisada por un humano
```

La corrección manual (`PATCH`) acepta el mismo shape que un evento de texto/archivo (`modo`, `tipo_dato`+`contenido`, o `file`), y queda registrada en el historial con `source: "preparador"` o `"administrador"` según quién la hizo (a diferencia de los eventos del agente, que quedan con `source: "agente_ia"`). `DELETE` borra la fila de `campos_cliente` (y el documento/archivo si era de tipo `documento`), pero agrega una entrada final al historial con `valor_nuevo: null` — nada se pierde de la trazabilidad.

## Panel de administración (solo web, sin API de token)

Tres áreas exclusivamente del panel web (sesión), sin equivalente sobre token — pensadas para el equipo interno, no para integraciones externas:

- **`/clientes`**: además de listar/ver, un preparador o administrador puede dar de alta un cliente manualmente (`POST /clientes`) y un administrador puede eliminarlo (`DELETE /clientes/{id}`, borra en cascada todos sus datos y archivos).
- **`/catalogo`** (solo administrador): CRUD de qué campos pide cada formulario — alta, edición y baja de definiciones (`tipo_campo`, `tipo_dato`, `formatos_aceptados`, `obligatorio`, `sensible`). Las 10 formas en sí son fijas; solo los campos dentro de cada una son editables. Borrar una definición no borra los datos de clientes ya cargados con ese campo — solo deja de pedirse/contar a futuro.
- **`/usuarios`** (solo administrador): alta, edición y baja de cualquier usuario (cliente, preparador o administrador), incluida la asignación/reasignación de preparador de un cliente. Un administrador no puede eliminarse a sí mismo.

## Revelar campos sensibles

Los campos marcados como sensibles en el catálogo (`identificacion_ssn_itin`, `info_conyuge`, `info_dependientes`, `info_bancaria`) se cifran en la base de datos y se muestran enmascarados en el panel y en la API de solo lectura. Revelar el valor real **solo está disponible desde el panel web** (no sobre token), exige reconfirmar la contraseña de la sesión (igual que el resto de acciones sensibles de la cuenta) y queda auditado (quién, cuándo, desde qué IP).

## Errores comunes

| Código | Causa típica |
|---|---|
| `401` | Token ausente, inválido o revocado. |
| `403` | Token sin la ability requerida, o el cliente/preparador no tiene acceso a ese recurso. |
| `404` | El cliente, campo o documento no existe (o no es visible para este token). |
| `422` | El evento/corrección está mal formado: campo inexistente en el catálogo para esa forma, `tipo_campo`/`modo` inconsistente, o falta un campo requerido de la request. |
| `429` | Límite de tasa excedido (hay throttling específico en revelar campos sensibles). |
