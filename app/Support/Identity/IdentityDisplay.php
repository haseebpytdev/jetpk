<?php

namespace App\Support\Identity;

use App\Models\Agency;
use App\Models\Agent;
use App\Models\User;
use App\Support\Access\AccountTypeLabels;
use App\Support\Agencies\AgencyPrefixService;
use Illuminate\Support\Facades\Schema;

/**
 * Display-only labels and formatting for agency vs performer identity (UI only).
 */
final class IdentityDisplay
{
    public static function labelAgencyCode(): string
    {
        return 'Agency code';
    }

    public static function labelAgencyPrefix(): string
    {
        return 'Agency code prefix';
    }

    public static function labelLegacyAgentProfileCode(): string
    {
        return 'Legacy agent profile code';
    }

    public static function labelPerformedBy(): string
    {
        return 'Performed by';
    }

    public static function labelPostedBy(): string
    {
        return 'Posted by';
    }

    public static function labelRequestedBy(): string
    {
        return 'Requested by';
    }

    public static function labelUserActorId(): string
    {
        return 'User / Actor ID';
    }

    public static function labelAccessType(): string
    {
        return 'Access type';
    }

    /**
     * Agency business code for UI: stored prefix, optional agencies.code when present.
     */
    public static function agencyCodeDisplay(?Agency $agency): ?string
    {
        if ($agency === null) {
            return null;
        }

        $prefix = AgencyPrefixService::storedPrefix($agency) ?? AgencyPrefixService::resolvePrefix($agency);
        $segments = [$prefix];

        if (Schema::hasColumn($agency->getTable(), 'code')) {
            $storedCode = trim((string) ($agency->code ?? ''));
            if ($storedCode !== '' && $storedCode !== $prefix) {
                $segments[] = $storedCode;
            }
        }

        $segments = array_values(array_filter($segments, fn (string $s): bool => $s !== ''));

        return $segments === [] ? null : implode(' · ', $segments);
    }

    public static function legacyAgentProfileCode(?Agent $agent): ?string
    {
        if ($agent === null) {
            return null;
        }

        $code = trim((string) ($agent->code ?? ''));

        return $code !== '' ? $code : 'AGT-'.$agent->id;
    }

    public static function userActorId(?User $user): string
    {
        return ActorIdentifier::forUser($user);
    }

    public static function accessTypeLabel(?User $user): string
    {
        if ($user === null) {
            return '—';
        }

        return AccountTypeLabels::label($user->account_type);
    }
}
