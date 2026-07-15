<?php

namespace App\Support\Emails;

/**
 * JetpkEmailViewResolver
 *
 * Maps a logical email "type" key to the JetPakistan Blade view path.
 * Read-only, client-specific. Does NOT change any mail-sending logic.
 *
 * Usage:
 *   $view = JetpkEmailViewResolver::resolve('booking_created', $clientSlug);
 *   if ($view) { return view($view, $payload); }
 */
class JetpkEmailViewResolver
{
    /** Only this client uses the JetPK theme. */
    public const CLIENT_SLUG = 'jetpk';

    /**
     * Hard fallback map, used when config('jetpk_email.views') is absent.
     * Keep in sync with config/jetpk_email.php.
     *
     * @var array<string, string>
     */
    protected static array $map = [
        // Auth / security
        'otp'                            => 'emails.themes.jetpakistan.auth.otp',
        'sign_in_success'                => 'emails.themes.jetpakistan.auth.sign-in-success',
        'password_reset'                 => 'emails.themes.jetpakistan.auth.password-reset',
        'account_created'                => 'emails.themes.jetpakistan.auth.account-created',

        // Booking
        'booking_created'                => 'emails.themes.jetpakistan.booking.booking-created',
        'booking_pending_manual_payment' => 'emails.themes.jetpakistan.booking.booking-pending-manual-payment',
        'booking_confirmed'              => 'emails.themes.jetpakistan.booking.booking-confirmed',
        'booking_failed'                 => 'emails.themes.jetpakistan.booking.booking-failed',
        'booking_cancelled'              => 'emails.themes.jetpakistan.booking.booking-cancelled',
        'booking_updated'                => 'emails.themes.jetpakistan.booking.booking-updated',
        'booking_expiring'               => 'emails.themes.jetpakistan.booking.booking-expiring',

        // Payment
        'manual_payment_received'        => 'emails.themes.jetpakistan.payment.manual-payment-received',
        'payment_success'                => 'emails.themes.jetpakistan.payment.payment-success',
        'payment_failed'                 => 'emails.themes.jetpakistan.payment.payment-failed',
        'invoice'                        => 'emails.themes.jetpakistan.payment.invoice',
        'refund_requested'               => 'emails.themes.jetpakistan.payment.refund-requested',
        'refund_updated'                 => 'emails.themes.jetpakistan.payment.refund-updated',

        // Support
        'support_ticket_created'         => 'emails.themes.jetpakistan.support.support-ticket-created',
        'support_reply'                  => 'emails.themes.jetpakistan.support.support-reply',

        // Generic
        'notification'                   => 'emails.themes.jetpakistan.generic.notification',
    ];

    /**
     * Resolve a JetPK view path for the given type.
     * Returns null when the client is not JetPK or the type is unknown.
     */
    public static function resolve(string $type, ?string $clientSlug = self::CLIENT_SLUG): ?string
    {
        if ($clientSlug !== self::CLIENT_SLUG) {
            return null; // Never apply JetPK theme to other clients.
        }

        $type = static::normalizeType($type);

        // Prefer config so integrators can override without touching code.
        $configured = static::configMap();

        return $configured[$type] ?? static::$map[$type] ?? null;
    }

    /**
     * Full type => view map (config overrides + defaults).
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        return array_merge(static::$map, static::configMap());
    }

    /**
     * @return array<string, string>
     */
    protected static function configMap(): array
    {
        if (function_exists('config')) {
            $views = config('jetpk_email.views');
            if (is_array($views)) {
                return $views;
            }
        }

        return [];
    }

    /** Normalise dashes/spaces to snake_case keys. */
    protected static function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));

        return str_replace(['-', ' '], '_', $type);
    }
}
