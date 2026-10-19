<?php

namespace App\Support\Bookings;

use Illuminate\Database\Eloquent\Builder;

/**
 * Exclude disposable QA / local_qa fixtures from operational KPI aggregates.
 */
final class QaFixtureBookingScope
{
    /**
     * @param  Builder<\App\Models\Booking>  $query
     * @return Builder<\App\Models\Booking>
     */
    public static function excludeFromOperationalMetrics(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $inner): void {
                $inner->whereNull('supplier')
                    ->orWhere('supplier', '!=', 'local_qa_inert');
            })
            ->where(function (Builder $inner): void {
                $inner->whereNull('meta')
                    ->orWhereNull('meta->qa_fixture')
                    ->orWhere('meta->qa_fixture', false)
                    ->orWhere('meta->qa_fixture', 0)
                    ->orWhere('meta->qa_fixture', '0')
                    ->orWhere('meta->qa_fixture', 'false');
            });
    }

    public static function isQaFixture(?array $meta, ?string $supplier): bool
    {
        if ($supplier === 'local_qa_inert') {
            return true;
        }

        if (! is_array($meta)) {
            return false;
        }

        $flag = $meta['qa_fixture'] ?? false;

        return $flag === true || $flag === 1 || $flag === '1' || $flag === 'true';
    }
}
