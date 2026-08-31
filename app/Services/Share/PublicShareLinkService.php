<?php

namespace App\Services\Share;

use App\Models\PublicShareLink;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PublicShareLinkService
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function createFlightLink(array $input, ?string $createdByContext = null): PublicShareLink
    {
        $ttlMinutes = (int) config('ota.share_links.flight_ttl_minutes', 180);
        $supplierExpiry = isset($input['supplier_offer_expires_at'])
            ? Carbon::parse((string) $input['supplier_offer_expires_at'])
            : null;
        $referenceExpiry = now()->addMinutes($ttlMinutes);
        $expiresAt = $supplierExpiry ? $referenceExpiry->min($supplierExpiry) : $referenceExpiry;

        return $this->create('flight_fare', $input, $expiresAt, $supplierExpiry, $createdByContext);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function createGroupLink(array $input, ?string $createdByContext = null): PublicShareLink
    {
        $ttlMinutes = (int) config('ota.share_links.group_ttl_minutes', 1440);

        return $this->create('group_offer', $input, now()->addMinutes($ttlMinutes), null, $createdByContext);
    }

    public function findByCode(string $code): ?PublicShareLink
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
        if ($code === '' || strlen($code) > 16) {
            return null;
        }

        return PublicShareLink::query()->where('code', $code)->first();
    }

    /**
     * @return array{url: string, results_url: string, expired: bool, link: PublicShareLink}
     */
    public function resolveFlight(PublicShareLink $link): array
    {
        if ($link->link_type !== 'flight_fare') {
            throw new InvalidArgumentException('Not a flight share link.');
        }

        $params = [
            'trip_type' => (string) ($link->trip_type ?: 'one_way'),
            'from' => (string) $link->origin,
            'to' => (string) $link->destination,
            'depart' => $link->depart_date?->format('Y-m-d'),
            'adults' => (string) max(1, (int) $link->adults),
            'children' => (string) max(0, (int) $link->children),
            'infants' => (string) max(0, (int) $link->infants),
            'cabin' => (string) ($link->cabin ?: 'economy'),
        ];
        if ($link->return_date) {
            $params['return_date'] = $link->return_date->format('Y-m-d');
        }

        $resultsUrl = url('/flights/results?'.http_build_query(array_filter($params)));

        return [
            'url' => url($link->publicPath()),
            'results_url' => $resultsUrl,
            'expired' => $link->isExpired(),
            'link' => $link,
        ];
    }

    public function assertCreateRateLimit(string $key): void
    {
        $limiterKey = 'share-link-create:'.$key;
        if (RateLimiter::tooManyAttempts($limiterKey, 30)) {
            abort(429, 'Too many share link requests. Please try again shortly.');
        }
        RateLimiter::hit($limiterKey, 60);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function create(
        string $type,
        array $input,
        Carbon $expiresAt,
        ?Carbon $supplierExpiry,
        ?string $createdByContext,
    ): PublicShareLink {
        $code = $this->generateUniqueCode();

        return PublicShareLink::query()->create([
            'code' => $code,
            'link_type' => $type,
            'origin' => isset($input['origin']) ? strtoupper(substr((string) $input['origin'], 0, 8)) : null,
            'destination' => isset($input['destination']) ? strtoupper(substr((string) $input['destination'], 0, 8)) : null,
            'depart_date' => $input['depart_date'] ?? null,
            'return_date' => $input['return_date'] ?? null,
            'trip_type' => $input['trip_type'] ?? null,
            'adults' => max(1, (int) ($input['adults'] ?? 1)),
            'children' => max(0, (int) ($input['children'] ?? 0)),
            'infants' => max(0, (int) ($input['infants'] ?? 0)),
            'cabin' => $input['cabin'] ?? null,
            'display_currency' => $input['display_currency'] ?? 'PKR',
            'display_fare' => $input['display_fare'] ?? null,
            'airline_code' => isset($input['airline_code']) ? strtoupper(substr((string) $input['airline_code'], 0, 8)) : null,
            'airline_name' => isset($input['airline_name']) ? substr((string) $input['airline_name'], 0, 120) : null,
            'offer_fingerprint' => isset($input['offer_fingerprint']) ? substr((string) $input['offer_fingerprint'], 0, 64) : null,
            'supplier_offer_expires_at' => $supplierExpiry,
            'expires_at' => $expiresAt,
            'payload' => $this->safePayload($input['payload'] ?? []),
            'created_by_context' => $createdByContext,
        ]);
    }

    private function generateUniqueCode(): string
    {
        $length = (int) config('ota.share_links.code_length', 8);
        for ($i = 0; $i < 12; $i++) {
            $code = strtoupper(Str::random($length));
            $code = preg_replace('/[^A-Z0-9]/', 'X', $code) ?? $code;
            if (! PublicShareLink::query()->where('code', $code)->exists()) {
                return $code;
            }
        }

        throw new InvalidArgumentException('Unable to allocate share code.');
    }

    /**
     * @param  mixed  $payload
     * @return array<string, mixed>
     */
    private function safePayload(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $blocked = ['password', 'secret', 'token', 'passport', 'document_number', 'card', 'cvv'];
        $out = [];
        foreach ($payload as $key => $value) {
            if (! is_string($key)) {
                continue;
            }
            $lower = strtolower($key);
            foreach ($blocked as $b) {
                if (str_contains($lower, $b)) {
                    continue 2;
                }
            }
            if (is_scalar($value) || $value === null) {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
