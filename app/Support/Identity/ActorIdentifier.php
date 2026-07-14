<?php

namespace App\Support\Identity;

use App\Enums\AccountType;
use App\Models\Agency;
use App\Models\User;
use App\Support\Agencies\AgencyPrefixService;
use Illuminate\Support\Str;

/**
 * Display-only actor codes for admin/operator UI (not login usernames).
 */
final class ActorIdentifier
{
    public static function forUser(?User $user): string
    {
        if ($user === null) {
            return 'System';
        }

        $firstName = self::sanitizeName(self::extractFirstName($user->name));
        $id = (int) $user->id;

        return match ($user->account_type) {
            AccountType::PlatformAdmin => "ADM-{$id}-{$firstName}",
            AccountType::Staff => "STF-{$id}-{$firstName}",
            AccountType::Agent => self::agencyPrefixForUser($user)."-AGM-{$id}-{$firstName}",
            AccountType::AgentStaff => self::agencyPrefixForUser($user)."-AGST-{$id}-{$firstName}",
            AccountType::Customer => "CU-{$id}-{$firstName}",
            default => "USR-{$id}-{$firstName}",
        };
    }

    /**
     * @param  array<string, mixed>|object  $guestRecord
     */
    public static function forGuest(array|object $guestRecord): string
    {
        $data = is_array($guestRecord) ? $guestRecord : (array) $guestRecord;
        $guestId = (int) ($data['guest_id'] ?? $data['id'] ?? 0);
        $firstName = self::sanitizeName((string) ($data['first_name'] ?? ''));

        if ($firstName === 'User' && ! empty($data['name'])) {
            $firstName = self::sanitizeName(self::extractFirstName((string) $data['name']));
        }

        return 'GU-'.$guestId.'-'.$firstName;
    }

    public static function forUserId(?int $userId): string
    {
        if ($userId === null || $userId <= 0) {
            return 'System';
        }

        $user = User::query()->find($userId);

        return self::forUser($user);
    }

    public static function agencyPrefixForUser(User $user): string
    {
        $agency = $user->relationLoaded('currentAgency')
            ? $user->currentAgency
            : $user->currentAgency()->first();

        if ($agency instanceof Agency) {
            return AgencyPrefixService::resolvePrefix($agency);
        }

        return 'AG';
    }

    public static function agencyPrefixForAgency(Agency $agency): string
    {
        return AgencyPrefixService::resolvePrefix($agency);
    }

    public static function sanitizePrefix(string $prefix): string
    {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $prefix) ?? '');

        return substr($clean, 0, 4);
    }

    public static function sanitizeName(?string $name): string
    {
        $trimmed = trim((string) $name);
        if ($trimmed === '') {
            return 'User';
        }

        $first = preg_split('/\s+/', $trimmed)[0] ?? $trimmed;
        $alpha = preg_replace('/[^A-Za-z]/', '', $first) ?? '';

        if ($alpha === '') {
            return 'User';
        }

        return Str::title(strtolower($alpha));
    }

    public static function extractFirstName(?string $fullName): string
    {
        $trimmed = trim((string) $fullName);
        if ($trimmed === '') {
            return '';
        }

        return preg_split('/\s+/', $trimmed)[0] ?? $trimmed;
    }
}
