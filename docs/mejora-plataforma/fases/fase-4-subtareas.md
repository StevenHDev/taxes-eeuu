# Fase 4 — Subtareas para trabajar con Sonnet 5.5

3 nov. 2026 · Plan aprobado: [`fase-4-lectura-validacion.md`](fase-4-lectura-validacion.md)

> **Por qué este documento (Steven, 3 nov.):** desde ahora se trabaja con **Sonnet 5.5**. Lo que queda de la fase 4 se partió en subtareas chicas con la plantilla de Steven (objetivo, archivos, contexto, criterio de terminado, dependencias y fuera de alcance). Las decisiones de diseño, fiscales y de §7216 ya están tomadas aquí, para que cada subtarea sea "seguir instrucciones", no "decidir".

## Cómo usar cada subtarea

1. Una subtarea por vez, en orden, en una sesión con Sonnet 5.5. Pegar la subtarea completa como pedido.
2. Antes de programar, leer: `docs/mejora-plataforma/guia-desarrollo.md`, la sección de la fase 4 que cita la subtarea y los archivos de "Contexto mínimo".
3. Tocar solo los "Archivos permitidos". Si hace falta otro, parar y preguntarle a Steven.
4. Al terminar: `vendor/bin/pint --dirty`, `npm run types:check` (si hubo frontend), las pruebas de la subtarea y `php artisan test --compact --parallel`. Si hubo pantallas, revisarlas en el navegador (escritorio y celular, ver "Reglas de interfaz" del plan).
5. Anotar el "Cómo quedó" en el plan, marcar la subtarea aquí (✅) y **preguntarle a Steven antes del commit**, que se hace con `/commit`.
6. Nunca: commit sin preguntar, tocar `.env*`, escribir en producción sin autorización, ni los tres archivos sueltos de la raíz.

## Reglas comunes a todas las subtareas

- **§7216 y datos sensibles:** ningún valor leído (montos, SSN, EIN, nombres) va en logs, en la bitácora, en `detalle` de una excepción, en mensajes de error ni en las props de una pantalla del cliente. En las del preparador, lo sensible (casillas con `sensible = true`) va enmascarado (`***-**-1234`) y se revela con contraseña, como en F2.9 (`EntrevistaRespuestaController@reveal`).
- **Los datos leídos se escriben solo con** `App\Services\Lectura\DatosDocumento`; **los estados del checklist solo con** `App\Services\Entrevista\ChecklistDocumentos`.
- **Los números fiscales** (topes, umbral de confianza) salen de los **parámetros del año** del catálogo (`ParametrosFiscales::valor($anio, $categoria, $clave)`), nunca escritos en el código.
- **Pruebas:** clases Pest como las de `tests/Feature/Entrevista/`. OpenAI siempre simulado (`Http::fake`); nunca llamar a la red en pruebas.

## Estado

| # | Subtarea | Depende de | Estado |
| --- | --- | --- | --- |
| 4.2a | Tablas, modelos y servicio de los datos leídos | ninguna | ✅ 3 nov. (sin commit) |
| 4.2b | Pruebas del modelo de datos | 4.2a | ✅ 3 nov. (sin commit) |
| 4.3a | Páginas como imagen y HEIC | 4.2b | ✅ 3 nov. (sin commit) |
| 4.3b | Esquema de lectura y llamada a OpenAI | 4.2b | ✅ 3 nov. (sin commit) |
| 4.3c | Confianza y evidencia | 4.3b | ⏳ |
| 4.3d | Lector de OpenAI, lector manual y bandera | 4.3a, 4.3c | ⏳ |
| 4.4a | Leer al subir: evento, job, estados y comando | 4.3d | ⏳ |
| 4.5a | Motor de reglas y reglas de un documento (R-03, R-04, R-06, R-07, R-09) | 4.4a | ⏳ |
| 4.5b | Reglas que cruzan datos (R-01, R-05, R-08, R-11) | 4.5a | ⏳ |
| 4.6a | Visor: rutas, permisos, imágenes y corrección | 4.5a | ⏳ |
| 4.6b | Visor: pantalla | 4.6a | ⏳ |
| 4.7a | Cola de excepciones: acciones | 4.5a | ⏳ |
| 4.7b | Cola de excepciones: pantalla | 4.7a | ⏳ |
| 4.8a | Lo que ve el cliente y el agente | 4.4a | ⏳ |
| 4.8b | Imagen de ejemplo de cada documento | ninguna | ⏳ |

