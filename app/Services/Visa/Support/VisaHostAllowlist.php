<?php

namespace App\Services\Visa\Support;

use InvalidArgumentException;

final class VisaHostAllowlist
{
    /** @var list<string> */
    private const BLOCKED_HOST_SUFFIXES = [
        'localhost',
        '127.0.0.1',
        '0.0.0.0',
        '::1',
        'metadata.google.internal',
    ];

    /**
     * @param  list<string>  $allowedHosts
     * @param  list<string>  $allowedPathPrefixes
     */
    public function __construct(
        private readonly array $allowedHosts,
        private readonly array $allowedPathPrefixes,
        private readonly int $maxBodyBytes = 2_000_000,
    ) {}

    public function assertSafeUrl(string $url): void
    {
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new InvalidArgumentException('Invalid provider URL.');
        }

        $scheme = strtolower((string) $parts['scheme']);
        if (! in_array($scheme, ['https'], true)) {
            throw new InvalidArgumentException('Only HTTPS provider URLs are allowed.');
        }

        $host = strtolower((string) $parts['host']);
        if ($this->isBlockedHost($host) || ! in_array($host, $this->allowedHosts, true)) {
            throw new InvalidArgumentException('Provider host is not allowlisted.');
        }

        $path = (string) ($parts['path'] ?? '/');
        $ok = false;
        foreach ($this->allowedPathPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $ok = true;
                break;
            }
        }
        if (! $ok) {
            throw new InvalidArgumentException('Provider path is not allowlisted.');
        }
    }

    public function assertRedirectAllowed(string $location, string $baseHost): void
    {
        if (str_starts_with($location, '/')) {
            $location = 'https://'.$baseHost.$location;
        }
        $this->assertSafeUrl($location);
    }

    public function assertBodySize(string $body): void
    {
        if (strlen($body) > $this->maxBodyBytes) {
            throw new InvalidArgumentException('Provider response exceeds size limit.');
        }
    }

    private function isBlockedHost(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        foreach (self::BLOCKED_HOST_SUFFIXES as $blocked) {
            if ($host === $blocked || str_ends_with($host, '.'.$blocked)) {
                return true;
            }
        }

        return false;
    }
}
