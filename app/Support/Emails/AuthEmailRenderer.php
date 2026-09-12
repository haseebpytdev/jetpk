<?php

namespace App\Support\Emails;

use App\Models\Agency;
use App\Models\User;
use App\Support\Branding\CompanyEmailProfileResolver;
use App\Support\Url\PublicActionUrl;
use Illuminate\Support\Facades\Route;

/**
 * Live send renderer for auth/registration emails (I8).
 */
class AuthEmailRenderer
{
    public function customerWelcome(User $user, string $brandName): CustomerFacingEmailRendered
    {
        $user->loadMissing('currentAgency.agencySetting');
        $profile = CompanyEmailProfileResolver::resolve($user->currentAgency);
        $name = trim((string) ($user->name ?? 'Customer')) ?: 'Customer';
        $cta = $this->loginCta();

        if ($this->usesJetpkEmailPackage()) {
            return $this->renderJetpkEmail(
                type: 'account_created',
                extra: [
                    'title' => 'Welcome to '.$brandName,
                    'intro' => sprintf(
                        'Hello %s, your customer account at %s was created successfully. Please verify your email address using the verification link we sent separately. The link expires in 24 hours.',
                        $name,
                        $brandName,
                    ),
                ],
                plainLines: [
                    'Hello '.$name.',',
                    '',
                    'Your customer account at '.$brandName.' was created successfully.',
                    '',
                    'Please verify your email address using the verification link we sent separately.',
                    'The link expires in 24 hours.',
                    '',
                    'If you did not create this account, please contact support.',
                ],
                agency: $user->currentAgency,
                runtimeVariables: [
                    'customer_name' => $name,
                    'customer_email' => (string) $user->email,
                    'user_name' => $name,
                    'recipient_role' => 'customer',
                    'login_url' => JetpkEmailBrandingResolver::publicAssetUrl($cta['url'] ?? 'https://jetpakistan.pk/login') ?? 'https://jetpakistan.pk/login',
                ],
            );
        }

        return $this->render(
            agency: $user->currentAgency,
            headline: 'Welcome to '.$brandName,
            intro: sprintf(
                'Hello %s, your customer account at %s was created successfully. Please verify your email address using the verification link we sent separately. The link expires in 24 hours.',
                $name,
                $brandName,
            ),
            details: [
                ['label' => 'Name', 'value' => $name],
                ['label' => 'Email', 'value' => (string) $user->email],
            ],
            ctaUrl: $cta['url'] ?? null,
            ctaLabel: $cta['label'] ?? null,
            footerDisclaimer: 'Please keep this email for your records.',
            statusBannerLabel: 'Welcome',
            statusBannerTone: 'success',
            nextSteps: [
                'Verify your email using the separate verification link.',
                'Sign in after verification to manage bookings.',
            ],
            plainBody: $this->plainLines([
                'Hello '.$name.',',
                '',
                'Your customer account at '.$brandName.' was created successfully.',
                '',
                'Please verify your email address using the verification link we sent separately.',
                'The link expires in 24 hours.',
                '',
                'If you did not create this account, please contact support.',
            ]),
        );
    }

