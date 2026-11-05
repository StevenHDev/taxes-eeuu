# Fase 5 — Subtareas para trabajar con Sonnet 5.5

5 nov. 2026 · Plan: [`fase-5-expediente-revision.md`](fase-5-expediente-revision.md) (**decisiones respondidas el 5 nov.; no se programa hasta que Steven apruebe el plan en conjunto**). Lee primero la sección "Ajustes por lo que se hizo el 4 y 5 de noviembre" del plan: cambia lo que usan 5.0a, 5.1b, 5.1c, 5.2d, 5.3a, 5.3b, 5.4a y 5.5a.

> **Por qué este documento:** todo F5 lo programa **Sonnet 5.5**, con varias subtareas **en paralelo** sobre el mismo repositorio. Las decisiones de diseño, fiscales y de §7216 ya están tomadas en el plan; cada subtarea es "seguir instrucciones", no "decidir". Si algo no está aquí ni en el plan, **para y pregúntale al agente principal**; no inventes arquitectura.

## Cómo se trabaja

1. Las subtareas van por **olas**. Dentro de una ola se lanzan todas a la vez (una sesión de Sonnet 5.5 por subtarea); una ola empieza cuando la anterior terminó y el agente principal la revisó.
2. Antes de programar, cada agente lee: `docs/mejora-plataforma/guia-desarrollo.md` (secciones 4 y 6), la sección del plan que cita su subtarea y los archivos de "Contexto mínimo".
3. **Solo se tocan los "Archivos permitidos".** Los de "No tocar" son de otra subtarea de la misma ola. Si hace falta otro archivo, se para y se pregunta.
4. Al terminar cada subtarea:
   - `vendor/bin/pint <tus archivos PHP>` (no `--dirty`: formatearía archivos de otros agentes a mitad de trabajo);
   - si tocaste rutas: `php artisan wayfinder:generate --with-form` (genera lo mismo para todos; escribe primero el controlador y después la ruta);
   - si tocaste frontend: `npm run types:check` y revisa que **tus** archivos no tengan errores (un error en un archivo de otra subtarea de la misma ola no es tuyo: anótalo);
   - tus pruebas: `PAO_DISABLE=1 php artisan test --compact <tus archivos de prueba>`;
   - **la suite completa no se corre por subtarea**: se corre una vez al cerrar la fase (`PAO_DISABLE=1 php artisan test --parallel`).
5. Entrega: un mensaje al agente principal con lo que hiciste, lo que cambió frente a la subtarea y por qué, y los resultados de las pruebas tal cual. El agente principal escribe el "Cómo quedó" en el plan y marca la subtarea aquí.
6. **Nunca:** commit ni push (los hace el agente principal con `/commit`, preguntándole a Steven), usar el navegador (la revisión visual es del agente principal), tocar `.env*`, escribir en producción, tocar los tres archivos sueltos de la raíz.

## Reglas comunes a todas las subtareas

- **§7216 y datos sensibles:** ningún SSN, EIN, número de cuenta, nombre de una persona del bloque C, fecha de nacimiento ni monto leído va en logs, en la bitácora, en `expediente_eventos`, en `excepciones.detalle`, en un correo, en el CSV o en las props de una pantalla del cliente. En las del preparador, lo sensible (casilla del catálogo con `sensible = true` o pregunta con `sensible = true`) va **nulo** con su `mascara` y se revela con los endpoints que ya existen (con contraseña y registro).
- **Un solo camino de escritura:** datos leídos solo con `App\Services\Lectura\DatosDocumento`; estados del checklist solo con `App\Services\Entrevista\ChecklistDocumentos`; respuestas solo con `App\Services\Entrevista\RespuestaGuardador`; **estado del expediente** solo con `App\Services\Expediente\EstadosExpediente` (automático y `registrarCambio`) y `App\Services\Expediente\RevisionExpediente` (transiciones manuales). Las pantallas no deciden reglas.
- **Lo fiscal sale del catálogo del año:** preguntas, documentos, casillas, líneas y su origen (`CatalogoFiscal`), reglas (`CatalogoRegla`), parámetros (`App\Support\ParametrosFiscales::valor($anio, $categoria, $clave)`). Nunca un número o un código de casilla "a mano" fuera de las constantes que pide la subtarea.
- **La confianza se explica:** toda confianza que se muestre lleva su motivo en palabras (`MotivoConfianza::titulo()` y `explicacion()`) y el acceso a la tabla (`CalculadoraConfianza::tabla()` y el umbral `lectura.umbral_confianza`). Los textos salen de ahí; no se repiten en el frontend.
- **Permisos:** `$this->authorize('view', $cliente)` para leer y `'update'` para escribir (`ClientePolicy`). Un ítem, persona, dato o expediente que no es del cliente da **404**.
- **Textos del servidor:** `__('texto en español')`. Su inglés **no** va en `lang/en.json` sino en el archivo de tu área: `lang/expediente/<area>/en.json` (lo crea la 5.1a y lo registra con `addJsonPath`). Sin `trans_choice()`.
- **Textos de la interfaz:** **no se edita `es.json` ni `en.json`** (salvo la 5.0a, que lo dice). Cada área tiene su par en `resources/js/i18n/locales/expediente/` con un espacio de nombres propio (lo crea la 5.2a): `ficha`, `revision`, `hoja`, `debidaDiligencia`, `panel`, `miDeclaracion`. Se pueden **leer** claves viejas (por ejemplo `clienteShow.*`) en el código que se mueve.
- **Rutas:** cada área en su archivo `routes/expediente-<area>.php` (los crea vacíos la 5.1b). Todas dentro de `Route::middleware(['auth', 'verified'])->group(...)`, con `->whereNumber()` en los ids.
- **Interfaz (guía 4.2):** skeleton para lo diferido (`<Deferred fallback>`, los diálogos fuera de `<Deferred>`); diseñado primero a 375 px, una columna, filas tocables de 44 px, sin scroll horizontal; una sola acción principal; solo `components/ui` e íconos de `lucide-react`; `aria-label` en botones de ícono, `fieldset`/`legend`, `Label` en cada campo, errores con `aria-describedby`/`aria-invalid`; estados con **texto e ícono**, nunca solo color; vacío, error y éxito; hover, foco, activo y deshabilitado (con el motivo al lado); spinner y sin doble envío al guardar; casilla y `Label` lado a lado; avisos con `Inertia::flash('toast', …)`.
- **React:** estado derivado en el render (no efectos para calcular); formularios que se reinician con `key`; ningún componente definido dentro de otro (`rerender-no-inline-components`); clics resueltos en el manejador; `Map`/`Set` para buscar (`js-index-maps`); importar cada componente de `components/ui` por su archivo (`bundle-barrel-imports`).
- **Pruebas:** clases Pest como las de `tests/Feature/Lectura/` (`RefreshDatabase`, `$this->seed(CatalogoFiscalSeeder::class)`, `Storage::fake(Documento::disco())`, `Queue::fake()` cuando suba archivos). Las nuevas van en `tests/Feature/Expediente/`. Nombres en español que digan el comportamiento. OpenAI nunca se llama (`LECTURA_AUTOMATICA=false` ya está en `phpunit.xml`).

## Estado y olas

| Ola | # | Subtarea | Depende de | Estado |
| --- | --- | --- | --- | --- |
| 1 | 5.0a | Bitácora "Revelado"/"Exportado" y visor abierto en un dato | — | ✅ |
| 1 | 5.1a | Modelo de datos del expediente | — | ✅ |
| 1 | 5.1c | Catálogo: Form 8867, vencimientos y R-02 | — | ✅ |
| 1 | 5.2a | Andamiaje de la ficha en el frontend | — | ✅ |
| 2 | 5.1b | Diagnóstico, estados, R-02 y recálculo | 5.1a, 5.1c | ✅ |
| 3 | 5.2b | Ficha: Entrevista, Documentos, Excepciones e Historial (servidor) | 5.1b | ✅ |
| 3 | 5.3a | Revisión: decisiones, checklist y transiciones (servidor) | 5.1b | ✅ |
| 3 | 5.4a | Debida diligencia: resumen y créditos (servidor) | 5.1b | ✅ |
| 3 | 5.5a | Hoja para TaxWise: servicio, página y CSV (servidor) | 5.1b, 5.0a | ✅ |
| 3 | 5.6a | Resumen, lista, panel, avisos y retiros (servidor) | 5.1b | ✅ |
| 3 | 5.7a | "¿Cómo va lo mío?" en el portal y el agente | 5.1b | ✅ |
| 4 | 5.2c | Pantallas Resumen e Historial | 5.2a, 5.2b, 5.6a | ✅ |
| 4 | 5.2d | Pantallas Entrevista, Documentos y Excepciones | 5.2a, 5.2b | ✅ |
| 4 | 5.3b | Pantalla Revisión | 5.2a, 5.3a | ✅ |
| 4 | 5.4b | Pantalla Debida diligencia | 5.2a, 5.4a | ✅ |
| 4 | 5.5b | Pantalla Hoja para TaxWise | 5.2a, 5.5a | ✅ |
| 4 | 5.6b | Pantallas Panel y Lista de clientes | 5.2a, 5.6a | ✅ |
| 5 | — | Cierre (agente principal): Wayfinder, pint, tipos, suite completa, `npm run build`, `MariaExpedienteTest`, revisión en el navegador, borrar claves i18n sin uso, documentación | todas | ✅ |

**Por qué este reparto no se pisa:** cada subtarea de una ola tiene sus propios controladores, servicios, rutas (`routes/expediente-<area>.php`), textos (`lang/expediente/<area>/en.json` y `locales/expediente/<area>.*.json`), tipos de TypeScript y pruebas. Los archivos compartidos (`AppServiceProvider`, `web.php`, `app.tsx`, `i18n/index.ts`, `ClienteController`, `routes/clientes.php`) tienen **un solo dueño** en toda la fase.

## Contratos compartidos

Estos nombres y formas los usan varias subtareas. Quien los crea debe respetarlos al pie de la letra; quien los usa puede contar con ellos.

**Enums** (5.1a, `app/Enums/Expediente/`):

| Enum | Casos (valor) | Métodos |
| --- | --- | --- |
| `EstadoExpediente` | `Invitado` (`invitado`), `EnEntrevista` (`en_entrevista`), `EnConsulta` (`en_consulta`), `EnRecoleccion` (`en_recoleccion`), `PorValidar` (`por_validar`), `ListoParaPreparar` (`listo_para_preparar`), `EnRevision` (`en_revision`), `PreparadoEnTaxwise` (`preparado_en_taxwise`), `EsperandoFirma` (`esperando_firma`), `Presentado` (`presentado`), `Aceptado` (`aceptado`), `Rechazado` (`rechazado`) | `label(): string`, `textoCliente(?Carbon $presentadoEn = null): string`, `derivado(): bool` (los 6 primeros), `reabrible(): bool` (`EnRevision`, `PreparadoEnTaxwise`, `EsperandoFirma`), `trasPresentar(): bool` (`Presentado`, `Aceptado`, `Rechazado`), `turno(): string` (`cliente`, `preparador`, `irs`, `nadie`, tabla del plan), `orden(): int` (1 a 12, en el orden de los casos) |
| `EstadoCivilIrs` | `Single` (`single`), `Mfj` (`mfj`), `Mfs` (`mfs`), `Hoh` (`hoh`), `Qss` (`qss`) | `label()`: "Soltero (Single)", "Casado, declaración conjunta (MFJ)", "Casado, declaraciones separadas (MFS)", "Cabeza de familia (HOH)", "Cónyuge sobreviviente calificado (QSS)" |
| `ClasificacionPersona` | `HijoCalificado` (`hijo_calificado`), `ParienteCalificado` (`pariente_calificado`), `NoDependiente` (`no_dependiente`) | `label()`: "Hijo calificado", "Pariente calificado", "No es dependiente"; `esDependiente(): bool` |
| `PuntoRevision` | `Identidad`, `EstadoCivil`, `Dependientes`, `Documentos`, `Excepciones`, `DebidaDiligencia`, `AnioAnterior` (valores en snake_case: `identidad`, `estado_civil`, `dependientes`, `documentos`, `excepciones`, `debida_diligencia`, `anio_anterior`) | `titulo()`, `ayuda()` (textos de la tabla 7.2 del plan), `manual(): bool` (`Identidad`, `DebidaDiligencia`, `AnioAnterior`), `orden(): int` |
| `Credito8867` | `Eic` (`eic`), `Ctc` (`ctc`), `Aotc` (`aotc`), `Hoh` (`hoh`) | `label()`: "Crédito por ingreso del trabajo (EIC)", "Crédito por hijos y otros dependientes (CTC/ACTC/ODC)", "Crédito de oportunidad americana (AOTC)", "Cabeza de familia (HOH)"; `parte(): string` (`parte-ii`, `parte-iii`, `parte-iv`, `parte-v`) |
| `TipoEventoExpediente` | `Estado` (`estado`), `Reabierto` (`reabierto`), `Decision` (`decision`), `Punto` (`punto`), `CambioTrasPresentar` (`cambio_tras_presentar`) | `label()` |
| `PerfilExpediente` | `Asalariado` (`asalariado`), `Independiente` (`independiente`), `Jubilacion` (`jubilacion`), `Inversiones` (`inversiones`), `Alquiler` (`alquiler`), `Marketplace` (`marketplace`), `Dependientes` (`dependientes`), `K1` (`k1`) | `label()`: "Asalariado", "Independiente", "Jubilación o Seguro Social", "Inversiones", "Alquiler", "Seguro del Mercado", "Con dependientes", "Socio (K-1)" |

