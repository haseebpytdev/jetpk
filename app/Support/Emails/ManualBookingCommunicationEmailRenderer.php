<?php

namespace App\Support\Emails;

use App\Models\Agency;
use App\Support\Branding\CompanyEmailProfile;
use App\Support\Branding\CompanyEmailProfileResolver;
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

        $result = app(JetpkEmailEventRenderer::class)->render(
            eventKey: 'notification',
            agency: $agency,
            runtimeVariables: [
                'recipient_role' => 'customer',
            ],
            payload: [
                'shell_notice' => true,
                'title' => $headline,
                'intro' => '',
                'detail_rows' => [],
                'cta_url_override' => null,
                'next_steps_text' => "Next steps\n- Review the message above regarding your booking.\n- Contact support if you have questions.",
                'extra_html' => $contentHtml,
            ],
        );

        return new ManualBookingCommunicationRendered(
            subject: $subject,
            html: $result->html,
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
