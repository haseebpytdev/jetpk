<?php

namespace App\Models;

use App\Enums\MarkupRuleStatus;
use App\Enums\MarkupRuleType;
use App\Enums\MarkupValueType;
use Database\Factories\MarkupRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'agency_id',
    'name',
    'rule_type',
    'value',
    'value_type',
    'applies_to',
    'priority',
    'status',
    'starts_at',
    'ends_at',
    'meta',
    'config',
    'is_active',
])]
class MarkupRule extends Model
{
    /** @use HasFactory<MarkupRuleFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'rule_type' => MarkupRuleType::class,
            'value' => 'decimal:4',
            'value_type' => MarkupValueType::class,
            'applies_to' => 'array',
            'status' => MarkupRuleStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'meta' => 'array',
            'config' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }
}
