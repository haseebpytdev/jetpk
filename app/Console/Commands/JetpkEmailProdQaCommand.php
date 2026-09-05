<?php

namespace App\Console\Commands;

use App\Models\Agency;
use App\Services\Email\JetpkOperationalEmailService;
use App\Support\Branding\CompanyEmailProfileResolver;
use App\Support\Emails\HtmlEmailPlainTextConverter;
use App\Support\Emails\JetpkEmailBrandingResolver;
use App\Support\Emails\JetpkEmailEventContentRegistry;
use App\Support\Emails\JetpkEmailQaContentAuditor;
use App\Support\Emails\JetpkEmailQaCorrelation;
use App\Support\Emails\JetpkEmailQaRecipientLock;
use App\Support\Emails\JetpkEmailQaSnapshotStore;
use App\Support\Emails\JetpkEmailSampleDataProvider;
use App\Support\Emails\JetpkOperationalEmailEventRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class JetpkEmailProdQaCommand extends Command
{
    protected $signature = 'jetpk:email-prod-qa
        {--run-id= : QA run identifier}
        {--inventory : Write inventory JSON only}
        {--render : Render and snapshot without SMTP send}
        {--send : Send after render (authorized inbox only)}
        {--family-gate : One representative scenario per major family}
        {--limit=0 : Max scenarios}
        {--start=0 : Skip first N scenarios}
        {--skip-ids= : Comma-separated scenario_ids already passed}';

    protected $description = 'JetPakistan production email QA: inventory, snapshot, optional locked send.';

    public function handle(JetpkOperationalEmailService $emailService, JetpkEmailQaContentAuditor $auditor): int
    {
        $runId = (string) ($this->option('run-id') ?: ('jp-email-prod-qa-02-'.now()->format('YmdHis')));
        $events = JetpkEmailEventContentRegistry::all();
        $rows = [];
        foreach ($events as $eventKey => $definition) {
            $buckets = JetpkOperationalEmailEventRegistry::bucketsForEvent($eventKey);
            if ($buckets === []) {
                $buckets = [$definition->audience ?: 'customer'];
            }
            foreach ($buckets as $bucket) {
                $rows[] = [
                    'scenario_id' => $eventKey.'__'.$bucket,
                    'event_key' => $eventKey,
                    'role' => $bucket,
                    'audience' => $definition->audience,
                    'status' => 'ACTIVE_PRODUCTION',
                    'proof_class' => $this->proofClassFor($eventKey),
                ];
            }
        }

        $outDir = storage_path('app/email-qa/live/'.$runId);
        File::ensureDirectoryExists($outDir, 0700);
        $docsDir = base_path('docs/evidence/jp-email-prod-qa-02');
        $inventoryPayload = [
            'run_id' => $runId,
            'discovered' => count($events),
            'active_email_events' => count($events),
            'active_role_copies' => count($rows),
            'scenarios' => $rows,
        ];
        File::put($outDir.'/email-inventory.json', json_encode($inventoryPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        if (is_dir($docsDir) && $this->option('inventory')) {
            File::ensureDirectoryExists($docsDir);
            File::put($docsDir.'/email-inventory.json', json_encode($inventoryPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        $this->info('Discovered events: '.count($events).'; role copies: '.count($rows));

        if ($this->option('inventory') && ! $this->option('render') && ! $this->option('send')) {
            return self::SUCCESS;
        }

        if (! $this->option('render') && ! $this->option('send')) {
            return self::SUCCESS;
        }

        $agency = Agency::query()->first();
        if ($agency === null) {
            $this->error('No agency available for QA render.');

            return self::FAILURE;
        }

        $store = JetpkEmailQaSnapshotStore::createForRun($runId);
        $htmlDir = $outDir.'/html';
        File::ensureDirectoryExists($htmlDir, 0700);
        $limit = (int) $this->option('limit');
        $start = (int) $this->option('start');
        $skipIds = array_filter(array_map('trim', explode(',', (string) $this->option('skip-ids'))));
        if ($skipIds !== []) {
            $rows = array_values(array_filter($rows, static fn (array $row): bool => ! in_array($row['scenario_id'], $skipIds, true)));
        }
        $slice = $this->option('family-gate') ? $this->familyGateRows($rows) : array_slice($rows, $start, $limit > 0 ? $limit : null);
        $brand = JetpkEmailBrandingResolver::resolve('jetpk');
        $company = CompanyEmailProfileResolver::resolveForPlatform();
        $from = (string) config('mail.from.address');
        $reply = (string) ($company->reply_to_email ?? '');

        JetpkEmailQaRecipientLock::activate(JetpkEmailQaRecipientLock::AUTHORIZED_INBOX);

        $manifest = [];
        try {
            foreach ($slice as $row) {
                $correlation = JetpkEmailQaCorrelation::make($row['scenario_id']);
                if ($store->correlationExists($correlation)) {
                    $this->error('Duplicate correlation');

                    return self::FAILURE;
                }
                app()->instance('jetpk.email_qa.correlation', $correlation);

                $sample = JetpkEmailSampleDataProvider::forEvent($row['event_key']);
                $payload = [];
                foreach (['booking', 'itinerary', 'passengers', 'payment', 'refund', 'group_reservation', 'security', 'agent_application'] as $block) {
                    if (isset($sample[$block]) && is_array($sample[$block])) {
                        $payload[$block] = $sample[$block];
                    }
                }

                try {
                    $rendered = $emailService->render(
                        agency: $agency,
                        eventKey: $row['event_key'],
                        templateVariables: is_array($sample) ? array_filter($sample, 'is_scalar') : [],
                        deliveryVariant: JetpkOperationalEmailEventRegistry::variantForBucket($row['event_key'], $row['role']),
                        recipientRole: $row['role'],
                        payload: $payload,
                    );
                } catch (Throwable $e) {
                    $this->error($row['scenario_id'].': '.$e->getMessage());

                    return self::FAILURE;
                }

                $plain = HtmlEmailPlainTextConverter::fromHtml($rendered['html'], $rendered['plain_body']);
                $audit = $auditor->audit($rendered['subject'], $rendered['html'], $plain);
                $store->insert([
                    'run_id' => $runId,
                    'scenario_id' => $row['scenario_id'],
                    'correlation_id' => $correlation,
                    'scenario_name' => $row['event_key'],
                    'intended_recipient_role' => $row['role'],
                    'proof_class' => $row['proof_class'],
                    'trigger_source' => 'JetpkOperationalEmailService::render',
                    'mailable_class' => \App\Mail\JetpkOperationalEventMail::class,
                    'subject' => $rendered['subject'],
                    'from_identity' => $from,
                    'reply_to' => $reply,
                    'test_recipient' => JetpkEmailQaRecipientLock::AUTHORIZED_INBOX,
                    'branding_json' => json_encode([
                        'name' => $company->name,
                        'logo_url' => $company->logo_url,
                        'support_email' => $company->support_email,
                        'support_phone' => $company->support_phone,
                        'website_url' => $company->website_url,
                        'EMAIL_LOGO_SOURCE' => 'CANONICAL_COMPANY_PROFILE',
                        'EMAIL_COMPANY_NAME_SOURCE' => 'CANONICAL_COMPANY_PROFILE',
                    ]),
                    'logo_url' => $brand['logo_url'] ?? $company->logo_url,
                    'support_json' => json_encode([
                        'email' => $company->support_email,
                        'phone' => $company->support_phone,
                        'website' => $company->website_url,
                        'EMAIL_SUPPORT_EMAIL_SOURCE' => 'CANONICAL_COMPANY_PROFILE',
                        'EMAIL_SUPPORT_PHONE_SOURCE' => 'CANONICAL_COMPANY_PROFILE',
                        'EMAIL_WEBSITE_SOURCE' => 'CANONICAL_COMPANY_PROFILE',
                    ]),
                    'html_body' => $rendered['html'],
                    'plain_text_body' => $plain,
                    'runtime_sha' => trim((string) @file_get_contents(base_path('.git/HEAD'))),
                    'public_build_id' => '',
                    'transport_result' => $this->option('send') ? 'pending' : 'render_only',
                    'content_audit_json' => json_encode($audit),
                    'raw_artifact_sha256' => hash('sha256', $rendered['html']),
                ]);

                File::put($htmlDir.'/'.$row['scenario_id'].'.html', $rendered['html']);
                File::put($htmlDir.'/'.$row['scenario_id'].'.txt', $plain);

                if (! $audit['pass']) {
                    $this->warn($row['scenario_id'].' content audit: '.implode(',', $audit['failures']));
                    if ($this->option('send')) {
                        $this->error('Stopping batch on content audit failure.');

                        return self::FAILURE;
                    }
                }

                $transport = 'render_only';
                if ($this->option('send')) {
                    $to = JetpkEmailQaRecipientLock::enforceOrFail(JetpkEmailQaRecipientLock::AUTHORIZED_INBOX);
                    $transport = $emailService->dispatchMail($to, $rendered['subject'], $rendered['html'], $plain);
                    usleep(250000);
                }

                $manifest[] = [
                    'scenario_id' => $row['scenario_id'],
                    'correlation_id' => $correlation,
                    'subject' => $rendered['subject'],
                    'role' => $row['role'],
                    'proof_class' => $row['proof_class'],
                    'transport' => $transport,
                    'content_audit_pass' => $audit['pass'],
                    'recipient' => JetpkEmailQaRecipientLock::AUTHORIZED_INBOX,
                ];
            }
        } finally {
            JetpkEmailQaRecipientLock::deactivate();
            app()->forgetInstance('jetpk.email_qa.correlation');
        }

        $manifestPayload = [
            'run_id' => $runId,
            'snapshot_db' => 'storage/app/email-qa/live/'.$runId.'.sqlite',
            'html_dir' => 'storage/app/email-qa/live/'.$runId.'/html',
            'messages' => $manifest,
            'GMAIL_INBOX_VERIFICATION' => 'PENDING_CHATGPT',
        ];
        File::put($outDir.'/email-delivery-manifest.json', json_encode($manifestPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        if (is_dir($docsDir)) {
            File::put($docsDir.'/email-delivery-manifest.json', json_encode($manifestPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        $this->info('Snapshots: '.count($manifest).' db='.$store->path());

        return self::SUCCESS;
    }

    protected function proofClassFor(string $eventKey): string
    {
        $liveSafe = [
            'customer_welcome',
            'email_verification',
            'password_reset',
            'login_otp',
            'login_new_device',
            'support_ticket_created',
            'contact_form_received',
            'agent_application_submitted',
        ];

        foreach ($liveSafe as $needle) {
            if (str_contains($eventKey, $needle)) {
                return 'FULL_LIVE_TRIGGER';
            }
        }

        if (preg_match('/booking|payment|ticket|refund|cancel|pnr|supplier|fare/i', $eventKey) === 1) {
            return 'PRODUCTION_EVENT_CHAIN';
        }

        return 'PRODUCTION_EVENT_CHAIN';
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function familyGateRows(array $rows): array
    {
        $families = [
            'customer_booking' => '/booking_(confirmed|request_received)/',
            'admin_operational' => '/admin|staff_review|manual_review/',
            'agent' => '/agent/',
            'staff' => '/staff/',
            'auth_security' => '/password_reset|login_otp|email_verification|login_new_device|customer_registered|customer_welcome/',
            'payment_refund' => '/payment|refund/',
            'ticket' => '/ticket_issued|tickets_issued/',
            'group_other' => '/group_booking/',
        ];
        $picked = [];
        foreach ($families as $family => $pattern) {
            foreach ($rows as $row) {
                if (preg_match($pattern, (string) $row['event_key']) !== 1) {
                    continue;
                }
                $picked[] = $row;
                break;
            }
        }

        return $picked;
    }
}