    public function adminNewCustomerSignup(User $user, string $phone): CustomerFacingEmailRendered
    {
        $user->loadMissing('currentAgency.agencySetting');
        $profile = CompanyEmailProfileResolver::resolve($user->currentAgency);
        $name = trim((string) ($user->name ?? 'Customer')) ?: 'Customer';

        if ($this->usesJetpkEmailPackage()) {
            $user->loadMissing('currentAgency.agencySetting');
            $name = trim((string) ($user->name ?? 'Customer')) ?: 'Customer';
            $result = app(JetpkEmailEventRenderer::class)->render(
                eventKey: 'notification',
                agency: $user->currentAgency,
                runtimeVariables: [
                    'customer_name' => $name,
                    'customer_email' => ModernEmailLayout::maskEmail((string) $user->email),
                    'recipient_role' => 'admin',
                ],
                payload: [
                    'shell_notice' => true,
                    'title' => 'New customer signup',
                    'intro' => 'A new customer signed up on your platform.',
                    'detail_rows' => [
                        ['label' => 'Name', 'value' => $name],
                        ['label' => 'Email', 'value' => ModernEmailLayout::maskEmail((string) $user->email)],
                        ['label' => 'Contact / mobile', 'value' => ModernEmailLayout::maskPhone($phone)],
                        ['label' => 'Signed up at', 'value' => now()->toDateTimeString()],
                    ],
                    'details_title' => 'Customer details',
                    'next_steps_text' => "What to do next\n- Review the new customer account in the admin panel if follow-up is required.",
                ],
            );

            return new CustomerFacingEmailRendered(
                html: $result->html,
                plainBody: $this->plainLines([
                    'A new customer signed up.',
                    '',
                    'Name: '.$name,
                    'Email: '.$user->email,
                    'Contact / mobile: '.$phone,
                    'Signed up at: '.now()->toDateTimeString(),
                ]),
                profile: CompanyEmailProfileResolver::resolveForPlatform(),
            );
        }

        return $this->render(
            agency: $user->currentAgency,
            headline: 'New customer signup',
            intro: 'A new customer signed up on your platform.',
            details: [
                ['label' => 'Name', 'value' => $name],
                ['label' => 'Email', 'value' => ModernEmailLayout::maskEmail((string) $user->email)],
                ['label' => 'Contact / mobile', 'value' => ModernEmailLayout::maskPhone($phone)],
                ['label' => 'Signed up at', 'value' => now()->toDateTimeString()],
            ],
            ctaUrl: null,
            ctaLabel: null,
            emailMode: ModernEmailLayout::MODE_OPS,
            statusBannerLabel: 'New signup',
            statusBannerTone: 'info',
            actionCardTitle: 'What to do next',
            actionCardBody: 'Review the new customer account in the admin panel if follow-up is required.',
            footerDisclaimer: 'Generated by OTA system. No passwords or verification tokens are included.',
            plainBody: $this->plainLines([
                'A new customer signed up.',
                '',
                'Name: '.$name,
                'Email: '.$user->email,
                'Contact / mobile: '.$phone,
                'Signed up at: '.now()->toDateTimeString(),
            ]),
        );
    }

    public function loginOtp(User $user, string $brandName, string $otpCode, int $expiryMinutes): CustomerFacingEmailRendered
    {
        if ($this->usesJetpkEmailPackage()) {
            $user->loadMissing('currentAgency.agencySetting');

            return $this->renderJetpkEmail(
                type: 'otp',
                extra: [
                    'security' => [
                        'otp' => $otpCode,
                        'expiry_minutes' => $expiryMinutes,
                        'context' => 'Web sign-in',
                    ],
                    'otpCode' => $otpCode,
                    'otpExpiryMinutes' => $expiryMinutes,
                ],
                plainLines: [
                    'Hello '.(trim((string) ($user->name ?? 'Customer')) ?: 'Customer').',',
                    '',
                    'Your '.$brandName.' login verification code is: '.$otpCode,
                    '',
                    'This code expires in '.$expiryMinutes.' minutes.',
                    '',
                    'Never share this code with anyone.',
                ],
                agency: $user->currentAgency,
            );
        }

        $user->loadMissing('currentAgency.agencySetting');
        $name = trim((string) ($user->name ?? 'Customer')) ?: 'Customer';

        return $this->render(
            agency: $user->currentAgency,
            headline: 'Your login verification code',
            intro: sprintf(
                'Hello %s, use this one-time code to complete your sign-in to %s. The code expires in %d minutes.',
                $name,
                $brandName,
                $expiryMinutes,
            ),
            details: [
                ['label' => 'Verification code', 'value' => $otpCode],
                ['label' => 'Expires in', 'value' => $expiryMinutes.' minutes'],
            ],
            ctaUrl: null,
            ctaLabel: null,
            footerDisclaimer: 'Never share this code with anyone. '.$brandName.' staff will never ask for your OTP.',
            statusBannerLabel: 'Login verification',
            statusBannerTone: 'info',
            nextSteps: [
                'Enter the code on the verification screen to finish signing in.',
                'If you did not attempt to sign in, change your password and contact support.',
            ],
            plainBody: $this->plainLines([
                'Hello '.$name.',',
                '',
                'Your '.$brandName.' login verification code is: '.$otpCode,
                '',
                'This code expires in '.$expiryMinutes.' minutes.',
                '',
                'Never share this code with anyone.',
                'If you did not attempt to sign in, change your password and contact support.',
            ]),
        );
    }

