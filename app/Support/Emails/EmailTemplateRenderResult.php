<?php

namespace App\Support\Emails;

/**
 * Result of rendering a template string with placeholder substitution.
 */
readonly class EmailTemplateRenderResult
{
    /**
     * @param  list<string>  $unresolvedKeys
     * @param  list<string>  $missingKeysOriginal
     * @param  list<string>  $fallbackKeysApplied
     * @param  list<string>  $unresolvedAfterFallback
     */
    public function __construct(
        public string $output,
        public array $unresolvedKeys = [],
        public bool $hadUnresolved = false,
        public array $missingKeysOriginal = [],
        public array $fallbackKeysApplied = [],
        public array $unresolvedAfterFallback = [],
    ) {}
}
