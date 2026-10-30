# Fase 1 — Catálogo fiscal del año: plan técnico

29 oct. 2026 · **Aprobado** por Steven (29 oct.) · Documento madre: [secciones 3, 4, 6, 7.7, 9.3.1 y 9.5](../documento-madre.md)

> **Objetivo:** que la plataforma tenga, **por año fiscal**, todo lo que el IRS define para una persona natural: las preguntas (13614-C), los documentos y sus casillas, los formularios que se llenan con sus líneas hasta el 1040, los parámetros del año y las reglas de validación. Todo como **datos**, con su fuente del IRS, en una vista única que el preparador **ve** y el administrador **edita**. **No cambia todavía lo que ve el cliente ni lo que hace el agente**: la entrevista, el checklist y la lectura de documentos se apoyan en este catálogo desde la fase 2.
>
> **Buenas prácticas:** skills `laravel-best-practices`, `react-best-practices` y `vercel-react-best-practices`. Cada tarea cita la regla que aplica. A diferencia de la fase 0, esta fase tiene bastante frontend (el menú nuevo con 5 pestañas).
>
> **Base:** código de `main` al 29 oct. (Laravel 13, Inertia 3 + React 19, Pest 4), con F0.1, F0.2, F0.3, F0.5 y F0.6 cerradas (F0.4, el respaldo, queda bloqueada hasta definir el destino; no afecta a esta fase).

> **Contenido revisado.** Steven revisó las secciones 3 a 10 del documento madre (29 oct.). Aun así, todo se carga con estado **"sin validar"**: ese estado significa "todavía no se comparó contra la publicación del IRS", no "Steven no lo revisó". Se corrige desde la pantalla, sin tocar código.

## Resumen

| # | Tarea | Sale de | Tamaño | Riesgo |
| --- | --- | --- | --- | --- |
| 1.1 | Modelo de datos por año (5 piezas + historial de cambios) | 9.3.1 | Mediano | Bajo: tablas nuevas, nada existente cambia |
| 1.2 | Parámetros del año sobre `parametros_fiscales` | 7.7 | Pequeño | Medio: el motor de cálculo lee esa tabla |
| 1.3 | Cargar 2025 y 2026 desde el documento madre | 3, 4, 6.2, 7.7 | Grande (contenido) | Medio: errores de transcripción |
| 1.4 | Menú "Catálogo fiscal del año" con 5 pestañas (ver) | 7.7, 9.7 | Mediano | Bajo |
| 1.5 | Edición por el administrador | 7.7 | Mediano | Bajo |
| 1.6 | Equivalencias de `campos_cliente` y retiro de las pseudoformas (E12) | 9.3.1, 9.4 | Pequeño | Medio: ver la pregunta 1 para Steven |

Orden sugerido de commits: 1.1 → 1.2 → 1.3 → 1.4 → 1.5 → 1.6. Primero el modelo y los datos (se pueden revisar con el comando de verificación antes de que exista la pantalla); luego la vista; al final la edición y las equivalencias.

---

## 1.1 Modelo de datos por año

**Hoy:**
- `catalogo_campos` (`CampoCatalogo`) guarda **campos sueltos por forma**: `forma` (una `TaxForm` o las pseudoformas `transversal` y `documentos_extra`), `clave`, `tipo_campo`, `tipo_dato`, `formatos_aceptados`, `subcampos`, `obligatorio`, `sensible`, `unico_por_cliente`, `tax_year`. Lo lee `App\Support\TaxFieldCatalog` (caché `rememberForever`), y de ahí casi todo el flujo actual: `EventoRecoleccionService`, `EventoValidator`, `PortalFormularioController`, `ClienteController`, `AgenteToolService`, `ToolExecutor`, `EstadoConversacionResolver`, `ActivosResolver`, `DashboardSummaryService`.
- `relaciones_documento_campo` (`RelacionDocumentoCampo`) dice qué campo resuelve cada documento (`w2 → form_1040.ingresos`), sin casillas del IRS.
- No hay preguntas, ni casillas, ni líneas del 1040, ni reglas como datos. Los cambios al catálogo solo dejan el **nombre** del atributo en la bitácora (`AuditoriaObserver`), nunca el valor anterior.

**Enfoque** (reglas `arch-feature-folders`, `eloquent-relationships`, `eloquent-casts`, `eloquent-model-events`):
- **Tablas nuevas, sin tocar las viejas.** `catalogo_campos` sigue alimentando el formulario y el agente actuales hasta que la fase 2 los reemplace. Todo lo nuevo vive en `App\Models\CatalogoFiscal\*`, `App\Enums\CatalogoFiscal\*`, `App\Services\CatalogoFiscal\*` y `App\Http\Controllers\CatalogoFiscal\*`.
- Tablas (todas con `tax_year`; los códigos son únicos por año):

