<?php

namespace App\Services\Communication;

use App\Enums\AccountType;
use App\Enums\OtaNotificationEvent;
use App\Models\Agency;
use App\Models\User;
use App\Support\Branding\CompanyEmailProfileResolver;
use App\Support\Emails\OperationalEmailDefaults;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Universal-layout payloads for auth/security operational emails (AUTH-SECURITY-EMAIL-1, AUTH-AU3-NEW-DEVICE-SUSPICIOUS-LOGIN-1).
 */
class AuthSecurityEmailPayloadFactory
{
    public function loginSuccess(
        User $user,
        Agency $agency,
        OtaNotificationEvent $event,
        ?Request $request = null,
    ): array {
        $profile = CompanyEmailProfileResolver::resolve($agency);
        $timestamp = OperationalEmailDefaults::formatTimestamp();
        $ip = $request !== null ? (string) $request->ip() : '';
        $userAgent = $request !== null ? substr((string) $request->userAgent(), 0, 250) : '';

        return [
            'type' => $this->loginSuccessType($event),
            'subject' => OperationalEmailDefaults::portalLabel($user->account_type).' login notice',
            'title' => 'Login successful',
            'status_label' => 'Login successful',
            'status_tone' => 'info',
            'greeting_name' => (string) $user->name,
            'intro' => 'A login to your '.$this->portalLabel($user->account_type).' account was detected.',
            'company' => $profile->toArray(),
            'notes' => $this->securityNotes($timestamp, $ip, $userAgent, true),
            'cta' => $this->passwordResetCta(),
        ];
    }

    public function failedLoginAlert(
        User $user,
        Agency $agency,
        ?Request $request = null,
        bool $privilegedAdminContext = false,
    ): array {
        $profile = CompanyEmailProfileResolver::resolve($agency);
        $timestamp = OperationalEmailDefaults::formatTimestamp();
        $ip = $request !== null ? (string) $request->ip() : '';
        $userAgent = $request !== null ? substr((string) $request->userAgent(), 0, 250) : '';

        return [
            'type' => $privilegedAdminContext ? 'auth_failed_login_alert' : 'auth_failed_login_alert',
            'subject' => 'Failed sign-in attempt',
            'title' => 'Failed login attempt',
            'status_label' => 'Security alert',
            'status_tone' => 'warning',
            'greeting_name' => $privilegedAdminContext ? 'Administrator' : (string) $user->name,
            'intro' => $privilegedAdminContext
                ? 'A failed sign-in attempt was detected for a privileged account.'
                : 'A failed sign-in attempt was detected on your account.',
            'company' => $profile->toArray(),
            'notes' => array_values(array_filter(array_merge(
                $privilegedAdminContext ? [
                    'Account name: '.(string) $user->name,
                    'Account email: '.(string) $user->email,
                    'Account type: '.OperationalEmailDefaults::accountTypeLabel($user->account_type),
                    'Portal: '.OperationalEmailDefaults::portalLabel($user->account_type),
                ] : [],
                $this->securityNotes($timestamp, $ip, $userAgent, false),
            ))),
            'cta' => $this->passwordResetCta(),
        ];
    }

    public function newDeviceLogin(
        User $user,
        Agency $agency,
        ?Request $request = null,
        string $detectionReason = 'user_agent_fingerprint_changed',
    ): array {
        $profile = CompanyEmailProfileResolver::resolve($agency);
        $timestamp = OperationalEmailDefaults::formatTimestamp();
        $ip = $request !== null ? (string) $request->ip() : '';
        $userAgent = $request !== null ? substr(trim((string) $request->userAgent()), 0, 250) : '';

        return [
            'type' => 'auth_new_device_login',
            'subject' => 'New login detected on your account',
            'title' => 'New login detected',
            'status_label' => 'Security notice',
            'status_tone' => 'warning',
            'greeting_name' => (string) $user->name,
            'intro' => 'A login was detected from a new device or browser.',
            'company' => $profile->toArray(),
            'notes' => array_values(array_filter([
                'Account name: '.(string) $user->name,
                'Time: '.$timestamp,
                $ip !== '' ? 'IP address: '.$ip : null,
                $userAgent !== '' ? 'Device / browser: '.$userAgent : null,
                'If this was you, no action is needed. If not, reset your password and contact support.',
            ])),
            'cta' => $this->passwordResetCta(),
        ];
    }

    private function loginSuccessType(OtaNotificationEvent $event): string
    {
        return match ($event) {
            OtaNotificationEvent::AdminLoginSuccess,
            OtaNotificationEvent::StaffLoginSuccess => 'auth_privileged_login_success',
            OtaNotificationEvent::AgentLoginSuccess => 'auth_agent_login_success',
            default => 'auth_login_success',
        };
    }

    private function portalLabel(?AccountType $accountType): string
    {
        return match ($accountType) {
            AccountType::Customer => 'customer portal',
            AccountType::PlatformAdmin, AccountType::AgencyAdmin => 'admin portal',
            AccountType::Staff => 'staff portal',
            AccountType::Agent, AccountType::AgentStaff => 'agent portal',
            default => 'account',
        };
    }

    /**
     * @return list<string>
     */
    private function securityNotes(string $timestamp, string $ip, string $userAgent, bool $success): array
    {
        $notes = ['Time: '.$timestamp];

        if ($ip !== '') {
            $notes[] = 'IP address: '.$ip;
        }

        if ($userAgent !== '') {
            $notes[] = 'Device / browser: '.$userAgent;
        }

        $notes[] = $success
            ? 'If this was not you, reset your password and contact support.'
            : 'If this was not you, reset your password immediately and contact support.';

        return $notes;
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    private function passwordResetCta(): array
    {
        if (! Route::has('password.request')) {
            return [];
        }

        return [[
            'label' => 'Reset password',
            'url' => route('password.request', absolute: true),
        ]];
    }
}
