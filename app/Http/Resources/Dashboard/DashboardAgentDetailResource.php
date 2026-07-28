<?php

namespace App\Http\Resources\Dashboard;

use App\Models\Agent;
use Illuminate\Support\Facades\Gate;

final class DashboardAgentDetailResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fromModel(Agent $agent, \App\Models\User $viewer): array
    {
        $includeWallet = Gate::forUser($viewer)->allows('viewWallet', $agent);
        $summary = DashboardAgentResource::fromModel($agent, $includeWallet);

        return [
            'summary' => $summary,
            'agency' => [
                'agencyId' => (int) $agent->agency_id,
                'agencyName' => $agent->displayBusinessName(),
                'code' => (string) ($agent->code ?? ''),
            ],
            'metrics' => [
                'bookingCount' => (int) ($agent->bookings_count ?? 0),
                'staffCount' => 0,
            ],
            'walletSummary' => $includeWallet ? ($summary['walletBalanceSummary'] ?? null) : null,
            'creditDepositSummary' => null,
            'updatedAt' => $agent->updated_at?->toIso8601String(),
        ];
    }
}
