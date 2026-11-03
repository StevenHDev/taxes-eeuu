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

**Cómo quedó 4.3b, esquema y llamada a OpenAI (3 nov., Sonnet 5.5):**
- `App\Services\Lectura\EsquemaLectura`: `casillas()`, `documentosLeibles()`, `para(codigo, anio)` (JSON Schema estricto) e `instrucciones(codigo, anio)`. Todo sale de las casillas del catálogo del año (2026 toma las suyas, por ejemplo la `1a` del 1099-NEC).
- Por cada casilla el modelo contesta `valor` (texto o nulo), `legibilidad` (`claro`, `dudoso`, `no_se_ve`), `pagina` y `texto_cercano`. El formato del valor lo dice la descripción según el `tipo_dato` (montos sin símbolo ni separadores, fechas `AAAA-MM-DD`, SSN como está impreso).
- `tipo_documento` es una **lista cerrada** con los códigos de los 21 documentos que se leen más `desconocido`, para que la R-06 compare códigos sin interpretar texto libre.
- `OpenAiClient::leerConEsquema()` (método nuevo; los demás no cambian): modelo `OPENAI_VISION_MODEL`, instrucciones como mensaje de sistema, una imagen por página con `detail: high`, `response_format` estricto. Tiempo de espera `services.openai.vision_timeout` (90 s por defecto en el código; no está en `config/services.php`).
- Los errores dicen el estado HTTP y el código de error, nunca el cuerpo ni la respuesta, que pueden traer datos del cliente. También falla con un mensaje claro si la respuesta se cortó, fue rechazada o no es JSON.
- Detalle de PHP cubierto con una prueba: las casillas con nombre numérico (`1`, `2`) se vuelven enteros como llaves; `required` se fuerza a textos y se verifica que los 21 documentos se codifican como objeto.
- Pruebas (`EsquemaLecturaTest`, 11). Suite: 693 pruebas, 690 pasan y 3 se saltan.

**Cómo quedó 4.3c, confianza y evidencia (3 nov., Sonnet 5.5):**
- `App\Services\Lectura\CalculadoraConfianza::calcular(casilla, valor, legibilidad, estaEnTextoDelPdf)`: sin valor o `no_se_ve` → 0; formato incorrecto → 0,60; valor en la capa de texto del PDF → 0,99; `claro` con buen formato → 0,90; `dudoso` → 0,60. El orden importa: **el formato se revisa antes que la capa de texto**, así un monto "12.3.4" nunca pasa de 0,60 aunque el PDF lo contenga. `formatoValido(tipo, valor)` queda público para quien lo necesite.
- Formatos: `monto` decimal con hasta 2 decimales (sin comas ni símbolo); `ssn` de 9 dígitos con o sin guiones o truncado (`XXX-XX-1234`, `***-**-1234`); `ein` `NN-NNNNNNN`; `tin` SSN, EIN o cualquiera de los dos truncado; `anio` entre 2000 y el año siguiente; `fecha` válida `AAAA-MM-DD`; `si_no` `si` o `no`; `numero` entero o decimal; el resto, no vacío.
- `App\Services\Lectura\UbicadorEnPdf::buscar(rutaPdf, valor, pagina?, textoCercano?)` devuelve `{pagina, x, y, ancho, alto}` entre 0 y 1 (origen arriba a la izquierda) o `null`. Un monto se compara como número (da igual `45120.00` que `45,120.00`); un texto o un nombre de varias palabras, palabra por palabra sin importar mayúsculas. Si no está en la página que dijo el modelo, busca en las demás. Si el valor sale varias veces (el mismo monto en dos casillas), gana el renglón que tiene el `texto_cercano`.
- **Cambio frente al plan:** las posiciones salen de `pdftotext -bbox-layout` y no de `smalot/pdfparser`. Verificado: `smalot` da `y = 2680` en una hoja de 792 pt (no aplica la transformación de la página) y letra por letra; un recuadro así sería inventado. `pdftotext` es del mismo paquete (`poppler-utils`) que ya necesita `pdftoppm`, no hay dependencia nueva.
- Para no dar un 0,99 por casualidad **no se buscan** los valores de menos de 3 caracteres ni los enteros de menos de 4 dígitos (un "2" o una "D" están en casi cualquier formulario). Un PDF escaneado, una foto o un archivo dañado dan `null`: sin texto no hay recuadro. Sin `pdftotext` instalado lanza un error claro.
- Rendimiento: `pdftotext` corre una sola vez por PDF y por instancia. **Quien lea un documento (4.3d) debe usar la misma instancia de `UbicadorEnPdf` para todas sus casillas.**
- **La confianza se explica (Steven, 3 nov.):** el administrador y el preparador tienen que entender cómo se evalúa. `CalculadoraConfianza::evaluar()` devuelve también el **motivo** (`MotivoConfianza`: en el texto del PDF, claro, dudoso, formato incorrecto, no se ve, sin valor), que se guarda en `documento_datos.motivo_confianza` (migración `2026_11_03_130000`). La tabla explicada sale de `CalculadoraConfianza::tabla()` (la leerá el visor); la regla R-03 del catálogo explica la confianza con los mismos números (una prueba falla si dejan de coincidir); y hay una guía para el equipo en `guia-confianza-de-lectura.md`. El umbral como parámetro editable y las pantallas llegan en 4.5a, 4.6a, 4.6b y 4.7b.
- Pruebas (`ConfianzaTest`, 52 con los casos de formato): cada fila de la tabla, formatos válidos e inválidos, recuadro dentro de la hoja, montos con comas, SSN truncado y EIN, nombre de varias palabras, página equivocada, desempate por texto cercano, valores que no se buscan, PDF solo de imagen y PDF dañado. PDF de prueba `dos-paginas.pdf` ampliado y `solo-imagen.pdf` nuevo. Suite completa al cerrar la 4.3c: 745 pruebas, 741 pasan y 4 se saltan (antes de agregar el motivo).

