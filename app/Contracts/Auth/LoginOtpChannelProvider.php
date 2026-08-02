<?php

namespace App\Contracts\Auth;

use App\Models\User;

/**
 * Production OTP delivery contract (JP-OPS-02 readiness).
 *
 * The approved demo fixed-OTP patch remains active until separately authorized
 * for removal. Implementations must never return OTP values in API responses.
 */
interface LoginOtpChannelProvider
{
    /**
     * Deliver a login OTP to the user through the production channel.
     *
     * @throws \App\Exceptions\Auth\LoginOtpDeliveryException when delivery fails
     */
    public function sendLoginOtp(User $user, string $otpCode, int $expiresInMinutes): void;

    /**
     * Whether this provider is configured for the current runtime environment.
     */
    public function isConfigured(): bool;
}
