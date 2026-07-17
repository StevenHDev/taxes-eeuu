<?php

namespace App\Http\Requests;

use App\Enums\TaxDocumentAbility;
use App\Enums\TaxDocumentType;
use App\Models\TaxDocument;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

class TaxDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Authorization must be checked here, before validation rules run — otherwise an
     * unauthorized request with invalid data would fail with a validation error instead
     * of the correct 403, since the controller's own authorize() call never gets reached.
     *
     * This same request is used by both the web controller (session auth) and the API
     * controller (Sanctum token auth), so the token's "write" ability is enforced here
     * too, once, instead of duplicating it in every API action.
     */
    public function authorize(): bool
    {
        $taxDocument = $this->routeTaxDocument();

        $can = $taxDocument
            ? $this->user()->can('update', $taxDocument)
            : $this->user()->can('create', TaxDocument::class);

        return $can && $this->tokenHasWriteAbility();
    }

    protected function tokenHasWriteAbility(): bool
    {
        $token = $this->user()?->currentAccessToken();

        if (! $token instanceof PersonalAccessToken) {
            // Session-authenticated (web) requests have no token abilities to check.
            return true;
        }

        return $token->can(TaxDocumentAbility::Write->value);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = TaxDocumentType::tryFrom((string) $this->input('type'));

        $taxDocument = $this->routeTaxDocument();

        $rules = [
            'type' => ['required', Rule::enum(TaxDocumentType::class)],
            'fiscal_year' => ['nullable', 'integer', 'between:1900,'.(date('Y') + 1)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'ssn_itin' => [
                // Never re-required on update: the field is write-only (masked in the UI),
                // so leaving it blank means "keep the existing encrypted value".
                Rule::requiredIf(fn () => ($type?->requiresSsn() ?? false) && ! $taxDocument?->ssn_itin),
                'nullable', 'string', 'regex:/^\d{3}-?\d{2}-?\d{4}$/',
            ],
            'dependent_name' => [
                Rule::requiredIf(fn () => $type?->requiresDependentFields() ?? false),
                'nullable', 'string', 'max:255',
            ],
            'dependent_date_of_birth' => [
                Rule::requiredIf(fn () => $type?->requiresDependentFields() ?? false),
                'nullable', 'date', 'before:today',
            ],
            'amount' => [
                Rule::requiredIf(fn () => $type?->requiresAmount() ?? false),
                'nullable', 'numeric', 'min:0',
            ],
            'file' => [
                Rule::requiredIf(fn () => ($type?->requiresFile() ?? false) && ! $taxDocument?->file_path),
                'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240',
            ],
        ];

        if ($this->user()?->role === 'preparer') {
            $rules['user_id'] = [
                'required',
                Rule::in($this->user()->clients()->pluck('id')),
            ];
        }

        return $rules;
    }

    /**
     * Route::resource derives the URI placeholder from the resource name, which
     * yields `{tax_document}` (snake_case), not `{taxDocument}`.
     */
    protected function routeTaxDocument(): ?TaxDocument
    {
        $taxDocument = $this->route('tax_document');

        return $taxDocument instanceof TaxDocument ? $taxDocument : null;
    }
}