| Tabla | Qué guarda | Columnas clave |
| --- | --- | --- |
| `catalogo_anios` | Cada año fiscal y su estado | `tax_year` (único), `estado` (`borrador` / `vigente`), `copiado_de` |
| `catalogo_preguntas` | Pestaña 1 (sección 3) | `codigo` (A1…G5), `bloque` (A–G), `pantalla` (1–8), `orden`, `texto_es`, `texto_en`, `ayuda_es`, `ayuda_en`, `tipo_respuesta`, `opciones` (json), `nivel`, `por_persona` (bloque C), `sensible` (solo portal, 9.6), `condicion` (json: cuándo se muestra) |
| `catalogo_documentos` | Pestaña 2 (4.0-A, 4.1, 5.2) | `codigo` (`w2`, `1099-nec`…), `grupo`, `nombre_oficial`, `nombre_sencillo_es/en`, `emisor_es/en`, `fecha_llegada` (nullable), `si_no_llega_es/en`, `formatos` (json), `uno_por` (empleo, póliza, persona, único) |
| `catalogo_documento_casillas` | Casillas del documento en ese año | `documento_id`, `casilla` ("1", "1a", "Parte III col. A"), `descripcion`, `tipo_dato`, `clave` (casilla de interés para F4) |
| `catalogo_formularios` | Pestaña 3 (4.0-B) | `codigo` (`1040`, `sch-c`, `sch-se`, `8812`…), `nombre`, `para_que`, `siempre` (solo el 1040), `ficha` (`completa` / `pendiente`) |
| `catalogo_lineas` | Líneas de cada formulario | `formulario_id`, `linea` ("1a", "31"), `descripcion`, `origen_tipo` (`casilla` / `linea` / `respuesta` / `calculo`), `origen_id` o `origen_clave`, `campo_pdf` (nullable, se llena en F6), `orden` |
| `catalogo_activaciones` | La columna "Activa / descarta" | `pregunta_id`, `respuesta` (json: qué valor activa), `objetivo_tipo` (`pregunta` / `documento` / `formulario`), `objetivo_id`, `nota` |
| `catalogo_reglas` | Pestaña 5 (6.2) | `codigo` (R-01…), `descripcion`, `severidad` (`bloqueante` / `revision` / `informativa`), `implementada` (falso hasta F4) |
| `catalogo_cambios` | Historial con valor anterior y nuevo (7.7) | `tax_year`, `auditable_type/id`, `campo`, `valor_anterior`, `valor_nuevo`, `actor_id`, `origen` (`pantalla` / `carga`), `created_at`. Solo se inserta |

- **Fuente del IRS y validación en todas las piezas** (principio 1 y 7.7): las cinco tablas principales llevan `fuente_texto`, `fuente_url`, `validado_por` y `validado_en` (nulo = "sin validar"). Se comparte con un trait `TieneFuenteIrs` y un scope `sinValidar()` (regla `eloquent-query-scopes`).
- **Enums** con cast (regla `eloquent-casts`): `TipoRespuesta` (si_no, usted_conyuge_no, opciones, casillas, dato, fecha, numero), `NivelPregunta` (entrada, seguimiento, no_se_pregunta), `SeveridadRegla`, `OrigenLinea`, `EstadoAnio`.
- **El año en los textos** se guarda como `{anio}` ("¿De dónde recibiste dinero en {anio}?") y se reemplaza al mostrar. Así copiar 2025 → 2026 no obliga a reescribir 75 textos.
- **`condicion`** es un JSON pequeño y cerrado: `{"pregunta":"B1","es":"casado"}`, `{"todas":[…]}`, `{"alguna":[…]}`. En esta fase **solo se guarda y se valida** (los códigos existen en el mismo año); quien la evalúa es la entrevista de la fase 2. Se valida con una regla propia `CondicionCatalogoValida` (regla `validation-custom-rules`).
- **Historial:** un observer `CatalogoCambioObserver` escribe en `catalogo_cambios` el valor anterior y el nuevo en cada `updated` (regla `eloquent-model-events`). El catálogo no tiene datos de clientes, así que guardar valores aquí no choca con el criterio de `AuditoriaObserver`; además, los modelos nuevos se registran también en `AuditoriaObserver` para que la bitácora general los muestre.
- **Lector único** `App\Services\CatalogoFiscal\CatalogoFiscal` (regla `arch-service-classes`), con el mismo diseño de caché que `TaxFieldCatalog`: una clave por año (`catalogo_fiscal.{anio}`), invalidada al guardar. Métodos mínimos: `preguntas()`, `documentos()`, `formularios()`, `reglas()`, `activadosPor(string $codigoPregunta)`. Las fases 2–4 lo amplían; esta fase no lo usa fuera de la pantalla.
- **Sin borrado:** las piezas del catálogo se **desactivan** (`activo = false`), no se borran, para que una respuesta o una regla de un año ya trabajado nunca apunte a algo que no existe (mismo criterio que F0.3).

