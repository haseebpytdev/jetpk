<?php

namespace App\Models;

use App\Enums\GroupBookingStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'reference',
    'user_id',
    'group_inventory_id',
    'status',
    'seat_count',
    'total_amount',
    'currency',
    'contact_name',
    'contact_email',
    'contact_phone',
    'expires_at',
    'reservation_created_at',
    'released_at',
    'release_reason',
    'supplier_reservation_id',
    'supplier_release_attempted_at',
    'supplier_released_at',
    'supplier_release_failed_at',
    'supplier_release_response',
    'payment_submitted_at',
    'payment_method',
    'payment_reference',
    'payment_proof_path',
    'manual_payment_status',
    'admin_payment_verified_at',
    'admin_payment_verified_by',
    'meta',
])]
class GroupBooking extends Model
{
    protected function casts(): array
    {
        return [
            'status' => GroupBookingStatus::class,
            'seat_count' => 'integer',
            'total_amount' => 'decimal:2',
            'expires_at' => 'datetime',
            'reservation_created_at' => 'datetime',
            'released_at' => 'datetime',
            'supplier_release_attempted_at' => 'datetime',
            'supplier_released_at' => 'datetime',
            'supplier_release_failed_at' => 'datetime',
            'payment_submitted_at' => 'datetime',
            'admin_payment_verified_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isPaymentWindowOpen(): bool
    {
        if (in_array($this->status, [
            GroupBookingStatus::Confirmed,
            GroupBookingStatus::ManualPaymentPendingReview,
            GroupBookingStatus::ManualPaymentSubmitted,
            GroupBookingStatus::Released,
            GroupBookingStatus::SupplierReleaseFailed,
            GroupBookingStatus::Expired,
            GroupBookingStatus::Cancelled,
        ], true)) {
            return false;
        }

        if ($this->expires_at === null) {
            return true;
        }

        return ! $this->expires_at->isPast();
    }

    public function isReleasable(): bool
    {
        return in_array($this->status, [
            GroupBookingStatus::ReservedAwaitingPayment,
            GroupBookingStatus::PaymentPending,
        ], true);
    }

    public function isReleased(): bool
    {
        return in_array($this->status, [
            GroupBookingStatus::Released,
            GroupBookingStatus::SupplierReleaseFailed,
            GroupBookingStatus::Expired,
        ], true) || $this->released_at !== null;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<GroupInventory, $this> */
    public function inventory(): BelongsTo
    {
        return $this->belongsTo(GroupInventory::class, 'group_inventory_id');
    }

    /** @return HasMany<GroupBookingPassenger, $this> */
    public function passengers(): HasMany
    {
        return $this->hasMany(GroupBookingPassenger::class)->orderBy('sort_order');
    }

    /** @return BelongsTo<User, $this> */
    public function adminPaymentVerifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_payment_verified_by');
    }
}
