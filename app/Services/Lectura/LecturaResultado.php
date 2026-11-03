<?php

namespace App\Services\Lectura;

use App\Enums\Lectura\Legibilidad;
use App\Enums\Lectura\MotivoConfianza;

/**
 * Lo que devolvió un lector al leer un archivo (F4.3d), listo para guardar con
 * `DatosDocumento::guardarLectura()`. La confianza y su motivo ya vienen
 * calculados por la plataforma (CalculadoraConfianza), no por el modelo.
 *
 * `casillas` va por casilla del catálogo. Un lector que no lee nada (el
 * manual) devuelve la lista vacía: los datos los escribe el preparador.
 */
final readonly class LecturaResultado
{
    /**
     * @param  string  $lector  quién leyó: `openai` o `manual`
     * @param  string|null  $tipoDetectado  el código del formulario que el lector vio, o `desconocido`
     * @param  array<string, array{valor: string|null, legibilidad: Legibilidad, confianza: float, motivo: MotivoConfianza, pagina: int|null, recuadro: array{x: float, y: float, ancho: float, alto: float}|null, evidencia: string|null}>  $casillas
     */
    public function __construct(
        public string $lector,
        public ?string $modelo = null,
        public ?string $tipoDetectado = null,
        public ?int $anioDetectado = null,
        public int $paginas = 0,
        public array $casillas = [],
    ) {}
}
