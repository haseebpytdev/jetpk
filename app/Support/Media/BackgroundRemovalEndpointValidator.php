<?php

namespace App\Support\Media;

use Illuminate\Validation\ValidationException;

final class BackgroundRemovalEndpointValidator
{
    public function assertSafeHttpsEndpoint(?string $endpoint): void
    {
        if ($endpoint === null || trim($endpoint) === '') {
            return;
        }

        $endpoint = trim($endpoint);
        $parts = parse_url($endpoint);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($scheme !== 'https') {
            throw ValidationException::withMessages([
                'api_endpoint' => 'Background-removal endpoint must use HTTPS.',
            ]);
        }

        if ($host === '' || $this->isBlockedHost($host)) {
            throw ValidationException::withMessages([
                'api_endpoint' => 'Background-removal endpoint host is not allowed.',
            ]);
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if ($this->isPrivateIp($host)) {
                throw ValidationException::withMessages([
                    'api_endpoint' => 'Background-removal endpoint cannot target private networks.',
                ]);
            }
        }
    }

    private function isBlockedHost(string $host): bool
    {
        return in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', '::1'], true)
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal');
    }

    private function isPrivateIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false;
    }
}
