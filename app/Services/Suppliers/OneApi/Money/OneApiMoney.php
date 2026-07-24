<?php

namespace App\Services\Suppliers\OneApi\Money;

/**
 * Money-safe arithmetic for One API supplier amounts (no floats).
 */
final class OneApiMoney
{
    public function __construct(
        public readonly string $amount,
        public readonly string $currency,
        public readonly int $decimalPlaces = 2,
    ) {}

    public static function fromParts(string $amount, string $currency, int $decimalPlaces = 2): self
    {
        $normalized = self::normalizeAmount($amount, $decimalPlaces);

        return new self($normalized, strtoupper(trim($currency)), $decimalPlaces);
    }

    public static function normalizeAmount(string $amount, int $decimalPlaces = 2): string
    {
        $trimmed = trim($amount);
        if ($trimmed === '') {
            return self::zero($decimalPlaces);
        }

        if (! str_contains($trimmed, '.')) {
            return $trimmed.'.'.str_repeat('0', $decimalPlaces);
        }

        [$whole, $frac] = explode('.', $trimmed, 2);
        $frac = substr(str_pad($frac, $decimalPlaces, '0'), 0, $decimalPlaces);

        return $whole.'.'.$frac;
    }

    public static function zero(int $decimalPlaces = 2): string
    {
        return '0.'.str_repeat('0', $decimalPlaces);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);
        $scale = max($this->decimalPlaces, $other->decimalPlaces);
        $sum = bcadd($this->amount, $other->amount, $scale);

        return new self(self::normalizeAmount($sum, $scale), $this->currency, $scale);
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency
            && bccomp($this->amount, $other->amount, max($this->decimalPlaces, $other->decimalPlaces)) === 0;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException('Currency mismatch for One API money operation.');
        }
    }
}
