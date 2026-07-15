<?php

namespace App\Support\Emails;

/**
 * Static catalog entry for a known system email (I3 registry; does not send mail).
 */
readonly class EmailTemplateDefinition
{
    /**
     * @param  list<string>  $variables
     */
    public function __construct(
        public string $key,
        public string $event,
        public string $name,
        public string $description,
        public string $category,
        public string $audience,
        public string $channel,
        public string $sendPath,
        public string $templateSource,
        public bool $editableNow,
        public bool $migrationSafeLater,
        public array $variables = [],
        public ?string $riskNote = null,
    ) {}
}
