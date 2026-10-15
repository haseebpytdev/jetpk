<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * OPS-DEF-0013: DB-level gateway payment settlement idempotency.
 *
 * A) booking_payments.payment_transaction_id — nullable FK, UNIQUE
 *    NULL = manual / non-gateway / historical rows (unlimited)
 *    non-NULL = at most one BookingPayment per PaymentTransaction
 *
 * B) UNIQUE(payment_transactions.gateway, environment, gateway_order_id)
 *    Multiple NULL gateway_order_id values remain allowed (MySQL/MariaDB/SQLite).
 *
 * Refuses when non-null provider-order duplicates exist or AbhiPay backfill is ambiguous.
 */
return new class extends Migration
{
    public const BOOKING_PAYMENT_TX_UNIQUE = 'booking_payments_payment_transaction_id_unique';

    public const BOOKING_PAYMENT_TX_FK = 'booking_payments_payment_transaction_id_foreign';

    public const PROVIDER_ORDER_UNIQUE = 'payment_transactions_gateway_env_order_unique';

    public const LEGACY_GATEWAY_ORDER_INDEX = 'payment_transactions_gateway_order_id_index';

    public function up(): void
    {
        $duplicateProviderOrders = DB::table('payment_transactions')
            ->select('gateway', 'environment', 'gateway_order_id', DB::raw('COUNT(*) as c'))
            ->whereNotNull('gateway_order_id')
            ->where('gateway_order_id', '!=', '')
            ->groupBy('gateway', 'environment', 'gateway_order_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicateProviderOrders->isNotEmpty()) {
            $sample = $duplicateProviderOrders->take(5)->map(static function ($row): string {
                return sprintf(
                    '%s/%s/%s=%d',
                    (string) $row->gateway,
                    (string) $row->environment,
                    (string) $row->gateway_order_id,
                    (int) $row->c,
                );
            })->implode('; ');

            throw new \RuntimeException(
                'OPS-DEF-0013 migration refused: duplicate non-null gateway_order_id groups exist. '.
                'Manual reconciliation required before UNIQUE(gateway,environment,gateway_order_id). sample='.$sample
            );
        }

        Schema::table('booking_payments', function (Blueprint $table): void {
            $table->foreignId('payment_transaction_id')
                ->nullable()
                ->after('booking_id')
                ->constrained('payment_transactions')
                ->nullOnDelete();
        });

        $this->backfillGatewayBookingPayments();

        Schema::table('booking_payments', function (Blueprint $table): void {
            $table->unique('payment_transaction_id', self::BOOKING_PAYMENT_TX_UNIQUE);
        });

        Schema::table('payment_transactions', function (Blueprint $table): void {
            if ($this->hasIndex('payment_transactions', self::LEGACY_GATEWAY_ORDER_INDEX)) {
                $table->dropIndex(self::LEGACY_GATEWAY_ORDER_INDEX);
            }

            $table->unique(
                ['gateway', 'environment', 'gateway_order_id'],
                self::PROVIDER_ORDER_UNIQUE
            );
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table): void {
            if ($this->hasIndex('payment_transactions', self::PROVIDER_ORDER_UNIQUE)) {
                $table->dropUnique(self::PROVIDER_ORDER_UNIQUE);
            }

            if (! $this->hasIndex('payment_transactions', self::LEGACY_GATEWAY_ORDER_INDEX)) {
                $table->index('gateway_order_id', self::LEGACY_GATEWAY_ORDER_INDEX);
            }
        });

        Schema::table('booking_payments', function (Blueprint $table): void {
            if ($this->hasIndex('booking_payments', self::BOOKING_PAYMENT_TX_UNIQUE)) {
                $table->dropUnique(self::BOOKING_PAYMENT_TX_UNIQUE);
            }

            if (Schema::hasColumn('booking_payments', 'payment_transaction_id')) {
                $table->dropConstrainedForeignId('payment_transaction_id');
            }
        });
    }

    protected function backfillGatewayBookingPayments(): void
    {
        $candidates = DB::table('booking_payments')
            ->where('method', 'abhipay')
            ->whereNotNull('payment_reference')
            ->where('payment_reference', '!=', '')
            ->whereNull('payment_transaction_id')
            ->get(['id', 'booking_id', 'payment_reference', 'meta']);

        foreach ($candidates as $payment) {
            $matches = DB::table('payment_transactions')
                ->where('client_transaction_id', $payment->payment_reference)
                ->get(['id', 'booking_id', 'uuid']);

            if ($matches->isEmpty()) {
                continue;
            }

            if ($matches->count() > 1) {
                throw new \RuntimeException(
                    'OPS-DEF-0013 migration refused: ambiguous PaymentTransaction match for booking_payment id='.
                    (int) $payment->id.' payment_reference='.(string) $payment->payment_reference.
                    ' matches='.$matches->count()
                );
            }

            $tx = $matches->first();

            if ($payment->booking_id !== null && $tx->booking_id !== null
                && (int) $payment->booking_id !== (int) $tx->booking_id) {
                throw new \RuntimeException(
                    'OPS-DEF-0013 migration refused: booking_id mismatch for booking_payment id='.
                    (int) $payment->id.' vs payment_transaction id='.(int) $tx->id
                );
            }

            $metaUuid = $this->metaPaymentTransactionUuid($payment->meta);
            if ($metaUuid !== null && $metaUuid !== (string) $tx->uuid) {
                throw new \RuntimeException(
                    'OPS-DEF-0013 migration refused: meta.payment_transaction_uuid mismatch for booking_payment id='.
                    (int) $payment->id
                );
            }

            $alreadyLinked = DB::table('booking_payments')
                ->where('payment_transaction_id', $tx->id)
                ->where('id', '!=', $payment->id)
                ->exists();

            if ($alreadyLinked) {
                throw new \RuntimeException(
                    'OPS-DEF-0013 migration refused: payment_transaction id='.(int) $tx->id.
                    ' already linked to another booking_payment; ambiguous settlement history.'
                );
            }

            DB::table('booking_payments')
                ->where('id', $payment->id)
                ->update(['payment_transaction_id' => $tx->id]);
        }
    }

    protected function metaPaymentTransactionUuid(mixed $meta): ?string
    {
        if ($meta === null || $meta === '') {
            return null;
        }

        if (is_string($meta)) {
            $decoded = json_decode($meta, true);
            if (! is_array($decoded)) {
                return null;
            }
            $meta = $decoded;
        }

        if (! is_array($meta)) {
            return null;
        }

        $uuid = $meta['payment_transaction_uuid'] ?? null;

        return filled($uuid) ? (string) $uuid : null;
    }

    protected function hasIndex(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = DB::select('PRAGMA index_list('.$table.')');
            foreach ($indexes as $index) {
                $name = (string) ($index->name ?? '');
                if ($name === $indexName) {
                    return true;
                }
            }

            return false;
        }

        $database = Schema::getConnection()->getDatabaseName();
        $rows = DB::select(
            'SELECT INDEX_NAME FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND INDEX_NAME = ? LIMIT 1',
            [$database, $table, $indexName]
        );

        return $rows !== [];
    }
};
