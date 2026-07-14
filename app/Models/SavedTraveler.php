<?php

namespace App\Models;

use App\Support\Travel\TravelDocumentFormatter;
use Database\Factories\SavedTravelerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'user_id',
    'agency_id',
    'first_name',
    'last_name',
    'title',
    'gender',
    'date_of_birth',
    'nationality',
    'document_type',
    'document_number',
    'document_expiry',
    'issuing_country',
    'phone',
    'email',
    'is_default',
    'meta',
])]
class SavedTraveler extends Model
{
    /** @use HasFactory<SavedTravelerFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'document_expiry' => 'date',
            'document_number' => 'encrypted',
            'is_default' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function maskedDocumentNumber(): ?string
    {
        return TravelDocumentFormatter::maskDocumentForList($this->document_number);
    }

    /**
     * @return list<string>
     */
    public function completenessIssues(): array
    {
        $issues = [];

        foreach ([
            'title' => 'Title',
            'first_name' => 'First name',
            'last_name' => 'Last name',
            'date_of_birth' => 'Date of birth',
            'nationality' => 'Nationality',
            'gender' => 'Gender',
            'document_type' => 'Document type',
            'document_number' => 'Document number',
            'document_expiry' => 'Document expiry',
            'issuing_country' => 'Issuing country',
        ] as $field => $label) {
            $value = $this->{$field};
            if ($value === null || (is_string($value) && trim($value) === '')) {
                $issues[] = $label.' is missing';
            }
        }

        return $issues;
    }

    public function isComplete(): bool
    {
        return $this->completenessIssues() === [];
    }

    public function completenessStatus(): string
    {
        return $this->isComplete() ? 'complete' : 'incomplete';
    }

    public function documentExpiryStatus(): string
    {
        if ($this->document_expiry === null) {
            return 'missing';
        }

        $expiry = $this->document_expiry instanceof Carbon
            ? $this->document_expiry
            : Carbon::parse($this->document_expiry);

        if ($expiry->isPast()) {
            return 'expired';
        }

        if ($expiry->lte(now()->addMonths(6))) {
            return 'expiring_soon';
        }

        return 'valid';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }
}
