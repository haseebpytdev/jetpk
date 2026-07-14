<?php

namespace App\Support\Emails;

use App\Models\AgencyMessageTemplate;

/**
 * Rendered admin preview for a registry email template (I4; does not send mail).
 */
readonly class EmailTemplatePreviewResult
{
    /**
     * @param  array<string, string>  $sampleVariables
     * @param  list<string>  $warnings
     */
    public function __construct(
        public EmailTemplateDefinition $definition,
        public string $subject,
        public string $html,
        public string $innerBody,
        public bool $usedDbTemplate,
        public ?AgencyMessageTemplate $dbTemplate,
        public array $sampleVariables,
        public array $warnings,
        public bool $notConnectedToLiveSending,
    ) {}
}
