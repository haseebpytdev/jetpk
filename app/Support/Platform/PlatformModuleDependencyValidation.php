<?php

namespace App\Support\Platform;

/**
 * Result of {@see PlatformModuleRegistry::validateDependencies()} (preview / tests only in Sprint 8B).
 */
final readonly class PlatformModuleDependencyValidation
{
    /**
     * @param  list<array{module: string, code: string, message: string, missing?: list<string>}>  $violations
     */
    public function __construct(
        public bool $valid,
        public array $violations = [],
    ) {}

    public function isValid(): bool
    {
        return $this->valid;
    }

    /**
     * @return list<array{module: string, code: string, message: string, missing?: list<string>}>
     */
    public function violations(): array
    {
        return $this->violations;
    }
}
