<?php

namespace App\Support\Emails;

/**
 * Default content contract for one JetPK email event inside the universal shell.
 *
 * Events supply copy and block schema only — never a full HTML layout.
 */
readonly class JetpkEmailEventContentDefinition
{
    /**
     * @param  list<string>  $detailFields  Variable keys rendered as detail rows when present.
     * @param  list<string>  $contentBlocks  Partial block keys rendered in @section('content').
     */
    public function __construct(
        public string $eventKey,
        public string $name,
        public string $category,
        public string $audience,
        public string $subject,
        public string $preheader,
        public string $heading,
        public string $intro,
        public ?string $statusLabel,
        public string $statusType,
        public array $detailFields,
        public ?string $ctaLabel,
        public ?string $ctaUrlKey,
        public array $contentBlocks,
        public bool $enabledByDefault = true,
        public ?string $alertTitle = null,
        public ?string $alertMessage = null,
        public ?string $jetpkTypeKey = null,
    ) {}
}
