<?php

namespace App\Services\Lectura;

use App\Enums\Lectura\ClaseDetectada;
use App\Enums\Lectura\Legibilidad;
use App\Enums\Lectura\MotivoConfianza;
use Carbon\CarbonImmutable;

/**
 * Lo que devolvió un lector al leer un archivo (F4.3d), listo para guardar con
 * `DatosDocumento::guardarLectura()`. La confianza y su motivo ya vienen
 * calculados por la plataforma (CalculadoraConfianza), no por el modelo.
 *
 * `casillas` va por casilla del catálogo. Un lector que no lee nada (el
 * manual) devuelve la lista vacía: los datos los escribe el preparador.
 *
 * Los documentos de renglones (F6 6.3b) traen además `renglones`, la clase del
 * archivo, su período y `observaciones`: lo que la hoja trajo y no se reconoció
 * (solo conteos y letras de columna, nunca valores), que lee la regla R-14. Un
 * PDF o una foto que no se mandó a la IA (6.3c) lleva `sin_leer` (el motivo) y
 * sus conteos (`maximo`, o `paginas`, `usadas` y `tope`).
 */
final readonly class LecturaResultado
{
    /**
     * @param  string  $lector  quién leyó: `openai`, `hoja` o `manual`
     * @param  string|null  $tipoDetectado  el código del formulario que el lector vio, o `desconocido`
     * @param  array<string, array{valor: string|null, legibilidad: Legibilidad, confianza: float, motivo: MotivoConfianza, pagina: int|null, recuadro: array{x: float, y: float, ancho: float, alto: float}|null, evidencia: string|null}>  $casillas
     * @param  list<RenglonLeido>  $renglones
     * @param  array{columnas_no_reconocidas?: list<string>, filas_sin_monto?: int, formulas_sin_valor?: int, filas_sin_fecha?: int, hojas_sin_leer?: int, formato_no_reconocido?: bool, sin_leer?: string, maximo?: int, paginas?: int, usadas?: int, tope?: int}|null  $observaciones
     */
    public function __construct(
        public string $lector,
        public ?string $modelo = null,
        public ?string $tipoDetectado = null,
        public ?int $anioDetectado = null,
        public int $paginas = 0,
        public array $casillas = [],
        public array $renglones = [],
        public ?ClaseDetectada $claseDetectada = null,
        public ?CarbonImmutable $periodoDesde = null,
        public ?CarbonImmutable $periodoHasta = null,
        public ?array $observaciones = null,
    ) {}
}
