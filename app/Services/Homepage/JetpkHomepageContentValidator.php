<?php

namespace App\Services\Homepage;

use App\Models\Airport;
use App\Support\Client\ClientPageKeys;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Validates JetPK homepage Page Settings content before draft save.
 */
final class JetpkHomepageContentValidator
{
    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public function validateAndNormalize(string $pageKey, array $content): array
    {
        if ($pageKey !== ClientPageKeys::HOME) {
            return $content;
        }

        if (isset($content['routes']['items']) && is_array($content['routes']['items'])) {
            $content['routes']['items'] = $this->normalizeRoutes($content['routes']['items']);
        }

        if (isset($content['destinations']['items']) && is_array($content['destinations']['items'])) {
            $content['destinations']['items'] = $this->normalizeDestinations($content['destinations']['items']);
        }

        if (isset($content['featured_deals']['items']) && is_array($content['featured_deals']['items'])) {
            $content['featured_deals']['items'] = $this->normalizeFeaturedDeals($content['featured_deals']['items']);
        }

        if (isset($content['support_cta']) && is_array($content['support_cta'])) {
            $content['support_cta'] = $this->normalizeSupportCta($content['support_cta']);
        }

        return $content;
    }

    /**
     * @param  list<mixed>  $items
     * @return list<array<string, mixed>>
     */
    private function normalizeRoutes(array $items): array
    {
        $normalized = [];
        $seen = [];
        $errors = [];

        foreach (array_values($items) as $index => $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $from = strtoupper(trim((string) ($raw['from'] ?? '')));
            $to = strtoupper(trim((string) ($raw['to'] ?? '')));
            if ($from === '' && $to === '') {
                continue;
            }

            $tripType = (string) ($raw['trip_type'] ?? 'one_way');
            if (! in_array($tripType, ['one_way', 'return'], true)) {
                $errors["content.routes.items.{$index}.trip_type"] = 'Trip type must be one-way or return.';
            }

            if (! $this->isValidIata($from)) {
                $errors["content.routes.items.{$index}.from"] = 'Origin must be a valid active IATA code.';
            }
            if (! $this->isValidIata($to)) {
                $errors["content.routes.items.{$index}.to"] = 'Destination must be a valid active IATA code.';
            }
            if ($from !== '' && $to !== '' && $from === $to) {
                $errors["content.routes.items.{$index}.to"] = 'Origin and destination cannot be the same.';
            }

            $signature = $from.'|'.$to.'|'.$tripType;
            if (isset($seen[$signature])) {
                $errors["content.routes.items.{$index}.from"] = 'Duplicate route card for this origin, destination, and trip type.';
            }
            $seen[$signature] = true;

            $manual = $this->optionalPositivePrice($raw['manual_fallback_price'] ?? null);
            if ($manual === false) {
                $errors["content.routes.items.{$index}.manual_fallback_price"] = 'Manual fallback price must be a positive number.';
            }

            $id = trim((string) ($raw['id'] ?? ''));
            if ($id === '') {
                $id = (string) Str::uuid();
            }

            $normalized[] = [
                'id' => $id,
                'from' => $from,
                'to' => $to,
                'from_label' => $this->sanitize($raw['from_label'] ?? ''),
                'to_label' => $this->sanitize($raw['to_label'] ?? ''),
                'title' => $this->sanitize($raw['title'] ?? ''),
                'trip_type' => $tripType,
                'return_stay_days' => max(1, min(30, (int) ($raw['return_stay_days'] ?? config('jetpk_homepage.default_return_stay_days', 7)))),
                'adults' => max(1, min(9, (int) ($raw['adults'] ?? config('jetpk_homepage.default_adults', 1)))),
                'cabin' => in_array((string) ($raw['cabin'] ?? 'economy'), ['economy', 'premium_economy', 'business', 'first'], true)
                    ? (string) ($raw['cabin'] ?? 'economy')
                    : 'economy',
                'currency' => strtoupper(trim((string) ($raw['currency'] ?? 'PKR'))) ?: 'PKR',
                'sort_order' => (int) ($raw['sort_order'] ?? $index),
                'enabled' => $this->boolString($raw['enabled'] ?? '1'),
                'dynamic_fare_enabled' => $this->boolString($raw['dynamic_fare_enabled'] ?? '0'),
                'manual_fallback_price' => $manual === false ? '' : ($manual ?? ''),
                'price' => $this->sanitize($raw['price'] ?? ''),
                'cta_label' => $this->sanitize($raw['cta_label'] ?? ''),
                'cta_url' => $this->sanitizeUrl($raw['cta_url'] ?? ''),
                'badge' => $this->sanitize($raw['badge'] ?? ''),
            ];
        }

        usort($normalized, static fn (array $a, array $b): int => $a['sort_order'] <=> $b['sort_order']);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $max = (int) config('jetpk_homepage.max_routes', 12);
        if (count($normalized) > $max) {
            throw ValidationException::withMessages([
                'content.routes.items' => "Maximum {$max} trending routes allowed.",
            ]);
        }

        return $normalized;
    }

