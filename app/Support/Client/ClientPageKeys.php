<?php

namespace App\Support\Client;

/**
 * Whitelisted client-scoped public page keys for the JetPK page builder.
 */
final class ClientPageKeys
{
    public const HOME = 'home';

    public const ABOUT = 'about';

    public const SUPPORT = 'support';

    public const GROUP_SEARCH = 'group-search';

    public const LOGIN = 'login';

    public const REGISTER = 'register';

    public const FOOTER = 'footer';

    public const GLOBAL = 'global';

    public const TERMS = 'terms';

    public const PRIVACY = 'privacy';

    public const FAQ = 'faq';

    public const BOOKING_LOOKUP = 'booking-lookup';

    public const AGENT_REGISTRATION = 'agent-registration';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::HOME,
            self::ABOUT,
            self::SUPPORT,
            self::GROUP_SEARCH,
            self::LOGIN,
            self::REGISTER,
            self::FOOTER,
            self::GLOBAL,
            self::TERMS,
            self::PRIVACY,
            self::FAQ,
            self::BOOKING_LOOKUP,
            self::AGENT_REGISTRATION,
        ];
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::HOME => 'Home page',
            self::ABOUT => 'About page',
            self::SUPPORT => 'Support page',
            self::GROUP_SEARCH => 'Group search hero',
            self::LOGIN => 'Login page',
            self::REGISTER => 'Register page',
            self::FOOTER => 'Footer & links',
            self::GLOBAL => 'Global public settings',
            self::TERMS => 'Terms of service',
            self::PRIVACY => 'Privacy policy',
            self::FAQ => 'FAQ / help centre',
            self::BOOKING_LOOKUP => 'Booking lookup',
            self::AGENT_REGISTRATION => 'Agent registration landing',
        ];
    }

    /** @return array<string, string> */
    public static function previewRoutes(): array
    {
        return [
            self::HOME => 'home',
            self::ABOUT => 'about',
            self::SUPPORT => 'support',
            self::GROUP_SEARCH => 'group-ticketing.search',
            self::LOGIN => 'login',
            self::REGISTER => 'register',
            self::FOOTER => 'home',
            self::GLOBAL => 'home',
            self::TERMS => 'terms',
            self::PRIVACY => 'privacy',
            self::FAQ => 'faq',
            self::BOOKING_LOOKUP => 'booking.lookup',
            self::AGENT_REGISTRATION => 'agent.register',
        ];
    }

    public const CUSTOM_PREFIX = 'custom:';

    public static function isValid(string $pageKey): bool
    {
        if (self::isCustom($pageKey)) {
            $slug = self::customSlug($pageKey);

            return $slug !== ''
                && ClientManagedPageReservedSlugs::isValidFormat($slug)
                && ! ClientManagedPageReservedSlugs::isReserved($slug);
        }

        return in_array($pageKey, self::all(), true);
    }

    public static function isCustom(string $pageKey): bool
    {
        return str_starts_with($pageKey, self::CUSTOM_PREFIX);
    }

    public static function customKey(string $slug): string
    {
        return self::CUSTOM_PREFIX.ClientManagedPageReservedSlugs::normalize($slug);
    }

    public static function customSlug(string $pageKey): string
    {
        return self::isCustom($pageKey)
            ? ClientManagedPageReservedSlugs::normalize(substr($pageKey, strlen(self::CUSTOM_PREFIX)))
            : '';
    }
}
