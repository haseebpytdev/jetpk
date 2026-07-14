<?php

namespace App\Support\Platform;

/**
 * Immutable platform module definition for the deployment control registry (read-only in Sprint 8B).
 */
final readonly class PlatformModule
{
    /**
     * @param  list<string>  $requiresAll
     * @param  list<string>  $requiresAny
     * @param  list<string>  $relatedRoutes
     * @param  list<string>  $notes
     * @param  list<string>  $configHints
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $description,
        public string $section,
        public string $risk,
        public bool $defaultEnabled,
        public bool $protected,
        public array $requiresAll = [],
        public array $requiresAny = [],
        public array $relatedRoutes = [],
        public array $notes = [],
        public array $configHints = [],
    ) {}

    public function isProtected(): bool
    {
        return $this->protected;
    }
}
