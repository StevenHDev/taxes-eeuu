# Datos del catálogo fiscal por año (F1.3)

Contenido del catálogo fiscal, uno por año. Sale del documento madre (`docs/mejora-plataforma/documento-madre.md`, secciones 3, 4, 5.2, 6.2 y 7.7) y se carga con:

```
php artisan catalogo-fiscal:cargar 2025 [--dry-run] [--forzar]
php artisan catalogo-fiscal:copiar 2025 2026
php artisan catalogo-fiscal:cargar 2026        # aplica 2026/ajustes.php sobre la copia
php artisan catalogo-fiscal:verificar 2025
```

Todo se carga como **"sin validar"** (todavía no comparado contra la publicación del IRS). La carga es idempotente y **no pisa** lo que un administrador editó desde la pantalla, salvo con `--forzar`.

## Archivos de un año

Cada archivo devuelve un arreglo PHP plano (`return [ ... ];`). Los códigos son únicos dentro del año y las piezas se apuntan entre sí **por código**, nunca por id.

### `preguntas.php` — pestaña 1 (sección 3, 13614-C)

```php
[
    'codigo' => 'D1',               // A1…G5, C1…C14
    'bloque' => 'D',                // A–G
    'pantalla' => null,             // 1–8 si la sección 3 lo define; si no, null
    'orden' => 1,                   // orden dentro del bloque
    'texto_es' => '¿Recibiste salario como empleado en {anio}? ¿En cuántos empleos?',
    'texto_en' => null,
    'ayuda_es' => null,             // aclaración corta para el cliente, si la hay
    'tipo_respuesta' => 'si_no',    // si_no | usted_conyuge_no | opciones | casillas | dato | fecha | numero
    'opciones' => null,             // lista de ['valor' => 'casado', 'texto_es' => 'Casado'] si es opciones o casillas
    'nivel' => 'entrada',           // entrada | seguimiento (si tiene condición) | no_se_pregunta
    'por_persona' => false,         // true en el bloque C
    'sensible' => false,            // true si solo se responde en el portal, nunca por WhatsApp (SSN, cuentas, montos)
    'condicion' => null,            // cuándo se muestra: ['pregunta' => 'B1', 'es' => 'casado'], ['todas' => [...]], ['alguna' => [...]]
    'efecto_es' => 'Un W-2 por empleo → 1040 líneas 1a y 25a',   // columna "Activa / descarta" o "Va a", tal cual
    'fuente_texto' => '1040 línea 1a: suma de la casilla 1 de todos los W-2',  // columna "Por qué (IRS)"
    'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/i1040gi.pdf',
    'activa' => [                   // lo que abre la respuesta, por código del mismo año
        ['respuesta' => 'si', 'objetivo' => 'documento:w2'],
        ['respuesta' => 'si', 'objetivo' => 'pregunta:D2', 'nota' => 'opcional'],
    ],
]
```

- El año fiscal en los textos va como `{anio}` (se reemplaza al mostrar). Otros años que dependen del año fiscal (por ejemplo "nació antes del 2 de enero de 1961") se dejan escritos en `fuente_texto`/`efecto_es` y se corrigen en los ajustes del año siguiente.
- `respuesta` es el valor que activa: `'si'`, `'usted'`, `'conyuge'`, el `valor` de una opción, o una lista (cualquiera de ellos).
- `objetivo` es `pregunta:CODIGO`, `documento:codigo` o `formulario:codigo`.

### `documentos.php` — pestaña 2 (4.0-A, 4.1–4.7, 5.2)

```php
[
    'codigo' => 'w2',               // minúsculas con guiones: w2, 1099-nec, 1095-a, tarjeta-ssn…
    'grupo' => 'trabajo',           // identidad | trabajo | negocio | jubilacion | inversiones | otros_ingresos | gastos | situaciones | pagos
    'nombre_oficial' => 'Form W-2',
    'nombre_sencillo_es' => 'Comprobante de salario de tu empleador',
    'nombre_sencillo_en' => null,
    'emisor_es' => 'Tu empleador',
    'emisor_en' => null,
    'fecha_llegada' => '2026-02-02',   // ISO si el documento madre la da; si no, null
    'si_no_llega_es' => 'Pídeselo a tu empleador. Igual tienes que declarar ese ingreso.',
    'si_no_llega_en' => null,
    'formatos' => ['pdf', 'jpg', 'png', 'heic'],
    'uno_por' => 'empleo',          // unico | empleo | poliza | persona | fuente
    'orden' => 1,
    'fuente_texto' => 'Instrucciones del 1040 de 2025',
    'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/i1040gi.pdf',
    'casillas' => [                 // solo los que tienen ficha (W-2, 1099-NEC, 1099-K, 1095-A); el resto []
        ['casilla' => '1', 'descripcion' => 'Salarios, propinas y otra compensación', 'tipo_dato' => 'monto', 'clave' => true],
    ],
]
```

