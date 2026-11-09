<?php

namespace App\Services\Lectura;

use App\Enums\Lectura\ClaseDetectada;
use App\Enums\Lectura\Legibilidad;
use App\Enums\Lectura\MotivoConfianza;
use App\Enums\Lectura\OrigenCategoria;
use App\Enums\Lectura\SentidoRenglon;
use App\Models\Documento;
use App\Models\Lectura\DocumentoLectura;
use App\Services\DocumentoAlmacen;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell\ErrorCell;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use RuntimeException;
use Throwable;

/**
 * Lee sin IA las hojas `xlsx` y `csv` de los registros del negocio (F6 6.3b,
 * decisión l). Un valor leído de una celda es exacto: confianza 0,99 con el
 * motivo "Leído de la hoja de cálculo (sin IA)". Nunca llama a OpenAI.
 *
 * Qué clase de hoja es se decide así, en este orden:
 *  1. Plantilla de F3.7: el título de la plantilla está en las primeras filas
 *     de la primera columna (en español o en inglés).
 *  2. Hoja propia: en las primeras 10 filas hay una fila de encabezados con una
 *     columna de fecha y otra de monto (o de débito y crédito).
 *  3. Plantilla sin título (el cliente lo borró): al menos 4 etiquetas de
 *     categoría de la plantilla en la primera columna.
 * Si no es ninguna, no se lee nada y el archivo va a R-14.
 *
 * Las etiquetas, los sinónimos y la línea del Schedule C de cada categoría salen
 * de `plantilla-registros.php` del catálogo del año. Lo que no se reconoce
 * (columnas, filas sin monto o sin fecha, fórmulas sin valor, hojas sin leer,
 * categorías) no se adivina: se cuenta en `observaciones` (solo conteos y letras
 * de columna, nunca valores) o queda `no_se`, y la regla R-14 lo manda al
 * preparador. La categoría reconocida manda sobre el signo del monto.
 *
 * Los mensajes de error nunca llevan lo leído.
 */
class LectorHojas implements LectorDocumentos
{
    public const NOMBRE = 'hoja';

    /** Una celda con fórmula sin valor guardado (o con error): el archivo no trae el resultado. */
    private const FORMULA_SIN_VALOR = "\0formula-sin-valor";

    /** Las filas de una hoja que se leen como máximo: más allá es otra cosa que una hoja de registros. */
    private const MAXIMO_FILAS = 20000;

    /** Cuántas etiquetas de la plantilla bastan para reconocerla sin su título. */
    private const ETIQUETAS_MINIMAS = 4;

    /** @var array<int, array<string, mixed>> */
    private array $tablas = [];

    public function __construct(private readonly DocumentoAlmacen $almacen) {}

    public function leer(Documento $documento, DocumentoLectura $lectura, string $codigo, int $anio): LecturaResultado
    {
        $contenido = $this->almacen->leer($documento);

        throw_if($contenido === null, new RuntimeException('No se pudo abrir el archivo de la hoja.'));

        $formato = strtolower((string) $documento->formato);
        $hojas = match ($formato) {
            'xlsx' => $this->hojasXlsx($contenido),
            'csv' => ['csv' => $this->filasCsv($contenido)],
            default => throw new RuntimeException('Este lector solo abre archivos xlsx y csv.'),
        };

        $tabla = $this->tabla($anio);

        foreach ($hojas as $nombre => $filas) {
            $leida = $this->plantilla($filas, $tabla, true) ?? $this->hojaPropia($filas, $tabla) ?? $this->plantilla($filas, $tabla, false);

            if ($leida !== null) {
                $otras = count(array_filter($hojas, fn (array $f, string|int $n) => (string) $n !== (string) $nombre && $this->tieneContenido($f), ARRAY_FILTER_USE_BOTH));

                return $this->resultado($codigo, $leida, $otras);
            }
        }

        return new LecturaResultado(
            lector: self::NOMBRE,
            tipoDetectado: $codigo,
            casillas: $this->casillas(['clase-detectada' => ClaseDetectada::Otro->value]),
            claseDetectada: ClaseDetectada::Otro,
            observaciones: ['formato_no_reconocido' => true],
        );
    }

