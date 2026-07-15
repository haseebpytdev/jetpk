<?php

namespace App\Services\Communication;

use App\Enums\AccountType;
use App\Enums\OtaNotificationEvent;
use App\Enums\UserAccountStatus;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Emails\OperationalEmailDefaults;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Production-safe auth/security login notifications (AUTH-SECURITY-EMAIL-1, AUTH-AU3-NEW-DEVICE-SUSPICIOUS-LOGIN-1).
 *
 * Success emails go only to the authenticated user after credentials verify.
 * Failed-login emails go only to known active users, are thresholded, and use cooldown keys.
 * New-device emails use prior auth.login_success audit metadata (IP + truncated user agent) with conservative detection.
 */
class AuthSecurityEmailNotificationService
{
    public function __construct(
        protected OtaNotificationService $notificationService,
        protected AuthSecurityEmailPayloadFactory $payloadFactory,
    ) {}

    public function notifyLoginSuccess(LoginRequest $request): void
    {
        $user = $request->user();
        if ($user === null || ! $this->userIsActive($user)) {
            return;
        }

        $agency = $this->resolveAgencyForUser($user);
        if ($agency === null) {
            $this->logSkipped('login_success', $user, 'missing_agency');

            return;
        }

        $event = $this->loginSuccessEvent($user->account_type);
        if ($event === null || ! $this->loginSuccessEnabled($event)) {
            $this->logSkipped('login_success', $user, 'config_disabled', $event?->value);

            return;
        }

        if (! $this->loginSuccessCooldownAllows($user)) {
            $this->logSkipped('login_success', $user, 'cooldown_active', $event->value);

            return;
        }

        $defaults = OperationalEmailDefaults::forEvent($event->value);
        $templateVariables = OperationalEmailDefaults::authVariablesFromUser($agency, $user, $request);
        $universalPayload = $this->payloadFactory->loginSuccess($user, $agency, $event, $request);

        $this->notificationService->send(
            agency: $agency,
            eventKey: $event->value,
            payload: [
                'account_type' => $user->account_type?->value,
                'timestamp' => now()->toIso8601String(),
                'ip' => (string) $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 250),
                'universal_email' => $universalPayload,
            ],
            actor: $user,
            fallbackSubject: $defaults['subject'] ?? 'Security login notice',
            fallbackBody: $defaults['body'] ?? 'A login to your account was detected.',
            templateVariables: $templateVariables,
            recipientContext: [
                'logged_in_user_email' => $user->email,
            ],
        );

