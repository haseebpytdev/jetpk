<?php

namespace Tests\Feature\Jetpk;

use App\Support\Emails\JetpkEmailBrandingLeakageAuditor;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PublicBladeBrandingLeakageAuditTest extends TestCase
{
    /** @var list<string> */
    private const SCAN_ROOTS = [
        'resources/views/frontend',
        'resources/views/booking',
        'resources/views/payments',
        'resources/views/customer',
        'resources/views/agent',
        'resources/views/emails/themes/jetpakistan',
    ];

    public function test_user_visible_blade_templates_do_not_contain_forbidden_branding(): void
    {
        $auditor = new JetpkEmailBrandingLeakageAuditor;
        $fragments = $auditor->forbiddenFragments();

        $this->assertNotEmpty($fragments, 'Expected configured forbidden branding fragments.');

        $hits = [];

        foreach (self::SCAN_ROOTS as $root) {
            $absolute = base_path($root);
            if (! is_dir($absolute)) {
                continue;
            }

            foreach (File::allFiles($absolute) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $relative = str_replace('\\', '/', $file->getRelativePathname());
                $fullRelative = trim($root.'/'.$relative, '/');
                $contents = File::get($file->getPathname());
                $visible = $this->stripBladeComments($contents);

                foreach ($fragments as $fragment) {
                    if (str_contains($visible, $fragment)) {
                        $hits[] = $fullRelative.':'.$fragment;
                    }
                }
            }
        }

        $this->assertSame([], $hits, 'Forbidden branding fragments found in user-visible Blade templates: '.implode(', ', $hits));
    }

    private function stripBladeComments(string $contents): string
    {
        $withoutBlade = preg_replace('/\{\{--.*?--\}\}/s', '', $contents) ?? $contents;
        $lines = preg_split('/\R/', $withoutBlade) ?: [];
        $visibleLines = [];

        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            if (str_starts_with($trimmed, '//')) {
                continue;
            }

            $visibleLines[] = $line;
        }

        return implode("\n", $visibleLines);
    }
}
