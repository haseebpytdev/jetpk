<?php

namespace App\Data\Ai\Lab\V1;

/**
 * Versioned request contract for lab consultant gateway.
 *
 * @phpstan-type ConsultantTurnRequestArray array<string, mixed>
 */
final class ConsultantTurnRequest
{
    public const VERSION = 'v1';

    /**
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $capabilities
     * @param  array<string, mixed>  $confirmation
     */
    public function __construct(
        public readonly string $conversationId,
        public readonly string $message,
        public readonly string $locale = 'en',
        public readonly string $channel = 'ask_jetpakistan',
        public readonly array $state = [],
        public readonly array $capabilities = [],
        public readonly array $confirmation = [],
        public readonly ?string $authenticatedUserId = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'contract_version' => self::VERSION,
            'conversation_id' => $this->conversationId,
            'message' => $this->message,
            'locale' => $this->locale,
            'channel' => $this->channel,
            'user_context' => [
                'authenticated' => $this->authenticatedUserId !== null,
                'user_id' => $this->authenticatedUserId,
            ],
            'state' => $this->state,
            'capabilities' => $this->capabilities,
            'confirmation' => $this->confirmation,
        ];
    }
}