    /**
     * @param  list<mixed>  $items
     * @return list<array<string, mixed>>
     */
    private function normalizeDestinations(array $items): array
    {
        $normalized = [];
        $seen = [];
        $errors = [];

        foreach (array_values($items) as $index => $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $code = strtoupper(trim((string) ($raw['code'] ?? '')));
            $title = $this->sanitize($raw['title'] ?? '');
            if ($code === '' && $title === '') {
                continue;
            }

            if ($code !== '' && ! $this->isValidIata($code)) {
                $errors["content.destinations.items.{$index}.code"] = 'Destination IATA must be valid and active.';
            }

            if ($code !== '' && isset($seen[$code])) {
                $errors["content.destinations.items.{$index}.code"] = 'Duplicate destination IATA code.';
            }
            if ($code !== '') {
                $seen[$code] = true;
            }

            $manual = $this->optionalPositivePrice($raw['manual_fallback_price'] ?? $raw['price'] ?? null);
            if ($manual === false) {
                $errors["content.destinations.items.{$index}.manual_fallback_price"] = 'Price must be a positive number when provided.';
            }

            $id = trim((string) ($raw['id'] ?? ''));
            if ($id === '') {
                $id = (string) Str::uuid();
            }

            $normalized[] = [
                'id' => $id,
                'code' => $code,
                'title' => $title,
                'country' => $this->sanitize($raw['country'] ?? ''),
                'text' => $this->sanitize($raw['text'] ?? ''),
                'origin' => strtoupper(trim((string) ($raw['origin'] ?? ''))),
                'manual_fallback_price' => $manual === false ? '' : ($manual ?? ''),
                'price' => $manual === false ? '' : ($manual ?? ''),
                'dynamic_fare_enabled' => $this->boolString($raw['dynamic_fare_enabled'] ?? '0'),
                'currency' => strtoupper(trim((string) ($raw['currency'] ?? 'PKR'))) ?: 'PKR',
                'image_asset_key' => $this->sanitizeAssetKey($raw['image_asset_key'] ?? ''),
                'alt' => $this->sanitize($raw['alt'] ?? ''),
                'sort_order' => (int) ($raw['sort_order'] ?? $index),
                'enabled' => $this->boolString($raw['enabled'] ?? '1'),
                'cta_label' => $this->sanitize($raw['cta_label'] ?? ''),
                'cta_url' => $this->sanitizeUrl($raw['link'] ?? $raw['cta_url'] ?? ''),
                'link' => $this->sanitizeUrl($raw['link'] ?? $raw['cta_url'] ?? ''),
                'badge' => $this->sanitize($raw['badge'] ?? ''),
            ];
        }

        usort($normalized, static fn (array $a, array $b): int => $a['sort_order'] <=> $b['sort_order']);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $max = (int) config('jetpk_homepage.max_destinations', 12);
        if (count($normalized) > $max) {
            throw ValidationException::withMessages([
                'content.destinations.items' => "Maximum {$max} destination cards allowed.",
            ]);
        }

        return $normalized;
    }

