<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tax_document_id
 * @property int $revealed_by_id
 * @property string|null $ip_address
 * @property Carbon|null $created_at
 */
#[Fillable(['tax_document_id', 'revealed_by_id', 'ip_address'])]
class TaxDocumentReveal extends Model
{
    const UPDATED_AT = null;

    /**
     * @return BelongsTo<TaxDocument, $this>
     */
    public function taxDocument(): BelongsTo
    {
        return $this->belongsTo(TaxDocument::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function revealedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revealed_by_id');
    }
}
