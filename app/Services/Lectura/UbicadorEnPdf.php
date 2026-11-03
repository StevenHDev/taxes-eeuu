<?php

namespace App\Services\Lectura;

use DOMDocument;
use DOMElement;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Busca un valor leído en la capa de texto de un PDF y devuelve su recuadro
 * exacto (F4.3c). Sirve para dos cosas: si el valor está en el texto, la
 * confianza sube a 0,99 (CalculadoraConfianza), y el visor resalta el recuadro
 * sobre la página (4.6).
 *
 * Las posiciones salen de `pdftotext -bbox-layout` (poppler-utils, el mismo
 * paquete que ya hace falta para `pdftoppm`) y no de `smalot/pdfparser`: ese
 * da las coordenadas sin la transformación de la página (en una hoja de
 * 792 pt de alto devolvía y = 2680) y letra por letra, así que un recuadro
 * calculado con él sería inventado. poppler entrega cada palabra con su caja
 * ya en la hoja.
 *
 * Nunca se inventa un recuadro: un PDF escaneado o una foto no tienen capa de
 * texto y dan null, igual que un valor que no aparece. Para no dar un 0,99 por
 * casualidad, no se buscan los valores demasiado cortos (menos de 3
 * caracteres) ni los enteros chicos de menos de 4 dígitos: "2" o "D" están en
 * casi cualquier formulario.
 *
 * Por cada PDF se corre `pdftotext` una sola vez por instancia (queda en
 * memoria): quien lea un documento debe usar la misma instancia para todas
 * sus casillas.
 */
class UbicadorEnPdf
{
    /** Menos caracteres que esto coinciden con cualquier cosa. */
    private const LARGO_MINIMO = 3;

    /**
     * @var array<string, array<int, array{ancho: float, alto: float, lineas: list<list<array{t: string, x0: float, y0: float, x1: float, y1: float}>>}>>
     */
    private array $memoria = [];

    /**
     * El recuadro del valor, o null si el PDF no tiene texto o el valor no está.
     *
     * @param  int|null  $pagina  la página donde el modelo dijo que estaba; se mira primero, pero si no está ahí se busca en las demás
     * @param  string|null  $textoCercano  el texto que el modelo vio junto al valor: si el valor sale varias veces (el mismo monto en dos casillas), gana el que tiene ese texto en su renglón
     * @return array{pagina: int, x: float, y: float, ancho: float, alto: float}|null coordenadas entre 0 y 1, con el origen arriba a la izquierda
     *
     * @throws RuntimeException si `pdftotext` no está instalado
     */
    public function buscar(string $rutaPdf, string $valor, ?int $pagina = null, ?string $textoCercano = null): ?array
    {
        $buscado = $this->preparar($valor);

        if ($buscado === null) {
            return null;
        }

        $paginas = $this->paginas($rutaPdf);
        $orden = array_keys($paginas);

        if ($pagina !== null && isset($paginas[$pagina])) {
            $orden = [$pagina, ...array_values(array_diff($orden, [$pagina]))];
        }

        foreach ($orden as $numero) {
            $candidatos = $this->coincidencias($paginas[$numero], $buscado);

            if ($candidatos === []) {
                continue;
            }

            return $this->recuadro($numero, $paginas[$numero], $this->elegir($candidatos, $textoCercano));
        }

        return null;
    }

    /**
     * Cómo se compara el valor: como número (un monto da lo mismo escrito
     * 45120.00 que 45,120.00) o como texto; null si no se puede buscar con
     * seguridad.
     *
     * @return array{tipo: 'numero', numero: float}|array{tipo: 'texto', tokens: list<string>}|null
     */
    private function preparar(string $valor): ?array
    {
        $valor = trim($valor);

        if (preg_match('/^-?\d+(\.\d+)?$/', $valor)) {
            $conDecimales = str_contains($valor, '.');
            $digitos = strlen(preg_replace('/\D/', '', $valor));

            return $conDecimales || $digitos >= 4 ? ['tipo' => 'numero', 'numero' => (float) $valor] : null;
        }

        if (mb_strlen(str_replace(' ', '', $valor)) < self::LARGO_MINIMO) {
            return null;
        }

        $tokens = array_values(array_filter(array_map($this->normalizar(...), preg_split('/\s+/', $valor) ?: []), fn (string $t) => $t !== ''));

        return $tokens === [] ? null : ['tipo' => 'texto', 'tokens' => $tokens];
    }

