# Cómo se evalúa la confianza de lo que lee la plataforma

3 nov. 2026 · Para **preparadores y administradores** de GTS. No hace falta saber de programación.

## Para qué sirve

Cuando el cliente sube un documento (un W-2, un 1099, un 1095-A…), la plataforma lo lee con inteligencia artificial y saca los datos de cada casilla. Cada dato leído trae una **confianza**, un número entre 0 y 1 que dice qué tan seguros estamos de que se leyó bien.

Esa confianza decide **qué tiene que mirar el preparador**: lo que tiene mucha confianza queda listo; lo que tiene poca va a la cola de excepciones. Así el preparador no revisa documento por documento, solo lo dudoso.

## Lo más importante: la confianza no la inventa la inteligencia artificial

La inteligencia artificial no sabe qué tan bien leyó. Por eso **no le creemos un número**: la plataforma calcula la confianza con cosas que sí puede comprobar. Lo único que le pedimos a la inteligencia artificial es que diga, casilla por casilla, si la leyó **con claridad**, **con dudas** o si **no se ve**.

## La regla

| Confianza | Motivo | Qué significa |
| --- | --- | --- |
| **0,99** | Está en el texto del PDF | El mismo valor aparece en el texto que viene escrito dentro del PDF (no en una imagen). Es la lectura más segura. |
| **0,99** | Leído de la hoja de cálculo (sin IA) | El valor salió de una celda de un archivo Excel o CSV, sin inteligencia artificial: es exactamente lo que escribió el cliente. Lo que hay que revisar es la categoría o la columna, no la lectura. |
| **0,90** | Leído con claridad | La inteligencia artificial lo leyó con claridad y tiene la forma que corresponde a la casilla. Viene de una imagen (foto o PDF escaneado), así que no se puede comprobar contra el texto del archivo. |
| **0,60** | Leído con dudas | La inteligencia artificial no está segura de algún carácter. |
| **0,60** | Formato incorrecto | Lo leído no tiene la forma de la casilla: por ejemplo, un monto con tres decimales o un SSN de 8 dígitos. Casi seguro se leyó mal. |
| **0** | No se ve | La casilla está borrosa, cortada o tapada. |
| **0** | Sin valor | No se leyó nada en esa casilla. |

El orden importa. La plataforma revisa primero si hay valor, después si se ve, después el formato y, solo si todo eso está bien, mira si el valor está en el texto del PDF. Por eso un valor mal formateado **nunca** pasa de 0,60, aunque esté en el PDF.

## Cuándo va un dato a la cola de excepciones

La regla **R-03** dice: *un dato de una casilla clave con confianza **menor de 0,90** va a revisión*. Con los números de arriba:

- **No** va a revisión: 0,99 (en el texto del PDF) y 0,90 (leído con claridad). Ojo: el 0,90 **no es menor** de 0,90.
- **Sí** va a revisión: 0,60 (con dudas o formato incorrecto) y 0 (no se ve o sin valor).
- Un dato que el preparador **ya corrigió** no cuenta: se asume que lo revisó una persona.
- Solo cuentan las casillas **clave** (las que alimentan una línea del 1040 o una regla), no todas las del formulario.

## Qué hacer según el motivo

| Motivo | Qué hace el preparador |
| --- | --- |
| Está en el texto del PDF | Nada: ya está comprobado. |
| Leído de la hoja de cálculo (sin IA) | Nada con el valor. Si la plataforma no reconoció una columna o una categoría, aparece la excepción R-14 y se revisa la hoja. |
| Leído con claridad | Nada, normalmente. Si el monto es alto o raro, vale la pena mirar el documento. |
| Leído con dudas | Comparar con el documento y corregir si hace falta. |
| Formato incorrecto | Mirar el documento: casi seguro hay que corregir el dato. |
| No se ve / Sin valor | Si el documento trae la casilla, escribir el dato a mano. Si no la trae, dejarla vacía. (Una casilla que la plataforma vio con claridad y está vacía, como la 7 o la 10 de casi todo W-2, no se marca para revisión.) |

Cada corrección queda registrada: quién, cuándo, de qué valor a cuál y por qué. El valor que leyó la inteligencia artificial nunca se borra.

## Dónde se ve esto

- **Administrador:** en **Catálogo fiscal del año → Reglas**, la regla **R-03** tiene la explicación completa. El **umbral (0,90)** se guarda como un parámetro del año en **Catálogo fiscal del año → Parámetros**, y se puede cambiar ahí si después de las primeras semanas vemos que pide demasiado o muy poco. El parámetro ya existe (`lectura.umbral_confianza`) y la regla lo lee de ahí.
- **Preparador:** en el **visor del documento**, cada dato muestra su confianza y su **motivo** en palabras, y hay un botón "¿Cómo se calcula?" con esta misma tabla. *(Llega con la subtarea 4.6b.)*

## Para quien programa

- Los números y los textos salen de un solo lugar: `App\Services\Lectura\CalculadoraConfianza` (el cálculo y `tabla()`) y `App\Enums\Lectura\MotivoConfianza` (el texto para personas).
- La regla R-03 del catálogo repite los números en su descripción. Una prueba (`ConfianzaTest::test_la_regla_r03_del_catalogo_explica_los_mismos_numeros_que_usa_el_codigo`) falla si el catálogo y el código dejan de coincidir: si cambias un número, cambia también `database/data/catalogo-fiscal/2025/reglas.php` y esta guía.
