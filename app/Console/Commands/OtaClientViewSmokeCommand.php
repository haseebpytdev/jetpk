<?php

namespace App\Console\Commands;

use App\Services\Client\ClientProfileResolver;
use App\Services\Client\RuntimeViewResolver;
use App\Support\Audits\BookingFlowSmokeSafetyOutput;
use App\Support\Audits\HaseebMasterRouteSafetyAuditService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OtaClientViewSmokeCommand extends Command
{
    protected $signature = 'ota:client-view-smoke
                            {--client=haseeb-master : Client slug to smoke-test view resolution for}';

    protected $description = 'MC-8C read-only smoke — migrated page resolution, theme preference, fallback, and HTTP 200 checks';

    public function handle(
        ClientProfileResolver $profileResolver,
        RuntimeViewResolver $viewResolver,
        HaseebMasterRouteSafetyAuditService $routeSafetyAudit,
    ): int {
        $clientSlug = trim((string) $this->option('client'));

        if ($clientSlug === '') {
            $this->error('Option --client must not be empty.');

            return self::FAILURE;
        }

        foreach (BookingFlowSmokeSafetyOutput::readOnlyBanner() as $line) {
            $this->line($line);
        }
        $this->line('Classification: READ-ONLY client view smoke (MC-8C).');
        $this->line('db_write_attempted=false');
        $this->newLine();

        $profile = $profileResolver->resolveBySlug($clientSlug);
        if ($profile === null) {
            $this->error("Client profile not found for slug: {$clientSlug}");

            return self::FAILURE;
        }

        /** @var array{area: string, logical_name: string, label: string, fallback_sample?: array{area: string, logical_name: string, label: string}} $page */
        $page = config('client_view_paths.mc8c_migrated_page', []);
        $area = (string) ($page['area'] ?? 'frontend');
        $logicalName = (string) ($page['logical_name'] ?? 'frontend.home');
        $label = (string) ($page['label'] ?? $logicalName);

        $checks = [];
        $resolution = $viewResolver->resolveSample($logicalName, $area, $profile);
        $checks[] = $this->row(
            'Migrated page resolves',
            $label,
            $resolution['view_exists'] ? $resolution['resolved_view_name'] : 'missing',
            $resolution['view_exists'],
        );

        $themePreferred = ! $resolution['fallback_used']
            && str_starts_with($resolution['resolved_view_name'], 'themes.');
        $checks[] = $this->row(
            'Theme view preferred when present',
            'themes.* view name',
            $resolution['resolved_view_name'],
            $themePreferred,
        );

        /** @var array{area: string, logical_name: string, label: string} $fallbackSample */
        $fallbackSample = $page['fallback_sample'] ?? ['area' => 'frontend', 'logical_name' => 'auth.login', 'label' => 'auth login'];
        $fallbackResolution = $viewResolver->resolveSample(
            $fallbackSample['logical_name'],
            $fallbackSample['area'],
            $profile,
        );
        $checks[] = $this->row(
            'Legacy fallback when theme view missing',
            $fallbackSample['label'],
            $fallbackResolution['fallback_used'] ? 'fallback_used=yes' : 'fallback_used=no',
            $fallbackResolution['fallback_used'] && $fallbackResolution['view_exists'],
        );

        $rootResponse = $this->httpGet('/');
        $checks[] = $this->row(
            'Root homepage GET /',
            '200 OK',
            (string) $rootResponse->getStatusCode(),
            $rootResponse->isOk(),
        );

        $prefixedHomePath = '/'.$clientSlug.'/home';
        $prefixedResponse = $this->httpGet($prefixedHomePath);

        if ($profileResolver->isDefaultDeploymentSlug($clientSlug)) {
            $checks[] = $this->row(
                'Default slug alias '.$prefixedHomePath,
                '302 redirect to /',
                (string) $prefixedResponse->getStatusCode(),
                $prefixedResponse->isRedirect() && $prefixedResponse->getStatusCode() === 302,
            );
        } else {
            $checks[] = $this->row(
                'Prefixed homepage '.$prefixedHomePath,
                '200 OK',
                (string) $prefixedResponse->getStatusCode(),
                $prefixedResponse->isOk(),
            );
        }

        $routeRows = $routeSafetyAudit->run($clientSlug);
        $routeCounts = ['OK' => 0, 'missing' => 0, 'collision-risk' => 0];
        foreach ($routeRows as $routeRow) {
            $status = $routeRow['status'];
            if (isset($routeCounts[$status])) {
                $routeCounts[$status]++;
            }
        }
        $checks[] = $this->row(
            'Route safety audit',
            '0 missing, 0 collision-risk',
            sprintf('%d OK, %d missing, %d collision-risk', $routeCounts['OK'], $routeCounts['missing'], $routeCounts['collision-risk']),
            $routeCounts['missing'] === 0 && $routeCounts['collision-risk'] === 0,
        );

        $this->info('Client slug: '.$clientSlug);
        $this->newLine();
        $this->table(
            ['check', 'expected', 'actual', 'status'],
            array_map(static fn (array $row): array => [
                $row['name'],
                $row['expected'],
                $row['actual'],
                $row['ok'] ? 'OK' : 'FAIL',
            ], $checks),
        );

        $failed = array_filter($checks, static fn (array $row): bool => ! $row['ok']);

        if ($failed !== []) {
            $this->error(sprintf('Client view smoke failed: %d check(s) did not pass.', count($failed)));

            return self::FAILURE;
        }

        $this->newLine();
        $this->line((string) config('client_view_paths.mc8c_note', 'MC-8C client view smoke passed.'));
        $this->info('Client view smoke passed for '.$clientSlug.'.');

        return self::SUCCESS;
    }

    /**
     * @return array{name: string, expected: string, actual: string, ok: bool}
     */
    private function row(string $name, string $expected, string $actual, bool $ok): array
    {
        return [
            'name' => $name,
            'expected' => $expected,
            'actual' => $actual,
            'ok' => $ok,
        ];
    }

    private function httpGet(string $path): Response
    {
        $kernel = app(Kernel::class);
        $request = Request::create($path, 'GET');
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return $response;
    }
}
