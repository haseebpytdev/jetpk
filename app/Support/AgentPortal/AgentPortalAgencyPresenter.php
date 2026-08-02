<?php

namespace App\Support\AgentPortal;

use App\Models\Agency;
use App\Models\Agent;

/**
 * Agent agency profile JSON presenter.
 */
class AgentPortalAgencyPresenter
{
    /**
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    public function presentShow(array $details, bool $canEdit, bool $canViewWallet, ?array $walletSummary): array
    {
        return [
            'ok' => true,
            'details' => $details,
            'capabilities' => [
                'can_edit_agency' => $canEdit,
                'can_view_wallet' => $canViewWallet,
            ],
            'wallet_summary' => $canViewWallet && $walletSummary !== null ? [
                'balance' => (float) ($walletSummary['balance'] ?? 0),
                'available_balance' => (float) ($walletSummary['available_balance'] ?? 0),
                'currency' => (string) ($walletSummary['currency'] ?? 'PKR'),
            ] : null,
            'update_url' => '/laravel/agent/agency',
        ];
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    public function presentEditForm(array $details, Agency $agency, Agent $agent): array
    {
        return [
            'ok' => true,
            'details' => $details,
            'supported_fields' => [
                'agency_name',
                'license_number',
                'email',
                'phone',
                'city',
                'country',
                'address',
                'code_prefix',
                'logo',
            ],
            'can_set_agency_prefix' => (bool) ($details['can_set_agency_prefix'] ?? false),
            'update_url' => '/laravel/agent/agency',
        ];
    }
}
