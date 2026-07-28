<?php

namespace App\Support\Client;

/**
 * Registry of JetPK managed public pages for CMS ownership audits and classification.
 */
final class ClientManagedPageCatalog
{
    public const OWNERSHIP_CONTENT = 'CONTENT_OWNED';

    public const OWNERSHIP_HYBRID = 'HYBRID_FUNCTIONAL';

    public const OWNERSHIP_GLOBAL = 'GLOBAL_COMPONENT';

    public const SOURCE_CMS_AUTHORITATIVE = 'CMS_AUTHORITATIVE';

    public const SOURCE_CMS_SAFE_EMPTY = 'CMS_WITH_SAFE_EMPTY_FALLBACK';

    public const SOURCE_CMS_BOOTSTRAP = 'CMS_WITH_BOOTSTRAP_TEMPLATE';

    public const SOURCE_HARDCODED_OVERRIDES = 'HARDCODED_OVERRIDES_CMS';

    public const SOURCE_HARDCODED_IF_MISSING = 'HARDCODED_IF_KEY_MISSING';

    public const SOURCE_DISCONNECTED = 'FRONTEND_DISCONNECTED_FROM_CMS';

    public const SOURCE_MULTIPLE = 'MULTIPLE_COMPETING_SOURCES';

    /**
     * @return list<array<string, mixed>>
     */
    public static function pages(): array
    {
        return [
            self::entry(
                ClientPageKeys::HOME,
                self::OWNERSHIP_HYBRID,
                '/',
                'home',
                'admin.page-settings.edit',
                'themes/frontend/jetpakistan/frontend/home.blade.php',
                'App\Http\Controllers\Frontend\HomeController@index',
                self::SOURCE_MULTIPLE,
            ),
            self::entry(
                ClientPageKeys::ABOUT,
                self::OWNERSHIP_CONTENT,
                '/about-us',
                'about',
                'admin.page-settings.edit',
                'themes/frontend/jetpakistan/frontend/about.blade.php',
                'App\Http\Controllers\Frontend\SupportController@about',
                self::SOURCE_HARDCODED_IF_MISSING,
            ),
            self::entry(
                ClientPageKeys::SUPPORT,
                self::OWNERSHIP_HYBRID,
                '/support',
                'support',
                'admin.page-settings.edit',
                'themes/frontend/jetpakistan/frontend/support.blade.php',
                'App\Http\Controllers\Frontend\SupportController@support',
                self::SOURCE_HARDCODED_IF_MISSING,
            ),
            self::entry(
                ClientPageKeys::GROUP_SEARCH,
                self::OWNERSHIP_HYBRID,
                '/groups/search',
                'group-ticketing.search',
                'admin.page-settings.edit',
                'themes/frontend/jetpakistan/frontend/group-ticketing/search.blade.php',
                'App\Http\Controllers\Frontend\GroupTicketingSearchController@index',
                self::SOURCE_DISCONNECTED,
            ),
            self::entry(
                ClientPageKeys::LOGIN,
                self::OWNERSHIP_HYBRID,
                '/login',
                'login',
                'admin.page-settings.edit',
                'themes/frontend/jetpakistan/auth/login.blade.php',
                'App\Http\Controllers\Auth\AuthenticatedSessionController@create',
                self::SOURCE_DISCONNECTED,
            ),
            self::entry(
                ClientPageKeys::REGISTER,
                self::OWNERSHIP_HYBRID,
                '/register',
                'register',
                'admin.page-settings.edit',
                'themes/frontend/jetpakistan/auth/register.blade.php',
                'App\Http\Controllers\Auth\RegisteredUserController@create',
                self::SOURCE_DISCONNECTED,
            ),
            self::entry(
                ClientPageKeys::FOOTER,
                self::OWNERSHIP_GLOBAL,
                '/',
                'home',
                'admin.page-settings.edit',
                'themes/frontend/jetpakistan/partials/footer.blade.php',
                'layout partial',
                self::SOURCE_DISCONNECTED,
            ),
            self::entry(
                ClientPageKeys::GLOBAL,
                self::OWNERSHIP_GLOBAL,
                '/',
                'home',
                'admin.page-settings.edit',
                'themes/frontend/jetpakistan/layouts/frontend.blade.php',
                'layout partial',
                self::SOURCE_DISCONNECTED,
            ),
            self::entry(
                ClientPageKeys::TERMS,
                self::OWNERSHIP_CONTENT,
                '/pages/terms-and-conditions',
                'pages.show',
                'admin.page-settings.edit',
                'resources/views/frontend/cms-pages/show.blade.php',
                'App\Http\Controllers\Frontend\CmsPageController@show',
                self::SOURCE_DISCONNECTED,
            ),
            self::entry(
                ClientPageKeys::PRIVACY,
                self::OWNERSHIP_CONTENT,
                '/pages/privacy-policy',
                'pages.show',
                'admin.page-settings.edit',
                'resources/views/frontend/cms-pages/show.blade.php',
                'App\Http\Controllers\Frontend\CmsPageController@show',
                self::SOURCE_DISCONNECTED,
            ),
            self::entry(
                ClientPageKeys::FAQ,
                self::OWNERSHIP_CONTENT,
                '/faq',
                'faq',
                'admin.page-settings.edit',
                'themes/frontend/jetpakistan/frontend/faq.blade.php',
                'App\Http\Controllers\Frontend\ClientManagedPageController@faq',
                self::SOURCE_DISCONNECTED,
            ),
            self::entry(
                ClientPageKeys::BOOKING_LOOKUP,
                self::OWNERSHIP_HYBRID,
                '/lookup-booking',
                'booking.lookup',
                'admin.page-settings.edit',
                'themes/frontend/jetpakistan/frontend/booking/lookup.blade.php',
                'App\Http\Controllers\Frontend\GuestBookingLookupController@showLookupForm',
                self::SOURCE_DISCONNECTED,
            ),
            self::entry(
                ClientPageKeys::AGENT_REGISTRATION,
                self::OWNERSHIP_HYBRID,
                '/agent/register',
                'agent.register',
                'admin.page-settings.edit',
                'themes/frontend/jetpakistan/frontend/agent-registration/landing.blade.php',
                'App\Http\Controllers\Frontend\AgentRegistrationController@landing',
                self::SOURCE_DISCONNECTED,
            ),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function keyed(): array
    {
        $keyed = [];
        foreach (self::pages() as $page) {
            $keyed[$page['page_key']] = $page;
        }

        return $keyed;
    }

    /**
     * @return list<string>
     */
    public static function managedFrontendViewPaths(): array
    {
        return [
            'themes/frontend/jetpakistan/frontend/about.blade.php',
            'themes/frontend/jetpakistan/frontend/support.blade.php',
            'themes/frontend/jetpakistan/frontend/home.blade.php',
            'themes/frontend/jetpakistan/frontend/faq.blade.php',
            'themes/frontend/jetpakistan/frontend/booking/lookup.blade.php',
            'themes/frontend/jetpakistan/frontend/agent-registration/landing.blade.php',
            'themes/frontend/jetpakistan/frontend/group-ticketing/search.blade.php',
            'themes/frontend/jetpakistan/auth/login.blade.php',
            'themes/frontend/jetpakistan/auth/register.blade.php',
            'themes/frontend/jetpakistan/layouts/auth.blade.php',
            'themes/frontend/jetpakistan/partials/header.blade.php',
            'themes/frontend/jetpakistan/partials/footer.blade.php',
            'themes/frontend/jetpakistan/partials/drawer.blade.php',
            'themes/frontend/jetpakistan/sections/hero.blade.php',
            'themes/frontend/jetpakistan/sections/why-book.blade.php',
            'themes/frontend/jetpakistan/sections/fares.blade.php',
            'themes/frontend/jetpakistan/sections/destinations.blade.php',
            'themes/frontend/jetpakistan/sections/routes.blade.php',
            'themes/frontend/jetpakistan/sections/groups.blade.php',
            'themes/frontend/jetpakistan/sections/trust.blade.php',
            'themes/frontend/jetpakistan/sections/support-cta.blade.php',
            'themes/frontend/jetpakistan/sections/feature-board.blade.php',
            'themes/frontend/jetpakistan/frontend/legal/show.blade.php',
            'themes/frontend/jetpakistan/frontend/content-page.blade.php',
        ];
    }

    /**
     * @return list<string>
     */
    public static function managedServicePaths(): array
    {
        return [
            'app/Support/Client/JetpkHomepageSectionData.php',
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private static function entry(
        string $pageKey,
        string $ownership,
        string $publicPath,
        string $publicRoute,
        string $adminRoute,
        string $blade,
        string $controller,
        string $runtimeClassification,
        array $overrides = [],
    ): array {
        return array_merge([
            'page_key' => $pageKey,
            'label' => ClientPageKeys::labels()[$pageKey] ?? $pageKey,
            'ownership_type' => $ownership,
            'public_path' => $publicPath,
            'public_route' => $publicRoute,
            'admin_route' => $adminRoute,
            'blade' => $blade,
            'controller' => $controller,
            'draft_source' => 'client_page_settings.status=draft',
            'published_source' => 'client_page_settings.status=published',
            'runtime_classification' => $runtimeClassification,
            'preview_available' => true,
            'publish_available' => true,
            'save_draft_available' => true,
        ], $overrides);
    }
}
