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
| **0,95** | Está en el texto del PDF, pero se repite en la página | Solo en los movimientos de un extracto o recibo (renglones). El monto está en el texto de esa página, pero otro movimiento de la misma página tiene el mismo valor: no se puede comprobar cuál de los dos es. |
| **0,90** | Leído con claridad | La inteligencia artificial lo leyó con claridad y tiene la forma que corresponde a la casilla. Viene de una imagen (foto o PDF escaneado), así que no se puede comprobar contra el texto del archivo. |
| **0,60** | Leído con dudas | La inteligencia artificial no está segura de algún carácter. |
| **0,60** | Formato incorrecto | Lo leído no tiene la forma de la casilla: por ejemplo, un monto con tres decimales o un SSN de 8 dígitos. Casi seguro se leyó mal. |
| **0,60** | Categoría propuesta por la IA | Solo en renglones, para la categoría (el monto tiene su propia confianza). La línea del Schedule C no está escrita en el documento: la eligió la inteligencia artificial. |
| **0,60** | Sin categoría | Solo en renglones, para la categoría. La inteligencia artificial no supo a qué línea del Schedule C va el movimiento; mientras no tenga categoría, no suma. |
| **0** | No se ve | La casilla está borrosa, cortada o tapada. |
| **0** | Sin valor | No se leyó nada en esa casilla. |

El orden importa. La plataforma revisa primero si hay valor, después si se ve, después el formato y, solo si todo eso está bien, mira si el valor está en el texto del PDF. Por eso un valor mal formateado **nunca** pasa de 0,60, aunque esté en el PDF.

## Movimientos de extractos y recibos (renglones)

Los registros del negocio (extractos del banco o de la tarjeta, recibos sueltos, fotos y la plantilla en PDF) no tienen casillas fijas: traen una lista de movimientos, y cada movimiento es un **renglón** con fecha, descripción, monto y categoría (la línea del Schedule C). La inteligencia artificial los lee y propone la categoría; la plataforma calcula la confianza de cada renglón así:

1. **El monto** (es la confianza que se guarda con el renglón), con la misma regla de arriba: 0,99 si está en el texto de **esa página** del PDF; 0,95 si está, pero otro movimiento de la misma página tiene el mismo valor; 0,90 si viene de una imagen (foto o escaneado) leída con claridad; 0,60 con dudas o formato incorrecto; 0 si no se ve.
2. **La categoría**, aparte: si el propio documento la dice (la fila de la plantilla, un recibo que dice "Category: Office supplies"), cuenta como leída. Si la eligió la inteligencia artificial, vale 0,60 con el motivo "Categoría propuesta por la IA"; si no tiene categoría, 0,60 con "Sin categoría" (y no suma).
3. **La suma de una categoría** toma la más baja de sus renglones, contando el monto y la categoría de cada uno. Por eso una suma con movimientos clasificados por la IA queda en 0,60 y va a revisión, aunque cada monto esté en el texto del PDF.

El preparador valida **la suma de cada categoría**, no cada renglón: un renglón con la categoría propuesta por la IA lleva su suma a revisión.

**Límites de la lectura con IA** (parámetros del año en **Catálogo fiscal del año → Parámetros**):

- `lectura.maximo_paginas_renglones` (40): un archivo con más páginas no se lee; la excepción R-14 lo dice y al cliente se le pide subirlo por meses. Se lee por bloques de 5 páginas.
- `lectura.maximo_paginas_ia_por_cliente` (120): páginas leídas con IA de los registros de un cliente en el año. Un archivo que haría pasar el tope no se manda a la IA: queda sin leer con la excepción R-14 y el preparador decide (subir el tope o pedir la plantilla).

Las hojas Excel y CSV no usan inteligencia artificial y no cuentan para ninguno de los dos.

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
| Está en el texto del PDF, pero se repite en la página | Nada, normalmente. Si el monto es alto, mirar la página: los dos movimientos iguales están marcados. |
| Categoría propuesta por la IA | Revisar la **suma de la categoría** (no cada renglón): si un movimiento no es de esa línea, cambiarle la categoría o marcarlo personal o transferencia. |
| Sin categoría | Abrir el renglón y decidir: ponerle la línea del Schedule C o marcarlo personal o transferencia. La excepción R-14 avisa cuántos hay por archivo. |
| Leído con claridad | Nada, normalmente. Si el monto es alto o raro, vale la pena mirar el documento. |
| Leído con dudas | Comparar con el documento y corregir si hace falta. |
| Formato incorrecto | Mirar el documento: casi seguro hay que corregir el dato. |
| No se ve / Sin valor | Si el documento trae la casilla, escribir el dato a mano. Si no la trae, dejarla vacía. (Una casilla que la plataforma vio con claridad y está vacía, como la 7 o la 10 de casi todo W-2, no se marca para revisión.) |

Cada corrección queda registrada: quién, cuándo, de qué valor a cuál y por qué. El valor que leyó la inteligencia artificial nunca se borra.

## Dónde se ve esto

- **Administrador:** en **Catálogo fiscal del año → Reglas**, la regla **R-03** tiene la explicación completa. El **umbral (0,90)** se guarda como un parámetro del año en **Catálogo fiscal del año → Parámetros**, y se puede cambiar ahí si después de las primeras semanas vemos que pide demasiado o muy poco. El parámetro ya existe (`lectura.umbral_confianza`) y la regla lo lee de ahí.
- **Preparador:** en el **visor del documento**, cada dato muestra su confianza y su **motivo** en palabras, y hay un botón "¿Cómo se calcula?" con esta misma tabla. *(Llega con la subtarea 4.6b.)*

## Para quien programa

- Los números y los textos salen de un solo lugar: `App\Services\Lectura\CalculadoraConfianza` (el cálculo y `tabla()`) y `App\Enums\Lectura\MotivoConfianza` (el texto para personas). La confianza del monto de un renglón la da `CalculadoraConfianza::evaluarRenglon()` y la de su categoría `evaluarCategoria()`; quien lee los renglones con IA es `LectorOpenAi` (modo lista de `EsquemaLectura`).
- La regla R-03 del catálogo repite los números en su descripción. Una prueba (`ConfianzaTest::test_la_regla_r03_del_catalogo_explica_los_mismos_numeros_que_usa_el_codigo`) falla si el catálogo y el código dejan de coincidir: si cambias un número, cambia también `database/data/catalogo-fiscal/2025/reglas.php` y esta guía.
