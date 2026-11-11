<?php

namespace App\Services\Lectura;

use App\Enums\Lectura\ClaseDetectada;
use App\Enums\Lectura\Legibilidad;
use App\Enums\Lectura\SentidoRenglon;
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
 *
 * Modo lista (F6 6.3c): un documento con `lectura = renglones` (los registros
 * del negocio: extractos, recibos sueltos, la plantilla en PDF o foto) pide
 * además de sus casillas fijas la clase del archivo y `renglones[]`, un
 * movimiento por renglón, con la categoría como opciones cerradas: las líneas
 * del Schedule C del año (con su texto en inglés del formulario) y `no_se`.
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

    /** La categoría de un renglón cuando el modelo no está seguro. */
    public const SIN_CATEGORIA = 'no_se';

    /**
     * El texto en inglés de cada línea del Schedule C que suma renglones, tal
     * como lo imprime el formulario de 2025 (f1040sc.pdf, p. 1, líneas 1, 2 y 8
     * a 27b). `1-sin-formulario` es la línea 1 sin los 1099 (esos los suma el
     * motor aparte). Qué líneas se ofrecen sale del catálogo del año (las de
     * `ResultadoSumas::LINEAS` que el Schedule C del año tiene).
     */
    private const ETIQUETAS_SCHEDULE_C = [
        '1-sin-formulario' => 'Line 1, gross receipts or sales (income of the business not reported on a Form 1099)',
        '2' => 'Line 2, returns and allowances',
        '8' => 'Line 8, advertising',
        '9' => 'Line 9, car and truck expenses',
        '10' => 'Line 10, commissions and fees',
        '11' => 'Line 11, contract labor',
        '12' => 'Line 12, depletion',
        '13' => 'Line 13, depreciation and section 179 expense deduction',
        '14' => 'Line 14, employee benefit programs (other than on line 19)',
        '15' => 'Line 15, insurance (other than health)',
        '16a' => 'Line 16a, interest: mortgage (paid to banks, etc.)',
        '16b' => 'Line 16b, interest: other',
        '17' => 'Line 17, legal and professional services',
        '18' => 'Line 18, office expense',
        '19' => 'Line 19, pension and profit-sharing plans',
        '20a' => 'Line 20a, rent or lease: vehicles, machinery, and equipment',
        '20b' => 'Line 20b, rent or lease: other business property',
        '21' => 'Line 21, repairs and maintenance',
        '22' => 'Line 22, supplies (not included in Part III)',
        '23' => 'Line 23, taxes and licenses',
        '24a' => 'Line 24a, travel',
        '24b' => 'Line 24b, meals (write the full amount paid)',
        '25' => 'Line 25, utilities',
        '26' => 'Line 26, wages (less employment credits)',
        '27a' => 'Line 27a, energy efficient commercial buildings deduction',
        '27b' => 'Line 27b, other expenses',
    ];

    public function __construct(private readonly CatalogoFiscal $catalogo) {}

    /**
     * Si el documento se lee por renglones (`lectura = renglones` en el catálogo del año).
     */
    public function esPorRenglones(string $codigo, int $anio): bool
    {
        return ($this->catalogo->documentos($anio)->firstWhere('codigo', $codigo)['lectura'] ?? 'casillas') === 'renglones';
    }

    /**
     * Las categorías que se le ofrecen al modelo para un renglón: las líneas
     * del Schedule C del catálogo del año que suman renglones, en el orden de
     * `ResultadoSumas::LINEAS`, con su texto para el modelo. `no_se` no está
     * aquí: se agrega en el esquema.
     *
     * @return array<int|string, string> código de la línea → texto (PHP vuelve enteros los códigos "2", "8"…)
     */
    public function categorias(int $anio): array
    {
        $lineas = [];

        foreach ($this->catalogo->formularios($anio)->firstWhere('codigo', 'sch-c')['lineas'] ?? [] as $linea) {
            $lineas[(string) $linea['linea']] = (string) ($linea['descripcion'] ?? '');
        }

        $categorias = [];

        foreach (ResultadoSumas::LINEAS as $codigo) {
            $linea = $codigo === '1-sin-formulario' ? '1' : $codigo;

            if (array_key_exists($linea, $lineas)) {
                $categorias[$codigo] = self::ETIQUETAS_SCHEDULE_C[$codigo];
            }
        }

        return $categorias;
    }

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

        $lista = $this->esPorRenglones($codigo, $anio) ? [
            'clase' => [
                'type' => 'string',
                'enum' => $this->clases(),
                'description' => 'What kind of business record this is: plantilla = the business income and expense template, extracto_banco = bank statement, extracto_tarjeta = credit card statement, recibo = a receipt or invoice, millaje = a mileage log, otro = anything else.',
            ],
            'renglones' => [
                'type' => 'array',
                'description' => 'Every money movement printed on these pages, one entry per movement, in the order printed. Empty if there are none.',
                'items' => $this->esquemaDeRenglon($anio),
            ],
        ] : [];

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
            ...$lista,
        ]);
    }

    /**
     * Lo que se le dice al modelo (mensaje de sistema). En modo lista, si el
     * archivo se lee por bloques, `$desde`, `$hasta` y `$total` dicen qué
     * páginas del archivo van en esta llamada.
     */
    public function instrucciones(string $codigo, int $anio, ?int $desde = null, ?int $hasta = null, ?int $total = null): string
    {
        $nombre = $this->documentosLeibles($anio)[$codigo] ?? $codigo;

        $instrucciones = [
            "You read scanned or photographed US tax documents for the {$anio} tax year. The pages should be a {$nombre} (code {$codigo}), but check: report in tipo_documento the form you actually see.",
            'Return one entry for every box in the schema. Read only what is printed; never infer, calculate, correct or complete a value.',
            'If you cannot read a box, set legibilidad to "no_se_ve" and valor to null. If you can read it but are not sure, set legibilidad to "dudoso". Use "claro" only when you are certain of every character.',
            'Write each value in the format its description asks for. Amounts have no currency symbol and no thousands separators. Copy numbers such as SSN, EIN and TIN exactly as printed, even when they are masked.',
            'pagina is the page (starting at 1) where you found the value. texto_cercano is the exact text printed next to or under the value, such as the box label and number, so a person can find it; copy it as printed.',
        ];

        if (! $this->esPorRenglones($codigo, $anio)) {
            return implode("\n", $instrucciones);
        }

        $sinCategoria = self::SIN_CATEGORIA;

        return implode("\n", [
            ...$instrucciones,
            'These pages are business records of a self-employed person: a bank or credit card statement, a receipt, a mileage log or the business income and expense template. Besides the boxes, list in renglones every money movement printed on the pages, in the order printed. In the income and expense template, each filled amount is one movement and its row label is its category (categoria_impresa true); the mileage and square feet go in their boxes, not in renglones.',
            'Do not invent movements: list only movements that are printed on these pages. Never add up, split, merge or complete movements. Do not list running balances, subtotals or totals as movements; the printed totals of a statement go in the boxes total-depositos, total-retiros, saldo-inicial and saldo-final.',
            'A box that this kind of document does not have (for example the miles on a bank statement) has valor null and legibilidad "claro".',
            'For each movement: monto is the amount as a positive plain decimal such as 45.20, without sign, currency symbol or thousands separators (null if you cannot read it); fecha is the date as YYYY-MM-DD (use the year of the statement period if the line shows only month and day; null if there is none); descripcion is the text of the movement as printed, but write any account or card number only with its last 4 digits; evidencia is the full printed line of the movement, copied as printed.',
            'sentido: "ingreso" for money the business received, "gasto" for money the business paid, "transferencia" for a movement between the person\'s own accounts (for example a transfer to savings or a credit card payment made from the bank account), "personal" if it is clearly a personal expense, "no_se" if you cannot tell.',
            "categoria: the Schedule C line the movement belongs to, from the options. If you are not sure of the category, use \"{$sinCategoria}\". Transfers and personal movements use \"{$sinCategoria}\". categoria_impresa is true only when the document itself prints the category or expense type of the movement (for example a row label of the template, or a receipt that says \"Category: Office supplies\"); false when you chose the category.",
            'legibilidad of a movement is about its amount: "claro" only if you are certain of every digit.',
            $desde !== null && $hasta !== null && $total !== null
                ? "The images are pages {$desde} to {$hasta} of a file with {$total} pages. pagina is the page number in the file (from {$desde} to {$hasta}), not the position of the image."
                : 'pagina of a movement is the page (starting at 1) where it is printed.',
        ]);
    }

    /**
     * Un renglón del modo lista: todo obligatorio, lo que no se ve va nulo.
     *
     * @return array<string, mixed>
     */
    private function esquemaDeRenglon(int $anio): array
    {
        $categorias = $this->categorias($anio);
        $opciones = collect($categorias)->map(fn (string $texto, int|string $codigo) => "{$codigo} = {$texto}")->implode('; ');

        return $this->objeto([
            'pagina' => ['type' => ['integer', 'null']],
            'fecha' => ['type' => ['string', 'null'], 'description' => 'The date of the movement as YYYY-MM-DD, or null.'],
            'descripcion' => ['type' => ['string', 'null']],
            'monto' => ['type' => ['string', 'null'], 'description' => 'The amount as a positive plain decimal such as 45.20, or null if you cannot read it.'],
            'sentido' => ['type' => 'string', 'enum' => array_map(fn (SentidoRenglon $s) => $s->value, SentidoRenglon::cases())],
            'categoria' => [
                'type' => 'string',
                // strval: PHP vuelve enteros los códigos "2", "8"… al usarlos de clave.
                'enum' => [...array_map('strval', array_keys($categorias)), self::SIN_CATEGORIA],
                'description' => "The Schedule C line ({$opciones}). Use \"".self::SIN_CATEGORIA.'" if you are not sure.',
            ],
            'categoria_impresa' => ['type' => 'boolean'],
            'legibilidad' => ['type' => 'string', 'enum' => array_map(fn (Legibilidad $l) => $l->value, Legibilidad::cases())],
            'evidencia' => ['type' => ['string', 'null']],
        ]);
    }

    /**
     * Las clases de archivo que puede decir el modelo: todas menos la hoja de
     * cálculo, que no pasa por la IA (LectorHojas).
     *
     * @return list<string>
     */
    private function clases(): array
    {
        return array_values(array_map(
            fn (ClaseDetectada $c) => $c->value,
            array_filter(ClaseDetectada::cases(), fn (ClaseDetectada $c) => $c !== ClaseDetectada::HojaCalculo),
        ));
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
