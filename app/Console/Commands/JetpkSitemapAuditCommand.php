<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route as RouteFacade;

class JetpkSitemapAuditCommand extends Command
{
    protected $signature = 'jetpk:sitemap-audit {--no-dispatch : Skip guest GET dispatch checks}';

    protected $description = 'Generate JetPK dedicated root-mode route/page inventory audit';

    public function handle(): int
    {
        $this->line('Classification: READ-ONLY JetPK sitemap audit.');
        $this->line('db_write_attempted=false');
        $this->newLine();

        $rows = [];
        foreach (RouteFacade::getRoutes() as $route) {
            $rows[] = $this->describeRoute($route);
        }

        usort($rows, fn (array $a, array $b): int => strcmp($a['uri'], $b['uri']));

        $dir = storage_path('app/audits');
        File::ensureDirectoryExists($dir);
        $jsonPath = $dir.'/jetpk-sitemap-audit.json';
        $mdPath = $dir.'/jetpk-sitemap-audit.md';

        File::put($jsonPath, json_encode([
            'generated_at' => now()->toIso8601String(),
            'route_count' => count($rows),
            'routes' => $rows,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $md = "# JetPK sitemap audit\n\nGenerated: ".now()->toIso8601String()."\n\n";
        $md .= "| URI | Methods | Name | Portal | GET safe | Browser test | Guest status |\n";
        $md .= "|-----|---------|------|--------|----------|--------------|-------------|\n";
        foreach ($rows as $row) {
            if (! $row['browser_test']) {
                continue;
            }
            $md .= sprintf(
                "| %s | %s | %s | %s | %s | %s | %s |\n",
                $row['uri'],
                implode(',', $row['methods']),
                $row['name'] ?: '—',
                $row['portal'],
                $row['get_safe'] ? 'yes' : 'no',
                $row['browser_test'] ? 'yes' : 'no',
                $row['guest_status'] ?? '—',
            );
        }
        File::put($mdPath, $md);

        $this->info("Routes inventoried: ".count($rows));
        $this->line("JSON: {$jsonPath}");
        $this->line("MD: {$mdPath}");

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function describeRoute(Route $route): array
    {
        $uri = '/'.ltrim($route->uri(), '/');
        $methods = array_values(array_diff($route->methods(), ['HEAD']));
        $name = (string) ($route->getName() ?? '');
        $portal = $this->classifyPortal($uri, $name, $route->middleware());
        $getSafe = in_array('GET', $methods, true);
        $browserTest = $getSafe && ! str_starts_with($uri, '/api/') && ! str_contains($uri, '{');
        $rootRisk = str_contains($uri, '/jetpk/') || str_contains($uri, '/haseeb-master/');

        return [
            'uri' => $uri === '/' ? '/' : rtrim($uri, '/'),
            'methods' => $methods,
            'name' => $name,
            'action' => $route->getActionName(),
            'middleware' => $route->middleware(),
            'portal' => $portal,
            'get_safe' => $getSafe,
            'browser_test' => $browserTest,
            'auth_required' => $this->requiresAuth($route->middleware()),
            'guest_accessible' => ! $this->requiresAuth($route->middleware()),
            'root_mode_link_risk' => $rootRisk,
            'guest_status' => null,
        ];
    }

    /**
     * @param  list<string>  $middleware
     */
    private function classifyPortal(string $uri, string $name, array $middleware): string
    {
        if (str_starts_with($uri, '/dev/cp') || str_starts_with($uri, '/devcp') || str_starts_with($name, 'dev.cp.')) {
            return 'devcp';
        }
        if (str_starts_with($uri, '/admin') || str_starts_with($name, 'admin.')) {
            return 'admin';
        }
        if (str_starts_with($uri, '/staff') || str_starts_with($name, 'staff.')) {
            return 'staff';
        }
        if (str_starts_with($uri, '/agent') || str_starts_with($name, 'agent.')) {
            return 'agent';
        }
        if (str_starts_with($uri, '/customer') || str_starts_with($name, 'customer.')) {
            return 'customer';
        }
        if (str_contains($uri, '/booking') || str_contains($name, 'booking.')) {
            return 'checkout';
        }
        if (str_contains($uri, '/groups') || str_contains($name, 'group-ticketing')) {
            return 'group_ticketing';
        }
        if (str_contains($uri, '/airports') || str_contains($uri, '/flights/results/data')) {
            return 'api/json';
        }
        if (in_array('auth', $middleware, true)) {
            return 'auth';
        }

        return 'public';
    }

    /**
     * @param  list<string>  $middleware
     */
    private function requiresAuth(array $middleware): bool
    {
        foreach ($middleware as $entry) {
            if ($entry === 'auth' || str_starts_with($entry, 'auth:') || $entry === 'developer.cp') {
                return true;
            }
        }

        return false;
    }
}