**Cómo quedó 4.3d, lector y bandera (3 nov., Sonnet 5.5):**
- `LectorDocumentos` (interfaz) con dos lectores y `LecturaResultado` (DTO `readonly`: lector, modelo, tipo y año detectados, páginas y, por casilla, valor, legibilidad, confianza, motivo, página, recuadro y evidencia).
- `LectorOpenAi`: genera las páginas (`PaginasDocumento`), pide las casillas del catálogo con salida estructurada (`EsquemaLectura` + `OpenAiClient::leerConEsquema`) y calcula la confianza y su motivo de cada casilla (`CalculadoraConfianza::evaluar`). En un PDF con texto busca cada valor con **una sola instancia** de `UbicadorEnPdf` (copia temporal descifrada del PDF, que se borra siempre); si está, 0,99 y el recuadro queda para el visor. En fotos, HEIC y escaneados no hay capa de texto. Un documento que no se lee (sin casillas) se manda al lector manual sin llamar a OpenAI. Sin `OPENAI_API_KEY` u `OPENAI_VISION_MODEL` falla antes de llamar, con un mensaje claro.
- Decisión mía (conservadora): si el modelo marca `paginas_legibles = false` (hojas borrosas o cortadas), ninguna casilla se da por `claro`: pasa a `dudoso` (0,60) y va a revisión.
- `LectorManual`: no lee nada ni llama a ningún servicio; el preparador escribe los datos en el visor.
- La bandera `tax.lectura_automatica` (`LECTURA_AUTOMATICA`, prendida por defecto, para todos los usuarios) decide cuál se enlaza a `LectorDocumentos`. Se evalúa al resolver, no al arrancar: se apaga con la variable de entorno sin tocar código.
- `DatosDocumento::guardarLectura(lectura, resultado)`: en una transacción crea un dato por casilla (también las que no se leyeron, con 0 y motivo "sin valor"), con el valor leído que no se pisa, y deja la lectura `leida`. Solo acepta lecturas `en_cola`: releer es otra lectura y la anterior no se toca. `DatosDocumento::fallar(lectura, error)` la deja `fallida`; de un error de nuestros lectores guarda su mensaje (no lleva datos del cliente) y de cualquier otro solo la clase.
- **Pendiente para la 4.4a:** quien orqueste (el job) debe llamar a `leer` → `guardarLectura`, o a `fallar` si lanza una excepción; hoy lo hace solo la prueba.
- Pruebas (`LectorTest`, 13): W-2 en PDF con confianza, motivo y evidencia; cifrado en la base; lo que se manda a OpenAI; foto sin capa de texto; hojas no legibles; tipo detectado distinto; bandera apagada sin ninguna llamada HTTP; documento fuera de la lista; error HTTP sin datos del cliente; sin configuración; releer.

## 4.4 Leer al subir

**Modelo: Opus 5.5** (colas y estados; reglas `arch-queue-routing`, `arch-event-driven`).

