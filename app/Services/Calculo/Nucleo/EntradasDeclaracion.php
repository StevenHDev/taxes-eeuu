<?php

namespace App\Services\Calculo\Nucleo;

use App\Enums\Expediente\EstadoCivilIrs;

/** Solo la forma; la llena ArmadorEntradas (6.4i). */
final readonly class EntradasDeclaracion
{
    /**
     * @param  list<PersonaEntrada>  $personas
     * @param  array<string, DatoEntrada>  $datos  clave 'documento:casilla' o 'categoria:linea'; el total de todos los documentos de ese tipo
     * @param  array<string, Dinero>  $sumasRegistros  clave = línea del Schedule C
     * @param  array<string, RespuestaEntrada>  $respuestas  clave = código de pregunta
     * @param  array<string, list<DocumentoEntrada>>  $documentos  cada documento por separado, por código (F6 6.4z): para lo que va por persona, proveedor, póliza o comprobante
     */
    public function __construct(
        public int $taxYear,
        public ?EstadoCivilIrs $estadoCivil,
        public array $personas = [],
        public array $datos = [],
        public array $sumasRegistros = [],
        public array $respuestas = [],
        public array $documentos = [],
    ) {}

    /**
     * Los documentos de un tipo, cada uno con sus casillas.
     *
     * @return list<DocumentoEntrada>
     */
    public function documentos(string $codigo): array
    {
        return $this->documentos[$codigo] ?? [];
    }
}
