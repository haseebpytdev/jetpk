<?php

namespace App\Support\Bookings;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\User;

/**
 * Persists admin supplier action attempts to audit log (PIA-NDC-OPS1).
 */
final class AdminBookingSupplierActionAuditor
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public function log(
        Booking $booking,
        User $user,
        string $action,
        bool $confirmationAccepted,
        string $preStatus,
        string $postStatus,
        bool $supplierRequestAttempted,
        ?string $supplierResponseStatus,
        ?string $errorSummary = null,
        array $extra = [],
    ): void {
        AuditLog::query()->create([
            'agency_id' => $booking->agency_id,
            'user_id' => $user->id,
            'action' => 'admin_supplier_action:'.$action,
            'auditable_type' => Booking::class,
            'auditable_id' => $booking->id,
            'properties' => [
                'old_values' => ['status' => $preStatus],
                'new_values' => array_merge([
                    'status' => $postStatus,
                    'action' => $action,
                    'confirmation_accepted' => $confirmationAccepted,
                    'supplier_request_attempted' => $supplierRequestAttempted,
                    'supplier_response_status' => $supplierResponseStatus,
                    'error_summary' => $errorSummary,
                    'booking_id' => $booking->id,
                    'payment_status' => (string) ($booking->payment_status ?? ''),
                    'ticketing_status' => (string) ($booking->ticketing_status ?? ''),
                    'pnr' => (string) ($booking->pnr ?? ''),
                ], $extra),
            ],
        ]);
    }
}
