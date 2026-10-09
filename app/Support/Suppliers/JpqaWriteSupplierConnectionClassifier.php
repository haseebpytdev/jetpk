<?php

namespace App\Support\Suppliers;

use App\Models\SupplierConnection;

/**
 * Classifies JetPakistan dashboard production-certification API connection rows
 * created by JPQA write flows (safe metadata only).
 */
final class JpqaWriteSupplierConnectionClassifier
{
    /** @var list<string> */
    public const PROTECTED_CONNECTION_NAMES = [
        'AirBlue Zapways TEST v2',
        'JPAK Group',
        'PIA JetPK',
        'JetPK Binham Sabre',
        'Sabre Sandbox QA (CERT)',
        'SMTP JetPakistan LIVE',
    ];

    /** @var list<int> */
    public const PROTECTED_CONNECTION_IDS = [
        22,
    ];

    public static function isJpqaWriteCertName(string $name): bool
    {
        return (bool) preg_match('/^JPQA-WRITE-\d+-api$/', trim($name));
    }

    public static function isDeleteCandidate(SupplierConnection $connection): bool
    {
        if (in_array($connection->id, self::PROTECTED_CONNECTION_IDS, true)) {
            return false;
        }

        $name = trim((string) $connection->name);
        if (in_array($name, self::PROTECTED_CONNECTION_NAMES, true)) {
            return false;
        }

        return self::isJpqaWriteCertName($name);
    }

    public static function classification(SupplierConnection $connection): string
    {
        if (in_array($connection->id, self::PROTECTED_CONNECTION_IDS, true)
            || in_array(trim((string) $connection->name), self::PROTECTED_CONNECTION_NAMES, true)) {
            return 'KEEP_REAL';
        }

        if (self::isJpqaWriteCertName((string) $connection->name)) {
            return 'DELETE_QA_CERT';
        }

        return 'REVIEW';
    }
}
