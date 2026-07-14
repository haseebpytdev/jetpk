<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'agency_id',
    'search_id',
    'user_id',
    'recipient_email',
    'session_id',
    'source_channel',
    'criteria',
    'criteria_fingerprint',
    'top_offers',
    'offer_count',
    'searched_at',
    'send_after_at',
    'expires_at',
    'status',
    'skip_reason',
    'sent_at',
    'communication_log_id',
    'meta',
])]
class FlightSearchMarketingSnapshot extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_READY = 'ready';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'criteria' => 'array',
            'top_offers' => 'array',
            'meta' => 'array',
            'searched_at' => 'datetime',
            'send_after_at' => 'datetime',
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
            'offer_count' => 'integer',
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

    public function markReady(): bool
    {
        return $this->transitionFromPending([
            'status' => self::STATUS_READY,
            'skip_reason' => null,
        ]);
    }

    public function markSkipped(string $reason): bool
    {
        return $this->transitionFromPending([
            'status' => self::STATUS_SKIPPED,
            'skip_reason' => $reason,
        ]);
    }

    public function markExpired(string $reason = 'expired'): bool
    {
        return $this->transitionFromPending([
            'status' => self::STATUS_EXPIRED,
            'skip_reason' => $reason,
        ]);
    }

    public function markFailed(string $reason): bool
    {
        if ($this->status === self::STATUS_READY) {
            return $this->transitionFromStatus(self::STATUS_READY, [
                'status' => self::STATUS_FAILED,
                'skip_reason' => $reason,
            ]);
        }

        return $this->transitionFromPending([
            'status' => self::STATUS_FAILED,
            'skip_reason' => $reason,
        ]);
    }

    public function markSent(?int $communicationLogId = null): bool
    {
        $attributes = [
            'status' => self::STATUS_SENT,
            'skip_reason' => null,
            'sent_at' => now(),
        ];

        if ($communicationLogId !== null) {
            $attributes['communication_log_id'] = $communicationLogId;
        }

        return $this->transitionFromStatus(self::STATUS_READY, $attributes);
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    public function hasSendableOffers(): bool
    {
        $topOffers = $this->top_offers;

        return is_array($topOffers) && $topOffers !== [];
    }

    public function markSkippedFromReady(string $reason): bool
    {
        return $this->transitionFromStatus(self::STATUS_READY, [
            'status' => self::STATUS_SKIPPED,
            'skip_reason' => $reason,
        ]);
    }

    public function markExpiredFromReady(string $reason = 'expired'): bool
    {
        return $this->transitionFromStatus(self::STATUS_READY, [
            'status' => self::STATUS_EXPIRED,
            'skip_reason' => $reason,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function transitionFromPending(array $attributes): bool
    {
        return $this->transitionFromStatus(self::STATUS_PENDING, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function transitionFromStatus(string $fromStatus, array $attributes): bool
    {
        $updated = self::query()
            ->whereKey($this->id)
            ->where('status', $fromStatus)
            ->update($attributes);

        if ($updated > 0) {
            $this->refresh();
        }

        return $updated > 0;
    }
}
