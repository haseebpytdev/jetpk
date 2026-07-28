<?php

namespace App\Http\Resources\Dashboard;

use App\Models\Agent;
use App\Services\Agents\AgentWalletService;

final class DashboardAgentResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fromModel(Agent $agent, bool $includeWallet = false): array
    {
        $agent->loadMissing(['user', 'agency', 'wallet']);
        $user = $agent->user;
        $bookingCount = (int) ($agent->bookings_count ?? 0);
        $currency = 'PKR';

        $row = [
            'id' => self::publicId($agent),
            'agencyName' => $agent->displayBusinessName(),
            'tradingName' => $agent->displayBusinessName(),
            'agentType' => 'Retail Agent',
            'city' => (string) (($agent->meta['city'] ?? '') ?: '—'),
            'country' => (string) (($agent->meta['country'] ?? '') ?: 'Pakistan'),
            'operatingRegion' => (string) (($agent->meta['region'] ?? '') ?: 'Pakistan'),
            'primaryContact' => (string) ($user?->name ?? '—'),
            'email' => self::authorizedEmail($user?->email),
            'phone' => self::maskPhone((string) ($user?->phone ?? '')),
            'accountStatus' => $agent->is_active ? 'Active' : 'Inactive',
            'verificationStatus' => 'Verified',
            'commercialStatus' => 'Standard',
            'settlementStatus' => 'Not Applicable',
            'preferredCurrency' => $currency,
            'commissionRatePercent' => (float) ($agent->commission_percent ?? 0),
            'customerCount' => 0,
            'travellerCount' => 0,
            'bookingCount' => $bookingCount,
            'confirmedBookingCount' => 0,
            'cancelledBookingCount' => 0,
            'ticketedBookingCount' => 0,
            'grossBookingValue' => 0,
            'totalPaid' => 0,
            'outstandingCustomerBalance' => 0,
            'commissionEarned' => 0,
            'commissionPaid' => 0,
            'commissionPending' => 0,
            'refundExposure' => 0,
            'lastBookingDate' => null,
            'lastPaymentDate' => null,
            'lastTicketActivity' => null,
            'createdDate' => $agent->created_at?->format('Y-m-d') ?? '',
            'supportOwner' => '—',
            'notesSummary' => (string) (($agent->meta['notes'] ?? '') ?: 'Agent account — read-only dashboard summary.'),
            'linkedCustomerIds' => [],
            'linkedBookingIds' => [],
            'linkedTransactionIds' => [],
            'linkedPnrIds' => [],
            'linkedTicketIds' => [],
            'currency' => $currency,
            'staffCount' => 0,
            'applicationState' => 'approved',
            'reviewFlags' => [
                'needsReview' => ! $agent->is_active,
            ],
        ];

        if ($includeWallet) {
            $walletSummary = app(AgentWalletService::class)->summary($agent);
            $row['walletBalanceSummary'] = [
                'currency' => (string) ($walletSummary['currency'] ?? $currency),
                'available' => (int) round((float) ($walletSummary['available_balance'] ?? 0)),
                'state' => 'summary_only',
            ];
        }

        return $row;
    }

    public static function publicId(Agent $agent): string
    {
        $code = trim((string) ($agent->code ?? ''));
        if ($code !== '') {
            return 'AG-'.$code;
        }

        return 'AG-'.str_pad((string) $agent->id, 5, '0', STR_PAD_LEFT);
    }

    protected static function authorizedEmail(?string $email): string
    {
        if ($email === null || $email === '') {
            return '—';
        }

        return DashboardSessionResource::maskEmail($email) ?? '—';
    }

    protected static function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) < 4) {
            return '—';
        }

        return '***'.substr($digits, -4);
    }
}