Todos los `label()` y textos usan `__()`; el inglés va en `lang/expediente/estados/en.json`.

**Servicios** (5.1b, `app/Services/Expediente/`):

```php
// Solo lee. Estado derivado y qué falta.
DiagnosticoExpediente::para(User $cliente, int $taxYear): Diagnostico
// DTO final readonly: EstadoExpediente $estado; bool $consentimiento; bool $entrevistaConfirmada;
// bool $r02Activa; bool $r02Cumple; array $faltan; array $noAplica; int $excepcionesQueFrenan;
// int $informativas; int $consultas; int $itemsCliente; int $itemsPreparador;
// métodos: listo(): bool; motivos(): list<string>; toArray(): array (forma abajo)

EstadosExpediente::recalcular(User $cliente, int $taxYear): Expediente
EstadosExpediente::pedirRecalculo(int $clienteId, int $taxYear): void   // después del commit, sin repetir
EstadosExpediente::registrarCambio(Expediente $e, EstadoExpediente $nuevo, ?User $actor, TipoEventoExpediente $tipo = TipoEventoExpediente::Estado, ?string $motivo = null, array $datos = []): void

TextoRespuesta::de(array $pregunta, mixed $valor, bool $ingles): ?string
FichaCliente::encabezado(User $cliente, int $taxYear, User $actor): array
```

`Diagnostico::toArray()`:

```php
[
  'estado' => 'por_validar', 'estado_label' => 'Por validar',
  'consentimiento' => true, 'entrevista_confirmada' => true,
  'r02' => [
    'activa' => true, 'cumple' => false,
    'faltan' => [['id' => 12, 'codigo' => 'w2', 'nombre' => 'Comprobante de salario…', 'numero' => 2, 'persona_orden' => null, 'estado' => 'leido']],
    'no_aplica' => [['id' => 15, 'nombre' => '…', 'motivo' => '…']],
  ],
  'excepciones_que_frenan' => 1, 'informativas' => 0, 'consultas' => 0,
  'items_cliente' => 0, 'items_preparador' => 2,
  'motivos' => ['2 documentos sin validar', '1 excepción bloqueante abierta'],
]
```

`FichaCliente::encabezado()` (prop `ficha` de **todas** las pestañas):

```php
[
  'cliente' => ['id' => 7, 'name' => '…', 'email' => '…', 'phone' => '…', 'role' => 'client', 'es_prueba' => true, 'archivado' => false],
  'tax_year' => 2025,
  'anios' => [2026, 2025, 2024],
  'expediente' => ['existe' => true, 'estado' => 'por_validar', 'label' => 'Por validar', 'desde' => '2026-11-03T…', 'turno' => 'preparador', 'vence_en' => '2026-04-15', 'cambios_tras_presentar' => false],
  'conteos' => ['excepciones' => 3, 'bloqueantes' => 1, 'documentos_por_validar' => 2, 'documentos_faltan' => 0],
  // Solo las pestañas cuya ruta existe (Route::has), en este orden, con ?tax_year=.
  'pestanas' => [['clave' => 'resumen', 'url' => '…'], /* entrevista, documentos, excepciones, revision, hoja, debida_diligencia, historial */],
  'puede_administrar' => false,
]
```

| Clave de pestaña | Ruta | Página Inertia |
| --- | --- | --- |
| `resumen` | `clientes.show` | `ficha/resumen` |
| `entrevista` | `clientes.ficha.entrevista` | `ficha/entrevista` |
| `documentos` | `clientes.ficha.documentos` | `ficha/documentos` |
| `excepciones` | `clientes.ficha.excepciones` | `ficha/excepciones` |
| `revision` | `clientes.revision.show` | `ficha/revision` |
| `hoja` | `clientes.hoja-taxwise.show` | `ficha/hoja-taxwise` |
| `debida_diligencia` | `clientes.debida-diligencia.show` | `ficha/debida-diligencia` |
| `historial` | `clientes.ficha.historial` | `ficha/historial` |

**Evento de Laravel** (5.1b): `App\Events\ExpedienteCambioDeEstado` (implementa `ShouldDispatchAfterCommit`) con `public Expediente $expediente, public ?EstadoExpediente $anterior, public EstadoExpediente $nuevo, public ?User $actor`. Lo dispara `registrarCambio`.

**Tipos de TypeScript** (5.2a, `resources/js/types/expediente.ts`): `EstadoExpediente`, `Turno`, `ClavePestana`, `PestanaFicha`, `FichaEncabezado`, `Diagnostico`, `EventoExpediente`, con la forma exacta de los arreglos de arriba.

**Ayuda para pruebas** (5.1b): `tests/Feature/Expediente/Concerns/ArmaExpediente.php` (trait) con:

```php
protected function clienteConExpediente(array $respuestas = ['D1' => 'si'], bool $consentimiento = true, bool $confirmada = true, ?User $preparador = null): array // [User $cliente, Entrevista $entrevista, User $preparador]
protected function item(Entrevista $e, string $codigo, EstadoDocumento $estado, ?int $personaId = null, int $numero = 1): EntrevistaDocumento
protected function datoLeido(EntrevistaDocumento $item, string $casilla, ?string $valor, float $confianza = 0.99, MotivoConfianza $motivo = MotivoConfianza::EnTextoDelPdf): DocumentoDato // crea Documento + DocumentoLectura leida + dato
protected function excepcion(Entrevista $e, string $regla, SeveridadRegla $sev, ?EntrevistaDocumento $item = null, EstadoExcepcion $estado = EstadoExcepcion::Abierta): Excepcion
```

---

## Subtarea 5.0a: Bitácora "Revelado"/"Exportado" y visor abierto en un dato

> **Ajuste del 5 nov.:** el visor ya muestra las excepciones de todo el documento (recuadro arriba, `excepciones` en las props) y la lectura ya funciona en producción. Aquí quedan solo la acción "Revelado" y `?dato=`; no toques el recuadro de excepciones.

**Ola:** 1 · **Depende de:** nada · **Plan:** 5.0

**Objetivo:**
Cerrar dos pendientes de F4 que F5 necesita: que revelar un dato quede en la bitácora con su propia acción (y que exista "Exportado" para el CSV de la hoja), y que el visor se pueda abrir en un dato con `?dato=`.

**Archivos permitidos:**
- `app/Enums/AccionAuditoria.php`
- `app/Http/Controllers/VisorDocumentoController.php` (solo `show` y `reveal`)
- `resources/js/pages/clientes/visor.tsx`, `resources/js/types/lectura.ts` (si las props del visor están tipadas ahí)
- `resources/js/pages/bitacora/index.tsx`, `resources/js/types/tax-event.ts` (tipo `AccionAuditoria`)
- `resources/js/i18n/locales/es.json` y `en.json`: **solo** `bitacora.acciones.revelado` y `bitacora.acciones.exportado`
- `tests/Feature/Lectura/VisorTest.php`

**No tocar:** `AppServiceProvider`, el observer de la bitácora, cualquier archivo de `app/Services/Expediente/`.

**Contexto mínimo:**
- Hoy `reveal` crea un `BitacoraActividad` con `AccionAuditoria::Actualizado` y `campos_afectados = ['revelado']` (comentario "Pendiente" en el plan de F4).
- La página del visor guarda la selección en `seleccionadoId` e `indiceHoja` (`useState`).

**Contrato:**
- `AccionAuditoria::Revelado = 'revelado'` (`label()` "Dato revelado") y `AccionAuditoria::Exportado = 'exportado'` (`label()` "Exportado"). Textos de la bitácora: es "Dato revelado"/"Exportado", en "Data revealed"/"Exported".
- `reveal` usa `AccionAuditoria::Revelado` y `campos_afectados = null`; la etiqueta queda igual (sin el valor).
- `show(Request $request, User $cliente, EntrevistaDocumento $item, …)`: si `$request->integer('dato')` es un id entre los datos vigentes del ítem, la prop nueva `dato_inicial` es ese id; si no, `null`.
- `visor.tsx`: `useState(() => dato_inicial)` para la selección y `useState(() => índice de la hoja de ese dato, o 0)` para la hoja (regla `rerender-lazy-state-init`); la fila del dato inicial recibe el foco con un *ref callback* (sin `useEffect`).

**Criterio de terminado:**
- Revelar deja una fila `revelado` sin el valor; la bitácora la muestra con su texto en los dos idiomas.
- `?dato=` de un dato vigente abre el visor en ese dato y en su hoja; un id de otro ítem o inventado da `dato_inicial = null` (sin error).

**Pruebas (en `VisorTest`):** `test_revelar_deja_la_accion_revelado_sin_el_valor`, `test_el_visor_abre_en_el_dato_pedido`, `test_un_dato_de_otro_item_no_se_abre_en_el_visor`.

**Fuera de alcance:** la hoja (5.5), el CSV.

---

## Subtarea 5.1a: Modelo de datos del expediente

**Ola:** 1 · **Depende de:** nada · **Plan:** "Modelo de datos"

**Objetivo:**
Crear las tablas, enums y modelos del expediente, registrar su bitácora y los archivos de traducción del servidor de F5.

**Archivos permitidos (crear):**
- `database/migrations/2026_11_05_100000_crea_expedientes.php` (exactamente el esquema del plan, con `down()`)
- `app/Enums/Expediente/EstadoExpediente.php`, `EstadoCivilIrs.php`, `ClasificacionPersona.php`, `PuntoRevision.php`, `Credito8867.php`, `TipoEventoExpediente.php`, `PerfilExpediente.php`
- `app/Models/Expediente/Expediente.php`, `ExpedienteEvento.php`, `ExpedientePersona.php`, `ExpedienteRevisionPunto.php`
- `lang/expediente/estados/en.json` (con el inglés de todos los enums) y, vacíos (`{}`), `lang/expediente/ficha/en.json`, `revision/en.json`, `debida-diligencia/en.json`, `hoja/en.json`, `panel/en.json`, `mi-declaracion/en.json`
- `tests/Feature/Expediente/ModeloExpedienteTest.php`

**Archivos permitidos (modificar):**
- `app/Models/Entrevista/Entrevista.php` (`expediente(): HasOne`)
- `app/Models/User.php` (`expedientes(): HasMany`)
- `app/Observers/AuditoriaObserver.php` (etiqueta: `Expediente` → `"expediente {$tax_year} (cliente #{$user_id})"`; `ExpedientePersona` y `ExpedienteRevisionPunto` → `"… (expediente #{$expediente_id})"`)
- `app/Providers/AppServiceProvider.php`: `AuditoriaObserver` en `Expediente`, `ExpedientePersona` y `ExpedienteRevisionPunto` (no en `ExpedienteEvento`), y `$this->app['translator']->addJsonPath(lang_path("expediente/{$area}"))` para cada una de las 7 carpetas.

**No tocar:** servicios, controladores, rutas, frontend.

**Contexto mínimo:**
- Imitar `app/Models/Lectura/Excepcion.php` (atributo `#[Fillable]`, `casts()`, PHPDoc de propiedades, relaciones tipadas).
- Textos de los enums: la sección "Contratos compartidos" de este documento y las tablas del plan ("Estados del expediente", 5.3 checklist, 5.7 textos para el cliente).

**Contrato:**
- `Expediente`: `#[Fillable]` con todas las columnas menos `id` y timestamps. Casts: `estado` → `EstadoExpediente`, `estado_desde` → `datetime`, `estado_civil_irs` → `EstadoCivilIrs`, `estado_civil_en` y `creditos_8867_en` y `cambios_tras_presentar_en` → `datetime`, `creditos_8867` y `perfil` → `array`, `presentado_en` y `vence_en` → `date`. Relaciones: `user()`, `entrevista()`, `eventos()` (`HasMany`, `orderBy('id')`), `personas()` (`ExpedientePersona`), `puntos()` (`ExpedienteRevisionPunto`), `decisorEstadoCivil()` (`estado_civil_por`). Scope `delAnio(int)`.
- `ExpedienteEvento`: `const UPDATED_AT = null`; casts `tipo` → `TipoEventoExpediente`, `estado_anterior`/`estado_nuevo` → `EstadoExpediente` (nullable), `datos` → `array`, `created_at` → `datetime`. Relaciones `expediente()`, `actor()`. PHPDoc: "solo se inserta".
- `ExpedientePersona`: casts `clasificacion` → `ClasificacionPersona`, `decidido_en` → `datetime`; `persona()` con `withTrashed()`, `decisor()`.
- `ExpedienteRevisionPunto`: casts `punto` → `PuntoRevision`, `confirmado_en` → `datetime`; `confirmador()`.
- `EstadoExpediente::turno()`: `invitado`, `en_entrevista`, `en_consulta`, `en_recoleccion`, `esperando_firma` → `cliente`; `por_validar`, `listo_para_preparar`, `en_revision`, `preparado_en_taxwise`, `rechazado` → `preparador`; `presentado` → `irs`; `aceptado` → `nadie`.
- `EstadoExpediente::textoCliente()`: la tabla de 5.7 del plan; `presentado` con `:fecha` (`$presentadoEn?->isoFormat('LL')` en el idioma actual) o sin fecha si es nula.

