<?php

namespace App\Services\Lectura;

use App\Enums\Lectura\ClaseDetectada;
use App\Enums\Lectura\EstadoLectura;
use App\Enums\Lectura\Legibilidad;
use App\Enums\Lectura\MotivoConfianza;
use App\Enums\Lectura\OrigenCategoria;
use App\Enums\Lectura\SentidoRenglon;
use App\Models\Documento;
use App\Models\Entrevista\EntrevistaDocumento;
use App\Models\Lectura\DocumentoLectura;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use App\Services\DocumentoAlmacen;
use App\Services\WhatsappAgent\OpenAiClient;
use App\Support\ParametrosFiscales;
use Carbon\CarbonImmutable;
use RuntimeException;
use Throwable;

/**
 * Lee un documento con OpenAI (F4.3d): convierte el archivo en imágenes por
 * página (PaginasDocumento), pide las casillas del catálogo con salida
 * estructurada (EsquemaLectura) y calcula la confianza de cada una
 * (CalculadoraConfianza). En un PDF con texto busca además cada valor en esa
 * capa de texto (UbicadorEnPdf): si está, la confianza sube y queda el
 * recuadro para el visor. En fotos y escaneados no hay texto que comparar.
 *
 * Los documentos que no se leen (sin casillas en el catálogo) se mandan al
 * lector manual sin llamar a OpenAI. Ningún mensaje de error lleva lo leído.
 *
 * Los de renglones (`lectura = renglones`, los registros del negocio, F6 6.3c):
 * una hoja `xlsx` o `csv` la lee `LectorHojas`, sin IA. Un PDF o una foto se
 * leen con IA en modo lista (EsquemaLectura), por bloques de
 * PAGINAS_POR_BLOQUE páginas: una llamada por bloque y los renglones se juntan
 * en el orden del archivo. Antes de llamar a OpenAI se revisan dos límites del
 * catálogo del año, y si alguno no deja, el archivo queda leído sin datos con
 * `observaciones.sin_leer` (la regla R-14 lo explica al preparador):
 *
 * - `lectura.maximo_paginas_renglones`: un archivo más largo queda
 *   `demasiadas_paginas` (que lo suba por meses);
 * - `lectura.maximo_paginas_ia_por_cliente`: las páginas leídas con IA de los
 *   registros del cliente en el año (paginasLeidasConIa) más las de este
 *   archivo no pueden pasar del tope; si pasan, queda `tope_alcanzado` y no se
 *   manda a OpenAI.
 *
 * La confianza del monto de cada renglón la calcula la plataforma
 * (CalculadoraConfianza::evaluarRenglon), nunca el modelo; la categoría queda
 * con su origen (`documento` si el papel la imprime, `ia` si la eligió el
 * modelo) para `CalculadoraConfianza::evaluarCategoria`.
 */
class LectorOpenAi implements LectorDocumentos
{
    public const NOMBRE = 'openai';

    /**
     * Páginas por llamada al leer renglones. Pocas, para que la respuesta (un
     * renglón por movimiento) no se corte: un extracto trae decenas de
     * movimientos por página. Por verificar con `lectura:medir-costo`.
     */
    public const PAGINAS_POR_BLOQUE = 5;

    /** `observaciones.sin_leer` de un archivo de renglones con más páginas que el máximo. */
    public const SIN_LEER_DEMASIADAS_PAGINAS = 'demasiadas_paginas';

    /** `observaciones.sin_leer` de un archivo que no se mandó a OpenAI porque el cliente llegó al tope. */
    public const SIN_LEER_TOPE = 'tope_alcanzado';

    public function __construct(
        private readonly PaginasDocumento $paginas,
        private readonly EsquemaLectura $esquemas,
        private readonly OpenAiClient $openai,
        private readonly CalculadoraConfianza $confianza,
        private readonly DocumentoAlmacen $almacen,
        private readonly LectorManual $manual,
        private readonly LectorHojas $hojas,
        private readonly CatalogoFiscal $catalogo,
    ) {}

