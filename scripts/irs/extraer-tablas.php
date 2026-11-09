<?php

/*
 * Extrae a CSV las tablas oficiales del IRS (año fiscal 2025) desde los PDF fijados.
 * Es una herramienta de desarrollo: se corre a mano, no corre en producción y no
 * lee nada de app/. Los PDF son datos no confiables: solo se pasan a pdftotext.
 *
 * Uso:
 *   php -d display_errors=1 scripts/irs/extraer-tablas.php <directorio-con-los-pdf> <directorio-de-salida>
 *
 * Ejemplo:
 *   php scripts/irs/extraer-tablas.php tests/Fixtures/irs/2025/pdf tests/Fixtures/irs/2025
 *
 * Salidas (todas en dólares enteros salvo las decimales del 2441 y del 8962):
 *   tax-table.csv                 desde,hasta,soltero,mfj,mfs,hoh          (p1040.pdf, págs. 2 a 13; hasta es exclusivo)
 *   eic-table.csv                 desde,hasta,solo_0..solo_3,mfj_0..mfj_3  (p1040.pdf, págs. 16 a 28; hasta es exclusivo)
 *   f2441-tabla.csv               desde,hasta,decimal                      (f2441.pdf, pág. 1, línea 8; "hasta" vacío = sin límite)
 *   f8962-pobreza.csv             tabla,tamano,monto,adicional             (i8962.pdf, págs. 8 y 9, Tablas 1-1, 1-2 y 1-3)
 *   f8962-cifra-aplicable.csv     desde,hasta,cifra                        (i8962.pdf, pág. 11, Tabla 2; extremos incluidos)
 *   f8962-limite-devolucion.csv   desde,hasta,soltero,otro                 (i8962.pdf, pág. 18, Tabla 5; "hasta" vacío = 400 o más)
 */
if ($argc !== 3) {
    fwrite(STDERR, "Uso: php scripts/irs/extraer-tablas.php <dir-pdf> <dir-salida>\n");
    exit(1);
}

[$dirPdf, $dirSalida] = [rtrim($argv[1], '/'), rtrim($argv[2], '/')];

function texto(string $pdf, int $desde, int $hasta, bool $layout = true): string
{
    if (! is_file($pdf)) {
        fwrite(STDERR, "No existe $pdf\n");
        exit(1);
    }
    $comando = 'pdftotext '.($layout ? '-layout ' : '').'-f '.$desde.' -l '.$hasta.' '.escapeshellarg($pdf).' - 2>/dev/null';
    $salida = shell_exec($comando);
    if (! is_string($salida) || $salida === '') {
        fwrite(STDERR, "pdftotext no devolvió texto para $pdf págs. $desde-$hasta\n");
        exit(1);
    }

    return $salida;
}

function entero(string $s): int
{
    return (int) str_replace(',', '', $s);
}

function fallar(string $mensaje): never
{
    fwrite(STDERR, "ERROR: $mensaje\n");
    exit(1);
}

/** @param list<list<int|string>> $filas */
function csv(string $ruta, array $encabezado, array $filas): void
{
    $f = fopen($ruta, 'w');
    fputcsv($f, $encabezado, ',', '"', '', "\n");
    foreach ($filas as $fila) {
        fputcsv($f, $fila, ',', '"', '', "\n");
    }
    fclose($f);
    echo basename($ruta).': '.count($filas)." filas\n";
}

/**
 * Agrega la fila; si ya estaba (el ejemplo "Sample Table" repite filas) exige que sea idéntica.
 *
 * @param  array<int, list<int>>  $filas
 * @param  list<int>  $fila
 */
function agregar(array &$filas, array $fila): void
{
    $clave = $fila[0];
    if (isset($filas[$clave]) && $filas[$clave] !== $fila) {
        fallar("Fila repetida con valores distintos en $clave");
    }
    $filas[$clave] = $fila;
}

