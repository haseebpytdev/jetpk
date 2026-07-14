<?php

namespace App\Services\Agents;

use App\Enums\AccountType;
use App\Enums\OtaNotificationEvent;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\AgencyUser;
use App\Models\Agent;
use App\Models\AgentApplication;
use App\Models\User;
use App\Services\Agencies\AgencyBrandingService;
use App\Services\Communication\AgencyMessageTemplateSeeder;
use App\Services\Communication\OtaNotificationService;
use App\Support\References\CompactReferenceGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Converts approved agent applications into agency companies with owner accounts.
 */
class AgentApplicationOnboardingService
{
    public function __construct(
        protected AgencyBrandingService $brandingService,
        protected AgencyMessageTemplateSeeder $messageTemplateSeeder,
        protected OtaNotificationService $notificationService,
        protected CompactReferenceGenerator $referenceGenerator,
    ) {}

    /**
     * @return array{agency: Agency, user: User, agent: Agent}
     */
    public function approve(AgentApplication $application, User $admin, ?string $internalNote = null): array
    {
        if ($application->status === 'approved') {
            throw new InvalidArgumentException('Application is already approved.');
        }

        $result = DB::transaction(function () use ($application, $admin, $internalNote): array {
            $agency = $this->resolveOrCreateAgency($application);
            $user = $this->resolveOrCreateOwnerUser($application, $agency);
            $agent = $this->resolveOrCreateAgent($application, $agency, $user);

            AgencyUser::query()->updateOrCreate(
                ['agency_id' => $agency->id, 'user_id' => $user->id],
                ['role' => AccountType::Agent->value]
            );

            $application->forceFill([
                'status' => 'approved',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'internal_note' => $internalNote ?: $application->internal_note,
            ])->save();

            return [
                'agency' => $agency->fresh(['agencySetting']),
                'user' => $user->fresh(),
                'agent' => $agent->fresh(),
            ];
        });

        Password::sendResetLink(['email' => $result['user']->email]);

        $this->sendApprovalNotification($result['agency'], $application, $result['user']);

        return $result;
    }

    public function sendNeedsMoreInfoNotification(AgentApplication $application, Agency $agency, ?string $note = null): void
    {
        $platformAgency = $this->platformNotificationAgency($agency);
        $supportEmail = (string) ($platformAgency->agencySetting?->support_email ?? config('mail.from.address', ''));
        $supportPhone = (string) ($platformAgency->agencySetting?->support_phone ?? '');

        $this->notificationService->send(
            agency: $platformAgency,
            eventKey: OtaNotificationEvent::AgentApplicationNeedsMoreInfo->value,
            payload: [
                'applicant_name' => trim($application->first_name.' '.$application->last_name),
                'company_name' => (string) $application->company_name,
                'information_required' => $note ?: 'Please contact our team with the requested details.',
            ],
            fallbackSubject: 'Agent application — additional information required',
            fallbackBody: implode("\n\n", array_filter([
                'Thank you for your interest in partnering with us.',
                $note ? 'We need the following information before we can continue reviewing your application:'."\n".$note : 'We need additional information before we can continue reviewing your application.',
                'Please reply to this email'.($supportEmail !== '' ? ' at '.$supportEmail : '').' or contact our support team'.($supportPhone !== '' ? ' at '.$supportPhone : '').'.',
            ])),
            templateVariables: [
                'information_required' => $note ?: '',
                'support_email' => $supportEmail,
                'support_phone' => $supportPhone,
            ],
            recipientContext: ['applicant_email' => $application->email],
        );
    }

    public function sendRejectionNotification(AgentApplication $application, Agency $agency, ?string $reason = null): void
    {
        $platformAgency = $this->platformNotificationAgency($agency);

        $this->notificationService->send(
            agency: $platformAgency,
            eventKey: OtaNotificationEvent::AgentApplicationRejected->value,
            payload: [
                'applicant_name' => trim($application->first_name.' '.$application->last_name),
                'company_name' => (string) $application->company_name,
                'rejection_reason' => $reason ?: '',
            ],
            fallbackSubject: 'Agent application update',
            fallbackBody: implode("\n\n", array_filter([
                'Thank you for your interest in partnering with us.',
                'After careful review, we are unable to approve your agent application at this time.',
                $reason ? 'Reason provided by our team:'."\n".$reason : null,
                'If you have questions, please contact our support team.',
            ])),
            templateVariables: [
                'rejection_reason' => $reason ?: '',
            ],
            recipientContext: ['applicant_email' => $application->email],
        );
    }

