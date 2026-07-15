<?php

namespace App\Support\Agencies;

use App\Enums\AccountType;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Resolves the agency name/badge shown in Platform Admin UI from true relationships,
 * not only users.current_agency_id (which may still point at the platform default agency).
 */
final class AgencyScopeResolver
{
    public static function badgeLabel(User $user): string
    {
        if ($user->isPlatformAdmin() || $user->isStaff()) {
            return 'Platform';
        }

        if ($user->isCustomer()) {
            return '';
        }

        if ($user->account_type === AccountType::Agent) {
            return self::displayName(self::ownerAgencyFor($user));
        }

        if ($user->account_type === AccountType::AgentStaff) {
            $ownerAgentId = (int) ($user->meta['owner_agent_id'] ?? 0);
            if ($ownerAgentId > 0) {
                $ownerAgency = Agent::query()->with(['agency.agencySetting'])->find($ownerAgentId)?->agency;
                if ($ownerAgency !== null) {
                    return self::displayName($ownerAgency);
                }
            }

            return self::displayName($user->currentAgency);
        }

        return self::displayName($user->currentAgency);
    }

    public static function scopeLabel(User $user): string
    {
        if ($user->isPlatformAdmin()) {
            return 'Cross-agency (all agencies)';
        }

        if ($user->isStaff()) {
            return 'Platform operations';
        }

        $label = self::badgeLabel($user);

        return $label !== '' ? $label : 'Unassigned agency';
    }

    public static function displayName(?Agency $agency): string
    {
        if ($agency === null) {
            return '';
        }

        $display = trim((string) ($agency->agencySetting?->display_name ?? ''));

        return $display !== '' ? $display : (string) $agency->name;
    }

    public static function ownerAgencyFor(User $user): ?Agency
    {
        $agent = self::canonicalOwnerAgent($user);

        return $agent?->agency ?? $user->currentAgency;
    }

    /**
     * Prefer the partner-agency owner row over an older active row on the platform default agency.
     */
    public static function canonicalOwnerAgent(User $user, ?string $expectedAgencyName = null): ?Agent
    {
        $agents = self::ownerAgentsFor($user);
        if ($agents->isEmpty()) {
            return null;
        }

        $expectedAgencyName = trim((string) ($expectedAgencyName ?? ($user->meta['company_name'] ?? '')));

        if ($expectedAgencyName !== '') {
            $matching = $agents->filter(
                fn (Agent $agent): bool => $agent->agency !== null
                    && self::namesMatch($expectedAgencyName, self::displayName($agent->agency))
            );
            $activeMatch = $matching->first(fn (Agent $agent): bool => (bool) $agent->is_active);
            if ($activeMatch !== null) {
                return $activeMatch;
            }
            if ($matching->isNotEmpty()) {
                return $matching->sortByDesc('id')->first();
            }
        }

        if ($user->current_agency_id !== null) {
            $onCurrent = $agents->where('agency_id', $user->current_agency_id);
            $activeOnCurrent = $onCurrent->first(fn (Agent $agent): bool => (bool) $agent->is_active);
            if ($activeOnCurrent !== null) {
                return $activeOnCurrent;
            }
            if ($onCurrent->isNotEmpty()) {
                return $onCurrent->sortByDesc('id')->first();
            }
        }

        $platformSlug = (string) config('ota.default_agency_slug', '');
        $nonPlatformActive = $agents->filter(
            fn (Agent $agent): bool => (bool) $agent->is_active
                && $agent->agency !== null
                && ($platformSlug === '' || $agent->agency->slug !== $platformSlug)
        );
        if ($nonPlatformActive->isNotEmpty()) {
            return $nonPlatformActive->sortByDesc('id')->first();
        }

        $anyActive = $agents->first(fn (Agent $agent): bool => (bool) $agent->is_active);

        return $anyActive ?? $agents->sortByDesc('id')->first();
    }

    /**
     * @return Collection<int, Agent>
     */
    public static function ownerAgentsFor(User $user): Collection
    {
        if ($user->relationLoaded('agentProfiles')) {
            return $user->agentProfiles
                ->loadMissing(['agency.agencySetting'])
                ->values();
        }

        return $user->agentProfiles()
            ->with(['agency.agencySetting'])
            ->orderBy('id')
            ->get();
    }

    public static function namesMatch(string $left, string $right): bool
    {
        return Str::slug(strtolower(trim($left))) === Str::slug(strtolower(trim($right)));
    }
}
