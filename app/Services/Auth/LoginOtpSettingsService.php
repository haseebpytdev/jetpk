<?php

namespace App\Services\Auth;

use App\Models\ClientProfile;
use App\Models\ClientProfileBranding;
use App\Services\Client\ClientProfileResolver;
use App\Support\Auth\ClientLoginOtpGate;

/**
 * Persists JetPakistan login OTP gate on ClientProfile branding.config.auth.require_login_otp.
 *
 * This is the admin → storage → runtime authority for login OTP (all roles share one gate).
 */
final class LoginOtpSettingsService
{
    public const CONFIG_KEY = 'require_login_otp';

    public function __construct(
        private readonly ClientProfileResolver $profiles,
    ) {}

    /**
     * @return array{
     *     required: bool,
     *     source: 'profile'|'env'|'default',
     *     env_default: bool,
     *     profile_override: bool|null,
     *     applies_to_roles: list<string>
     * }
     */
    public function snapshot(): array
    {
        $envDefault = (bool) config('ota_client.auth.require_login_otp', false);
        $override = $this->profileOverride();

        return [
            'required' => ClientLoginOtpGate::isRequired(),
            'source' => $override !== null ? 'profile' : ($envDefault ? 'env' : 'default'),
            'env_default' => $envDefault,
            'profile_override' => $override,
            'applies_to_roles' => ['customer', 'agent', 'staff', 'admin'],
        ];
    }

    public function setRequired(bool $required): ClientProfileBranding
    {
        $profile = $this->resolveJetPkProfile();
        $branding = $profile->branding;
        if ($branding === null) {
            $branding = ClientProfileBranding::query()->create([
                'client_profile_id' => $profile->id,
                'company_name' => $profile->name ?: 'JetPakistan',
                'config' => [],
            ]);
            $profile->setRelation('branding', $branding);
        }

        $config = is_array($branding->config) ? $branding->config : [];
        $auth = is_array($config['auth'] ?? null) ? $config['auth'] : [];
        $auth[self::CONFIG_KEY] = $required;
        $config['auth'] = $auth;

        $branding->forceFill(['config' => $config])->save();

        return $branding->fresh();
    }

    public function clearOverride(): void
    {
        $profile = $this->resolveJetPkProfile();
        $branding = $profile->branding;
        if ($branding === null) {
            return;
        }

        $config = is_array($branding->config) ? $branding->config : [];
        $auth = is_array($config['auth'] ?? null) ? $config['auth'] : [];
        if (! array_key_exists(self::CONFIG_KEY, $auth)) {
            return;
        }

        unset($auth[self::CONFIG_KEY]);
        if ($auth === []) {
            unset($config['auth']);
        } else {
            $config['auth'] = $auth;
        }

        $branding->forceFill(['config' => $config])->save();
    }

    private function profileOverride(): ?bool
    {
        try {
            $profile = $this->profiles->resolveBySlug('jetpk');
            $config = is_array($profile?->branding?->config) ? $profile->branding->config : [];
            $auth = is_array($config['auth'] ?? null) ? $config['auth'] : [];
            if (array_key_exists(self::CONFIG_KEY, $auth)) {
                return (bool) $auth[self::CONFIG_KEY];
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    private function resolveJetPkProfile(): ClientProfile
    {
        $profile = $this->profiles->resolveBySlug('jetpk');
        if (! $profile instanceof ClientProfile) {
            throw new \RuntimeException('JetPakistan client profile is missing; cannot persist login OTP settings.');
        }

        return $profile;
    }
}