**Criterio de terminado:**
- `php artisan migrate` y `migrate:rollback` funcionan en SQLite y PostgreSQL (local).
- Los casts devuelven enums y fechas; un segundo expediente del mismo cliente y año viola el único.
- Crear un `ExpedienteEvento` no escribe `updated_at`.
- La bitácora registra el alta y el cambio de un `Expediente` con nombres de atributo, nunca con el motivo.
- Con `App::setLocale('en')`, `EstadoExpediente::PorValidar->label()` sale en inglés.

**Pruebas (`ModeloExpedienteTest`):** `test_los_casts_devuelven_enums_y_fechas`, `test_un_cliente_tiene_un_expediente_por_anio`, `test_un_evento_no_tiene_updated_at`, `test_la_bitacora_registra_el_expediente_sin_valores`, `test_las_etiquetas_salen_en_ingles`, `test_cada_estado_tiene_turno_texto_para_el_cliente_y_orden`.

**Fuera de alcance:** calcular estados (5.1b).

---

## Subtarea 5.1c: Catálogo — Form 8867, vencimientos y R-02

**Ola:** 1 · **Depende de:** nada · **Plan:** "Reglas de esta fase" y 5.4

**Objetivo:**
Agregar al catálogo de 2025 (y su ajuste de 2026) lo que F5 lee: el Form 8867 con las preguntas de cada parte, las fechas de vencimiento y R-02 implementada.

**Archivos permitidos:**
- `database/data/catalogo-fiscal/2025/formularios.php`, `parametros.php`, `reglas.php`
- `database/data/catalogo-fiscal/2026/ajustes.php`
- `tests/Feature/CatalogoFiscal/CatalogoRealTest.php` (y `PantallaTest.php` solo si sus conteos cambian)

**No tocar:** el importador, el verificador, `parametros-metadatos.php`, cualquier otro archivo.

**Contexto mínimo:**
- Formato de los archivos: `database/data/catalogo-fiscal/README.md`. El verificador exige: origen `respuesta` con códigos de pregunta que existan, fuente en cada pieza, y un solo formulario `siempre` (el 1040).

**Contrato:**
- `formularios.php`, al final de las fichas completas:
  ```php
  [
      'codigo' => 'f8867', 'nombre' => 'Form 8867', 'para_que' => 'Debida diligencia del preparador pagado (EIC, crédito por hijos, AOTC y cabeza de familia)',
      'siempre' => false, 'ficha' => 'completa', 'orden' => 30,
      'fuente_texto' => 'Form 8867 e instrucciones (rev. noviembre de 2025)', 'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/i8867.pdf',
      'lineas' => [ /* las 5 de la tabla de 5.4 del plan, origen_tipo 'respuesta' */ ],
  ],
  ```
  Las descripciones de las líneas: "Parte I — Requisitos de debida diligencia (entrevista y conocimiento)", "Parte II — Crédito por ingreso del trabajo (EIC)", "Parte III — Crédito por hijos, ACTC y otros dependientes", "Parte IV — Crédito de oportunidad americana (AOTC)", "Parte V — Cabeza de familia". Si puedes abrir `https://www.irs.gov/pub/irs-pdf/f8867.pdf`, confirma que las partes se llaman así y anota lo que difiera; si no, deja estos textos y dilo.
- `parametros.php`: dos parámetros nuevos, `categoria => 'vencimientos'`, claves `presentacion` (`'2026-04-15'`) y `extension` (`'2026-10-15'`), `grupo => 'topes_umbrales'`, `tipo => 'fecha'`, con `nombre_es` ("Fecha límite para presentar la declaración del año" / "Fecha límite con la extensión (Form 4868)"), `formulario_linea` ("Form 1040" / "Form 4868"), `condicion_es` (qué hace: ordenar la cola del preparador por vencimiento; la de extensión aplica si el cliente respondió "sí" a G2) y fuente (IRC §6072(a) e instrucciones del 1040 de 2025; Form 4868).
- `2026/ajustes.php`: `'vencimientos.presentacion' => '2027-04-15'` y `'vencimientos.extension' => '2027-10-15'`.
- `reglas.php`, R-02: `'implementada' => true` y descripción: "Todo documento activado está validado o en no aplica con motivo. Se revisa en el expediente (frena "Listo para preparar") y no abre filas en la cola de excepciones: lo que falta se ve en el Resumen y en la Revisión del cliente."

**Criterio de terminado:**
- `php artisan catalogo-fiscal:cargar 2025 --dry-run`, `cargar 2025`, `copiar` no hace falta (2026 ya existe), `cargar 2026` y `catalogo-fiscal:verificar 2025` y `2026` sin problemas (en la base local).
- `CatalogoRealTest` cuenta el formulario nuevo y sus 5 líneas, los parámetros nuevos (con el valor de 2026) y R-02 implementada.

**Pruebas:** en `CatalogoRealTest`: `test_el_form_8867_tiene_sus_cinco_partes_con_preguntas_que_existen`, `test_los_vencimientos_del_anio_estan_en_los_parametros`, `test_r02_esta_implementada`.

**Fuera de alcance:** usar estos datos (5.1b, 5.4a).

---

## Subtarea 5.2a: Andamiaje de la ficha en el frontend

**Ola:** 1 · **Depende de:** nada (trabaja contra el contrato) · **Plan:** 5.2

**Objetivo:**
Dejar listo lo que comparten todas las pestañas de la ficha: el layout con encabezado y pestañas, los componentes comunes, los tipos, los archivos de textos por área, la impresión y las páginas provisionales.

**Archivos permitidos (crear):**
- `resources/js/layouts/ficha/layout.tsx`
- `resources/js/components/ficha/ficha-encabezado.tsx`, `ficha-pestanas.tsx`, `estado-expediente.tsx`, `use-revelar.ts`, `boton-imprimir.tsx`, `ficha-skeleton.tsx`
- `resources/js/types/expediente.ts`
- `resources/js/i18n/locales/expediente/ficha.es.json`, `ficha.en.json`, `revision.es.json`, `revision.en.json`, `hoja.es.json`, `hoja.en.json`, `debidaDiligencia.es.json`, `debidaDiligencia.en.json`, `panel.es.json`, `panel.en.json`, `miDeclaracion.es.json`, `miDeclaracion.en.json` (cada uno `{"<espacio>": {…}}`; los que no usa esta subtarea quedan con el objeto vacío)
- **Páginas provisionales** (las reemplaza la ola 4; existen para que las pruebas del servidor de la ola 3 pasen con `inertia.testing.ensure_pages_exist`): `resources/js/pages/ficha/resumen.tsx`, `entrevista.tsx`, `documentos.tsx`, `excepciones.tsx`, `revision.tsx`, `hoja-taxwise.tsx`, `debida-diligencia.tsx`, `historial.tsx`. Cada una exporta un componente que pinta `<FichaSkeleton />`.

**Archivos permitidos (modificar):**
- `resources/js/i18n/index.ts` (mezclar los 12 archivos nuevos con `en`/`es`: `translation: { ...en, ...fichaEn, … }`)
- `resources/js/app.tsx` (`name.startsWith('ficha/')` → `[AppLayout, FichaLayout]`)
- `resources/js/components/app-sidebar.tsx` y `resources/js/components/app-sidebar-header.tsx` (agregar `print:hidden` a su contenedor raíz; nada más)
- `resources/css/app.css` (solo un bloque `@media print`: fondo blanco, sin sombras, `@page { margin: 12mm }`)

**No tocar:** `es.json`, `en.json`, `pages/clientes/*`, cualquier archivo del servidor.

**Contexto mínimo:**
- Patrón a copiar: `resources/js/layouts/catalogo-fiscal/layout.tsx` (pestañas como enlaces con `aria-current`, `prefetch`) y su registro en `app.tsx`.
- Encabezado actual de la ficha: el JSX de `pages/clientes/show.tsx` desde `<h1` hasta `ClienteCuentaAcciones` (nombre, insignia de prueba, correo y teléfono en `font-mono`, `WhatsappConversationDialog`, `ClienteCuentaAcciones`).
- Revelar con contraseña: `FilaRespuesta.revelar` en `components/cliente/entrevista-cliente.tsx` (fetch POST con `X-CSRF-TOKEN`, 423 → enlace a `confirmPasswordShow()`).

**Contrato:**
- `types/expediente.ts`: los tipos de "Contratos compartidos" (forma exacta de `ficha`, `Diagnostico`, `EventoExpediente` = `{id, tipo, tipo_label, estado_anterior, estado_nuevo, estado_nuevo_label, actor: string|null, motivo: string|null, creado_en: string}`).
- `FichaLayout({ children })`: lee `usePage<{ ficha: FichaEncabezado }>().props.ficha` y `page.component`; la pestaña activa sale de `page.component` (`ficha/hoja-taxwise` → `hoja`, `ficha/debida-diligencia` → `debida_diligencia`, el resto igual al nombre). Pinta `FichaEncabezado`, `FichaPestanas` y `<section>{children}</section>`, todo con `print:` para que al imprimir solo salga el contenido y una línea con cliente y año.
- `FichaEncabezado({ ficha })`: h1 con el nombre y la insignia de prueba; correo y teléfono; `EstadoExpedienteBadge` + "desde {fecha}" + "Le toca: {turno}" + "Vence {fecha}" (si hay); conteos ("3 excepciones (1 bloqueante) · 2 documentos por validar", sin mostrar los que están en cero); selector de año (`Select` con `Label`, navega a la URL de la pestaña activa cambiando `tax_year` con `URL`/`searchParams`); `WhatsappConversationDialog` si hay teléfono; `ClienteCuentaAcciones` si `puede_administrar`; un `Alert` si `cambios_tras_presentar` ("Algo cambió después de presentar: revisa si hace falta una enmienda").
- `FichaPestanas({ pestanas, activa })`: en `sm` y más, `nav` con enlaces como el catálogo; en el celular, un `Select` con `Label` "Sección" que navega (`router.visit`). Las etiquetas: `ficha.pestanas.<clave>`.
- `EstadoExpedienteBadge({ estado, label })`: `Badge` con ícono de `lucide-react` por estado (por ejemplo `CircleDashed`, `MessagesSquare`, `CircleHelp`, `FileUp`, `ClipboardCheck`, `CircleCheck`, `FileSearch`, `FileCheck2`, `PenLine`, `Send`, `BadgeCheck`, `CircleX`) y el texto; variante por turno.
- `useRevelar(url: string)`: `{ valor: string | null | undefined, revelando: boolean, pideContrasena: boolean, revelar: () => Promise<void> }`. `undefined` = no revelado. Respeta la respuesta `{ valor }` de los dos endpoints existentes.
- `BotonImprimir({ etiqueta })`: `Button variant="outline"` con ícono `Printer` que llama `window.print()`, `print:hidden`.
- `FichaSkeleton`: bloques `Skeleton` del alto de una tarjeta y dos tablas.
- Textos de `ficha.*.json` (es/en): `pestanas.{resumen, entrevista, documentos, excepciones, revision, hoja, debida_diligencia, historial}`, `seccion`, `anio`, `desde`, `leToca.{cliente, preparador, irs, nadie}`, `vence`, `conteos.{excepciones, bloqueantes, porValidar, faltan}`, `cambiosTrasPresentar`, `imprimir`, `revelar`, `revelarAria`, `confirmarContrasena`, `revelarError`, `cargando`, `error`.

**Criterio de terminado:**
- `npm run types:check` sin errores en estos archivos; `npm run build` compila.
- El idioma sigue funcionando: las claves viejas de `es.json` siguen visibles (nada se pisó al mezclar).

**Pruebas:** no hay prueba de Pest; la revisión visual la hace el agente principal en la ola 4.

**Fuera de alcance:** el contenido de cada pestaña.

---

## Subtarea 5.1b: Diagnóstico, estados, R-02 y recálculo

**Ola:** 2 · **Depende de:** 5.1a, 5.1c · **Plan:** "Estados del expediente y transiciones", "Reglas de esta fase", 5.1

**Objetivo:**
El corazón de F5: calcular el estado del expediente, guardarlo con su fecha, recalcularlo solo cuando algo cambia, reabrir la revisión, y dejar listos el encabezado común, las rutas vacías y la ayuda para pruebas.

**Archivos permitidos (crear):**
- `app/Services/Expediente/Diagnostico.php` (DTO `final readonly`), `DiagnosticoExpediente.php`, `EstadosExpediente.php`, `TextoRespuesta.php`, `FichaCliente.php`
- `app/Events/ExpedienteCambioDeEstado.php`
- `app/Observers/ExpedienteObserver.php`
- `app/Console/Commands/RecalcularExpedientes.php`
- `routes/expediente-ficha.php`, `routes/expediente-revision.php`, `routes/expediente-hoja.php`, `routes/expediente-debida-diligencia.php` (**vacíos**: solo `<?php`, los `use` y un `Route::middleware(['auth', 'verified'])->group(function () { });`)
- `tests/Feature/Expediente/Concerns/ArmaExpediente.php`, `tests/Feature/Expediente/EstadosExpedienteTest.php`, `RecalculoTest.php`, `FichaClienteTest.php`
- `lang/expediente/estados/en.json` (agregar los textos nuevos de esta subtarea)

