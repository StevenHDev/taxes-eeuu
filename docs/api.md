# API de Documentos Fiscales

API REST para crear, consultar y administrar los documentos de un contribuyente (identificación, dependientes, W-2, 1099-NEC, estados bancarios, P&L, balance general, gastos deducibles, activos y la declaración del año anterior). Pensada para integraciones externas (contabilidad, apps móviles, scripts de importación), separada de la interfaz web (Inertia) de la aplicación.

## Autenticación

La API usa [Laravel Sanctum](https://laravel.com/docs/sanctum) con **tokens personales** (Bearer tokens), igual que un token de acceso personal de GitHub.

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

Cada token se crea con uno o más de estos permisos. Un endpoint que requiera un permiso que el token no tiene responde `403 Forbidden`, aunque el usuario sea dueño del documento.

| Ability | Qué habilita |
|---|---|
| `tax-documents:read` | Listar, ver el detalle y descargar archivos. |
| `tax-documents:write` | Crear, actualizar y eliminar documentos. |
| `tax-documents:reveal-ssn` | Obtener el SSN/ITIN en texto plano (ver [Revelar SSN/ITIN](#revelar-ssnitin)). No se marca por defecto al crear un token: es opt-in explícito dado lo sensible del dato. |

## Alcance de los datos

- Un token solo puede ver/gestionar los documentos del usuario dueño del token.
- Si el usuario es un **preparador** (`role = preparer`), también puede ver/gestionar los documentos de los clientes que tenga asignados. La asignación cliente↔preparador se hace fuera de la API (actualmente vía consola, no hay UI todavía).
- Un `client` normal solo ve sus propios documentos, sin importar qué contenga el token.

## Tipos de documento (`type`)

| Valor | Descripción | Requiere archivo | Requiere SSN/ITIN | Campos de dependiente | Requiere monto |
|---|---|:---:|:---:|:---:|:---:|
| `identification` | Identificación del contribuyente (SSN/ITIN) | | ✓ | | |
| `dependent` | Cónyuge o dependiente | | ✓ | ✓ | |
| `w2` | Formulario W-2 | ✓ | | | |
| `form_1099_nec` | Formulario 1099-NEC | ✓ | | | |
| `bank_statement` | Estado de cuenta bancario | ✓ | | | |
| `profit_and_loss` | Estado de resultados (P&L) | ✓ | | | |
| `balance_sheet` | Balance general | ✓ | | | |
| `deductible_expense` | Gasto deducible (con comprobante) | ✓ | | | ✓ |
| `asset_depreciation` | Activo / depreciación | ✓ | | | ✓ |
| `prior_year_return` | Declaración del año anterior | ✓ | | | |

## Endpoints

Base URL de ejemplo: `http://localhost:8000/api`. Todas las respuestas son JSON.

### Listar documentos

```
GET /api/tax-documents
```

Ability requerida: `tax-documents:read`.

Query params opcionales: `type` (uno de los valores de la tabla anterior), `fiscal_year`.

```bash
curl -s "http://localhost:8000/api/tax-documents?type=w2&fiscal_year=2024" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"
```

Respuesta (paginada, 15 por página):

```json
{
  "data": [
    {
      "id": 1,
      "user_id": 3,
      "type": "w2",
      "type_label": "Formulario W-2",
      "fiscal_year": 2024,
      "title": "W-2 — Acme Corp",
      "description": null,
      "ssn_itin_masked": null,
      "dependent_name": null,
      "dependent_date_of_birth": null,
      "amount": null,
      "file_original_name": "w2-acme.pdf",
      "file_mime_type": "application/pdf",
      "file_size": 102400,
      "download_url": "http://localhost:8000/api/tax-documents/1/download",
      "created_at": "2026-01-15T18:00:00.000000Z",
      "updated_at": "2026-01-15T18:00:00.000000Z"
    }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": null },
  "meta": { "current_page": 1, "last_page": 1, "per_page": 15, "total": 1 }
}
```

### Ver un documento

```
GET /api/tax-documents/{id}
```

Ability requerida: `tax-documents:read`.

### Crear un documento

```
POST /api/tax-documents
```

Ability requerida: `tax-documents:write`. Content-Type `multipart/form-data` si incluye archivo.

Campos comunes: `type` (requerido), `title` (requerido), `fiscal_year`, `description`.
Campos condicionales según `type` (ver tabla arriba): `ssn_itin`, `dependent_name`, `dependent_date_of_birth`, `amount`, `file`.

Si el usuario del token es un preparador, debe incluir además `user_id` con el id del cliente dueño del documento (debe ser uno de sus clientes asignados).

Ejemplo — W-2 con archivo:

```bash
curl -s -X POST "http://localhost:8000/api/tax-documents" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -F "type=w2" \
  -F "title=W-2 — Acme Corp" \
  -F "fiscal_year=2024" \
  -F "file=@/ruta/local/w2-acme.pdf;type=application/pdf"
```

Ejemplo — identificación (sin archivo):

```bash
curl -s -X POST "http://localhost:8000/api/tax-documents" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -d "type=identification" \
  -d "title=SSN del contribuyente" \
  -d "ssn_itin=123-45-6789"
```

Respuesta: `201 Created` con el documento creado (mismo shape que en el listado).

### Actualizar un documento

```
PUT /api/tax-documents/{id}
```

Ability requerida: `tax-documents:write`. Mismos campos que crear.

- El SSN/ITIN es de solo escritura: si lo dejas vacío, se conserva el valor cifrado existente. Solo se sobreescribe si envías un valor nuevo.
- El archivo es opcional en una actualización si el documento ya tiene uno guardado; si envías un archivo nuevo, reemplaza al anterior.

Como los navegadores/clientes HTTP no siempre soportan `PUT` con `multipart/form-data`, puedes usar `POST` con `_method=PUT`:

```bash
curl -s -X POST "http://localhost:8000/api/tax-documents/1" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -F "_method=PUT" \
  -F "type=w2" \
  -F "title=W-2 — Acme Corp (corregido)"
```

### Eliminar un documento

```
DELETE /api/tax-documents/{id}
```

Ability requerida: `tax-documents:write`. Respuesta: `204 No Content`.

### Descargar el archivo adjunto

```
GET /api/tax-documents/{id}/download
```

Ability requerida: `tax-documents:read`. Responde con el archivo binario (`Content-Disposition: attachment`). `404` si el documento no tiene archivo.

### Revelar SSN/ITIN

```
POST /api/tax-documents/{id}/reveal-ssn
```

Ability requerida: `tax-documents:reveal-ssn` (además de `tax-documents:read`/pertenencia). Un token sin esta ability recibe `403`, aunque el usuario sea dueño del documento — es una segunda barrera intencional para un dato tan sensible.

```bash
curl -s -X POST "http://localhost:8000/api/tax-documents/1/reveal-ssn" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"
```

```json
{ "ssn_itin": "123-45-6789" }
```

Cada llamada a este endpoint queda registrada (quién reveló, qué documento, IP) para auditoría.

## Errores

- `401 Unauthorized` — falta el header `Authorization` o el token no es válido.
- `403 Forbidden` — el token no tiene la ability requerida, o el usuario no es dueño del documento (ni su preparador asignado).
- `404 Not Found` — el documento no existe, o intentas descargar un documento sin archivo.
- `422 Unprocessable Entity` — error de validación. Cuerpo de ejemplo:

```json
{
  "message": "The file field is required.",
  "errors": {
    "file": ["The file field is required."]
  }
}
```

## Límites de tasa

- Todas las rutas comparten el throttle por defecto de Laravel para el grupo `api`.
- `POST /api/tax-documents/{id}/reveal-ssn` tiene además un límite propio de 10 solicitudes por minuto por usuario, dado lo sensible de la operación.