**Catálogo (3 nov., sin commit):** `tarjeta-ssn` con sus 3 casillas (344 casillas activas por año).

**4.2a (ya hecha, sin commit, 3 nov.):** migración `2026_11_03_120000_crea_lectura_de_documentos` (tablas `documento_lecturas`, `documento_datos`, `documento_dato_cambios`, `excepciones`), enums en `app/Enums/Lectura/`, modelos en `app/Models/Lectura/`, `App\Services\Lectura\DatosDocumento` (`vigentes()`, `corregir()`), `ChecklistDocumentos::reabrirValidacion()`, relaciones en `Documento`, `Entrevista` y `EntrevistaDocumento`, y bitácora en `AppServiceProvider`.

## Decisiones de Steven

1. **(3 nov.) Se lee también la tarjeta del Seguro Social (`tarjeta-ssn`).** La R-01 compara el SSN de cada documento con el de la persona, pero la entrevista solo guarda el número del cónyuge (B4.3); del contribuyente (A3) y de los dependientes (C9) guarda el tipo (SSN, ITIN o ATIN), no el número, que viene en la tarjeta que suben. Casillas leídas (ya en el catálogo): `nombre`, `ssn` y `no-valido-para-trabajar`, las dos primeras sensibles. Son **21 documentos** los que lee F4. La R-01 compara contra la tarjeta (contribuyente y dependientes) y contra B4.3 (cónyuge).

---

## Subtarea 4.2b: Pruebas del modelo de datos

**Objetivo:**
Probar lo que dejó la 4.2a: que los valores quedan cifrados, que corregir guarda el historial y reabre lo validado, y que nada se borra al retirar un ítem.

**Archivos permitidos:**
- `tests/Feature/Lectura/DatosDocumentoTest.php` (nuevo)
- `app/Services/Lectura/DatosDocumento.php` y los modelos de `app/Models/Lectura/`, solo si una prueba muestra un error

**Contexto mínimo:**
- Un dato se crea con `DocumentoLectura::create([...])->datos()->create([...])`; el ítem es un `EntrevistaDocumento` (ver `tests/Feature/Entrevista/RevisionDocumentosTest.php` para armar cliente, entrevista, ítem y archivo con `Storage::fake(Documento::disco())`).
- `valor`, `valor_leido` y los valores del historial usan el cast `ValorCifrado`; `evidencia` usa `encrypted`.
- `DatosDocumento::corregir()` exige motivo (lanza `TransicionNoPermitida`), no hace nada si el valor no cambia, guarda un `DocumentoDatoCambio` con el valor anterior y el nuevo, y si el ítem estaba `validado` lo pasa a `leido`.

**Criterio de terminado:**
- En la base (`DB::table('documento_datos')->value('valor')`), el valor no se ve en claro; con el modelo sí.
- Corregir: queda un cambio con anterior, nuevo, motivo y actor; `valor_leido` no cambia; `origen` pasa a `preparador`.
- Corregir sin motivo lanza `TransicionNoPermitida` y no escribe nada.
- Corregir un ítem `validado` lo deja `leido`; uno `subido` no cambia de estado.
- `vigentes()` devuelve solo los datos de la última lectura `leida` de cada archivo.
- La bitácora (`BitacoraActividad`) registra el cambio sin el valor.
- Retirar el ítem (`ChecklistDocumentos::sincronizar` cuando deja de aplicar) no borra lecturas ni datos.
- Pasa la suite completa.

**Dependencias:**
4.2a (hecha).

**Fuera de alcance:**
Lector, reglas y pantallas.

---

## Subtarea 4.3a: Páginas como imagen y HEIC

**Objetivo:**
Convertir cada archivo (PDF, JPG, PNG o HEIC) en imágenes PNG por página, guardadas cifradas, para mandarlas a OpenAI y mostrarlas en el visor.

**Archivos permitidos:**
- `app/Services/Lectura/PaginasDocumento.php` (nuevo)
- `Dockerfile` (agregar `libheif-examples` en la etapa que ya instala `poppler-utils`)
- `tests/Feature/Lectura/PaginasDocumentoTest.php` (nuevo) y un PDF de prueba pequeño en `tests/Fixtures/lectura/` (sin datos reales)

