<?php

namespace Tests\Support\Auth;

/**
 * Isolates auth feature tests from JetPakistan OTP-required deployment defaults.
 */
trait ConfiguresAuthTestEnvironment
{
    protected function withoutLoginOtpGate(): void
    {
        config([
            'ota_client.single_client_mode' => false,
            'ota_client.single_client_root' => false,
            'ota_client.slug' => '',
            'ota_client.auth.require_login_otp' => false,
        ]);
    }

    protected function withJetPkLoginOtpGate(): void
    {
        config([
            'ota_client.single_client_mode' => true,
            'ota_client.single_client_root' => true,
            'ota_client.slug' => 'jetpk',
            'ota_client.auth.require_login_otp' => true,
        ]);
    }
}