    /**
     * @param  array{ancho: float, alto: float, lineas: list<list<array{t: string, x0: float, y0: float, x1: float, y1: float}>>}  $pagina
     * @param  array<string, mixed>  $buscado
     * @return list<array{renglon: list<array{t: string, x0: float, y0: float, x1: float, y1: float}>, palabras: list<array{t: string, x0: float, y0: float, x1: float, y1: float}>}>
     */
    private function coincidencias(array $pagina, array $buscado): array
    {
        $encontradas = [];

        foreach ($pagina['lineas'] as $palabras) {
            if ($buscado['tipo'] === 'numero') {
                foreach ($palabras as $palabra) {
                    $numero = $this->comoNumero($palabra['t']);

                    if ($numero !== null && abs($numero - $buscado['numero']) < 0.005) {
                        $encontradas[] = ['renglon' => $palabras, 'palabras' => [$palabra]];
                    }
                }

                continue;
            }

            $normales = array_map(fn (array $p) => $this->normalizar($p['t']), $palabras);
            $largo = count($buscado['tokens']);

            for ($desde = 0; $desde + $largo <= count($normales); $desde++) {
                if (array_slice($normales, $desde, $largo) === $buscado['tokens']) {
                    $encontradas[] = ['renglon' => $palabras, 'palabras' => array_slice($palabras, $desde, $largo)];
                }
            }
        }

        return $encontradas;
    }

    /**
     * Con varias coincidencias, la que tiene el texto cercano en su renglón;
     * si ninguna, la primera.
     *
     * @param  list<array{renglon: list<array{t: string, x0: float, y0: float, x1: float, y1: float}>, palabras: list<array{t: string, x0: float, y0: float, x1: float, y1: float}>}>  $candidatos
     * @return array{renglon: list<array{t: string, x0: float, y0: float, x1: float, y1: float}>, palabras: list<array{t: string, x0: float, y0: float, x1: float, y1: float}>}
     */
    private function elegir(array $candidatos, ?string $textoCercano): array
    {
        $tokens = array_values(array_filter(array_map($this->normalizar(...), preg_split('/\s+/', (string) $textoCercano) ?: []), fn (string $t) => $t !== ''));

        if (count($candidatos) === 1 || $tokens === []) {
            return $candidatos[0];
        }

        foreach ($candidatos as $candidato) {
            $renglon = array_map(fn (array $p) => $this->normalizar($p['t']), $candidato['renglon']);

            if (array_diff($tokens, $renglon) === []) {
                return $candidato;
            }
        }

        return $candidatos[0];
    }

    /**
     * @param  array{ancho: float, alto: float, lineas: list<list<array{t: string, x0: float, y0: float, x1: float, y1: float}>>}  $pagina
     * @param  array{renglon: list<array{t: string, x0: float, y0: float, x1: float, y1: float}>, palabras: list<array{t: string, x0: float, y0: float, x1: float, y1: float}>}  $candidato
     * @return array{pagina: int, x: float, y: float, ancho: float, alto: float}
     */
    private function recuadro(int $numero, array $pagina, array $candidato): array
    {
        $x0 = min(array_column($candidato['palabras'], 'x0'));
        $y0 = min(array_column($candidato['palabras'], 'y0'));
        $x1 = max(array_column($candidato['palabras'], 'x1'));
        $y1 = max(array_column($candidato['palabras'], 'y1'));

        $entre = fn (float $v) => round(max(0.0, min(1.0, $v)), 4);

        return [
            'pagina' => $numero,
            'x' => $entre($x0 / $pagina['ancho']),
            'y' => $entre($y0 / $pagina['alto']),
            'ancho' => $entre(($x1 - $x0) / $pagina['ancho']),
            'alto' => $entre(($y1 - $y0) / $pagina['alto']),
        ];
    }

