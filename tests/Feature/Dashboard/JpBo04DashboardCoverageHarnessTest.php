<?php

namespace Tests\Feature\Dashboard;

use App\Support\BackOffice\BackOfficeCapabilitiesPresenter;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Safe JP-BO-04 dashboard coverage inventory (no destructive clicks).
 */
class JpBo04DashboardCoverageHarnessTest extends TestCase
{
    public function test_writes_dashboard_coverage_report(): void
    {
        $nav = app(BackOfficeCapabilitiesPresenter::class);
        // Presenter needs a user for full nav; inventory filesystem routes instead for harness safety.
        $pages = collect(File::allFiles(base_path('dashboard/app')))
            ->filter(static fn ($f) => str_ends_with($f->getFilename(), 'page.tsx'))
            ->map(static fn ($f) => str_replace('\\', '/', $f->getRelativePathname()))
            ->sort()
            ->values()
            ->all();

        $portalPaths = base_path('dashboard/lib/api/portal-paths.ts');
        $opsApi = base_path('dashboard/services/operational-api.ts');
        $this->assertFileExists($portalPaths);
        $this->assertFileExists($opsApi);

        $portalSrc = File::get($portalPaths);
        $opsSrc = File::get($opsApi);

        $pathExports = preg_match_all('/export function (\w+Path)\(/', $portalSrc, $m1) ? $m1[1] : [];
        $mutationExports = preg_match_all('/export async function (\w+)\(/', $opsSrc, $m2) ? $m2[1] : [];

        $report = [
            'phase' => 'JP-BO-04',
            'generated_at' => now()->toIso8601String(),
            'dashboard_page_count' => count($pages),
            'portal_path_helpers' => count($pathExports),
            'operational_api_mutations' => count($mutationExports),
            'pages' => $pages,
            'notes' => [
                'Harness enumerates Next pages and mutation helpers only.',
                'Does not click destructive production actions.',
                'Destructive execution reserved for local fixture tests / Stage B owner-gated live proof.',
            ],
        ];

        $dir = base_path('tmp/jp-bo-04');
        File::ensureDirectoryExists($dir);
        File::put($dir.'/JP_BO04_DASHBOARD_COVERAGE_REPORT.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        File::put($dir.'/JP_BO04_DASHBOARD_COVERAGE_REPORT.txt', implode("\n", [
            'JP_BO04_DASHBOARD_COVERAGE_REPORT',
            'dashboard_page_count='.count($pages),
            'portal_path_helpers='.count($pathExports),
            'operational_api_mutations='.count($mutationExports),
            'destructive_auto_click=NO',
            'status=PASS_ENUMERATION',
        ])."\n");

        $this->assertGreaterThan(20, count($pages));
        $this->assertGreaterThan(20, count($pathExports));
        $this->assertFileExists($dir.'/JP_BO04_DASHBOARD_COVERAGE_REPORT.json');
        unset($nav);
    }
}