**Contexto mínimo:**
- `app/Services/DocumentoExtraccion/DocumentoVisionExtractorService.php` ya rasteriza con `pdftoppm -png -r 150` (copiar el enfoque, no llamar a esa clase).
- `App\Services\DocumentoAlmacen::leer(Documento)` devuelve el contenido descifrado del archivo; para guardar las páginas cifradas usar `Crypt::encryptString(base64)` o el mismo esquema de `DocumentoAlmacen` en el disco `Documento::disco()`, ruta `lecturas/{lectura_id}/pagina-{n}.png`.
- HEIC: `heif-convert entrada.heic salida.jpg`. Si el binario no existe, lanzar una excepción clara (como hace la clase vieja con `pdftoppm`).
- Máximo 10 páginas por archivo (un formulario del IRS tiene 1 o 2); si hay más, error claro.
- Archivos temporales siempre borrados al terminar (`finally`).

**Criterio de terminado:**
- `PaginasDocumento::generar(Documento, DocumentoLectura): int` guarda las páginas cifradas y devuelve cuántas; `PaginasDocumento::leer(DocumentoLectura, int $pagina): string` devuelve el PNG descifrado.
- Prueba con el PDF de muestra (2 páginas) y con un JPG; la de HEIC se salta si falta `heif-convert` (`markTestSkipped`).
- En disco no queda ningún PNG en claro.

**Dependencias:**
4.2b.

**Fuera de alcance:**
OpenAI, confianza, estados del checklist.

---

## Subtarea 4.3b: Esquema de lectura y llamada a OpenAI

**Objetivo:**
Pedirle a OpenAI los datos de un documento con salida estructurada, armada desde las casillas del catálogo.

**Archivos permitidos:**
- `app/Services/WhatsappAgent/OpenAiClient.php` (método nuevo, sin cambiar los que hay)
- `app/Services/Lectura/EsquemaLectura.php` (nuevo)
- `tests/Feature/Lectura/EsquemaLecturaTest.php` (nuevo)

**Contexto mínimo:**
- Casillas del catálogo: `app(CatalogoFiscal::class)->documentos($anio)->firstWhere('codigo', $codigo)['casillas']` (cada una con `casilla`, `descripcion`, `tipo_dato`, `clave`, `sensible`).
- Modelo: `config('services.openai.vision_model')` (decisión 1 del plan: el mismo que ya usa la plataforma).
- Método nuevo en `OpenAiClient`: `leerConEsquema(string $instrucciones, array $imagenesDataUrl, array $esquema): array` con `response_format: {type: json_schema, json_schema: {name, strict: true, schema}}`, mismo manejo de errores y de `reasoning_effort` que `completarChat`.
- El esquema pide: `tipo_documento` (string), `anio` (integer o null), `paginas_legibles` (bool) y `casillas`: un objeto con una propiedad por casilla del catálogo y, en cada una, `valor` (string o null), `legibilidad` (`claro` | `dudoso` | `no_se_ve`), `pagina` (integer o null) y `texto_cercano` (string o null: el texto exacto impreso alrededor del valor).
- Instrucciones al modelo (en inglés, cortas): leer solo lo que se ve, nunca inferir ni completar; si no se lee, `no_se_ve` y `valor` null; montos sin símbolo ni comas de miles (`45120.00`); fechas `YYYY-MM-DD`; SSN como aparece (puede venir truncado `XXX-XX-1234`).
- Nunca registrar en logs la respuesta de OpenAI (trae datos de la declaración).

**Criterio de terminado:**
- `EsquemaLectura::para(string $codigo, int $anio): array` arma el esquema JSON (strict: todas las propiedades en `required`, `additionalProperties: false`).
- Prueba del esquema para W-2 y 1095-A (trae `12a-codigo`, `21-A`…).
- Prueba de `leerConEsquema` con `Http::fake`: manda el modelo, las imágenes y el `response_format`, y devuelve el JSON decodificado; un error HTTP lanza una excepción sin el contenido.

**Dependencias:**
4.2b.

**Fuera de alcance:**
Confianza, guardar datos, cola.

---

## Subtarea 4.3c: Confianza y evidencia

**Objetivo:**
Calcular la confianza de cada dato y ubicar su recuadro cuando el PDF tiene texto.

**Archivos permitidos:**
- `app/Services/Lectura/CalculadoraConfianza.php` (nuevo)
- `app/Services/Lectura/UbicadorEnPdf.php` (nuevo)
- `tests/Feature/Lectura/ConfianzaTest.php` (nuevo) y un PDF con texto de prueba en `tests/Fixtures/lectura/`

