<?php

namespace App\Http\Resources\Dashboard;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Support\Suppliers\SabreSupplierChannelConfig;
use App\Support\Suppliers\SupplierSourcePresenter;

final class DashboardSupplierResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fromModel(SupplierConnection $connection): array
    {
        $provider = $connection->provider;
        $bookingCount = (int) ($connection->supplier_bookings_count ?? 0);
        $currency = 'PKR';

        return [
            'id' => self::publicId($connection),
            'supplierName' => self::displayName($connection),
            'displayCode' => self::displayCode($connection),
            'supplierCategory' => self::category($provider),
            'operatingRegion' => 'Pakistan',
            'operationalStatus' => $connection->isActive() ? 'Active' : 'Inactive',
            'integrationStatus' => self::integrationStatus($connection),
            'credentialStatus' => self::credentialStatus($connection),
            'settlementStatus' => 'Not Applicable',
            'currency' => $currency,
            'bookingCount' => $bookingCount,
            'confirmedBookingCount' => 0,
            'failedBookingCount' => 0,
            'totalBookingValue' => 0,
            'totalPaidToSupplier' => 0,
            'outstandingSettlement' => 0,
            'refundExposure' => 0,
            'lastBookingActivity' => $connection->updated_at?->toIso8601String(),
            'lastSettlementActivity' => null,
            'createdDate' => $connection->created_at?->format('Y-m-d') ?? '',
            'supportContact' => '—',
            'escalationContact' => '—',
            'linkedBookingIds' => [],
            'linkedTransactionIds' => [],
            'notesSummary' => self::notesSummary($connection),
            'provider' => $provider->value,
            'channel' => self::primaryChannel($connection),
            'environment' => $connection->environment?->value ?? 'sandbox',
            'healthSummary' => self::healthSummary($connection),
            'lastSuccessfulActivity' => $connection->last_tested_at?->toIso8601String(),
            'lastFailureSummary' => self::safeFailureSummary($connection),
            'configured' => $connection->credentials !== null,
            'enabled' => $connection->isActive(),
            'capabilities' => self::capabilities($connection),
            'resultSourceState' => self::resultSourceState($connection),
            'channelStates' => self::channelStates($connection),
            'reviewFlags' => [
                'needsReview' => ! $connection->supplierHealthHealthy() && $connection->isActive(),
                'credentialsMissing' => $connection->credentials === null,
            ],
        ];
    }

    public static function publicId(SupplierConnection $connection): string
    {
        return 'SC-'.str_pad((string) $connection->id, 5, '0', STR_PAD_LEFT);
    }

    protected static function displayName(SupplierConnection $connection): string
    {
        $label = trim((string) ($connection->display_name ?? ''));
        if ($label !== '') {
            return $label;
        }

        if ($connection->provider === SupplierProvider::Sabre) {
            return SabreSupplierChannelConfig::connectionAdminLabel($connection);
        }

        return SupplierSourcePresenter::label($connection->provider->value);
    }

    protected static function displayCode(SupplierConnection $connection): string
    {
        return match ($connection->provider) {
            SupplierProvider::Sabre => 'SBR',
            SupplierProvider::Duffel => 'DFL',
            SupplierProvider::PiaNdc => 'PIA',
            SupplierProvider::Airblue => 'ABQ',
            SupplierProvider::Iati => 'IAT',
            SupplierProvider::Amadeus => 'AMA',
            SupplierProvider::Travelport => 'TVP',
            default => strtoupper(substr($connection->provider->value, 0, 3)),
        };
    }

    protected static function category(SupplierProvider $provider): string
    {
        return match ($provider) {
            SupplierProvider::Sabre, SupplierProvider::Amadeus, SupplierProvider::Travelport => 'GDS',
            SupplierProvider::Duffel, SupplierProvider::PiaNdc => 'NDC',
            SupplierProvider::Airblue, SupplierProvider::AirlineDirect => 'Airline',
            default => 'Ancillary Service',
        };
    }

    protected static function integrationStatus(SupplierConnection $connection): string
    {
        if (! $connection->isActive()) {
            return 'Disabled';
        }
        if (! $connection->supplierHealthHealthy()) {
            return 'Degraded';
        }
        if ($connection->credentials === null) {
            return 'Mock Only';
        }

        return 'Connected';
    }

    protected static function credentialStatus(SupplierConnection $connection): string
    {
        if ($connection->credentials === null) {
            return 'Missing';
        }

        return 'Configured';
    }

    protected static function healthSummary(SupplierConnection $connection): string
    {
        if ($connection->supplierHealthHealthy()) {
            return 'healthy';
        }
        if ($connection->last_test_status) {
            return 'degraded';
        }

        return 'unknown';
    }

    protected static function safeFailureSummary(SupplierConnection $connection): ?string
    {
        $error = trim((string) ($connection->last_error ?? ''));
        if ($error === '') {
            return null;
        }

        $sanitized = preg_replace('/\b(pcc|lniata|password|token|secret|api[_-]?key)\b/i', '[redacted]', $error);

        return mb_substr((string) $sanitized, 0, 200);
    }

    protected static function primaryChannel(SupplierConnection $connection): string
    {
        if ($connection->provider !== SupplierProvider::Sabre) {
            return strtolower($connection->provider->value);
        }

        $config = SabreSupplierChannelConfig::fromConnection($connection);
        if ($config->gdsEnabled && $config->ndcEnabled) {
            return 'sabre_multi';
        }
        if ($config->gdsEnabled) {
            return 'gds';
        }
        if ($config->ndcEnabled) {
            return 'ndc';
        }

        return 'sabre_off';
    }

    /**
     * @return list<string>
     */
    protected static function capabilities(SupplierConnection $connection): array
    {
        $caps = ['search'];
        if ($connection->isEligibleForSupplierSearch()) {
            $caps[] = 'booking';
        }
        if ($connection->provider === SupplierProvider::Sabre) {
            $config = SabreSupplierChannelConfig::fromConnection($connection);
            if ($config->gdsEnabled) {
                $caps[] = 'gds_pnr';
            }
            if ($config->ndcEnabled) {
                $caps[] = 'ndc_order';
            }
        }

        return $caps;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function resultSourceState(SupplierConnection $connection): array
    {
        if ($connection->provider !== SupplierProvider::Sabre) {
            return [
                'primary' => $connection->provider->value,
                'gds' => null,
                'ndc' => null,
            ];
        }

        $config = SabreSupplierChannelConfig::fromConnection($connection);

        return [
            'primary' => $config->gdsEnabled ? 'gds' : ($config->ndcEnabled ? 'ndc' : 'disabled'),
            'gds' => $config->gdsEnabled ? 'enabled' : 'disabled',
            'ndc' => $config->ndcEnabled ? 'enabled' : 'disabled',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected static function channelStates(SupplierConnection $connection): ?array
    {
        if ($connection->provider !== SupplierProvider::Sabre) {
            return null;
        }

        $config = SabreSupplierChannelConfig::fromConnection($connection);

        return [
            'gds' => [
                'enabled' => $config->gdsEnabled,
                'health' => $config->gdsEnabled ? self::healthSummary($connection) : 'not_applicable',
                'resultSource' => 'gds',
            ],
            'ndc' => [
                'enabled' => $config->ndcEnabled,
                'health' => $config->ndcEnabled ? self::healthSummary($connection) : 'not_applicable',
                'resultSource' => 'ndc',
            ],
            'sharedAuthentication' => $connection->credentials !== null,
        ];
    }

    protected static function notesSummary(SupplierConnection $connection): string
    {
        $env = $connection->environment?->value ?? 'sandbox';

        return sprintf(
            '%s connection (%s) — read-only dashboard summary.',
            self::displayName($connection),
            $env,
        );
    }
}