**Archivos permitidos (modificar):**
- `app/Providers/AppServiceProvider.php` (registrar `ExpedienteObserver` en `EntrevistaDocumento`, `Excepcion`, `Entrevista` y `Consentimiento`)
- `app/Services/Lectura/Reglas/MotorReglas.php` (una línea al final de `correr()`: `app(EstadosExpediente::class)->pedirRecalculo($entrevista->user_id, $entrevista->tax_year);`, pedido con `app()` como hace `DatosDocumento`)
- `routes/web.php` (4 `require` nuevos, después de `clientes.php`)

**No tocar:** `ChecklistDocumentos`, `ColaExcepciones`, controladores, frontend.

**Contexto mínimo:**
- Insumos: `Entrevista` (`estado`, `confirmada_en`), `Consentimientos::firmado(User, int)`, `ChecklistDocumentos::completo()` y los ítems vigentes (`EntrevistaDocumento::vigentes()`), `Excepcion::abiertas()` y `severidad`, `CatalogoRegla::delAnio()->activos()` (R-02 con `implementada`), `MotorEntrevista::situacion()` (`documentos`, `formularios`, `respuestas`), `ParametrosFiscales::valor($anio, 'vencimientos', 'presentacion'|'extension')`, `ColaExcepciones::conteo()`.
- Orden del estado derivado y reglas de recálculo y reapertura: plan, "Estados del expediente y transiciones". **Cópialas tal cual.**

**Contrato:**
- `DiagnosticoExpediente::para(User $cliente, int $taxYear): Diagnostico`. Sin entrevista → `invitado` con todo en cero. `faltan`: los ítems vigentes que no están cerrados, con el nombre del catálogo en el idioma actual (un "otro" usa su `descripcion`), `numero` solo si hay más de uno del mismo documento y persona. `noAplica`: los ítems en `no_aplica` con su motivo. `excepcionesQueFrenan`: abiertas o consultadas con severidad `bloqueante` o `revision`. `motivos()`: frases con `__()` como "2 documentos sin validar", "1 excepción bloqueante abierta", "Falta confirmar la entrevista" (dos mensajes para singular y plural, sin `trans_choice`).
- `EstadosExpediente::recalcular(User, int): Expediente`, en una transacción con el expediente bloqueado (`lockForUpdate()` sobre la fila, **sin** agregados en la misma consulta):
  1. `firstOrCreate` por `user_id` + `tax_year` (estado inicial `invitado`, `estado_desde = now()`), `entrevista_id` al día.
  2. Diagnóstico; `perfil` (lista de `PerfilExpediente`: `asalariado` si se activó `w2`; `independiente` si está el formulario `sch-c`; `jubilacion` con `1099-r` o `ssa-1099`; `inversiones` con `1099-int`, `1099-div`, `1099-b` o `1099-da`; `alquiler` con `registros-alquiler`; `marketplace` con `1095-a`; `k1` con `k-1`; `dependientes` si la entrevista tiene personas); `vence_en` (extensión si G2 = `si`; si no, presentación; nulo si el parámetro no existe).
  3. Reglas del plan: derivado → estado del diagnóstico; reabrible y no listo → estado derivado, `confirmado_por`/`confirmado_en` en nulo en todos los puntos, evento `reabierto` con `motivo = implode('; ', motivos())`; tras presentar y no listo → `cambios_tras_presentar_en` (si estaba nulo) y evento `cambio_tras_presentar` una sola vez.
  4. Solo escribe si algo cambió (correr dos veces no deja eventos nuevos).
- `pedirRecalculo(int $clienteId, int $taxYear)`: guarda la clave `"{$clienteId}#{$taxYear}"` en un arreglo de la instancia (el servicio se registra como `scoped` en el contenedor, en esta subtarea, dentro de `AppServiceProvider::register()`), y con `DB::afterCommit()` corre `recalcular` una vez por clave y la quita. Fuera de una transacción corre en el momento.
- `registrarCambio(...)`: actualiza `estado` y `estado_desde`, crea el `ExpedienteEvento` (tipo, anterior, nuevo, actor, motivo, datos) y dispara `ExpedienteCambioDeEstado` cuando el estado cambia.
- `ExpedienteObserver`: `saved` de `EntrevistaDocumento` (por su entrevista), `Excepcion` (por su entrevista) y `Entrevista`; `created` de `Consentimiento` (por `user_id` y `tax_year`). Todos llaman `pedirRecalculo`.
- `TextoRespuesta::de(array $pregunta, mixed $valor, bool $ingles): ?string`: `null` si no hay valor; `si_no` → "Sí"/"No"; `usted_conyuge_no` → "Usted", "Cónyuge", "Usted y cónyuge" o "No"; `opciones` y `casillas` → los `texto_es`/`texto_en` de las opciones, separados por ", "; `fecha` → `AAAA-MM-DD`; el resto, el valor como texto. **Nunca se llama con una pregunta sensible** (quien la use lo filtra antes).
- `FichaCliente::encabezado(User $cliente, int $taxYear, User $actor): array` con la forma de "Contratos compartidos". Solo lee: si no hay expediente, `existe = false` y el estado sale del diagnóstico. `archivado` = `$cliente->trashed()`. `puede_administrar` = el actor es administrador.
- Comando `expedientes:recalcular {--anio=} {--solo-prueba} {--dry-run}`: recorre las entrevistas (del año si se da, solo de usuarios `es_prueba` con `--solo-prueba`) y recalcula; con `--dry-run` solo calcula el diagnóstico. Imprime una tabla de **cantidades por estado**, nunca nombres ni datos.

**Criterio de terminado y pruebas:**
- `EstadosExpedienteTest`: `test_sin_entrevista_es_invitado`, `test_sin_consentimiento_es_invitado`, `test_entrevista_sin_confirmar_esta_en_entrevista`, `test_un_documento_con_observacion_esta_en_consulta`, `test_un_no_aplica_rechazado_esta_en_consulta`, `test_la_consulta_manda_sobre_los_documentos_pendientes`, `test_un_documento_pendiente_esta_en_recoleccion`, `test_un_documento_leido_esta_por_validar`, `test_una_excepcion_de_revision_frena_y_una_informativa_no`, `test_todo_validado_o_no_aplica_esta_listo_para_preparar`, `test_r02_apagada_en_el_catalogo_no_frena`, `test_desde_en_revision_se_reabre_y_borra_las_confirmaciones`, `test_desde_preparado_en_taxwise_se_reabre_con_motivo`, `test_presentado_no_cambia_y_marca_la_alerta_una_vez`, `test_correr_dos_veces_no_duplica_eventos`, `test_el_perfil_y_el_vencimiento`, `test_registrar_cambio_dispara_el_evento` (`Event::fake`), `test_los_eventos_no_guardan_valores_sensibles`.
- `RecalculoTest`: el estado cambia solo al validar un documento (`ChecklistDocumentos::validar`), al observarlo, al marcar "no aplica", al subir un archivo, al aceptar y al consultar una excepción (`ColaExcepciones`), al cerrar una excepción con `MotorReglas::correr`, al confirmar la entrevista y al firmar el consentimiento; dentro de una transacción con varios cambios recalcula una vez; el comando con `--dry-run` no escribe y no imprime el nombre del cliente.
- `FichaClienteTest`: forma del encabezado, `pestanas` solo con las rutas que existen (con `Route::has` falso para las que todavía no se llenaron, no aparecen), conteos y `existe = false` sin expediente.

**Fuera de alcance:** transiciones manuales (5.3a), pantallas, avisos (5.6a).

---

## Subtarea 5.2b: Ficha — Entrevista, Documentos, Excepciones e Historial (servidor)

> **Ajuste del 5 nov.:** en la pestaña Excepciones, cada fila usa `App\Services\Lectura\ExplicadorExcepcion` (`caso` y `que_hacer`), igual que la cola global; no escribas textos de reglas.

**Ola:** 3 · **Depende de:** 5.1b · **Plan:** 5.2

**Objetivo:**
Las rutas y props de cuatro pestañas de la ficha, y mover los datos del formulario anterior a un servicio para la pestaña Historial.

**Archivos permitidos:**
- Crear: `app/Http/Controllers/FichaClienteController.php`, `app/Services/Expediente/FichaAnterior.php`, `tests/Feature/Expediente/FichaPestanasTest.php`, `tests/Feature/Expediente/FichaHistorialTest.php`
- Modificar: `routes/expediente-ficha.php`, `app/Services/Lectura/ColaExcepciones.php` (filtro opcional `anio` en `lista()`), `lang/expediente/ficha/en.json`

**No tocar:** `ClienteController.php` (es de la 5.6a: **solo léelo** para copiar el código de los datos viejos), `routes/clientes.php`, `ClientePanelTest.php`, frontend.

**Contexto mínimo:**
- `ClienteController@show` arma hoy `formas`, `campos`, `catalogoDisponible`, `determinaciones`, `derivationLogs` y usa `duplicadoDe()`: ese código se **copia** a `FichaAnterior::para()` sin cambiarle la forma (la 5.6a lo borra del controlador).
- `VistaEntrevistaPreparador::para()` ya da todo lo de Entrevista y Documentos.
- `ColaExcepciones::lista($actor, ['cliente' => …])` da las filas de la cola global.

**Contrato:**
- Rutas (en `routes/expediente-ficha.php`):
  - `GET clientes/{cliente}/entrevista` → `entrevista` · `clientes.ficha.entrevista`
  - `GET clientes/{cliente}/documentos` → `documentos` · `clientes.ficha.documentos`
  - `GET clientes/{cliente}/excepciones` → `excepciones` · `clientes.ficha.excepciones`
  - `GET clientes/{cliente}/historial` → `historial` · `clientes.ficha.historial`
- Cada método: `authorize('view')`, `$taxYear = (int) $request->query('tax_year', config('tax.current_tax_year'))`, prop `ficha` = `FichaCliente::encabezado(...)`.
  - `entrevista` → página `ficha/entrevista`, prop `entrevista` = `Inertia::defer(fn () => $vista->para($cliente, $taxYear, app()->getLocale() === 'en'), rescue: true)`.
  - `documentos` → `ficha/documentos`, la misma prop `entrevista`.
  - `excepciones` → `ficha/excepciones`, prop `excepciones` = `Inertia::defer(fn () => $cola->lista($request->user(), ['cliente' => $cliente->id, 'anio' => $taxYear]))`.
  - `historial` → `ficha/historial`, props `eventos` (diferida: los eventos del expediente del año, del más nuevo al más viejo, máximo 200, con la forma `EventoExpediente`; nombre del actor con `User::withTrashed()`) y `anterior` (diferida: `FichaAnterior::para($cliente, $taxYear, $request->user())`, que devuelve `null` si el cliente no tiene campos, formas, determinaciones ni trazas de ese año; si no, `['formas' => …, 'campos' => …, 'catalogoDisponible' => …, 'determinaciones' => …, 'derivationLogs' => …]` igual que hoy).
- `ColaExcepciones::lista()`: si `$filtros['anio']` viene, solo las excepciones de entrevistas de ese año. Nada más cambia.

**Criterio de terminado y pruebas:**
- `FichaPestanasTest`: cada pestaña 200 al preparador asignado y al administrador con su componente y la prop `ficha`; 403 a otro preparador y al cliente; la de excepciones filtra por año; las props diferidas se cargan con `->loadDeferredProps()` sin error.
- `FichaHistorialTest`: mueve aquí, adaptados a la ruta nueva, los casos de la ficha vieja de `ClientePanelTest` (`test_el_detalle_carga_con_campos_sensibles_con_acentos`, `test_el_detalle_expone_subcampos_y_tipo_dato_para_editar_objetos_correctamente`, `test_el_show_expone_los_derivation_logs_del_ano_fiscal_actual`, `test_los_datos_del_formulario_anterior_son_de_solo_lectura_en_la_ficha`); `anterior` es `null` para un cliente que solo usó la entrevista nueva; los eventos salen ordenados y sin valores.

**Fuera de alcance:** Resumen (`clientes.show`, es de la 5.6a), pantallas.

---

## Subtarea 5.3a: Revisión — decisiones, checklist y transiciones (servidor)

> **Ajuste del 5 nov.:** (1) la persona exenta (C12 = sí, C13 = no o C14 = no) llega **sugerida como `no_dependiente`** con el motivo "Sus respuestas la dejan fuera como dependiente"; no tiene C1, C2, C5–C11 ni tarjeta, así que se muestra por parentesco ("Persona 1 · padre"). (2) El punto 1 del checklist incluye la tarjeta del cónyuge (`tarjeta-ssn-conyuge`). (3) Los mensajes de excepciones salen de `ExplicadorExcepcion`.

**Ola:** 3 · **Depende de:** 5.1b · **Plan:** 5.3 y "Estados del expediente y transiciones"

**Objetivo:**
Todo lo que hace el preparador en la pestaña Revisión: decidir estado civil y dependientes con motivo, confirmar los puntos del checklist 7.2 y mover el expediente por los estados manuales.

**Archivos permitidos:**
- Crear: `app/Services/Expediente/RevisionExpediente.php`, `app/Http/Controllers/RevisionExpedienteController.php`, `app/Http/Requests/Expediente/EstadoCivilRequest.php`, `ClasificarPersonaRequest.php`, `PuntoRevisionRequest.php`, `TransicionExpedienteRequest.php`, `tests/Feature/Expediente/RevisionTest.php`, `TransicionesTest.php`
- Modificar: `routes/expediente-revision.php`, `lang/expediente/revision/en.json`

