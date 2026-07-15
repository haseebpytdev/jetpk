<?php

namespace App\Console\Commands;

use App\Models\Agency;
use App\Support\Emails\EmailBaseVariables;
use App\Support\Emails\EmailTemplateDefinition;
use App\Support\Emails\EmailTemplatePreviewRenderer;
use App\Support\Emails\EmailTemplateRegistry;
use App\Support\Emails\EmailTemplateStringRenderer;
use App\Support\Emails\OperationalEmailDefaults;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class OtaEmailTemplateSmokeCommand extends Command
{
    /** @var list<string> */
    private const SKIP_TEMPLATE_KEYS = [
        'auth-email-verification',
        'auth-password-reset',
    ];

    protected $signature = 'ota:email-template-smoke
                            {--agency= : Limit smoke check to one agency slug}
                            {--visual : Also verify representative HTML layout sections (no mail send)}';

    protected $description = 'Render OTA email templates with minimal variables to verify placeholder fallbacks (no mail send)';

    public function handle(EmailTemplateStringRenderer $stringRenderer): int
    {
        $agency = $this->resolveAgency();
        if ($agency === null) {
            $this->error('No agency found for smoke check. Seed the platform or pass --agency=slug.');

            return self::FAILURE;
        }

        $totalChecked = 0;
        $templatesWithUnresolved = 0;
        $unresolvedAfterFallbackCount = 0;
        $failed = 0;
        $skipped = 0;

        foreach (EmailTemplateRegistry::all() as $definition) {
            if ($definition->channel !== 'email') {
                continue;
            }

            if (in_array($definition->key, self::SKIP_TEMPLATE_KEYS, true)) {
                $skipped++;

                continue;
            }

            $defaults = $this->resolveTemplateCopy($definition);
            if ($defaults === null) {
                $skipped++;

                continue;
            }

            $mergedVariables = EmailBaseVariables::merge($agency, null, []);
            $context = [
                'template_key' => $definition->key,
                'event_key' => $definition->event,
                'audience' => $definition->audience,
                'brand_name' => (string) ($mergedVariables['brand_name'] ?? ''),
            ];

            $subjectResult = $stringRenderer->render($defaults['subject'], $mergedVariables, $context);
            $bodyResult = $stringRenderer->render($defaults['body'], $mergedVariables, $context);

            $totalChecked++;
            $templateUnresolved = array_values(array_unique(array_merge(
                $subjectResult->unresolvedAfterFallback,
                $bodyResult->unresolvedAfterFallback,
            )));

            $templateFailed = false;

            if ($templateUnresolved !== []) {
                $templatesWithUnresolved++;
                $unresolvedAfterFallbackCount += count($templateUnresolved);
                $templateFailed = true;
                $this->warn(sprintf(
                    '%s unresolved: %s',
                    $definition->key,
                    implode(', ', $templateUnresolved),
                ));
            }

            if ($this->containsPlaceholder($subjectResult->output) || $this->containsPlaceholder($bodyResult->output)) {
                $templateFailed = true;
                $this->warn(sprintf('%s still contains raw placeholder tokens.', $definition->key));
            }

            if ($templateFailed) {
                $failed++;
            }
        }

        $this->line('total_templates_checked='.$totalChecked);
        $this->line('templates_with_unresolved_placeholders='.$templatesWithUnresolved);
        $this->line('unresolved_after_fallback_count='.$unresolvedAfterFallbackCount);
        $this->line('skipped='.$skipped);
        $this->line('failed='.$failed);

        if ($this->option('visual')) {
            $visualFailed = $this->runVisualSmoke($agency);
            if ($visualFailed > 0) {
                $failed += $visualFailed;
                $this->line('visual_failed='.$visualFailed);
            } else {
                $this->line('visual_failed=0');
            }
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function runVisualSmoke(Agency $agency): int
    {
        $previewRenderer = app(EmailTemplatePreviewRenderer::class);
        $representativeKeys = [
            'ops-booking_request_received',
            'ops-booking_manual_review_required',
            'ops-ticket_issued',
            'ops-supplier_booking_failed',
        ];
        $failed = 0;

        foreach ($representativeKeys as $key) {
            $definition = EmailTemplateRegistry::find($key);
            if ($definition === null) {
                continue;
            }

            try {
                $result = $previewRenderer->render($agency, $definition);
            } catch (\Throwable $e) {
                $failed++;
                $this->warn($key.' visual render failed: '.$e->getMessage());

                continue;
            }

            $html = $result->html;
            $structuralChecks = [
                '<!DOCTYPE html>' => 'DOCTYPE',
                'Operational alert' => 'ops header label',
                'Summary' => 'summary section',
            ];

            foreach ($structuralChecks as $needle => $label) {
                if (! str_contains($html, $needle)) {
                    $failed++;
                    $this->warn($key.' missing visual marker: '.$label);
                }
            }

            if (! preg_match('/Action required|What to do next/', $html)) {
                $failed++;
                $this->warn($key.' missing action card title.');
            }

            if (preg_match('/\{\{\s*[\w.]+\s*\}\}/', $html) === 1) {
                $failed++;
                $this->warn($key.' HTML contains raw placeholder tokens.');
            }
        }

        return $failed;
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
     * @return array{subject: string, body: string}|null
     */
    protected function resolveTemplateCopy(EmailTemplateDefinition $definition): ?array
    {
        $defaults = OperationalEmailDefaults::forEvent($definition->event);
        if ($defaults !== null) {
            return $defaults;
        }

        if ($definition->variables === []) {
            return null;
        }

        return [
            'subject' => '{{ brand_name }} — '.$definition->name,
            'body' => implode("\n", array_map(
                fn (string $variable): string => Str::headline(str_replace('_', ' ', $variable)).': {{ '.$variable.' }}',
                $definition->variables,
            )),
        ];
    }

    protected function containsPlaceholder(string $output): bool
    {
        return (bool) preg_match('/\{\{\s*[\w.]+\s*\}\}/', $output);
    }
}
