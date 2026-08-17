<?php

namespace Tests\Feature;

use App\Support\Client\ReservedPublicPath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class ReservedRoutePrecedencePhase17CTest extends TestCase
{
    #[DataProvider('reservedFirstSegmentProvider')]
    public function test_reserved_paths_are_not_matched_by_client_custom_page_show(string $path): void
    {
        $matched = $this->matchRouteNameOrNull('GET', $path);

        $this->assertNotSame(
            'client.custom-page.show',
            $matched,
            "Expected {$path} not to match client.custom-page.show; got ".($matched ?? 'null'),
        );

        $segment = trim(explode('/', ltrim($path, '/'))[0] ?? '', '/');
        if ($segment !== '') {
            $this->assertTrue(
                ReservedPublicPath::isReservedFirstSegment($segment),
                "Expected first segment [{$segment}] to be reserved",
            );
        }
    }

    public function test_admin_root_matches_admin_entry_not_custom_page(): void
    {
        // Canonical admin root is a named redirect entry that sends browsers to /admin/dashboard.
        $this->assertSame('admin.entry', $this->matchRouteNameOrNull('GET', '/admin'));
    }

    public function test_client_custom_page_route_uses_reserved_slug_constraint(): void
    {
        $route = Route::getRoutes()->getByName('client.custom-page.show');
        $this->assertNotNull($route);
        $wheres = $route->wheres;
        $this->assertArrayHasKey('slug', $wheres);
        $this->assertSame(ReservedPublicPath::customPageSlugConstraint(), $wheres['slug']);
    }

    public function test_non_reserved_slug_passes_custom_page_route_constraint(): void
    {
        $pattern = '/^'.ReservedPublicPath::customPageSlugConstraint().'$/';
        $this->assertSame(1, preg_match($pattern, 'zz-phase17c-cms-slug'));
        $route = Route::getRoutes()->getByName('client.custom-page.show');
        $this->assertNotNull($route);
    }

    public function test_route_cache_preserves_admin_entry_match(): void
    {
        $this->artisan('route:cache')->assertSuccessful();
        try {
            $this->assertSame('admin.entry', $this->matchRouteNameOrNull('GET', '/admin'));
            $this->assertNotSame('client.custom-page.show', $this->matchRouteNameOrNull('GET', '/login'));
        } finally {
            $this->artisan('route:clear')->assertSuccessful();
        }
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function reservedFirstSegmentProvider(): array
    {
        $paths = [
            '/admin',
            '/admin/home',
            '/login',
            '/password/force-change',
            '/agent/bookings',
            '/booking/review',
            '/flights/results',
            '/dev/cp/login',
            '/register',
            '/api',
            '/dashboard',
        ];

        $cases = [];
        foreach ($paths as $path) {
            $cases[$path] = [$path];
        }

        return $cases;
    }

    protected function matchRouteNameOrNull(string $method, string $uri): ?string
    {
        $request = Request::create($uri, $method);

        try {
            $route = Route::getRoutes()->match($request);
        } catch (NotFoundHttpException) {
            // Reserved prefixes such as /api may intentionally have no bare GET route.
            return null;
        }

        $name = $route->getName();

        return $name !== null && $name !== '' ? (string) $name : null;
    }
}