**Contexto mínimo:**
- Tabla de confianza (plan, 4.3; decisión de Steven):

| Caso | Confianza |
| --- | --- |
| PDF con texto y el valor aparece en la capa de texto | 0,99 |
| `claro` y pasa el formato de su `tipo_dato` | 0,90 |
| `dudoso`, o no pasa el formato | 0,60 |
| `no_se_ve` o sin valor | 0 |

- Formatos por `tipo_dato`: `monto` número con hasta 2 decimales; `ssn` 9 dígitos o truncado `XXX-XX-1234`; `ein` `NN-NNNNNNN`; `tin` ssn o ein; `anio` 4 dígitos entre 2000 y el año actual + 1; `fecha` `YYYY-MM-DD` válida; `si_no` `si`/`no`; el resto, texto no vacío.
- Capa de texto: `PdfTextExtractorService::extraer()` (ya existe). Para comparar montos, normalizar (`45,120.00` = `45120.00`).
- Recuadro: `smalot/pdfparser` da posiciones con `Page::getDataTm()`; normalizar a 0–1 sobre el tamaño de la página (`x`, `y`, `ancho`, `alto`). Si no se encuentra, `null` (nunca inventar un recuadro).

**Criterio de terminado:**
- `CalculadoraConfianza::calcular(array $casilla, ?string $valor, Legibilidad $legibilidad, bool $estaEnTextoDelPdf): float` cumple la tabla (prueba por cada fila).
- `UbicadorEnPdf::buscar(string $rutaPdf, string $valor, ?int $pagina): ?array` encuentra el monto del PDF de prueba y devuelve un recuadro dentro de 0–1.
- Valores mal formateados (`12.3.4` como monto, SSN de 8 dígitos) dan 0,60.

**Dependencias:**
4.3b.

**Fuera de alcance:**
Llamar a OpenAI, guardar datos.

---

## Subtarea 4.3d: Lector de OpenAI, lector manual y bandera

**Objetivo:**
Unir 4.3a, 4.3b y 4.3c en un lector que devuelve un resultado listo para guardar, con un lector manual de respaldo y la bandera para apagar la lectura.

**Archivos permitidos:**
- `app/Services/Lectura/LectorDocumentos.php` (interfaz), `LectorOpenAi.php`, `LectorManual.php`, `LecturaResultado.php` (DTO, `readonly`) (nuevos)
- `config/tax.php` (`'lectura_automatica' => (bool) env('LECTURA_AUTOMATICA', true)`) y `config/services.php` si hace falta
- `app/Providers/AppServiceProvider.php` (enlazar la interfaz según la bandera)
- `app/Services/Lectura/DatosDocumento.php` (método `guardarLectura`)
- `tests/Feature/Lectura/LectorTest.php` (nuevo)

**Contexto mínimo:**
- Decisión de Steven (3 nov.): la lectura automática corre para **todos** los usuarios; la bandera `tax.lectura_automatica` (prendida por defecto) permite apagarla sin deploy. Apagada → `LectorManual` (no lee nada; el preparador escribe los datos en el visor).
- Documentos que se leen: los 21 de la decisión 4 del plan (incluye `tarjeta-ssn`). Los demás → `LectorManual`.
- `LecturaResultado`: tipo y año detectados, páginas, y por casilla `valor`, `legibilidad`, `confianza`, `pagina`, `recuadro`, `evidencia` (`texto_cercano`).
- `DatosDocumento::guardarLectura(DocumentoLectura, LecturaResultado)`: en una transacción, crea un `DocumentoDato` por casilla con `valor` = `valor_leido` = lo leído y `origen = lector`; marca la lectura `leida` con `terminada_en`. Releer crea otra lectura; no toca la anterior.

**Criterio de terminado:**
- Con OpenAI simulado, un W-2 de muestra queda con sus casillas, confianza y evidencia guardadas y cifradas.
- Con la bandera apagada no se hace ninguna llamada HTTP (`Http::assertNothingSent()`).
- Un documento fuera de la lista usa el lector manual.
- Un error de OpenAI marca la lectura `fallida` con un error sin datos del cliente.

**Dependencias:**
4.3a y 4.3c.

**Fuera de alcance:**
Cola, estados del checklist, reglas.

---

## Subtarea 4.4a: Leer al subir