**No tocar:** `EstadosExpediente` (úsalo), `DebidaDiligencia`, frontend.

**Contexto mínimo:**
- `TransicionNoPermitida` (`App\Services\Entrevista`) es la excepción de dominio; el controlador la convierte en `ValidationException` (clave `accion`, o el campo que corresponda), como `EntrevistaDocumentoController`.
- Respuestas en palabras: `TextoRespuesta::de()`; las preguntas del catálogo (`CatalogoFiscal::preguntas()`), con su `sensible`.
- Personas vigentes: `$entrevista->personas()` (las archivadas no cuentan).

**Contrato:**
- Rutas (`routes/expediente-revision.php`):
  - `GET clientes/{cliente}/revision` → `show` · `clientes.revision.show` → página `ficha/revision`, props `ficha` y `revision` (diferida)
  - `PUT clientes/{cliente}/revision/estado-civil` → `estadoCivil` · `clientes.revision.estado-civil`
  - `PUT clientes/{cliente}/revision/personas/{persona}` → `persona` · `clientes.revision.persona` (`{persona}` = `EntrevistaPersona`, `whereNumber`)
  - `PUT clientes/{cliente}/revision/puntos/{punto}` → `punto` · `clientes.revision.punto` (`{punto}` con `whereIn` de los 3 manuales)
  - `POST clientes/{cliente}/revision/transicion` → `transicion` · `clientes.revision.transicion`
- Form requests (`authorize` con `ClientePolicy::update`; todos con `tax_year` requerido, entero, 4 dígitos):
  - `EstadoCivilRequest`: `valor` (`Rule::enum(EstadoCivilIrs::class)`), `motivo` requerido, texto, máx. 1.000.
  - `ClasificarPersonaRequest`: `clasificacion` (`Rule::enum(ClasificacionPersona::class)`), `motivo` igual.
  - `PuntoRevisionRequest`: `confirmado` booleano requerido, `nota` opcional máx. 1.000.
  - `TransicionExpedienteRequest`: `accion` en `empezar, preparado, a_firma, presentado, aceptado, rechazado, volver_a_revision`; `fecha` `required_if:accion,presentado`, fecha, `before_or_equal:today`; `motivo` `required_if:accion,rechazado`, máx. 1.000 (regla `validation-conditional-rules`).
- `RevisionExpediente` (cada escritura empieza con `$e = $estados->recalcular($cliente, $anio)`, corre en una transacción y deja su evento):
  - `vista(User $cliente, int $taxYear, bool $ingles): array` con la forma:
    ```php
    [
      'estado' => 'en_revision',
      'acciones' => [['accion' => 'preparado', 'etiqueta' => 'Aprobar y marcar preparado en TaxWise', 'requiere' => null, 'habilitada' => false, 'motivo_deshabilitada' => 'Faltan 2 puntos de la revisión', 'principal' => true]],
      'decisiones_abiertas' => true,
      'estado_civil' => ['valor' => 'hoh', 'motivo' => '…', 'por' => 'Ana', 'en' => '…', 'opciones' => [['valor' => 'single', 'etiqueta' => '…']], 'respuestas_cliente' => [['codigo' => 'B1', 'texto' => '…', 'valor_texto' => 'Soltero(a)']]],
      'personas' => [['id' => 3, 'orden' => 1, 'etiqueta' => 'Persona 1', 'clasificacion' => null, 'motivo' => null, 'por' => null, 'en' => null, 'respuestas' => [['codigo' => 'C3', 'texto' => '…', 'valor_texto' => 'Hijo(a)']]]],
      'clasificaciones' => [['valor' => 'hijo_calificado', 'etiqueta' => 'Hijo calificado']],
      'puntos' => [['punto' => 'identidad', 'titulo' => '…', 'ayuda' => '…', 'manual' => true, 'requerido' => true, 'cumplido' => false, 'detalle' => null, 'confirmado_por' => null, 'confirmado_en' => null, 'nota' => null, 'enlace' => null, 'editable' => true]],
      'no_aplica' => [['id' => 15, 'nombre' => '…', 'motivo' => '…']],
      'puede_aprobar' => false, 'faltan' => 2,
      'diagnostico' => /* Diagnostico::toArray() */,
      'enlaces' => ['documentos' => '…', 'entrevista' => '…', 'excepciones' => '…', 'debida_diligencia' => '…'|null, 'hoja' => '…'|null],
    ]
    ```
    - `respuestas_cliente`: B1, B2, B3, B5 y B6, solo las visibles y respondidas. `personas[].respuestas`: C3, C4, C5, C6, C7, C8, C11, C12, C13 y C14 visibles; **nunca** C1, C2 ni C9 (sensibles).
    - Puntos: la tabla de 5.3 del plan. `detalle`: por qué no se cumple ("Falta clasificar a la persona 2", "R-02: 1 documento sin validar"). `enlace`: a Documentos, Excepciones, Debida diligencia o Entrevista según el punto. `editable` = es manual, requerido y el estado es `en_revision`. El punto 6 es requerido si `creditos_8867` no está vacío; el 7, si G5 = `si`; el 3, si hay personas vigentes.
    - `acciones`: las del estado actual según la tabla del plan; `principal` en la que avanza (empezar, preparado, presentado según el caso).
    - `enlaces.debida_diligencia` y `enlaces.hoja`: solo si la ruta existe (`Route::has`).
  - `decidirEstadoCivil(Expediente, EstadoCivilIrs, string $motivo, User)`, `clasificarPersona(Expediente, EntrevistaPersona, ClasificacionPersona, string $motivo, User)` (`updateOrCreate`), `confirmarPunto(Expediente, PuntoRevision, bool, ?string $nota, User)`, `puntos(Expediente): list`, `puedeAprobar(Expediente): bool`, `transicion(Expediente, string $accion, User, ?string $fecha, ?string $motivo)`.
  - Reglas: decisiones prohibidas si el estado es `trasPresentar()`; una decisión en `preparado_en_taxwise` o `esperando_firma` lleva a `en_revision` con evento `reabierto` ("Cambió una decisión después de preparar"); un punto solo se confirma o desconfirma en `en_revision` y si es manual; `empezar` exige que `recalcular` deje `listo_para_preparar`; `preparado` exige `puedeAprobar`; `presentado` guarda `presentado_en` y la fecha en `datos`; `rechazado` guarda el motivo en el evento; cada transición con `EstadosExpediente::registrarCambio(..., $actor)`.
  - Eventos: decisión → tipo `decision`, `datos = ['decision' => 'estado_civil'|'persona', 'persona_id' => …, 'valor' => 'hoh'|'hijo_calificado']` y el motivo; punto → tipo `punto`, `datos = ['punto' => …, 'confirmado' => bool]`.
- Respuestas del controlador: `back()` con `Inertia::flash('toast', ['type' => 'success', 'message' => __('…')])`.

**Criterio de terminado y pruebas:**
- `RevisionTest`: la vista trae lo del contrato y **ningún** valor de C1, C2, C9 ni B4.3; decidir y clasificar guardan con motivo y evento; motivo vacío → 422; persona de otro cliente → 404; otro preparador → 403; los puntos automáticos se cumplen con los datos; el punto 6 solo es requerido con créditos; el 7 con G5 = `si`; confirmar fuera de `en_revision` → 422.
- `TransicionesTest`: cada acción permitida; una prohibida por estado (422 con el mensaje); `empezar` cuando no está listo falla; `preparado` sin puntos falla y con todos pasa; `presentado` sin fecha o con fecha futura falla; `rechazado` sin motivo falla; `volver_a_revision`; una decisión en `preparado_en_taxwise` vuelve a `en_revision`; después de `presentado` decidir → 422; cada acción deja su evento con el actor.

**Fuera de alcance:** créditos del 8867 (5.4a), la pantalla.

---

## Subtarea 5.4a: Debida diligencia — resumen y créditos (servidor)

**Ola:** 3 · **Depende de:** 5.1b (y 5.1c para el catálogo) · **Plan:** 5.4

**Objetivo:**
El resumen de debida diligencia (Form 8867) armado desde las respuestas y los documentos, y el registro de qué créditos se reclaman.

**Archivos permitidos:**
- Crear: `app/Services/Expediente/DebidaDiligencia.php`, `app/Http/Controllers/DebidaDiligenciaController.php`, `app/Http/Requests/Expediente/CreditosDebidaDiligenciaRequest.php`, `tests/Feature/Expediente/DebidaDiligenciaTest.php`
- Modificar: `routes/expediente-debida-diligencia.php`, `lang/expediente/debida-diligencia/en.json`

**No tocar:** `RevisionExpediente`, `EstadosExpediente` (úsalos), frontend.

**Contexto mínimo:**
- Las preguntas de cada parte: líneas del formulario `f8867` en `CatalogoFiscal::formularios($anio)` (`origen_clave` separado por `;`).
- Respuestas con su historial: `EntrevistaRespuesta` (`canal`, `respondida_en`, `actor_id`) y `cambios()`; `VistaEntrevistaPreparador::para()` muestra cómo se arma algo parecido.
- Documentos de una pregunta: `CatalogoFiscal::activadosPor($anio, $codigo)` con `objetivo_tipo = documento`; los ítems vigentes de ese código, con `revisor` y `revisado_en`.
- Consentimiento firmado: igual que en `VistaEntrevistaPreparador` (último del uso 7216 del año).

**Contrato:**
- Rutas: `GET clientes/{cliente}/debida-diligencia` → `show` · `clientes.debida-diligencia.show` (página `ficha/debida-diligencia`, props `ficha` y `debida` diferida); `PUT clientes/{cliente}/debida-diligencia/creditos` → `creditos` · `clientes.debida-diligencia.creditos`.
- `CreditosDebidaDiligenciaRequest`: `creditos` arreglo (puede ir vacío), cada uno `Rule::enum(Credito8867::class)`, distintos; `tax_year`.
- `DebidaDiligencia::vista(User $cliente, int $taxYear, bool $ingles): array`:
  ```php
  [
    'aplica' => true,
    'creditos' => [['credito' => 'ctc', 'etiqueta' => '…', 'sugerido' => true, 'motivo_sugerencia' => 'La persona 1 está clasificada como hijo calificado', 'reclamado' => true]],
    'creditos_por' => 'Ana', 'creditos_en' => '…',
    'entrevista' => ['iniciada_en' => '…', 'canal_inicio' => 'whatsapp', 'confirmada_en' => '…', 'consentimiento' => ['version' => 1, 'aceptado_en' => '…'] /* o null */],
    'secciones' => [[
      'parte' => 'parte-iii', 'titulo' => 'Parte III — …', 'credito' => 'ctc',
      'preguntas' => [['codigo' => 'C4', 'persona' => ['orden' => 1, 'etiqueta' => 'Persona 1'], 'texto' => '…', 'respondida' => true, 'sensible' => false, 'valor_texto' => '12', 'canal' => 'portal', 'respondida_en' => '…', 'actor' => 'Ana', 'cambios' => 1]],
      'documentos' => [['item_id' => 9, 'codigo' => 'tarjeta-ssn', 'nombre' => '…', 'persona_orden' => 1, 'estado' => 'validado', 'validado_por' => 'Ana', 'validado_en' => '…', 'enlace' => '…visor…']],
    ]],
    'generado_en' => '…',
  ]
  ```
  - Secciones: siempre `parte-i`; además la parte de cada crédito **reclamado** (`Credito8867::parte()`), en el orden del catálogo.
  - Preguntas: solo las visibles en la entrevista (`SituacionEntrevista::esVisible`), una por persona si es del bloque C; `valor_texto` con `TextoRespuesta::de()` y **`null` si la pregunta es sensible**; `actor` = nombre del preparador si la respuesta la escribió el personal, `null` si fue el cliente; `cambios` = cuántos cambios tuvo.
  - Documentos: sin repetir, con `enlace` al visor (`clientes.documentos.visor`).
  - Sugerencias (con su motivo en palabras): `hoh` si `estado_civil_irs = hoh`; `ctc` si alguna persona está clasificada `hijo_calificado` o `pariente_calificado`; `aotc` si F1 = `si`; `eic` si D1, D15 o D16 = `si` y A3 = `ssn_valido`. `reclamado` = está en `creditos_8867`; si nunca se guardó (`creditos_8867` nulo), `reclamado` = `sugerido`.
- `guardarCreditos(Expediente $e, list<Credito8867> $creditos, User $actor)`: escribe `creditos_8867`, `creditos_8867_por`, `creditos_8867_en` y un evento `decision` con `datos = ['decision' => 'creditos_8867', 'valor' => [...]]`. Prohibido después de presentar (422). Empieza con `recalcular`.

**Criterio de terminado y pruebas (`DebidaDiligenciaTest`):** `test_las_partes_y_sus_preguntas_salen_del_catalogo`, `test_un_credito_no_reclamado_no_muestra_su_parte`, `test_las_sugerencias_con_su_motivo`, `test_ningun_valor_sensible_sale_en_las_props`, `test_cada_respuesta_trae_canal_fecha_y_quien`, `test_los_documentos_de_cada_parte_con_quien_los_valido`, `test_guardar_creditos_deja_el_evento`, `test_despues_de_presentar_no_se_cambian_los_creditos`, `test_otro_preparador_recibe_403`.