/** @param array<int, list<int>> $filas */
function contigua(array $filas, string $nombre): void
{
    ksort($filas);
    $anterior = null;
    foreach ($filas as $fila) {
        if ($anterior !== null && $fila[0] !== $anterior) {
            fallar("$nombre no es contigua: una fila termina en $anterior y la siguiente empieza en {$fila[0]}");
        }
        if ($fila[1] <= $fila[0]) {
            fallar("$nombre tiene una franja vacía o invertida en {$fila[0]}");
        }
        $anterior = $fila[1];
    }
}

$N = '(\d{1,3}(?:,\d{3})*)';

// ---------------------------------------------------------------- Tax Table
$filas = [];
for ($p = 2; $p <= 13; $p++) {
    $t = texto("$dirPdf/p1040.pdf", $p, $p);
    if ($p === 2) {
        // Las dos primeras franjas (0–5 y 5–15) salen en líneas sueltas, una cifra por línea:
        // se exige que el texto traiga exactamente esa secuencia y se agregan a mano.
        if (! preg_match('/\n\s+0\n\s+5\n\s+5\n\s+15\n\s+0\n\s+1\n\s+0\n\s+1\n\s+0\n\s+1\n\s+0\n\s+1\s/', $t)) {
            fallar('La secuencia de las franjas 0–5 y 5–15 no está en la pág. 2 de la Pub. 1040');
        }
        agregar($filas, [0, 5, 0, 0, 0, 0]);
        agregar($filas, [5, 15, 1, 1, 1, 1]);
    }
    foreach (explode("\n", $t) as $linea) {
        if (preg_match_all("/(?<![\d,]){$N}\s+{$N}\s+{$N}\s+{$N}\s+{$N}\s+{$N}(?![\d,])/", $linea, $m, PREG_SET_ORDER)) {
            foreach ($m as $c) {
                agregar($filas, array_map('entero', array_slice($c, 1, 6)));
            }
        }
    }
}
ksort($filas);
contigua($filas, 'tax-table');
if (array_key_first($filas) !== 0 || end($filas)[1] !== 100000) {
    fallar('La Tax Table debe ir de 0 a 100.000 (a partir de ahí se usa la hoja de cálculo)');
}
csv("$dirSalida/tax-table.csv", ['desde', 'hasta', 'soltero', 'mfj', 'mfs', 'hoh'], array_values($filas));

// ---------------------------------------------------------------- EIC Table
// Algunas celdas traen un asterisco (*, ** o ***) en vez de cifra: es una nota al pie de la página
// ("si el monto es al menos X pero menos de Y, y tienes N hijos con SSN válido, tu crédito es $Z;
// de Y en adelante no hay crédito"). La celda queda con su marcador en el CSV y la nota se extrae
// a eic-notas.csv.
$C = '(\d{1,3}(?:,\d{3})*|\*{1,3})';
$filas = [];
$marcadas = [];
$textoNotas = '';
for ($p = 16; $p <= 27; $p++) {
    $t = texto("$dirPdf/p1040.pdf", $p, $p);
    $textoNotas .= ' '.preg_replace('/\s+/', ' ', $t);
    foreach (explode("\n", $t) as $linea) {
        if (preg_match_all("/(?<![\d,*]){$N}\s+{$N}".str_repeat("\s+{$C}", 8)."(?![\d,*])/", $linea, $m, PREG_SET_ORDER)) {
            foreach ($m as $c) {
                $celdas = array_slice($c, 1, 10);
                $fila = [entero($celdas[0]), entero($celdas[1])];
                foreach (array_slice($celdas, 2) as $i => $celda) {
                    $fila[] = str_contains($celda, '*') ? $celda : entero($celda);
                    if (str_contains($celda, '*')) {
                        $marcadas[$fila[0]][] = [$i, $celda];
                    }
                }
                // Las líneas de encabezado ("0 1 2 3 0 1 2 3 ...") también calzan con 10 números: no son filas.
                if ($fila[0] < 1 || $fila[1] - $fila[0] < 49 || $fila[1] % 50 !== 0) {
                    continue;
                }
                agregar($filas, $fila);
            }
        }
    }
}
ksort($filas);
contigua($filas, 'eic-table');
csv("$dirSalida/eic-table.csv", ['desde', 'hasta', 'solo_0', 'solo_1', 'solo_2', 'solo_3', 'mfj_0', 'mfj_1', 'mfj_2', 'mfj_3'], array_values($filas));