**Pruebas (Pest):** `tests/Feature/CatalogoFiscal/ModeloTest.php`
- Un código de pregunta es único por año y se puede repetir en otro año.
- Editar un parámetro o una pregunta deja una fila en `catalogo_cambios` con el valor anterior, el nuevo y el actor.
- `condicion` con un código que no existe en ese año no pasa la validación.
- La caché del lector se invalida al guardar.
- Desactivar una pieza no la borra y deja de aparecer en el lector.

---

## 1.2 Parámetros del año sobre `parametros_fiscales`

**Hoy:** `parametros_fiscales` (`ParametroFiscal`: `tax_year`, `categoria`, `clave`, `valor` json) tiene **31 valores de 2025** sembrados por `ParametrosFiscalesSeeder`. Los leen 9 calculadoras de `app/Services/Reglas` vía `App\Support\ParametrosFiscales::valor()` y `valorRequerido()`. No hay 2026, ni fuente, ni estado de validación, y no se ven en ninguna pantalla (7.7).

**Enfoque** (reglas `eloquent-casts`, `arch-service-classes`):
- **Se amplía la tabla existente, no se crea otra**, para no tocar las calculadoras. Columnas nuevas: `grupo` (deducción estándar, deducciones detalladas, ajustes, Schedule 1-A, créditos, topes y umbrales, tablas: los de 7.7), `nombre_es`, `condicion_es`, `formulario_linea`, `tipo` (`monto` / `porcentaje` / `tabla` / `fecha`), `orden`, más el trait `TieneFuenteIrs` de 1.1.
- `valor` pasa a **nullable**: un parámetro "pendiente" (sin cifra del IRS todavía) se ve en la pantalla y `valorRequerido()` sigue fallando con un mensaje claro si una calculadora lo pide. Nunca se inventa una cifra.
- `categoria` y `clave` **no cambian**: `ParametrosFiscales::valor()` sigue igual y las calculadoras no se tocan. El historial de 1.1 cubre también este modelo.
- Los 31 valores actuales quedan **"sin validar"**: el seeder dice que salieron de búsqueda web y que un CPA debe validarlos. La validación de cada uno es trabajo de la fase 6, que puede empezar en paralelo.

**Cómo quedó (29 oct.):** producción tiene 31 parámetros de 2025, no 54 como decía este plan. Sus metadatos (grupo, tipo, nombre, formulario y línea, condición) viven en `database/data/catalogo-fiscal/2025/parametros-metadatos.php`, que usa el seeder; la migración `2026_10_29_150000` tiene su propia copia fija para llenar producción. Se agregó el tipo `numero` (la edad límite del crédito por cuidado). `valorRequerido()` distingue "falta sembrar" de "pendiente".

**Pruebas:**
- Las calculadoras de `app/Services/Reglas` dan lo mismo antes y después de la migración (la suite actual de reglas pasa sin cambios).
- Un parámetro con `valor` nulo: `valor()` devuelve null y `valorRequerido()` lanza la excepción con el nombre del parámetro.
- Cambiar 2026 no toca 2025.

---

## 1.3 Cargar 2025 y 2026 desde el documento madre

**Hoy:** el catálogo se carga con seeders (`CatalogoCamposSeeder`, `RelacionesDocumentoCampoSeeder`, `ParametrosFiscalesSeeder`), que **no corren en el deploy**: `docker/entrypoint.sh` solo corre `php artisan migrate --force 2>/dev/null || true` (y además se traga los errores). Los cambios posteriores se metieron como migraciones de datos (por ejemplo `2026_09_17_130000_agrega_salarios_medicare_a_catalogo.php`).

**Enfoque** (reglas `arch-service-classes`, `arch-dto-pattern`):
- **El contenido vive en archivos de datos versionados**, uno por pestaña y año: `database/data/catalogo-fiscal/2025/{preguntas,documentos,formularios,parametros,reglas}.php`, que devuelven arrays planos. Son fáciles de revisar en un diff y de comparar con el documento madre.
- Servicio `CatalogoFiscalImportador` que lee esos archivos y hace `upsert` por (`tax_year`, `codigo`) dentro de una transacción. Es **idempotente**: correrlo dos veces no duplica nada. Si una fila ya fue editada desde la pantalla (tiene cambios en `catalogo_cambios` con origen `pantalla`), **no la pisa** salvo con `--forzar`, y lo informa.
- Comandos:
  - `php artisan catalogo-fiscal:cargar {anio} [--dry-run] [--forzar]`: muestra qué crea, qué actualiza y qué salta.
  - `php artisan catalogo-fiscal:verificar {anio}`: revisa la integridad y **termina con error** si algo falla. Revisa que el 1040 exista con `siempre = true` (9.5: "el 1040 siempre presente"), que toda activación y toda condición apunte a códigos del mismo año, que toda pregunta, documento, formulario y regla tenga fuente, y que los conteos coincidan con los archivos.
