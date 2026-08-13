<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OtaNotificationEvent;
use App\Http\Controllers\Concerns\RespondsWithBackOfficeJson;
use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\AgencyCommunicationSetting;
use App\Models\AgencyNotificationSetting;
use App\Http\Resources\Dashboard\DashboardSettingsResource;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AgencyNotificationSettingController extends Controller
{
    use RespondsWithBackOfficeJson;

    public function index(Request $request): View|JsonResponse
    {
        $agency = Agency::query()->findOrFail($request->user()->current_agency_id);
        $communicationSettingsRecord = $this->communicationSettingsForAuthorization($agency);
        Gate::authorize('view', $communicationSettingsRecord);

        foreach (OtaNotificationEvent::cases() as $event) {
            AgencyNotificationSetting::query()->firstOrCreate(
                [
                    'agency_id' => $agency->id,
                    'event_key' => $event->value,
                    'channel' => 'email',
                ],
                [
                    'enabled' => true,
                    'recipient_scope' => $event->defaultScope(),
                    'digest_mode' => 'immediate',
                ]
            );
        }

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'notifications' => DashboardSettingsResource::notifications(),
            ]);
        }

        $notificationSettings = AgencyNotificationSetting::query()
            ->where('agency_id', $agency->id)
            ->where('channel', 'email')
            ->orderBy('event_key')
            ->get()
            ->keyBy('event_key');

        return view(client_view('settings.communications.notification-events', 'admin'), [
            'agency' => $agency,
            'communicationSettingsRecord' => $communicationSettingsRecord,
            'notificationSettings' => $notificationSettings,
            'events' => OtaNotificationEvent::cases(),
            'eventGroups' => \App\Support\Communication\JetpkNotificationEventCategories::grouped(),
            'canUpdate' => Gate::check('update', $communicationSettingsRecord),
        ]);
    }

    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $agency = Agency::query()->findOrFail($request->user()->current_agency_id);
        $settings = $this->communicationSettingsForAuthorization($agency);
        Gate::authorize('update', $settings);

        $allowedKeys = collect(OtaNotificationEvent::cases())->map->value->all();

        if ($request->filled('categories') && ! $request->filled('events')) {
            $events = [];
            foreach ((array) $request->input('categories', []) as $category) {
                if (! is_array($category)) {
                    continue;
                }
                foreach ((array) ($category['eventKeys'] ?? []) as $eventKey) {
                    $events[$eventKey] = [
                        'enabled' => $category['enabled'] ?? false,
                        'email_channel' => $category['emailChannel'] ?? true,
                        'recipient_scope' => is_array($category['recipientRoles'] ?? null)
                            ? ($category['recipientRoles'][0] ?? 'admin')
                            : ($category['recipient_scope'] ?? 'admin'),
                        'recipient_roles' => is_array($category['recipientRoles'] ?? null)
                            ? $category['recipientRoles']
                            : [$category['recipient_scope'] ?? 'admin'],
                        'dashboard_channel' => $category['dashboardChannel'] ?? false,
                        'delivery_mode' => $category['deliveryMode'] ?? 'immediate',
                        'severity_threshold' => $category['severityThreshold'] ?? 'notice',
                    ];
                }
            }
            $request->merge(['events' => $events]);
        }

        $validated = $request->validate([
            'events' => ['required', 'array'],
            'events.*.enabled' => ['nullable'],
            'events.*.recipient_scope' => ['nullable', 'string', 'in:admin,staff,agent,customer'],
            'events.*.recipient_emails' => ['nullable', 'string', 'max:8000'],
            'events.*.cc_emails' => ['nullable', 'string', 'max:8000'],
            'events.*.bcc_emails' => ['nullable', 'string', 'max:8000'],
        ]);

        foreach ($validated['events'] as $eventKey => $row) {
            if (! in_array($eventKey, $allowedKeys, true)) {
                continue;
            }

            $enabled = filter_var($row['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $emailChannel = filter_var($row['email_channel'] ?? $row['emailChannel'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $dashboard = filter_var($row['dashboard_channel'] ?? $row['dashboardChannel'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $digest = ($row['delivery_mode'] ?? $row['deliveryMode'] ?? 'immediate') === 'digest' ? 'digest' : 'immediate';
            $severity = (string) ($row['severity_threshold'] ?? $row['severityThreshold'] ?? 'notice');
            if (! in_array($severity, ['notice', 'warning', 'critical'], true)) {
                $severity = 'notice';
            }
            $roles = is_array($row['recipient_roles'] ?? null) ? $row['recipient_roles'] : [];
            $roles = array_values(array_filter($roles, static fn ($role): bool => in_array((string) $role, ['admin', 'staff', 'agent', 'customer'], true)));
            $scope = (string) ($row['recipient_scope'] ?? $roles[0] ?? 'admin');
            if (! in_array($scope, ['admin', 'staff', 'agent', 'customer'], true)) {
                $scope = 'admin';
            }
            if ($roles === []) {
                $roles = [$scope];
            }

            AgencyNotificationSetting::query()->updateOrCreate(
                [
                    'agency_id' => $agency->id,
                    'event_key' => $eventKey,
                    'channel' => 'email',
                ],
                [
                    'enabled' => $enabled && $emailChannel,
                    'recipient_scope' => $scope,
                    'recipient_emails' => $this->parseEmailList($row['recipient_emails'] ?? ''),
                    'cc_emails' => $this->parseEmailList($row['cc_emails'] ?? ''),
                    'bcc_emails' => $this->parseEmailList($row['bcc_emails'] ?? ''),
                    'digest_mode' => $digest,
                    'meta' => [
                        'category_enabled' => $enabled,
                        'email_channel' => $emailChannel,
                        'dashboard_channel' => $dashboard,
                        'severity_threshold' => $severity,
                        'recipient_roles' => $roles,
                    ],
                ]
            );
        }

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'Notification routing updated.',
                'notifications' => DashboardSettingsResource::notifications(),
            ]);
        }

        return redirect()->route('admin.settings.communications.notification-events.index')->with('status', 'notification-routing-updated');
    }

    private function communicationSettingsForAuthorization(Agency $agency): AgencyCommunicationSetting
    {
        return AgencyCommunicationSetting::query()->firstOrCreate(
            ['agency_id' => $agency->id],
            ['notification_rules' => ['email' => true, 'whatsapp' => false]]
        );
    }

    /**
     * @return array<int, string>
     */
    private function parseEmailList(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        return collect(preg_split('/[\s,;]+/', $raw) ?: [])
            ->map(fn ($s): string => strtolower(trim((string) $s)))
            ->filter(fn (string $e): bool => $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }
}