**Objetivo:**
Que cada archivo subido se lea solo, en la cola, y el ítem quede `leido` o `no_se_pudo_leer`.

**Archivos permitidos:**
- `app/Events/ArchivoDocumentoSubido.php`, `app/Jobs/LeerDocumentoJob.php`, `app/Console/Commands/LeerDocumentos.php` (nuevos)
- `app/Services/Entrevista/DocumentosEntrevista.php` (disparar el evento en `subir()`)
- `app/Services/Entrevista/ChecklistDocumentos.php` (métodos `leido()` y `noSePudoLeer()`)
- `tests/Feature/Lectura/LeerAlSubirTest.php` (nuevo)

**Contexto mínimo:**
- El worker de producción corre `php artisan queue:work --tries=3 --max-time=3600` (`docker/entrypoint.sh`), que escucha la cola `default`. El job usa la cola `default` (no hace falta tocar el despliegue); `tries = 3`, `backoff = [60, 300]`.
- Estados: al terminar bien, `subido` → `leido` (si el ítem sigue `subido`; si el preparador ya lo validó o lo observó, no se toca). Si falla después de los reintentos (`failed()`), → `no_se_pudo_leer`. Nunca queda `subido` sin datos ni aviso (6.1.5).
- Si el tipo o el año detectados no coinciden con el ítem, se guarda igual (la regla R-06 de la 4.5a lo marca).
- Comando `documentos:leer {--entrevista=} {--solo-prueba} {--dry-run}`: encola la lectura de los archivos vigentes que no tienen una lectura `leida`.

**Criterio de terminado:**
- Subir un archivo encola `LeerDocumentoJob` (`Queue::fake`).
- Job bien → ítem `leido` y datos guardados; job fallido → `no_se_pudo_leer`.
- Un ítem ya validado no cambia de estado al releer.
- El comando con `--dry-run` no encola nada y no muestra datos del cliente.

**Dependencias:**
4.3d.

**Fuera de alcance:**
Reglas (las corre la 4.5a), pantallas.

---

## Subtarea 4.5a: Motor de reglas y reglas de un documento

**Objetivo:**
Correr las reglas del año después de cada lectura y corrección, abriendo y cerrando excepciones; implementar R-03, R-04, R-06, R-07 y R-09.

**Archivos permitidos:**
- `app/Services/Lectura/Reglas/Regla.php` (interfaz), `MotorReglas.php`, `R03Confianza.php`, `R04TopesW2.php`, `R06AnioYTipo.php`, `R07Repetido.php`, `R09Adelanto8962.php` (nuevos)
- `database/data/catalogo-fiscal/2025/parametros.php` (umbral y topes si faltan) y `reglas.php` (marcar `implementada`)
- `app/Jobs/LeerDocumentoJob.php` y `app/Services/Lectura/DatosDocumento.php` (llamar al motor)
- `app/Services/Entrevista/ChecklistDocumentos.php` (`validar()` no deja validar con una excepción bloqueante abierta)
- `tests/Feature/Lectura/ReglasDocumentoTest.php` (nuevo)

**Contexto mínimo:**
- Cada regla: `codigo()` y `evaluar(Entrevista, Collection $datosPorItem): list<Hallazgo>`. Un hallazgo trae el ítem, el dato (si aplica), una `clave` única (por ejemplo `R-04#item:12#casilla:2`) y un `detalle` **sin valores en claro** (sí nombres de casilla y códigos).
- `MotorReglas::correr(Entrevista)`: corre las reglas activas y `implementada` del año; por cada hallazgo hace `firstOrCreate` por (`entrevista_id`, `clave`) y lo deja `abierta` (si estaba `corregida`, la reabre; si está `aceptada`, no la toca); las excepciones abiertas cuyo hallazgo ya no aparece pasan a `corregida`.
- Severidad: la del catálogo (`CatalogoRegla::severidad`).
- Reglas:
  - **R-03:** casilla con `clave = true` y confianza < umbral (parámetro, 0,90). Los datos corregidos por el preparador no cuentan.
  - **R-04:** W-2: casilla 2 ≤ casilla 1, y casilla 3 + casilla 7 ≤ tope del Seguro Social de 2025 (USD 176.100, parámetro).
  - **R-06:** `anio_detectado` ≠ año de la entrevista, o `tipo_detectado` ≠ código del ítem.
  - **R-07:** el archivo tiene coincidencias en `DocumentoDuplicadoService::buscarCoincidencias()`.
  - **R-09:** 1095-A con alguna casilla `21-C`…`32-C` o `33-C` > 0 → el Form 8962 es obligatorio (informa; el 8962 ya lo activa F4).
