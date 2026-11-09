<?php

namespace App\Services\Lectura;

use App\Models\Expediente\Expediente;
use Illuminate\Container\Attributes\Bind;

/**
 * Las sumas por categoría de los registros del negocio de un cliente (F6
 * 6.3b). Las calculadoras del motor dependen solo de esta interfaz; en sus
 * pruebas usan una implementación simulada. El tramo 2 (6.3d) agrega el
 * cuadre y la conciliación con los 1099 sin cambiar este contrato.
 *
 * La implementación se registra con el atributo `Bind` (sin tocar el proveedor).
 */
#[Bind(SumasRegistrosPorRenglones::class)]
interface SumasRegistros
{
    public function para(Expediente $expediente): ResultadoSumas;
}
