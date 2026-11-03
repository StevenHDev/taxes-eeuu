# Fase 4 — Lectura y validación de documentos: plan técnico

2 nov. 2026 · **Aprobado** por Steven (2 nov.), con las decisiones del final · Documento madre: [secciones 4, 6 y 9.5](../documento-madre.md), pendientes T-c, T-d, L2 y L4 de la [sección 10](../documento-madre.md#10-decisiones-pendientes)

> **Objetivo:** que el preparador **no revise documento por documento**. La plataforma lee cada documento por casilla, guarda el valor, la confianza y de dónde salió (página y recuadro), y corre las reglas del año. Lo que pasa las reglas queda listo. Lo que falla o es dudoso va a una cola, donde el preparador corrige, acepta con nota o le consulta al cliente.
>
> **Resultado que se busca (9.5):** un W-2 y un 1095-A se leen con evidencia y las reglas detectan los errores de prueba.
>
> **Objetivo del producto (Steven, 2 nov.):** menos trabajo para el cliente y para el preparador. La lectura quita la digitación; la cola deja revisar solo lo que importa. El cliente nunca escribe un número que ya está en un documento.
>
> **Buenas prácticas:** skills `laravel-best-practices`, `react-best-practices` y `vercel-react-best-practices`; cada tarea cita la regla que aplica. Reglas de interfaz de Steven (carga con skeleton, responsive, jerarquía, consistencia con `components/ui`, accesibilidad, estados vacío/error/éxito, estados de interacción).
>
> **Modelo por tarea:** **Opus 5.5** para diseño, datos de clientes, §7216, lógica fiscal y reglas; **Sonnet 5.5** para pantallas que siguen un patrón, contenido de catálogo con formato conocido y pruebas de comportamiento ya definido. Si un punto mezcla las dos cosas, se parte.
>
> **Base:** `main` al 2 nov. (F0 menos F0.4, F1, F2 y F3 menos 3.6 en producción).

## Qué hay hoy

- **Checklist con estados (F3.2):** `entrevista_documentos` ya tiene `leido` y `no_se_pudo_leer`, pero nada los usa todavía. Un archivo subido queda en `subido` hasta que el preparador lo valida a ojo.
- **Archivos cifrados (F0.2)** con su hash (`hash_contenido`) y `DocumentoDuplicadoService`, que detecta el mismo archivo en otra cuenta.
- **Extracción vieja** (`app/Services/DocumentoExtraccion`): saca el texto completo (PDF con texto o visión de OpenAI). No lee por casilla, no da confianza ni evidencia, y desde 2.11 nadie la llama. `pdftoppm` ya está en la imagen de Docker.
- **Catálogo (F1):** casillas solo para W-2, 1099-NEC, 1099-K y 1095-A, sin el encabezado (SSN, EIN, año) y con el 1095-A resumido. Las reglas R-01 a R-13 están como **texto** (código, severidad, fuente), sin nada que las ejecute. Las líneas del 1040 ya apuntan a casillas (`w2:1` → línea 1a).

## Resumen

| # | Tarea | Sale de | Tamaño | Riesgo | Modelo |
| --- | --- | --- | --- | --- | --- |
| 4.1 | Casillas completas en el catálogo: encabezado, W-2 casilla 12, 1095-A por mes, 1099 frecuentes | 4, 6.1 | Mediano | Bajo | Sonnet 5.5 (contenido) + Opus 5.5 (revisión fiscal) |
| 4.2 | Modelo de datos: lectura, dato leído con confianza y evidencia, excepción | 6.1, 6.3 | Mediano | Medio: datos de clientes cifrados | Opus 5.5 |
| 4.3 | Lector de documentos con OpenAI: casillas con salida estructurada, confianza calculada, evidencia, páginas como imagen y HEIC | 6.1, T-c | Grande | Alto: §7216 (L2) y precisión | Opus 5.5 |
| 4.4 | Leer al subir: cola, estados `leido` / `no_se_pudo_leer`, reintentos | 6.1.5 | Mediano | Medio | Opus 5.5 |
| 4.5 | Motor de reglas: R-01 y R-03 a R-09, y R-11 como aviso, con parámetros del catálogo | 6.2 | Grande | Medio: lógica fiscal | Opus 5.5 |
| 4.6 | Visor de documento: archivo a la izquierda, datos a la derecha, recuadro resaltado, corregir con motivo | 6.3 | Grande | Medio | Sonnet 5.5 (pantalla) + Opus 5.5 (corrección y reapertura) |
| 4.7 | Cola de excepciones: corregir, aceptar con nota o consultar al cliente | 6.3 | Mediano | Medio | Opus 5.5 (salidas) + Sonnet 5.5 (pantalla) |
| 4.8 | El cliente ve "Leído" o "No se pudo leer" y la consulta; imagen de ejemplo de cada documento | 5.2, 6.3 | Pequeño | Bajo | Sonnet 5.5 |

Orden: 4.1 → 4.2 → 4.3 → 4.4 → 4.5 → 4.6 → 4.7 → 4.8. 4.1 y la imagen de ejemplo de 4.8 no dependen de nada y se pueden adelantar.

---

## 4.1 Casillas completas en el catálogo

**Modelo: Sonnet 5.5** (contenido con formato conocido) **+ Opus 5.5** (revisión contra los formularios del IRS de 2025).

**Enfoque:**
- Encabezado de cada documento como casillas (`tipo_dato` nuevo: `ssn`, `ein`, `anio`, `nombre`), marcadas `sensible` cuando lo son (columna nueva en `catalogo_casillas`): W-2 a, b, c, e y el año; 1099 del pagador y del receptor; 1095-A parte I y parte II (personas cubiertas, con SSN).
- W-2 casilla 12 con hasta cuatro pares código y monto (12a a 12d) y casilla 14 libre.
- 1095-A por mes: líneas 21 a 32 con columnas A, B y C, y la línea 33. Reemplaza las cuatro casillas resumidas de hoy.
- Las casillas de los 21 documentos que se leen (decisión 4): las que piden las reglas de 4.5 y las líneas del 1040.
- Cada casilla con su fuente (el formulario oficial de 2025). 2026 lo hereda.

**Pruebas:** `CatalogoRealTest` cuenta las casillas por documento y verifica que las líneas del 1040 apunten a casillas que existen.

**Cómo quedó (3 nov., subagente Sonnet 5.5 + revisión con Opus 5.5):**
- Los 20 documentos tienen sus casillas, verificadas contra los formularios del IRS de 2025 (`pub/irs-prior/f…--2025.pdf`). Excepciones: 1099-INT y 1099-DIV (Rev. 1-2024, la vigente en 2025), 1099-K y W-2G (solo hay revisiones posteriores; se dejaron fuera las casillas que agregan) y SSA-1099 (Pub. 915). 341 casillas activas, 76 sensibles.
- Encabezado en todos: `pagador-*`, `receptor-*` (o `a`, `b`, `c`, `e` en el W-2) y `anio`. W-2 casilla 12 como `12a-codigo` / `12a-monto` … `12d`. Sin casillas estatales (Florida no cobra renta), direcciones ni números de cuenta.
- 1095-A: parte I, personas cubiertas `16-A` a `20-E`, meses `21-A` a `32-C` y totales `33-A` a `33-C`.
- **Form 8962 corregido en la revisión:** el subagente había dejado la línea 11 como suma de `33-A;33-B;33-C` (en el catálogo `;` suma) y los meses sumados en `col-a`, `col-b` y `col-f`. Ahora cada columna sale de una sola casilla: `11a`, `11b`, `11f` (totales del año) y `12a` a `23f` (enero a diciembre = líneas 21 a 32 del 1095-A). 98 líneas activas.
- Columna `sensible` en las casillas (migración `2026_11_03_100000`). `tipo_dato` limitado a `monto, numero, texto, si_no, codigo, fecha, anio, nombre, ssn, ein, tin`; el verificador lo revisa, igual que ssn/ein/tin sensibles y que cada línea apunte a una casilla activa.
- Importador: desactiva casillas y líneas que salen del archivo (también en 2026 con sus ajustes) y, **corregido en la revisión**, las reactiva si vuelven. Respeta lo editado en pantalla salvo con `--forzar`. Resuelve el pendiente técnico de 2.5.
- Editor del catálogo: selector de tipo de dato y casilla "Sensible". Revisado en el navegador: cada casilla va en una fila en pantalla ancha (grilla propia, diálogo más ancho) y en dos columnas en el celular; la marca "Se lee" pasó a llamarse "Clave", porque ahora se leen todas y la marca dice cuáles usan las reglas y las líneas.
- Pruebas: `CatalogoRealTest`, `CargaTest` (desactivar, reactivar, editado en pantalla, verificador) y `EdicionTest`. Suite: 662 pruebas, 660 pasan y 2 se saltan.

## 4.2 Modelo de datos

**Modelo: Opus 5.5** (datos de clientes; reglas `eloquent-casts`, `eloquent-relationships`, `arch-value-objects`).

**Enfoque:**
- `documento_lecturas`: un intento de lectura por archivo. Guarda el lector y la versión del modelo, el tipo y el año detectados, el estado (`en_cola`, `leida`, `fallida`), el error y las páginas.
- `documento_datos`: un dato por casilla.
  - Guarda la casilla, el valor **cifrado** (cast `encrypted`: montos y SSN son información de la declaración, §7216), la confianza (0 a 1), la página y el recuadro (polígono normalizado de 0 a 1).
  - El origen es `lector` o `preparador`, con quién lo corrigió, cuándo y el motivo.
  - Una corrección **no pisa**: guarda el valor leído y el corregido, como el historial de respuestas de F2.9.
- `excepciones`: una por regla que falla. Guarda la entrevista, el ítem y el dato de donde sale, la regla, la severidad, el detalle (sin valores sensibles en claro) y el estado (`abierta`, `corregida`, `aceptada`, `consultada`), con quién la resolvió y la nota.
- Todo auditado (bitácora de F0). Lo leído nunca se muestra fuera del portal y de la ficha.

**Pruebas:** el valor queda cifrado en la base; corregir guarda los dos valores y el motivo; un ítem retirado (F3.2) no borra sus datos.

**Cómo quedó (3 nov., Opus 5.5 y Sonnet 5.5):**
- Migración `2026_11_03_120000_crea_lectura_de_documentos`: `documento_lecturas`, `documento_datos`, `documento_dato_cambios` y `excepciones`.
- Enums en `app/Enums/Lectura/` (`EstadoLectura`, `Legibilidad`, `OrigenDato`, `EstadoExcepcion`) y modelos en `app/Models/Lectura/`, con relaciones desde `Documento`, `Entrevista` y `EntrevistaDocumento`.
- Cifrado: `valor`, `valor_leido` y los valores del historial con `ValorCifrado`; `evidencia` con `encrypted`. `valor_leido` no cambia nunca; el recuadro va como `{x, y, ancho, alto}` entre 0 y 1.
- `App\Services\Lectura\DatosDocumento`: `vigentes()` (última lectura terminada de cada archivo) y `corregir()` (motivo obligatorio, historial, `origen = preparador`, y si el ítem estaba validado lo vuelve a `leido` con `ChecklistDocumentos::reabrirValidacion()`).
- Bitácora: lecturas, datos y excepciones se registran con `AuditoriaObserver` (solo nombres de atributo).
- Pruebas (`tests/Feature/Lectura/DatosDocumentoTest.php`, 8): cifrado en la base, historial, corregir sin motivo y al mismo valor, reapertura, `vigentes()`, bitácora sin valores y ítem retirado sin borrar nada. Suite: 671 pruebas, 669 pasan y 2 se saltan.
- **Tarjeta del Seguro Social:** `tarjeta-ssn` ahora tiene casillas (`nombre`, `ssn`, `no-valido-para-trabajar`) y es el documento 21 que lee F4 (344 casillas activas por año).

## 4.3 Lector de documentos

**Modelo: Opus 5.5** (arquitectura, §7216 y costo; reglas `arch-service-classes`, `controller-dependency-injection`).

**Decisión de Steven (2 nov.):** se lee con **OpenAI**, con el modelo que ya usa la plataforma (`OPENAI_VISION_MODEL`). Azure no se puede usar por ahora.

**Enfoque:**
- Interfaz `LectorDocumentos::leer(archivo, codigo esperado, año): LecturaResultado`. Devuelve el tipo y el año detectados, y para cada casilla del catálogo el valor, la confianza, la página y la evidencia. Las casillas salen del catálogo (4.1): el lector no "busca campos sueltos". Si algún día se cambia de proveedor, se cambia solo esta clase.
- **Lectura con OpenAI:** cada página como imagen, con **salida estructurada** (`response_format` con un esquema JSON armado desde las casillas del catálogo). `OpenAiClient` suma un método para eso. Por cada casilla el modelo devuelve:
  - el valor;
  - si se lee **claro**, **dudoso** o **no se ve**;
  - la página;
  - el texto exacto que leyó al lado (por ejemplo, "1 Wages, tips, other compensation 45,120.00").
- **Confianza sin Azure:** OpenAI no da una confianza calibrada, así que la plataforma la calcula con lo que puede comprobar:

| Caso | Confianza |
| --- | --- |
| PDF con texto y el valor aparece en la capa de texto del PDF | 0,99 |
| El modelo lo leyó "claro" y pasa el formato de la casilla (monto, SSN, EIN, año) | 0,90 |
| El modelo lo leyó "dudoso", o no pasa el formato | 0,60 |
| No se ve | 0 (sin valor) |

  Con el umbral de 0,90 (R-03), lo dudoso siempre va a revisión. Las reglas de 4.5 (por ejemplo, casilla 2 ≤ casilla 1) son una segunda comprobación.
- **Evidencia sin Azure:**
  - **PDF con texto:** el recuadro exacto, buscando el valor en las posiciones del texto del PDF (`smalot/pdfparser` las da con `getDataTm()`).
  - **Foto o PDF escaneado:** la página y el texto que leyó el modelo, sin recuadro exacto. El visor (4.6) muestra la página y ese texto; no inventa un recuadro.
- **Páginas como imagen:** cada página se guarda como PNG cifrado (con `pdftoppm`, que ya está en la imagen de Docker), para mandarla a OpenAI y para el visor. HEIC se convierte a JPG; hace falta agregar `libheif-examples` (o Imagick con HEIC) al `Dockerfile`.
- **Costo:** una llamada por página. El W-2 y los 1099 casi siempre son una página; el 1095-A, una o dos. Se guarda cuántas páginas se mandaron para poder medirlo.
- **Lector manual:** si OpenAI falla o la lectura está apagada, el preparador escribe los datos en el visor.
- **§7216 (L2), decisión de Steven (3 nov.):** la lectura automática corre para **todos** los usuarios. Queda la bandera `tax.lectura_automatica`, prendida por defecto, para apagarla sin deploy si el abogado responde otra cosa en L2. Con la bandera apagada, el preparador escribe los datos en el visor.

**Pruebas:**
- Con OpenAI simulado (sin red), un W-2 de muestra devuelve sus casillas con confianza y evidencia.
- En un PDF con texto, el valor encontrado da 0,99 y su recuadro.
- Un valor "dudoso" da 0,60 y abre R-03.
- Un archivo ilegible devuelve error.
- Con la bandera apagada no se llama a OpenAI.

**Cómo quedó 4.3a, páginas como imagen (3 nov., Sonnet 5.5):**
- `App\Services\Lectura\PaginasDocumento`: `generar(Documento, DocumentoLectura)` convierte el archivo en un PNG por página, cifrado en `lecturas/{lectura}/pagina-{n}.png` del mismo disco que los archivos, y anota las páginas en la lectura; `leer(lectura, pagina)` lo devuelve descifrado.
- El tipo se decide por el contenido (`finfo`), no por la extensión. PDF con `pdftoppm` a 150 dpi (máximo 10 páginas; más es error); JPG y PNG por GD; HEIC con `heif-convert` y después como JPG.
- Las imágenes se enderezan con el EXIF (las fotos de celular verticales) y se reducen a 2400 px el lado más largo.
- Los errores son claros y no copian el contenido del archivo. Los temporales se borran siempre. Releer deja solo las páginas de la última vez.
- **Dockerfile:** el GD de producción no tenía soporte de JPEG (verificado). Se agregó `libjpeg-dev` con `docker-php-ext-configure gd --with-jpeg` y `libheif-examples` (`heif-convert`). **No se pudo construir la imagen en la máquina de desarrollo (sin Docker): revisar el primer despliegue.**
- Pruebas (`PaginasDocumentoTest`, 11): PDF de 2 páginas, nada en claro en el disco, JPG y PNG, reducción, EXIF, PDF de 12 páginas, archivo dañado, HEIC sin `heif-convert`, releer y página inexistente. La conversión real de HEIC queda saltada hasta que haya `heif-convert` y un `tests/Fixtures/lectura/foto.heic`. Suite: 682 pruebas, 679 pasan y 3 se saltan.

## 4.4 Leer al subir

**Modelo: Opus 5.5** (colas y estados; reglas `arch-queue-routing`, `arch-event-driven`).

**Enfoque:**
- Evento `ArchivoDocumentoSubido` (desde `DocumentosEntrevista::subir`) → job `LeerDocumentoJob` en su propia cola (`lectura`), con reintentos y espera progresiva. Corre en el worker; el contenedor web no llama al proveedor.
- Al terminar, el ítem pasa de `subido` a `leido` y corre las reglas (4.5). Si falla después de los reintentos, pasa a `no_se_pudo_leer` y se avisa al preparador. Nunca queda "subido" sin datos ni aviso (6.1.5).
- Si el tipo o el año detectado no coinciden con el ítem (subió un 1099 en la fila del W-2, o el W-2 de 2024), sale la excepción R-06 o una de tipo distinto, sin descartar lo leído.
- Varios archivos en un ítem (por ejemplo, las dos caras): se leen todos y el visor los muestra juntos.
- Comando `documentos:leer --entrevista= --solo-prueba` para releer lo ya subido.

**Pruebas:** subir encola la lectura; éxito → `leido` y reglas corridas; falla → `no_se_pudo_leer`; releer no duplica datos.

## 4.5 Motor de reglas

**Modelo: Opus 5.5** (lógica fiscal y referencias del IRS).

**Enfoque:**
- La regla sigue siendo **dato** en el catálogo (código, severidad, descripción, fuente y año). Lo que hace cada una vive en una clase por código (`Reglas\R04W2Topes`…). Sus números (el tope del Seguro Social, el exceso retenido, el umbral de confianza) salen de los **parámetros** del año, no del código. Cambiar de año no toca el código.
- Corre después de cada lectura, cada corrección y cada cambio de respuesta (desde `RespuestaGuardador`). Abre o cierra excepciones, sin duplicarlas.
- **En esta fase** (decisión de Steven, 2 nov., después de revisar cada regla contra los documentos que se leen):

| Regla | Qué mira | Detalle |
| --- | --- | --- |
| R-01 | El SSN del documento coincide con el de la persona de la entrevista | W-2 casilla a, 1099, SSA-1099 y 1095-A parte II. Las copias del cliente pueden traer el SSN recortado (***-**-1234): se comparan los últimos 4 dígitos. El SSN también dice de quién es cada W-2 (contribuyente o cónyuge) |
| R-03 | Casilla clave con confianza < 0,90 | — |
| R-04 | W-2: casilla 2 ≤ casilla 1 y casilla 3 + casilla 7 ≤ tope del Seguro Social | El tope sale de los parámetros del año |
| R-05 | Casilla 4 de dos o más W-2 que suma más del máximo | Se suma **por persona**, no por declaración |
| R-06 | Año y tipo del documento | — |
| R-07 | Archivo repetido | Con `DocumentoDuplicadoService` (hash) |
| R-08 | Lo respondido contra lo leído | (a) W-2 con casilla 10 y E6 distinto de "sí"; (b) más W-2 que los empleos de D1.1; (c) persona cubierta del 1095-A que no está en la entrevista |
| R-09 | 1095-A con columna C > 0 → Form 8962 obligatorio | F4 = "sí" ya activa el 8962; la regla dice si es obligatorio (hubo adelanto) u opcional |
| R-11 | Posible hijo para el crédito por hijos sin SSN válido para trabajar | **Solo aviso (revisión)**, con las respuestas: menor de 17, parentesco de hijo y tipo de número de C. Pasa a bloqueante en F6, cuando se calcula el 8812 |

- **Fuera:**
  - R-02 es del expediente (F5); hoy lo cubre `ChecklistDocumentos::completo()`.
  - R-10 pasa a F6: el total del Schedule C (1099 más registros propios) se arma ahí.
  - R-12 pasa a F6 con el Form 2441: el SSN o EIN del proveedor viene en el recibo de cuidado, que no se lee en F4.
  - R-13 necesita la declaración anterior (F7).
- El preparador no puede validar un documento con una excepción **bloqueante** abierta.

**Pruebas:** un caso por regla con los "errores de prueba" de 9.5 (y el SSN recortado de R-01, y R-05 con dos cónyuges) (W-2 con casilla 2 mayor que la 1, W-2 de 2024, 1095-A con adelanto, casilla 10 sin guardería); corregir el dato cierra la excepción; cambiar la respuesta la vuelve a evaluar.

## 4.6 Visor de documento

**Modelo: Sonnet 5.5** (pantalla) **+ Opus 5.5** (corrección, reapertura y permisos).

**Enfoque:**
- En la ficha, cada documento leído tiene "Ver datos". Abre una página con la imagen de la página a la izquierda y los datos por casilla a la derecha (confianza, excepción si la hay). Al tocar un dato se resalta su recuadro (SVG sobre la imagen) cuando lo hay (PDF con texto); en una foto se muestra la página y el texto que leyó el modelo.
- Las imágenes se sirven por una ruta firmada y autorizada (`DocumentoPolicy`), descifradas al vuelo, nunca en `public/`.
- El preparador corrige un dato con motivo (FormRequest, `controller-form-requests`): se guarda el historial y se corren las reglas. Si el documento estaba validado, vuelve a revisión (6.4.3).
- Lo sensible (SSN, EIN) sale enmascarado y se revela con contraseña, como en F2.9.
- En celular, la imagen pasa arriba y los datos abajo.

**Pruebas:** solo el preparador del cliente (o el administrador) ve el visor y las imágenes; corregir guarda el historial y reabre lo validado; lo sensible no viaja en claro en las props.

## 4.7 Cola de excepciones

**Modelo: Opus 5.5** (las tres salidas y su efecto) **+ Sonnet 5.5** (pantalla).

**Enfoque:**
- Menú "Excepciones" (o pestaña de la lista de clientes): lista de los clientes del preparador, ordenada por severidad y por la fecha de entrega, con filtros. Cada fila lleva al visor del dato.
- Tres salidas:
  - **Corregir:** abre el visor.
  - **Aceptar con nota:** cierra la excepción y queda en la bitácora.
  - **Consultar al cliente:** reutiliza la observación de 3.4 (el ítem pasa a "con observación"). El cliente la ve en "Mis documentos" y el agente se la explica (3.5). Al responder, vuelve a la cola.
- La ficha del cliente muestra cuántas excepciones abiertas tiene.

**Pruebas:** cada salida cambia el estado y deja registro; consultar al cliente se ve en el portal y en `consultar_entrevista`; el preparador ve solo sus clientes.

## 4.8 Lo que ve el cliente

**Modelo: Sonnet 5.5.**

**Enfoque:**
- En "Mis documentos", "Leído" se muestra como "Lo estamos revisando". "No se pudo leer" pide subirlo de nuevo, más nítido o en PDF, con un ejemplo. El cliente nunca ve valores leídos ni confianza.
- **Imagen de ejemplo de cada documento** (decisión 3 de F3): una miniatura del formulario oficial del IRS (dominio público), sin datos, para que sepa qué buscar. Campo `imagen_ejemplo` en el catálogo.
- El agente: si un documento no se pudo leer, se lo dice y manda el enlace.

**Pruebas:** el cliente no recibe datos leídos en las props; la imagen de ejemplo aparece solo donde existe.

---

## Reglas de interfaz comunes

Las mismas de F3: carga con skeleton, responsive, una acción principal por pantalla, `components/ui`, accesibilidad (el recuadro resaltado también se anuncia con texto) y estados vacío, error y éxito. El visor es la pantalla donde el preparador pasa más tiempo (6.3): debe cargar rápido (imágenes por página, perezosas) y funcionar con teclado (subir y bajar entre datos).

## Migración de datos existentes

No hay datos leídos que migrar. Los archivos ya subidos se pueden leer con `documentos:leer --solo-prueba` cuando 4.4 esté en producción (con autorización).

## Despliegue

Como en F3: push a `main`, revisar las migraciones, recargar el catálogo cuando cambie (4.1 y 4.8) y crear el borrador de prompts si cambian (4.8). Para 4.3, además:
- cargar las credenciales del proveedor en Dokploy;
- confirmar que el worker escucha la cola `lectura`;
- rehacer la imagen de Docker con HEIC.

## Listo cuando

- Un W-2 y un 1095-A de prueba se leen con valor, confianza y evidencia por casilla (recuadro exacto si el PDF tiene texto).
- Las reglas detectan los errores de prueba y la cola los muestra.
- El preparador corrige en el visor, acepta con nota o consulta al cliente, y todo queda registrado.
- Un documento ilegible pasa a "No se pudo leer" con aviso.
- Ningún dato leído sale del portal ni de la ficha, y con la bandera apagada no se llama a OpenAI.

## Fuera de esta fase

- R-02 y el expediente completo (F5).
- R-10, R-11 como bloqueante y R-12 (F6).
- R-13 y la comparación con el año anterior (F7).
- La transcripción del IRS (Form 8821), en F7.
- Leer Excel o CSV de registros propios.
- Usar los datos leídos para llenar el 1040 (F6). En F4 quedan listos y validados para eso.

## Preguntas para Steven

1. ~~Lector (T-c).~~ Decidido: OpenAI con el modelo actual.
2. ~~Umbral de confianza (T-d).~~ Decidido: 0,90.
3. ~~Clientes reales y L2.~~ Decidido: la lectura corre para todos (3 nov.), con la bandera para apagarla.
4. ~~Reglas de esta fase.~~ Decidido: ver 4.5 y la decisión 5.
5. ~~Documentos que se leen en F4.~~ Decidido: la lista recomendada.

## Decisiones de Steven (2 nov.)

1. **Lector:** OpenAI con el modelo que ya usa la plataforma (`OPENAI_VISION_MODEL`). Azure no se puede usar por ahora. La confianza y la evidencia se calculan como dice 4.3.
2. **Todos los usuarios (3 nov., reemplaza la del 2 nov.):** la lectura automática corre para todos, no solo para los de prueba. Steven conoce el riesgo de L2 (mandar documentos de clientes reales a OpenAI sin la respuesta del abogado). La bandera `tax.lectura_automatica` queda prendida por defecto para apagarla sin deploy.
3. **Umbral de confianza:** 0,90, guardado como parámetro del año.
4. **Documentos que se leen en F4 (21; ampliado el 3 nov.):** W-2, 1095-A, 1099-NEC, 1099-K, 1099-MISC, 1099-INT, 1099-DIV, 1099-R, SSA-1099, 1099-G, 1098, 1098-E, 1098-T, 1099-S, 1099-C, 1099-SA, 5498-SA, W-2G, 5498 (estado de cuenta de la IRA) y 1099-Q, más la **tarjeta del Seguro Social** (`tarjeta-ssn`: nombre, número y si dice "no válida para trabajar"; la entrevista solo guarda el número del cónyuge, y la R-01 y el 1040 necesitan el de cada persona). El preparador escribe a mano los largos (1099-B, 1099-DA, K-1, declaración anterior, cartas del IRS), los que no tienen formato del IRS (recibos y registros propios) y los de identidad.
5. **Reglas:** F4 corre R-01 y R-03 a R-09, y R-11 como aviso. R-02 va en F5; R-10, R-11 como bloqueante y R-12, en F6; R-13, en F7.
