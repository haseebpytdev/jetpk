<?php

namespace App\Console\Commands;

use App\Enums\OtaNotificationEvent;
use App\Support\Emails\JetpkEmailEventContentRegistry;
use App\Support\Emails\JetpkEmailEventRenderer;
use App\Support\Emails\JetpkEmailEventTypeMap;
use App\Support\Emails\JetpkEmailSampleDataProvider;
use App\Support\Emails\JetpkEmailViewResolver;
use App\Support\Emails\EmailVariableIdentifierAuditor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\View;

/**
 * Audits the JetPK universal email shell and event-content system.
 *
 *   php artisan ota:jetpk-email-template-audit
 *
 * Read-only. Sends no email, writes no DB, calls no supplier.
 */
class OtaJetpkEmailTemplateAuditCommand extends Command
{
    protected $signature = 'ota:jetpk-email-template-audit {--verbose-rows : Print each passing check}';

    protected $description = 'Audit JetPK universal shell, event-content coverage, and rendered output safety.';

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

    public function handle(JetpkEmailEventRenderer $renderer): int
    {
        $this->failCount = 0;

        $this->line('JetPK email template audit');
        $this->line('==========================');
        $this->newLine();

        $this->auditStructure();
        $this->auditVariableIdentifierHygiene();
        $this->auditEventContentCoverage();
        $this->auditNoDuplicatedFullLayouts();
        $this->auditRenders($renderer);

        $this->newLine();
        $this->line('fail_count='.$this->failCount);

        return $this->failCount === 0 ? self::SUCCESS : self::FAILURE;
    }

    protected function auditStructure(): void
    {
        $this->line('[structure]');

        $this->check('canonical shell exists', View::exists(JetpkEmailViewResolver::SHELL_VIEW));
        $this->check('universal event content view exists', View::exists(JetpkEmailViewResolver::UNIVERSAL_VIEW));
        $this->check('all legacy types resolve to universal view', collect(JetpkEmailEventTypeMap::all())->every(
            fn (string $event, string $type) => JetpkEmailViewResolver::resolve($type, 'jetpk') === JetpkEmailViewResolver::UNIVERSAL_VIEW
        ));

        foreach ($this->requiredPartials as $partial) {
            $this->check("partial {$partial}", View::exists($partial));
        }

        $this->newLine();
    }

    protected function auditVariableIdentifierHygiene(): void
    {
        $this->line('[variable identifier hygiene]');

        $result = (new EmailVariableIdentifierAuditor)->scan();

        if ($result['pass']) {
            $this->check('no malformed email variable identifiers', true);
        } else {
            $summary = collect($result['hits'])
                ->take(5)
                ->map(fn (array $hit): string => sprintf('%s:%d %s', $hit['file'], $hit['line'], $hit['fragment']))
                ->implode('; ');
            if ($result['hit_count'] > 5) {
                $summary .= ' (+'.($result['hit_count'] - 5).' more)';
            }
            $this->check('no malformed email variable identifiers', false, $summary);
        }

        $this->newLine();
    }

    protected function auditEventContentCoverage(): void
    {
        $this->line('[event-content coverage]');

        $eventCount = count(OtaNotificationEvent::cases());
        $contentCount = count(JetpkEmailEventContentRegistry::all());
        $this->check("every OTA event has content definition ({$contentCount}/{$eventCount})", $contentCount >= $eventCount);

        foreach (OtaNotificationEvent::cases() as $event) {
            $definition = JetpkEmailEventContentRegistry::find($event->value);
            $this->check("  content for {$event->value}", $definition !== null);
        }

        $this->newLine();
    }

    protected function auditNoDuplicatedFullLayouts(): void
    {
        $this->line('[no duplicated full layouts]');

        $legacyDirs = [
            'auth', 'booking', 'payment', 'support', 'agent', 'admin', 'group-ticketing', 'generic',
        ];

        $duplicates = [];
        foreach ($legacyDirs as $dir) {
            $path = resource_path('views/emails/themes/jetpakistan/'.$dir);
            if (! is_dir($path)) {
                continue;
            }
            foreach (glob($path.'/*.blade.php') ?: [] as $file) {
                $contents = (string) file_get_contents($file);
                if (str_contains($contents, "@extends('emails.themes.jetpakistan.layouts.base')")) {
                    $duplicates[] = str_replace(resource_path('views/'), '', $file);
                }
            }
        }

        $this->check('no per-event full layout blades extend base', $duplicates === [], $duplicates ? implode(', ', array_slice($duplicates, 0, 5)) : '');
        $this->newLine();
    }

    protected function auditRenders(JetpkEmailEventRenderer $renderer): void
    {
        $this->line('[render + content]');

        foreach (JetpkEmailEventTypeMap::all() as $type => $eventKey) {
            try {
                $sample = JetpkEmailSampleDataProvider::forType($type);
                $result = $renderer->render($eventKey, null, null, $sample, $sample);
                $html = $result->html;
            } catch (\Throwable $e) {
                $this->fail("render {$type}/{$eventKey}: ".$e->getMessage());
                continue;
            }

            $this->check("render {$type}", true);

            $hasPlaceholder = str_contains($html, '{{') || str_contains($html, '}}')
                || str_contains($html, '@include') || str_contains($html, '@yield');
            $this->check("  no unresolved placeholders ({$type})", ! $hasPlaceholder);

            $leak = null;
            foreach ($this->forbidden as $needle) {
                if (str_contains($html, $needle)) {
                    $leak = $needle;
                    break;
                }
            }
            $this->check("  no forbidden text ({$type})", $leak === null, $leak ? "found: {$leak}" : '');

            $this->check("  JetPakistan branding present ({$type})", str_contains($html, 'JetPakistan') || str_contains($html, 'Jet<'));

            $badImg = preg_match('#<img[^>]+src=("|\')\s*("|\')#i', $html)
                || preg_match('#<img[^>]+src=("|\')(?!https?://|//)#i', $html);
            $this->check("  no broken/relative logo image ({$type})", ! $badImg);
        }
    }

    protected function check(string $label, bool $passed, string $detail = ''): void
    {
        if ($passed) {
            if ($this->option('verbose-rows')) {
                $this->info('  PASS  '.$label.($detail ? "  ({$detail})" : ''));
            }

            return;
        }

        $this->recordFailure($label.($detail ? "  ({$detail})" : ''));
    }

    protected function recordFailure(string $label): void
    {
        $this->failCount++;
        $this->error('  FAIL  '.$label);
    }
}