    public function leer(Documento $documento, DocumentoLectura $lectura, string $codigo, int $anio): LecturaResultado
    {
        if ($this->esquemas->esPorRenglones($codigo, $anio)) {
            return in_array(strtolower((string) $documento->formato), ['xlsx', 'csv'], true)
                ? $this->hojas->leer($documento, $lectura, $codigo, $anio)
                : $this->leerRenglones($documento, $lectura, $codigo, $anio);
        }

        if (! array_key_exists($codigo, $this->esquemas->documentosLeibles($anio))) {
            return $this->manual->leer($documento, $lectura, $codigo, $anio);
        }

        $modelo = $this->modelo();

        $cantidad = $this->paginas->generar($documento, $lectura);

        $leido = $this->openai->leerConEsquema(
            $this->esquemas->instrucciones($codigo, $anio),
            $this->imagenes($lectura, 1, $cantidad),
            $this->esquemas->para($codigo, $anio),
            $this->nombreEsquema($codigo),
        );

        $pdf = $this->pdfEnDisco($documento);

        try {
            $casillas = $this->casillas($pdf, $codigo, $anio, (array) ($leido['casillas'] ?? []), ($leido['paginas_legibles'] ?? true) !== false);
        } finally {
            $this->borrar($pdf);
        }

        return new LecturaResultado(
            lector: self::NOMBRE,
            modelo: $modelo,
            tipoDetectado: is_string($leido['tipo_documento'] ?? null) ? $leido['tipo_documento'] : null,
            anioDetectado: is_int($leido['anio'] ?? null) ? $leido['anio'] : null,
            paginas: $cantidad,
            casillas: $casillas,
        );
    }

    /**
     * Las páginas que ya se leyeron con IA de los registros del negocio del
     * cliente en el año: la suma de `paginas` de las lecturas terminadas de
     * este lector en documentos de renglones de cualquier entrevista suya del
     * año, sin las que quedaron sin leer. Volver a leer un archivo cuenta otra
     * vez (es otra llamada); una lectura que falló no cuenta.
     */
    public function paginasLeidasConIa(DocumentoLectura $lectura, int $anio): int
    {
        $entrevista = EntrevistaDocumento::query()->with('entrevista')->find($lectura->entrevista_documento_id)?->entrevista;

        if ($entrevista === null) {
            return 0;
        }

        $codigos = $this->catalogo->documentos($anio)->where('lectura', 'renglones')->pluck('codigo')->all();

        return (int) DocumentoLectura::query()
            ->where('lector', self::NOMBRE)
            ->where('estado', EstadoLectura::Leida)
            ->whereKeyNot($lectura->id)
            ->whereHas('item', fn ($item) => $item
                ->whereIn('documento_codigo', $codigos)
                ->whereHas('entrevista', fn ($e) => $e->where('user_id', $entrevista->user_id)->where('tax_year', $anio)))
            ->get(['id', 'paginas', 'observaciones'])
            ->reject(fn (DocumentoLectura $l) => isset($l->observaciones['sin_leer']))
            ->sum('paginas');
    }

