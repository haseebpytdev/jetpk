<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown when a platform module is planned off (Sprint 8L backend enforcer).
 */
class PlatformModuleDisabledException extends HttpException
{
    public const PUBLIC_MESSAGE = 'This action is unavailable for this deployment.';

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        private readonly string $moduleKey,
        private readonly ?string $action = null,
        private readonly array $context = [],
        ?string $publicMessage = null,
    ) {
        parent::__construct(403, $publicMessage ?? self::PUBLIC_MESSAGE);
    }

    public function moduleKey(): string
    {
        return $this->moduleKey;
    }

    public function action(): ?string
    {
        return $this->action;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}
