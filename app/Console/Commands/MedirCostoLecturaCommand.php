<?php

namespace App\Console\Commands;

use App\Services\Lectura\EsquemaLectura;
use App\Services\Lectura\LectorOpenAi;
use App\Services\Lectura\PaginasDocumento;
use App\Services\WhatsappAgent\OpenAiClient;
use App\Support\ParametrosFiscales;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\Event;
use Throwable;

/**
 * Mide cuánto cuesta leer con OpenAI los documentos de montos (F6 6.3c,
 * decisión D): lee los fixtures sintéticos de `tests/Fixtures/lectura/montos/`
 * igual que la plataforma (las mismas páginas, el mismo esquema y, en los
 * registros del negocio, los mismos bloques de páginas) y escribe por archivo
 * las páginas, las llamadas, los tokens de entrada y de salida, los segundos y
 * el costo estimado con la tarifa que se le pase (no hay tarifa fija en el
 * código: cambia por modelo y con el tiempo).
 *
 * Es una herramienta local de desarrollo y llama a OpenAI de verdad: se niega
 * a correr en producción y sin la clave. No guarda nada ni muestra lo leído,
 * solo conteos.
 */
class MedirCostoLecturaCommand extends Command
{
    protected $signature = 'lectura:medir-costo
        {--tarifa-entrada= : USD por millón de tokens de entrada del modelo}
        {--tarifa-salida= : USD por millón de tokens de salida del modelo}
        {--anio=2025 : Año fiscal del catálogo con el que se lee}
        {--archivo=* : Solo estos fixtures (nombre del archivo)}';

    protected $description = 'Mide páginas, llamadas, tokens, tiempo y costo de leer con OpenAI los documentos sintéticos de montos (solo local)';

    /** Qué documento del catálogo es cada fixture, por el comienzo de su nombre; el resto son registros del negocio. */
    private const DOCUMENTO_POR_PREFIJO = [
        'recibo-guarderia' => 'recibo-cuidado',
        'comprobante-' => 'comprobantes-pagos',
        'recibo-util-escolar' => 'recibos-utiles-educador',
    ];

    /** Lo que se lee con IA; las hojas (`xlsx`, `csv`) no pasan por OpenAI. */
    private const FORMATOS = ['pdf', 'jpg', 'jpeg', 'png', 'heic'];