**Fuera de alcance:** la pantalla; escribir el Form 8867 (se llena en TaxWise).

---

## Subtarea 5.5a: Hoja para TaxWise — servicio, página y CSV (servidor)

> **Ajuste del 5 nov.:** (1) el SSN del cónyuge sale de la tarjeta `tarjeta-ssn-conyuge` (o de B4.3); B4, B4.1 y B4.3 son sensibles (enmascarados en el CSV, revelables con contraseña). (2) La sección `dependientes` omite a las personas exentas. (3) El texto del estado `con_excepcion` usa `ExplicadorExcepcion` (`caso`).

**Ola:** 3 · **Depende de:** 5.1b, 5.0a · **Plan:** 5.5 (léelo entero: secciones, estados, prioridad, totales, sensibles)

**Objetivo:**
Armar la hoja ordenada como el 1040 con el valor, el destino, el origen y el estado de cada dato, y descargarla en CSV sin datos sensibles.

**Archivos permitidos:**
- Crear: `app/Services/Expediente/HojaTaxwise.php`, `app/Http/Controllers/HojaTaxwiseController.php`, `tests/Feature/Expediente/HojaTaxwiseTest.php`, `HojaTaxwiseCsvTest.php`
- Modificar: `routes/expediente-hoja.php`, `lang/expediente/hoja/en.json`

**No tocar:** `DatosDocumento`, `VisorDocumentoController`, `EntrevistaRespuestaController`, frontend.

**Contexto mínimo:**
- Datos vigentes de un ítem: `DatosDocumento::vigentes($item)` (cada `DocumentoDato` con `valor`, `confianza`, `motivo_confianza`, `origen`, `corregido_por`, `corregido_en`).
- Casillas del catálogo (`descripcion`, `tipo_dato`, `clave`, `sensible`): `CatalogoFiscal::documentos($anio)`; líneas y su origen: `CatalogoFiscal::formularios($anio)`.
- Umbral: `ParametrosFiscales::valor($anio, 'lectura', 'umbral_confianza')`; tabla: `CalculadoraConfianza::tabla()`.
- Últimos 4 dígitos: `R01Ssn::ultimos4()`.
- Endpoints para revelar (ya existen): `clientes.documentos.visor.revelar` (`cliente`, `item`, `dato`) y `clientes.entrevista.respuestas.revelar` (`cliente`, `respuesta`).
- Visor en un dato: `route('clientes.documentos.visor', [...]).'?dato='.$id` (5.0a).

**Contrato:**
- Rutas: `GET clientes/{cliente}/hoja-taxwise` → `show` · `clientes.hoja-taxwise.show` (página `ficha/hoja-taxwise`, props `ficha`, `hoja` diferida y `csv_url`); `GET clientes/{cliente}/hoja-taxwise/csv` → `csv` · `clientes.hoja-taxwise.csv`.
- `HojaTaxwise::SECCIONES` (constante, una sola fuente del orden, P6):
  ```php
  public const SECCIONES = [
      'encabezado' => ['preguntas' => ['A1', 'A1.1', 'A1.2', 'A2', 'A3', 'A12', 'B1', 'B4', 'B4.1', 'B4.2', 'B4.3', 'A5', 'A9', 'A14', 'A15'], 'grupos' => []],
      'dependientes' => ['preguntas' => ['C1', 'C2', 'C3', 'C4'], 'grupos' => []],
      'salarios' => ['preguntas' => [], 'grupos' => ['trabajo']],
      'negocio' => ['preguntas' => [], 'grupos' => ['negocio']],
      'otros_ingresos' => ['preguntas' => [], 'grupos' => ['jubilacion', 'inversiones', 'otros_ingresos']],
      'deducciones' => ['preguntas' => [], 'grupos' => ['gastos']],
      'situaciones' => ['preguntas' => [], 'grupos' => ['situaciones']],
      'pagos' => ['preguntas' => ['G1', 'G2'], 'grupos' => ['pagos'], 'sin' => ['datos-bancarios']],
      'banco' => ['preguntas' => ['G3', 'G4'], 'documentos' => ['datos-bancarios']],
  ];
  ```
  - `encabezado` además lleva la fila del **SSN del contribuyente** (ítem `tarjeta-ssn` sin persona, casilla `ssn`) después de A2, y la del **estado civil para el IRS** (decisión) antes de B1. `dependientes` va por persona vigente: sus preguntas, el SSN de su `tarjeta-ssn` y su clasificación (decisión). La sección `totales` va al final, aparte.
  - En las secciones por `grupos`, un **grupo por ítem vigente** (título "W-2 #1 (Empresa X)": nombre oficial, número si hay más de uno, emisor de la casilla `c` o `pagador-nombre` si no es sensible), en el orden del catálogo.
- `HojaTaxwise::para(User $cliente, int $taxYear): array`:
  ```php
  [
    'tax_year' => 2025, 'generado_en' => '…',
    'conteos' => ['filas' => 18, 'pendientes' => 3, 'baja_confianza' => 1, 'con_excepcion' => 1, 'sin_validar' => 2],
    'secciones' => [['codigo' => 'salarios', 'titulo' => 'W-2', 'grupos' => [['titulo' => 'W-2 #1 (Empresa X)', 'item_id' => 12, 'enlace' => '…visor…', 'filas' => [/* Fila */]]]]],
    'totales' => [['destino' => 'Form 1040 · línea 1a', 'descripcion' => 'Salarios (suma de la casilla 1 de todos los W-2)', 'valor' => '45120.00', 'documentos' => 1, 'completo' => true, 'nota' => 'Suma de 1 documento']],
    'confianza_tabla' => CalculadoraConfianza::tabla(),
    'umbral' => 0.9,
  ]
  ```
  Fila:
  ```php
  ['id' => 'dato:345', 'destino' => 'Form 1040 · línea 1a', 'descripcion' => 'Casilla 1 — Salarios, propinas y otra compensación',
   'valor' => '45120.00', 'tipo' => 'monto', 'sensible' => false, 'mascara' => null, 'revelar_url' => null,
   'origen' => 'W-2 #1 (Empresa X), casilla 1', 'enlace' => '…/visor?dato=345',
   'estado' => 'validado', 'estado_texto' => 'Validado por Ana el 3 nov. 2026',
   'confianza' => ['valor' => 0.99, 'titulo' => '…', 'explicacion' => '…']]
  ```
  - Filas de documento: las casillas con valor y las `clave` sin valor (estas como `pendiente`). `destino`: las líneas cuyo `origen_clave` incluye `"{documento}:{casilla}"`, unidas con " y " ("Form 1040 · línea 1a"); `null` si ninguna. `confianza` solo si el dato tiene confianza.
  - Prioridad del estado: la tabla del plan (`con_excepcion` > `pendiente` > `baja_confianza` > `corregido` > `validado` > `sin_validar`). `baja_confianza` = `confianza < umbral` y `origen = lector`. `estado_texto` con `__()` y fechas `->locale(app()->getLocale())->isoFormat('D MMM YYYY')`.
  - Ítem sin datos: si es un documento que se lee y no tiene lectura terminada → una fila `pendiente` ("Falta leerlo" o "Falta: el cliente no lo ha subido" según su estado); si es un documento que **no se lee** (sin casillas en el catálogo) u `otro` → una fila `manual` ("Digitar desde el documento", `enlace` al visor, `origen` con cuántos archivos tiene), o `pendiente` si el ítem está `pendiente`.
  - Filas de respuesta: solo preguntas visibles. `valor` = `TextoRespuesta::de()`; si es sensible, `valor = null`, `mascara` = últimos 4 para B4.3 (`***-**-1234`) y "Oculto" para lo demás, `revelar_url` = el endpoint de la respuesta. Estado `respondido` ("Respondido por WhatsApp el …", "Respondido en el portal el …", "Escrito por Ana el …") o `pendiente` si no tiene respuesta. `enlace` = pestaña Entrevista (`clientes.ficha.entrevista`) si la ruta existe.
  - Filas de decisión: `decidido` ("Decidido por Ana el …", con la etiqueta del enum como `valor`) o `pendiente` ("Falta decidirlo en Revisión"). `enlace` = `clientes.revision.show` si la ruta existe.
  - Casillas sensibles: `valor = null`, `mascara` = últimos 4 si su `tipo_dato` es `ssn`, `ein` o `tin`, si no "Oculto"; `revelar_url` = el endpoint del visor. **El emisor sale en el título solo si su casilla no es sensible.**
  - Totales: por cada línea con `origen_tipo = casilla` de los formularios activos del cliente (`SituacionEntrevista::formularios`, más el 1040) cuya casilla sea `monto`: la suma con `bcadd` a 2 decimales de los valores numéricos vigentes; `valor = null` si ningún documento tiene valor; `completo` si todas sus filas son `validado` o `corregido` y no hay ítems de ese documento `pendiente` ni por leer. Para las líneas de varias fuentes (`1099-nec:1;1099-k:1a`) suma todas.
  - Las líneas `calculo` y `linea` no son filas.
- `HojaTaxwise::filasCsv(User $cliente, int $taxYear): iterable<list<string>>`: la primera fila es el encabezado (`__('Sección')`, `__('Formulario y línea')`, `__('Descripción')`, `__('Valor')`, `__('Origen')`, `__('Estado')`); después una por fila de la hoja (valor = `valor` o `mascara`, **nunca** el revelado) y los totales.
- `HojaTaxwiseController@csv`: `authorize('view')`; `response()->streamDownload(...)` con BOM UTF-8 (`"\xEF\xBB\xBF"`) y `fputcsv` sobre `php://output`; nombre `hoja-taxwise-{cliente}-{anio}.csv`; cabeceras `Content-Type: text/csv; charset=UTF-8` y `Cache-Control: private, no-store`; un `BitacoraActividad` con `AccionAuditoria::Exportado`, etiqueta `"Descargó la hoja para TaxWise {$anio} (cliente #{$id})"`, sin contenido.

**Criterio de terminado y pruebas:**
- `HojaTaxwiseTest`: caso María (W-2 y tarjeta leídos y validados, HOH decidido, un hijo clasificado): secciones en el orden de `SECCIONES`, destinos `Form 1040 · línea 1a` y `25a`, total de 1a; prioridad de estados con una excepción, una casilla clave sin valor, una confianza de 0,60, un dato corregido; cambiar `lectura.umbral_confianza` a 0,5 cambia `baja_confianza` por `validado`; un documento que no se lee sale `manual`; las líneas `calculo` no salen; 2026 usa `1099-nec:1a`; **ninguna prop trae el SSN, el EIN ni B4.3 en claro** (buscar el número de prueba en el JSON de la respuesta); otro preparador 403; el cliente 403.
- `HojaTaxwiseCsvTest`: BOM y encabezados; mismas filas que la pantalla; el SSN sale `***-**-1234`; deja `exportado` en la bitácora sin valores; 403 a otro preparador.

**Fuera de alcance:** la pantalla, el PDF oficial del 1040 (F6).

---

## Subtarea 5.6a: Resumen, lista, panel, avisos y retiros (servidor)

**Ola:** 3 · **Depende de:** 5.1b · **Plan:** 5.2 (Resumen) y 5.6

**Objetivo:**
La pestaña Resumen, la lista de clientes y el panel sobre el expediente; el correo al preparador; y retirar de la web el nivel de riesgo, el ZIP y "marcar revisado".

**Archivos permitidos:**
- Crear: `app/Services/Expediente/PanelExpedientes.php`, `app/Listeners/AvisarPreparadorDeExpediente.php`, `app/Notifications/AvisoExpedientePreparador.php`, `tests/Feature/Expediente/ResumenTest.php`, `PanelTest.php`, `ListaClientesTest.php`, `AvisoPreparadorTest.php`
- Modificar: `app/Http/Controllers/ClienteController.php`, `app/Http/Controllers/DashboardController.php`, `routes/clientes.php`, `config/tax.php` (`'avisos_correo_preparador' => (bool) env('AVISOS_CORREO_PREPARADOR', true)`), `tests/Feature/ClientePanelTest.php`, `tests/Feature/DashboardTest.php`, `lang/expediente/panel/en.json`
- Borrar: `app/Services/RiesgoCasoService.php`, `app/Services/DashboardSummaryService.php`, `tests/Feature/RiesgoCasoTest.php`

**No tocar:** `ClienteExportService.php`, `FormaCliente.php`, `app/Http/Controllers/Api/*`, `routes/api.php` (la API externa sigue igual), `NivelRiesgoManual` y su tabla, frontend.

**Contexto mínimo:**
- `ManagesClientes::clientesVisiblesPara()` limita por rol.
- Conteos por cliente en una consulta: imitar `ChecklistDocumentos::resumenPorCliente()`.
- La 5.2b se lleva los datos del formulario anterior a `FichaAnterior` y sus pruebas a `FichaHistorialTest`: aquí **se borran** de `ClienteController@show` y de `ClientePanelTest`.