    /**
     * Un PDF o una foto de los registros del negocio, con IA y por bloques (6.3c).
     */
    private function leerRenglones(Documento $documento, DocumentoLectura $lectura, string $codigo, int $anio): LecturaResultado
    {
        $maximo = (int) ParametrosFiscales::valorRequerido($anio, 'lectura', 'maximo_paginas_renglones');
        $tope = (int) ParametrosFiscales::valorRequerido($anio, 'lectura', 'maximo_paginas_ia_por_cliente');

        try {
            $cantidad = $this->paginas->generar($documento, $lectura, $maximo);
        } catch (RuntimeException $error) {
            if ($error->getCode() !== PaginasDocumento::DEMASIADAS_PAGINAS) {
                throw $error;
            }

            return $this->sinLeer(self::SIN_LEER_DEMASIADAS_PAGINAS, 0, ['maximo' => $maximo]);
        }

        $usadas = $this->paginasLeidasConIa($lectura, $anio);

        // El tope no se pasa nunca: si este archivo no cabe entero, no se lee (ni una parte).
        if ($usadas + $cantidad > $tope) {
            return $this->sinLeer(self::SIN_LEER_TOPE, $cantidad, ['paginas' => $cantidad, 'usadas' => $usadas, 'tope' => $tope]);
        }

        $modelo = $this->modelo();
        $esquema = $this->esquemas->para($codigo, $anio);
        $bloques = [];

        for ($desde = 1; $desde <= $cantidad; $desde += self::PAGINAS_POR_BLOQUE) {
            $hasta = min($cantidad, $desde + self::PAGINAS_POR_BLOQUE - 1);

            $bloques[] = ['desde' => $desde, 'hasta' => $hasta, 'leido' => $this->openai->leerConEsquema(
                $this->esquemas->instrucciones($codigo, $anio, $desde, $hasta, $cantidad),
                $this->imagenes($lectura, $desde, $hasta),
                $esquema,
                $this->nombreEsquema($codigo),
            )];
        }

        $legibles = array_all($bloques, fn (array $b) => ($b['leido']['paginas_legibles'] ?? true) !== false);
        $pdf = $this->pdfEnDisco($documento);

        try {
            $casillas = $this->casillas($pdf, $codigo, $anio, $this->juntarCasillas($bloques), $legibles);
            $renglones = $this->renglones($pdf, $anio, $bloques);
        } finally {
            $this->borrar($pdf);
        }

        $primero = $bloques[0]['leido'];
        $clases = array_map(fn (array $b) => ClaseDetectada::tryFrom((string) ($b['leido']['clase'] ?? '')), $bloques);

        return new LecturaResultado(
            lector: self::NOMBRE,
            modelo: $modelo,
            tipoDetectado: is_string($primero['tipo_documento'] ?? null) ? $primero['tipo_documento'] : null,
            anioDetectado: is_int($primero['anio'] ?? null) ? $primero['anio'] : null,
            paginas: $cantidad,
            casillas: $casillas,
            renglones: $renglones,
            claseDetectada: array_find($clases, fn (?ClaseDetectada $c) => $c !== null && $c !== ClaseDetectada::Otro) ?? ClaseDetectada::Otro,
            periodoDesde: $this->fecha($casillas['periodo-desde']['valor'] ?? null),
            periodoHasta: $this->fecha($casillas['periodo-hasta']['valor'] ?? null),
        );
    }

    /**
     * Un archivo de renglones que no se manda a OpenAI: queda leído sin datos y
     * `observaciones` dice por qué (solo el motivo y conteos), que lee R-14.
     *
     * @param  array{maximo?: int, paginas?: int, usadas?: int, tope?: int}  $conteos
     */
    private function sinLeer(string $motivo, int $paginas, array $conteos): LecturaResultado
    {
        return new LecturaResultado(lector: self::NOMBRE, paginas: $paginas, observaciones: ['sin_leer' => $motivo, ...$conteos]);
    }

    /**
     * Las casillas fijas de todos los bloques en una: de cada casilla, la
     * primera lectura con valor (en el orden de las páginas); si ningún bloque
     * le vio valor, la del primero. La página pasa a ser la del archivo.
     *
     * @param  list<array{desde: int, hasta: int, leido: array<string, mixed>}>  $bloques
     * @return array<string, mixed>
     */
    private function juntarCasillas(array $bloques): array
    {
        $juntas = [];

        foreach ($bloques as $bloque) {
            foreach ((array) ($bloque['leido']['casillas'] ?? []) as $casilla => $una) {
                $una = (array) $una;
                $una['pagina'] = $this->paginaDelArchivo($una['pagina'] ?? null, $bloque['desde'], $bloque['hasta']);
                $conValor = isset($una['valor']) && is_scalar($una['valor']) && trim((string) $una['valor']) !== '';
                $anterior = $juntas[$casilla] ?? null;

                if ($anterior === null || ($conValor && ! (isset($anterior['valor']) && is_scalar($anterior['valor']) && trim((string) $anterior['valor']) !== ''))) {
                    $juntas[$casilla] = $una;
                }
            }
        }

        return $juntas;
    }

