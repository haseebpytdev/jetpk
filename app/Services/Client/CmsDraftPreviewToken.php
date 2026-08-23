<?php

namespace App\Services\Client;

/**
 * Short-lived, page-scoped HMAC token so CMS draft preview works when the
 * Dashboard → public Next → Laravel hop cannot share the admin PHP session.
 *
 * Minting remains admin-authenticated. Token only authorizes draft *read*
 * for one page_key until expiry — never mutations.
 */
final class CmsDraftPreviewToken
{
    private const VERSION = 1;

    private const DEFAULT_TTL_SECONDS = 300;

    public function mint(string $pageKey, int $userId, int $ttlSeconds = self::DEFAULT_TTL_SECONDS): string
    {
        $payload = [
            'v' => self::VERSION,
            'page_key' => $pageKey,
            'uid' => $userId,
            'exp' => time() + max(60, $ttlSeconds),
        ];

        $body = $this->encode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = $this->sign($body);

        return $body.'.'.$signature;
    }

    public function verify(?string $token, string $pageKey): bool
    {
        if (! is_string($token) || $token === '' || ! str_contains($token, '.')) {
            return false;
        }

        [$body, $signature] = explode('.', $token, 2);
        if ($body === '' || $signature === '' || ! hash_equals($this->sign($body), $signature)) {
            return false;
        }

        try {
            $json = $this->decode($body);
            $payload = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return false;
        }

        if (! is_array($payload)) {
            return false;
        }

        if ((int) ($payload['v'] ?? 0) !== self::VERSION) {
            return false;
        }

        if (($payload['page_key'] ?? '') !== $pageKey) {
            return false;
        }

        $exp = (int) ($payload['exp'] ?? 0);
        if ($exp < time()) {
            return false;
        }

        $uid = (int) ($payload['uid'] ?? 0);

        return $uid > 0;
    }

    private function sign(string $body): string
    {
        return hash_hmac('sha256', $body, $this->secret());
    }

    private function secret(): string
    {
        $key = (string) config('app.key', '');
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }
        }

        if ($key === '') {
            throw new \RuntimeException('APP_KEY required to mint CMS draft preview tokens.');
        }

        return $key;
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function decode(string $value): string
    {
        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if (! is_string($decoded)) {
            throw new \InvalidArgumentException('Invalid preview token encoding.');
        }

        return $decoded;
    }
}
