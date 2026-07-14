<?php

namespace App\Support\Emails;

use App\Models\Agency;
use App\Models\AgencyMessageTemplate;
use App\Support\Branding\CompanyEmailProfile;
use App\Support\Branding\CompanyEmailProfileResolver;
use Illuminate\Support\Facades\View;

/**
 * Live send renderer for communication settings SMTP test email (I5; settings_test_email only).
 */
class SettingsTestEmailRenderer
{
    public const EVENT = 'settings_test_email';

    public function __construct(
        protected EmailTemplateStringRenderer $stringRenderer,
    ) {}

    public function render(Agency $agency): SettingsTestEmailRendered
    {
        $profile = CompanyEmailProfileResolver::resolve($agency);
        $variables = EmailBaseVariables::merge($agency, null, $this->variables($profile));
        $dbTemplate = $this->loadDbTemplate($agency);

        $rawSubject = filled($dbTemplate?->subject)
            ? (string) $dbTemplate->subject
            : 'Test email from {{ brand_name }}';
        $rawBody = filled($dbTemplate?->body)
            ? (string) $dbTemplate->body
            : $this->defaultBody();

        $context = ['event_key' => self::EVENT, 'template_key' => 'settings_test_email'];
        $subject = $this->stringRenderer->render($rawSubject, $variables, $context)->output;
        $innerBody = $this->stringRenderer->render($rawBody, $variables, $context)->output;
        $innerHtml = nl2br(strip_tags($innerBody), false);

        $html = View::make('emails.layouts.modern', array_merge(
            ['companyEmailProfile' => $profile],
            ModernEmailLayout::viewData([
                'emailMode' => ModernEmailLayout::MODE_OPS,
                'headline' => 'Communication settings test',
                'intro' => 'This is a safe diagnostic email from your communication settings. It confirms that outbound email can be delivered.',
                'statusBannerLabel' => 'Settings test',
                'statusBannerTone' => 'neutral',
                'contentHtml' => $innerHtml,
                'details' => [],
                'ctaUrl' => null,
                'ctaLabel' => null,
                'footerDisclaimer' => 'This is a test email only. No booking or payment action is required.',
            ]),
        ))->render();

        return new SettingsTestEmailRendered(
            subject: $subject,
            html: $html,
            innerBody: $innerBody,
            usedDbTemplate: $dbTemplate !== null && filled($dbTemplate->body),
            profile: $profile,
        );
    }

    protected function loadDbTemplate(Agency $agency): ?AgencyMessageTemplate
    {
        return AgencyMessageTemplate::query()
            ->where('agency_id', $agency->id)
            ->where('event', self::EVENT)
            ->where('channel', 'email')
            ->first();
    }

    /**
     * @return array<string, string>
     */
    protected function variables(CompanyEmailProfile $profile): array
    {
        return [
            'company_name' => $profile->name,
            'agency_name' => $profile->name,
            'brand_name' => $profile->name,
            'support_email' => $profile->support_email ?? '',
            'support_phone' => $profile->support_phone ?? '',
            'website_url' => $profile->website_url ?? '',
        ];
    }

    protected function defaultBody(): string
    {
        return implode("\n", [
            'Hello,',
            '',
            'This is a test email sent from your communication settings.',
            '',
            'If you received this message, outbound email delivery is working for {{ brand_name }}.',
            'This is not a booking confirmation, payment receipt, or ticket.',
            '',
            'You can ignore this message.',
        ]);
    }
}

/**
 * @internal
 */
final class SettingsTestEmailRendered
{
    public function __construct(
        public string $subject,
        public string $html,
        public string $innerBody,
        public bool $usedDbTemplate,
        public CompanyEmailProfile $profile,
    ) {}
}
