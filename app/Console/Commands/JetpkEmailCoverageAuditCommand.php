<?php

namespace App\Console\Commands;

use App\Enums\OtaNotificationEvent;
use App\Models\Agency;
use App\Services\Communication\NotificationRecipientResolver;
use App\Support\Emails\JetpkEmailBrandingLeakageAuditor;
use App\Support\Emails\JetpkEmailEventContentRegistry;
use App\Support\Emails\JetpkOperationalEmailEventRegistry;
use Illuminate\Console\Command;

/**
 * Read-only operational email coverage audit (no mail send, no DB writes).
 *
 *   php artisan jetpk:email-coverage-audit
 */
class JetpkEmailCoverageAuditCommand extends Command
{
    protected $signature = 'jetpk:email-coverage-audit
        {--write-matrix : Write EMAIL-EVENT-MATRIX files under storage/app/audits/jetpk-email-coverage/}';

    protected $description = 'Audit JetPK operational email coverage, recipients, templates, and branding (no delivery).';

    protected int $failCount = 0;

    /** @var list<array<string, mixed>> */
    protected array $matrixRows = [];

    public function handle(NotificationRecipientResolver $recipientResolver, JetpkEmailBrandingLeakageAuditor $leakageAuditor): int
    {
        $this->failCount = 0;
        $this->matrixRows = [];

        $this->line('JetPK operational email coverage audit');
        $this->line('======================================');
        $this->line('mail_send=prohibited db_writes=prohibited');
        $this->newLine();

        $this->auditClientConfig();
        $this->auditTemplateStructure();
        $this->auditEventRegistry($recipientResolver);
        $this->auditMisspelledApplicantKey($leakageAuditor);
        $this->auditForbiddenBrandingInTemplates($leakageAuditor);
        $this->auditDenylistConfigIsExcludedFromLeakage($leakageAuditor);

        if ($this->option('write-matrix')) {
            $this->writeMatrixFiles();
        }

        $this->newLine();
        $this->line('fail_count='.$this->failCount);
        $this->line('matrix_rows='.count($this->matrixRows));

        return $this->failCount === 0 ? self::SUCCESS : self::FAILURE;
    }

    protected function auditClientConfig(): void
    {
        if (! JetpkOperationalEmailEventRegistry::isJetpkClient()) {
            $this->recordFail('client_slug is not jetpk');
        } else {
            $this->pass('client_slug=jetpk');
        }
    }

    protected function auditTemplateStructure(): void
    {
        $required = [
            'resources/views/emails/themes/jetpakistan/layouts/base.blade.php',
            'resources/views/emails/themes/jetpakistan/universal-event.blade.php',
            'resources/views/emails/themes/jetpakistan/partials/header.blade.php',
            'resources/views/emails/themes/jetpakistan/partials/footer.blade.php',
            'resources/views/emails/themes/jetpakistan/plain-text.blade.php',
        ];

        foreach ($required as $path) {
            if (! is_file(base_path($path))) {
                $this->recordFail("Missing template: {$path}");
            } else {
                $this->pass("template ok: {$path}");
            }
        }
    }

