<?php

namespace App\Models;

use App\Enums\AgencyRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable(['agency_id', 'user_id', 'role', 'agency_role'])]
class AgencyUser extends Pivot
{
    public $incrementing = true;

    protected $table = 'agency_users';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'agency_role' => AgencyRole::class,
        ];
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
