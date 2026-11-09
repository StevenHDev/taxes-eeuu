<?php

namespace App\Services\Calculo\Nucleo;

interface Calculadora
{
    /** Código del formulario que escribe, como en el catálogo ('1040', 'sch-c'…). */
    public function formulario(): string;

    /** Escribe solo sus líneas en la declaración; lee las de otros con Declaracion::linea(). */
    public function calcular(EntradasDeclaracion $e, Declaracion $d): void;
}
