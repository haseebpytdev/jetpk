<?php

namespace App\Services\Notifications;

final class ResolvedNotificationRoute
{
    public function __construct(
        public string $audience,
        public string $recipientStrategy,
        public string $queueName,
        public string $priority,
        public ?string $templateKey,
        public string $provider,
        public ?string $locale,
        public bool $fromLegacyFallback = false,
    ) {}
}
