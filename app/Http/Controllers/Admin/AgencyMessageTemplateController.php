<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CommunicationChannel;
use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\AgencyMessageTemplate;
use App\Services\Communication\AgencyCommunicationSettingsService;
use App\Support\Branding\CompanyEmailProfileResolver;
use App\Support\Emails\EmailTemplatePreviewRenderer;
use App\Support\Emails\EmailTemplateRegistry;
use App\Support\Emails\JetpkEmailEventContentRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class AgencyMessageTemplateController extends Controller
{
    public function __construct(
        protected AgencyCommunicationSettingsService $settingsService,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AgencyMessageTemplate::class);

        $agency = $this->resolveTemplateAgency($request);
        $companyProfile = CompanyEmailProfileResolver::resolve($agency);

        $filters = [
            'q' => $request->string('q')->toString(),
            'category' => $request->string('category')->toString(),
            'audience' => $request->string('audience')->toString(),
            'connection' => $request->string('connection')->toString(),
            'db' => $request->string('db')->toString(),
            'enabled' => $request->string('enabled')->toString(),
        ];

        $entries = EmailTemplateRegistry::listForAgency($agency, $filters);
        $dbTemplates = collect($entries)->keyBy(fn (array $row) => $row['definition']->event);
        $eventContentGroups = [];
        $eventContentTotal = 0;
        $categoryCounts = [];

        foreach (JetpkEmailEventContentRegistry::categories() as $cat) {
            $categoryCounts[$cat['value']] = 0;
        }

        foreach (JetpkEmailEventContentRegistry::groupedByCategory() as $categoryKey => $definitions) {
            $rows = [];
            foreach ($definitions as $content) {
                if (! $this->matchesEventContentFilters($content, $filters, $dbTemplates->get($content->eventKey))) {
                    continue;
                }

                $registry = EmailTemplateRegistry::find('ops-'.$content->eventKey)
                    ?? collect(EmailTemplateRegistry::all())->first(fn ($d) => $d->event === $content->eventKey && $d->channel === 'email');
                $dbRow = $dbTemplates->get($content->eventKey);
                $dbTemplate = $dbRow['db_template'] ?? null;
                $resolved = JetpkEmailEventContentRegistry::resolveContent($content->eventKey, $dbTemplate);

                $rows[] = [
                    'content' => $content,
                    'registry' => $registry,
                    'has_override' => $dbTemplate !== null,
                    'is_enabled' => $resolved['enabled'],
                    'subject' => $resolved['subject'],
                    'preheader' => $resolved['preheader'],
                    'heading' => $resolved['heading'],
                    'status_label' => $resolved['status_label'] ?? '—',
                    'status_type' => $resolved['status_type'],
                    'cta_label' => $resolved['cta_label'],
                ];
                $categoryCounts[$categoryKey] = ($categoryCounts[$categoryKey] ?? 0) + 1;
                $eventContentTotal++;
            }

            if ($rows !== []) {
                $eventContentGroups[$categoryKey] = $rows;
            }
        }

        return view(client_view('settings.communications.templates', 'admin'), [
            'agency' => $agency,
            'companyProfile' => $companyProfile,
            'entries' => $entries,
            'eventContentGroups' => $eventContentGroups,
            'eventContentTotal' => $eventContentTotal,
            'categoryCounts' => $categoryCounts,
            'filters' => $filters,
            'categories' => EmailTemplateRegistry::categories(),
            'channels' => CommunicationChannel::cases(),
        ]);
    }

    public function preview(Request $request, string $registryKey): View
    {
        Gate::authorize('viewAny', AgencyMessageTemplate::class);

        $definition = EmailTemplateRegistry::find($registryKey);
        abort_if($definition === null, 404);

        $agency = $this->resolveTemplateAgency($request);
        $preview = app(EmailTemplatePreviewRenderer::class)->render($agency, $definition);

        return view(client_view('settings.communications.template-preview', 'admin'), [
            'agency' => $agency,
            'definition' => $definition,
            'dbTemplate' => $preview->dbTemplate,
            'companyProfile' => CompanyEmailProfileResolver::resolve($agency),
            'preview' => $preview,
        ]);
    }

    public function edit(Request $request, string $event, string $channel): View
    {
        Gate::authorize('viewAny', AgencyMessageTemplate::class);
        $agency = $this->resolveTemplateAgency($request);
        $template = AgencyMessageTemplate::query()->firstOrNew([
            'agency_id' => $agency->id,
            'event' => $event,
            'channel' => $channel,
        ], ['body' => '', 'is_enabled' => true]);

        $registryEntry = collect(EmailTemplateRegistry::all())
            ->first(fn ($row) => $row->event === $event && $row->channel === $channel && $row->editableNow);

        $eventContent = JetpkEmailEventContentRegistry::find($event);
        $meta = is_array($template->meta) ? $template->meta : [];
        $override = is_array($meta['jetpk_event_content'] ?? null) ? $meta['jetpk_event_content'] : [];
        $resolved = $eventContent
            ? JetpkEmailEventContentRegistry::resolveContent($event, $template->exists ? $template : null)
            : null;

        $isNewTemplate = ! $template->exists;
        if ($isNewTemplate && $registryEntry !== null) {
            $defaults = app(EmailTemplatePreviewRenderer::class)->defaultFields($registryEntry);
            $template->subject = $defaults['subject'];
            $template->body = $defaults['body'];
        }

        return view(client_view('settings.communications.template-edit', 'admin'), [
            'template' => $template,
            'event' => $event,
            'channel' => $channel,
            'registryEntry' => $registryEntry,
            'eventContent' => $eventContent,
            'resolvedContent' => $resolved,
            'contentOverride' => $override,
            'fullHtmlOverrideEnabled' => ($meta['full_html_override_enabled'] ?? false) === true,
            'isNewTemplate' => $isNewTemplate,
            'allowedVariables' => $registryEntry?->variables ?? ['agency_name', 'booking_reference', 'passenger_name'],
        ]);
    }

    public function update(Request $request, string $event, string $channel): RedirectResponse
    {
        $agency = $this->resolveTemplateAgency($request);
        $existing = AgencyMessageTemplate::query()->firstOrNew([
            'agency_id' => $agency->id,
            'event' => $event,
            'channel' => $channel,
        ]);
        Gate::authorize('update', $existing);

        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'preheader' => ['nullable', 'string', 'max:255'],
            'heading' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'status_label' => ['nullable', 'string', 'max:120'],
            'status_type' => ['nullable', 'string', 'in:info,success,warning,error,neutral'],
            'cta_label' => ['nullable', 'string', 'max:120'],
            'cta_url_key' => ['nullable', 'string', 'max:120'],
            'is_enabled' => ['nullable', 'boolean'],
            'full_html_override_enabled' => ['nullable', 'boolean'],
            'full_html' => ['nullable', 'string', 'max:50000'],
            'variables' => ['nullable', 'array'],
        ]);
        $validated['is_enabled'] = $request->boolean('is_enabled');

        $meta = is_array($existing->meta) ? $existing->meta : [];
        $meta['jetpk_event_content'] = array_filter([
            'subject' => $validated['subject'] ?? null,
            'preheader' => $validated['preheader'] ?? null,
            'heading' => $validated['heading'] ?? null,
            'intro' => $validated['body'],
            'body' => $validated['body'],
            'status_label' => $validated['status_label'] ?? null,
            'status_type' => $validated['status_type'] ?? null,
            'cta_label' => $validated['cta_label'] ?? null,
            'cta_url_key' => $validated['cta_url_key'] ?? null,
            'full_html' => $request->boolean('full_html_override_enabled') ? ($validated['full_html'] ?? null) : null,
        ], static fn ($v) => $v !== null && $v !== '');
        $meta['full_html_override_enabled'] = $request->boolean('full_html_override_enabled');

        $payload = [
            'subject' => $validated['subject'] ?? null,
            'body' => $validated['body'],
            'is_enabled' => $validated['is_enabled'],
            'variables' => $validated['variables'] ?? null,
            'meta' => $meta,
        ];

        $this->settingsService->updateTemplate($agency, $request->user(), $event, $channel, $payload);

        return redirect()->route('admin.settings.communications.templates.index')->with('status', 'communication-template-updated');
    }

    public function reset(Request $request, string $event, string $channel): RedirectResponse
    {
        $agency = $this->resolveTemplateAgency($request);
        $existing = AgencyMessageTemplate::query()->firstOrNew([
            'agency_id' => $agency->id,
            'event' => $event,
            'channel' => $channel,
        ]);
        Gate::authorize('update', $existing);

        $this->settingsService->resetTemplate($agency, $request->user(), $event, $channel);

        return redirect()->route('admin.settings.communications.templates.index')->with('status', 'communication-template-reset');
    }

    protected function resolveTemplateAgency(Request $request): Agency
    {
        $slug = trim((string) config('ota.default_agency_slug', ''));
        if ($slug !== '' && Schema::hasTable('agencies')) {
            $platformAgency = Agency::query()->where('slug', $slug)->first();
            if ($platformAgency !== null) {
                return $platformAgency;
            }
        }

        return Agency::query()->findOrFail($request->user()->current_agency_id);
    }

    protected function matchesEventContentFilters(
        \App\Support\Emails\JetpkEmailEventContentDefinition $content,
        array $filters,
        ?array $registryRow,
    ): bool {
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $haystack = strtolower($content->name.' '.$content->eventKey);
            if (! str_contains($haystack, strtolower($q))) {
                return false;
            }
        }

        if (($filters['audience'] ?? '') !== '' && $content->audience !== $filters['audience']) {
            return false;
        }

        $hasDb = $registryRow !== null && ($registryRow['has_db_row'] ?? false);
        if (($filters['db'] ?? '') === 'saved' && ! $hasDb) {
            return false;
        }
        if (($filters['db'] ?? '') === 'missing' && $hasDb) {
            return false;
        }

        $enabled = $registryRow['is_enabled'] ?? true;
        if (($filters['enabled'] ?? '') === 'enabled' && $enabled === false) {
            return false;
        }
        if (($filters['enabled'] ?? '') === 'disabled' && $enabled !== false) {
            return false;
        }

        return true;
    }
}
