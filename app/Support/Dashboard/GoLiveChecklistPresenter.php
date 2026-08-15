<?php

namespace App\Support\Dashboard;

use App\Enums\AccountType;
use App\Models\AgencySetting;
use App\Models\MarkupRule;
use App\Models\SupplierConnection;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Evaluates production-readiness items from live configuration. Never marks commercial UAT as done.
 */
final class GoLiveChecklistPresenter
{
    /**
     * @return list<array{key: string, label: string, ok: bool, note: string, href: ?string, actionLabel: ?string}>
     */
    public function items(User $user): array
    {
        $agencyId = $user->current_agency_id;

        $logoOk = false;
        if ($agencyId && Schema::hasTable('agency_settings')) {
            $logoOk = AgencySetting::query()
                ->where('agency_id', $agencyId)
                ->whereNotNull('logo_path')
                ->where('logo_path', '!=', '')
                ->exists();
        }

        $connectionOk = false;
        if (Schema::hasTable('supplier_connections')) {
            $connectionOk = SupplierConnection::query()
                ->when($agencyId, fn ($q) => $q->where('agency_id', $agencyId))
                ->where(function ($q): void {
                    $q->where('is_active', true)->orWhere('status', 'active');
                })
                ->exists();
        }

        $staffOk = User::query()
            ->when($agencyId, fn ($q) => $q->where('current_agency_id', $agencyId))
            ->whereIn('account_type', [AccountType::Staff, AccountType::AgencyAdmin, AccountType::PlatformAdmin])
            ->exists();

        $markupOk = Schema::hasTable('markup_rules')
            && MarkupRule::query()->when($agencyId, fn ($q) => $q->where('agency_id', $agencyId))->exists();

        $productionReady = app()->environment('production') && ! (bool) config('app.debug') && filled(config('app.key'));

        return [
            [
                'key' => 'app_url',
                'label' => 'Application URL configured',
                'ok' => filled(config('app.url')),
                'note' => (string) config('app.url'),
                'href' => '/admin/dashboard/settings/general',
                'actionLabel' => 'Open organization settings',
            ],
            [
                'key' => 'branding',
                'label' => 'Organization logo uploaded',
                'ok' => $logoOk,
                'note' => $logoOk ? 'Logo path is present on agency settings.' : 'Upload a logo on Organization Profile.',
                'href' => '/admin/dashboard/settings/general',
                'actionLabel' => 'Open organization profile',
            ],
            [
                'key' => 'api_connection',
                'label' => 'At least one active API connection',
                'ok' => $connectionOk,
                'note' => $connectionOk ? 'An active supplier connection exists.' : 'Add and enable an installed adapter connection.',
                'href' => '/admin/dashboard/api-connections',
                'actionLabel' => 'Open API connections',
            ],
            [
                'key' => 'staff',
                'label' => 'Staff or admin operators exist',
                'ok' => $staffOk,
                'note' => $staffOk ? 'Operator accounts are present.' : 'Invite staff from Users / Staff.',
                'href' => '/admin/dashboard/users',
                'actionLabel' => 'Open users',
            ],
            [
                'key' => 'markups',
                'label' => 'Markup rules exist',
                'ok' => $markupOk,
                'note' => $markupOk ? 'At least one markup rule is stored.' : 'Create markup rules before commercial traffic.',
                'href' => '/admin/dashboard/markups',
                'actionLabel' => 'Open markups',
            ],
            [
                'key' => 'mail',
                'label' => 'Mailer configured',
                'ok' => filled(config('mail.default')),
                'note' => 'Mailer: '.(string) config('mail.default'),
                'href' => '/admin/dashboard/settings/notifications',
                'actionLabel' => 'Open notifications',
            ],
            [
                'key' => 'debug',
                'label' => 'Production debug is off',
                'ok' => $productionReady || ! app()->environment('production'),
                'note' => app()->environment('production')
                    ? 'APP_DEBUG must remain false in production.'
                    : 'Non-production environment: debug policy is not blocking.',
                'href' => '/admin/dashboard/system/health',
                'actionLabel' => 'Open system health',
            ],
            [
                'key' => 'commercial_uat',
                'label' => 'Commercial UAT remains owner-signed',
                'ok' => false,
                'note' => 'Live booking, PNR, ticket, and payment proofs are not auto-completed by this checklist.',
                'href' => '/admin/dashboard/bookings',
                'actionLabel' => 'Open bookings',
            ],
        ];
    }
}
