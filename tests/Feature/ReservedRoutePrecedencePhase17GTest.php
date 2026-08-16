<?php

namespace Tests\Feature;

use App\Support\Client\ClientManagedPageReservedSlugs;
use App\Support\Client\ReservedPublicPath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Phase 17G: CMS catch-all reserved-path constraint and admin route precedence closure.
 */
class ReservedRoutePrecedencePhase17GTest extends TestCase
{
    /**
     * @param  array{method: string, uri: string, expected: string}  $case
     */
    #[DataProvider('deterministicRoutePrecedenceProvider')]
    public function test_deterministic_route_precedence(array $case): void
    {
        $this->assertSame(
            $case['expected'],
            $this->matchRouteName($case['method'], $case['uri']),
            "Expected {$case['method']} {$case['uri']} to resolve to {$case['expected']}",
        );
    }

    #[DataProvider('minimumReservedRootSlugProvider')]
    public function test_reserved_root_slugs_never_match_custom_page_show(string $slug): void
    {
        $this->assertTrue(ClientManagedPageReservedSlugs::isReserved($slug), "Expected reserved slug: {$slug}");

        $matched = $this->matchRouteName('GET', '/'.$slug);
        $this->assertNotSame(
            'client.custom-page.show',
            $matched,
            "Reserved root /{$slug} must not match client.custom-page.show; got {$matched}",
        );
    }

    public function test_custom_page_route_uses_canonical_reserved_slug_constraint(): void
    {
        $route = Route::getRoutes()->getByName('client.custom-page.show');
        $this->assertNotNull($route);
        $this->assertArrayHasKey('slug', $route->wheres);
        $this->assertSame(
            ClientManagedPageReservedSlugs::routeSlugConstraint(),
            $route->wheres['slug'],
        );
        $this->assertSame(
            ReservedPublicPath::customPageSlugConstraint(),
            ClientManagedPageReservedSlugs::routeSlugConstraint(),
        );
    }

    public function test_valid_custom_slug_matches_custom_page_show_route(): void
    {
        $slug = 'valid-custom-slug';
        $this->assertFalse(ClientManagedPageReservedSlugs::isReserved($slug));
        $this->assertSame(1, preg_match(
            '/^'.ClientManagedPageReservedSlugs::routeSlugConstraint().'$/',
            $slug,
        ));
        $this->assertSame('client.custom-page.show', $this->matchRouteName('GET', '/'.$slug));
    }

    public function test_production_style_web_catch_all_gets_constraint_patch(): void
    {
        $route = Route::getRoutes()->getByName('client.custom-page.show');
        $this->assertNotNull($route);

        // Simulate production web.php catch-all registered with format-only constraint.
        $route->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*');

        app(\App\Services\Client\ClientCustomPageRouteRegistrar::class)->register();

        $this->assertSame(
            ClientManagedPageReservedSlugs::routeSlugConstraint(),
            $route->wheres['slug'] ?? null,
        );

        $pattern = '/^'.ClientManagedPageReservedSlugs::routeSlugConstraint().'$/';
        $this->assertSame(0, preg_match($pattern, 'admin'));
        $this->assertSame(1, preg_match($pattern, 'valid-custom-slug'));
        $this->assertSame('admin.entry', $this->matchRouteName('GET', '/admin'));
    }

    /**
     * @return array<string, array{0: array{method: string, uri: string, expected: string}}>
     */
    public static function deterministicRoutePrecedenceProvider(): array
    {
        $cases = [
            ['method' => 'GET', 'uri' => '/admin', 'expected' => 'admin.entry'],
            ['method' => 'GET', 'uri' => '/admin/dashboard', 'expected' => 'admin.dashboard'],
            ['method' => 'GET', 'uri' => '/admin/bookings', 'expected' => 'admin.bookings'],
            ['method' => 'POST', 'uri' => '/booking/review', 'expected' => 'booking.review'],
            ['method' => 'GET', 'uri' => '/login', 'expected' => 'login'],
            ['method' => 'GET', 'uri' => '/register', 'expected' => 'register'],
            ['method' => 'GET', 'uri' => '/support', 'expected' => 'support'],
            ['method' => 'GET', 'uri' => '/pages/example', 'expected' => 'pages.show'],
            ['method' => 'GET', 'uri' => '/valid-custom-slug', 'expected' => 'client.custom-page.show'],
        ];

        $provider = [];
        foreach ($cases as $case) {
            $provider[$case['method'].' '.$case['uri']] = [$case];
        }

        return $provider;
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function minimumReservedRootSlugProvider(): array
    {
        $slugs = [
            'admin',
            'api',
            'booking',
            'login',
            'logout',
            'register',
            'customer',
            'agent',
            'staff',
            'dashboard',
            'profile',
            'password',
            'verification',
            'groups',
            'support',
            'pages',
            'storage',
            'vendor',
            'build',
        ];

        $cases = [];
        foreach ($slugs as $slug) {
            $cases[$slug] = [$slug];
        }

        return $cases;
    }

    protected function matchRouteName(string $method, string $uri): string
    {
        $request = Request::create($uri, $method);

        try {
            $route = Route::getRoutes()->match($request);
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
            // Reserved roots may have no named route (filesystem / API prefixes) but must
            // still not fall through to the CMS custom-page catch-all.
            return '';
        }

        return (string) $route->getName();
    }
}
