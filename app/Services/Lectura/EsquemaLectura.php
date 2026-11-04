<?php

namespace App\Services\Lectura;

use App\Enums\Lectura\Legibilidad;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use InvalidArgumentException;

/**
 * El esquema y las instrucciones con los que se le pide a OpenAI que lea un
 * documento (F4.3b). Salen de las casillas del catálogo del año: el lector
 * no "busca campos sueltos", lee las casillas del formulario oficial
 * (documento madre, 6.1.2), y cambiar una casilla en el catálogo cambia lo
 * que se le pide sin tocar código.
 *
 * El esquema es estricto (todas las propiedades en `required`, sin
 * `additionalProperties`): por cada casilla el modelo contesta siempre las
 * mismas cuatro cosas, y lo que no ve lo marca `no_se_ve` con valor nulo en
 * vez de inventarlo. La confianza no la da el modelo: la calcula la
 * plataforma (CalculadoraConfianza, 4.3c).
 */
class EsquemaLectura
{
    /** Lo que dice `tipo_documento` cuando el documento no es ninguno de los que se leen. */
    public const DESCONOCIDO = 'desconocido';

    /**
     * Cómo escribir el valor según el tipo de dato de la casilla.
     */
    private const FORMATO_DEL_VALOR = [
        'monto' => 'a plain decimal number such as 45120.00, with no currency symbol and no thousands separators',
        'numero' => 'a plain integer',
        'fecha' => 'a date as YYYY-MM-DD',
        'anio' => 'a four-digit year',
        'si_no' => 'the string "si" if the box is checked or the statement is true, "no" if it is clearly not',
        'ssn' => 'the number exactly as printed, for example 123-45-6789 or XXX-XX-6789 if it is masked',
        'ein' => 'the number exactly as printed, for example 12-3456789',
        'tin' => 'the number exactly as printed; it may be masked, for example ***-**-6789',
        'nombre' => 'the name exactly as printed',
        'codigo' => 'the code exactly as printed, for example D or DD',
        'texto' => 'the text exactly as printed',
    ];

    public function __construct(private readonly CatalogoFiscal $catalogo) {}

    /**
     * Las casillas que se le piden al modelo de un documento, en el orden
     * impreso.
     *
     * @return list<array{casilla: string, descripcion: string, tipo_dato: string|null, clave: bool, sensible: bool}>
     *
     * @throws InvalidArgumentException si el documento no existe en ese año o no tiene casillas
     */
    public function casillas(string $codigo, int $anio): array
    {
        $documento = $this->catalogo->documentos($anio)->firstWhere('codigo', $codigo);
        $casillas = collect($documento['casillas'] ?? [])->sortBy('orden')->values();

        if ($documento === null || $casillas->isEmpty()) {
            throw new InvalidArgumentException("El documento {$codigo} no tiene casillas para leer en {$anio}.");
        }

        return $casillas->map(fn (array $c) => [
            'casilla' => (string) $c['casilla'],
            'descripcion' => (string) $c['descripcion'],
            'tipo_dato' => $c['tipo_dato'] ?? null,
            'clave' => (bool) ($c['clave'] ?? false),
            'sensible' => (bool) ($c['sensible'] ?? false),
        ])->all();
    }

    /**
     * Los documentos del año que se pueden leer: los que tienen casillas.
     *
     * @return array<string, string> código → nombre para el modelo
     */
    public function documentosLeibles(int $anio): array
    {
        return $this->catalogo->documentos($anio)
            ->filter(fn (array $d) => ($d['casillas'] ?? []) !== [])
            ->mapWithKeys(fn (array $d) => [$d['codigo'] => (string) ($d['nombre_oficial'] ?? $d['nombre_sencillo_en'] ?? $d['codigo'])])
            ->all();
    }

    /**
     * JSON Schema (modo estricto de OpenAI) de la lectura de un documento.
     *
     * @return array<string, mixed>
     */
    public function para(string $codigo, int $anio): array
    {
        $propiedades = [];

        foreach ($this->casillas($codigo, $anio) as $casilla) {
            $propiedades[$casilla['casilla']] = $this->esquemaDeCasilla($casilla);
        }

        $nombres = collect($this->documentosLeibles($anio))->map(fn (string $nombre, string $codigo) => "{$codigo} = {$nombre}")->implode('; ');

        return $this->objeto([
            'tipo_documento' => [
                'type' => 'string',
                // strval: PHP vuelve entero el código "1098" al usarlo de clave, y OpenAI rechaza un enum de texto con un número.
                'enum' => [...array_map('strval', array_keys($this->documentosLeibles($anio))), self::DESCONOCIDO],
                'description' => "The code of the form in the images ({$nombres}). Use \"".self::DESCONOCIDO.'" if it is none of them.',
            ],
            'anio' => [
                'type' => ['integer', 'null'],
                'description' => 'The tax year printed on the form, or null if it does not show one.',
            ],
            'paginas_legibles' => [
                'type' => 'boolean',
                'description' => 'false if the pages are too blurry, cut off or dark to read most of the boxes.',
            ],
            'casillas' => $this->objeto($propiedades),
        ]);
    }

    /**
     * Lo que se le dice al modelo (mensaje de sistema).
     */
    public function instrucciones(string $codigo, int $anio): string
    {
        $nombre = $this->documentosLeibles($anio)[$codigo] ?? $codigo;

        return implode("\n", [
            "You read scanned or photographed US tax documents for the {$anio} tax year. The pages should be a {$nombre} (code {$codigo}), but check: report in tipo_documento the form you actually see.",
            'Return one entry for every box in the schema. Read only what is printed; never infer, calculate, correct or complete a value.',
            'If you cannot read a box, set legibilidad to "no_se_ve" and valor to null. If you can read it but are not sure, set legibilidad to "dudoso". Use "claro" only when you are certain of every character.',
            'Write each value in the format its description asks for. Amounts have no currency symbol and no thousands separators. Copy numbers such as SSN, EIN and TIN exactly as printed, even when they are masked.',
            'pagina is the page (starting at 1) where you found the value. texto_cercano is the exact text printed next to or under the value, such as the box label and number, so a person can find it; copy it as printed.',
        ]);
    }

    /**
     * @param  array{casilla: string, descripcion: string, tipo_dato: string|null}  $casilla
     * @return array<string, mixed>
     */
    private function esquemaDeCasilla(array $casilla): array
    {
        $formato = self::FORMATO_DEL_VALOR[$casilla['tipo_dato'] ?? 'texto'] ?? self::FORMATO_DEL_VALOR['texto'];

        return $this->objeto([
            'valor' => [
                'type' => ['string', 'null'],
                'description' => "Box {$casilla['casilla']}: {$casilla['descripcion']}. Write it as {$formato}. null if it is empty or you cannot read it.",
            ],
            'legibilidad' => [
                'type' => 'string',
                'enum' => array_map(fn (Legibilidad $l) => $l->value, Legibilidad::cases()),
            ],
            'pagina' => ['type' => ['integer', 'null']],
            'texto_cercano' => ['type' => ['string', 'null']],
        ]);
    }

    /**
     * Un objeto del modo estricto: todas sus propiedades son obligatorias y no admite otras.
     *
     * @param  array<string, array<string, mixed>>  $propiedades
     * @return array<string, mixed>
     */
    private function objeto(array $propiedades): array
    {
        return [
            'type' => 'object',
            'properties' => $propiedades,
            // PHP vuelve enteros las llaves "1", "2"…: `required` tiene que llevar textos.
            'required' => array_map('strval', array_keys($propiedades)),
            'additionalProperties' => false,
        ];
    }
}