        $this->markLoginSuccessEmailSent($user);
    }

    public function notifyNewDeviceLogin(LoginRequest $request): void
    {
        $user = $request->user();
        if ($user === null || ! $this->userIsActive($user)) {
            return;
        }

        $currentIp = (string) $request->ip();
        $currentUserAgent = $this->safeUserAgent($request);
        $currentFingerprint = $this->userAgentFingerprint($currentUserAgent);
        $priorLogin = $this->findPriorSuccessfulLoginAudit($user);

        if ($priorLogin === null) {
            $this->recordLoginSuccessAudit($user, $request);
            $this->logNewDeviceSkipped($user, 'no_prior_login', $currentIp, $currentUserAgent);

            return;
        }

        if (! $this->isNewDeviceLogin($priorLogin, $currentFingerprint)) {
            $this->recordLoginSuccessAudit($user, $request);
            $this->logNewDeviceSkipped($user, 'known_device', $currentIp, $currentUserAgent);

            return;
        }

        $detectionReason = $this->newDeviceDetectionReason($priorLogin, $currentIp, $currentFingerprint);

        if (! (bool) config('ota.notify_auth_new_device_login', true)) {
            $this->recordLoginSuccessAudit($user, $request);
            $this->logNewDeviceSkipped($user, 'config_disabled', $currentIp, $currentUserAgent, $detectionReason);

            return;
        }

        $agency = $this->resolveAgencyForUser($user);
        if ($agency === null) {
            $this->recordLoginSuccessAudit($user, $request);
            $this->logNewDeviceSkipped($user, 'missing_agency', $currentIp, $currentUserAgent, $detectionReason);

            return;
        }

        if (! $this->newDeviceEmailCooldownAllows($user, $currentFingerprint)) {
            $this->recordLoginSuccessAudit($user, $request);
            $this->logNewDeviceSkipped($user, 'cooldown_active', $currentIp, $currentUserAgent, $detectionReason);

            return;
        }

        $event = OtaNotificationEvent::AuthNewDeviceLogin;
        $defaults = OperationalEmailDefaults::forEvent($event->value);
        $templateVariables = OperationalEmailDefaults::authVariablesFromUser($agency, $user, $request);
        $universalPayload = $this->payloadFactory->newDeviceLogin($user, $agency, $request, $detectionReason);

        $this->notificationService->send(
            agency: $agency,
            eventKey: $event->value,
            payload: [
                'account_type' => $user->account_type?->value,
                'timestamp' => now()->toIso8601String(),
                'ip' => $currentIp,
                'user_agent' => $currentUserAgent,
                'notification_type' => 'auth_new_device_login',
                'detection_reason' => $detectionReason,
                'prior_login_seen' => true,
                'cooldown_minutes' => max(1, (int) config('ota.auth_new_device_email_cooldown_minutes', 60)),
                'universal_email' => $universalPayload,
            ],
            actor: $user,
            fallbackSubject: $defaults['subject'] ?? 'New login detected',
            fallbackBody: $defaults['body'] ?? 'A login was detected from a new device or browser.',
            templateVariables: $templateVariables,
            recipientContext: [
                'logged_in_user_email' => $user->email,
            ],
        );

        $this->recordNewDeviceLoginAudit($user, $request, $detectionReason);
        $this->recordLoginSuccessAudit($user, $request);
        $this->markNewDeviceEmailSent($user, $currentFingerprint);

        Log::info('auth.security_email.new_device.sent', [
            'notification_type' => 'auth_new_device_login',
            'user_id' => $user->id,
            'account_type' => $user->account_type?->value,
            'recipient_email' => $user->email,
            'ip_address' => $currentIp,
            'user_agent_safe' => $currentUserAgent,
            'detection_reason' => $detectionReason,
            'prior_login_seen' => true,
            'cooldown_minutes' => max(1, (int) config('ota.auth_new_device_email_cooldown_minutes', 60)),
        ]);
    }

    public function notifyFailedLogin(LoginRequest $request, string $login, string $field): void
    {
        $user = $this->resolveUserByLogin($login, $field);
        if ($user === null || ! $this->userIsActive($user)) {
            return;
        }

        if (! $this->failedLoginAttemptThresholdMet($request)) {
            return;
        }

        $agency = $this->resolveAgencyForUser($user);
        if ($agency === null) {
            $this->logSkipped('failed_login', $user, 'missing_agency');

            return;
        }

        if (! $this->failedLoginEmailCooldownAllows($user)) {
            $this->logSkipped('failed_login', $user, 'cooldown_active');

            return;
        }

        if ($this->shouldNotifyPrivilegedAdminFailure($user)) {
            $this->recordPrivilegedAdminFailureAudit($user, $field, $request);
            $this->sendPrivilegedAdminFailureAlert($user, $agency, $request);
        }

        if ($this->shouldNotifyUserFailedLogin($user)) {
            $this->recordUserFailureAudit($user, $field, $request);
            $this->sendUserFailedLoginAlert($user, $agency, $request);
        }

        $this->markFailedLoginEmailSent($user);
    }

    public function resolveAgencyForUser(User $user): ?Agency
    {
        $user->loadMissing('currentAgency');
        if ($user->currentAgency !== null) {
            return $user->currentAgency;
        }

        $slug = trim((string) config('ota.default_agency_slug', ''));
        if ($slug === '') {
            return null;
        }

        return Agency::query()->where('slug', $slug)->first();
    }

    protected function resolveUserByLogin(string $login, string $field): ?User
    {
        $normalized = strtolower(trim($login));

        return User::query()
            ->when(
                $field === 'email',
                fn ($query) => $query->whereRaw('LOWER(email) = ?', [$normalized]),
                fn ($query) => $query->whereRaw('LOWER(username) = ?', [$normalized]),
            )
            ->first();
    }

    protected function loginSuccessEvent(?AccountType $accountType): ?OtaNotificationEvent
    {
        return match ($accountType) {
            AccountType::Customer => OtaNotificationEvent::CustomerLoginSuccess,
            AccountType::AgencyAdmin, AccountType::PlatformAdmin => OtaNotificationEvent::AdminLoginSuccess,
            AccountType::Staff => OtaNotificationEvent::StaffLoginSuccess,
            AccountType::Agent, AccountType::AgentStaff => OtaNotificationEvent::AgentLoginSuccess,
            default => null,
        };
    }

    protected function loginSuccessEnabled(OtaNotificationEvent $event): bool
    {
        return match ($event) {
            OtaNotificationEvent::CustomerLoginSuccess => (bool) config('ota.notify_customer_login'),
            OtaNotificationEvent::AdminLoginSuccess => (bool) config('ota.notify_admin_login'),
            OtaNotificationEvent::StaffLoginSuccess => (bool) config('ota.notify_staff_login'),
            OtaNotificationEvent::AgentLoginSuccess => (bool) config('ota.notify_agent_login'),
            default => false,
        };
    }

    protected function shouldNotifyPrivilegedAdminFailure(User $user): bool
    {
        if (! (bool) config('ota.notify_failed_admin_login')) {
            return false;
        }

        return in_array($user->account_type, [AccountType::AgencyAdmin, AccountType::PlatformAdmin], true);
    }

    protected function shouldNotifyUserFailedLogin(User $user): bool
    {
        if (! (bool) config('ota.notify_failed_login')) {
            return false;
        }

        return in_array($user->account_type, [
            AccountType::Customer,
            AccountType::Staff,
            AccountType::Agent,
            AccountType::AgentStaff,
        ], true);
    }

    protected function failedLoginAttemptThresholdMet(LoginRequest $request): bool
    {
        $attempts = RateLimiter::attempts($request->throttleKey());
        $threshold = max(1, (int) config('ota.auth_failed_login_email_threshold', 3));
        $lockoutThreshold = 5;

        return $attempts >= $threshold || $attempts >= $lockoutThreshold;
    }

    protected function loginSuccessCooldownAllows(User $user): bool
    {
        $minutes = max(0, (int) config('ota.auth_login_success_email_cooldown_minutes', 15));
        if ($minutes === 0) {
            return true;
        }

        return ! RateLimiter::tooManyAttempts($this->loginSuccessCooldownKey($user), 1);
    }

    protected function failedLoginEmailCooldownAllows(User $user): bool
    {
        $minutes = max(1, (int) config('ota.auth_failed_login_email_cooldown_minutes', 60));

        return ! RateLimiter::tooManyAttempts($this->failedLoginEmailCooldownKey($user), 1);
    }

    protected function markLoginSuccessEmailSent(User $user): void
    {
        $minutes = max(0, (int) config('ota.auth_login_success_email_cooldown_minutes', 15));
        if ($minutes === 0) {
            return;
        }

        RateLimiter::hit($this->loginSuccessCooldownKey($user), $minutes * 60);
    }

    protected function markFailedLoginEmailSent(User $user): void
    {
        $minutes = max(1, (int) config('ota.auth_failed_login_email_cooldown_minutes', 60));
        RateLimiter::hit($this->failedLoginEmailCooldownKey($user), $minutes * 60);
    }

    protected function sendPrivilegedAdminFailureAlert(User $user, Agency $agency, Request $request): void
    {
        $event = OtaNotificationEvent::LoginFailedSensitive;
        $defaults = OperationalEmailDefaults::forEvent($event->value);
        $templateVariables = OperationalEmailDefaults::authVariablesFromUser($agency, $user, $request);
        $universalPayload = $this->payloadFactory->failedLoginAlert($user, $agency, $request, true);

        $this->notificationService->send(
            agency: $agency,
            eventKey: $event->value,
            actor: $user,
            payload: [
                'account_type' => $user->account_type?->value,
                'timestamp' => now()->toIso8601String(),
                'ip' => (string) $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 250),
                'universal_email' => $universalPayload,
            ],
            fallbackSubject: $defaults['subject'] ?? 'Failed admin login attempt',
            fallbackBody: $defaults['body'] ?? 'A failed login attempt was recorded for a privileged account.',
            templateVariables: $templateVariables,
        );
    }

    protected function sendUserFailedLoginAlert(User $user, Agency $agency, Request $request): void
    {
        $event = OtaNotificationEvent::LoginFailedAlert;
        $defaults = OperationalEmailDefaults::forEvent($event->value);
        $templateVariables = OperationalEmailDefaults::authVariablesFromUser($agency, $user, $request);
        $universalPayload = $this->payloadFactory->failedLoginAlert($user, $agency, $request, false);

        $this->notificationService->send(
            agency: $agency,
            eventKey: $event->value,
            actor: $user,
            payload: [
                'account_type' => $user->account_type?->value,
                'timestamp' => now()->toIso8601String(),
                'ip' => (string) $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 250),
                'universal_email' => $universalPayload,
            ],
            fallbackSubject: $defaults['subject'] ?? 'Failed sign-in attempt',
            fallbackBody: $defaults['body'] ?? 'A failed sign-in attempt was detected on your account.',
            templateVariables: $templateVariables,
            recipientContext: [
                'logged_in_user_email' => $user->email,
            ],
        );
    }

    protected function recordPrivilegedAdminFailureAudit(User $user, string $field, Request $request): void
    {
        AuditLog::query()->create([
            'agency_id' => $user->current_agency_id,
            'user_id' => $user->id,
            'action' => 'auth.admin_login_failed',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'properties' => [
                'account_type' => $user->account_type?->value,
                'login_field' => $field,
            ],
            'ip_address' => (string) $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 250),
        ]);
    }

    protected function recordUserFailureAudit(User $user, string $field, Request $request): void
    {
        AuditLog::query()->create([
            'agency_id' => $user->current_agency_id,
            'user_id' => $user->id,
            'action' => 'auth.login_failed',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'properties' => [
                'account_type' => $user->account_type?->value,
                'login_field' => $field,
            ],
            'ip_address' => (string) $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 250),
        ]);
    }

    protected function findPriorSuccessfulLoginAudit(User $user): ?AuditLog
    {
        return AuditLog::query()
            ->where('user_id', $user->id)
            ->where('action', 'auth.login_success')
            ->orderByDesc('id')
            ->first();
    }

    protected function isNewDeviceLogin(AuditLog $priorLogin, string $currentFingerprint): bool
    {
        $priorFingerprint = $this->userAgentFingerprint((string) ($priorLogin->user_agent ?? ''));

        return $priorFingerprint !== $currentFingerprint;
    }

    protected function newDeviceDetectionReason(AuditLog $priorLogin, string $currentIp, string $currentFingerprint): string
    {
        $priorIp = (string) ($priorLogin->ip_address ?? '');
        $priorFingerprint = $this->userAgentFingerprint((string) ($priorLogin->user_agent ?? ''));
        $ipChanged = $priorIp !== '' && $currentIp !== '' && $priorIp !== $currentIp;
        $userAgentChanged = $priorFingerprint !== $currentFingerprint;

        if ($ipChanged && $userAgentChanged) {
            return 'ip_and_user_agent_changed';
        }

        if ($userAgentChanged) {
            return 'user_agent_fingerprint_changed';
        }

        return 'unknown';
    }

    protected function recordLoginSuccessAudit(User $user, Request $request): void
    {
        AuditLog::query()->create([
            'agency_id' => $user->current_agency_id,
            'user_id' => $user->id,
            'action' => 'auth.login_success',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'properties' => [
                'account_type' => $user->account_type?->value,
            ],
            'ip_address' => (string) $request->ip(),
            'user_agent' => $this->safeUserAgent($request),
        ]);
    }

    protected function recordNewDeviceLoginAudit(User $user, Request $request, string $detectionReason): void
    {
        AuditLog::query()->create([
            'agency_id' => $user->current_agency_id,
            'user_id' => $user->id,
            'action' => 'auth.new_device_login',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'properties' => [
                'account_type' => $user->account_type?->value,
                'notification_type' => 'auth_new_device_login',
                'detection_reason' => $detectionReason,
                'prior_login_seen' => true,
                'cooldown_minutes' => max(1, (int) config('ota.auth_new_device_email_cooldown_minutes', 60)),
            ],
            'ip_address' => (string) $request->ip(),
            'user_agent' => $this->safeUserAgent($request),
        ]);
    }

    protected function safeUserAgent(Request $request): string
    {
        return substr(trim((string) $request->userAgent()), 0, 250);
    }

    protected function userAgentFingerprint(string $userAgent): string
    {
        return strtolower(preg_replace('/\s+/', ' ', trim($userAgent)) ?? '');
    }

    protected function newDeviceEmailCooldownAllows(User $user, string $fingerprint): bool
    {
        return ! RateLimiter::tooManyAttempts($this->newDeviceEmailCooldownKey($user, $fingerprint), 1);
    }

    protected function markNewDeviceEmailSent(User $user, string $fingerprint): void
    {
        $minutes = max(1, (int) config('ota.auth_new_device_email_cooldown_minutes', 60));
        RateLimiter::hit($this->newDeviceEmailCooldownKey($user, $fingerprint), $minutes * 60);
    }

    protected function newDeviceEmailCooldownKey(User $user, string $fingerprint): string
    {
        return 'auth-new-device-email|user:'.$user->id.'|fp:'.substr(hash('sha256', $fingerprint), 0, 16);
    }

    protected function logNewDeviceSkipped(
        User $user,
        string $reason,
        string $ip,
        string $userAgent,
        ?string $detectionReason = null,
    ): void {
        Log::info('auth.security_email.new_device.skipped', [
            'notification_type' => 'auth_new_device_login',
            'user_id' => $user->id,
            'account_type' => $user->account_type?->value,
            'recipient_email' => $user->email,
            'reason' => $reason,
            'ip_address' => $ip,
            'user_agent_safe' => $userAgent,
            'detection_reason' => $detectionReason,
            'prior_login_seen' => $reason !== 'no_prior_login',
            'cooldown_minutes' => max(1, (int) config('ota.auth_new_device_email_cooldown_minutes', 60)),
        ]);
    }

    protected function userIsActive(User $user): bool
    {
        return ! $user->isSuspended() && $user->status === UserAccountStatus::Active;
    }

    protected function loginSuccessCooldownKey(User $user): string
    {
        return 'auth-login-success-email|user:'.$user->id;
    }

    protected function failedLoginEmailCooldownKey(User $user): string
    {
        return 'auth-failed-login-email|user:'.$user->id;
    }

    protected function logSkipped(string $kind, User $user, string $reason, ?string $eventKey = null): void
    {
        Log::info('auth.security_email.skipped', [
            'kind' => $kind,
            'event' => $eventKey,
            'user_id' => $user->id,
            'account_type' => $user->account_type?->value,
            'recipient_email' => $user->email,
            'reason' => $reason,
        ]);
    }
}