    /**
     * @param  list<mixed>  $items
     * @return list<array<string, mixed>>
     */
    private function normalizeFeaturedDeals(array $items): array
    {
        $normalized = [];
        $errors = [];

        foreach (array_values($items) as $index => $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $from = strtoupper(trim((string) ($raw['from'] ?? '')));
            $to = strtoupper(trim((string) ($raw['to'] ?? '')));
            $airline = $this->sanitize($raw['airline'] ?? '');
            if ($airline === '' && $from === '' && $to === '') {
                continue;
            }

            if ($from !== '' && ! preg_match('/^[A-Z]{3}$/', $from)) {
                $errors["content.featured_deals.items.{$index}.from"] = 'Origin must be a 3-letter IATA code.';
            }
            if ($to !== '' && ! preg_match('/^[A-Z]{3}$/', $to)) {
                $errors["content.featured_deals.items.{$index}.to"] = 'Destination must be a 3-letter IATA code.';
            }

            $price = $this->optionalPositivePrice($raw['price'] ?? null);
            if ($price === false) {
                $errors["content.featured_deals.items.{$index}.price"] = 'Price must be a positive number when provided.';
            }

            $normalized[] = [
                'airline' => $airline,
                'from' => $from,
                'to' => $to,
                'depart' => $this->sanitize($raw['depart'] ?? ''),
                'arrive' => $this->sanitize($raw['arrive'] ?? ''),
                'dur' => $this->sanitize($raw['dur'] ?? ''),
                'stops' => max(0, min(9, (int) ($raw['stops'] ?? 0))),
                'price' => $price === false ? 0 : (int) round((float) ($price ?? 0)),
                'sort_order' => (int) ($raw['sort_order'] ?? $index),
                'enabled' => $this->boolString($raw['enabled'] ?? '1'),
            ];
        }

        usort($normalized, static fn (array $a, array $b): int => $a['sort_order'] <=> $b['sort_order']);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $max = (int) config('jetpk_homepage.max_featured_deals', 6);
        if (count($normalized) > $max) {
            throw ValidationException::withMessages([
                'content.featured_deals.items' => "Maximum {$max} featured deal cards allowed.",
            ]);
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $support
     * @return array<string, mixed>
     */
    private function normalizeSupportCta(array $support): array
    {
        $errors = [];
        $phone = $this->sanitize($support['phone_value'] ?? '');
        if ($phone !== '' && ! preg_match('/^[\d\s+\-()]+$/', $phone)) {
            $errors['content.support_cta.phone_value'] = 'Phone number contains invalid characters.';
        }

        $callUrl = $this->sanitizeUrl($support['call_url'] ?? '');
        if ($callUrl !== '') {
            $callUrl = $this->normalizeCallSupportUrl($callUrl);
            if (! $this->isSafeCallSupportUrl($callUrl)) {
                $errors['content.support_cta.call_url'] = 'Call support URL must be a valid relative, https, or telephone link.';
            }
        }
        $support['call_url'] = $callUrl;

        foreach (['cta_link' => 'CTA link', 'chat_url' => 'Live chat URL'] as $field => $label) {
            $url = $this->sanitizeUrl($support[$field] ?? '');
            if ($url !== '' && ! $this->isSafeUrl($url)) {
                $errors["content.support_cta.{$field}"] = "{$label} must be a valid relative or https URL.";
            }
            $support[$field] = $url;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $support['phone_value'] = $phone;
        $support['call_enabled'] = $this->boolString($support['call_enabled'] ?? '1');
        $support['chat_enabled'] = $this->boolString($support['chat_enabled'] ?? '1');
        $backgroundMode = (string) ($support['background_mode'] ?? 'gradient');
        $support['background_mode'] = in_array($backgroundMode, ['uploaded', 'gradient', 'uploaded_overlay'], true)
            ? $backgroundMode
            : 'gradient';

        return $support;
    }

    private function isValidIata(string $code): bool
    {
        if (! preg_match('/^[A-Z]{3}$/', $code)) {
            return false;
        }

        return Airport::query()->active()->where('iata_code', $code)->exists();
    }

    private function optionalPositivePrice(mixed $value): float|string|null|false
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = preg_replace('/[^\d.]/', '', $value) ?? '';
        }

        $amount = (float) $value;

        return $amount > 0 ? $amount : false;
    }

    private function sanitize(mixed $value): string
    {
        $decoded = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(strip_tags($decoded));
    }

    private function sanitizeUrl(mixed $value): string
    {
        return trim(strip_tags((string) $value));
    }

    private function normalizeCallSupportUrl(string $url): string
    {
        $url = trim($url);
        if (! str_starts_with(strtolower($url), 'tel:')) {
            return $url;
        }

        return 'tel:'.trim(substr($url, 4));
    }

    private function isSafeCallSupportUrl(string $url): bool
    {
        if ($url === '') {
            return true;
        }

        if (str_starts_with(strtolower($url), 'tel:')) {
            return $this->isSafeTelephoneUrl($url);
        }

        return $this->isSafeUrl($url);
    }

    private function isSafeTelephoneUrl(string $url): bool
    {
        if (! str_starts_with(strtolower($url), 'tel:')) {
            return false;
        }

        $number = trim(substr($url, 4));
        if ($number === '') {
            return false;
        }

        if (preg_match('/[a-z]/i', $number)) {
            return false;
        }

        $normalized = preg_replace('/[\s\-().]/', '', $number) ?? '';
        if ($normalized === '' || ! preg_match('/^\+?\d{7,15}$/', $normalized)) {
            return false;
        }

        return true;
    }

    private function sanitizeAssetKey(mixed $value): string
    {
        $key = Str::slug((string) $value, '_');

        return preg_match('/^[a-z0-9_\-]{1,64}$/', $key) ? $key : '';
    }

    private function isSafeUrl(string $url): bool
    {
        if ($url === '') {
            return true;
        }

        if (str_starts_with($url, '//')) {
            return false;
        }

        $lower = strtolower($url);
        if (str_starts_with($lower, 'javascript:') || str_starts_with($lower, 'data:')) {
            return false;
        }

        if (str_starts_with($url, '/')) {
            return true;
        }

        return (bool) filter_var($url, FILTER_VALIDATE_URL) && str_starts_with($lower, 'https://');
    }

    private function boolString(mixed $value): string
    {
        return in_array((string) $value, ['1', 'true', 'yes', 'on'], true) ? '1' : '0';
    }
}