- Corre después de `guardarLectura` y de `corregir`.

**Criterio de terminado:**
- Un caso por regla con los errores de prueba (W-2 con casilla 2 mayor que la 1, casilla 3 + 7 sobre el tope, W-2 de 2024, archivo repetido, 1095-A con adelanto, dato clave con 0,60).
- Correr dos veces no duplica; corregir el dato pasa la excepción a `corregida`.
- No se puede validar un ítem con una bloqueante abierta (`TransicionNoPermitida`).
- El `detalle` de ninguna excepción contiene un monto ni un SSN.

**Dependencias:**
4.4a.

**Fuera de alcance:**
R-01, R-05, R-08 y R-11 (4.5b); pantallas.

---

## Subtarea 4.5b: Reglas que cruzan datos

**Objetivo:**
Implementar R-01, R-05, R-08 y R-11, que cruzan varios documentos o la entrevista, y correr el motor cuando cambia una respuesta.

**Archivos permitidos:**
- `app/Services/Lectura/Reglas/R01Ssn.php`, `R05ExcesoSeguroSocial.php`, `R08RespuestasContraDocumentos.php`, `R11HijoSinSsn.php` (nuevos)
- `app/Services/Entrevista/RespuestaGuardador.php` (llamar al motor al guardar)
- `database/data/catalogo-fiscal/2025/reglas.php` (marcar `implementada`; R-11 queda con severidad `revision` en F4)
- `tests/Feature/Lectura/ReglasCruzadasTest.php` (nuevo)

**Contexto mínimo:**
- **R-01:** los últimos 4 dígitos del SSN del documento (`a` en el W-2, `receptor-tin` en los 1099, `receptor-tin` en SSA-1099, `5`/`8` y `16-B`…`20-B` en el 1095-A) deben coincidir con los de la persona. Fuente de la persona: el cónyuge en B4.3 (respuesta sensible, se descifra en el servidor y nunca sale); el contribuyente y los dependientes, la tarjeta del Seguro Social leída (`tarjeta-ssn`, un ítem por persona, casilla `ssn`). Sin tarjeta leída, solo se compara entre documentos de la misma persona. Las copias del cliente pueden traer el SSN truncado: comparar solo los últimos 4.
- **R-05:** suma de la casilla 4 de los W-2 **por persona** (contribuyente y cónyuge por separado, según R-01 o el ítem) con dos o más empleadores; si pasa el máximo de 2025 (USD 10.918,20, parámetro) → informativa.
- **R-08**, tres casos: (a) W-2 con casilla 10 > 0 y E6 distinto de `si`; (b) más W-2 leídos que los empleos de D1.1; (c) persona cubierta del 1095-A (parte II) cuyos últimos 4 del SSN no están en la entrevista.
- **R-11 (solo aviso):** persona de C con parentesco de hijo, menor de 17 al 31 de diciembre (C2) y tipo de número (C9) distinto de `ssn_valido`.
- Las respuestas sensibles se leen con el modelo (`EntrevistaRespuesta`, cast `ValorCifrado`) solo dentro de la regla; nunca se ponen en `detalle`.

**Criterio de terminado:**
- Un caso que falla y uno que pasa por regla, incluidos el SSN truncado (R-01) y R-05 con dos cónyuges (cada uno bajo el máximo, aunque juntos lo pasen → no hay excepción).
- Cambiar E6 a `si` cierra la excepción de R-08 (a).
- Ninguna excepción guarda un SSN ni un monto.

**Dependencias:**
4.5a.

**Fuera de alcance:**
R-02 (F5), R-10, R-11 bloqueante y R-12 (F6), R-13 (F7).

---

## Subtarea 4.6a: Visor — rutas, permisos, imágenes y corrección

**Objetivo:**
El backend del visor: página con los datos de un ítem, imágenes de sus páginas, revelar lo sensible y corregir un dato.

**Archivos permitidos:**
- `app/Http/Controllers/VisorDocumentoController.php`, `app/Http/Requests/CorregirDatoRequest.php` (nuevos)
- `routes/clientes.php`
- `app/Policies/ClientePolicy.php` (si hace falta un permiso)
- `tests/Feature/Lectura/VisorTest.php` (nuevo)

