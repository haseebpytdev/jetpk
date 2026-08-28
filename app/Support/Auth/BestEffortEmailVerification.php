<?php

namespace App\Support\Auth;

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends customer email verification without rolling back DB work or producing HTTP 500.
 */
final class BestEffortEmailVerification
{
    public const SESSION_DELIVERY_KEY = 'verification_delivery';

    public const DELIVERY_SUCCESS = 'success';

    public const DELIVERY_FAILURE = 'failure';

    public const FAILURE_MESSAGE = 'Your account was created, but we couldn\'t send the verification email. Please try sending it again.';

    /**
     * Attempt verification notification immediately. Never throws.
     */
    public static function send(User $user): bool
    {
        if (! $user instanceof MustVerifyEmail || $user->hasVerifiedEmail()) {
            self::rememberDelivery(true);

            return true;
        }

        try {
            $user->sendEmailVerificationNotification();
            self::rememberDelivery(true);

            return true;
        } catch (Throwable $e) {
            report($e);
            Log::warning('auth.verification_email_delivery_failed', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);
            self::rememberDelivery(false);

            return false;
        }
    }

    /**
     * Defer send until the current DB transaction commits (or run immediately when idle).
     */
    public static function sendAfterCommit(User $user): void
    {
        $run = static function () use ($user): void {
            self::send($user);
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($run);

            return;
        }

        $run();
    }

    public static function rememberDelivery(bool $ok): void
    {
        if (! app()->bound('session') || ! session()->isStarted()) {
            return;
        }

        session([
            self::SESSION_DELIVERY_KEY => $ok ? self::DELIVERY_SUCCESS : self::DELIVERY_FAILURE,
        ]);

        if (! $ok) {
            session()->flash('verification_delivery_failed', self::FAILURE_MESSAGE);
            session()->flash('status', 'verification-delivery-failed');
        }
    }

    public static function lastDeliverySucceeded(): ?bool
    {
        if (! app()->bound('session') || ! session()->isStarted()) {
            return null;
        }

        $value = session(self::SESSION_DELIVERY_KEY);
        if ($value === self::DELIVERY_SUCCESS) {
            return true;
        }
        if ($value === self::DELIVERY_FAILURE) {
            return false;
        }

        return null;
    }
}
