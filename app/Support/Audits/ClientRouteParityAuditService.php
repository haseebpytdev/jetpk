<?php

namespace App\Support\Audits;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * MC-7A read-only full route registry scan for client-prefix parity planning.
 */
class ClientRouteParityAuditService
{
    public function __construct(
        private readonly ClientRouteParityClassifier $classifier = new ClientRouteParityClassifier,
        private readonly ClientRouteParityRouteFilter $routeFilter = new ClientRouteParityRouteFilter,
    ) {}

    /**
     * @return array{
     *     rows: list<array<string, string>>,
     *     summary: array<string, mixed>,
     *     json_path: string,
     *     md_path: string,
     *     high_risk_prefixable_conflicts: list<array<string, string>>
     * }
     */
    public function run(string $clientSlug, string $targetSlug, string $exportDir): array
    {
        $rows = $this->scanRoutes($targetSlug);
        $summary = $this->buildSummary($rows, $clientSlug, $targetSlug);
        $conflicts = $this->highRiskPrefixableConflicts($rows);

        $timestamp = now()->format('Ymd-His');
        $baseName = 'client-route-parity-'.$timestamp;
        $exportPath = rtrim($exportDir, DIRECTORY_SEPARATOR);
        File::ensureDirectoryExists($exportPath);

        $jsonPath = $exportPath.DIRECTORY_SEPARATOR.$baseName.'.json';
        $mdPath = $exportPath.DIRECTORY_SEPARATOR.$baseName.'.md';

        $this->writeJson($jsonPath, $clientSlug, $targetSlug, $rows, $summary, $conflicts);
        $this->writeMarkdown($mdPath, $clientSlug, $targetSlug, $rows, $summary, $conflicts);

        return [
            'rows' => $rows,
            'summary' => $summary,
            'json_path' => $jsonPath,
            'md_path' => $mdPath,
            'high_risk_prefixable_conflicts' => $conflicts,
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    public function scanRoutes(string $targetSlug): array
    {
        $rows = [];

        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            if (! $route instanceof Route) {
                continue;
            }

            if (! $this->routeFilter->isWebRoute($route)) {
                continue;
            }

            $methods = array_values(array_diff($route->methods(), ['HEAD']));
            $routeName = (string) $route->getName();
            $uri = $route->uri();
            $action = $this->routeFilter->resolveAction($route);
            $middleware = $route->gatherMiddleware();

            foreach ($methods as $method) {
                $classification = $this->classifier->classify(
                    $routeName,
                    $method,
                    $uri,
                    $action,
                    $middleware,
                );

                $rows[] = [
                    'route_name' => $routeName !== '' ? $routeName : '-',
                    'method' => strtoupper($method),
                    'uri' => $uri,
                    'action' => $action,
                    'middleware' => implode(', ', $middleware),
                    'classification' => $classification['classification'],
                    'should_have_client_prefix' => $classification['should_have_client_prefix'],
                    'suggested_prefixed_uri' => $this->classifier->suggestedPrefixedUri($targetSlug, $uri),
                    'risk_level' => $classification['risk_level'],
                    'notes' => $classification['notes'],
                ];
            }
        }

        usort($rows, static function (array $a, array $b): int {
            $uriCompare = strcmp($a['uri'], $b['uri']);
            if ($uriCompare !== 0) {
                return $uriCompare;
            }

            return strcmp($a['method'], $b['method']);
        });

        return $rows;
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return list<array<string, string>>
     */
    public function highRiskPrefixableConflicts(array $rows): array
    {
        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => $row['should_have_client_prefix'] === 'yes'
                && $row['risk_level'] === 'high',
        ));
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return array<string, mixed>
     */
    private function buildSummary(array $rows, string $clientSlug, string $targetSlug): array
    {
        $byClassification = [];
        $byPrefix = ['yes' => 0, 'no' => 0];
        $byRisk = ['low' => 0, 'medium' => 0, 'high' => 0];

        foreach ($rows as $row) {
            $class = $row['classification'];
            $byClassification[$class] = ($byClassification[$class] ?? 0) + 1;
            $byPrefix[$row['should_have_client_prefix']] = ($byPrefix[$row['should_have_client_prefix']] ?? 0) + 1;
            $byRisk[$row['risk_level']] = ($byRisk[$row['risk_level']] ?? 0) + 1;
        }

        arsort($byClassification);

        return [
            'client' => $clientSlug,
            'target' => $targetSlug,
            'total_rows' => count($rows),
            'prefixable_yes' => $byPrefix['yes'] ?? 0,
            'prefixable_no' => $byPrefix['no'] ?? 0,
            'high_risk' => $byRisk['high'] ?? 0,
            'medium_risk' => $byRisk['medium'] ?? 0,
            'low_risk' => $byRisk['low'] ?? 0,
            'by_classification' => $byClassification,
            'high_risk_prefixable_conflicts' => count($this->highRiskPrefixableConflicts($rows)),
        ];
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @param  list<array<string, string>>  $conflicts
     */
    private function writeJson(
        string $path,
        string $clientSlug,
        string $targetSlug,
        array $rows,
        array $summary,
        array $conflicts,
    ): void {
        $payload = [
            'generated_at' => now()->toIso8601String(),
            'command' => 'ota:client-route-parity-audit',
            'client' => $clientSlug,
            'target' => $targetSlug,
            'classification' => 'READ-ONLY',
            'live_supplier_call_attempted' => false,
            'summary' => $summary,
            'high_risk_prefixable_conflicts' => $conflicts,
            'routes' => $rows,
        ];

        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @param  list<array<string, string>>  $conflicts
     */
    private function writeMarkdown(
        string $path,
        string $clientSlug,
        string $targetSlug,
        array $rows,
        array $summary,
        array $conflicts,
    ): void {
        $lines = [
            '# Client Route Parity Audit (MC-7A)',
            '',
            'Generated: '.now()->toIso8601String(),
            '',
            'Command: `php artisan ota:client-route-parity-audit --client='.$clientSlug.' --target='.$targetSlug.'`',
            '',
            'Classification: **READ-ONLY** — no route changes, no supplier calls, no DB writes.',
            '',
            '## Summary',
            '',
            '| Metric | Count |',
            '|--------|------:|',
            '| Total route rows | '.$summary['total_rows'].' |',
            '| Prefixable (yes) | '.$summary['prefixable_yes'].' |',
            '| Prefixable (no) | '.$summary['prefixable_no'].' |',
            '| High risk | '.$summary['high_risk'].' |',
            '| Medium risk | '.$summary['medium_risk'].' |',
            '| Low risk | '.$summary['low_risk'].' |',
            '| High-risk + prefixable conflicts | '.$summary['high_risk_prefixable_conflicts'].' |',
            '',
            'Client slug: `'.$clientSlug.'` | Target example slug: `'.$targetSlug.'`',
            '',
            'Default deployment prefixed example: `/'.$clientSlug.'/login` (MC-5B currently redirects to `/login`).',
            '',
            '## By classification',
            '',
            '| Classification | Count |',
            '|----------------|------:|',
        ];

        foreach ($summary['by_classification'] as $classification => $count) {
            $lines[] = '| '.$classification.' | '.$count.' |';
        }

        $lines[] = '';
        $lines[] = '## High-risk prefixable conflicts';
        $lines[] = '';

        if ($conflicts === []) {
            $lines[] = '_None — `--fail-on-high-risk` would pass._';
        } else {
            $lines[] = '| Route name | Method | URI | Classification | Notes |';
            $lines[] = '|------------|--------|-----|----------------|-------|';
            foreach ($conflicts as $row) {
                $lines[] = sprintf(
                    '| `%s` | %s | `%s` | %s | %s |',
                    $row['route_name'],
                    $row['method'],
                    $row['uri'],
                    $row['classification'],
                    $row['notes'],
                );
            }
        }

        $lines[] = '';
        $lines[] = '## Route matrix';
        $lines[] = '';
        $lines[] = '| Route name | Method | URI | Action | Middleware | Classification | Prefix? | Suggested URI | Risk | Notes |';
        $lines[] = '|------------|--------|-----|--------|------------|----------------|---------|---------------|------|-------|';

        $displayRows = array_slice($rows, 0, 200);
        foreach ($displayRows as $row) {
            $lines[] = sprintf(
                '| `%s` | %s | `%s` | `%s` | %s | %s | %s | `%s` | %s | %s |',
                $row['route_name'],
                $row['method'],
                $row['uri'],
                $this->truncate($row['action'], 60),
                $this->truncate($row['middleware'], 40),
                $row['classification'],
                $row['should_have_client_prefix'],
                $row['suggested_prefixed_uri'],
                $row['risk_level'],
                $this->truncate($row['notes'], 50),
            );
        }

        if (count($rows) > 200) {
            $lines[] = '';
            $lines[] = '_... +'.(count($rows) - 200).' more rows — see JSON export for full list._';
        }

        $lines[] = '';
        $lines[] = '## Next steps';
        $lines[] = '';
        $lines[] = 'See [client-route-page-parity-audit.md](../client-route-page-parity-audit.md) for MC-7B implementation phases.';

        OtaAuditReportWriter::write($path, $lines);
    }

    private function truncate(string $value, int $max): string
    {
        if (strlen($value) <= $max) {
            return $value;
        }

        return substr($value, 0, $max - 1).'…';
    }
}
