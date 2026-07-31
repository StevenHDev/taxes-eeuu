# Reporte de cambios — agente conversacional externo (prompt)

> **Uso**: este documento se le pasa al LLM que mantiene el prompt del agente conversacional externo (el que habla con el cliente y usa las tools `crear_cliente_taxes`/`guardar_campo_cliente` contra esta plataforma), para que sepa exactamente qué actualizar en el prompt.
> Se completa **fase por fase**, a medida que cada fase de `plan-desarrollo-fases.md` queda cerrada en la plataforma — no se aplican cambios de una fase que todavía no está lista, aunque queden anticipados más abajo para contexto.

Leyenda: `[LISTO PARA APLICAR]` el cambio de plataforma ya existe, el prompt puede actualizarse ya · `[PENDIENTE]` anticipado, todavía no implementado del lado de la plataforma — no aplicar todavía.

---

## Fase 1 — Versionado por tax year `[LISTO PARA APLICAR]` (completada 2026-07-31)

### 1. Nuevo parámetro obligatorio `tax_year` en `guardar_campo_cliente`

- **Antes**: la tool recibía 7 parámetros — `cliente_id, forma, campo, tipo_campo, modo, tipo_dato, contenido`.
- **Ahora**: recibe **8 parámetros** — se agrega `tax_year` (entero de 4 dígitos, ej. `2025`), **siempre presente, nunca opcional, nunca con valor por default**.
- El endpoint real (`POST /api/eventos`) ya devuelve **422** si `tax_year` no viene en la petición. Sin este cambio, el prompt deja de poder guardar cualquier dato en cuanto se actualice a producción.
- `tax_year` se envía igual en cada invocación, sin importar si el campo es único por cliente (`forma="transversal"`) o de una forma específica — es una dimensión aparte de `forma`, no un reemplazo.

### 2. Nuevo paso en el árbol de determinación: confirmar el año fiscal

- Va **después del PASO 0** (verificación/creación de cuenta) y **antes** de empezar a recolectar cualquier campo del catálogo.
- Igual que con `forma`, no se asume el año por default — se pregunta explícitamente. Ejemplo: *"¿Esta declaración es para el año fiscal 2025?"*
- Si el cliente confirma otro año, ese es el `tax_year` a usar el resto de la conversación — no hay fallback silencioso.
- El valor resuelto (`tax_year`) se guarda igual que `cliente_id`: una vez determinado, se reutiliza en **todas** las invocaciones de `guardar_campo_cliente` de ahí en adelante, sin volver a preguntarlo.

### 3. Actualizar la sección "DEFINICIÓN DE LAS TOOLS" del prompt

- La documentación de `guardar_campo_cliente` dentro del prompt debe listar 8 parámetros, no 7, agregando `tax_year` con su regla de "siempre presente, entero, sin default".

### Nota para quien mantiene el prompt

- El año fiscal por defecto de la plataforma **hoy** es 2025 (confirmado con el cliente) — pero eso es solo el valor que un humano ve como sugerencia en los paneles internos; el agente conversacional **no debe usarlo como default silencioso**, siempre debe preguntar.
- El catálogo de campos (qué se pide bajo cada `forma`) todavía **no cambió** — sigue siendo el mismo que ya conocía el prompt. Los campos nuevos llegan en la Fase 2 (ver abajo).

---

## Fase 2 — Ampliación del catálogo + motor de reglas `[LISTO PARA APLICAR]` (completada 2026-07-31)

### 1. Nuevo campo transversal `estado_civil` — sub-entrevista de filing status

El prompt necesita una sub-entrevista nueva (una sola vez por cliente, como campo único global bajo `forma="transversal"`) que capture estos 6 hechos exactos — el objeto completo se guarda con `guardar_campo_cliente` (`campo: "estado_civil"`, `tipo_dato: "object"`):

- `casado_al_31_dic` (boolean)
- `convivio_conyuge_ultimos_6_meses` (boolean — solo aplica si casado)
- `costeo_mas_mitad_hogar` (boolean)
- `existe_persona_calificable` (boolean)
- `conyuge_fallecio_en_anio` (boolean)
- `anio_fallecimiento_conyuge` (entero o null — solo si `conyuge_fallecio_en_anio` es true)

**Importante para el prompt**: nunca le preguntes al cliente "¿cuál es tu filing status?" directamente — la plataforma lo calcula a partir de estos 6 hechos. El prompt solo recolecta los hechos, en lenguaje natural (ej. "¿Estuviste casado/a al 31 de diciembre?"), nunca mostrando los nombres técnicos de campo.

**Invocación exacta de `guardar_campo_cliente` para este campo:**

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

Las 6 claves del objeto `contenido` son obligatorias todas juntas — no se envían parciales. `anio_fallecimiento_conyuge` va como `null` salvo que `conyuge_fallecio_en_anio` sea `true`.

### 2. `info_dependientes` — 7 subcampos nuevos

Se agregan a los 3 que ya existían (`nombre_completo`, `fecha_nacimiento`, `ssn`):

`relacion` (string), `meses_en_hogar` (número 0-12), `estudiante_tiempo_completo` (boolean), `discapacitado` (boolean), `provee_mas_50_soporte_propio` (boolean), `ingreso_bruto_anual` (número), `custodia_compartida_sin_conflicto` (boolean).

El prompt necesita preguntar estos datos por cada dependiente que el cliente reporte (no una sola vez global — es por dependiente, dentro del mismo array acumulado de `info_dependientes`).

