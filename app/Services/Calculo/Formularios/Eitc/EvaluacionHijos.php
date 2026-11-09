<?php

namespace App\Services\Calculo\Formularios\Eitc;

/**
 * Qué dependientes cumplen las condiciones de hijo calificado del EIC (instrucciones del Form 1040 de 2025, p. 41,
 * Paso 3). Solo ids (referencias): nunca nombres ni SSN.
 */
final readonly class EvaluacionHijos
{
    /**
     * @param  list<int>  $calificados  hijos que cumplen parentesco, convivencia y edad (con o sin SSN válido)
     * @param  list<int>  $conSsnValido  los de la lista anterior con SSN válido para el EIC: los que cuentan para la columna de la tabla
     * @param  list<string>  $faltantes  lo que falta saber, en palabras
     */
    public function __construct(
        public array $calificados = [],
        public array $conSsnValido = [],
        public array $faltantes = [],
    ) {}

    public function hayHijoCalificado(): bool
    {
        return $this->calificados !== [];
    }

    /** Columna de hijos de la EIC Table: 0, 1, 2 o 3 ("3 o más"). */
    public function hijosParaLaTabla(): int
    {
        return min(3, count($this->conSsnValido));
    }
}