$columnas = ['solo_0', 'solo_1', 'solo_2', 'solo_3', 'mfj_0', 'mfj_1', 'mfj_2', 'mfj_3'];
$hijos = ['no' => 0, 'one' => 1, 'two' => 2, 'three' => 3];
preg_match_all('/at least \$([\d,]+) but less than \$([\d,]+), and you have (no|one|two|three) qualifying child(?:ren)? who(?: has| have)? a? ?valid SSNs?,? your credit is \$(\d+)/', $textoNotas, $notas, PREG_SET_ORDER);
$salida = [];
foreach ($notas as $n) {
    $desde = entero($n[1]);
    $columna = null;
    foreach ($marcadas[$desde] ?? [] as [$i, $marca]) {
        if ($i % 4 === $hijos[$n[3]]) {
            $columna = $columnas[$i];
        }
    }
    if ($columna === null) {
        fallar("La nota de la EIC Table que empieza en $desde no tiene su celda marcada");
    }
    $salida[] = [$desde, $filas[$desde][1], $columna, entero($n[2]), (int) $n[4]];
}
if (count($salida) !== count(array_sum(array_map('count', $marcadas)) ? array_merge(...array_values($marcadas)) : [])) {
    fallar('Las celdas marcadas y las notas al pie no coinciden: '.count($salida).' notas, '.array_sum(array_map('count', $marcadas)).' celdas');
}
csv("$dirSalida/eic-notas.csv", ['desde', 'hasta', 'columna', 'credito_hasta_menos_de', 'credito'], $salida);

// ---------------------------------------------------------------- Form 2441, línea 8
$filas = [];
foreach (explode("\n", texto("$dirPdf/f2441.pdf", 1, 1)) as $linea) {
    if (preg_match_all('/\$?([\d,]+)—(No limit|[\d,]+)\s+\.(\d{2})/', $linea, $m, PREG_SET_ORDER)) {
        foreach ($m as $c) {
            $filas[entero($c[1])] = [entero($c[1]), $c[2] === 'No limit' ? '' : entero($c[2]), '0.'.$c[3]];
        }
    }
}
ksort($filas);
if (count($filas) !== 16) {
    fallar('El Form 2441 debe traer 16 franjas en la línea 8');
}
$previo = null;
foreach ($filas as $f) {
    if ($previo !== null && $f[0] !== $previo) {
        fallar('f2441-tabla no es contigua');
    }
    $previo = $f[1];
}
csv("$dirSalida/f2441-tabla.csv", ['desde', 'hasta', 'decimal'], array_values($filas));

// ---------------------------------------------------------------- 8962: línea de pobreza
$filas = [];
$adicional = [];
$tabla = null;
foreach (explode("\n", texto("$dirPdf/i8962.pdf", 8, 9)) as $linea) {
    if (preg_match('/Table 1-([123])\. Federal Poverty Line/', $linea, $m)) {
        $tabla = '1-'.$m[1];
    }
    if ($tabla !== null && preg_match('/\s{10,}([1-8])\s{10,}\$([\d,]+)(?![\d,])/', $linea, $m)) {
        $filas[$tabla][(int) $m[1]] = entero($m[2]);
    }
    if ($tabla !== null && preg_match('/add \$([\d,]+) for each additional/', $linea, $m)) {
        $adicional[$tabla] = entero($m[1]);
    }
}
$salida = [];
foreach (['1-1', '1-2', '1-3'] as $t) {
    if (count($filas[$t] ?? []) !== 8 || ! isset($adicional[$t])) {
        fallar("La Tabla $t del 8962 debe traer 8 tamaños y el monto adicional");
    }
    ksort($filas[$t]);
    foreach ($filas[$t] as $tam => $monto) {
        $salida[] = [$t, $tam, $monto, $adicional[$t]];
    }
}
csv("$dirSalida/f8962-pobreza.csv", ['tabla', 'tamano', 'monto', 'adicional'], $salida);

