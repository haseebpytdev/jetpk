<?php

namespace App\Support\Integrations;

use App\Enums\IntegrationCategory;

/**
 * Immutable descriptor for an Integration Hub card (admin facade metadata only).
 */
final class IntegrationDefinition
{
    /**
     * @param  list<string>  $capabilities
     */
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly IntegrationCategory $category,
        public readonly string $icon,
        public readonly array $capabilities,
        public readonly bool $adapterInstalled,
        public readonly bool $supportsConnectionTest,
        public readonly bool $supportsTestTransaction,
        public readonly bool $supportsEnableToggle,
        public readonly bool $canActivateRuntime,
        public readonly ?string $docsUrl,
        public readonly string $manager = 'null',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'category' => $this->category->value,
            'categoryLabel' => $this->category->label(),
            'icon' => $this->icon,
            'capabilities' => $this->capabilities,
            'adapterInstalled' => $this->adapterInstalled,
            'supportsConnectionTest' => $this->supportsConnectionTest,
            'supportsTestTransaction' => $this->supportsTestTransaction,
            'supportsEnableToggle' => $this->supportsEnableToggle,
            'canActivateRuntime' => $this->canActivateRuntime,
            'docsUrl' => $this->docsUrl,
            'manager' => $this->manager,
        ];
    }
}
