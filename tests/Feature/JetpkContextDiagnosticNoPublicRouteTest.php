<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/**
 * JETPK-HOMEPAGE-CMS Task 14: "no public diagnostic route" — this diagnostic
 * is log-only, triggered as a side effect of the existing homepage route,
 * not exposed as its own endpoint anywhere.
 */
class JetpkContextDiagnosticNoPublicRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_route_name_or_uri_mentions_the_diagnostic(): void
    {
        $matches = collect(RouteFacade::getRoutes())->filter(function ($route) {
            $name = strtolower((string) ($route->getName() ?? ''));
            $uri = strtolower($route->uri());

            if (str_contains($name, 'supplier') && str_contains($name, 'diagnostic')) {
                return false;
            }

            return (str_contains($name, 'jetpk') && str_contains($name, 'diagnostic'))
                || (str_contains($uri, 'jetpk') && str_contains($uri, 'diagnostic'))
                || str_contains($uri, 'context-diagnostic')
                || str_contains($name, 'context_diagnostic');
        });

        $this->assertCount(0, $matches, 'No route anywhere in the application should expose the context diagnostic as an HTTP endpoint');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function plausibleGuessedUrls(): array
    {
        return [
            '/diagnostic' => ['/diagnostic'],
            '/admin/diagnostic' => ['/admin/diagnostic'],
            '/admin/context-diagnostic' => ['/admin/context-diagnostic'],
            '/jetpk-diagnostic' => ['/jetpk-diagnostic'],
            '/_diagnostic' => ['/_diagnostic'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('plausibleGuessedUrls')]
    public function test_plausible_diagnostic_urls_are_not_routable(string $path): void
    {
        $this->get($path)->assertNotFound();
    }
}
