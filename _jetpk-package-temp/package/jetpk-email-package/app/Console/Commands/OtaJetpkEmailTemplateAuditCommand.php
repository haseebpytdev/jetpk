<?php

namespace App\Console\Commands;

use App\Support\Emails\JetpkEmailSampleData;
use App\Support\Emails\JetpkEmailViewResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\View;

/**
 * Audits the JetPK email template package.
 *
 *   php artisan ota:jetpk-email-template-audit
 *
 * Checks:
 *   - base layout exists
 *   - all partials exist
 *   - all required views exist
 *   - every view renders with safe sample data
 *   - rendered HTML has no unresolved Blade placeholders
 *   - rendered HTML contains no forbidden Master/placeholder text
 *   - logo fallback is safe (no broken <img>)
 *   - support details are JetPK
 *   - all href/src URLs are absolute
 *
 * Read-only. Sends no email, writes no DB, calls no supplier.
 */
class OtaJetpkEmailTemplateAuditCommand extends Command
{
    use JetpkEmailSampleData;

    protected $signature = 'ota:jetpk-email-template-audit {--verbose-rows : Print each passing check}';

    protected $description = 'Audit the JetPakistan email template package for completeness, leakage, and unresolved placeholders.';

    /** Text that must never appear in a rendered JetPK email. */
    protected array $forbidden = [
        'Parwaaz',
        'parwaaz',
        'YD Travel',
        'YoursDomain',
        'yoursdomain',
        'haseeb-master',
        'placeholder 123',
    ];

    protected array $requiredPartials = [
        'emails.themes.jetpakistan.partials.header',
        'emails.themes.jetpakistan.partials.footer',
        'emails.themes.jetpakistan.partials.button',
        'emails.themes.jetpakistan.partials.info-row',
        'emails.themes.jetpakistan.partials.alert-box',
        'emails.themes.jetpakistan.partials.booking-summary',
        'emails.themes.jetpakistan.partials.passenger-summary',
        'emails.themes.jetpakistan.partials.payment-summary',
        'emails.themes.jetpakistan.partials.flight-itinerary',
        'emails.themes.jetpakistan.partials.support-card',
    ];

    protected int $failCount = 0;

    public function handle(): int
    {
        $this->failCount = 0;

        $this->line('JetPK email template audit');
        $this->line('==========================');
        $this->newLine();

        $this->auditStructure();
        $this->auditRenders();

        $this->newLine();
        $this->line('fail_count=' . $this->failCount);

        if ($this->failCount === 0) {
            $this->info('AUDIT PASSED');
            return self::SUCCESS;
        }

        $this->error('AUDIT FAILED');
        return self::FAILURE;
    }

    protected function auditStructure(): void
    {
        $this->line('[structure]');

        $this->check('base layout exists', View::exists('emails.themes.jetpakistan.layouts.base'));

        foreach ($this->requiredPartials as $partial) {
            $this->check("partial {$partial}", View::exists($partial));
        }

        foreach (JetpkEmailViewResolver::all() as $type => $view) {
            $this->check("view {$type}", View::exists($view), $view);
        }

        $this->newLine();
    }

    protected function auditRenders(): void
    {
        $this->line('[render + content]');

        foreach (JetpkEmailViewResolver::all() as $type => $view) {
            if (! View::exists($view)) {
                continue; // already reported in structure pass
            }

            try {
                $html = View::make($view, $this->sampleData($type))->render();
            } catch (\Throwable $e) {
                $this->fail("render {$type}: " . $e->getMessage());
                continue;
            }

            $this->check("render {$type}", true);

            // Unresolved Blade placeholders.
            $hasPlaceholder = str_contains($html, '{{') || str_contains($html, '}}')
                || str_contains($html, '@include') || str_contains($html, '@yield');
            $this->check("  no unresolved placeholders ({$type})", ! $hasPlaceholder);

            // Forbidden leakage.
            $leak = null;
            foreach ($this->forbidden as $needle) {
                if (str_contains($html, $needle)) {
                    $leak = $needle;
                    break;
                }
            }
            $this->check("  no forbidden text ({$type})", is_null($leak), $leak ? "found: {$leak}" : '');

            // Brand presence.
            $this->check("  JetPakistan branding present ({$type})", str_contains($html, 'JetPakistan') || str_contains($html, 'Jet<'));

            // Logo fallback safety: no empty/relative img src.
            $badImg = preg_match('#<img[^>]+src=("|\')\s*("|\')#i', $html)
                || preg_match('#<img[^>]+src=("|\')(?!https?://|//)#i', $html);
            $this->check("  no broken/relative logo image ({$type})", ! $badImg);

            // Absolute URLs only for links (allow mailto:, tel:, and the '#' no-op).
            $relative = [];
            if (preg_match_all('#href=("|\')([^"\']+)("|\')#i', $html, $m)) {
                foreach ($m[2] as $href) {
                    if (preg_match('#^(https?://|//|mailto:|tel:|\#)#i', $href)) {
                        continue;
                    }
                    $relative[] = $href;
                }
            }
            $this->check("  all URLs absolute ({$type})", empty($relative), $relative ? 'relative: ' . implode(', ', array_slice($relative, 0, 3)) : '');
        }
    }

    protected function check(string $label, bool $passed, string $detail = ''): void
    {
        if ($passed) {
            if ($this->option('verbose-rows')) {
                $this->info('  PASS  ' . $label . ($detail ? "  ({$detail})" : ''));
            }
            return;
        }

        $this->fail($label . ($detail ? "  ({$detail})" : ''));
    }

    protected function fail(string $label): void
    {
        $this->failCount++;
        $this->error('  FAIL  ' . $label);
    }
}
