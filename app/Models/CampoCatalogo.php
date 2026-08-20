<?php

namespace App\Models;

use App\Enums\FieldDataType;
use App\Enums\FieldKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Definición editable de un campo del catálogo (sección 2 de la especificación):
 * qué campos pide cada forma del IRS, y con qué reglas. Sucesora del array
 * estático que tenía `TaxFieldCatalog` — las 10 formas siguen fijas
 * (`App\Enums\TaxForm`), solo los campos dentro de cada forma son editables.
 *
 * @property int $id
 * @property string $forma
 * @property int $tax_year
 * @property string $clave
 * @property FieldKind $tipo_campo
 * @property FieldDataType|null $tipo_dato
 * @property array<int, string>|null $formatos_aceptados
 * @property array<int, string>|null $subcampos
 * @property bool $obligatorio
 * @property bool $sensible
 * @property bool $unico_por_cliente
 */
#[Fillable(['forma', 'tax_year', 'clave', 'tipo_campo', 'tipo_dato', 'formatos_aceptados', 'subcampos', 'obligatorio', 'sensible', 'unico_por_cliente'])]
class CampoCatalogo extends Model
{
    public const TRANSVERSAL = 'transversal';

    /**
     * Documentos opcionales que, igual que TRANSVERSAL, se piden siempre sin
     * importar qué forma(s) tenga el cliente — pero se agrupan aparte porque
     * a diferencia de TRANSVERSAL (identidad del cliente: SSN, cónyuge,
     * dependientes, estado civil, w2, 1099-NEC, 1095-A) no son el núcleo
     * siempre-preguntado, sino documentos que el cliente puede enviar además.
     */
    public const DOCUMENTOS_EXTRA = 'documentos_extra';

    /**
     * Valores de `forma` que no son una `TaxForm` real — único punto de verdad
     * para todo chequeo de "¿esta forma es una de las pseudo-formas especiales?".
     *
     * @return array<int, string>
     */
    public static function pseudoFormas(): array
    {
        return [self::TRANSVERSAL, self::DOCUMENTOS_EXTRA];
    }

    /**
     * Convención de pluralización de Eloquent daría "campo_catalogos" — la tabla
     * real es "catalogo_campos" (orden del dominio: "campos del catálogo").
     */
    protected $table = 'catalogo_campos';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo_campo' => FieldKind::class,
            'tipo_dato' => FieldDataType::class,
            'formatos_aceptados' => 'array',
            'subcampos' => 'array',
            'obligatorio' => 'boolean',
            'sensible' => 'boolean',
            'unico_por_cliente' => 'boolean',
        ];
    }

    /**
     * Shape idéntico al que producía `TaxFieldCatalog::field()` sobre el array
     * estático, para no tener que tocar los consumidores (EventoRecoleccionService,
     * EventoRequest, CampoClienteUpdateRequest) al migrar de array a base de datos.
     *
     * @return array<string, mixed>
     */
    public function toDefinition(): array
    {
        return [
            'campo' => $this->clave,
            'tax_year' => $this->tax_year,
            'tipo' => $this->tipo_campo,
            'tipo_dato' => $this->tipo_dato,
            'formatos_aceptados' => $this->formatos_aceptados,
            'subcampos' => $this->subcampos,
            'obligatorio' => $this->obligatorio,
            'sensible' => $this->sensible,
            'unico_por_cliente' => $this->unico_por_cliente,
        ];
    }
}