**Contexto mínimo:**
- Rutas bajo `clientes/{cliente}/documentos/{item}`: `GET visor` (página Inertia `clientes/visor`), `GET visor/paginas/{lectura}/{pagina}` (imagen PNG, sin caché pública), `POST visor/datos/{dato}/revelar` (con contraseña reciente, como `EntrevistaRespuestaController@reveal`) y `PATCH visor/datos/{dato}` (corrección).
- Permiso: el preparador asignado al cliente o un administrador (mismo criterio que la ficha, `ClientePolicy`); el ítem, la lectura y el dato tienen que ser de ese cliente (si no, 404).
- Props: el ítem, sus archivos, sus lecturas (páginas) y por dato: casilla, descripción del catálogo, `tipo_dato`, `clave`, valor (enmascarado si `sensible`), confianza, legibilidad, página, recuadro, evidencia (enmascarada si la casilla es sensible), origen, quién corrigió y las excepciones abiertas del dato.
- Corregir: `CorregirDatoRequest` (valor nullable string máx. 255, motivo requerido máx. 500) → `DatosDocumento::corregir()`.

**Criterio de terminado:**
- Otro preparador recibe 403; un ítem de otro cliente, 404.
- Las props no traen ningún valor sensible en claro.
- La imagen se descifra al vuelo; sin permiso, 403.
- Corregir guarda el historial, corre las reglas y reabre lo validado.

**Dependencias:**
4.5a.

**Fuera de alcance:**
La pantalla (4.6b).

---

## Subtarea 4.6b: Visor — pantalla

**Objetivo:**
La pantalla donde el preparador ve el documento y sus datos lado a lado y corrige.

**Archivos permitidos:**
- `resources/js/pages/clientes/visor.tsx` (nuevo) y componentes en `resources/js/components/visor/` (nuevos)
- `resources/js/types/lectura.ts` (nuevo)
- `resources/js/components/cliente/entrevista-cliente.tsx` (botón "Ver datos" en cada documento leído)
- `resources/js/i18n/locales/es.json` y `en.json`

**Contexto mínimo:**
- Escritorio: imagen de la página a la izquierda (con selector de página si hay varias) y lista de datos a la derecha. Al tocar un dato con recuadro, se resalta con un `<rect>` SVG sobre la imagen (coordenadas 0–1); sin recuadro, se muestra la página y el texto leído ("Se leyó: …").
- Celular: imagen arriba, datos abajo.
- Cada dato: casilla, descripción, valor, confianza (baja en ámbar), excepciones, "Revelar" si es sensible y "Corregir" (diálogo con valor y motivo, ambos con su error).
- Teclado: subir y bajar entre datos.
- Reglas de interfaz de Steven: skeleton al cargar las imágenes, estados vacío (sin lectura todavía), error (no se pudo leer) y éxito, una acción principal, `components/ui`, accesibilidad (el resaltado también se anuncia con texto).

**Criterio de terminado:**
- `npm run types:check` limpio; probado en el navegador en escritorio y en celular (390 px) con un W-2 y un 1095-A de prueba.

**Dependencias:**
4.6a.

**Fuera de alcance:**
Cola de excepciones.

---

## Subtarea 4.7a: Cola de excepciones — acciones

**Objetivo:**
Las tres salidas de una excepción: corregir (abre el visor), aceptar con nota y consultar al cliente.

**Archivos permitidos:**
- `app/Services/Lectura/ColaExcepciones.php`, `app/Http/Controllers/ExcepcionController.php`, `app/Http/Requests/ResolverExcepcionRequest.php` (nuevos)
- `routes/clientes.php`
- `app/Services/Entrevista/VistaEntrevistaPreparador.php` (conteo de excepciones abiertas en la ficha)
- `tests/Feature/Lectura/ColaExcepcionesTest.php` (nuevo)

**Contexto mínimo:**
- Aceptar con nota: estado `aceptada`, `resuelta_por`, `resuelta_en`, `nota` requerida.
- Consultar al cliente: texto requerido; usa `ChecklistDocumentos::observar()` en el ítem (queda "con observación"; el cliente lo ve en "Mis documentos" y el agente se lo explica, 3.5) y la excepción pasa a `consultada`. Cuando el cliente sube algo nuevo, la lectura y el motor la vuelven a evaluar.
- Lista: excepciones abiertas de los clientes del preparador (el administrador ve todas), ordenadas por severidad (bloqueante, revisión, informativa) y después por fecha.