**Enfoque:**
- Evento `ArchivoDocumentoSubido` (desde `DocumentosEntrevista::subir`) → job `LeerDocumentoJob` en su propia cola (`lectura`), con reintentos y espera progresiva. Corre en el worker; el contenedor web no llama al proveedor.
- Al terminar, el ítem pasa de `subido` a `leido` y corre las reglas (4.5). Si falla después de los reintentos, pasa a `no_se_pudo_leer` y se avisa al preparador. Nunca queda "subido" sin datos ni aviso (6.1.5).
- Si el tipo o el año detectado no coinciden con el ítem (subió un 1099 en la fila del W-2, o el W-2 de 2024), sale la excepción R-06 o una de tipo distinto, sin descartar lo leído.
- Varios archivos en un ítem (por ejemplo, las dos caras): se leen todos y el visor los muestra juntos.
- Comando `documentos:leer --entrevista= --solo-prueba` para releer lo ya subido.

**Pruebas:** subir encola la lectura; éxito → `leido` y reglas corridas; falla → `no_se_pudo_leer`; releer no duplica datos.

**Cómo quedó 4.4a, leer al subir (3 nov., Sonnet 5.5):**
- `DocumentosEntrevista::subir()` dispara `ArchivoDocumentoSubido`; el listener `EncolarLecturaDeDocumento` (Laravel lo registra solo por el tipo del evento: registrarlo a mano lo duplicaba y encolaba dos veces) encola `LeerDocumentoJob`. El contenedor web nunca llama a OpenAI.
- `LeerDocumentoJob` (cola `default`, la que escucha el worker de producción; 3 intentos con espera de 60 y 300 s, tiempo máximo 240 s, `afterCommit`): crea la lectura, la hace `LectorDocumentos`, guarda con `DatosDocumento::guardarLectura` y llama a `ChecklistDocumentos::leido()`. Si lanza una excepción, esa lectura queda `fallida` (`fallar`) y la cola reintenta; cada intento es una lectura. Al agotar los intentos, `failed()` llama a `ChecklistDocumentos::noSePudoLeer()`.
- Estados: `leido()` solo cambia un ítem `subido` o `no_se_pudo_leer` (una relectura buena lo recupera); `noSePudoLeer()` solo desde `subido`. Un ítem validado u observado no se toca, aunque igual se lean y se guarden los datos.
- **No se lee lo que no se lee solo:** con la bandera apagada o con un documento sin casillas, el job no crea lectura ni llama a OpenAI y el ítem sigue `subido`: el preparador escribe los datos en el visor. Así no queda una lectura "terminada" vacía que impida leerlo después.
- Comando `documentos:leer {--entrevista=} {--solo-prueba} {--dry-run}`: encola los archivos vigentes de documentos que se leen y que no tienen una lectura `leida`. Con la bandera apagada no encola nada. Solo muestra cantidades.
- **Pruebas:** como las pruebas usan la cola `sync`, subir un archivo corría la lectura de verdad. Se agregó `LECTURA_AUTOMATICA=false` en `phpunit.xml` (como `QUEUE_CONNECTION`); las pruebas de lectura la prenden y simulan la cola o OpenAI.
- Pruebas (`LeerAlSubirTest`, 11). Suite: 772 pruebas, 768 pasan y 4 se saltan.
- Para el comando `php artisan test` con el filtro de salida `pao` a veces no imprime nada: `PAO_DISABLE=1 php artisan test` muestra el error real.
- **Pendiente para la 4.5a:** el job no corre las reglas todavía (las pone el motor de la 4.5a al terminar la lectura).

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