- **2025:** 75 preguntas (bloques A–G, 61 + 14 por persona del bloque C), los documentos del inventario 4.0-A con las casillas de las fichas 4.1–4.7 (W-2, 1099-NEC, 1099-K, 1095-A), los 26 formularios del inventario 4.0-B (7 con ficha completa y líneas; los demás con `ficha = pendiente`, sin líneas, como dice 4.0), los parámetros de 1.2 con los grupos de 7.7 y las 13 reglas R-01 a R-13.
- **2026:** se copia 2025 con un servicio `CatalogoFiscalCopiador` (también con comando: `catalogo-fiscal:copiar 2025 2026`) y después se aplica `database/data/catalogo-fiscal/2026/ajustes.php` con lo que el documento madre ya trae de 2026: mayor de 65 = nació antes del 2 ene. 1962; pariente calificado < USD 5.300; 1099-NEC casilla **1a** (revisión de dic. 2026). Los **montos de 2026 que no están en el documento madre quedan en "pendiente"** (`valor` nulo), no con la cifra de 2025. El año queda en estado `borrador`.
- **Schedule F no se carga:** no está en el inventario 4.0 de persona natural (E11, espera P3). Schedule C y E sí (C con ficha completa; E con ficha pendiente).
- **Seeders:** `DatabaseSeeder` llama al importador para 2025 y 2026, para que `migrate:fresh --seed` en local deje el catálogo completo.

**Pruebas:** `tests/Feature/CatalogoFiscal/CargaTest.php`
- `cargar 2025` dos veces deja los mismos conteos (idempotente); `--dry-run` no escribe.
- No pisa una fila editada en pantalla sin `--forzar`.
- `verificar 2025` y `verificar 2026` pasan; una activación rota o el 1040 sin `siempre` hacen fallar el comando.
- **Caso María** (4.8): desde las activaciones cargadas, D1 lleva al W-2, C2 + C9 al Schedule 8812 y al EIC, E6 al Form 2441 y al recibo del proveedor, y el W-2 casilla 1 llega a la línea 1a del 1040. No evalúa respuestas (eso es la fase 2), solo que el grafo esté conectado.
- 2026: la casilla del 1099-NEC es "1a", el parámetro del pariente es 5.300 y un monto que no está en el documento madre queda en pendiente.

**Cómo quedó (29 oct.):**
- Contenido de 2025 en `database/data/catalogo-fiscal/2025/` (formato en el README de esa carpeta): 75 preguntas (A15, B6, C14 por persona, D17, E9, F9, G5) en las 8 pantallas de entrada de la sección 3, 41 documentos con 19 casillas (W-2, 1099-NEC, 1099-K, 1095-A), 26 formularios (7 con ficha completa, 3 conectores, 16 pendientes) con 63 líneas, 106 activaciones y 13 reglas. Más 1 parámetro nuevo: la fecha de nacimiento para "mayor de 65".
- 2026 (`2026/ajustes.php`): pariente calificado USD 5.300, mayor de 65 = nació antes del 2 ene. 1962, 1099-NEC casilla 1 → 1a (y la línea 1 del Schedule C que la lee), y el texto de C13. Todo lo demás de 2026 queda pendiente.
- Se agregó el tipo de ficha `conector` (Schedules 1, 2 y 3) y la columna `efecto_es` (lo que dice "Activa / descarta"). `nombre_oficial` de un documento es opcional (una identificación o un recibo no son formularios del IRS); `nombre_sencillo_es` es obligatorio.
- El importador rechaza claves desconocidas en los archivos (los modelos las descartarían en silencio). La carga sin usuario no va a la bitácora general (serían cientos de filas); su rastro queda en `catalogo_cambios`.
- `CatalogoFiscalSeeder` (en `DatabaseSeeder`) carga 2025, copia a 2026 y aplica los ajustes, para `migrate:fresh --seed` en local.
- Decisiones tomadas al transcribir, para revisar en la pantalla: Schedule D y Form 8949 van juntos (`sch-d`); B5 es de entrada para todos (como en el ejemplo de María); C12–C14 se muestran según el parentesco (C3), y las demás pruebas (meses en C4, otro padre) las resuelve la fase 2; las preguntas "Sí / No + número o fecha" quedan como Sí / No; sensibles (solo portal): A3, A12, B4, C9, C10, G3 y G4; los documentos que el documento madre no describe quedan sin emisor, fecha ni "si no te llega".

**Riesgo:** es mucho contenido escrito a mano. Se mitiga con el comando de verificación, con la prueba de María y con la revisión de Steven en la propia pantalla (pestañas con filtro "sin validar").