    /**
     * Los renglones de todos los bloques, en orden, con su confianza.
     *
     * @param  list<array{desde: int, hasta: int, leido: array<string, mixed>}>  $bloques
     * @return list<RenglonLeido>
     */
    private function renglones(?string $pdf, int $anio, array $bloques): array
    {
        $categorias = array_map('strval', array_keys($this->esquemas->categorias($anio)));
        $leidos = [];

        foreach ($bloques as $bloque) {
            $legibles = ($bloque['leido']['paginas_legibles'] ?? true) !== false;

            foreach ((array) ($bloque['leido']['renglones'] ?? []) as $uno) {
                if (is_array($uno)) {
                    $leidos[] = $this->renglonLeido($uno, $bloque['desde'], $bloque['hasta'], $legibles, $categorias);
                }
            }
        }

        // Cuántos movimientos de cada página tienen el mismo monto: si hay otro, no se puede comprobar cuál es cuál.
        $repetidos = [];

        foreach ($leidos as $leido) {
            if ($leido['pagina'] !== null && $leido['monto'] !== null) {
                $clave = "{$leido['pagina']}|{$leido['monto']}";
                $repetidos[$clave] = ($repetidos[$clave] ?? 0) + 1;
            }
        }

        $ubicador = new UbicadorEnPdf;
        $ubicados = [];
        $recuadrosPorGrupo = [];

        foreach ($leidos as $i => $leido) {
            $ubicados[$i] = $pdf !== null && $leido['monto'] !== null && $leido['pagina'] !== null && $leido['legibilidad'] !== Legibilidad::NoSeVe
                ? $this->ubicarEnSuPagina($ubicador, $pdf, $leido['monto'], $leido['pagina'], $leido['evidencia'])
                : null;

            if ($ubicados[$i] !== null) {
                $recuadrosPorGrupo["{$leido['pagina']}|{$leido['monto']}"][] = json_encode($ubicados[$i]);
            }
        }

        $renglones = [];

        foreach ($leidos as $i => $leido) {
            $ubicado = $ubicados[$i];
            $grupo = "{$leido['pagina']}|{$leido['monto']}";

            $evaluacion = $this->confianza->evaluarRenglon(
                $leido['monto'] ?? $leido['montoLeido'],
                $leido['legibilidad'],
                $ubicado !== null,
                ($repetidos[$grupo] ?? 0) > 1,
            );

            // Con montos repetidos en la página, el ubicador puede dar el mismo lugar a los dos
            // (la evidencia no siempre los separa): sin un recuadro propio, mejor ninguno que uno equivocado.
            $recuadros = $recuadrosPorGrupo[$grupo] ?? [];
            $recuadro = $ubicado === null || count($recuadros) !== count(array_unique($recuadros))
                ? null
                : ['x' => $ubicado['x'], 'y' => $ubicado['y'], 'ancho' => $ubicado['ancho'], 'alto' => $ubicado['alto']];

            $renglones[] = new RenglonLeido(
                numero: $i + 1,
                pagina: $ubicado['pagina'] ?? $leido['pagina'],
                fecha: $leido['fecha'],
                descripcion: $leido['descripcion'],
                monto: $leido['monto'],
                sentido: $leido['sentido'],
                categoria: $leido['categoria'],
                categoriaOrigen: $leido['categoriaOrigen'],
                confianza: $evaluacion['confianza'],
                motivo: $evaluacion['motivo'],
                legibilidad: $leido['legibilidad'],
                recuadro: $recuadro,
                evidencia: $leido['evidencia'],
            );
        }

        return $renglones;
    }