    protected function resolveOrCreateAgency(AgentApplication $application): Agency
    {
        $existingUser = User::query()->where('email', $application->email)->first();
        $existingAgent = $existingUser !== null
            ? Agent::query()->where('user_id', $existingUser->id)->orderBy('id')->first()
            : null;

        if ($existingAgent !== null) {
            return Agency::query()->findOrFail($existingAgent->agency_id);
        }

        $companyName = trim((string) ($application->company_name ?: trim($application->first_name.' '.$application->last_name)));
        if ($companyName === '') {
            $companyName = 'Agency '.$application->id;
        }

        $slug = $this->uniqueAgencySlug($companyName);

        $agency = Agency::query()->create([
            'name' => $companyName,
            'slug' => $slug,
            'timezone' => 'Asia/Karachi',
        ]);

        $this->brandingService->getSettingsForAgency($agency);
        $agency->agencySetting?->forceFill([
            'display_name' => $companyName,
            'support_email' => $application->email,
            'city' => $application->city,
            'country' => $application->country,
        ])->save();

        $this->seedDefaultMessageTemplatesForNewAgency($agency);

        return $agency->fresh(['agencySetting']);
    }

    protected function resolveOrCreateOwnerUser(AgentApplication $application, Agency $agency): User
    {
        $user = User::query()->firstOrCreate(
            ['email' => $application->email],
            [
                'name' => trim($application->first_name.' '.$application->last_name),
                'password' => bcrypt(str()->random(32)),
                'account_type' => AccountType::Agent,
                'status' => UserAccountStatus::Invited,
                'invited_at' => now(),
                'current_agency_id' => $agency->id,
                'meta' => [
                    'phone' => $application->mobile,
                    'city' => $application->city,
                    'company_name' => $application->company_name,
                ],
            ]
        );

        $user->forceFill([
            'account_type' => AccountType::Agent,
            'status' => UserAccountStatus::Invited,
            'invited_at' => $user->invited_at ?? now(),
            'current_agency_id' => $agency->id,
            'meta' => array_merge($user->meta ?? [], [
                'phone' => $application->mobile,
                'city' => $application->city,
                'company_name' => $application->company_name,
            ]),
        ])->save();

        return $user;
    }

    protected function resolveOrCreateAgent(AgentApplication $application, Agency $agency, User $user): Agent
    {
        return Agent::query()->updateOrCreate(
            ['user_id' => $user->id, 'agency_id' => $agency->id],
            [
                'code' => Agent::query()->where('user_id', $user->id)->value('code')
                    ?: $this->referenceGenerator->generateUnique('agents', 'code', 7),
                'commission_percent' => 0,
                'is_active' => true,
                'meta' => [
                    'city' => $application->city,
                    'company_name' => $application->company_name,
                    'mobile' => $application->mobile,
                ],
            ]
        );
    }

    protected function sendApprovalNotification(Agency $agency, AgentApplication $application, User $user): void
    {
        $platformAgency = $this->platformNotificationAgency($agency);
        $loginUrl = route('login');

        $this->notificationService->send(
            agency: $platformAgency,
            eventKey: OtaNotificationEvent::AgentApplicationApproved->value,
            payload: [
                'applicant_name' => trim($application->first_name.' '.$application->last_name),
                'company_name' => (string) $application->company_name,
                'login_email' => $user->email,
                'username' => (string) ($user->username ?? ''),
                'dashboard_url' => $loginUrl,
            ],
            fallbackSubject: 'Agent application approved',
            fallbackBody: implode("\n\n", array_filter([
                'Your agent application has been approved.',
                'Sign in email: '.$user->email,
                $user->username ? 'Username: '.$user->username : null,
                'Use the password reset link we sent separately to set your password, then sign in at: '.$loginUrl,
            ])),
            templateVariables: [
                'login_email' => $user->email,
                'username' => (string) ($user->username ?? ''),
                'dashboard_url' => $loginUrl,
            ],
            recipientContext: ['applicant_email' => $application->email],
        );
    }

    protected function platformNotificationAgency(Agency $agency): Agency
    {
        $platformAgency = Agency::query()
            ->where('slug', config('ota.default_agency_slug'))
            ->first();

        return $platformAgency ?? $agency;
    }

    protected function uniqueAgencySlug(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'agency';
        }

        $slug = $base;
        $suffix = 2;
        while (Agency::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    protected function seedDefaultMessageTemplatesForNewAgency(Agency $agency): void
    {
        try {
            $stats = $this->messageTemplateSeeder->seedAllDefaultsForAgency($agency);
            Log::info('Agency message templates seeded for new agency', [
                'agency_id' => $agency->id,
                'created' => $stats['created'],
                'skipped_existing' => $stats['skipped_existing'],
                'skipped_no_defaults' => $stats['skipped_no_defaults'],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Agency message template seeding failed for new agency', [
                'agency_id' => $agency->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
