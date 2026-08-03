<?php

namespace App\Http\Resources\Dashboard;

use App\Models\AgentDepositRequest;
use App\Models\User;
use App\Support\BackOffice\BackOfficeCapabilitiesPresenter;

final class DashboardDepositResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fromModel(AgentDepositRequest $deposit, ?User $viewer = null): array
    {
        $deposit->loadMissing(['agency', 'user', 'agent.user', 'reviewer']);

        return [
            'id' => (string) $deposit->id,
            'reference' => $deposit->reference,
            'status' => $deposit->status->value,
            'amount' => (float) $deposit->amount,
            'currency' => $deposit->currency,
            'agencyName' => $deposit->agency?->name ?? '—',
            'agentName' => $deposit->agent?->user?->name ?? $deposit->user?->name ?? '—',
            'submittedAt' => $deposit->created_at?->toIso8601String() ?? '',
            'reviewedAt' => $deposit->reviewed_at?->toIso8601String(),
            'adminNote' => $deposit->admin_note,
            'capabilities' => $viewer !== null
                ? app(BackOfficeCapabilitiesPresenter::class)->presentDepositCapabilities($viewer, $deposit)
                : null,
        ];
    }
}
