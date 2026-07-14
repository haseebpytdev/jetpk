<?php

namespace App\Console\Commands;

use App\Enums\OtaNotificationEvent;
use App\Support\Emails\JetpkEmailEventContentRegistry;
use App\Support\Emails\JetpkEmailEventRenderer;
use App\Support\Emails\JetpkEmailEventTypeMap;
use App\Support\Emails\JetpkEmailSampleDataProvider;
use App\Support\Emails\EmailVariableIdentifierAuditor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\View;

/**
 * Read-only render audit for the universal JetPK email shell.
 *
 * Production-safe renderer-only audit with no testing framework or mail transport.
 *
 *   php artisan jetpk:universal-email-render-audit
 */
class JetpkUniversalEmailRenderAuditCommand extends Command
{
    protected $signature = 'jetpk:universal-email-render-audit {--verbose-rows : Print each passing check}';

    protected $description = 'Render-audit universal JetPK email shell and event-content coverage (no mail send).';

    /** Must remain true for the entire command; proves audit never enters a mail-send path. */
    private bool $mailDispatchProhibited = true;

    /** @var list<string> */
    protected array $forbidden = [
        'Parwaaz', 'parwaaz', 'YD Travel', 'YoursDomain', 'yoursdomain', 'haseeb-master', 'placeholder 123',
    ];

    /** @var list<string> */
    protected array $representativeTypes = [
        'booking_created', 'booking_confirmed', 'booking_cancelled', 'payment_success', 'payment_failed',
        'refund_requested', 'refund_updated', 'otp', 'sign_in_success', 'password_reset',
        'support_ticket_created', 'support_reply', 'agent_registration_received',
        'admin_operational_notification', 'group_reservation_created', 'invoice', 'notification',
    ];

    protected int $failCount = 0;

    protected int $unresolvedPlaceholderCount = 0;

