<?php

namespace App\Http\Requests\Expediente;

use App\Enums\Expediente\Credito8867;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * El preparador marca los créditos que se reclaman en el Form 8867 (F5.4a).
 * La lista puede ir vacía: ningún crédito.
 */
class CreditosDebidaDiligenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User $cliente */
        $cliente = $this->route('cliente');

        return $this->user()?->can('update', $cliente) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'creditos' => ['nullable', 'array'],
            'creditos.*' => ['distinct', Rule::enum(Credito8867::class)],
            'tax_year' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return list<Credito8867>
     */
    public function creditos(): array
    {
        return array_map(Credito8867::from(...), $this->validated('creditos') ?? []);
    }
}
