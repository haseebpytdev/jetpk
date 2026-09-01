<?php

namespace App\Services\Visa\Transport;

use App\Services\Visa\Exceptions\PolicyBlocked;
use App\Services\Visa\Exceptions\ProviderUnavailable;
use App\Services\Visa\Support\VisaHostAllowlist;
use App\Services\Visa\VisaPolicyGate;
use Illuminate\Support\Facades\Http;

/**
 * Live HTTPS transport — blocked unless policy gate allows.
 */
final class LiveVisaHttpTransport implements VisaHttpTransport
{
    public function __construct(
        private readonly VisaPolicyGate $policyGate,
        private readonly VisaHostAllowlist $allowlist,
    ) {}

    public function request(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null,
        bool $followRedirects = false,
    ): array {
        if (! $this->policyGate->liveAllowed()) {
            throw new PolicyBlocked($this->policyGate->denyLiveReason() ?? 'Live MOFA denied.');
        }

        $this->allowlist->assertSafeUrl($url);

        try {
            $pending = Http::withHeaders($headers)
                ->withOptions(['allow_redirects' => $followRedirects])
                ->timeout((int) config('visa.saudi_mofa.timeout_seconds', 20));

            $response = match (strtoupper($method)) {
                'GET' => $pending->get($url),
                'POST' => $pending->withBody($body ?? '', $headers['Content-Type'] ?? 'application/x-www-form-urlencoded')->post($url),
                default => throw new ProviderUnavailable('Unsupported HTTP method.'),
            };
        } catch (PolicyBlocked $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ProviderUnavailable('Provider network failure.');
        }

        $responseBody = (string) $response->body();
        $this->allowlist->assertBodySize($responseBody);

        /** @var array<string, string> $respHeaders */
        $respHeaders = [];
        foreach ($response->headers() as $name => $values) {
            $respHeaders[strtolower((string) $name)] = is_array($values) ? (string) ($values[0] ?? '') : (string) $values;
        }

        if (isset($respHeaders['location'])) {
            $this->allowlist->assertRedirectAllowed($respHeaders['location'], parse_url($url, PHP_URL_HOST) ?: 'visa.mofa.gov.sa');
        }

        return [
            'status' => $response->status(),
            'headers' => $respHeaders,
            'body' => $responseBody,
            'final_url' => $url,
        ];
    }
}
