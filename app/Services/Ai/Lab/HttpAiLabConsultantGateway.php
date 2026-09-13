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
    public function isHealthy(): bool
    {
        $url = $this->gatewayUrl();
        if ($url === null) {
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
        $url = $this->gatewayUrl();
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
            ]);

            throw $e;
        }
    }

    private function gatewayUrl(): ?string
    {
        $raw = rtrim((string) config('ai_lab.gateway_url', ''), '/');
        if ($raw === '') {
            return null;
        }

        if ((bool) config('ai_lab.require_localhost', true) && ! $this->isLocalhostUrl($raw)) {
            return null;
        }

        return $raw;
    }

    private function isLocalhostUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return in_array($host, ['127.0.0.1', 'localhost', '::1'], true);
    }
}