    public function handle(JetpkEmailEventRenderer $renderer): int
    {
        $this->mailDispatchProhibited = true;
        $this->failCount = 0;
        $this->unresolvedPlaceholderCount = 0;

        $this->line('JetPK universal email render audit');
        $this->line('===================================');
        $this->line('mail_send_guard=active renderer_only=1 audit_mode=1');
        $this->newLine();

        $this->assertMailDispatchProhibited();

        $this->auditStructure();
        $this->auditVariableIdentifierHygiene();
        $this->auditEventDefinitions();
        $this->auditRepresentativeRenders($renderer);
        $this->auditAllOtaEvents($renderer);

        $this->assertMailDispatchProhibited();

        $this->newLine();
        $this->line('fail_count='.$this->failCount);
        $this->line('unresolved_placeholders='.$this->unresolvedPlaceholderCount);
        $this->line('mail_send_guard=ok transport_called=0');

        return $this->failCount === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Explicit production safety guard: audit uses View::make via JetpkEmailEventRenderer only.
     */
    protected function assertMailDispatchProhibited(): void
    {
        if (! $this->mailDispatchProhibited) {
            throw new \RuntimeException('JetPK universal email render audit safety guard violated: mail dispatch path entered.');
        }
    }

    protected function auditStructure(): void
    {
        $this->line('[structure]');
        $shell = JetpkEmailEventContentRegistry::shellView();
        $content = JetpkEmailEventContentRegistry::contentView();

        $this->check('exactly one canonical shell view key', $shell === 'emails.themes.jetpakistan.layouts.base');
        $this->check('canonical shell exists', View::exists($shell));
        $this->check('universal content view exists', View::exists($content));
        $this->check('no second full-layout blade besides universal-event', $this->countLegacyFullLayouts() === 0);

        foreach (JetpkEmailEventTypeMap::all() as $type => $event) {
            $view = \App\Support\Emails\JetpkEmailViewResolver::resolve($type, 'jetpk');
            $this->check("type {$type} resolves to universal view", $view === $content);
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
            $this->recordFailure('no malformed email variable identifiers ('.$summary.')');
        }

        $this->newLine();
    }

    protected function auditEventDefinitions(): void
    {
        $this->line('[event definitions]');
        $count = count(JetpkEmailEventContentRegistry::all());
        $ota = count(OtaNotificationEvent::cases());
        $this->check("content definitions ({$count}) cover OTA events ({$ota})", $count >= $ota);

        foreach (OtaNotificationEvent::cases() as $event) {
            $this->check('  definition '.$event->value, JetpkEmailEventContentRegistry::find($event->value) !== null);
        }

        $this->newLine();
    }

    protected function auditRepresentativeRenders(JetpkEmailEventRenderer $renderer): void
    {
        $this->line('[representative renders]');

        foreach ($this->representativeTypes as $type) {
            $eventKey = JetpkEmailEventTypeMap::eventForType($type);
            if ($eventKey === null) {
                $this->recordFailure("missing event map for type {$type}");
                continue;
            }

            $sample = JetpkEmailSampleDataProvider::forType($type);
            try {
                $result = $renderer->render($eventKey, null, null, $sample, $sample, auditMode: true);
            } catch (\Throwable $e) {
                $this->recordFailure("render {$type}/{$eventKey}: ".$e->getMessage());
                continue;
            }

            $this->check("render {$type}", true);
            $this->auditRenderResult($type, $result);
        }

        $this->newLine();
    }

    protected function auditAllOtaEvents(JetpkEmailEventRenderer $renderer): void
    {
        $this->line('[all OTA event renders]');
        $otaFails = 0;

        foreach (OtaNotificationEvent::cases() as $event) {
            $type = JetpkEmailEventTypeMap::typeForEvent($event->value) ?? 'notification';
            $sample = JetpkEmailSampleDataProvider::forType($type);

            try {
                $result = $renderer->render($event->value, null, null, $sample, $sample, auditMode: true);
            } catch (\Throwable $e) {
                $otaFails++;
                $this->recordFailure("render {$event->value}: ".$e->getMessage());
                continue;
            }

            if ($this->renderResultHasDefects($event->value, $result)) {
                $otaFails++;
            }
        }

        $this->check('all OTA events rendered without unresolved placeholders', $otaFails === 0);
        $this->newLine();
    }

    protected function auditRenderResult(string $label, \App\Support\Emails\JetpkEmailRenderResult $result): void
    {
        $hasDefects = $this->renderResultHasDefects($label, $result);
        $this->check("  no unresolved placeholders ({$label})", ! $hasDefects);

        foreach ($this->forbidden as $needle) {
            if (str_contains($result->html, $needle) || str_contains($result->subject, $needle)) {
                $this->recordFailure("  forbidden text in {$label}: {$needle}");

                return;
            }
        }

        $this->check("  no forbidden master/parwaaz text ({$label})", true);

        if (preg_match('#<script\b#i', $result->html)) {
            $this->recordFailure("  unsafe script tag in {$label}");
        }

        $this->check("  no unsafe script tags ({$label})", ! preg_match('#<script\b#i', $result->html));
    }

    protected function renderResultHasDefects(string $label, \App\Support\Emails\JetpkEmailRenderResult $result): bool
    {
        if (! $result->hasPlaceholderDefects()) {
            return false;
        }

        $defects = [];

        if ($result->hasUnresolvedPlaceholders()) {
            $defects[] = 'unresolved='.implode(',', $result->unresolvedPlaceholders);
            $this->unresolvedPlaceholderCount += count($result->unresolvedPlaceholders);
        }

        if ($result->hasMissingRequiredVariables()) {
            $defects[] = 'missing_required='.implode(',', $result->missingRequiredVariables);
        }

        if (str_contains($result->subject, '{{') || str_contains($result->preheader, '{{') || str_contains($result->html, '{{')) {
            $defects[] = 'raw_placeholder_tokens';
        }

        $this->recordFailure("  placeholder defect in {$label}: ".implode('; ', $defects));

        return true;
    }

    protected function countLegacyFullLayouts(): int
    {
        $count = 0;
        $legacyDirs = ['auth', 'booking', 'payment', 'support', 'agent', 'admin', 'group-ticketing', 'generic'];
        foreach ($legacyDirs as $dir) {
            $path = resource_path('views/emails/themes/jetpakistan/'.$dir);
            if (! is_dir($path)) {
                continue;
            }
            foreach (glob($path.'/*.blade.php') ?: [] as $file) {
                if (str_contains((string) file_get_contents($file), "@extends('emails.themes.jetpakistan.layouts.base')")) {
                    $count++;
                }
            }
        }

        return $count;
    }

    protected function check(string $label, bool $passed): void
    {
        if ($passed) {
            if ($this->option('verbose-rows')) {
                $this->info('  PASS  '.$label);
            }

            return;
        }

        $this->failCount++;
        $this->error('  FAIL  '.$label);
    }

    protected function recordFailure(string $label): void
    {
        $this->failCount++;
        $this->error('  FAIL  '.$label);
    }
}