    /**
     * Compara textos sin mayúsculas, sin tildes, sin lo que va entre paréntesis y con los
     * espacios juntos: "Auto y camioneta (gasolina…)" y "AUTO  Y CAMIONETA" son lo mismo.
     */
    public static function normalizar(string $texto): string
    {
        $texto = preg_replace('/\([^)]*\)/u', ' ', $texto) ?? $texto;
        $texto = mb_strtolower(Str::ascii($texto));

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $texto) ?? '');
    }

    // ───────────────────────── Tablas del catálogo ─────────────────────────

    /**
     * La tabla de etiquetas del año, ya normalizada. 2026 se carga de 2025: si el año no
     * tiene archivo propio se usa el del año anterior más cercano.
     *
     * @return array{encabezados: array<string, true>, totales: array<string, true>, estructura: array<string, true>, categorias: array<string, array{linea: string, sentido: SentidoRenglon, plantilla: bool}>, cantidades: array<string, string>, columnas: array<string, array<string, true>>}
     */
    private function tabla(int $anio): array
    {
        if (isset($this->tablas[$anio])) {
            return $this->tablas[$anio];
        }

        $archivo = null;

        for ($a = $anio; $a >= 2025; $a--) {
            $ruta = database_path("data/catalogo-fiscal/{$a}/plantilla-registros.php");

            if (is_file($ruta)) {
                $archivo = $ruta;
                break;
            }
        }

        throw_if($archivo === null, new RuntimeException("Falta plantilla-registros.php del catálogo de {$anio}."));

        /** @var array<string, mixed> $datos */
        $datos = require $archivo;

        $conjunto = fn (array $textos) => array_fill_keys(array_map(self::normalizar(...), $textos), true);
        $categorias = [];

        foreach ($datos['categorias'] as $categoria) {
            foreach (['plantilla' => true, 'sinonimos' => false] as $origen => $esPlantilla) {
                foreach ($categoria[$origen] as $etiqueta) {
                    $categorias[self::normalizar($etiqueta)] ??= [
                        'linea' => (string) $categoria['linea'],
                        'sentido' => SentidoRenglon::from($categoria['sentido']),
                        'plantilla' => $esPlantilla,
                    ];
                }
            }
        }

        $cantidades = [];

        foreach ($datos['cantidades'] as $cantidad) {
            foreach ($cantidad['etiquetas'] as $etiqueta) {
                $cantidades[self::normalizar($etiqueta)] = (string) $cantidad['casilla'];
            }
        }

        return $this->tablas[$anio] = [
            'encabezados' => $conjunto($datos['encabezados_plantilla']),
            'totales' => $conjunto($datos['totales']),
            'estructura' => $conjunto($datos['estructura']),
            'categorias' => $categorias,
            'cantidades' => $cantidades,
            'columnas' => array_map($conjunto, $datos['columnas']),
        ];
    }

    // ───────────────────────── Plantilla de F3.7 ─────────────────────────

    /**
     * @param  array<int, array<int, mixed>>  $filas
     * @param  array<string, mixed>  $tabla
     * @param  bool  $conTitulo  exige el título de la plantilla; si no, bastan sus etiquetas
     * @return array<string, mixed>|null
     */
    private function plantilla(array $filas, array $tabla, bool $conTitulo): ?array
    {
        $etiquetas = 0;
        $titulo = false;

        foreach ($filas as $numero => $fila) {
            $etiqueta = $this->texto($fila[0] ?? null);

            if ($etiqueta === null) {
                continue;
            }

            $normal = self::normalizar($etiqueta);
            $titulo = $titulo || ($numero <= 6 && isset($tabla['encabezados'][$normal]));
            $etiquetas += (($tabla['categorias'][$normal]['plantilla'] ?? false) || isset($tabla['cantidades'][$normal])) ? 1 : 0;
        }

        if ($conTitulo ? ! $titulo : $etiquetas < self::ETIQUETAS_MINIMAS) {
            return null;
        }

        $renglones = [];
        $casillas = ['clase-detectada' => ClaseDetectada::Plantilla->value];
        $observaciones = ['filas_sin_monto' => 0, 'formulas_sin_valor' => 0];
        $anioHoja = null;

        foreach ($filas as $numero => $fila) {
            $etiqueta = $this->texto($fila[0] ?? null);
            $celda = $fila[1] ?? null;

            if ($etiqueta === null) {
                continue;
            }

            $normal = self::normalizar($etiqueta);

            if (isset($tabla['totales'][$normal]) || isset($tabla['encabezados'][$normal]) || isset($tabla['estructura'][$normal])) {
                // "Año fiscal: 2025" es la única de estas filas con un dato que se lee.
                if (in_array($normal, ['ano fiscal', 'tax year'], true) && is_numeric($celda) && preg_match('/^\d{4}$/', (string) (int) $celda) === 1) {
                    $anioHoja = (int) $celda;
                }

                continue;
            }

            $vacia = $celda === null || $celda === '';

            if ($vacia) {
                continue;
            }

            if ($celda === self::FORMULA_SIN_VALOR) {
                $observaciones['formulas_sin_valor']++;

                continue;
            }

            $monto = $this->decimal($celda);

            if (isset($tabla['cantidades'][$normal])) {
                if ($monto === null) {
                    $observaciones['filas_sin_monto']++;
                } else {
                    $casillas[$tabla['cantidades'][$normal]] = $monto;
                }

                continue;
            }

            $categoria = $tabla['categorias'][$normal] ?? null;

            if ($monto === null) {
                $observaciones['filas_sin_monto']++;

                continue;
            }

            if ($this->esCero($monto)) {
                continue;
            }

            $renglones[] = $this->renglon(
                numero: count($renglones) + 1,
                fila: $numero,
                fecha: null,
                descripcion: $etiqueta,
                monto: $monto,
                categoria: $categoria,
            );
        }

        return [
            'clase' => ClaseDetectada::Plantilla,
            'renglones' => $renglones,
            'casillas' => $casillas,
            'observaciones' => $observaciones,
            'anio' => $anioHoja,
            'desde' => null,
            'hasta' => null,
        ];
    }

    // ───────────────────────── Hoja propia ─────────────────────────

    /**
     * @param  array<int, array<int, mixed>>  $filas
     * @param  array<string, mixed>  $tabla
     * @return array<string, mixed>|null
     */
    private function hojaPropia(array $filas, array $tabla): ?array
    {
        $encabezado = null;
        $columnas = [];
        $revisadas = 0;

        foreach ($filas as $numero => $fila) {
            if (++$revisadas > 10) {
                break;
            }

            $mapa = [];

            foreach ($fila as $indice => $celda) {
                $texto = $this->texto($celda);

                if ($texto === null) {
                    continue;
                }

                $normal = self::normalizar($texto);

                foreach ($tabla['columnas'] as $tipo => $sinonimos) {
                    if (isset($sinonimos[$normal])) {
                        // Un tipo repetido (dos columnas de monto) deja la segunda como no reconocida.
                        $mapa[$tipo] ??= $indice;

                        continue 2;
                    }
                }
            }

            if (isset($mapa['fecha']) && (isset($mapa['monto']) || isset($mapa['debito']) || isset($mapa['credito']))) {
                $encabezado = $numero;
                $columnas = $mapa;
                $desconocidas = [];

                foreach ($fila as $indice => $celda) {
                    if ($this->texto($celda) !== null && ! in_array($indice, $mapa, true)) {
                        $desconocidas[] = $indice;
                    }
                }

                break;
            }
        }

        if ($encabezado === null) {
            return null;
        }

        $observaciones = ['columnas_no_reconocidas' => array_map($this->letra(...), $desconocidas), 'filas_sin_monto' => 0, 'filas_sin_fecha' => 0, 'formulas_sin_valor' => 0];
        $renglones = [];
        $desde = null;
        $hasta = null;

        foreach ($filas as $numero => $fila) {
            if ($numero <= $encabezado || $this->filaVacia($fila)) {
                continue;
            }

            $fecha = $this->fecha($fila[$columnas['fecha']] ?? null);
            $monto = null;
            $sinValor = false;
            $ilegible = false;

            foreach (['monto', 'debito', 'credito'] as $tipo) {
                $celda = isset($columnas[$tipo]) ? ($fila[$columnas[$tipo]] ?? null) : null;

                if ($celda === null || $celda === '') {
                    continue;
                }

                if ($celda === self::FORMULA_SIN_VALOR) {
                    $sinValor = true;

                    continue;
                }

                $valor = $this->decimal($celda);

                if ($valor === null || $this->esCero($valor)) {
                    $ilegible = $ilegible || $valor === null;

                    continue;
                }

                // Débito y crédito a la vez en una misma fila: no se sabe cuál cuenta.
                if ($monto !== null) {
                    $monto = null;
                    $ilegible = true;
                    break;
                }

                $monto = $valor;
            }

            if ($sinValor) {
                $observaciones['formulas_sin_valor']++;

                continue;
            }

            if ($monto === null) {
                // Una fila con importe en cero no es un renglón; una con texto raro o sin importe, sí se cuenta.
                if ($ilegible || $this->soloTexto($fila, $columnas)) {
                    $observaciones['filas_sin_monto']++;
                }

                continue;
            }

            if ($fecha === null) {
                $observaciones['filas_sin_fecha']++;

                continue;
            }

            $desde = $desde === null || $fecha->lt($desde) ? $fecha : $desde;
            $hasta = $hasta === null || $fecha->gt($hasta) ? $fecha : $hasta;

            $textoCategoria = isset($columnas['categoria']) ? $this->texto($fila[$columnas['categoria']] ?? null) : null;
            $categoria = $textoCategoria === null ? null : ($tabla['categorias'][self::normalizar($textoCategoria)] ?? null);

            $descripcion = isset($columnas['descripcion']) ? $this->texto($fila[$columnas['descripcion']] ?? null) : null;

            $renglones[] = $this->renglon(
                numero: count($renglones) + 1,
                fila: $numero,
                fecha: $fecha,
                descripcion: $descripcion,
                monto: $monto,
                categoria: $categoria,
            );
        }

        return [
            'clase' => ClaseDetectada::HojaCalculo,
            'renglones' => $renglones,
            'casillas' => ['clase-detectada' => ClaseDetectada::HojaCalculo->value],
            'observaciones' => $observaciones,
            'anio' => null,
            'desde' => $desde,
            'hasta' => $hasta,
        ];
    }

    // ───────────────────────── Armado del resultado ─────────────────────────

    /**
     * @param  array{linea: string, sentido: SentidoRenglon, plantilla: bool}|null  $categoria
     */
    private function renglon(int $numero, int $fila, ?CarbonImmutable $fecha, ?string $descripcion, string $monto, ?array $categoria): RenglonLeido
    {
        return new RenglonLeido(
            numero: $numero,
            pagina: $fila,
            fecha: $fecha,
            descripcion: $descripcion,
            // El monto se guarda siempre positivo: la dirección la dice el sentido.
            monto: ltrim($monto, '-'),
            // Sin categoría reconocida no se adivina la dirección: `no_se`, y va a R-14.
            sentido: $categoria['sentido'] ?? SentidoRenglon::NoSe,
            categoria: $categoria['linea'] ?? null,
            categoriaOrigen: $categoria === null ? null : OrigenCategoria::Documento,
            confianza: CalculadoraConfianza::EN_TEXTO_DEL_PDF,
            motivo: MotivoConfianza::HojaCalculo,
            legibilidad: Legibilidad::Claro,
        );
    }

    /**
     * @param  array<string, mixed>  $leida
     */
    private function resultado(string $codigo, array $leida, int $hojasSinLeer): LecturaResultado
    {
        $observaciones = array_filter($leida['observaciones'], fn (mixed $v) => $v !== 0 && $v !== []);

        if ($hojasSinLeer > 0) {
            $observaciones['hojas_sin_leer'] = $hojasSinLeer;
        }

        return new LecturaResultado(
            lector: self::NOMBRE,
            tipoDetectado: $codigo,
            anioDetectado: $leida['anio'],
            casillas: $this->casillas($leida['casillas']),
            renglones: $leida['renglones'],
            claseDetectada: $leida['clase'],
            periodoDesde: $leida['desde'],
            periodoHasta: $leida['hasta'],
            observaciones: $observaciones === [] ? null : $observaciones,
        );
    }

    /**
     * @param  array<string, string>  $valores  casilla → valor
     * @return array<string, array{valor: string|null, legibilidad: Legibilidad, confianza: float, motivo: MotivoConfianza, pagina: int|null, recuadro: null, evidencia: null}>
     */
    private function casillas(array $valores): array
    {
        return array_map(fn (string $valor) => [
            'valor' => $valor,
            'legibilidad' => Legibilidad::Claro,
            'confianza' => CalculadoraConfianza::EN_TEXTO_DEL_PDF,
            'motivo' => MotivoConfianza::HojaCalculo,
            'pagina' => null,
            'recuadro' => null,
            'evidencia' => null,
        ], $valores);
    }

    // ───────────────────────── Lectura de los archivos ─────────────────────────

    /**
     * @return array<string, array<int, array<int, mixed>>> nombre de la hoja → número de fila → columna → valor
     */
    private function hojasXlsx(string $contenido): array
    {
        $ruta = tempnam(sys_get_temp_dir(), 'hoja-');

        throw_if($ruta === false || file_put_contents($ruta, $contenido) === false, new RuntimeException('No se pudo preparar la hoja para leerla.'));

        $reader = new Reader(new Options(SHOULD_PRESERVE_EMPTY_ROWS: true));
        $hojas = [];

        try {
            $reader->open($ruta);

            foreach ($reader->getSheetIterator() as $hoja) {
                $filas = [];

                foreach ($hoja->getRowIterator() as $numero => $fila) {
                    if ($numero > self::MAXIMO_FILAS) {
                        break;
                    }

                    $filas[$numero] = array_map($this->valorDeCelda(...), $fila->cells);
                }

                $hojas[$hoja->getName()] = $filas;
            }
        } catch (Throwable) {
            // El mensaje de la librería podría traer texto del archivo: no se propaga.
            throw new RuntimeException('No se pudo abrir la hoja de cálculo: el archivo está dañado o no es un xlsx.');
        } finally {
            try {
                $reader->close();
            } catch (Throwable) {
                // ya estaba cerrado
            }

            @unlink($ruta);
        }

        return $hojas;
    }

    private function valorDeCelda(mixed $celda): mixed
    {
        if ($celda instanceof ErrorCell) {
            return self::FORMULA_SIN_VALOR;
        }

        if ($celda instanceof FormulaCell) {
            $valor = $celda->getComputedValue();

            // El archivo guarda `<v/>` cuando nadie calculó la fórmula, y la librería lo lee como 0: un cero
            // calculado de verdad y un valor que falta no se distinguen, así que los dos van a revisión.
            return $valor === null || $valor === '' || (is_numeric($valor) && (float) $valor === 0.0) ? self::FORMULA_SIN_VALOR : $valor;
        }

        $valor = $celda->getValue();

        return is_string($valor) ? (trim($valor) === '' ? null : trim($valor)) : $valor;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function filasCsv(string $contenido): array
    {
        // Excel guarda a veces en Latin-1 / Windows-1252: se pasa a UTF-8.
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido) ?? $contenido;

        if (! mb_check_encoding($contenido, 'UTF-8')) {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');
        }

        $separador = $this->separador($contenido);
        $manejador = fopen('php://temp', 'r+');

        throw_if($manejador === false, new RuntimeException('No se pudo leer el csv.'));

        fwrite($manejador, $contenido);
        rewind($manejador);

        $filas = [];
        $numero = 0;

        while (($registro = fgetcsv($manejador, null, $separador, '"', '')) !== false && $numero < self::MAXIMO_FILAS) {
            $numero++;
            $filas[$numero] = array_map(fn (?string $c) => $c === null || trim($c) === '' ? null : trim($c), $registro);
        }

        fclose($manejador);

        return $filas;
    }

    private function separador(string $contenido): string
    {
        $linea = '';

        foreach (preg_split('/\R/', $contenido) ?: [] as $candidata) {
            if (trim($candidata) !== '') {
                $linea = $candidata;
                break;
            }
        }

        $linea = preg_replace('/"[^"]*"/', '', $linea) ?? $linea;
        $cuentas = [',' => substr_count($linea, ','), ';' => substr_count($linea, ';'), "\t" => substr_count($linea, "\t")];
        arsort($cuentas);

        return array_key_first($cuentas) !== null && reset($cuentas) > 0 ? (string) array_key_first($cuentas) : ',';
    }

    // ───────────────────────── Celdas ─────────────────────────

    private function texto(mixed $celda): ?string
    {
        if ($celda === null || $celda === self::FORMULA_SIN_VALOR || $celda instanceof DateTimeInterface) {
            return null;
        }

        $texto = trim((string) (is_scalar($celda) ? $celda : ''));

        return $texto === '' ? null : $texto;
    }

    /**
     * Un monto en texto con dos decimales ("45.20", "-300.00"), o null si no es un monto. No adivina:
     * más de dos decimales o un texto que no se entiende no son un monto.
     */
    private function decimal(mixed $celda): ?string
    {
        if (is_int($celda)) {
            return $celda.'.00';
        }

        if (is_float($celda)) {
            $texto = rtrim(rtrim(sprintf('%.6F', $celda), '0'), '.');
        } elseif (is_string($celda)) {
            $texto = $this->limpiarMonto($celda);
        } else {
            return null;
        }

        if ($texto === null || preg_match('/^-?\d+(\.\d{1,2})?$/', $texto) !== 1) {
            return null;
        }

        return str_contains($texto, '.') ? $texto.str_repeat('0', 2 - strlen(substr($texto, strpos($texto, '.') + 1))) : $texto.'.00';
    }

    private function limpiarMonto(string $texto): ?string
    {
        $texto = trim(str_replace(['$', ' ', "\u{a0}"], '', $texto));
        $negativo = false;

        if (preg_match('/^\((.*)\)$/', $texto, $m) === 1) {
            $negativo = true;
            $texto = $m[1];
        }

        if (str_starts_with($texto, '-')) {
            $negativo = ! $negativo;
            $texto = substr($texto, 1);
        }

        $comas = substr_count($texto, ',');
        $puntos = substr_count($texto, '.');

        $texto = match (true) {
            // 1,234.56 o 1.234,56: el último separador es el decimal.
            $comas > 0 && $puntos > 0 => strrpos($texto, ',') > strrpos($texto, '.')
                ? str_replace(',', '.', str_replace('.', '', $texto))
                : str_replace(',', '', $texto),
            // 1,234 o 1,234,567 son miles; 12,5 o 12,50 es un decimal.
            $comas > 0 => preg_match('/^\d{1,3}(,\d{3})+$/', $texto) === 1 ? str_replace(',', '', $texto) : (preg_match('/^\d+,\d{1,2}$/', $texto) === 1 ? str_replace(',', '.', $texto) : $texto),
            default => $texto,
        };

        if (preg_match('/^\d+(\.\d+)?$/', $texto) !== 1) {
            return null;
        }

        return ($negativo ? '-' : '').$texto;
    }

    private function esCero(string $monto): bool
    {
        return trim($monto, '-0.') === '';
    }

    /**
     * Fechas de una celda: las de Excel llegan como fecha; en texto se aceptan AAAA-MM-DD y
     * MM/DD/AAAA (o DD/MM/AAAA si el primer número no puede ser un mes). Lo demás no es fecha.
     */
    private function fecha(mixed $celda): ?CarbonImmutable
    {
        if ($celda instanceof DateTimeInterface) {
            return CarbonImmutable::instance($celda)->startOfDay();
        }

        if (! is_string($celda)) {
            return null;
        }

        $texto = trim($celda);

        if (preg_match('/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})(?:[ T].*)?$/', $texto, $m) === 1) {
            [$a, $mes, $dia] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2}|\d{4})$/', $texto, $m) === 1) {
            [$primero, $segundo] = [(int) $m[1], (int) $m[2]];
            $a = (int) $m[3] < 100 ? 2000 + (int) $m[3] : (int) $m[3];
            [$mes, $dia] = $primero > 12 ? [$segundo, $primero] : [$primero, $segundo];
        } else {
            return null;
        }

        return checkdate($mes, $dia, $a) ? CarbonImmutable::create($a, $mes, $dia)->startOfDay() : null;
    }

    /**
     * @param  array<int, mixed>  $fila
     */
    private function filaVacia(array $fila): bool
    {
        foreach ($fila as $celda) {
            if ($celda !== null && $celda !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, array<int, mixed>>  $filas
     */
    private function tieneContenido(array $filas): bool
    {
        foreach ($filas as $fila) {
            if (! $this->filaVacia($fila)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Una fila con algo escrito en la columna de descripción o de categoría pero sin importe.
     *
     * @param  array<int, mixed>  $fila
     * @param  array<string, int>  $columnas
     */
    private function soloTexto(array $fila, array $columnas): bool
    {
        foreach (['descripcion', 'categoria', 'fecha'] as $tipo) {
            if (isset($columnas[$tipo]) && $this->texto($fila[$columnas[$tipo]] ?? null) !== null) {
                return true;
            }

            if (isset($columnas[$tipo]) && ($fila[$columnas[$tipo]] ?? null) instanceof DateTimeInterface) {
                return true;
            }
        }

        return false;
    }

    private function letra(int $indice): string
    {
        $letra = '';

        for ($i = $indice + 1; $i > 0; $i = intdiv($i - 1, 26)) {
            $letra = chr(65 + ($i - 1) % 26).$letra;
        }

        return $letra;
    }
}
