<?php

namespace App\Console\Commands;

use App\Models\Agency;
use App\Support\Branding\CompanyEmailProfileResolver;
use App\Support\Emails\EmailBaseVariables;
use App\Support\Emails\EmailTemplateDefinition;
use App\Support\Emails\EmailTemplatePreviewRenderer;
use App\Support\Emails\EmailTemplateRegistry;
use App\Support\Emails\EmailTemplateSampleData;
use App\Support\Emails\EmailTemplateStringRenderer;
use App\Support\Emails\OperationalEmailDefaults;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class OtaEmailTemplateAuditCommand extends Command
{
    protected $signature = 'ota:email-template-audit
                            {--agency= : Limit audit to one agency slug}
                            {--write-previews : Write rendered HTML previews to storage/app/email-previews/}';

    protected $description = 'Audit OTA email templates for unresolved placeholders and missing base variables (no mail send)';

    public function handle(
        EmailTemplateStringRenderer $stringRenderer,
        EmailTemplatePreviewRenderer $previewRenderer,
    ): int {
        $agency = $this->resolveAgency();
        if ($agency === null) {
            $this->error('No agency found for audit. Seed the platform or pass --agency=slug.');

            return self::FAILURE;
        }

        $profile = CompanyEmailProfileResolver::resolve($agency);
        $failures = 0;
        $rows = [];

        foreach (EmailTemplateRegistry::all() as $definition) {
            if ($definition->channel !== 'email') {
                continue;
            }

            $sampleVariables = EmailTemplateSampleData::forDefinition($definition, $profile);
            $mergedVariables = EmailBaseVariables::merge($agency, null, $sampleVariables);
            $missingBase = $this->missingBaseVariables($mergedVariables);
            $defaults = OperationalEmailDefaults::forEvent($definition->event);
            $rawSubject = $defaults['subject'] ?? '{{ agency_name }} — '.$definition->name;
            $rawBody = $defaults['body'] ?? 'Booking reference: {{ booking_reference }}';

            $subjectResult = $stringRenderer->render($rawSubject, $mergedVariables, [
                'template_key' => $definition->key,
                'event_key' => $definition->event,
            ]);
            $bodyResult = $stringRenderer->render($rawBody, $mergedVariables, [
                'template_key' => $definition->key,
                'event_key' => $definition->event,
            ]);

            $hasUnresolved = $subjectResult->unresolvedAfterFallback !== []
                || $bodyResult->unresolvedAfterFallback !== [];
            if ($hasUnresolved || $missingBase !== []) {
                $failures++;
            }

            $rows[] = [
                $definition->key,
                $definition->event,
                $hasUnresolved ? 'FAIL' : 'OK',
                $missingBase === [] ? '—' : implode(', ', $missingBase),
                mb_strimwidth($subjectResult->output, 0, 70, '…'),
            ];

            if ((bool) $this->option('write-previews')) {
                $this->writePreview($previewRenderer, $agency, $definition);
            }
        }

        $this->info('Agency: '.$agency->slug.' ('.$profile->name.')');
        $this->table(['Registry key', 'Event', 'Render', 'Missing base vars', 'Sample subject'], $rows);
        $this->line('Templates audited: '.count($rows));
        $this->line('Failures: '.$failures);

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function resolveAgency(): ?Agency
    {
        $slug = trim((string) ($this->option('agency') ?: config('ota.default_agency_slug', '')));
        if ($slug === '') {
            return Agency::query()->orderBy('id')->first();
        }

        return Agency::query()->where('slug', $slug)->first();
    }

    /**
     * @param  array<string, string>  $variables
     * @return list<string>
     */
    protected function missingBaseVariables(array $variables): array
    {
        $required = [
            'brand_name',
            'booking_reference',
            'support_email',
        ];

        $missing = [];
        foreach ($required as $key) {
            if (! array_key_exists($key, $variables) || trim((string) $variables[$key]) === '') {
                if (in_array($key, ['booking_reference'], true)) {
                    continue;
                }
                $missing[] = $key;
            }
        }

        return $missing;
    }

    protected function writePreview(
        EmailTemplatePreviewRenderer $previewRenderer,
        Agency $agency,
        EmailTemplateDefinition $definition,
    ): void {
        $result = $previewRenderer->render($agency, $definition);
        $dir = storage_path('app/email-previews');
        File::ensureDirectoryExists($dir);
        $filename = $dir.DIRECTORY_SEPARATOR.$definition->key.'.html';
        File::put($filename, $result->html);
        $this->line('Preview written: '.$filename);
    }
}
