<?php

namespace App\Support\References;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Central compact public reference generator: uppercase alphanumeric, no dashes/prefixes.
 * Alphabet excludes I, O, 0, 1 to avoid confusion.
 */
class CompactReferenceGenerator
{
    public const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const MAX_UNIQUE_ATTEMPTS = 50;

    /** @var list<string> */
    private const ALLOWED_TARGETS = [
        'bookings.booking_reference',
        'group_bookings.reference',
        'agents.code',
        'booking_payments.payment_reference',
        'booking_refunds.reference',
        'support_tickets.ticket_reference',
        'agent_wallet_transactions.reference',
        'ledger_transactions.transaction_ref',
    ];

    public function generate(int $length = 8, ?string $startsWith = null): string
    {
        if ($length < 1) {
            throw new InvalidArgumentException('Reference length must be at least 1.');
        }

        $prefix = $startsWith !== null ? strtoupper($startsWith) : '';
        if ($prefix !== '' && ! $this->charsAreValid($prefix)) {
            throw new InvalidArgumentException('startsWith contains invalid characters for compact references.');
        }

        if (strlen($prefix) > $length) {
            throw new InvalidArgumentException('startsWith is longer than the requested reference length.');
        }

        $remaining = $length - strlen($prefix);
        $alphabetLength = strlen(self::ALPHABET);
        $suffix = '';

        for ($i = 0; $i < $remaining; $i++) {
            $suffix .= self::ALPHABET[random_int(0, $alphabetLength - 1)];
        }

        return $prefix.$suffix;
    }

    public function generateUnique(string $table, string $column, int $length = 8, ?string $startsWith = null): string
    {
        $this->assertAllowedTarget($table, $column);

        for ($attempt = 0; $attempt < self::MAX_UNIQUE_ATTEMPTS; $attempt++) {
            $reference = $this->generate($length, $startsWith);

            if (! DB::table($table)->where($column, $reference)->exists()) {
                return $reference;
            }
        }

        throw new RuntimeException(sprintf(
            'Unable to generate a unique compact reference for %s.%s after %d attempts (length=%d).',
            $table,
            $column,
            self::MAX_UNIQUE_ATTEMPTS,
            $length,
        ));
    }

    public function matchesCompactFormat(string $value, int $length): bool
    {
        $pattern = '/^[A-Z2-9]{'.$length.'}$/';

        return preg_match($pattern, $value) === 1;
    }

    private function assertAllowedTarget(string $table, string $column): void
    {
        $key = $table.'.'.$column;

        if (! in_array($key, self::ALLOWED_TARGETS, true)) {
            throw new InvalidArgumentException('Compact reference target is not allowlisted: '.$key);
        }
    }

    private function charsAreValid(string $value): bool
    {
        $length = strlen($value);
        for ($i = 0; $i < $length; $i++) {
            if (strpos(self::ALPHABET, $value[$i]) === false) {
                return false;
            }
        }

        return true;
    }
}