    public function handle(PaginasDocumento $paginas, EsquemaLectura $esquemas, OpenAiClient $openai): int
    {
        if (app()->environment('production')) {
            $this->error(__('Este comando no corre en producción: llama a OpenAI de verdad con los documentos de prueba.'));

            return self::FAILURE;
        }

        if ((string) config('services.openai.api_key') === '' || (string) config('services.openai.vision_model') === '') {
            $this->error(__('Falta la clave de desarrollo de OpenAI (OPENAI_API_KEY) o el modelo (OPENAI_VISION_MODEL).'));

            return self::FAILURE;
        }

        $anio = (int) $this->option('anio');
        $tarifaEntrada = $this->tarifa('tarifa-entrada');
        $tarifaSalida = $this->tarifa('tarifa-salida');
        $archivos = $this->archivos();

        if ($archivos === []) {
            $this->error(__('No hay fixtures que leer en tests/Fixtures/lectura/montos/.'));

            return self::FAILURE;
        }

        $uso = ['entrada' => 0, 'salida' => 0];

        Event::listen(ResponseReceived::class, function (ResponseReceived $evento) use (&$uso) {
            if (str_contains($evento->request->url(), 'chat/completions')) {
                $uso['entrada'] += (int) $evento->response->json('usage.prompt_tokens', 0);
                $uso['salida'] += (int) $evento->response->json('usage.completion_tokens', 0);
            }
        });

        $filas = [];

        foreach ($archivos as $ruta) {
            $nombre = basename($ruta);
            $codigo = $this->documento($nombre);
            $uso = ['entrada' => 0, 'salida' => 0];
            $inicio = hrtime(true);
            $llamadas = 0;
            $paginasDelArchivo = 0;
            $renglones = 0;
            $error = null;

            try {
                $porRenglones = $esquemas->esPorRenglones($codigo, $anio);
                $maximo = $porRenglones ? (int) ParametrosFiscales::valorRequerido($anio, 'lectura', 'maximo_paginas_renglones') : null;
                $imagenes = $paginas->imagenes((string) file_get_contents($ruta), pathinfo($ruta, PATHINFO_EXTENSION), $maximo);
                $paginasDelArchivo = count($imagenes);
                $bloque = $porRenglones ? LectorOpenAi::PAGINAS_POR_BLOQUE : max(1, $paginasDelArchivo);
                $esquema = $esquemas->para($codigo, $anio);

                foreach (array_chunk($imagenes, $bloque) as $i => $pngs) {
                    $desde = $i * $bloque + 1;
                    $instrucciones = $porRenglones
                        ? $esquemas->instrucciones($codigo, $anio, $desde, $desde + count($pngs) - 1, $paginasDelArchivo)
                        : $esquemas->instrucciones($codigo, $anio);

                    $leido = $openai->leerConEsquema(
                        $instrucciones,
                        array_map(fn (string $png) => 'data:image/png;base64,'.base64_encode($png), $pngs),
                        $esquema,
                        'lectura_'.preg_replace('/[^A-Za-z0-9]+/', '_', $codigo),
                    );

                    $llamadas++;
                    $renglones += count((array) ($leido['renglones'] ?? []));
                }
            } catch (Throwable $e) {
                // Solo la clase del error: el mensaje de OpenAI puede repetir parte del documento.
                $error = class_basename($e);
            }

            $filas[] = [
                'archivo' => $nombre,
                'documento' => $codigo,
                'paginas' => $paginasDelArchivo,
                'llamadas' => $llamadas,
                'renglones' => $renglones,
                'entrada' => $uso['entrada'],
                'salida' => $uso['salida'],
                'segundos' => round((hrtime(true) - $inicio) / 1e9, 1),
                'error' => $error,
            ];
        }

        $this->table(
            [__('Archivo'), __('Documento'), __('Páginas'), __('Llamadas'), __('Renglones'), __('Tokens de entrada'), __('Tokens de salida'), __('Segundos'), __('Costo USD'), __('Error')],
            [
                ...array_map(fn (array $f) => [...array_slice($f, 0, 8), $this->costo($f['entrada'], $f['salida'], $tarifaEntrada, $tarifaSalida), $f['error'] ?? ''], $filas),
                $this->total($filas, $tarifaEntrada, $tarifaSalida),
            ],
        );

        if ($tarifaEntrada === null || $tarifaSalida === null) {
            $this->warn(__('Sin --tarifa-entrada y --tarifa-salida no se calcula el costo: pásalas en USD por millón de tokens, con la tarifa vigente del modelo.'));
        }

        $paginasTotales = array_sum(array_column($filas, 'paginas'));

        if ($paginasTotales > 0 && $tarifaEntrada !== null && $tarifaSalida !== null) {
            $costo = $this->costoNumero((int) array_sum(array_column($filas, 'entrada')), (int) array_sum(array_column($filas, 'salida')), $tarifaEntrada, $tarifaSalida);
            $this->info(__('Costo medio por página: USD :costo (modelo :modelo).', ['costo' => number_format($costo / $paginasTotales, 4, '.', ''), 'modelo' => (string) config('services.openai.vision_model')]));
        }

        return array_filter(array_column($filas, 'error')) === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return list<string>
     */
    private function archivos(): array
    {
        $todos = glob(base_path('tests/Fixtures/lectura/montos/*')) ?: [];
        $pedidos = array_map('strval', (array) $this->option('archivo'));

        return array_values(array_filter($todos, fn (string $ruta) => is_file($ruta)
            && in_array(strtolower(pathinfo($ruta, PATHINFO_EXTENSION)), self::FORMATOS, true)
            && ($pedidos === [] || in_array(basename($ruta), $pedidos, true))));
    }

    private function documento(string $nombre): string
    {
        foreach (self::DOCUMENTO_POR_PREFIJO as $prefijo => $codigo) {
            if (str_starts_with($nombre, $prefijo)) {
                return $codigo;
            }
        }

        return 'registros-ingresos-gastos';
    }

    private function tarifa(string $opcion): ?float
    {
        $valor = $this->option($opcion);

        return is_numeric($valor) ? (float) $valor : null;
    }

    private function costo(int $entrada, int $salida, ?float $tarifaEntrada, ?float $tarifaSalida): string
    {
        return $tarifaEntrada === null || $tarifaSalida === null
            ? '—'
            : number_format($this->costoNumero($entrada, $salida, $tarifaEntrada, $tarifaSalida), 4, '.', '');
    }

    private function costoNumero(int $entrada, int $salida, float $tarifaEntrada, float $tarifaSalida): float
    {
        return $entrada / 1_000_000 * $tarifaEntrada + $salida / 1_000_000 * $tarifaSalida;
    }

    /**
     * @param  list<array{paginas: int, llamadas: int, renglones: int, entrada: int, salida: int, segundos: float}>  $filas
     * @return list<string|int|float>
     */
    private function total(array $filas, ?float $tarifaEntrada, ?float $tarifaSalida): array
    {
        $suma = fn (string $columna) => array_sum(array_column($filas, $columna));
        $entrada = (int) $suma('entrada');
        $salida = (int) $suma('salida');

        return [
            __('Total'), '', $suma('paginas'), $suma('llamadas'), $suma('renglones'), $entrada, $salida,
            round($suma('segundos'), 1), $this->costo($entrada, $salida, $tarifaEntrada, $tarifaSalida), '',
        ];
    }
}
