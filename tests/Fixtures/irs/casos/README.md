# Casos del IRS (oráculo de validación)

Cada archivo `tests/Fixtures/irs/casos/<año>/<codigo>.php` es **un caso transcrito a mano de una hoja o ejemplo del IRS**. Los usan las pruebas del motor de cálculo (fase 6) y el botón "validada contra el IRS" de la pantalla de Formularios (a través del archivo generado por `php artisan calculo:cobertura <año>`).

## Regla de doble lectura

- **Quien escribe los casos no escribe la calculadora ni la lee.** El valor esperado no puede salir del código que se va a probar.
- Se transcribe **a mano, paso por paso**, la hoja de cálculo del IRS en `pasos`, como lo haría un preparador en papel.
- Si calculadora y caso no coinciden, **el agente principal revisa contra el PDF fijado antes de tocar cualquiera de los dos** y anota qué estaba mal.
- Las entradas son **hechos** (estado civil, montos, número de hijos), nunca conclusiones del cálculo.
- Si el IRS no nombra la línea del formulario donde va una cifra, se anota la suposición en el comentario del archivo y el caso **no declara cobertura** (`cubre => []`).

## Formato

```php
return [
    'codigo' => 'pub1040-tax-table-mfj-25300',   // igual al nombre del archivo, sin .php
    'anio' => 2025,
    'descripcion' => '…',
    'entradas' => [ /* hechos, no conclusiones */ ],
    'pasos' => [
        ['formulario' => '1040', 'linea' => '16', 'texto' => 'Hoja…', 'valor' => '2562',
         'fuente' => ['doc' => 'p1040', 'pagina' => 'p. 2', 'url' => 'https://www.irs.gov/pub/irs-prior/p1040--2025.pdf']],
    ],
    'lineas' => [ '1040' => ['16' => '2562'] ],  // valores esperados en dólares, como texto
    'cubre' => ['1040:16'],                      // líneas que este caso prueba de verdad
    'contraste' => null,                         // ['herramienta' => 'EITC Assistant', 'fecha' => '2026-11-09', 'resultado' => '…']
];
```

- `valor` y `lineas` van **en dólares, como texto** (`'2562'`, `'0.0800'`); nunca `float`.
- `cubre` usa `"<formulario>:<línea>"`, con el mismo código de formulario y línea del catálogo fiscal.

## Cómo citar

Cada paso lleva `fuente` con tres datos, todos obligatorios:

1. `doc`: la clave del documento en `tests/Fixtures/irs/<año>/fuentes.json` (`p1040`, `i8962`, `i2441`, `i1040gi`, …).
2. `pagina`: la **página** del PDF, más la hoja, tabla o línea si hace falta (`p. 21 (Example 1, Allocation Situation 4)`).
3. `url`: **la URL exacta del PDF fijado**; debe estar en `fuentes.json`.

Los PDF fijados viven en `tests/Fixtures/irs/<año>/pdf/` con su SHA-256 en `fuentes.json`. Para cambiar de versión de un PDF se actualiza el archivo y su hash a la vez; la prueba `CasosIrsTest` falla si no coinciden.

## Tablas extraídas

`tests/Fixtures/irs/2025/*.csv` salen de `scripts/irs/extraer-tablas.php` (pdftotext sobre el PDF fijado, no teclean cifras). Cada CSV tiene sus diez filas de control en `fuentes.json` (`revision`). Las pruebas que recorren cada fila (`TablasIrsTest`) están saltadas hasta que exista la calculadora (6.4).
