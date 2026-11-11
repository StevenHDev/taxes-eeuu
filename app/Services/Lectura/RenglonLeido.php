<?php

namespace App\Services\Lectura;

use App\Enums\Lectura\Legibilidad;
use App\Enums\Lectura\MotivoConfianza;
use App\Enums\Lectura\OrigenCategoria;
use App\Enums\Lectura\SentidoRenglon;
use Carbon\CarbonImmutable;

/**
 * Un renglón que leyó un lector de los registros del negocio (F6 6.3b), listo
 * para guardar con `DatosDocumento::guardarLectura()`. `monto` es un decimal
 * en texto, positivo ("45.20"); la dirección la dice `sentido`. Es nulo solo
 * en un renglón leído con IA cuyo monto no se ve o no tiene forma de monto
 * (6.3c): queda con confianza baja y no suma hasta que el preparador lo escriba. `numero` es el
 * orden en el archivo y `pagina` la página (o la fila, en una hoja).
 */
final readonly class RenglonLeido
{
    /**
     * @param  array{x: float, y: float, ancho: float, alto: float}|null  $recuadro  nulo en las hojas
     */
    public function __construct(
        public int $numero,
        public ?int $pagina,
        public ?CarbonImmutable $fecha,
        public ?string $descripcion,
        public ?string $monto,
        public SentidoRenglon $sentido,
        public ?string $categoria,
        public ?OrigenCategoria $categoriaOrigen,
        public float $confianza,
        public MotivoConfianza $motivo,
        public Legibilidad $legibilidad = Legibilidad::Claro,
        public ?array $recuadro = null,
        public ?string $evidencia = null,
    ) {}
}