    /**
     * "$45,120.00", "(1,200.50)" y "4512.00," son números; "Form" no.
     */
    private function comoNumero(string $texto): ?float
    {
        $limpio = rtrim(str_replace(['$', ' '], '', $texto), ',;:.');

        if (! preg_match('/^(\()?-?[\d,]*\d(\.\d+)?\)?$/', $limpio)) {
            return null;
        }

        $numero = (float) str_replace(['(', ')', ','], '', $limpio);

        return str_starts_with($limpio, '(') ? -abs($numero) : $numero;
    }

    private function normalizar(string $texto): string
    {
        return trim(mb_strtolower($texto), " \t\n\r.,;:()\"'");
    }

    /**
     * Las palabras de cada página con su caja, por renglón. Vacío si el PDF no
     * tiene texto (escaneado) o no se pudo leer.
     *
     * @return array<int, array{ancho: float, alto: float, lineas: list<list<array{t: string, x0: float, y0: float, x1: float, y1: float}>>}>
     */
    private function paginas(string $rutaPdf): array
    {
        $clave = (realpath($rutaPdf) ?: $rutaPdf).'|'.(@filemtime($rutaPdf) ?: 0).'|'.(@filesize($rutaPdf) ?: 0);

        return $this->memoria[$clave] ??= $this->leerPaginas($rutaPdf);
    }

    /**
     * @return array<int, array{ancho: float, alto: float, lineas: list<list<array{t: string, x0: float, y0: float, x1: float, y1: float}>>}>
     */
    private function leerPaginas(string $rutaPdf): array
    {
        $binario = (new ExecutableFinder)->find('pdftotext');

        throw_if($binario === null, new RuntimeException('pdftotext (poppler-utils) no está instalado en este host: es requisito de infraestructura para ubicar los valores en un PDF.'));

        $proceso = new Process([$binario, '-bbox-layout', $rutaPdf, '-']);
        $proceso->setTimeout(60);
        $proceso->run();

        // Un PDF dañado o cifrado no tiene texto que ubicar: no es un error de la plataforma.
        if (! $proceso->isSuccessful()) {
            return [];
        }

        $documento = new DOMDocument;
        $anterior = libxml_use_internal_errors(true);
        $cargado = $documento->loadXML($proceso->getOutput(), LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        if (! $cargado) {
            return [];
        }

        $paginas = [];

        foreach ($documento->getElementsByTagName('page') as $n => $pagina) {
            /** @var DOMElement $pagina */
            $ancho = (float) $pagina->getAttribute('width');
            $alto = (float) $pagina->getAttribute('height');

            if ($ancho <= 0 || $alto <= 0) {
                continue;
            }

            $lineas = [];

            foreach ($pagina->getElementsByTagName('line') as $linea) {
                $palabras = [];

                foreach ($linea->getElementsByTagName('word') as $palabra) {
                    $palabras[] = [
                        't' => $palabra->textContent,
                        'x0' => (float) $palabra->getAttribute('xMin'),
                        'y0' => (float) $palabra->getAttribute('yMin'),
                        'x1' => (float) $palabra->getAttribute('xMax'),
                        'y1' => (float) $palabra->getAttribute('yMax'),
                    ];
                }

                if ($palabras !== []) {
                    $lineas[] = $palabras;
                }
            }

            $paginas[$n + 1] = ['ancho' => $ancho, 'alto' => $alto, 'lineas' => $lineas];
        }

        return $paginas;
    }
}
