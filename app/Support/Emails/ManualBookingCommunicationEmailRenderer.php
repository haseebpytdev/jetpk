<?php

namespace App\Support\Emails;

use App\Models\Agency;
use App\Support\Branding\CompanyEmailProfile;
use App\Support\Branding\CompanyEmailProfileResolver;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

/**
 * Wraps admin-entered manual booking-console email bodies in the modern layout (I8).
 */
class ManualBookingCommunicationEmailRenderer
{
    public function render(
        Agency $agency,
        string $eventKey,
        string $subject,
        string $body,
    ): ManualBookingCommunicationRendered {
        $profile = CompanyEmailProfileResolver::resolve($agency);
        $definition = EmailTemplateRegistry::find('manual-'.$eventKey);
        $safePlain = EmailBodySanitizer::toSafePlainBody($body);
        $contentHtml = EmailBodySanitizer::toSafeHtmlBody($body);
        $headline = $definition?->name ?? Str::headline(str_replace('_', ' ', $eventKey));

        $html = View::make('emails.layouts.modern', array_merge(
            ['companyEmailProfile' => $profile],
            ModernEmailLayout::viewData([
                'emailMode' => ModernEmailLayout::MODE_CUSTOMER,
                'headline' => $headline,
                'intro' => null,
                'statusBannerLabel' => $headline,
                'statusBannerTone' => 'info',
                'contentHtml' => $contentHtml,
                'details' => [],
                'ctaUrl' => null,
                'ctaLabel' => null,
                'nextSteps' => [
                    'Review the message above regarding your booking.',
                    'Contact support if you have questions.',
                ],
                'footerDisclaimer' => 'Please keep this email for your records.',
            ]),
        ))->render();

        return new ManualBookingCommunicationRendered(
            subject: $subject,
            html: $html,
            plainBody: $safePlain,
            profile: $profile,
        );
    }
}

/**
 * @internal
 */
final class ManualBookingCommunicationRendered
{
    public function __construct(
        public string $subject,
        public string $html,
        public string $plainBody,
        public CompanyEmailProfile $profile,
    ) {}
}
