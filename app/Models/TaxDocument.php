<?php

namespace App\Models;

use App\Enums\TaxDocumentType;
use Database\Factories\TaxDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $uploaded_by_id
 * @property TaxDocumentType $type
 * @property int|null $fiscal_year
 * @property string $title
 * @property string|null $description
 * @property string|null $ssn_itin
 * @property string|null $dependent_name
 * @property Carbon|null $dependent_date_of_birth
 * @property string|null $amount
 * @property string|null $file_path
 * @property string|null $file_original_name
 * @property string|null $file_mime_type
 * @property int|null $file_size
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string|null $ssn_itin_masked
 * @property-read string $type_label
 */
#[Fillable([
    'user_id',
    'uploaded_by_id',
    'type',
    'fiscal_year',
    'title',
    'description',
    'ssn_itin',
    'dependent_name',
    'dependent_date_of_birth',
    'amount',
    'file_path',
    'file_original_name',
    'file_mime_type',
    'file_size',
])]
class TaxDocument extends Model
{
    /** @use HasFactory<TaxDocumentFactory> */
    use HasFactory;

    protected $hidden = ['ssn_itin', 'file_path'];

    protected $appends = ['type_label'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TaxDocumentType::class,
            'ssn_itin' => 'encrypted',
            'dependent_date_of_birth' => 'date',
            'amount' => 'decimal:2',
            'fiscal_year' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    /**
     * The SSN/ITIN is write-only: it is never appended to the model's array/JSON
     * representation as an accessor (to guarantee it can't accidentally leak into
     * an Inertia prop or API response), so it's added to toArray() explicitly instead.
     */
    public function maskedSsnItin(): ?string
    {
        if (! $this->ssn_itin) {
            return null;
        }

        return sprintf('***-**-%s', substr($this->ssn_itin, -4));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'ssn_itin_masked' => $this->maskedSsnItin(),
        ];
    }

    /**
     * @return Attribute<string, never>
     */
    protected function typeLabel(): Attribute
    {
        return Attribute::get(fn (): string => $this->type->label());
    }

    /**
     * @param  Builder<TaxDocument>  $query
     * @return Builder<TaxDocument>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->role === 'preparer') {
            return $query->whereIn('user_id', [
                $user->id,
                ...$user->clients()->pluck('id'),
            ]);
        }

        return $query->where('user_id', $user->id);
    }
}
