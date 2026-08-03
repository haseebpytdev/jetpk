<?php

namespace App\Support\BackOffice;

use App\Models\BookingCancellationRequest;

final class BackOfficeCancellationPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(BookingCancellationRequest $request): array
    {
        return [
            'id' => (string) $request->id,
            'booking_id' => (string) $request->booking_id,
            'status' => $request->status->value,
            'cancellation_type' => $request->cancellation_type->value,
            'reason' => $request->reason,
            'approved_at' => $request->approved_at?->toIso8601String(),
            'rejected_at' => $request->rejected_at?->toIso8601String(),
        ];
    }
}