    /**
     * Un renglón como lo dio el modelo, limpio: el monto en positivo con dos
     * decimales (o null si no se ve o no tiene forma de monto), la página del
     * archivo y la categoría solo si es una de las opciones. Una categoría
     * manda sobre un sentido `no_se`; si contradice el sentido (un ingreso en
     * una línea de gasto), la categoría se descarta y el preparador decide.
     *
     * @param  array<string|int, mixed>  $uno
     * @param  list<string>  $categorias
     * @return array{pagina: int|null, fecha: CarbonImmutable|null, descripcion: string|null, monto: string|null, montoLeido: string|null, sentido: SentidoRenglon, categoria: string|null, categoriaOrigen: OrigenCategoria|null, legibilidad: Legibilidad, evidencia: string|null}
     */
    private function renglonLeido(array $uno, int $desde, int $hasta, bool $legibles, array $categorias): array
    {
        $texto = fn (mixed $v) => is_scalar($v) && trim((string) $v) !== '' ? trim((string) $v) : null;

        $legibilidad = Legibilidad::tryFrom((string) ($uno['legibilidad'] ?? '')) ?? Legibilidad::NoSeVe;

        if (! $legibles && $legibilidad === Legibilidad::Claro) {
            $legibilidad = Legibilidad::Dudoso;
        }

        $montoLeido = $texto($uno['monto'] ?? null);
        $sentido = SentidoRenglon::tryFrom((string) ($uno['sentido'] ?? '')) ?? SentidoRenglon::NoSe;
        $categoria = $texto($uno['categoria'] ?? null);
        $categoria = in_array($categoria, $categorias, true) ? $categoria : null;

        if ($categoria !== null) {
            $lado = $categoria === '1-sin-formulario' ? SentidoRenglon::Ingreso : SentidoRenglon::Gasto;

            if ($sentido === SentidoRenglon::NoSe) {
                $sentido = $lado;
            } elseif ($sentido !== $lado) {
                $categoria = null;
            }
        }

        return [
            'pagina' => $this->paginaDelArchivo($uno['pagina'] ?? null, $desde, $hasta),
            'fecha' => $this->fecha($uno['fecha'] ?? null),
            'descripcion' => $texto($uno['descripcion'] ?? null),
            'monto' => $this->monto($montoLeido),
            'montoLeido' => $montoLeido,
            'sentido' => $sentido,
            'categoria' => $categoria,
            'categoriaOrigen' => $categoria === null ? null : (($uno['categoria_impresa'] ?? false) === true ? OrigenCategoria::Documento : OrigenCategoria::Ia),
            'legibilidad' => $legibilidad,
            'evidencia' => $texto($uno['evidencia'] ?? null),
        ];
    }

    /**
     * El monto en positivo con dos decimales ("45.2" → "45.20"; "$1,200.00",
     * "-45.00" y "(45.00)" también valen: el signo lo dice el sentido). Null
     * si no tiene forma de monto.
     */
    private function monto(?string $leido): ?string
    {
        if ($leido === null) {
            return null;
        }

        $limpio = trim(str_replace(['$', ',', ' '], '', $leido), '()-+');

        if (preg_match('/^\d+(\.\d{1,2})?$/', $limpio) !== 1) {
            return null;
        }

        [$entero, $decimales] = array_pad(explode('.', $limpio), 2, '');
        $entero = ltrim($entero, '0');

        return ($entero === '' ? '0' : $entero).'.'.str_pad($decimales, 2, '0');
    }

    /**
     * El recuadro del monto si está en el texto de la página del renglón; en
     * otra página no cuenta. Un extracto imprime los retiros con signo o entre
     * paréntesis: se busca también en negativo.
     *
     * @return array{pagina: int, x: float, y: float, ancho: float, alto: float}|null
     */
    private function ubicarEnSuPagina(UbicadorEnPdf $ubicador, string $pdf, string $monto, int $pagina, ?string $evidencia): ?array
    {
        foreach ([$monto, "-{$monto}"] as $buscado) {
            $ubicado = $ubicador->buscar($pdf, $buscado, $pagina, $evidencia);

            if ($ubicado !== null && $ubicado['pagina'] === $pagina) {
                return $ubicado;
            }
        }

        return null;
    }

    /**
     * La página del archivo de lo que el modelo dijo en un bloque. Se le pide
     * la página del archivo; si contestó la posición de la imagen en el
     * bloque (1, 2…), se corrige. Fuera de los dos rangos, null.
     */
    private function paginaDelArchivo(mixed $pagina, int $desde, int $hasta): ?int
    {
        if (! is_int($pagina) || $pagina < 1) {
            return null;
        }

        return match (true) {
            $pagina >= $desde && $pagina <= $hasta => $pagina,
            $pagina <= $hasta - $desde + 1 => $desde + $pagina - 1,
            default => null,
        };
    }

    private function fecha(mixed $valor): ?CarbonImmutable
    {
        if (! is_string($valor) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) !== 1) {
            return null;
        }

        try {
            $fecha = CarbonImmutable::createFromFormat('!Y-m-d', $valor);
        } catch (Throwable) {
            return null;
        }

