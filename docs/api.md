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

## Año fiscal (`tax_year`)

Desde la Fase 1 (2026-07-31), el catálogo de campos y todos los datos de cliente están versionados por año fiscal (`tax_year`, entero de 4 dígitos, ej. `2025`) — porque los montos de créditos, límites y reglas de dependientes cambian cada año, un mismo campo puede tener una definición distinta año a año, y los datos de un cliente para 2025 son completamente independientes de sus datos para 2026.

Impacto concreto en la API:

- **`POST /api/eventos`** exige `tax_year` en el body, siempre, sin default — un evento sin `tax_year` responde `422`.
- **`GET/PATCH/DELETE /api/clientes/{id}/campos/{campo}`** exigen también `?tax_year=` en el query string, igual que ya exigían `?forma=`.
- **`POST /api/clientes/{id}/marcar-revisado/{forma}`** exige `tax_year` en el body — es una acción mutante, tampoco tiene default.
- **`GET /api/clientes`** y **`GET /api/clientes/{id}`** aceptan `?tax_year=` **opcional**: si se omite, usan el año fiscal actual configurado en la plataforma (`2025` hoy) como default de conveniencia para un humano navegando el panel. A diferencia de los endpoints de arriba, acá sí hay default — son lecturas, no escrituras del agente.
- El catálogo también está versionado por año: un campo puede existir para `tax_year: 2025` y todavía no existir para `2026` hasta que un administrador lo agregue desde `/catalogo` para ese año.

## Catálogo maestro de campos

Cada campo pertenece a una de dos categorías:

- **Datos únicos por cliente** (`unico_por_cliente: sí`): son datos de la **persona**, no de una forma en particular (identificación, cónyuge, dependientes, W-2, 1099-NEC, declaración del año anterior). Se piden **una sola vez** y se comparten entre todas las formas del cliente. Internamente se guardan bajo la forma canónica **`transversal`** — una sola fila por `(cliente, campo)`, sin importar en qué forma llegó el evento. Ver [Cómo se guardan los datos únicos](#cómo-se-guardan-los-datos-únicos-por-cliente).
- **Campos por forma**: pertenecen a una **forma específica** (`form_1040`, `schedule_c`, `schedule_e`, `form_1065`, `form_1120`, `form_1120_s`, `schedule_f`, `form_1041`, `form_990`, `form_1040_nr`). Un mismo campo puede pedirse en varias formas y tener **un valor distinto en cada una** (ej. `estados_bancarios`, `gastos`, `activos`).

El catálogo completo (fuente de verdad) vive en `catalogo_campos` (administrable desde `/catalogo`) y se lee vía `App\Support\TaxFieldCatalog`; las tablas de abajo son su documentación exhaustiva, campo por campo — si cambia el catálogo, hay que actualizar esta sección también.

**Las tablas de abajo documentan el catálogo del año fiscal `2025`** (el baseline sembrado por el seeder — ver [Año fiscal](#año-fiscal-taxyear)). Un año fiscal distinto puede tener campos adicionales, faltantes, o con reglas distintas — consulta `/catalogo?tax_year=` para el año que te interese antes de asumir que esta tabla aplica igual.

Qué significa cada columna:

- **`tipo_campo`**: `documento` (el campo **solo** admite `modo: archivo`), `dato` (**solo** admite `modo: texto`), o `mixto` (admite cualquiera de los dos — el agente elige según lo que el cliente entregue realmente).
- **`tipo_dato`**: solo aplica cuando `modo: texto`. Uno de `string`, `number`, `object`, `array_string`, `array_object`.
- **`formatos_aceptados`**: solo aplica cuando `modo: archivo`. Extensiones de archivo válidas para ese campo — cualquier otra extensión hace que el evento se guarde con `estado: "invalido"`.
- **`obligatorio`**: si es `no`, ese campo no cuenta para que la API marque la forma como `completo` (ver [Emitir eventos](#emitir-un-evento-post-apieventos)).
- **`sensible`**: si es `sí`, el valor se cifra en la base de datos, se muestra enmascarado en el panel/API, y revelarlo exige el flujo de [Revelar campos sensibles](#revelar-campos-sensibles).
- **`unico_por_cliente`**: si es `sí`, es un dato de la persona que se guarda una sola vez (bajo la forma `transversal`) y se comparte entre todas sus formas.

**Nota importante:** varios nombres de campo se repiten en formas distintas con significado distinto (ej. `gastos` existe en `form_1065`, `form_1120`, `form_1120_s`, `form_1041` y `form_990`; `estados_bancarios` en todas las formas de negocio). Por eso todo endpoint que identifique un campo específico exige también `forma` — nunca alcanza con el nombre del campo solo. Para los datos únicos por cliente, la forma de almacenamiento es siempre `transversal` (ver más abajo).

### Datos únicos por cliente (`unico_por_cliente`, se guardan bajo `transversal`)

| Campo | `tipo_campo` | `tipo_dato` / subcampos | `formatos_aceptados` | Obligatorio | Sensible |
|---|---|---|---|---|---|
| `identificacion_ssn_itin` | dato | `string` (SSN/ITIN, 9 dígitos) | — | sí | sí |
| `info_conyuge` | dato | `object` (`nombre_completo`, `fecha_nacimiento`, `ssn`) | — | sí | sí |
| `info_dependientes` | dato | `array_object` (`nombre_completo`, `fecha_nacimiento`, `ssn`, `relacion`, `meses_en_hogar`, `estudiante_tiempo_completo`, `discapacitado`, `provee_mas_50_soporte_propio`, `ingreso_bruto_anual`, `custodia_compartida_sin_conflicto`) | — | sí | sí |
| `w2` | documento | — | `pdf`, `jpg`, `png`, `heic` | sí | no |
| `form_1099_nec` | documento | — | `pdf`, `jpg`, `png`, `heic` | sí | no |
| `declaracion_anio_anterior` | documento | — | `pdf` | **no** | no |
| `estado_civil` | dato | `object` (`casado_al_31_dic`, `convivio_conyuge_ultimos_6_meses`, `costeo_mas_mitad_hogar`, `existe_persona_calificable`, `conyuge_fallecio_en_anio`, `anio_fallecimiento_conyuge`) | — | sí | no |

Estos 7 cuentan para la completitud de **todas** las formas del cliente (basta cargarlos una vez). Al emitir el evento se envían con `forma: "transversal"` (ver [Cómo se guardan los datos únicos](#cómo-se-guardan-los-datos-únicos-por-cliente)). Los subcampos ampliados de `info_dependientes` y el campo nuevo `estado_civil` (Fase 2) alimentan el motor de reglas — capturan **hechos**, no conclusiones: nunca se le pregunta al cliente su filing status directamente, se deriva de estos datos.

### `form_1040`

| Campo | `tipo_campo` | `tipo_dato` / subcampos | `formatos_aceptados` | Obligatorio | Sensible |
|---|---|---|---|---|---|
| `ingresos` | dato | `object` (`salarios`, `intereses_dividendos`, `ganancias_capital`, `ingresos_jubilacion`, `otros_ingresos`, `ajustes_ingreso`) | — | sí | no |
| `deducciones` | mixto | `number` | `pdf`, `jpg` | sí | no |
| `gastos_cuidado_dependientes` | mixto | `object` (`proveedor_nombre`, `proveedor_ssn_ein`, `monto_anual`, `dependiente_relacionado`) | `pdf`, `jpg` | **no** | sí |
| `impuestos_retenidos` | dato | `number` | — | sí | no |
| `info_bancaria` | dato | `object` (`banco`, `tipo_cuenta`, `numero_cuenta`, `routing_number`) | — | sí | sí |

**`creditos` ya no existe en el catálogo** (Fase 2, Decisión A): pasó de ser un dato que se recolectaba (`array_string`) a ser 100% un resultado calculado por el motor de reglas (filing status, calificación de dependientes, AGI y créditos elegibles) — ver [Motor de reglas fiscales](#motor-de-reglas-fiscales-fase-2) más abajo. Ya no se pide ni se guarda vía `POST /api/eventos`.

### `schedule_c`

| Campo | `tipo_campo` | `tipo_dato` | `formatos_aceptados` | Obligatorio | Sensible |
|---|---|---|---|---|---|
| `estados_bancarios` | documento | — | `pdf`, `xlsx`, `csv` | sí | no |
| `ingresos_negocio` | dato | `number` | — | sí | no |
| `gastos_deducibles_negocio` | mixto | `number` | `pdf`, `jpg`, `csv` | sí | no |
| `millaje` | dato | `number` | — | sí | no |
| `activos` | mixto | `array_object` | `pdf`, `xlsx` | sí | no |
| `costo_ventas` | dato | `number` | — | sí | no |

### `schedule_e`

| Campo | `tipo_campo` | `tipo_dato` | `formatos_aceptados` | Obligatorio | Sensible |
|---|---|---|---|---|---|
| `estados_bancarios` | documento | — | `pdf`, `xlsx`, `csv` | sí | no |
| `ingresos_renta` | dato | `number` | — | sí | no |
| `gastos_propiedad` | mixto | `number` | `pdf`, `jpg` | sí | no |
| `depreciacion` | dato | `number` | — | sí | no |
| `intereses_hipotecarios` | documento | — | `pdf` | sí | no |
| `impuestos_propiedad` | documento | — | `pdf` | sí | no |
| `seguros_propiedad` | documento | — | `pdf` | sí | no |

### `form_1065`

| Campo | `tipo_campo` | `tipo_dato` | `formatos_aceptados` | Obligatorio | Sensible |
|---|---|---|---|---|---|
| `estados_bancarios` | documento | — | `pdf`, `xlsx`, `csv` | sí | no |
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
| `estados_bancarios` | documento | — | `pdf`, `xlsx`, `csv` | sí | no |
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
| `estados_bancarios` | documento | — | `pdf`, `xlsx`, `csv` | sí | no |
| `ingresos` | dato | `number` | — | sí | no |
| `gastos` | mixto | `number` | `pdf`, `xlsx` | sí | no |
| `estados_financieros` | documento | — | `pdf`, `xlsx` | sí | no |
| `nomina_compensacion_accionistas` | mixto | `array_object` | `pdf` | sí | no |
| `depreciacion` | dato | `number` | — | sí | no |
| `datos_k1` | documento | — | `pdf` | sí | no |

### `schedule_f`

| Campo | `tipo_campo` | `tipo_dato` | `formatos_aceptados` | Obligatorio | Sensible |
|---|---|---|---|---|---|
| `estados_bancarios` | documento | — | `pdf`, `xlsx`, `csv` | sí | no |
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

## Cómo se guardan los datos únicos por cliente

Los 6 campos marcados `unico_por_cliente` (identificación, cónyuge, dependientes, W-2, 1099-NEC, declaración del año anterior) son datos de la **persona**, no de una forma. Antes se guardaban con la `forma` de cada evento, así que el mismo dato quedaba **duplicado** si llegaba en el contexto de dos formas distintas (ej. un SSN en `form_1040` y otro en `schedule_c`). Ahora:

- Se guardan **una sola vez por cliente y por año fiscal**, bajo la forma canónica **`transversal`** (`campos_cliente.forma = "transversal"`), sin importar en qué `forma` llegó el evento. El mismo campo para dos `tax_year` distintos son dos filas independientes — ver [Año fiscal](#año-fiscal-taxyear).
- Al emitir el evento, envía **`forma: "transversal"`** (recomendado, es lo semánticamente correcto para un dato de la persona). También se acepta una forma real (ej. `form_1040`): la API la reubica igual bajo `transversal`. La respuesta del evento devuelve la `forma` que enviaste.
- Cuentan para la **completitud de todas** las formas del cliente: basta cargarlos una vez para que todas sus formas los den por recibidos.
- Reenviar uno de estos campos (en cualquier forma) **sobrescribe la única fila existente** — no crea una nueva por forma.
- En el detalle del cliente (`GET /api/clientes/{id}`) estos campos aparecen con `forma: "transversal"`, y en el panel web se muestran agrupados como **"Datos del cliente"**.
- Para los endpoints que identifican un campo por `forma` (historial, `PATCH`, `DELETE`), usa **`?forma=transversal`** para estos campos — es lo que devuelve el detalle. Pasar una forma real también funciona: la API la normaliza a `transversal` para estos campos.

Los demás campos (los que pertenecen a una forma) siguen guardándose por `(cliente, forma, campo)` como antes: un mismo campo puede tener valores distintos en formas distintas.

## Emitir un evento (`POST /api/eventos`)

Requiere ability `eventos:write`. Un evento = un solo campo, nunca varios juntos. **`tax_year` es obligatorio en todos los casos** (ver [Año fiscal](#año-fiscal-taxyear)) — se omitió de los ejemplos de versiones anteriores de este documento, pero la API lo exige desde la Fase 1.

Para `modo: "texto"` (campos `string`, `number`, `object`, `array_string`, `array_object`) el body se puede mandar como **JSON puro** (`Content-Type: application/json`) — Laravel lee los campos igual, sea form-data o JSON. Para `modo: "archivo"` **no hay opción**: un archivo binario no entra en un JSON, así que ese caso siempre va como `multipart/form-data` (el archivo se sube directo en el mismo request — no existe un endpoint de subida separado).

### Ejemplos por tipo de dato

**`string`** — ej. `identificacion_ssn_itin`:

```json
{
  "cliente_id": 42,
  "forma": "transversal",
  "tax_year": 2025,
  "campo": "identificacion_ssn_itin",
  "tipo_campo": "dato",
  "modo": "texto",
  "tipo_dato": "string",
  "contenido": "123-45-6789"
}
```

> `identificacion_ssn_itin` es un dato **único por cliente**, así que su `forma` es `transversal` (ver [Cómo se guardan los datos únicos](#cómo-se-guardan-los-datos-únicos-por-cliente)). También se aceptaría una forma real (ej. `form_1040`): la API lo reubica igual bajo `transversal`.

**`number`** — ej. `impuestos_retenidos`:

```json
{
  "cliente_id": 42,
  "forma": "form_1040",
  "tax_year": 2025,
  "campo": "impuestos_retenidos",
  "tipo_campo": "dato",
  "modo": "texto",
  "tipo_dato": "number",
  "contenido": 5200
}
```

**`object`** — ej. `info_conyuge` (subcampos `nombre_completo`, `fecha_nacimiento`, `ssn`):

```json
{
  "cliente_id": 42,
  "forma": "transversal",
  "tax_year": 2025,
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

**`object` — `ingresos` (Fase 2, desglosado — reemplaza al `number` suelto que tenía antes)**, siempre con las 6 claves presentes, `0` en las que no apliquen:

```json
{
  "cliente_id": 42,
  "forma": "form_1040",
  "tax_year": 2025,
  "campo": "ingresos",
  "tipo_campo": "dato",
  "modo": "texto",
  "tipo_dato": "object",
  "contenido": {
    "salarios": 52000,
    "intereses_dividendos": 0,
    "ganancias_capital": 0,
    "ingresos_jubilacion": 0,
    "otros_ingresos": 0,
    "ajustes_ingreso": 0
  }
}
```

**`object` — `estado_civil` (Fase 2, transversal — único por cliente)**: captura **hechos**, nunca la conclusión — nunca le preguntes al cliente "¿cuál es tu filing status?"; la plataforma lo calcula a partir de estos 6 valores:

```json
{
  "cliente_id": 42,
  "forma": "transversal",
  "tax_year": 2025,
  "campo": "estado_civil",
  "tipo_campo": "dato",
  "modo": "texto",
  "tipo_dato": "object",
  "contenido": {
    "casado_al_31_dic": false,
    "convivio_conyuge_ultimos_6_meses": false,
    "costeo_mas_mitad_hogar": true,
    "existe_persona_calificable": true,
    "conyuge_fallecio_en_anio": false,
    "anio_fallecimiento_conyuge": null
  }
}
```

**`mixto` como dato — `gastos_cuidado_dependientes` (Fase 2, `form_1040`, opcional)**: si el cliente no tuvo estos gastos, no se pide nada — no insistir.

```json
{
  "cliente_id": 42,
  "forma": "form_1040",
  "tax_year": 2025,
  "campo": "gastos_cuidado_dependientes",
  "tipo_campo": "mixto",
  "modo": "texto",
  "tipo_dato": "object",
  "contenido": {
    "proveedor_nombre": "Guardería Sol",
    "proveedor_ssn_ein": "12-3456789",
    "monto_anual": 4800,
    "dependiente_relacionado": "Kid One"
  }
}
```

**`array_string`**: hoy **ningún campo del catálogo usa este tipo** (el único que lo usaba, `creditos`, se eliminó en la Fase 2 — ver [Motor de reglas fiscales](#motor-de-reglas-fiscales-fase-2)). Se documenta la forma igual, por si se agrega un campo nuevo de este tipo más adelante:

```json
{
  "cliente_id": 42,
  "forma": "form_1040",
  "tax_year": 2025,
  "campo": "algun_campo_array_string",
  "tipo_campo": "dato",
  "modo": "texto",
  "tipo_dato": "array_string",
  "contenido": ["valor_uno", "valor_dos"]
}
```

**`array_object`** — `info_dependientes` (transversal, único por cliente): siempre con el **arreglo acumulado completo**, no solo el elemento nuevo (ver nota más abajo), y con los **10 subcampos** completos por dependiente (los 7 nuevos de la Fase 2 se agregan a los 3 que ya existían — nunca omitir una clave, usar `false`/`0`/`""` cuando no aplique):

```json
{
  "cliente_id": 42,
  "forma": "transversal",
  "tax_year": 2025,
  "campo": "info_dependientes",
  "tipo_campo": "dato",
  "modo": "texto",
  "tipo_dato": "array_object",
  "contenido": [
    {
      "nombre_completo": "Kid One",
      "fecha_nacimiento": "2015-03-01",
      "ssn": "111-22-3333",
      "relacion": "hija",
      "meses_en_hogar": 12,
      "estudiante_tiempo_completo": false,
      "discapacitado": false,
      "provee_mas_50_soporte_propio": false,
      "ingreso_bruto_anual": 0,
      "custodia_compartida_sin_conflicto": false
    },
    {
      "nombre_completo": "Kid Two",
      "fecha_nacimiento": "2018-09-20",
      "ssn": "444-55-6666",
      "relacion": "hijo",
      "meses_en_hogar": 12,
      "estudiante_tiempo_completo": false,
      "discapacitado": false,
      "provee_mas_50_soporte_propio": false,
      "ingreso_bruto_anual": 0,
      "custodia_compartida_sin_conflicto": false
    }
  ]
}
```

Cualquiera de los de arriba se envía así con curl:

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
  -F "forma=transversal" \
  -F "tax_year=2025" \
  -F "campo=w2" \
  -F "tipo_campo=documento" \
  -F "modo=archivo" \
  -F "file=@w2_2025.pdf"
```

**Límite de tamaño:** `file` acepta hasta **20 MB** en este endpoint (`POST /api/eventos`). Superarlo responde `422`. Nota: la corrección manual (`PATCH /api/clientes/{id}/campos/{campo}`, ver [Endpoints del panel](#endpoints-del-panel)) acepta solo **10 MB** — es un límite más chico a propósito, pensado para correcciones puntuales de un preparador, no para la carga inicial del agente.

Respuesta `201` (mismo shape para cualquiera de los casos anteriores). `forma` refleja la que enviaste; para un campo único enviado como `transversal`, `forma_estado` puede venir `null` si el cliente todavía no tiene ninguna forma iniciada (el dato igual queda guardado y contará cuando existan formas):

```json
{
  "cliente_id": 42,
  "forma": "transversal",
  "forma_estado": null,
  "campo": "w2",
  "estado": "recibido"
}
```

Notas:

- **`cliente_id` vacío/null** = primer contacto: la API crea un cliente nuevo (placeholder, sin nombre) y lo devuelve en la respuesta. Guarda ese `cliente_id` para los siguientes eventos de la misma persona.
- **`external_ref`** (opcional, extensión sobre el contrato original): identificador estable de la conversación externa (ej. el id de sesión del agente). Si lo envías la primera vez que `cliente_id` es null, y luego lo repites, la API reconoce que es el mismo cliente en vez de crear uno duplicado — protección recomendada si tu agente puede perder el `cliente_id` entre turnos.
- **`phone`** (opcional, extensión sobre el contrato original): teléfono del cliente. Si lo envías cuando `cliente_id` es null y ya existe un cliente con ese teléfono, la API reutiliza ese cliente en vez de crear uno duplicado — es un identificador más estable que `external_ref` para esto, y además queda guardado para poder [buscar al cliente por teléfono](#buscar-un-cliente-por-id-o-por-telefono) más adelante.
- El `estado` que envíes (si lo envías) se ignora: **la API siempre calcula `estado` del lado del servidor** validando el contenido (SSN de 9 dígitos, fecha válida, número ≥ 0, formato de archivo aceptado, archivo legible). Un evento con contenido inválido igual se acepta y persiste con `estado: "invalido"` — no se rechaza con 422, salvo que la forma del evento esté mal (campo inexistente, `tipo_campo`/`modo` inconsistente con el catálogo, etc.).
- Reenviar el mismo `(cliente_id, forma, campo, tax_year)` sobrescribe el valor anterior (idempotencia) y queda registrado en el historial de cambios. Para los campos `unico_por_cliente` la unicidad es por `(cliente_id, campo, tax_year)` — reenviarlos en cualquier `forma` sobrescribe la misma fila única (ver [Cómo se guardan los datos únicos](#cómo-se-guardan-los-datos-únicos-por-cliente)). **`tax_year` es parte de la identidad del dato**: el mismo campo enviado para dos años fiscales distintos crea **dos filas independientes**, no se sobrescriben entre sí.
- Para campos `array_object`/`array_string` (ej. `info_dependientes`), reenvía siempre el **arreglo acumulado completo** — la API sobrescribe, no hace merge parcial.

## Endpoints del panel

Requieren ability `clientes:read` (lectura) o `clientes:write` (escritura).

```
GET    /api/clientes?tax_year=                              — lista paginada de clientes visibles (tax_year opcional, default = año actual)
POST   /api/clientes                                         — alta de un cliente (mismas reglas que /clientes web)
GET    /api/clientes/buscar?id=|phone=|email=&tax_year=      — busca un cliente por id, teléfono o email (ver abajo)
GET    /api/clientes/{id}?tax_year=                          — detalle: formas aplicables + todos los campos y su estado (tax_year opcional, default = año actual)
GET    /api/clientes/{id}/documentos?tax_year=               — documentos subidos, con URL de descarga firmada y temporal (tax_year opcional)
GET    /api/clientes/{id}/export?tax_year=                   — descarga un ZIP con documentos + JSON de campos de ese año (tax_year opcional)
GET    /api/clientes/{id}/campos/{campo}?forma=&tax_year=    — historial de cambios de un campo (forma y tax_year obligatorios)
PATCH  /api/clientes/{id}/campos/{campo}?forma=&tax_year=    — corrección manual de un campo por un preparador/administrador (forma y tax_year obligatorios)
DELETE /api/clientes/{id}/campos/{campo}?forma=&tax_year=    — elimina un campo cargado por error (forma y tax_year obligatorios; conserva el historial)
POST   /api/clientes/{id}/marcar-revisado/{forma}            — marca una forma como revisada por un humano (tax_year obligatorio en el body)
```

`tax_year` en los endpoints de **lectura** (`GET`) es opcional: si se omite, usa el año fiscal actual configurado en la plataforma. En los que identifican/modifican un campo puntual (`campos/{campo}`, `marcar-revisado`) es **obligatorio** — ver [Año fiscal](#año-fiscal-taxyear).

### Listar clientes (`GET /api/clientes`)

Paginado, 20 por página. Acepta `?search=` (busca por nombre, email o teléfono), `?page=`, y `?tax_year=` (opcional — `estado_general` de cada cliente se calcula sobre las formas de ese año; si se omite, usa el año fiscal actual de la plataforma).

```bash
curl -s "https://tu-dominio/api/clientes?search=lopez&page=2&tax_year=2025" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"
```

```json
{
  "data": [
    { "id": 42, "name": "María López", "email": "maria@ejemplo.com", "phone": "+15551234567", "estado_general": "en_progreso" }
  ],
  "meta": { "current_page": 2, "last_page": 5 },
  "tax_year": 2025
}
```

### Crear un cliente (`POST /api/clientes`)

Requiere ability `clientes:write`. Pensado para dar de alta al cliente con datos reales **antes** de emitir eventos, en vez de dejar que `POST /eventos` genere el placeholder "Cliente sin nombre" cuando `cliente_id` llega vacío. Mismas reglas que el alta manual desde `/clientes` (rol fijo `client`, `email`/`phone` únicos):

```bash
curl -X POST https://tu-dominio/api/clientes \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{ "name": "María López", "email": "maria@ejemplo.com", "phone": "+15551234567" }'
```

Responde `201` con el mismo shape que `GET /api/clientes/{id}` (ver más abajo) — que ahora incluye `tax_year` en el nivel superior de la respuesta, con el año usado para resolver `formas`/`campos` (el actual de la plataforma, ya que la creación no recibe `tax_year` propio).

La corrección manual (`PATCH`) requiere `?forma=` **y `?tax_year=`** en el query string (ambos obligatorios, sin default), acepta el mismo shape que un evento de texto/archivo (`modo`, `tipo_dato`+`contenido`, o `file` — máx. **10 MB**, ver nota en [Emitir eventos](#emitir-un-evento-post-apieventos)), y queda registrada en el historial con `source: "preparador"` o `"administrador"` según quién la hizo (a diferencia de los eventos del agente, que quedan con `source: "agente_ia"`). Responde `200` con `{ "campo": "...", "estado": "..." }`.

`DELETE` también requiere `?forma=` **y `?tax_year=`**. Borra la fila de `campos_cliente` (y el documento/archivo si era de tipo `documento`), pero agrega una entrada final al historial con `valor_nuevo: null` — nada se pierde de la trazabilidad. Responde `204` sin body.

`POST .../marcar-revisado/{forma}` requiere `tax_year` en el body (obligatorio, sin default — es una acción mutante). Responde `200` con `{ "forma": "form_1040", "estado": "completo", "revisado_en": "2025-01-15T10:30:00Z" }`.

### Historial de un campo (`GET /api/clientes/{id}/campos/{campo}?forma=&tax_year=`)

`forma` y `tax_year` son ambos obligatorios (sin default) — identifican de forma única a qué instancia del campo corresponde el historial.

```json
{
  "data": [
    {
      "valor_anterior": null,
      "valor_nuevo": 52000,
      "source": "agente_ia",
      "modificado_por": null,
      "created_at": "2025-01-10T09:00:00Z"
    }
  ]
}
```

**Nota:** a diferencia del detalle del cliente, los valores de este historial **no se enmascaran** aunque el campo sea sensible (`HistorialCambio` no tiene el accessor de máscara que sí tiene `campos_cliente.valor`) — quien tenga ability `clientes:read` ve el valor real en texto plano en `valor_anterior`/`valor_nuevo`.

### Buscar un cliente por id, teléfono o email

Útil para que el agente conversacional resuelva el `cliente_id` a partir del teléfono o el email antes de emitir eventos, en vez de depender de `external_ref`:

```bash
curl -s "https://tu-dominio/api/clientes/buscar?phone=%2B15551234567&tax_year=2025" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"
```

Se le puede pasar `id` o `email` en vez de `phone` (`?id=42`, `?email=maria@ejemplo.com`) — hace falta exactamente uno de los tres. `tax_year` es opcional (default = año actual de la plataforma), igual que en `GET /api/clientes/{id}`. Devuelve el mismo shape que `GET /api/clientes/{id}` (incluye `phone`), respetando el mismo alcance de datos que el resto de la API: un preparador solo encuentra a sus clientes asignados. Si no hay ningún cliente visible con ese id/teléfono/email, responde `404`.

## Panel de administración (solo web, sin API de token)

Tres áreas exclusivamente del panel web (sesión), sin equivalente sobre token — pensadas para el equipo interno, no para integraciones externas:

- **`/clientes`**: además de listar/ver, un preparador o administrador puede dar de alta un cliente manualmente (`POST /clientes`) y un administrador puede eliminarlo (`DELETE /clientes/{id}`, borra en cascada todos sus datos y archivos).
- **`/catalogo?tax_year=`** (solo administrador): CRUD de qué campos pide cada formulario, **por año fiscal** — alta, edición y baja de definiciones (`tipo_campo`, `tipo_dato`, `formatos_aceptados`, `obligatorio`, `sensible`, `tax_year`). Las 10 formas en sí son fijas; los campos dentro de cada una, y el año fiscal al que pertenecen, son editables. Un campo nuevo se crea para el año seleccionado en el selector del panel — no se copia automáticamente a otros años. Borrar una definición no borra los datos de clientes ya cargados con ese campo — solo deja de pedirse/contar a futuro (para ese año).
- **`/usuarios`** (solo administrador): alta, edición y baja de cualquier usuario (cliente, preparador o administrador), incluida la asignación/reasignación de preparador de un cliente. Un administrador no puede eliminarse a sí mismo.
- **`POST /clientes/{cliente}/determinaciones`** (`tax_year` requerido en el body): dispara el motor de reglas fiscales para ese cliente y año — ver [Motor de reglas fiscales](#motor-de-reglas-fiscales-fase-2) abajo.

## Motor de reglas fiscales (Fase 2)

A partir de la Fase 2, la plataforma **calcula** (no solo recolecta) cuatro determinaciones fiscales por cliente y año: **filing status**, **calificación de dependientes** (qualifying child / qualifying relative), **AGI**, y **créditos elegibles** (Child Tax Credit, Credit for Other Dependents, crédito de cuidado de dependientes). El cálculo:

- Es **on-demand**, no automático: un preparador dispara `POST /clientes/{cliente}/determinaciones` (solo panel web, sesión — sin equivalente sobre token de agente) desde la ficha del cliente.
- Lee los datos ya recolectados (`estado_civil`, `info_dependientes`, `ingresos`, `gastos_cuidado_dependientes`) y persiste el resultado en `determinaciones_fiscales`, **separado** de `campos_cliente` — nunca se mezcla "lo que dijo el cliente" con "lo que calculó el sistema".
- Si falta un dato de entrada (ej. `estado_civil` nunca capturado), la determinación correspondiente queda con `disponible: false` y un `motivo_no_disponible` — nunca lanza un error 500 por datos incompletos.
- Los montos y umbrales del IRS (`parametros_fiscales`) están versionados por `tax_year`, igual que el catálogo — hoy sembrados con cifras de 2025 confirmadas por fuente pública (Child Tax Credit $2,200, phase-out $200k/$400k, límite de ingreso "qualifying relative" $5,200, entre otros). El porcentaje del crédito de cuidado de dependientes se aproxima linealmente entre 35% y 20% — es una simplificación documentada de la tabla escalonada real del IRS, no el valor exacto.
- **Estas cifras no reemplazan el criterio de un CPA** — están pensadas como apoyo al preparador, no como fuente final de verdad para presentar una declaración real.

## Revelar campos sensibles

Los campos marcados como sensibles en el catálogo (`identificacion_ssn_itin`, `info_conyuge`, `info_dependientes`, `info_bancaria`) se cifran en la base de datos y se muestran enmascarados en el panel y en la API de solo lectura. Revelar el valor real **solo está disponible desde el panel web** (no sobre token), exige reconfirmar la contraseña de la sesión (igual que el resto de acciones sensibles de la cuenta) y queda auditado (quién, cuándo, desde qué IP).

## Errores comunes

| Código | Causa típica |
|---|---|
| `401` | Token ausente, inválido o revocado. |
| `403` | Token sin la ability requerida, o el cliente/preparador no tiene acceso a ese recurso. |
| `404` | El cliente, campo o documento no existe (o no es visible para este token). |
| `422` | El evento/corrección está mal formado: falta `tax_year` (ver [Año fiscal](#año-fiscal-taxyear)), campo inexistente en el catálogo **para ese `tax_year` y esa forma** (puede existir en otro año y no en este), `tipo_campo`/`modo` inconsistente, falta un campo requerido de la request, o el archivo supera el límite de tamaño (ver [Emitir eventos](#emitir-un-evento-post-apieventos) y [Endpoints del panel](#endpoints-del-panel)). |

Ningún endpoint bajo `/api/*` tiene rate limiting hoy — un token puede llamarlos sin límite de tasa. (El `429` solo existe en `POST /clientes/{cliente}/campos/{campo}/reveal`, que es exclusivo del panel web con sesión, no un endpoint de esta API — ver [Revelar campos sensibles](#revelar-campos-sensibles).)