// ---------------------------------------------------------------- 8962: cifra aplicable (Tabla 2)
$pares = [];
foreach (explode("\n", texto("$dirPdf/i8962.pdf", 11, 12)) as $linea) {
    if (preg_match_all('/(?<![\d.])(\d{3})\s+(0\.\d{4})(?!\d)/', $linea, $m, PREG_SET_ORDER)) {
        foreach ($m as $c) {
            $pares[(int) $c[1]] = $c[2];
        }
    }
}
ksort($pares);
if (array_key_first($pares) !== 150 || array_key_last($pares) !== 399 || count($pares) !== 250) {
    fallar('La Tabla 2 del 8962 debe traer del 150 al 399 sin huecos (cuenta: '.count($pares).')');
}
$salida = [[0, 149, '0.0000']];
foreach ($pares as $linea5 => $cifra) {
    $salida[] = [$linea5, $linea5, $cifra];
}
$salida[] = [400, '', '0.0850'];
csv("$dirSalida/f8962-cifra-aplicable.csv", ['desde', 'hasta', 'cifra'], $salida);

// ---------------------------------------------------------------- 8962: límite de devolución (Tabla 5)
$t = texto("$dirPdf/i8962.pdf", 18, 18);
// La página trae dos columnas de texto: se recorta cada línea desde donde empieza "Table 5".
$lineas = explode("\n", $t);
$region = '';
foreach ($lineas as $i => $linea) {
    if (($col = strpos($linea, 'Table 5. Repayment Limitation')) !== false) {
        foreach (array_slice($lineas, $i, 16) as $l) {
            $region .= ' '.substr($l, $col);
        }
        break;
    }
}
$region = preg_replace('/\s+/', ' ', $region);
$salida = [];
if (preg_match('/Less than (\d+)[ .]+\$([\d,]+) \$([\d,]+)/', $region, $m)) {
    $salida[] = [0, (int) $m[1], entero($m[2]), entero($m[3])];
}
if (preg_match_all('/At least (\d+) but less than (\d+)[ .]+\$([\d,]+) \$([\d,]+)/', $region, $mm, PREG_SET_ORDER)) {
    foreach ($mm as $m) {
        $salida[] = [(int) $m[1], (int) $m[2], entero($m[3]), entero($m[4])];
    }
}
if (count($salida) !== 3) {
    // El texto de las franjas parte "At least 200 but less than" y "300" en líneas distintas.
    $salida = [];
    $region = preg_replace('/\.( \.)+/', ' ', $region);
    if (preg_match('/Less than (\d+) \$([\d,]+) \$([\d,]+)/', $region, $m)) {
        $salida[] = [0, (int) $m[1], entero($m[2]), entero($m[3])];
    }
    if (preg_match_all('/At least (\d+) but less than (\d+) \$([\d,]+) \$([\d,]+)/', $region, $mm, PREG_SET_ORDER)) {
        foreach ($mm as $m) {
            $salida[] = [(int) $m[1], (int) $m[2], entero($m[3]), entero($m[4])];
        }
    }
}
if (count($salida) !== 3) {
    fallar('La Tabla 5 del 8962 debe traer 3 franjas (cuenta: '.count($salida).')');
}
$salida[] = [400, '', '', ''];
csv("$dirSalida/f8962-limite-devolucion.csv", ['desde', 'hasta', 'soltero', 'otro'], $salida);