        return $fecha !== null && $fecha->format('Y-m-d') === $valor ? $fecha : null;
    }

    /**
     * Las páginas `$desde` a `$hasta` ya generadas, como data URL para OpenAI.
     *
     * @return list<string>
     */
    private function imagenes(DocumentoLectura $lectura, int $desde, int $hasta): array
    {
        $imagenes = [];

        for ($pagina = $desde; $pagina <= $hasta; $pagina++) {
            $imagenes[] = 'data:image/png;base64,'.base64_encode($this->paginas->leer($lectura, $pagina));
        }

        return $imagenes;
    }

    /**
     * @throws RuntimeException si falta la clave o el modelo de OpenAI
     */
    private function modelo(): string
    {
        $modelo = (string) config('services.openai.vision_model');

        throw_if($modelo === '' || (string) config('services.openai.api_key') === '', new RuntimeException('Falta configurar OPENAI_API_KEY y OPENAI_VISION_MODEL para leer documentos.'));

        return $modelo;
    }

    private function nombreEsquema(string $codigo): string
    {
        return 'lectura_'.preg_replace('/[^A-Za-z0-9]+/', '_', $codigo);
    }

    /**
     * @param  array<string|int, mixed>  $leidas
     * @return array<string, array{valor: string|null, legibilidad: Legibilidad, confianza: float, motivo: MotivoConfianza, pagina: int|null, recuadro: array{x: float, y: float, ancho: float, alto: float}|null, evidencia: string|null}>
     */
    private function casillas(?string $pdf, string $codigo, int $anio, array $leidas, bool $paginasLegibles): array
    {
        $ubicador = new UbicadorEnPdf;
        $resultado = [];

        foreach ($this->esquemas->casillas($codigo, $anio) as $casilla) {
            $una = (array) ($leidas[$casilla['casilla']] ?? []);
            $valor = isset($una['valor']) && is_scalar($una['valor']) ? trim((string) $una['valor']) : null;
            $valor = $valor === '' ? null : $valor;
            $legibilidad = Legibilidad::tryFrom((string) ($una['legibilidad'] ?? '')) ?? Legibilidad::NoSeVe;

            // Con las hojas borrosas o cortadas, nada se da por claro.
            if (! $paginasLegibles && $legibilidad === Legibilidad::Claro) {
                $legibilidad = Legibilidad::Dudoso;
            }

            $pagina = isset($una['pagina']) && is_int($una['pagina']) && $una['pagina'] > 0 ? $una['pagina'] : null;
            $evidencia = isset($una['texto_cercano']) && is_string($una['texto_cercano']) && trim($una['texto_cercano']) !== '' ? trim($una['texto_cercano']) : null;

            $ubicado = $pdf !== null && $valor !== null && $legibilidad !== Legibilidad::NoSeVe
                ? $ubicador->buscar($pdf, $valor, $pagina, $evidencia)
                : null;

            $evaluacion = $this->confianza->evaluar($casilla, $valor, $legibilidad, $ubicado !== null);

            $resultado[$casilla['casilla']] = [
                'valor' => $valor,
                'legibilidad' => $legibilidad,
                'confianza' => $evaluacion['confianza'],
                'motivo' => $evaluacion['motivo'],
                'pagina' => $ubicado['pagina'] ?? $pagina,
                'recuadro' => $ubicado === null ? null : ['x' => $ubicado['x'], 'y' => $ubicado['y'], 'ancho' => $ubicado['ancho'], 'alto' => $ubicado['alto']],
                'evidencia' => $evidencia,
            ];
        }

        return $resultado;
    }

    private function borrar(?string $pdf): void
    {
        if ($pdf !== null) {
            @unlink($pdf);
        }
    }

    /**
     * Si el archivo es un PDF, una copia temporal descifrada para `pdftotext`
     * (que necesita un archivo); se borra siempre al terminar. Una foto o un
     * HEIC no tienen capa de texto: null.
     */
    private function pdfEnDisco(Documento $documento): ?string
    {
        $contenido = $this->almacen->leer($documento);

        if ($contenido === null || ! str_starts_with($contenido, '%PDF')) {
            return null;
        }

        $ruta = tempnam(sys_get_temp_dir(), 'lectura-');

        throw_if($ruta === false || file_put_contents($ruta, $contenido) === false, new RuntimeException('No se pudo preparar el PDF para ubicar los valores.'));

        return $ruta;
    }
}