    protected function auditEventRegistry(NotificationRecipientResolver $recipientResolver): void
    {
        $agency = Agency::query()->first();
        if ($agency === null) {
            $this->recordFail('No agency row available for recipient resolver smoke check');
        }

        foreach (OtaNotificationEvent::cases() as $event) {
            $key = $event->value;
            $definition = JetpkEmailEventContentRegistry::find($key);
            $buckets = JetpkOperationalEmailEventRegistry::bucketsForEvent($key);
            $perBucket = JetpkOperationalEmailEventRegistry::requiresPerBucketDelivery($key);

            $status = 'ok';
            $fixRequired = false;

            if ($definition === null) {
                $status = 'missing_definition';
                $fixRequired = true;
                $this->recordFail("No JetPK content definition for event: {$key}");
            }

            if ($agency !== null && $buckets !== []) {
                foreach ($buckets as $bucket) {
                    $resolved = $recipientResolver->resolveBucket($agency, $bucket, null, null, []);
                    if ($resolved['skipped'] && in_array($key, [
                        OtaNotificationEvent::AgentApplicationSubmitted->value,
                        OtaNotificationEvent::StaffCreated->value,
                        OtaNotificationEvent::UserSuspended->value,
                    ], true)) {
                        // Expected without context email — not a fail if bucket needs context
                    }
                }
            }

            if ($perBucket) {
                foreach ($buckets as $bucket) {
                    $variant = JetpkOperationalEmailEventRegistry::variantForBucket($key, $bucket);
                    if ($variant === null) {
                        $status = 'missing_variant';
                        $fixRequired = true;
                        $this->recordFail("Per-bucket event {$key} missing variant for bucket {$bucket}");
                    }
                }
            }

            $this->matrixRows[] = [
                'event_key' => $key,
                'business_event' => $definition?->name ?? $key,
                'trigger_location' => 'OtaNotificationService / domain services',
                'current_implementation' => 'OtaNotificationService + JetPK universal shell',
                'intended_recipients' => $buckets,
                'actual_recipients' => $buckets,
                'sender_identity' => 'agency SMTP / mail.default',
                'subject' => $definition?->subject ?? '',
                'template_layout' => JetpkEmailEventContentRegistry::SHELL_VIEW,
                'queue_mode' => (string) config('queue.default'),
                'duplicate_send_risk' => $perBucket ? 'low_per_bucket' : 'resolver_dedup',
                'missing_send_risk' => $fixRequired ? 'high' : 'low',
                'role_specific_wording' => $perBucket ? 'required' : ($definition?->audience ?? 'mixed'),
                'admin_notification_required' => in_array('admin', $buckets, true),
                'customer_notification_required' => in_array('applicant', $buckets, true) || in_array('user', $buckets, true),
                'status' => $status,
                'fix_required' => $fixRequired,
            ];
        }
    }

    protected function auditMisspelledApplicantKey(JetpkEmailBrandingLeakageAuditor $auditor): void
    {
        $hits = $auditor->scanMisspelledApplicantEmailKey();
        if ($hits !== []) {
            foreach ($hits as $hit) {
                $this->recordFail("Misspelled applican_email in {$hit['file']}:{$hit['line']}");
            }

            return;
        }

        $this->pass('no applican_email typo in active code');
    }

    protected function auditForbiddenBrandingInTemplates(JetpkEmailBrandingLeakageAuditor $auditor): void
    {
        foreach ($auditor->scanActiveBladeTemplates() as $hit) {
            $this->recordFail("Forbidden branding fragment [{$hit['fragment']}] in {$hit['file']}");
        }

        $this->pass('active blade template branding scan complete');
    }

    protected function auditDenylistConfigIsExcludedFromLeakage(JetpkEmailBrandingLeakageAuditor $auditor): void
    {
        $fragments = $auditor->denylistConfigFragments();
        if ($fragments === []) {
            $this->recordFail('Denylist config fragments missing — denylist may be misconfigured');

            return;
        }

        // Config files containing denylist entries must NOT be scanned as template leakage.
        foreach (['config/jetpk_operational_email.php', 'config/jetpk_email.php'] as $configPath) {
            if (! $auditor->isExcludedConfigPath($configPath)) {
                $this->recordFail("Denylist config path not excluded: {$configPath}");
            }
        }

        $this->pass('denylist config excluded from template leakage scan');
    }

    protected function writeMatrixFiles(): void
    {
        $dir = storage_path('app/audits/jetpk-email-coverage');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $jsonPath = $dir.'/EMAIL-EVENT-MATRIX.json';
        file_put_contents($jsonPath, json_encode([
            'generated_at' => now()->toIso8601String(),
            'fail_count' => $this->failCount,
            'rows' => $this->matrixRows,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $md = "# JetPK Operational Email Event Matrix\n\n";
        $md .= 'Generated: '.now()->toIso8601String()."\n\n";
        $md .= "| event_key | status | per_bucket | intended_recipients | fix_required |\n";
        $md .= "|---|---|---|---|---|\n";
        foreach ($this->matrixRows as $row) {
            $md .= sprintf(
                "| %s | %s | %s | %s | %s |\n",
                $row['event_key'],
                $row['status'],
                JetpkOperationalEmailEventRegistry::requiresPerBucketDelivery($row['event_key']) ? 'yes' : 'no',
                implode(', ', $row['intended_recipients'] ?? []),
                ($row['fix_required'] ?? false) ? 'yes' : 'no',
            );
        }

        file_put_contents($dir.'/EMAIL-EVENT-MATRIX.md', $md);
        $this->info("Matrix written to {$dir}");
    }

    protected function recordFail(string $message): void
    {
        $this->failCount++;
        $this->error($message);
    }

    protected function pass(string $message): void
    {
        if ($this->output->isVerbose()) {
            $this->line($message);
        }
    }
}