**Contrato:**
- `ClienteController@show` → página `ficha/resumen`, props `ficha` (`FichaCliente::encabezado`) y `resumen` (diferida):
  ```php
  [
    'diagnostico' => /* Diagnostico::toArray() */,
    'entrevista' => ['estado' => 'confirmada', 'iniciada_en' => '…', 'confirmada_en' => '…', 'canal_inicio' => 'whatsapp'] /* o null */,
    'consentimiento' => ['version' => 1, 'provisional' => true, 'aceptado_en' => '…'] /* o null */,
    'eventos' => [/* los 30 más nuevos, forma EventoExpediente */],
    'accion_principal' => ['clave' => 'documentos'|'revision'|'hoja', 'url' => '…'] /* o null */,
  ]
  ```
  `accion_principal`: `por_validar` → Documentos; `listo_para_preparar` → Revisión; `en_revision` → Hoja (o Revisión si la ruta de la hoja no existe); los demás, `null`. Solo rutas que existen.
- `ClienteController@index` → `clientes/index` (la página la cambia la 5.6b), por cliente: `id`, `name`, `email`, `phone`, `es_prueba`, `created_at`, `expediente` (`['estado', 'label', 'desde', 'turno', 'vence_en']`; `invitado` si no hay), `perfil` (lista de códigos), `canal_inicio` (o `null`), `excepciones` (`['total', 'bloqueantes']`, una consulta agrupada por cliente y año), `documentos` (como hoy). Arriba: `estados` (`[['valor', 'label']]` en orden), `perfiles` (`[['valor', 'label']]`), `taxYearActual`, `filtroEstado` (`$request->query('estado')` si es un estado válido, si no `null`). Se quitan `formas`, `estado_general` y el riesgo.
- Se borran de `ClienteController`: `marcarRevisado`, `establecerNivelRiesgo`, `limpiarNivelRiesgo`, `export`, `duplicadoDe` y el código de los datos viejos de `show`; y de `routes/clientes.php` las rutas `clientes.export`, `clientes.marcar-revisado`, `clientes.nivel-riesgo.store` y `.destroy`. Lo demás de `ClienteController` (crear, archivar, WhatsApp) queda igual.
- `DashboardController@index` → `dashboard`, props `panel` (diferida) y `taxYearActual`; el cliente sigue yendo a su entrevista.
- `PanelExpedientes::resumen(User $actor, int $taxYear): array`:
  ```php
  [
    'por_estado' => [['estado' => 'por_validar', 'label' => '…', 'cantidad' => 4, 'url' => '…/clientes?estado=por_validar&tax_year=2025']],
    'colas' => ['listos' => 2, 'por_validar' => 4, 'en_consulta' => 3, 'excepciones' => 7, 'bloqueantes' => 2,
                'urls' => ['listos' => '…', 'por_validar' => '…', 'en_consulta' => '…', 'excepciones' => '…/excepciones']],
    'vencen_pronto' => [['cliente_id' => 7, 'nombre' => '…', 'estado' => '…', 'estado_label' => '…', 'vence_en' => '2026-04-15', 'dias' => 12, 'url' => '…']],
    'avisos' => [['tipo' => 'listo_para_preparar', 'texto' => 'María P. está lista para preparar', 'cliente_id' => 7, 'nombre' => '…', 'creado_en' => '…', 'url' => '…']],
    'tiempos' => ['dias_hasta_listo' => 6.2, 'horas_preparacion' => 1.4, 'consultas_por_expediente' => 0.8, 'correcciones_por_expediente' => 1.5, 'muestra' => 5],
    'ultimos_clientes' => [['id' => 7, 'name' => '…']],
  ]
  ```
  - `por_estado`: los 12 estados en orden; `invitado` suma los clientes visibles sin expediente.
  - `vencen_pronto`: `vence_en` dentro de 30 días, estado ni `presentado` ni `aceptado`, del más cercano al más lejano, máximo 10.
  - `avisos`: eventos `estado` de los últimos 14 días de los clientes visibles, máximo 20, del más nuevo: `estado_anterior = en_entrevista` → `termino_entrevista`; `en_recoleccion` → `por_validar` = `subio_documentos`; `estado_anterior = en_consulta` → `respondio_consulta`; `estado_nuevo = listo_para_preparar` → `listo_para_preparar` (gana sobre los otros). Texto con `__()` y el nombre del cliente.
  - `tiempos` (expedientes del año visibles; `null` si no hay muestra): días entre `entrevistas.iniciada_en` y el primer evento a `listo_para_preparar`; horas entre el primer `en_revision` y el primer `preparado_en_taxwise`; promedio de eventos a `en_consulta` por expediente; promedio de `documento_dato_cambios` por expediente. Calculado en PHP sobre los eventos (sin funciones de fecha propias de PostgreSQL, para que SQLite y PostgreSQL den lo mismo).
- Aviso por correo: `AvisarPreparadorDeExpediente::handle(ExpedienteCambioDeEstado $e)` (Laravel lo registra solo por el tipo; **no** registrarlo a mano, se duplicaría como pasó en F4.4a). Si `config('tax.avisos_correo_preparador')` y (`nuevo = listo_para_preparar` **o** `anterior = en_consulta` y `nuevo` ∉ {`en_consulta`, `invitado`, `en_entrevista`}), manda `AvisoExpedientePreparador` (en cola, `mail`) al preparador del cliente o, si no tiene, a los administradores. El correo: asunto `__('Listo para preparar: :cliente')` o `__(':cliente respondió una consulta')`, una línea y el botón `__('Abrir la ficha')` a `clientes.show` con `tax_year`. Nada de la declaración.

**Criterio de terminado y pruebas:**
- `ResumenTest`: componente `ficha/resumen` con `ficha` y `resumen`; `accion_principal` por estado; 403 a otro preparador.
- `ListaClientesTest`: el preparador ve solo los suyos; un cliente sin entrevista sale `invitado`; `perfil`, `canal_inicio`, `excepciones` y `vence_en`; `filtroEstado` válido e inválido; ya no hay `nivel_riesgo` ni `formas`.
- `PanelTest`: conteos por estado (con `invitado`), colas, `vencen_pronto`, avisos de los eventos y el límite de 14 días, tiempos con eventos sembrados (`muestra`), el preparador ve solo sus clientes.
- `AvisoPreparadorTest` (`Notification::fake`): llega a `listo_para_preparar` → un correo al preparador; sin preparador → a los administradores; `en_consulta` → `por_validar` → correo de consulta respondida; bandera apagada → nada; recalcular sin cambio → nada; el correo no trae montos ni SSN.
- `ClientePanelTest` y `DashboardTest` ajustados: se quitan los casos de la ficha vieja (se movieron a `FichaHistorialTest`), de `marcar-revisado` y de formas; las rutas retiradas dan 404; `tests/Feature/ClienteFormasApiTest.php` y las pruebas de la API externa siguen pasando sin tocarlas.

**Fuera de alcance:** las pantallas (5.2c, 5.6b).

---

## Subtarea 5.7a: "¿Cómo va lo mío?" en el portal y el agente

**Ola:** 3 · **Depende de:** 5.1b · **Plan:** 5.7

**Objetivo:**
Que el cliente vea en el portal, y el agente le pueda decir por WhatsApp, en qué va su declaración, sin montos.

**Archivos permitidos:**
- `app/Http/Controllers/Portal/EntrevistaController.php` (solo `inicio`)
- `app/Services/WhatsappAgent/HerramientasEntrevista.php` (solo `consultar`)
- `resources/js/pages/portal/entrevista/inicio.tsx`
- `resources/js/i18n/locales/expediente/miDeclaracion.es.json` y `.en.json`
- `prompt_actuales/fases/seguimiento.md` y `prompt_actuales/fases/portal_dudas.md` (una línea en cada uno)
- `lang/expediente/mi-declaracion/en.json`
- `tests/Feature/Portal/EntrevistaTest.php`, `tests/Feature/Entrevista/AgenteEntrevistaTest.php`

**No tocar:** las demás tools del agente, `EstadoConversacionResolver`, la ficha.

**Contrato:**
- Estado del cliente: `Expediente::query()->where('user_id', …)->where('tax_year', …)->first()`; si no hay, el estado del `DiagnosticoExpediente`. **No** recalcular desde aquí.
- Portal `inicio`: prop nueva `expediente` = `['estado' => 'por_validar', 'texto' => EstadoExpediente::textoCliente($presentadoEn), 'desde' => '…'|null]`.
- `consultar`: clave nueva `'expediente' => ['estado' => …, 'para_el_cliente' => …, 'desde' => …]`, con el texto en español (el agente traduce si habla inglés, como el resto de la tool).
- `inicio.tsx`: arriba de las pantallas, una `Card` "Tu declaración" (`miDeclaracion.titulo`) con el texto del servidor, la fecha ("Actualizado el …") y un ícono; sin acción (la acción principal de la página sigue siendo "Continuar"). Sin colores como única señal.
- Prompts (una línea en cada archivo, en el estilo de los demás): "Si el cliente pregunta cómo va su declaración, contesta con `expediente.para_el_cliente` de `consultar_entrevista`. No inventes fechas ni montos ni prometas cuándo llega el reembolso."

**Criterio de terminado y pruebas:**
- `EntrevistaTest`: el inicio trae `expediente.texto` en español y en inglés (con la cookie de idioma); un cliente no ve el de otro.
- `AgenteEntrevistaTest`: `consultar` trae `expediente` con el estado y el texto; no trae montos (buscar un monto de prueba en el JSON).
- Anotar en la entrega: después del deploy hace falta `agente:borrador-prompts --fases=seguimiento,portal_dudas` (con autorización de Steven).

**Fuera de alcance:** el resumen final con montos (F6), avisos por WhatsApp.

---

## Subtarea 5.2c: Pantallas Resumen e Historial

**Ola:** 4 · **Depende de:** 5.2a, 5.2b, 5.6a · **Plan:** 5.2 (interfaz)

**Objetivo:**
La pestaña Resumen (estado, qué falta, acción principal, línea de tiempo) y la pestaña Historial (eventos y datos del formulario anterior), y retirar la ficha vieja.

**Archivos permitidos:**
- Reemplazar: `resources/js/pages/ficha/resumen.tsx`, `resources/js/pages/ficha/historial.tsx`
- Crear: `resources/js/components/ficha/linea-tiempo.tsx`, `resources/js/components/ficha/que-falta.tsx`, `resources/js/components/ficha-anterior/*` (lo que se mueve de `clientes/show.tsx`: `FormaSection`, `CategoriaGroup`, `CampoRow`, `ValorCampo`, `HistorialDialog`, `FieldValue`, `DuplicadoBadge` y sus constantes)
- Borrar: `resources/js/pages/clientes/show.tsx`
- Modificar: `resources/js/i18n/locales/expediente/ficha.es.json` y `.en.json` (claves `resumen.*` e `historial.*`)

**No tocar:** `layouts/ficha/layout.tsx` y los componentes de la 5.2a (úsalos), `components/cliente/entrevista-cliente.tsx` (es de la 5.2d), `determinacion-fiscal-panel.tsx` y `derivation-logs-panel.tsx` (se usan tal cual).

**Contexto mínimo:**
- Props: `ficha` y `resumen` (5.6a) en Resumen; `ficha`, `eventos` y `anterior` (5.2b) en Historial.
- En Historial, lo viejo se muestra **en solo lectura**: se quita el botón "marcar revisado" de `FormaSection` (su ruta ya no existe) y no se trae `NivelRiesgoControl`.

**Contrato:**
- Resumen: tarjeta "Qué falta para Listo para preparar" (`QueFalta`) con cada condición del diagnóstico como fila con ícono y texto (✓/✗ más palabras): consentimiento, entrevista confirmada, R-02 (lista de `faltan` con su estado y enlace a Documentos; los `no_aplica` con su motivo en una lista plegable), excepciones que frenan (enlace a Excepciones), consultas abiertas; la **acción principal** de `accion_principal` como `Button` (la única primaria de la página); si `listo`, un mensaje de éxito. Debajo, la línea de tiempo (`LineaTiempo`): eventos del más nuevo al más viejo, con fecha, estado nuevo (con `EstadoExpedienteBadge`), quién y el motivo si hay (los `reabierto` resaltados con ícono). Datos de la entrevista y del consentimiento en una línea. Skeleton mientras llega `resumen`; error con `rescue`.
- Historial: la misma `LineaTiempo` con todos los eventos; si `anterior` no es nulo, una sección "Formulario anterior (solo lectura)" con `DeterminacionFiscalPanel`, `DerivationLogsPanel` y las secciones por forma; si es nulo, nada de eso. Vacío: "Todavía no hay movimientos en este expediente".
- `.layout` de cada página: `{ breadcrumbs: [dashboard, clientes] }` como hoy.

**Criterio de terminado:** `npm run types:check` sin errores en tus archivos; nadie importa `pages/clientes/show.tsx`; claves en es y en. La revisión visual la hace el agente principal.

**Fuera de alcance:** otras pestañas.

---

## Subtarea 5.2d: Pantallas Entrevista, Documentos y Excepciones

**Ola:** 4 · **Depende de:** 5.2a, 5.2b · **Plan:** 5.2

**Objetivo:**
Tres pestañas que reutilizan lo que ya existe, partiendo `EntrevistaCliente` en respuestas y documentos.

**Archivos permitidos:**
- Reemplazar: `resources/js/pages/ficha/entrevista.tsx`, `documentos.tsx`, `excepciones.tsx`
- Modificar: `resources/js/components/cliente/entrevista-cliente.tsx` (queda solo con la cabecera de la entrevista, avisos y respuestas)
- Crear: `resources/js/components/cliente/documentos-cliente.tsx` (se mueven `Documentos`, `ResumenDocs`, `ExcepcionesAbiertasFila`, `DocumentoFilaPreparador`, `RevisionDialog`, `accionesPermitidas`, `nombreDocumento` y lo que usen, **sin cambiar su lógica**; si comparten utilidades con el otro archivo, pásalas a `components/cliente/utilidades.ts`)
- Modificar: `resources/js/i18n/locales/expediente/ficha.es.json` y `.en.json` (claves `excepciones.*`)

