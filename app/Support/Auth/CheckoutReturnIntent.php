<?php

namespace App\Support\Auth;

use Illuminate\Http\Request;

/**
 * Stores Laravel's post-auth redirect when the user opens login/register from checkout.
 * Supports {@see redirect} (preferred) and legacy {@see checkout_return} query params.
 */
class CheckoutReturnIntent
{
    public static function primeSessionFromQuery(Request $request): void
    {
        $target = $request->query('redirect');
        if (! is_string($target) || $target === '') {
            $target = $request->query('checkout_return');
        }
        if (! is_string($target) || $target === '') {
            return;
        }

        if (! self::isAllowedCheckoutReturn($target)) {
            return;
        }

        $request->session()->put('url.intended', url($target));
    }

    public static function isAllowedCheckoutReturn(string $target): bool
    {
        if (str_contains($target, "\n") || str_contains($target, "\r")) {
            return false;
        }

        if (str_contains($target, '://')) {
            return false;
        }

        if (! str_starts_with($target, '/')) {
            return false;
        }

        if (str_starts_with($target, '//')) {
            return false;
        }

        $path = parse_url($target, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return false;
        }

        if (str_starts_with($path, '/booking/passengers')) {
            return true;
        }

        if (self::isGroupBookingReturn($path)) {
            return true;
        }

        return preg_match('#^/customer/bookings/\d+$#', $path) === 1;
    }

    public static function isGroupBookingReturn(string $path): bool
    {
        return preg_match('#^/groups/[^/]+/passengers$#', $path) === 1;
    }

    public static function hasGroupBookingIntent(Request $request): bool
    {
        foreach (['redirect', 'checkout_return'] as $key) {
            $target = $request->query($key);
            if (is_string($target) && $target !== '' && self::isAllowedCheckoutReturn($target)) {
                $path = parse_url($target, PHP_URL_PATH);
                if (is_string($path) && self::isGroupBookingReturn($path)) {
                    return true;
                }
            }
        }

        $intended = $request->session()->get('url.intended');
        if (is_string($intended) && $intended !== '') {
            $path = parse_url($intended, PHP_URL_PATH);
            if (is_string($path) && self::isGroupBookingReturn($path)) {
                return true;
            }
        }

        return false;
    }
}