---

## 1.4 Menú "Catálogo fiscal del año" con 5 pestañas (ver)

**Hoy:** el menú "Catálogo" (`resources/js/pages/catalogo/index.tsx`, 610 líneas, `CatalogoController@index`) lista los campos por forma con el inspector de relaciones y las estadísticas de `CampoDerivationLog`. Solo lo ve el administrador (`CatalogoPolicy::viewAny`). En `app-sidebar.tsx` aparece solo para `esAdministrador`.

**Enfoque backend** (reglas `controller-resource-controllers`, `controller-middleware`, `controller-dependency-injection`):
- Rutas en `routes/catalogo-fiscal.php`: `catalogo-fiscal/{anio}/{preguntas|documentos|formularios|parametros|reglas}`. `catalogo-fiscal` redirige al año de `config('tax.current_tax_year')` y a "Preguntas".
- Un controlador por pestaña en `App\Http\Controllers\CatalogoFiscal\` (`PreguntaController`, `DocumentoController`, `FormularioController`, `ParametroController`, `ReglaController`). Cada `index` manda **solo los datos de su pestaña** (vercel: `server-serialization`), con `with()` para casillas, líneas y activaciones (regla `eloquent-eager-loading`).
- Policy nueva `CatalogoFiscalPolicy`: `viewAny` para preparador y administrador; `create`, `update` y `validar` solo administrador. El frontend recibe `puedeEditar` calculado con la policy; el cliente recibe 403.
- La tabla de cada pestaña va como **prop diferida** de Inertia (`Inertia::defer`), para que la cabecera y las pestañas se pinten de inmediato y la tabla llegue después.

**Enfoque frontend** (react-best-practices: *derivar durante el render*, sin `useEffect` para filtros; vercel: `rerender-derived-state-no-effect`, `rerender-use-deferred-value`, `rerender-no-inline-components`, `rendering-conditional-render`, `bundle-barrel-imports`):
- Layout `resources/js/layouts/catalogo-fiscal/layout.tsx`, con el **mismo patrón de pestañas que `layouts/agente/layout.tsx`** (links con `Button variant="ghost"` y `isCurrentOrParentUrl`). Las pestañas son enlaces reales (se pueden abrir, compartir y volver atrás); se precargan al pasar el mouse (`prefetch` de Inertia; vercel: `bundle-preload`).
- Páginas `resources/js/pages/catalogo-fiscal/{preguntas,documentos,formularios,parametros,reglas}.tsx`. Componentes de cada fila y diálogos definidos **fuera** del componente de la página.
- Filtros por pestaña (bloque y nivel; grupo; formulario; severidad; "sin validar") y búsqueda por texto. El resultado se calcula en el render a partir de las props; la búsqueda usa `useDeferredValue` para que escribir no se trabe.
- Formularios: cada formulario se abre y muestra sus líneas con el origen ("W-2 casilla 1", "Schedule C línea 31", "cálculo"), y el camino hasta el 1040. El 1040 va primero y fijo.
- Sidebar: "Catálogo fiscal del año" visible para preparador y administrador. El menú viejo "Catálogo" se trata en 1.6.

**Reglas de interfaz (todas las pestañas):**

| Regla | Cómo se cumple |
| --- | --- |
| **Carga (skeleton)** | Mientras llega la prop diferida, `<Deferred fallback={…}>` muestra filas con `ui/skeleton` del mismo alto que la tabla real (sin saltos al cargar). Al cambiar de año o de pestaña se usa el mismo skeleton |
| **Responsive** | En `md` y más: tabla (`ui/table`). Debajo de `md`: se esconden las columnas secundarias (`hidden md:table-cell`) y cada fila abre el detalle completo en un `ui/sheet`. Filtros en una fila que se apila en móvil. Sin scroll horizontal de página |
| **Jerarquía visual** | Cabecera con título, selector de año y **una sola acción principal** (administrador: "Agregar pregunta/documento…" con `Button` primario; preparador: ninguna). Código (A1, W-2, R-04) en monoespaciado al inicio de cada fila; estado con `ui/badge` al final; contadores por grupo ("12 sin validar") arriba de la tabla para escanear rápido |
| **Consistencia** | Solo componentes de `resources/js/components/ui` (`table`, `badge`, `button`, `dialog`, `sheet`, `select`, `input`, `textarea`, `checkbox`, `label`, `tooltip`, `skeleton`, `spinner`) y `alert-error`, `input-error`, `heading`. Textos en `es.json` y `en.json` |
| **Accesibilidad** | Botones de solo ícono con `aria-label` que nombra la fila ("Ver fuente del IRS de A1", "Editar R-04") y `tooltip`. Navegación de pestañas con `aria-label` y `aria-current="page"`. Todos los campos con `Label` asociado. Estados que no dependen solo del color (la insignia dice "Sin validar" y lleva ícono). Foco visible (anillo de los componentes `ui`, sin quitar `outline`). Contraste AA en las insignias en tema claro y oscuro |
| **Estados de la interfaz** | **Vacío:** "Este año no tiene preguntas cargadas" (administrador: botón para agregar; preparador: "Pídele a un administrador que lo cargue"). **Sin resultados del filtro:** mensaje + "Limpiar filtros". **Error:** `alert-error` arriba si falla la carga diferida o un guardado. **Éxito:** toast con `use-flash-toast` al guardar |
| **Estados de interacción** | Hover, foco y activo de los componentes `ui`. Botones `disabled` cuando no aplican (por ejemplo "Marcar validado" si ya lo está). Mientras se guarda: botón deshabilitado con `ui/spinner` y texto "Guardando…" (estado `processing` del formulario de Inertia), sin poder enviar dos veces |

**Cómo quedó (29 oct.):**
- Rutas `catalogo-fiscal/{anio}/{pestaña}` con un controlador por pestaña (`App\Http\Controllers\CatalogoFiscal\*`) y el trait `ArmaPaginaCatalogo` (permiso, año, años, `puedeEditar` y la tabla como prop diferida con `rescue`). `CatalogoFiscalPolicy` registrada sobre `CatalogoAnio`.
- Se reutiliza el `DataTable` de Clientes y Usuarios (búsqueda, filtros por facetas, orden, columnas, paginación) para que el catálogo se vea igual que el resto. El detalle de cada pieza abre en un panel lateral (`ui/sheet`).
- Los documentos se ordenan por grupo como el inventario 4.0-A (identidad primero). La cabecera usa su propio `h1`.
- Probado en el navegador: las 5 pestañas, el detalle de A7 y del 1040, el cambio de año (2026 con montos "Pendiente") y el skeleton. Falta mirar el ancho de celular en el navegador (la ventana no se dejó achicar).

**Pruebas:** `tests/Feature/CatalogoFiscal/PantallaTest.php`
- Preparador y administrador ven las 5 pestañas; el cliente recibe 403.
- `puedeEditar` es falso para el preparador y verdadero para el administrador.
- Cada pestaña devuelve solo sus datos y del año pedido (2025 no mezcla 2026).
- Un año sin datos responde vacío, no error.
- `npm run build` y `npm run types:check` sin errores.

---

## 1.5 Edición por el administrador

**Hoy:** `CatalogoController@store/update/destroy` con `CatalogoCampoRequest` edita `catalogo_campos` y llama a `TaxFieldCatalog::invalidate()`. Se puede **borrar** un campo. No queda el valor anterior.

**Enfoque** (reglas `controller-form-requests`, `validation-form-requests`, `validation-array-validation`, `validation-conditional-rules`, `sec-mass-assignment`):
- `store` y `update` en cada controlador de 1.4, con un Form Request por pieza (`PreguntaRequest`, `DocumentoRequest` con sus casillas, `FormularioRequest` con sus líneas, `ParametroRequest`, `ReglaRequest`). `authorize()` usa la policy. `opciones`, `casillas.*` y `lineas.*` se validan como arreglos; `condicion` con `CondicionCatalogoValida`; el `valor` de un parámetro según su `tipo`.
- Sin `destroy`: se **desactiva** (1.1). Cambiar `codigo` no se permite una vez creado (otras piezas apuntan a él).
- Acción **"Marcar como validado contra el IRS"** (`POST …/{pieza}/validar`): guarda quién y cuándo, y exige que tenga `fuente_url`. Editar después el valor o el texto **reabre** la validación (vuelve a "sin validar"), igual que pide 6.4 para los datos.
- Cada guardado: transacción, historial (1.1), invalidar la caché del año y `back()` con toast.
- **Año vigente protegido:** un año en estado `vigente` muestra un aviso antes de guardar ("este cambio afecta a los clientes de {anio}"). No bloquea.
- Frontend: diálogos (`ui/dialog`) con el componente `Form` de Inertia; `processing` deshabilita y muestra spinner; errores con `input-error` junto a cada campo. Sin estado duplicado de las props (react-best-practices: el formulario se reinicia con `key` al abrir otra fila).

**Cómo quedó (30 oct.):**
- `CatalogoFiscalEditor` aplica las reglas: editar el contenido de algo validado lo vuelve a "sin validar" (cambiar solo el orden o activar/desactivar no), validar exige `fuente_url`, nada se borra. Las casillas y las líneas se sincronizan por su clave: las que se quitan se desactivan y, si cambian, la pieza vuelve a "sin validar".
- Un Form Request por pieza (`App\Http\Requests\CatalogoFiscal\*`) sobre una base común: solo el administrador, el código solo al crear (y único en el año), fuente con URL válida. Los parámetros no se crean desde la pantalla (las calculadoras los leen por clave): se edita su valor, se puede dejar pendiente y se valida según su tipo (monto, porcentaje entre 0 y 1, fecha, tabla de tramos).
- `PiezaController` con "Marcar como validado" y activar/desactivar para las 5 pestañas; el Form 1040 no se puede desactivar.
- El administrador ve también lo desactivado (con la etiqueta "Desactivado") para poder reactivarlo; el preparador solo ve lo activo.
- Frontend: botón principal "Agregar …" en cada pestaña (menos Parámetros); acciones en el panel de detalle ("Editar" como principal; validar y desactivar como secundarias, con su estado de "procesando"); diálogos con `useForm` de Inertia; errores junto a cada campo (enlazados con `aria-describedby`); filas editables para opciones, casillas, líneas y tramos; aviso si el año está vigente; aviso de éxito al guardar.
- El diálogo y el panel de detalle viven fuera de `<Deferred>`: al guardar, o si el servidor devuelve errores, la tabla se vuelve a cargar y antes se llevaba el diálogo con ella (se perdían los errores; visto al probar en el navegador).
- Probado en el navegador: marcar como validado, editar (vuelve a "sin validar" con aviso) y crear con errores (el diálogo queda abierto con los mensajes). Los formularios de preguntas, documentos, formularios y parámetros están cubiertos por las pruebas, no se probaron en pantalla.
- Las activaciones (qué abre cada respuesta) no se editan todavía desde la pantalla: se cambian en los archivos de datos y con `catalogo-fiscal:cargar`. Se suman cuando la fase 2 las use.

**Pruebas:**
- El administrador crea, edita, desactiva y valida en cada pestaña; el preparador recibe 403 en todas las escrituras.
- Editar un parámetro validado lo deja "sin validar" y guarda el valor anterior.
- No se puede validar sin fuente; no se puede cambiar el `codigo`.
- Validaciones: opciones vacías en una pregunta de opciones, severidad inválida, casilla repetida en el mismo documento.

---

## 1.6 Equivalencias de `campos_cliente` y retiro de las pseudoformas (E12)

> **Pasó a la fase 2 (decisión de Steven, 30 oct.).** El formulario actual se reemplaza en F2 por la entrevista del catálogo nuevo; el mapa de equivalencias y el cambio de nombre del menú viejo se hacen ahí, cuando ya existe dónde guardar las respuestas nuevas. La fase 1 se cierra con 1.1 a 1.5 en producción.

**Hoy:**
- `campos_cliente` (`CampoCliente`: `user_id`, `forma`, `tax_year`, `campo`, `valor_texto` cifrado, `documento_id`, `estado`, `source`) guarda lo que respondió cada cliente, por forma o bajo `transversal` / `documentos_extra`.
- Las pseudoformas se usan en todo el flujo vivo: `CampoCatalogo::pseudoFormas()`, `TaxFieldCatalog::formaAlmacen()`, `EventoRecoleccionService`, `EventoRequest`, `CampoClienteUpdateRequest`, `PortalFormularioController`, `ClienteController`, `ToolExecutor` y la tool `consultar_documentos_extra` (`ToolDefinitions`), los prompts `prompt_actuales/fases/recoleccion.md` y `cierre.md`, y las páginas `portal/formulario.tsx`, `clientes/show.tsx` y `mi-informacion.tsx`.
- Producción tiene muy pocos clientes.

**Problema:** el checklist de F1 dice "migrar `campos_cliente` y eliminar E12", pero en esta fase **todavía no existe dónde guardar las respuestas nuevas** (eso es la entrevista de la fase 2), y quitar las pseudoformas ahora obliga a reescribir el formulario y el agente actuales justo antes de que la fase 2 los reemplace.

**Enfoque propuesto** (reglas `eloquent-chunking`, `arch-service-classes`):
- **En F1:** archivo `database/data/catalogo-fiscal/equivalencias-campos-cliente.php` que dice, para cada `forma` + `campo` (+ subcampo) de `catalogo_campos` 2025, a qué **pregunta** o **documento** del catálogo nuevo corresponde, o "sin equivalente" con el motivo. Ejemplos: `transversal.estado_civil → B1`; `transversal.w2 → documento w2`; `form_1040.ingresos` (monto digitado) → sin equivalente: "se lee del documento" (9.3.2).
- Comando `php artisan catalogo-fiscal:equivalencias --reporte` que recorre `campos_cliente` por partes y muestra, sin escribir nada: cuántos datos tienen equivalente, cuántos no y cuáles claves del catálogo viejo no están en el archivo. **Nunca muestra valores** (son datos cifrados del cliente).
- El catálogo viejo sigue funcionando y editable, pero sale del sidebar como "Catálogo" y queda accesible solo para el administrador como **"Campos del formulario actual"**, con un aviso de que se retira en la fase 2.
- **En F2 (al cambiar de flujo):** se mueven los datos a las respuestas nuevas usando este mismo archivo, y se borran las pseudoformas, la tool `consultar_documentos_extra` y `/api/catalogo/documentos-extra` (este último junto con E3). `campos_cliente` no se borra: se conserva como histórico (L12).

**Pruebas:**
- Toda clave de `catalogo_campos` 2025 en alcance aparece en el archivo de equivalencias (con destino o con "sin equivalente").
- Todo destino del archivo existe en el catálogo 2025.
- El reporte cuenta bien con datos de prueba y no imprime valores.

---

## Migración de datos existentes

| Qué | Cómo | Se puede repetir |
| --- | --- | --- |
| Tablas nuevas de 1.1 | Migraciones que solo crean tablas | — |
| Columnas nuevas de `parametros_fiscales` (1.2) | Migración que agrega columnas nullable y pasa `valor` a nullable; una migración de datos pone `grupo`, `nombre_es` y `tipo` a los 31 valores actuales | Sí (`whereNull`) |
| Contenido 2025 y 2026 (1.3) | Comandos `catalogo-fiscal:cargar` y `copiar`, corridos a mano después del deploy | Sí (idempotente) |
| `campos_cliente` (1.6) | Solo reporte en F1; la migración real va con la fase 2 | Sí (no escribe) |

No se modifica ni se borra ningún dato existente de clientes en esta fase.

## Despliegue

1. Desplegar el código (push a `main`).
2. **Verificar las migraciones**, porque el entrypoint se traga los errores: `php artisan migrate:status` en el contenedor del frontend y confirmar que las migraciones de F1 dicen `Ran`. Si alguna no corrió, correr `php artisan migrate --force` a mano y leer el error.
3. `php artisan catalogo-fiscal:cargar 2025 --dry-run`, revisar, y luego sin `--dry-run`.
4. `php artisan catalogo-fiscal:copiar 2025 2026` y `php artisan catalogo-fiscal:cargar 2026` (aplica los ajustes).
5. `php artisan catalogo-fiscal:verificar 2025` y `… 2026`: deben terminar sin errores.
6. Confirmar que el motor sigue igual: abrir la determinación fiscal de un cliente existente y ver que da lo mismo que antes.
7. `php artisan catalogo-fiscal:equivalencias --reporte` y guardar el resultado para la fase 2.
8. Entrar como preparador y como administrador y revisar las 5 pestañas.

## Listo cuando

- Un administrador ve y edita preguntas, documentos, líneas, parámetros y reglas de 2025 y 2026, cada uno con su fuente del IRS y su estado de validación, y cada cambio guarda el valor anterior.
- Un preparador ve las 5 pestañas sin poder editar; un cliente no entra.
- `catalogo-fiscal:verificar` pasa para 2025 y 2026, y la prueba de María pasa.
- Las calculadoras dan lo mismo que antes.
- El reporte de equivalencias cubre todas las claves del catálogo viejo.
- La suite de Pest pasa completa, Pint sin cambios y `npm run build` compila.

## Fuera de esta fase

- Evaluar las condiciones y mostrar la entrevista (fase 2); armar el checklist (fase 3); correr las reglas y leer por casilla (fase 4); llenar el PDF oficial y el "cómo se calculó" por cliente (fase 6, parte B de 7.7).
- Mover los datos de `campos_cliente` y borrar las pseudoformas (fase 2, ver 1.6).
- Validar contra el IRS cada parámetro y las 12 calculadoras (fase 6; puede empezar en paralelo).
- Fichas pendientes de 4.0 (1-A, A, B, D/8949, E, H, R, LEP, 4137, 4684, 8829, 8862, 8863, 8880, 8888, 8889): se cargan sin líneas (fase 7).
- Botón en pantalla para "copiar un año en otro": en esta fase es un comando.
- Preguntas de alerta de sales tax y otros impuestos de Florida: esperan P4.

## Decisiones de Steven (29 oct.)

1. **`campos_cliente` y E12:** de acuerdo. En F1 solo el mapa de equivalencias y el reporte; la migración real y el borrado de las pseudoformas van con la fase 2.
2. **Menú viejo:** queda como "Campos del formulario actual", solo para el administrador, hasta la fase 2.
3. **Montos de 2026:** los que no están en el documento madre quedan **pendientes** (sin cifra).
4. **Contenido de las secciones 5 a 10:** Steven las revisó y están bien. Se cargan como "sin validar" (contra el IRS) y se validan en la pantalla.
5. **Quién valida:** por ahora cualquier administrador. **Más adelante también los preparadores**, porque son los que acompañan el proceso del cliente: la acción `validar` de `CatalogoFiscalPolicy` se escribe para que sumar el rol de preparador sea un cambio de una línea (se deja una prueba que documenta el comportamiento actual).
