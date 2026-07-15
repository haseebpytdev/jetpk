<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route as RouteFacade;

class OtaAuditRoutesCommand extends Command
{
    protected $signature = 'ota:audit-routes {--export= : Write markdown report to this path}';

    protected $description = 'Audit route exposure, middleware, and risky mutating endpoints';

    public function handle(): int
    {
        $routes = collect(RouteFacade::getRoutes()->getRoutes());

        if ($routes->isEmpty()) {
            $this->error('No routes registered — route bootstrap may be broken.');

            return self::FAILURE;
        }

        $buckets = $this->bucketRoutes($routes);
        $risky = $this->riskyMutatingRoutes($routes);
        $missingAuthWarning = $this->missingAuthWarnings($risky);

        $this->info('=== OTA Route Audit ===');
        $this->line('Total routes: '.$routes->count());
        $this->newLine();

        foreach ($buckets as $bucket => $group) {
            $this->outputGroup(ucfirst(str_replace('_', ' ', $bucket)).' routes', $group);
        }

        $this->outputGroup('Risky mutating routes', $risky);

        $this->warn('Routes that may be missing auth (heuristic warning only)');
        if ($missingAuthWarning->isEmpty()) {
            $this->line('  none');
        } else {
            foreach ($missingAuthWarning as $route) {
                $this->line('  '.$this->routeSummary($route));
            }
        }

        $exportPath = $this->option('export');
        if (is_string($exportPath) && $exportPath !== '') {
            $markdown = $this->buildMarkdownReport($routes, $buckets, $risky, $missingAuthWarning);
            File::ensureDirectoryExists(dirname($exportPath));
            File::put($exportPath, $markdown);
            $this->newLine();
            $this->info('Markdown report written to: '.$exportPath);
        }

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, Route>  $routes
     * @return array<string, Collection<int, Route>>
     */
    protected function bucketRoutes(Collection $routes): array
    {
        $buckets = [
            'public' => collect(),
            'auth' => collect(),
            'admin' => collect(),
            'staff' => collect(),
            'agent' => collect(),
            'customer' => collect(),
            'dev_cp' => collect(),
            'api' => collect(),
            'health' => collect(),
            'unclassified' => collect(),
        ];

        foreach ($routes as $route) {
            $bucket = $this->resolveBucket($route);
            $buckets[$bucket]->push($route);
        }

        return $buckets;
    }

    protected function resolveBucket(Route $route): string
    {
        $name = (string) $route->getName();
        $uri = $route->uri();

        if ($uri === 'up' || str_starts_with($uri, 'health')) {
            return 'health';
        }

        if (str_starts_with($name, 'admin.')) {
            return 'admin';
        }
        if (str_starts_with($name, 'staff.')) {
            return 'staff';
        }
        if (str_starts_with($name, 'agent.')) {
            return 'agent';
        }
        if (str_starts_with($name, 'customer.')) {
            return 'customer';
        }
        if (str_starts_with($name, 'dev.cp.')) {
            return 'dev_cp';
        }
        if (str_starts_with($uri, 'api/') || str_starts_with($name, 'api.')) {
            return 'api';
        }

        $middleware = $route->gatherMiddleware();
        if (in_array('auth', $middleware, true)) {
            return 'auth';
        }

        if ($name !== '' && (
            str_contains($name, 'login')
            || str_contains($name, 'password')
            || str_contains($name, 'register')
            || str_starts_with($name, 'verification.')
        )) {
            return 'auth';
        }

        if (! in_array('auth', $middleware, true)) {
            return 'public';
        }

        return 'unclassified';
    }

    /**
     * @param  Collection<int, Route>  $routes
     * @return Collection<int, Route>
     */
    protected function riskyMutatingRoutes(Collection $routes): Collection
    {
        return $routes->filter(function (Route $route): bool {
            $methods = array_diff($route->methods(), ['HEAD']);

            return collect($methods)->contains(fn (string $method): bool => in_array($method, ['POST', 'PATCH', 'PUT', 'DELETE'], true));
        });
    }

    /**
     * @param  Collection<int, Route>  $risky
     * @return Collection<int, Route>
     */
    protected function missingAuthWarnings(Collection $risky): Collection
    {
        return $risky
            ->filter(fn (Route $route): bool => ! in_array('auth', $route->gatherMiddleware(), true))
            ->filter(function (Route $route): bool {
                $name = (string) $route->getName();

                return ! str_starts_with($name, 'guest.')
                    && ! str_starts_with($name, 'lookup-booking.')
                    && ! str_starts_with($name, 'booking.')
                    && ! str_contains($name, 'login')
                    && ! str_contains($name, 'password')
                    && ! str_starts_with($name, 'dev.cp.');
            });
    }

    /**
     * @param  Collection<int, Route>  $routes
     * @param  array<string, Collection<int, Route>>  $buckets
     * @param  Collection<int, Route>  $risky
     * @param  Collection<int, Route>  $missingAuthWarning
     */
    protected function buildMarkdownReport(
        Collection $routes,
        array $buckets,
        Collection $risky,
        Collection $missingAuthWarning,
    ): string {
        $lines = [
            '# OTA Route Inventory',
            '',
            'Generated: '.now()->toIso8601String(),
            '',
            'Command: `php artisan ota:audit-routes --export=docs/audits/OTA_ROUTE_INVENTORY.md`',
            '',
            'Verify live routes: `php artisan route:list`',
            '',
            '## Summary',
            '',
            '| Metric | Count |',
            '|--------|------:|',
            '| Total routes | '.$routes->count().' |',
            '| Auth middleware | '.$routes->filter(fn (Route $r): bool => in_array('auth', $r->gatherMiddleware(), true))->count().' |',
            '| Mutating (POST/PATCH/PUT/DELETE) | '.$risky->count().' |',
            '| Mutating without auth (heuristic) | '.$missingAuthWarning->count().' |',
            '',
            '## Buckets',
            '',
            '| Bucket | Count |',
            '|--------|------:|',
        ];

        foreach ($buckets as $bucket => $group) {
            $lines[] = '| '.$bucket.' | '.$group->count().' |';
        }

        $lines[] = '';
        $lines[] = '## Bucket details';
        $lines[] = '';

        foreach ($buckets as $bucket => $group) {
            $lines[] = '### '.$bucket.' ('.$group->count().')';
            $lines[] = '';
            if ($group->isEmpty()) {
                $lines[] = '_No routes in this bucket._';
                $lines[] = '';

                continue;
            }

            $lines[] = '| Methods | URI | Name | Middleware | Platform module |';
            $lines[] = '|---------|-----|------|------------|-----------------|';

            foreach ($group->take(100) as $route) {
                $lines[] = '| '.$this->markdownMethods($route).' | `'.$route->uri().'` | `'.($route->getName() ?: '-').'` | '.$this->markdownMiddleware($route).' | '.$this->platformModuleMiddleware($route).' |';
            }

            if ($group->count() > 100) {
                $lines[] = '';
                $lines[] = '_... +'.($group->count() - 100).' more routes in this bucket._';
            }

            $lines[] = '';
        }

        if ($missingAuthWarning->isNotEmpty()) {
            $lines[] = '## Mutating routes possibly missing auth';
            $lines[] = '';
            foreach ($missingAuthWarning->take(50) as $route) {
                $lines[] = '- `'.$this->routeSummary($route).'`';
            }
            $lines[] = '';
        }

        return implode("\n", $lines)."\n";
    }

    protected function markdownMethods(Route $route): string
    {
        return implode('|', array_diff($route->methods(), ['HEAD']));
    }

    protected function markdownMiddleware(Route $route): string
    {
        $mw = $route->gatherMiddleware();

        return $mw === [] ? '—' : implode(', ', array_slice($mw, 0, 6)).(count($mw) > 6 ? '…' : '');
    }

    protected function platformModuleMiddleware(Route $route): string
    {
        foreach ($route->gatherMiddleware() as $mw) {
            if (str_starts_with((string) $mw, 'platform.module:')) {
                return str_replace('platform.module:', '', (string) $mw);
            }
        }

        return '—';
    }

    /**
     * @param  Collection<int, Route>  $routes
     */
    protected function outputGroup(string $title, Collection $routes): void
    {
        $this->info("{$title}: ".$routes->count());
        foreach ($routes->take(25) as $route) {
            $this->line('  '.$this->routeSummary($route));
        }
        if ($routes->count() > 25) {
            $this->line('  ... +'.($routes->count() - 25).' more');
        }
        $this->newLine();
    }

    protected function routeSummary(Route $route): string
    {
        $methods = implode('|', array_diff($route->methods(), ['HEAD']));
        $middleware = implode(',', $route->gatherMiddleware());

        return sprintf(
            '[%s] %s name=%s mw=%s',
            $methods,
            $route->uri(),
            $route->getName() ?: '-',
            $middleware
        );
    }
}