    public function loginSecurity(array $payload): CustomerFacingEmailRendered
    {
        $agency = null;
        $headline = (string) ($payload['title'] ?? 'Login successful');
        $status = (string) ($payload['status_label'] ?? 'Security notice');
        $name = trim((string) ($payload['greeting_name'] ?? 'there')) ?: 'there';
        $intro = (string) ($payload['intro'] ?? 'A login to your account was detected.');
        $notes = is_array($payload['notes'] ?? null) ? $payload['notes'] : [];
        $cta = is_array($payload['cta'][0] ?? null) ? $payload['cta'][0] : [];
        $ctaUrl = JetpkEmailBrandingResolver::publicForgotPasswordUrl();
        if (is_string($cta['url'] ?? null) && trim((string) $cta['url']) !== '') {
            $ctaUrl = JetpkEmailBrandingResolver::publicAssetUrl((string) $cta['url']) ?: $ctaUrl;
        }
        $ctaLabel = is_string($cta['label'] ?? null) && trim((string) $cta['label']) !== ''
            ? (string) $cta['label']
            : 'Reset password';

        $details = [];
        foreach ($notes as $note) {
            $line = trim((string) $note);
            if ($line === '') {
                continue;
            }
            if (str_contains($line, ':')) {
                [$label, $value] = array_map('trim', explode(':', $line, 2));
                if ($label !== '' && $value !== '') {
                    $details[] = ['label' => $label, 'value' => $value];

                    continue;
                }
            }
            $details[] = ['label' => 'Notice', 'value' => $line];
        }

        $plain = $this->plainLines(array_merge(
            ['Hello '.$name.',', '', $intro, ''],
            array_map(static fn (string $note): string => trim((string) $note), $notes),
            ['', 'Reset password: '.$ctaUrl],
        ));

        if ($this->usesJetpkEmailPackage()) {
            $security = $this->securityFactsFromDetails($details);
            $eventKey = $this->loginSecurityEventKey($payload);

            $result = app(JetpkEmailEventRenderer::class)->render(
                eventKey: $eventKey,
                agency: $agency,
                dbTemplate: null,
                runtimeVariables: [
                    'customer_name' => $name,
                    'user_name' => $name,
                    'login_time' => $security['login_time'] ?? '',
                    'device' => $security['device'] ?? '',
                    'location' => $security['ip'] ?? '',
                    'reset_url' => $ctaUrl,
                    'recipient_role' => $this->recipientRoleForLoginEvent($eventKey),
                ],
                payload: [
                    'title' => $headline,
                    'intro' => $intro,
                    'security' => $security,
                ],
            );

            return new CustomerFacingEmailRendered(
                html: $result->html,
                plainBody: $result->plainBody !== '' ? $result->plainBody : $plain,
                profile: CompanyEmailProfileResolver::resolveForPlatform(),
            );
        }

        return $this->render(
            agency: $agency,
            headline: $headline,
            intro: 'Hello '.$name.', '.$intro,
            details: $details,
            ctaUrl: $ctaUrl,
            ctaLabel: $ctaLabel,
            footerDisclaimer: 'If you did not perform this sign-in, reset your password and contact support immediately.',
            emailMode: ModernEmailLayout::MODE_OPS,
            statusBannerLabel: $status,
            statusBannerTone: (string) ($payload['status_tone'] ?? 'info'),
            nextSteps: [
                'Review the time, IP address, and device shown above.',
                'Reset your password if this sign-in was not you.',
            ],
            plainBody: $plain,
            detailsTitle: 'Sign-in details',
        );
    }

    /**
     * @return array{url: string, label: string}|null
     */
    protected function loginCta(): ?array
    {
        if (Route::has('login')) {
            return [
                'url' => PublicActionUrl::route('login', absolute: true),
                'label' => 'Sign in to your account',
            ];
        }

        return null;
    }

