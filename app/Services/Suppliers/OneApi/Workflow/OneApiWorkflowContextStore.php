<?php

namespace App\Services\Suppliers\OneApi\Workflow;

use App\Support\OneApi\OneApiWorkflowFingerprint;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class OneApiWorkflowContextStore
{
    public function create(
        int $connectionId,
        string $correlationId,
        array $signedOfferPayload,
        ?int $agencyId = null,
        ?int $ownerUserId = null,
    ): OneApiWorkflowContext {
        $ttl = (int) config('suppliers.one_api.workflow_context_ttl_seconds', 3600);
        $now = CarbonImmutable::now();
        $context = new OneApiWorkflowContext(
            contextId: (string) Str::uuid(),
            connectionId: $connectionId,
            correlationId: $correlationId,
            signedOfferPayload: $signedOfferPayload,
            signedOfferFingerprint: OneApiWorkflowFingerprint::signedOffer($signedOfferPayload),
            passengerProfileFingerprint: OneApiWorkflowFingerprint::passengerProfile(
                is_array($signedOfferPayload['passengers'] ?? null) ? $signedOfferPayload['passengers'] : [],
            ),
            agencyId: $agencyId,
            ownerUserId: $ownerUserId,
            createdAtIso: $now->toIso8601String(),
            expiresAtIso: $now->addSeconds($ttl)->toIso8601String(),
        );
        $this->put($context);

        return $context;
    }

    public function put(OneApiWorkflowContext $context): void
    {
        $ttl = (int) config('suppliers.one_api.workflow_context_ttl_seconds', 3600);
        Cache::put($this->key($context->contextId), $context->toArray(), $ttl);
    }

    public function get(string $contextId): ?OneApiWorkflowContext
    {
        $data = Cache::get($this->key($contextId));
        if (! is_array($data)) {
            return null;
        }

        return OneApiWorkflowContext::fromArray($data);
    }

    private function key(string $contextId): string
    {
        return 'one_api_workflow:'.$contextId;
    }
}
