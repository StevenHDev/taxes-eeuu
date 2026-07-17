<?php

namespace App\Http\Resources;

use App\Models\TaxDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TaxDocument
 */
class TaxDocumentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'type' => $this->type,
            'type_label' => $this->type_label,
            'fiscal_year' => $this->fiscal_year,
            'title' => $this->title,
            'description' => $this->description,
            'ssn_itin_masked' => $this->maskedSsnItin(),
            'dependent_name' => $this->dependent_name,
            'dependent_date_of_birth' => $this->dependent_date_of_birth?->toDateString(),
            'amount' => $this->amount,
            'file_original_name' => $this->file_original_name,
            'file_mime_type' => $this->file_mime_type,
            'file_size' => $this->file_size,
            'download_url' => $this->file_original_name
                ? route('api.tax-documents.download', $this->id)
                : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
