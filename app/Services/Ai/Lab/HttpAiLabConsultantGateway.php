<?php

namespace App\Services\Ai\Lab;

use App\Contracts\Ai\Lab\AiLabConsultantGateway;
use App\Data\Ai\Lab\V1\ConsultantTurnRequest;
use App\Data\Ai\Lab\V1\ConsultantTurnResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTTP client for localhost AI lab consultant gateway.
 */
final class HttpAiLabConsultantGateway implements AiLabConsultantGateway
{
    public function __construct(
        private readonly AiLabGatewayUrlResolver $urlResolver,
    ) {}

    public function isHealthy(): bool
    {
        $url = $this->urlResolver->resolve();
        if ($url === null) {
            return false;
        }

        if ($this->urlResolver->isOllamaDownSimulation() || $this->urlResolver->isMalformedSimulation()) {
            return false;
        }

        try {
            $response = Http::timeout(3)->get($url.'/health');

            return $response->ok() && ($response->json('ok') === true);
        } catch (\Throwable) {
            return false;
        }
    }

    public function turn(ConsultantTurnRequest $request): ConsultantTurnResponse
    {
        if ($this->urlResolver->isMalformedSimulation()) {
            throw new \RuntimeException('AI lab gateway returned malformed JSON.');
        }

        if ($this->urlResolver->isOllamaDownSimulation()) {
            throw new \RuntimeException('AI lab gateway returned HTTP 503');
        }

        $url = $this->urlResolver->resolve();
        if ($url === null) {
            throw new \RuntimeException('AI lab gateway URL is not localhost-safe.');
        }

        $timeout = max(1, (int) config('ai_lab.gateway_timeout_ms', 8000) / 1000);

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->post($url.'/v1/consultant/turn', $request->toArray());

            if (! $response->ok()) {
                throw new \RuntimeException('AI lab gateway returned HTTP '.$response->status());
            }

            $json = $response->json();
            if (! is_array($json)) {
                throw new \RuntimeException('AI lab gateway returned malformed JSON.');
            }

            return ConsultantTurnResponse::fromArray($json);
        } catch (\Throwable $e) {
            Log::warning('ai.lab.gateway_turn_failed', [
                'message' => $e->getMessage(),
                'resolved_gateway_url' => $url,
                'fault_mode' => AiLabFaultInjectionContext::mode(),
            ]);

            throw $e;
        }
    }
}