**No tocar:** `pages/clientes/show.tsx` (la borra la 5.2c), `pages/excepciones/index.tsx`, `components/excepciones/resolver-dialog.tsx` (úsalo tal cual).

**Contrato:**
- `ficha/entrevista`: `<Deferred data="entrevista" fallback={<EntrevistaClienteSkeleton />} rescue={<EntrevistaClienteError />}>` con `EntrevistaCliente`.
- `ficha/documentos`: mismo `Deferred` con `DocumentosCliente({ clienteId, entrevista })` (resumen, excepciones abiertas y la lista con "Ver datos").
- `ficha/excepciones`: lista de tarjetas (no tabla: en el celular no cabe) con regla, severidad (insignia con texto), documento, casilla, motivo de la confianza en R-03 (título + explicación en un `Tooltip` y en texto para lectores de pantalla), fecha y las acciones: "Corregir" (enlace al visor), "Aceptar con nota" y "Consultar al cliente" (`ResolverExcepcionDialog`, fuera del `<Deferred>`). Vacío: "Este cliente no tiene excepciones abiertas" con ícono. Enlace "Ver la cola de todos mis clientes".
- Las claves de texto viejas (`clienteShow.*`, `excepciones.*`) se siguen usando donde ya estaban.

**Criterio de terminado:** `types:check` sin errores en tus archivos; el comportamiento de corregir, revelar, historial, revisar documentos y resolver excepciones es el mismo de antes.

**Fuera de alcance:** Resumen, Historial.

---

## Subtarea 5.3b: Pantalla Revisión

**Ola:** 4 · **Depende de:** 5.2a, 5.3a · **Plan:** 5.3 (interfaz)

**Objetivo:**
La pestaña donde el preparador decide, confirma el checklist y mueve el expediente.

**Archivos permitidos:**
- Reemplazar: `resources/js/pages/ficha/revision.tsx`
- Crear: `resources/js/components/revision/*` (por ejemplo `estado-civil-form.tsx`, `persona-form.tsx`, `checklist-revision.tsx`, `acciones-expediente.tsx`, `transicion-dialog.tsx`), `resources/js/types/revision.ts`
- Modificar: `resources/js/i18n/locales/expediente/revision.es.json` y `.en.json`

**No tocar:** otras páginas y componentes.

**Contrato:**
- Tipos en `types/revision.ts` con la forma exacta de `revision` (5.3a).
- Formularios con `useForm` de Inertia y las rutas de Wayfinder (`clientes.revision.*`), `tax_year` en el cuerpo, `preserveScroll`:
  - **Estado civil:** `fieldset`/`legend`; "El cliente respondió" (lista de `respuestas_cliente`); `Select` con `Label`; `Textarea` del motivo con su error (`aria-describedby`, `aria-invalid`); "Guardar" con spinner; "Decidido por … el …". Si `decisiones_abiertas` es falso, todo en solo lectura con el motivo al lado.
  - **Personas:** una tarjeta por persona, su formulario reiniciado con `key={persona.id + persona.en}`; radios (casilla y `Label` lado a lado) con las `clasificaciones`; motivo; respuestas del cliente en una lista; "Datos sensibles (nombre, fecha, número): ver en Entrevista" con enlace.
  - **Checklist:** una fila por punto con ícono y texto de estado (cumplido / falta / no aplica), título, ayuda, `detalle` y `enlace`; los manuales `editable` con `Checkbox` + `Label` lado a lado y nota opcional (se guarda al cambiar la casilla, con spinner por fila); los automáticos dicen "Lo verifica la plataforma". El punto 4 muestra los `no_aplica` con su motivo.
  - **Acciones:** abajo, la `principal` como único botón primario; si está deshabilitada, `motivo_deshabilitada` se lee al lado; las demás `variant="outline"`. Las que `requiere` fecha o motivo abren `TransicionDialog` (fecha con `Input type="date"` y `max` = hoy; motivo con `Textarea`); confirmación antes de `preparado` ("Vas a marcar este expediente como preparado en TaxWise").
- Estados: skeleton mientras llega `revision`; si el estado no permite revisar (antes de `listo_para_preparar`), un `Alert` con el diagnóstico ("La revisión empieza cuando el expediente esté Listo para preparar: …") y las decisiones igual se pueden tomar.
- Responsive: una columna a 375 px; las acciones se apilan; nada de scroll horizontal.

**Criterio de terminado:** `types:check` sin errores en tus archivos; textos en es y en.

**Fuera de alcance:** créditos del 8867 (5.4b).

---

## Subtarea 5.4b: Pantalla Debida diligencia

**Ola:** 4 · **Depende de:** 5.2a, 5.4a · **Plan:** 5.4 (interfaz)

**Objetivo:**
La pestaña con los créditos que se reclaman y el resumen por parte del Form 8867, imprimible.

**Archivos permitidos:**
- Reemplazar: `resources/js/pages/ficha/debida-diligencia.tsx`
- Crear: `resources/js/components/debida-diligencia/*`, `resources/js/types/debida-diligencia.ts`
- Modificar: `resources/js/i18n/locales/expediente/debidaDiligencia.es.json` y `.en.json`

**No tocar:** otras páginas.

**Contrato:**
- Tipos con la forma de `debida` (5.4a).
- **Créditos:** `fieldset` con una `Checkbox` + `Label` por crédito (lado a lado), el motivo de la sugerencia debajo ("Sugerido: …"), "Guardar créditos" (`useForm`, `PUT clientes.debida-diligencia.creditos`, spinner); "Marcado por … el …".
- **Entrevista:** una línea con inicio, canal, confirmación y consentimiento.
- **Partes:** una tarjeta por sección con su título; preguntas como lista de definición (`dl`): código y texto, persona si hay, respuesta (o "Sensible: ver en Entrevista" con enlace), canal, fecha y hora, quién ("el cliente" o el nombre), "N cambios"; documentos con estado y "validado por … el …" y enlace al visor.
- `BotonImprimir` arriba a la derecha; al imprimir no salen botones ni formularios, sí todas las partes y la fecha de `generado_en`.
- Vacío (sin créditos): "Ningún crédito del 8867 marcado: este cliente no necesita el resumen" y la parte I igual se muestra.

**Criterio de terminado:** `types:check` sin errores en tus archivos; textos en es y en.

**Fuera de alcance:** la hoja.

---

## Subtarea 5.5b: Pantalla Hoja para TaxWise

**Ola:** 4 · **Depende de:** 5.2a, 5.5a · **Plan:** 5.5 (interfaz)

**Objetivo:**
La hoja en pantalla, con filtro de pendientes, revelar, la confianza explicada, imprimir y descargar el CSV.

**Archivos permitidos:**
- Reemplazar: `resources/js/pages/ficha/hoja-taxwise.tsx`
- Crear: `resources/js/components/hoja/*` (por ejemplo `seccion-hoja.tsx`, `fila-hoja.tsx`, `estado-fila.tsx`, `totales-hoja.tsx`), `resources/js/types/hoja-taxwise.ts`
- Modificar: `resources/js/i18n/locales/expediente/hoja.es.json` y `.en.json`

**No tocar:** `components/visor/confianza-panel.tsx` (úsalo tal cual), otras páginas.

**Contrato:**
- Tipos con la forma de `hoja` (5.5a).
- Arriba: título, año, "generada el …", los conteos como texto ("3 pendientes · 1 con dudas · 1 con excepción"), `Checkbox` + `Label` "Solo lo pendiente" (estado local; filtra en el render las filas cuyo estado no sea `validado`, `corregido`, `respondido` ni `decidido`), "Descargar CSV" (`<a href={csv_url}>` con `Button variant="outline"`) y `BotonImprimir` (`print:hidden` ambos). Una nota fija: "Hoja de trabajo para digitar en TaxWise. No se presenta al IRS."
- Secciones y grupos con encabezados `h2`/`h3`. En `md` y más, cada grupo es una tabla (`components/ui/table`) con columnas Destino, Descripción, Valor, Origen y Estado; en el celular cada fila es una tarjeta con las mismas cinco cosas etiquetadas (sin scroll horizontal; regla `responsive-design`).
- Valor: monto con `Intl.NumberFormat` (2 decimales) si `tipo = monto`; lo demás tal cual. Sensible: `mascara` + botón "Revelar" (`useRevelar(fila.revelar_url)`; 423 → enlace a confirmar contraseña); al revelar se muestra el valor (y así sale al imprimir).
- Estado: `EstadoFila` con ícono y `estado_texto` (nunca solo color). Si hay `confianza`: el número (`formatearConfianza`), el título del motivo, la explicación (texto visible o en un `Tooltip` con el texto también para lectores de pantalla) y un botón "¿Cómo se calcula?" que abre **un solo** `ConfianzaPanel` de la página con `confianza_tabla` y `umbral`.
- Origen con enlace (`<Link>` al visor o a la pestaña); en la impresión el enlace se ve como texto.
- Totales al final: destino, descripción, valor, nota y "completa"/"incompleta" con ícono.
- Skeleton por secciones mientras llega `hoja`; error con `rescue`; vacío: "Todavía no hay datos para la hoja: el cliente no ha respondido ni subido documentos".
- Impresión: sin botones ni filtros; todas las filas (ignora "solo lo pendiente" con `print:` o desmarcándolo antes de imprimir); encabezado con cliente, año y fecha; cortes de página evitando partir una fila (`break-inside-avoid`).

**Criterio de terminado:** `types:check` sin errores en tus archivos; textos en es y en.

**Fuera de alcance:** el borrador oficial del 1040 (F6).

---

## Subtarea 5.6b: Pantallas Panel y Lista de clientes

**Ola:** 4 · **Depende de:** 5.2a, 5.6a · **Plan:** 5.6 (interfaz)

**Objetivo:**
El panel sobre expedientes (estados, colas, avisos, tiempos) y la lista de clientes como cola de trabajo.

**Archivos permitidos:**
- Reemplazar el contenido de `resources/js/pages/dashboard.tsx` y `resources/js/pages/clientes/index.tsx`
- Crear: `resources/js/components/panel/*`, `resources/js/types/panel.ts`
- Modificar: `resources/js/i18n/locales/expediente/panel.es.json` y `.en.json`

**No tocar:** `components/dashboard/*` salvo para dejar de importarlo (si queda sin uso, anótalo para el cierre), `data-table*.tsx`.

**Contrato:**
- Panel (`panel` diferida, skeleton de tarjetas):
  - fila de tarjetas por estado (`cantidad` > 0 primero, cada una un `Link` a su `url`, con `EstadoExpedienteBadge`);
  - "Te toca" (listos, por validar, en consulta, excepciones con bloqueantes; cada una un enlace), "Vencen en 30 días" (lista con días), "Avisos" (lista con ícono por tipo, texto del servidor y fecha relativa), "Tiempos" (cuatro cifras con su explicación en una línea; "Sin datos todavía" si `muestra` = 0), "Últimos clientes";
  - en el celular una columna; en `lg` dos.
- Lista de clientes (sigue con `DataTable` de TanStack y el filtrado en el navegador):
  - columnas: Cliente, Estado del expediente (`EstadoExpedienteBadge` + "desde"), Le toca, Vence, Tipo de ingreso (insignias de `perfil` con su `label` de `perfiles`), Excepciones (total y bloqueantes con ícono), Documentos (como hoy), WhatsApp (como hoy);
  - filtros con `DataTableFacetedFilter`: estado, le toca, tipo de ingreso, canal; y "con excepciones";
  - el filtro de estado arranca con `filtroEstado` (estado inicial perezoso, sin efecto);
  - orden inicial: le toca al preparador primero, después vencimiento;
  - se quitan las columnas de riesgo, formas y estado general; el alta de cliente queda igual.

**Criterio de terminado:** `types:check` sin errores en tus archivos; textos en es y en.

**Fuera de alcance:** la cola de excepciones global (queda igual).

---

## Cierre de la fase (agente principal)

1. `php artisan wayfinder:generate --with-form`, `vendor/bin/pint --test`, `npm run types:check`, `npx eslint` sobre lo tocado, `npm run build`.
2. `tests/Feature/Expediente/MariaExpedienteTest.php` (el caso de punta a punta del plan).
3. `PAO_DISABLE=1 php artisan test --parallel`; informar cuántas pasan, se saltan y fallan.
4. Revisión en el navegador contra PostgreSQL local: cada pestaña a 375 px y en escritorio, en español e inglés, con estados vacío, error, éxito y procesando; imprimir la hoja y la debida diligencia (vista previa); abrir el CSV en Excel o Numbers; revelar con contraseña; panel y lista con clientes de prueba en varios estados.
5. Borrar las claves de `es.json`/`en.json` que quedaron sin uso (riesgo, formas, `clienteShow.exportZip`…) y `components/dashboard/*` si nadie lo importa.
6. "Cómo quedó" de cada entrega en el plan, `tareas-desarrollo.md`, Wiplitask, y preguntarle a Steven antes de cada commit (orden del plan).
