<?php

namespace App\Console\Commands;

use App\Models\Agency;
use App\Services\Email\JetpkOperationalEmailService;
use App\Support\Emails\JetpkEmailBrandingLeakageAuditor;
use App\Support\Emails\JetpkEmailSampleDataProvider;
use App\Support\Emails\JetpkOperationalEmailEventRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Local HTML preview for JetPK operational emails (no mail send).
 *
 *   php artisan jetpk:email-preview --event=agent_application_submitted --role=applicant
 */
class JetpkEmailPreviewCommand extends Command
{
    protected $signature = 'jetpk:email-preview
        {--event= : OTA notification event key}
        {--role= : Recipient role/bucket variant (e.g. applicant, admin, user, staff)}';

    protected $description = 'Render a JetPK operational email preview to storage/app/email-previews/jetpk/ (no send).';

    public function handle(JetpkOperationalEmailService $emailService, JetpkEmailBrandingLeakageAuditor $leakageAuditor): int
    {
        $eventKey = (string) $this->option('event');
        $role = (string) ($this->option('role') ?: 'admin');

        if ($eventKey === '') {
            $this->error('--event is required');

            return self::FAILURE;
        }

        try {
            JetpkOperationalEmailEventRegistry::assertKnownEvent($eventKey);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $agency = Agency::query()->first();
        if ($agency === null) {
            $this->error('No agency found for preview rendering');

            return self::FAILURE;
        }

        $variant = JetpkOperationalEmailEventRegistry::variantForBucket($eventKey, $role)
            ?? ($role !== '' ? $role : null);

        $sample = JetpkEmailSampleDataProvider::forEvent($eventKey);
        $variantOverrides = $variant !== null
            ? JetpkOperationalEmailEventRegistry::variantContentOverrides($eventKey, $variant)
            : [];

        // Structured blocks (booking/itinerary/passengers/…) must be passed as renderer
        // payload — EmailBaseVariables scalar merge drops arrays.
        $payload = [];
        foreach ([
            'booking',
            'itinerary',
            'passengers',
            'payment',
            'payment_summary',
            'refund',
            'group_reservation',
            'security',
            'agent_application',
            'invoice',
            'message',
            'change_summary',
        ] as $blockKey) {
            if (isset($sample[$blockKey]) && is_array($sample[$blockKey])) {
                $payload[$blockKey] = $sample[$blockKey];
            }
        }

        $customerFacingRoles = ['user', 'customer', 'applicant', 'agent'];
        $introOverride = [];
        if (in_array(strtolower($role), $customerFacingRoles, true)) {
            $displayName = trim((string) ($sample['customer_name'] ?? $sample['recipientName'] ?? ''));
            $introOverride['intro'] = $displayName !== ''
                ? 'Hello '.$displayName.','
                : 'Hello,';
        }

        $rendered = $emailService->render(
            agency: $agency,
            eventKey: $eventKey,
            templateVariables: array_merge($sample, $variantOverrides, $introOverride, [
                'recipient_role' => $role,
                'recipient_designation' => $sample['recipient_designation'] ?? 'Reservation Staff',
                'customer_name' => $sample['customer_name'] ?? $sample['recipientName'] ?? 'Ayesha Khan',
            ]),
            deliveryVariant: $variant,
            recipientRole: $role,
            recipientDesignation: $sample['recipient_designation'] ?? 'Reservation Staff',
            payload: $payload,
        );

        $dir = storage_path('app/email-previews/jetpk');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = $eventKey.'_'.$role.'.html';
        $path = $dir.'/'.$filename;
        file_put_contents($path, $rendered['html']);

        $leakageHits = $leakageAuditor->scanRenderedContent($rendered['html'], 'preview_html');
        $leakageHits = array_merge(
            $leakageHits,
            $leakageAuditor->scanRenderedContent($rendered['plain_body'], 'preview_plain'),
            $leakageAuditor->scanRenderedContent($rendered['subject'], 'preview_subject'),
        );

        if ($leakageHits !== []) {
            $first = $leakageHits[0];
            $this->error("Forbidden branding fragment [{$first['fragment']}] in {$first['context']}");

            return self::FAILURE;
        }

        $this->info("Preview written: {$path}");

        return self::SUCCESS;
    }
}
