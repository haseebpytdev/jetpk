<?php

namespace App\Support\Suppliers;

use App\Enums\SupplierConnectionStatus;
use App\Models\SupplierConnection;

/**
 * Detects foundation-seeder placeholder supplier rows safe for optional cleanup.
 */
final class EmptySupplierConnectionPlaceholderInspector
{
    /** @var list<string> */
    public const KNOWN_PLACEHOLDER_NAMES = [
        'Duffel',
        'Sabre',
        'PIA NDC',
        'Airline Direct API',
    ];

    public function isRemovablePlaceholder(SupplierConnection $connection): bool
    {
        if (! $this->credentialsEmpty($connection)) {
            return false;
        }

        if ($connection->is_active) {
            return false;
        }

        if ($connection->status !== SupplierConnectionStatus::Inactive) {
            return false;
        }

        if (! $this->isKnownPlaceholderName($connection)) {
            return false;
        }

        if ($connection->exists) {
            if ($connection->supplierBookings()->exists()) {
                return false;
            }

            if ($connection->supplierBookingAttempts()->exists()) {
                return false;
            }

            if ($connection->latestSearchDiagnostic()->exists()) {
                return false;
            }

            if ($connection->latestOrderDiagnostic()->exists()) {
                return false;
            }
        }

        if ($connection->last_tested_at !== null) {
            return false;
        }

        return true;
    }

    public function credentialsEmpty(SupplierConnection $connection): bool
    {
        $credentials = $connection->credentials;
        if ($credentials === null) {
            return true;
        }

        if (! is_array($credentials) || $credentials === []) {
            return true;
        }

        foreach ($credentials as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    public function isKnownPlaceholderName(SupplierConnection $connection): bool
    {
        $name = trim((string) ($connection->name ?? ''));
        $display = trim((string) ($connection->display_name ?? ''));

        foreach (self::KNOWN_PLACEHOLDER_NAMES as $placeholder) {
            if (strcasecmp($name, $placeholder) === 0 || strcasecmp($display, $placeholder) === 0) {
                return true;
            }
        }

        return false;
    }
}
