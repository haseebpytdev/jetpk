<?php

namespace App\Support\Emails;

use App\Enums\OtaNotificationEvent;
use InvalidArgumentException;

/**
 * Central JetPK operational email event registry (config/jetpk_operational_email.php).
 */
class JetpkOperationalEmailEventRegistry
{
    public static function isJetpkClient(): bool
    {
        return (string) config('jetpk_operational_email.client_slug', config('jetpk_email.client_slug', '')) === 'jetpk';
    }

    public static function assertKnownEvent(string $eventKey): void
    {
        if (JetpkEmailEventContentRegistry::find($eventKey) === null) {
            throw new InvalidArgumentException("Unknown JetPK operational email event key: {$eventKey}");
        }
    }

    public static function requiresPerBucketDelivery(string $eventKey): bool
    {
        return (bool) (config('jetpk_operational_email.per_bucket_delivery')[$eventKey] ?? false);
    }

  /**
   * @return list<string>
   */
    public static function bucketsForEvent(string $eventKey): array
    {
        $policy = config('jetpk_operational_email.recipient_policies')[$eventKey] ?? null;
        if (is_array($policy) && $policy !== []) {
            return array_values($policy);
        }

        $resolverBuckets = \App\Services\Communication\NotificationRecipientResolver::policyBucketsFor($eventKey);
        if ($resolverBuckets !== []) {
            return $resolverBuckets;
        }

        return [];
    }

    public static function variantForBucket(string $eventKey, string $bucket): ?string
    {
        $variants = config('jetpk_operational_email.bucket_variants')[$eventKey] ?? [];

        return is_array($variants) ? ($variants[$bucket] ?? null) : null;
    }

    /**
     * @return array<string, string>
     */
    public static function variantContentOverrides(string $eventKey, string $variant): array
    {
        $variants = config('jetpk_operational_email.variants')[$eventKey] ?? [];

        if (! is_array($variants)) {
            return [];
        }

        $override = $variants[$variant] ?? [];

        return is_array($override) ? $override : [];
    }

    public static function dedupMinutes(): int
    {
        return max(0, (int) config('jetpk_operational_email.dedup_minutes', 5));
    }

    /**
     * @return list<string>
     */
    public static function forbiddenBrandFragments(): array
    {
        $fragments = config('jetpk_operational_email.forbidden_brand_fragments', []);

        return is_array($fragments) ? array_values($fragments) : [];
    }

    /**
     * @return list<string>
     */
    public static function allEventKeys(): array
    {
        return array_map(
            static fn (OtaNotificationEvent $event): string => $event->value,
            OtaNotificationEvent::cases(),
        );
    }
}
