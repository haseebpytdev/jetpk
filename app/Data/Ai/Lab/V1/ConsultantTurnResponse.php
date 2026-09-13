<?php

namespace App\Data\Ai\Lab\V1;

/**
 * Versioned response contract from lab consultant gateway.
 */
final class ConsultantTurnResponse
{
    /**
     * @param  array<string, mixed>  $parser
     * @param  array<string, mixed>  $confirmation
     * @param  array<string, mixed>  $action
     * @param  array<string, mixed>  $rag
     * @param  list<array<string, mixed>>  $recommendations
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>|null  $learningEvent
     * @param  array<string, mixed>  $labState
     */
    public function __construct(
        public readonly string $assistantMessage,
        public readonly string $status,
        public readonly string $mode,
        public readonly array $parser = [],
        public readonly array $confirmation = [],
        public readonly array $action = [],
        public readonly array $rag = [],
        public readonly array $recommendations = [],
        public readonly array $meta = [],
        public readonly ?array $learningEvent = null,
        public readonly array $labState = [],
        public readonly ?string $failureEvent = null,
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        return new self(
            assistantMessage: (string) ($raw['assistant_message'] ?? $raw['message'] ?? ''),
            status: (string) ($raw['status'] ?? 'degraded'),
            mode: (string) ($raw['mode'] ?? 'LAB_CONSULTANT_V1'),
            parser: is_array($raw['parser'] ?? null) ? $raw['parser'] : [],
            confirmation: is_array($raw['confirmation'] ?? null) ? $raw['confirmation'] : [],
            action: is_array($raw['action'] ?? null) ? $raw['action'] : [],
            rag: is_array($raw['rag'] ?? null) ? $raw['rag'] : [],
            recommendations: is_array($raw['recommendations'] ?? null) ? $raw['recommendations'] : [],
            meta: is_array($raw['meta'] ?? null) ? $raw['meta'] : [],
            learningEvent: is_array($raw['learning_event'] ?? null) ? $raw['learning_event'] : null,
            labState: is_array($raw['lab_state'] ?? null) ? $raw['lab_state'] : [],
            failureEvent: isset($raw['failure_event']) ? (string) $raw['failure_event'] : null,
        );
    }

    public function actionKind(): string
    {
        return strtoupper((string) ($this->action['kind'] ?? 'NONE'));
    }

    public function confirmationState(): string
    {
        return strtoupper((string) ($this->confirmation['state'] ?? 'NONE'));
    }

    public function snapshotHash(): ?string
    {
        $hash = $this->confirmation['snapshot_hash'] ?? null;

        return is_string($hash) && $hash !== '' ? $hash : null;
    }
}
