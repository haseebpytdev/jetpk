<?php

namespace App\Support\Client;

/**
 * Platform-owned literals permitted in managed public views at runtime.
 */
final class ClientManagedPageHardcodeAllowlist
{
    /**
     * @return list<string>
     */
    public static function platformLiterals(): array
    {
        return [
            'Create account',
            'Log in',
            'Lookup your booking',
            'Agent partnership',
            'Benefits for agents',
            'Support & contact',
            'check-square',
            'route:',
            'Sign in',
            'Register',
            'Password',
            'Email or username',
            'Booking reference',
            'Email address',
            'Lookup booking',
            'Manage booking',
            'Continue with Google',
            'Switch day or night theme',
            'Open menu',
            'Primary',
            'Customer Registration',
            'Agent Registration',
            'Preparing your journey',
            'Search flights',
            'Contact support',
            'Fares available',
            'CSRF',
            '@csrf',
            'aria-label',
            'role=',
            'data-jp-',
            'x-jp.',
            'x-turnstile',
            'x-account-dropdown',
            'x-jp.brand-logo',
            'x-jp.page-hero',
            'x-jp.card',
            'x-jp.form-group',
            'x-jp.button',
            'x-jp.alert',
            'x-jp.icon',
            'x-jp.google-sign-in',
            'required',
            'autocomplete',
            'type="submit"',
            'method="POST"',
            'method="post"',
            '@guest',
            '@else',
            '@endguest',
            '@auth',
            '@endauth',
            '@extends',
            '@section',
            '@push',
            '@endsection',
            'client_route(',
            'client_url(',
            'client_page_content(',
            'client_page_field(',
            'client_page_asset(',
            'client_branding(',
            'route(',
            'old(',
            'session(',
            'errors->',
            '$errors',
            'CheckoutReturnIntent',
            'platform.module',
            'noindex',
            'nofollow',
            'tel:',
            'mailto:',
            'https://wa.me/',
            'target="_blank"',
            'rel="noopener"',
            'PCI-DSS',
            'IATA',
            'PCAA',
            'All rights reserved',
            '©',
            'date(',
            'parse_url(',
            'PHP_URL_HOST',
            'hidden',
            'aria-hidden',
            'aria-live',
            'aria-expanded',
            'aria-haspopup',
            'aria-busy',
            'aria-invalid',
            'tabindex',
            'autofocus',
            'block',
            'details',
            'summary',
            'Turnstile',
            'CAPTCHA',
            'OTP',
        ];
    }

    /**
     * Client-specific contact patterns that must not appear as runtime literals.
     *
     * @return list<string>
     */
    public static function forbiddenContactPatterns(): array
    {
        return [
            '0311 1222427',
            '+923111222427',
            '923111222427',
            'ota@jetpakistan.pk',
            'facebook.com/jetpakistan',
            'instagram.com/jetpakistan',
            'Century Tower',
            'Kalma Chowk',
            'Gulberg III',
            '+92 21 111 000 000',
            'user@ota.demo',
        ];
    }

    /**
     * @return list<string>
     */
    public static function forbiddenLegalPatterns(): array
    {
        return [
            'Terms and Conditions',
            'Privacy Policy',
            'refund policy',
            'cancellation policy',
            'baggage policy',
        ];
    }
}
