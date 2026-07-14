<?php

namespace App\Support\Emails;

/**
 * Result of rendering a JetPK universal-shell email.
 */
readonly class JetpkEmailRenderResult
{
    /** @var list<string> */
    public const REQUIRED_BASE_VARIABLES = ['brand_name', 'agency_name', 'support_email'];

    /**
     * @param  array<string, mixed>  $content
     * @param  list<string>  $unresolvedPlaceholders
     * @param  list<string>  $fallbackKeysApplied
     * @param  list<string>  $missingRequiredVariables
     */
    public function __construct(
        public string $eventKey,
        public string $subject,
        public string $html,
        public array $content,
        public bool $usedDbTemplate,
        public string $preheader = '',
        public array $unresolvedPlaceholders = [],
        public array $fallbackKeysApplied = [],
        public array $missingRequiredVariables = [],
    ) {}

    public function hasUnresolvedPlaceholders(): bool
    {
        return $this->unresolvedPlaceholders !== [];
    }

    public function hasMissingRequiredVariables(): bool
    {
        return $this->missingRequiredVariables !== [];
    }

    public function hasPlaceholderDefects(): bool
    {
        if ($this->hasUnresolvedPlaceholders() || $this->hasMissingRequiredVariables()) {
            return true;
        }

        return str_contains($this->subject, '{{')
            || str_contains($this->preheader, '{{')
            || str_contains($this->html, '{{');
    }
}
