<?php

namespace App\Support\Auth;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Session-gated Google customer onboarding (profile completion after OAuth).
 */
class GoogleOnboarding
{
    public const SESSION_REQUIRED = 'google_onboarding_required';

    public const SESSION_IS_NEW = 'google_onboarding_is_new';

    public static function markSessionRequired(Request $request, bool $isNewCustomer): void
    {
        $request->session()->put(self::SESSION_REQUIRED, true);
        if ($isNewCustomer) {
            $request->session()->put(self::SESSION_IS_NEW, true);
        }
    }

    public static function sessionRequiresCompletion(Request $request): bool
    {
        return (bool) $request->session()->get(self::SESSION_REQUIRED);
    }

    public static function sessionIsNewCustomer(Request $request): bool
    {
        return (bool) $request->session()->get(self::SESSION_IS_NEW);
    }

    public static function clearSession(Request $request): void
    {
        $request->session()->forget([self::SESSION_REQUIRED, self::SESSION_IS_NEW]);
    }

    public static function requiresOnboarding(User $user, bool $wasNewCustomer): bool
    {
        if (! $user->isCustomer()) {
            return false;
        }

        if ($wasNewCustomer) {
            return true;
        }

        return ! self::customerProfileIsComplete($user);
    }

    public static function customerProfileIsComplete(User $user): bool
    {
        if (! $user->isCustomer()) {
            return true;
        }

        [$firstName, $lastName] = self::resolvedNames($user);

        return filled($firstName)
            && filled($lastName)
            && filled(self::resolvedPhone($user));
    }

    /**
     * @return array{first_name: string, last_name: string, email: string, mobile_country_code: string, mobile: string}
     */
    public static function formDefaults(User $user, ?string $oauthDisplayName = null): array
    {
        [$firstName, $lastName] = self::resolvedNames($user);
        if ($firstName === '' && $lastName === '' && filled($oauthDisplayName)) {
            [$firstName, $lastName] = self::splitDisplayName($oauthDisplayName);
        }

        $phone = self::resolvedPhone($user);
        [$countryCode, $mobile] = self::splitStoredPhone($phone);

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => (string) $user->email,
            'mobile_country_code' => $countryCode,
            'mobile' => $mobile,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function resolvedNames(User $user): array
    {
        $meta = is_array($user->meta) ? $user->meta : [];
        $first = trim((string) ($meta['first_name'] ?? ''));
        $last = trim((string) ($meta['last_name'] ?? ''));

        if ($first !== '' && $last !== '') {
            return [$first, $last];
        }

        return self::splitDisplayName(trim((string) ($user->name ?? '')));
    }

    public static function resolvedPhone(User $user): string
    {
        $meta = is_array($user->meta) ? $user->meta : [];
        $fromMeta = trim((string) ($meta['phone'] ?? ''));
        if ($fromMeta !== '') {
            return $fromMeta;
        }

        $user->loadMissing('profile');

        return trim((string) ($user->profile?->phone ?? $user->profile?->whatsapp ?? ''));
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function splitDisplayName(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['', ''];
        }

        $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) === 1) {
            return [$parts[0], ''];
        }

        $last = array_pop($parts);

        return [implode(' ', $parts), $last];
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function splitStoredPhone(string $phone): array
    {
        $phone = trim($phone);
        if ($phone === '') {
            return ['+92', ''];
        }

        if (preg_match('/^(\+\d{1,4})(\d+)$/', $phone, $matches)) {
            return [$matches[1], $matches[2]];
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return ['+92', $digits];
    }

    /**
     * @param  array{first_name: string, last_name: string, phone: string}  $validated
     */
    public static function persistCompletedProfile(User $user, array $validated): void
    {
        $firstName = trim($validated['first_name']);
        $lastName = trim($validated['last_name']);
        $phone = trim($validated['phone']);

        $meta = is_array($user->meta) ? $user->meta : [];
        $meta['first_name'] = $firstName;
        $meta['last_name'] = $lastName;
        $meta['phone'] = $phone;
        $meta['google_onboarding_completed_at'] = now()->toIso8601String();

        $user->forceFill([
            'name' => trim($firstName.' '.$lastName),
            'meta' => $meta,
        ])->save();

        $profile = $user->profile()->firstOrNew([]);
        $profile->phone = $phone;
        $profile->user_id = $user->id;
        $profile->save();
    }
}