    protected function render(
        ?Agency $agency,
        string $headline,
        string $intro,
        array $details,
        ?string $ctaUrl,
        ?string $ctaLabel,
        string $footerDisclaimer,
        string $plainBody,
        string $emailMode = ModernEmailLayout::MODE_CUSTOMER,
        ?string $statusBannerLabel = null,
        string $statusBannerTone = 'info',
        ?string $actionCardTitle = null,
        ?string $actionCardBody = null,
        array $nextSteps = [],
        ?string $detailsTitle = null,
    ): CustomerFacingEmailRendered {
        $detailRows = [];
        foreach ($details as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));
            if ($label === '' || $value === '') {
                continue;
            }
            $detailRows[] = ['label' => $label, 'value' => $value];
        }
        $nextStepsText = $nextSteps !== []
            ? "Next steps\n".implode("\n", array_map(static fn ($step): string => '- '.$step, $nextSteps))
            : '';
        $result = app(JetpkEmailEventRenderer::class)->render(
            eventKey: 'notification',
            agency: $agency,
            runtimeVariables: [
                'recipient_role' => $emailMode === ModernEmailLayout::MODE_OPS ? 'admin' : 'customer',
                'reset_url' => (string) ($ctaUrl ?? ''),
                'login_url' => (string) ($ctaUrl ?? ''),
            ],
            payload: [
                'shell_notice' => true,
                'title' => $headline,
                'intro' => $intro,
                'detail_rows' => $detailRows,
                'details_title' => $detailsTitle ?? 'Details',
                'cta_url_override' => $ctaUrl,
                'cta_label_override' => $ctaLabel,
                'next_steps_text' => $nextStepsText,
                'extra_html' => $actionCardBody ?? '',
            ],
        );

        return new CustomerFacingEmailRendered(
            html: $result->html,
            plainBody: $plainBody,
            profile: CompanyEmailProfileResolver::resolve($agency),
        );
    }

    /**
     * @param  list<array{label: string, value: string}>  $details
     * @return array{login_time?: string, device?: string, ip?: string, location?: string}
     */
    protected function securityFactsFromDetails(array $details): array
    {
        $facts = [];
        foreach ($details as $row) {
            $label = strtolower(trim((string) ($row['label'] ?? '')));
            $value = trim((string) ($row['value'] ?? ''));
            if ($value === '') {
                continue;
            }
            if (str_contains($label, 'time')) {
                $facts['login_time'] = $value;
            } elseif (str_contains($label, 'device') || str_contains($label, 'browser')) {
                $facts['device'] = $value;
            } elseif (str_contains($label, 'ip')) {
                $facts['ip'] = $value;
            } elseif (str_contains($label, 'location')) {
                $facts['location'] = $value;
            }
        }

        return $facts;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function loginSecurityEventKey(array $payload): string
    {
        $event = trim((string) ($payload['event'] ?? ''));
        if ($event !== '' && JetpkEmailEventContentRegistry::find($event) !== null) {
            return $event;
        }

        $type = strtolower(trim((string) ($payload['type'] ?? '')));

        return match ($type) {
            'auth_agent_login_success' => 'agent_login_success',
            'auth_login_success' => 'customer_login_success',
            'auth_new_device_login' => 'auth_new_device_login',
            'auth_failed_login_alert' => 'login_failed_alert',
            default => 'admin_login_success',
        };
    }

    protected function recipientRoleForLoginEvent(string $eventKey): string
    {
        return match ($eventKey) {
            'staff_login_success' => 'staff',
            'agent_login_success' => 'agent',
            'customer_login_success' => 'customer',
            'login_failed_alert' => 'customer',
            default => 'admin',
        };
    }

    protected function usesJetpkEmailPackage(): bool
    {
        if ((string) config('ota_client.slug', '') === 'jetpk') {
            return true;
        }

        if (function_exists('ota_single_client_root_slug') && ota_single_client_root_slug() === 'jetpk') {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @param  list<string>  $plainLines
     * @param  array<string, mixed>  $runtimeVariables
     */
    protected function renderJetpkEmail(
        string $type,
        array $extra,
        array $plainLines,
        ?Agency $agency = null,
        array $runtimeVariables = [],
    ): CustomerFacingEmailRendered {
        $eventKey = JetpkEmailViewResolver::eventKeyForType($type);
        if ($eventKey === null) {
            throw new \RuntimeException('JetPK email event missing for type: '.$type);
        }

        $result = app(JetpkEmailEventRenderer::class)->render(
            eventKey: $eventKey,
            agency: $agency,
            dbTemplate: null,
            runtimeVariables: $runtimeVariables,
            payload: $extra,
        );

        return new CustomerFacingEmailRendered(
            html: $result->html,
            plainBody: $this->plainLines($plainLines),
            profile: CompanyEmailProfileResolver::resolveForPlatform(),
        );
    }

    /**
     * @param  list<string>  $lines
     */
    protected function plainLines(array $lines): string
    {
        return implode("\n", $lines);
    }
}
