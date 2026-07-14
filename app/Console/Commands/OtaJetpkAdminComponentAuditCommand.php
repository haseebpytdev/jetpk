<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Read-only source audit — JetPK admin anonymous Blade components must resolve to existing files.
 */
class OtaJetpkAdminComponentAuditCommand extends Command
{
    protected $signature = 'ota:jetpk-admin-component-audit';

    protected $description = 'Read-only audit — every x-themes.admin.jetpakistan.components.* reference must have a Blade file';

    /** @var list<string> */
    private array $scanRoots = [
        'resources/views',
        'app',
    ];

    public function handle(): int
    {
        $this->line('Classification: READ-ONLY JetPK admin component registry audit.');
        $this->newLine();

        $referenced = $this->collectReferencedComponents();
        $rows = [];
        $failCount = 0;

        foreach ($referenced as $component) {
            $anonymousPath = resource_path('views/themes/admin/jetpakistan/components/'.$component.'.blade.php');
            $classPath = resource_path('views/components/themes/admin/jetpakistan/components/'.$component.'.blade.php');
            $anonymousExists = File::exists($anonymousPath);
            $classExists = File::exists($classPath);
            $ok = $anonymousExists && $classExists;

            if (! $ok) {
                $failCount++;
            }

            $rows[] = [
                'component' => $component,
                'anonymous' => $anonymousExists ? 'yes' : 'MISSING',
                'class_path' => $classExists ? 'yes' : 'MISSING',
                'status' => $ok ? 'PASS' : 'FAIL',
            ];
        }

        $this->table(['component', 'themes/.../components', 'components/.../components', 'status'], $rows);
        $this->newLine();
        $this->line(sprintf('Summary: referenced=%d fail=%d pass=%d', count($referenced), $failCount, count($referenced) - $failCount));

        return $failCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function collectReferencedComponents(): array
    {
        $pattern = '/x-themes\.admin\.jetpakistan\.components\.([a-z0-9\-]+)/i';
        $found = [];

        foreach ($this->scanRoots as $root) {
            $base = base_path($root);
            if (! File::isDirectory($base)) {
                continue;
            }

            foreach (File::allFiles($base) as $file) {
                if (! in_array($file->getExtension(), ['php', 'blade.php'], true)) {
                    continue;
                }

                $contents = File::get($file->getPathname());
                if (preg_match_all($pattern, $contents, $matches)) {
                    foreach ($matches[1] as $name) {
                        $found[strtolower($name)] = true;
                    }
                }
            }
        }

        $names = array_keys($found);
        sort($names);

        return $names;
    }
}
