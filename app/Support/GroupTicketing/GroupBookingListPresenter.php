<?php

namespace App\Support\GroupTicketing;

use App\Enums\GroupBookingStatus;
use App\Models\GroupBooking;

/**
 * Normalizes group booking rows for admin list tables.
 */
final class GroupBookingListPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function toListRow(GroupBooking $booking): array
    {
        $booking->loadMissing('inventory', 'user');

        return [
            'id' => $booking->id,
            'reference' => $booking->reference,
            'status' => $booking->status?->value,
            'status_label' => $booking->status?->label() ?? '—',
            'customer_name' => $booking->user?->name ?? '—',
            'package_title' => $booking->inventory?->title ?? '—',
            'sector' => $booking->inventory?->sector,
            'seat_count' => $booking->seat_count,
            'total_amount' => (float) $booking->total_amount,
            'currency' => $booking->currency,
            'expires_at' => $booking->expires_at?->toIso8601String(),
            'created_at' => $booking->created_at?->toIso8601String(),
            'show_url' => route('admin.group-bookings.show', $booking),
            'product_type' => 'group',
        ];
    }

    public static function statusBadgeClass(GroupBookingStatus $status): string
    {
        return match ($status) {
            GroupBookingStatus::Confirmed => 'bg-success',
            GroupBookingStatus::ReservedAwaitingPayment, GroupBookingStatus::PaymentPending => 'bg-warning',
            GroupBookingStatus::Expired, GroupBookingStatus::Released, GroupBookingStatus::Cancelled => 'bg-secondary',
            GroupBookingStatus::Failed => 'bg-danger',
            default => 'bg-info',
        };
    }
}
