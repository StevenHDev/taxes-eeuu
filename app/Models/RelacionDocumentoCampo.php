<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Relación confirmada entre un campo-documento del catálogo (ej. 'w2',
 * 'form_1099_nec') y un campo-destino que ese documento ya resuelve sin
 * tener que preguntárselo al cliente aparte (ej. w2 → form_1040.ingresos,
 * subcampo 'salarios').
 *
 * Antes esta lógica vivía como texto fijo en el prompt del agente externo
 * (sección "RELACIONES CONOCIDAS ENTRE DOCUMENTOS Y CAMPOS" de docs/prompt.md),
 * memorizada por el LLM y desincronizada del catálogo real cada vez que
 * cambiaba. Ahora es una tabla más del catálogo: el agente la consulta vía
 * la clave `revela` que expone `TaxFieldCatalog::pendientesPara()`, igual que
 * consulta `tipo_dato`, `sensible`, etc. — nunca la memoriza.
 *
 * @property int $id
 * @property string $documento_forma
 * @property string $documento_campo
 * @property string $campo_destino_forma
 * @property string $campo_destino
 * @property string|null $subcampo_destino
 * @property string|null $descripcion
 * @property int $tax_year
 */
#[Fillable(['documento_forma', 'documento_campo', 'campo_destino_forma', 'campo_destino', 'subcampo_destino', 'descripcion', 'tax_year'])]
class RelacionDocumentoCampo extends Model
{
    protected $table = 'relaciones_documento_campo';

    /**
     * Shape que consume el agente externo desde la clave `revela` de un
     * pendiente tipo documento — ver TaxFieldCatalog::pendientesPara().
     *
     * @return array<string, mixed>
     */
    public function toDefinition(): array
    {
        return [
            'forma' => $this->campo_destino_forma,
            'campo' => $this->campo_destino,
            'subcampo' => $this->subcampo_destino,
            'descripcion' => $this->descripcion,
        ];
    }
}