**Cómo quedó 4.5a, motor y reglas de un documento (3 nov., Sonnet 5.5):**
- `App\Services\Lectura\Reglas\`: `Regla` (interfaz: `codigo()` y `evaluar(Entrevista, datosPorItem)`), `Hallazgo` (clave única, ítem, dato y detalle sin valores en claro) y `MotorReglas::correr(Entrevista)`. Corre solo las reglas **activas e `implementada`** del catálogo del año, con la severidad del catálogo. Por hallazgo hay una sola excepción (`entrevista_id` + `clave`): nueva → abierta; corregida que vuelve a fallar → se reabre; aceptada → no se toca; abierta cuyo hallazgo ya no aparece → corregida. Corre en una transacción con la entrevista bloqueada.
- Cuándo corre: `LeerDocumentoJob` después de `leido()`, y `DatosDocumento::corregir()` al terminar (el motor se pide con `app()` ahí porque él mismo usa `DatosDocumento`; inyectarlo en el constructor haría un ciclo).
- **R-03** `R03Confianza`: casilla `clave` con confianza < parámetro `lectura.umbral_confianza` (0,90, grupo "Topes y umbrales", con su explicación; el administrador lo cambia en Catálogo fiscal → Parámetros). No cuenta lo que el preparador corrigió. El detalle lleva el `motivo_confianza`, la confianza y el umbral, nunca el valor. **Decisión mía:** una casilla **vacía que el lector vio con claridad** (sin valor y `claro`: la 7 y la 10 de casi todo W-2) no es una duda y no se marca; una que no pudo ver o que ni mencionó, sí. Sin esto, casi todo W-2 traería excepciones inútiles.
- **R-04** `R04TopesW2`: en un W-2, casilla 2 ≤ casilla 1 y casilla 3 + casilla 7 ≤ tope (`self_employment_tax.tope_salario_social_security`; si el año no lo tiene, solo se revisa lo primero). Usa el valor vigente, el corregido. Texto de la regla actualizado.
- **R-06** `R06AnioYTipo`: año o tipo detectados por el lector distintos del año de la entrevista o del ítem; lo leído se conserva. El detalle lleva solo los códigos y el año.
- **R-07** `R07Repetido`: coincidencias de `DocumentoDuplicadoService`; el detalle dice cuántas y si alguna es de otro cliente, no de quién.
- **R-09** `R09Adelanto8962`: 1095-A con alguna casilla `21-C` a `32-C` o `33-C` mayor que cero. **Cambio de severidad:** en el catálogo estaba como `bloqueante`; la dejé `informativa` porque la subtarea dice que "informa" (el 8962 ya lo activa la respuesta de F4) y una bloqueante impediría validar todo 1095-A con adelanto, que es lo normal. Steven puede volverla bloqueante en el catálogo.
- `ChecklistDocumentos::validar()` no deja validar un ítem con una excepción **bloqueante abierta** (abierta o consultada): `TransicionNoPermitida`. Aceptarla con nota o corregir el dato la libera.
- Catálogo: R-03, R-04, R-06, R-07 y R-09 con `implementada = true`; parámetro nuevo `lectura.umbral_confianza`. No hizo falta tocar `parametros-metadatos.php` (esos son los parámetros sembrados; el nuevo viene con sus metadatos en `parametros.php`).
- Pruebas (`ReglasDocumentoTest`, 18): un caso por regla (casilla 2 sobre la 1, 3 + 7 sobre el tope, W-2 de 2024 y tipo distinto, archivo repetido de otro cliente, 1095-A con y sin adelanto, dato clave con 0,60), umbral editable, casilla vacía, correr dos veces no duplica, corregir cierra y reabre, aceptada intacta, validar bloqueado, detalle sin montos ni SSN y regla no implementada que no corre.
- **Para producción:** recargar el catálogo (reglas `implementada`, R-04 y R-09 con su texto nuevo y el parámetro del umbral) con autorización.

**Cómo quedó 4.5b, reglas que cruzan datos (3 nov., Sonnet 5.5, en paralelo con 4.6a y 4.7a):**
- `R01Ssn` (bloqueante): compara solo los **últimos 4 dígitos** del SSN de cada documento (`a` del W-2, `receptor-tin` de los 1099 y del SSA-1099, `5` y `8` del 1095-A) contra los números que la entrevista conoce: la casilla `ssn` de cada `tarjeta-ssn` leída y B4.3 (cónyuge, descifrado dentro de la regla). No abre nada sin números conocidos, ni si está casado y falta el del cónyuge. Un TIN con forma de EIN no se compara. **Cambio frente a la subtarea:** `16-B` a `20-B` del 1095-A (personas cubiertas) quedaron solo en R-08 (c), no en R-01: una persona cubierta que no va en la declaración es legítima y R-01 la bloquearía sin motivo; dejarlas en las dos reglas abriría dos excepciones por el mismo dato.
- `R05ExcesoSeguroSocial` (informativa): agrupa los W-2 por los últimos 4 de la casilla `a` (por persona); dos o más W-2 de una persona con la casilla 4 sobre el máximo abren la excepción; cada cónyuge cuenta aparte. Parámetro nuevo `self_employment_tax.maximo_seguro_social_retenido_por_persona` (USD 10.918,20 en 2025); sin él la regla no corre.
- `R08RespuestasContraDocumentos` (revisión): (a) W-2 con casilla 10 > 0 y E6 distinto de `si`; (b) más W-2 leídos que los empleos de D1.1; (c) persona cubierta del 1095-A cuyos últimos 4 no están en la entrevista (solo con todas las tarjetas leídas y el número del cónyuge).
- `R11HijoSinSsn` (**solo aviso**, revisión en F4; bloqueante en F6): persona de C con parentesco de hijo, hijastro o hijo de crianza, menor de 17 el 31 de diciembre y C9 distinto de `ssn_valido`.
- `RespuestaGuardador::guardar` corre el motor (con `app()`, sin ciclos) cuando hay datos leídos en la entrevista o la respuesta es C2, C3 o C9; así cambiar E6 a `si` cierra la excepción de R-08 (a). Ninguna excepción guarda un SSN, monto o nombre.
- Pruebas (`ReglasCruzadasTest`, 14). **Producción:** la carga del catálogo trae el parámetro nuevo.

**Cómo quedó 4.6a, visor de documento, servidor (3 nov., Sonnet 5.5):**
- Rutas bajo `clientes/{cliente}/documentos/{item}/visor`: la página Inertia `clientes/visor` (`clientes.documentos.visor`), la imagen de cada hoja (`.pagina`, PNG descifrado al vuelo, `Cache-Control: private, no-store`), `POST .../datos/{dato}/revelar` (`RequirePassword`, `throttle:10,1`) y `PATCH .../datos/{dato}` (corregir, `CorregirDatoRequest`: valor máx. 255, motivo requerido máx. 500).
- Permisos con `ClientePolicy`; un ítem, lectura o dato de otro cliente da 404; la página debe existir. El dato debe estar entre los vigentes.
- Props: `cliente`, `item`, `archivos` (URL firmada), `lecturas` (última terminada por archivo, con las URL de sus imágenes), `datos` (casilla, descripción del catálogo, `tipo_dato`, `clave`, `sensible`, `confianza`, `motivo_confianza` con título y explicación, legibilidad, página, recuadro, origen, quién corrigió, `excepciones` abiertas con `detalle`), `confianza_tabla` y `umbral`. **Lo sensible va `null`** (valor y evidencia) y se pide con `revelar`; una casilla que el catálogo ya no conoce se trata como sensible.
- **Pendiente:** revelar deja registro en la bitácora con acción `Actualizado` y la etiqueta `revelado` (sin el valor); lo ideal es una acción `Revelado` propia o una tabla de revelados como en F2.9, que no estaba en los archivos permitidos.
- Pruebas (`VisorTest`, 10).

**Cómo quedó 4.7a, cola de excepciones, servidor (3 nov., Sonnet 5.5):**
- `ColaExcepciones`, `ExcepcionController`, `ResolverExcepcionRequest`. Rutas `GET /excepciones` (`clientes.excepciones.index`, página `excepciones/index`), `POST /excepciones/{excepcion}/aceptar` (nota requerida) y `.../consultar` (texto requerido; usa `ChecklistDocumentos::observar` y deja la excepción `consultada`, todo o nada). El prefijo es `/excepciones` y no `/clientes/excepciones` porque `clientes/{cliente}` se lo comería.
- La lista: abiertas y consultadas de los clientes del preparador (el administrador ve todas), por severidad, fecha e id, con filtros por severidad, estado y cliente. Por fila: regla, severidad, estado, cliente, ítem, casilla, `motivo_confianza` (solo R-03) y enlaces (`corregir` apunta al visor). Nunca valores.
- La ficha trae `excepciones_abiertas: {total, bloqueantes}` (`VistaEntrevistaPreparador`). La bitácora la cubre el `AuditoriaObserver` sin valores.
- Pruebas (`ColaExcepcionesTest`, 10). Suite completa con 4.5b, 4.6a y 4.7a: 826 pruebas, 822 pasan y 4 se saltan.

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

**Cómo quedó 4.8b, imagen de ejemplo (3 nov., Sonnet 5.5, en paralelo con la 4.5a):**
- Columna `catalogo_documentos.imagen_ejemplo` (migración `2026_11_03_140000`), en `CLAVES` del importador y en `documentos.php` de 2025 (el copiador la lleva a 2026). 19 documentos con imagen: w2, 1095-a, 1099-nec, 1099-k, 1099-misc, 1099-int, 1099-div, 1099-r, 1099-g, 1098, 1098-e, 1098-t, 1099-s, 1099-c, 1099-sa, 5498-sa, w2g, estado-cuenta-ira (formulario 5498) y 1099-q. Sin imagen: `ssa-1099` y `tarjeta-ssn` (no son del IRS) y los que no son formularios. `1099-b`, `1099-da` y `k-1` son formularios pero no están en la lista de la fase 4.
- Las imágenes salen de los PDF vacíos de irs.gov: la **Copia B** (la del contribuyente) recortada, 600 px de ancho y menos de 60 KB cada una (64 colores), en `public/ejemplos-documentos/{codigo}.png`. W-2, 1095-A y 1098-T muestran "2026" impreso porque es el único formulario vacío que publica el IRS; no cambia qué buscar.
- `DocumentosEntrevista::lista()` devuelve la URL (`asset()`) o null. `documento-fila.tsx` muestra la miniatura solo si existe, como botón con foco visible, tamaño fijo y `loading="lazy"`; al tocarla abre el `Dialog` con la imagen grande. Textos en `es.json` y `en.json`.
- **No se vio en el navegador:** el diseño de la miniatura está sin revisar visualmente.
- Pruebas: cada ruta del catálogo existe en `public/`, solo documentos con formulario oficial la traen, y `lista()` la devuelve donde corresponde. Suite completa con 4.5a y 4.8b: 792 pruebas, 788 pasan y 4 se saltan; `tsc` y pint sin errores.

**Cómo quedó 4.6b, 4.7b y 4.8a, las pantallas (3 nov., Sonnet 5.5, en paralelo; revisadas en el navegador en escritorio y celular):**
- **Visor** (`pages/clientes/visor.tsx`, `components/visor/`, `types/lectura.ts`): imagen de la hoja con selector Anterior/Siguiente y el recuadro SVG (0 a 1) sobre el valor; lista de datos con casilla, descripción, valor, confianza (ámbar bajo el umbral) con la insignia del motivo y su explicación, excepciones, "Revelar" y "Corregir" (diálogo con valor y motivo, cada uno con su error). Panel "¿Cómo se calcula la confianza?" con la tabla y el umbral, solo con textos de las props. Teclado con flechas y anuncio para lectores de pantalla. En celular, imagen arriba y datos abajo. Botón "Ver datos" en cada documento leído, no leído o validado de la ficha.
- **Cola de excepciones** (`pages/excepciones/index.tsx`, `components/excepciones/`, `types/excepciones.ts`, ítem "Excepciones" del menú): tabla con cliente, documento, regla (primera frase; la descripción completa, en el título), severidad, fecha y acciones (Corregir lleva al visor; Aceptar con nota y Consultar al cliente abren un diálogo); R-03 muestra el motivo de la confianza en palabras con su explicación en un tooltip; filtros por severidad, estado y cliente; vacío "No hay excepciones abiertas". La ficha muestra "N excepciones abiertas · M bloqueantes".
- **Cliente y agente:** `leido` se ve "Lo estamos revisando" y `no_se_pudo_leer` "No pudimos leerlo" con la nota "Súbelo de nuevo, más nítido o en PDF" y "Subir de nuevo" como única acción. **`no_se_pudo_leer` ahora cuenta como pendiente para el cliente** (`resueltoParaElCliente`): si no lo vuelve a subir, le salen "Faltan N" y el agente lo menciona; el preparador lo sigue viendo en "por revisar". `consultar_entrevista` trae por ese ítem `no_se_pudo_leer` y `aviso_no_se_pudo_leer`, y el contador `documentos.no_se_pudo_leer`; una línea nueva en `seguimiento.md` y `portal_dudas.md` (**falta crear el borrador de prompts después del deploy, con autorización**). Una prueba garantiza que las props del portal no traen datos leídos.
- **Arreglos de la revisión visual:** la tabla de excepciones desbordaba con la descripción larga de R-03; el visor salía en blanco porque el `detalle` de la excepción (un objeto) se pintaba como texto; el preparador veía "Lo estamos revisando" (ahora ve "Leído" o "No se pudo leer", `vista="preparador"` en `DocumentoEstado`); y el error de validación decía "motive" (`CorregirDatoRequest::attributes()`).
- **Pendiente, no resuelto:** en el visor la hoja no queda fija al bajar por una lista larga de datos (probablemente un `overflow` del contenedor del layout rompe el `sticky`); "Revelar" no se probó en el navegador porque pide contraseña. Suite completa: 829 pruebas, 825 pasan y 4 se saltan.

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