**Criterio de terminado:**
- Cada salida cambia el estado y deja registro en la bitácora.
- Consultar al cliente aparece en `consultar_entrevista` del agente (como comentario del preparador).
- Un preparador no ve ni resuelve excepciones de clientes de otro (403/404).

**Dependencias:**
4.5a.

**Fuera de alcance:**
La pantalla (4.7b).

---

## Subtarea 4.7b: Cola de excepciones — pantalla

**Objetivo:**
La lista de excepciones del preparador y el conteo en la ficha.

**Archivos permitidos:**
- `resources/js/pages/excepciones/index.tsx` (nuevo), menú lateral (`resources/js/components/app-sidebar.tsx` o donde estén los ítems)
- `resources/js/components/cliente/entrevista-cliente.tsx` (conteo de abiertas)
- `resources/js/i18n/locales/es.json` y `en.json`

**Contexto mínimo:**
- Columnas: cliente, documento, regla (código y descripción), severidad (insignia), fecha y acciones (corregir → visor; aceptar con nota y consultar al cliente → diálogos).
- Filtros por severidad y por cliente; vacío: "No hay excepciones abiertas".
- Seguir el patrón de tablas de `resources/js/pages/clientes/index.tsx` y las reglas de interfaz de Steven.

**Criterio de terminado:**
- Probado en el navegador (escritorio y celular) con excepciones de prueba; tipos limpios.

**Dependencias:**
4.7a.

**Fuera de alcance:**
Expediente y estados del caso (F5).

---

## Subtarea 4.8a: Lo que ve el cliente y el agente

**Objetivo:**
Que el cliente sepa que su documento se está revisando o que hay que subirlo de nuevo, sin ver nunca lo leído.

**Archivos permitidos:**
- `resources/js/components/entrevista/documento-estado.tsx`, `documento-fila.tsx`
- `resources/js/i18n/locales/es.json` y `en.json`
- `app/Services/WhatsappAgent/HerramientasEntrevista.php` (marcar los `no_se_pudo_leer`)
- `prompt_actuales/fases/seguimiento.md` y `portal_dudas.md` (una línea)
- `tests/Feature/Portal/DocumentosTest.php`

**Contexto mínimo:**
- "Leído" se muestra como "Lo estamos revisando". "No se pudo leer" pide subirlo de nuevo, más nítido o en PDF.
- Las props del portal nunca traen datos leídos ni confianza.
- Después del deploy hay que crear un borrador de prompts (con autorización de Steven).

**Criterio de terminado:**
- Prueba: las props del portal no tienen `documento_datos`; un ítem `no_se_pudo_leer` aparece en `documentos.pendientes` del agente con su aviso.

**Dependencias:**
4.4a.

**Fuera de alcance:**
Visor y cola.

---

## Subtarea 4.8b: Imagen de ejemplo de cada documento

**Objetivo:**
Una miniatura del formulario oficial del IRS (sin datos) en la fila de cada documento, para que el cliente sepa qué buscar (decisión 3 de F3).

**Archivos permitidos:**
- Migración nueva (`imagen_ejemplo` en `catalogo_documentos`), `CatalogoFiscalImportador.php` (`CLAVES`), `database/data/catalogo-fiscal/2025/documentos.php`
- `public/ejemplos-documentos/` (imágenes)
- `app/Services/Entrevista/DocumentosEntrevista.php` (`lista()` devuelve la URL)
- `resources/js/components/entrevista/documento-fila.tsx` y `resources/js/types/entrevista.ts`
- `tests/Feature/CatalogoFiscal/CatalogoRealTest.php`

**Contexto mínimo:**
- Las imágenes salen de los PDF oficiales vacíos del IRS (dominio público), por ejemplo `pdftoppm -png -r 60 -f 1 -l 1` sobre `https://www.irs.gov/pub/irs-prior/fw2--2025.pdf`. Solo para formularios del IRS de la lista de la decisión 4; los demás quedan sin imagen.
- Imagen chica (ancho 600 px, menos de 150 KB), con `alt` descriptivo y que se abra más grande al tocarla.
- Hay que recargar el catálogo en producción (con autorización).

**Criterio de terminado:**
- La imagen aparece solo en los documentos que la tienen; prueba de que las rutas del catálogo existen en `public/`.

**Dependencias:**
ninguna.

**Fuera de alcance:**
Lectura y reglas.