### `formularios.php` — pestaña 3 (4.0-B, 4.1–4.7)

```php
[
    'codigo' => '1040',             // 1040, sch-1, sch-2, sch-3, sch-c, sch-se, sch-8812, f2441, sch-eic, f8962, sch-1a, sch-a…
    'nombre' => 'Form 1040',
    'para_que' => 'La declaración ("front")',
    'siempre' => true,              // solo el 1040
    'ficha' => 'completa',          // completa | conector (Schedules 1, 2 y 3) | pendiente
    'orden' => 1,
    'fuente_texto' => 'Form 1040 de 2025',
    'fuente_url' => 'https://www.irs.gov/pub/irs-pdf/f1040.pdf',
    'lineas' => [
        ['linea' => '1a', 'descripcion' => 'Salarios (suma de la casilla 1 de todos los W-2)', 'origen_tipo' => 'casilla', 'origen_clave' => 'w2:1'],
        ['linea' => '8', 'descripcion' => 'Otros ingresos del Schedule 1', 'origen_tipo' => 'linea', 'origen_clave' => 'sch-1:10'],
        ['linea' => '11a', 'descripcion' => 'Ingreso ajustado', 'origen_tipo' => 'calculo', 'origen_clave' => null],
    ],
]
```

- `origen_tipo`: `casilla` (`documento:casilla`), `linea` (`formulario:linea`), `respuesta` (`CODIGO` de pregunta) o `calculo` (sin clave).
- Si una línea suma varias fuentes del mismo tipo, van separadas por `;`: `'1099-nec:1;1099-k:1a'`.
- Los formularios con ficha pendiente se cargan sin líneas.

### `reglas.php` — pestaña 5 (6.2)

```php
['codigo' => 'R-04', 'descripcion' => '…', 'severidad' => 'bloqueante', 'orden' => 4,
 'fuente_texto' => 'Instrucciones del 1040 de 2025', 'fuente_url' => 'https://…'],
```

`severidad`: `bloqueante` | `revision` | `informativa`.

### `parametros.php` — pestaña 4 (7.7), solo lo que no está en `ParametrosFiscalesSeeder`

```php
['categoria' => 'edad', 'clave' => 'nacido_antes_de_mayor_65', 'valor' => '1961-01-02',
 'grupo' => 'deduccion_estandar', 'tipo' => 'fecha', 'nombre_es' => '…', 'formulario_linea' => 'Form 1040, línea 12d',
 'condicion_es' => null, 'fuente_texto' => '…', 'fuente_url' => '…'],
```

Los 31 parámetros de 2025 que ya existen siguen en `ParametrosFiscalesSeeder` (valores) y `2025/parametros-metadatos.php` (grupo, nombre, línea).

## `2026/ajustes.php`

Se aplica después de `catalogo-fiscal:copiar 2025 2026`. Solo lo que el documento madre ya trae de 2026; el resto de los montos queda **pendiente** (sin cifra).

```php
return [
    'parametros' => [
        'dependiente_calificado.limite_ingreso_bruto_pariente_calificado' => 5300,
    ],
    'casillas' => [
        // documento => [casilla vieja => [cambios]]
        '1099-nec' => ['1' => ['casilla' => '1a']],
    ],
    'lineas' => [
        // formulario:linea => [cambios]; p. ej. una línea que lee la casilla renombrada
        'sch-c:1' => ['origen_clave' => '1099-nec:1a'],
    ],
    'preguntas' => [
        'A1' => ['fuente_texto' => '…nació antes del 2 de enero de 1962…'],
    ],
];
```
