<?php

namespace App\Services\Ai\Lab;

/**
 * Resolves the effective AI lab gateway URL for the current request/process scope.
 */
final class AiLabGatewayUrlResolver
{
    private const UNREACHABLE_PORT = 'http://127.0.0.1:1';

    public function resolve(): ?string
    {
        $mode = AiLabFaultInjectionContext::mode();

        if ($mode === AiLabFaultInjectionContext::MODE_SIMULATE_GATEWAY_DOWN) {
            return self::UNREACHABLE_PORT;
        }

        $raw = rtrim((string) config('ai_lab.gateway_url', ''), '/');
        if ($raw === '') {
            return null;
        }

        if ((bool) config('ai_lab.require_localhost', true) && ! $this->isLocalhostUrl($raw)) {
            return null;
        }

        return $raw;
    }

    public function isMalformedSimulation(): bool
    {
        return AiLabFaultInjectionContext::mode() === AiLabFaultInjectionContext::MODE_SIMULATE_MALFORMED_RESPONSE;
    }

    public function isOllamaDownSimulation(): bool
    {
        return AiLabFaultInjectionContext::mode() === AiLabFaultInjectionContext::MODE_SIMULATE_OLLAMA_DOWN;
    }

    private function isLocalhostUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return in_array($host, ['127.0.0.1', 'localhost', '::1'], true);
    }
}