**Invocación exacta de `guardar_campo_cliente` para este campo (arreglo acumulado completo — cada envío reemplaza el arreglo entero, no hace "append" del lado del servidor, así que el prompt debe reenviar todos los dependientes ya conocidos más el nuevo/editado):**

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
    }
  ]
}
```

Los 10 subcampos son obligatorios en cada objeto del arreglo (no solo los 3 viejos) — un dependiente enviado con solo `nombre_completo/fecha_nacimiento/ssn` va a fallar la validación de la plataforma después de esta fase.

### 3. `ingresos` en `form_1040` — de number a object desglosado

- **Antes**: `ingresos` era un solo número.
- **Ahora**: es un objeto con 6 subcampos — `salarios, intereses_dividendos, ganancias_capital, ingresos_jubilacion, otros_ingresos, ajustes_ingreso`. El prompt debe recolectar cada uno por separado (o al menos preguntar "¿tuviste otros ingresos además de tu salario — intereses, dividendos, ganancias de capital, jubilación?" y llenar `0` en los que no apliquen — nunca omitir una clave del objeto).
- `tipo_dato` pasa de `"number"` a `"object"` en la invocación de `guardar_campo_cliente`.

**Invocación exacta de `guardar_campo_cliente` para este campo:**

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

**Importante**: si el prompt sigue enviando `ingresos` como un número suelto con `tipo_dato: "number"`, la plataforma ya rechaza la petición (422) — antes de este endurecimiento, un envío viejo se aceptaba en silencio y rompía el cálculo de AGI en producción.

### 4. Nuevo campo `gastos_cuidado_dependientes` en `form_1040` (opcional)

Objeto con `proveedor_nombre, proveedor_ssn_ein, monto_anual, dependiente_relacionado`. Es **opcional** — el prompt debe preguntar una vez ("¿tuviste gastos de cuidado de algún dependiente este año?") y, si el cliente dice que no, no insistir (igual que `declaracion_anio_anterior`).

**Invocación exacta de `guardar_campo_cliente` para este campo:**

```json
{
  "cliente_id": 42,
  "forma": "form_1040",
  "tax_year": 2025,
  "campo": "gastos_cuidado_dependientes",
  "tipo_campo": "dato",
  "modo": "texto",
  "tipo_dato": "object",
  "contenido": {
    "proveedor_nombre": "Guardería Los Pinitos",
    "proveedor_ssn_ein": "12-3456789",
    "monto_anual": 4800,
    "dependiente_relacionado": "Kid One"
  }
}
```

`dependiente_relacionado` debe ser el mismo `nombre_completo` ya usado en `info_dependientes`, para que la plataforma pueda cruzar ambos campos en el futuro.

### 5. `creditos` desaparece por completo — dejar de pedirlo

El campo `creditos` (que antes el prompt pedía como una lista de nombres de crédito, `tipo_dato: "array_string"`) **ya no existe en el catálogo**. El prompt debe **eliminar por completo** cualquier pregunta tipo "¿qué créditos aplican?" — los créditos ahora los calcula la plataforma automáticamente a partir de `estado_civil`, `info_dependientes` e `ingresos` (CTC, ODC y crédito de cuidado de dependientes, con las cifras oficiales del IRS para 2025).

**Ya no invocar** `guardar_campo_cliente` con esta forma (ejemplo de lo que había ANTES y debe eliminarse del prompt):

```json
{
  "cliente_id": 42,
  "forma": "form_1040",
  "tax_year": 2025,
  "campo": "creditos",
  "tipo_campo": "dato",
  "modo": "texto",
  "tipo_dato": "array_string",
  "contenido": ["child_tax_credit", "earned_income_credit"]
}
```

Si el prompt sigue enviando esto, la petición falla con 422 (el campo no existe en el catálogo para 2025). Ningún campo del catálogo usa hoy `tipo_dato: "array_string"` — era exclusivo de `creditos`.

---

## Fase 3 — Integración TaxWise (diferida)

No aplica al agente conversacional — es integración backend/preparador con TaxWise, no toca la conversación con el cliente.

---

## Mejoras al prompt, independientes de las fases de arriba `[PENDIENTE]`

Estas ya están identificadas y documentadas en `plan-desarrollo-fases.md` (sección 4), pero requieren trabajo de plataforma que todavía no se hizo:

- **Catálogo dinámico**: reemplazar el catálogo hardcodeado como texto en el prompt por una tool nueva que lo consulte contra un endpoint real (hoy no existe expuesto bajo `/api` para el agente externo).
- **Endpoint de pendientes**: `GET /api/clientes/{id}/pendientes` — combina catálogo vigente + datos ya guardados del cliente y devuelve directamente qué campos faltan, para que el prompt no tenga que llevar su propio checklist.
- **Extracción estructurada por documento**: sección nueva en el prompt para extraer datos de `declaracion_anio_anterior` (filing status, dependientes, AGI, créditos del año pasado) de forma sistemática, no incidental.
- **Regla de confirmación**: todo dato "de situación" extraído de un documento de años anteriores (estado civil, dependientes, cuenta bancaria) debe confirmarse con una pregunta corta antes de guardarse — nunca guardarse en silencio.
- **Priorizar `declaracion_anio_anterior`** más temprano en el flujo (hoy es casi lo último y opcional).
- **Regla de precedencia**: si el cliente responde algo directamente en el chat y un documento dice algo distinto, siempre gana la respuesta directa del cliente.
