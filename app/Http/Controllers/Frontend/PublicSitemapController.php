<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\ClientPage;
use App\Models\CmsPage;
use App\Services\PublicContent\PublicContentApiPresenter;
use App\Support\Client\ClientManagedPageReservedSlugs;
use App\Support\Client\ReservedPublicPath;
use Illuminate\Http\Response;

/**
 * Authoritative XML sitemap for public marketing and CMS routes.
 */
class PublicSitemapController extends Controller
{
    public function __construct(
        private readonly PublicContentApiPresenter $presenter,
    ) {}

    public function index(): Response
    {
        $routes = $this->presenter->sitemapRoutes();
        $base = rtrim((string) config('app.url'), '/');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($routes as $route) {
            $path = (string) ($route['path'] ?? '');
            if ($path === '') {
                continue;
            }

            $loc = $base.'/'.ltrim($path, '/');
            $xml .= '  <url>'."\n";
            $xml .= '    <loc>'.htmlspecialchars($loc, ENT_XML1).'</loc>'."\n";
            if (! empty($route['lastmod'])) {
                $xml .= '    <lastmod>'.htmlspecialchars((string) $route['lastmod'], ENT_XML1).'</lastmod>'."\n";
            }
            $xml .= '  </url>'."\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
