<?php

namespace App\Http\Resources\Dashboard;

use App\Models\User;

final class DashboardCustomerResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fromModel(User $customer): array
    {
        $customer->loadMissing(['profile']);
        $profile = $customer->profile;
        $phone = (string) ($profile?->phone ?? $profile?->whatsapp ?? ($customer->meta['phone'] ?? ''));
        $bookingCount = (int) ($customer->bookings_count ?? $customer->bookings()->count());
        $currency = 'PKR';

        return [
            'id' => 'CU-'.$customer->id,
            'fullName' => (string) $customer->name,
            'email' => DashboardSessionResource::maskEmail($customer->email) ?? '—',
            'phone' => self::maskPhone($phone),
            'city' => (string) ($profile?->city ?? '—'),
            'country' => (string) ($profile?->country_code ?? 'Pakistan'),
            'nationality' => (string) ($profile?->nationality ?? '—'),
            'customerType' => 'Individual',
            'accountStatus' => self::mapAccountStatus((string) ($customer->status->value ?? 'active')),
            'verificationStatus' => self::mapVerification($customer),
            'travellerCount' => 0,
            'travellers' => [],
            'bookingCount' => $bookingCount,
            'completedBookingCount' => 0,
            'cancelledBookingCount' => 0,
            'totalBookedValue' => 0,
            'totalPaid' => 0,
            'outstandingBalance' => 0,
            'refundTotal' => 0,
            'lastBookingDate' => $customer->last_booking_at ?? null,
            'lastPaymentDate' => null,
            'createdDate' => $customer->created_at?->format('Y-m-d') ?? '',
            'preferredContactMethod' => 'email',
            'notesSummary' => '',
            'linkedBookingIds' => [],
            'linkedTransactionIds' => [],
            'currency' => $currency,
            'reviewState' => 'none',
        ];
    }

    protected static function mapAccountStatus(string $status): string
    {
        return match (strtolower($status)) {
            'active' => 'Active',
            'suspended' => 'Suspended',
            'inactive' => 'Inactive',
            default => 'Review Required',
        };
    }

    protected static function mapVerification(User $customer): string
    {
        if ($customer->email_verified_at !== null) {
            return 'Verified';
        }

        return 'Pending';
    }

    protected static function maskPhone(string $phone): string
    {
        if ($phone === '') {
            return '—';
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) < 4) {
            return '***';
        }

        return '***'.substr($digits, -4);
    }
}
