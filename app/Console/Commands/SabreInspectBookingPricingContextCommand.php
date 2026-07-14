<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use App\Services\Suppliers\Sabre\SabreStoredPricingContextDigest;
use Illuminate\Console\Command;

/**
 * [local/testing only] Safe digest of preserved Sabre shop/pricing scalar context on a booking's stored offer snapshot.
 * Does not call Sabre; does not print raw JSON, PII, or full nested arrays.
 */
class SabreInspectBookingPricingContextCommand extends Command
{
    protected $signature = 'sabre:inspect-booking-pricing-context
                            {--booking= : Booking ID}
                            {--paths : Print safe ref/index scalar key paths from stored snapshots}';

    protected $description = '[local/testing only] Safe stored-offer Sabre pricing context digest (no live HTTP)';

    public function handle(SabreStoredPricingContextDigest $digestor): int
    {
        if (! SabreInspectGate::allowed()) {
            $this->components->error('This command only runs when APP_ENV is local or testing.');

            return self::FAILURE;
        }

        $raw = $this->option('booking');
        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            $this->components->error('Pass --booking={id} with a numeric booking id.');

            return self::FAILURE;
        }

        $booking = Booking::query()->find((int) $raw);
        if ($booking === null) {
            $this->components->error('Booking not found.');

            return self::FAILURE;
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $p = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($p !== SupplierProvider::Sabre->value) {
            $this->line('booking_id='.$booking->id);
            $this->line('error=booking_not_sabre');

            return self::SUCCESS;
        }

        $snapshot = [];
        if (is_array($meta['normalized_offer_snapshot'] ?? null)) {
            $snapshot = $meta['normalized_offer_snapshot'];
        } elseif (is_array($meta['validated_offer_snapshot'] ?? null)) {
            $snapshot = $meta['validated_offer_snapshot'];
        } elseif (is_array($meta['flight_offer_snapshot'] ?? null)) {
            $snapshot = $meta['flight_offer_snapshot'];
        }

        if ($snapshot === []) {
            $this->line('booking_id='.$booking->id);
            $this->line('error=no_offer_snapshot');

            return self::SUCCESS;
        }

        $rawPayload = is_array($snapshot['raw_payload'] ?? null) ? $snapshot['raw_payload'] : [];
        $digest = $digestor->withBookingId($booking->id, $digestor->digest($snapshot));
        $this->line('payload_style='.(string) config('suppliers.sabre.revalidate_payload_style', 'bfm_revalidate_v1'));
        if ($this->option('paths')) {
            foreach ($digestor->collectSafeRefKeyPaths($snapshot, $meta) as $path) {
                $this->line('storage_path='.$path);
            }
            $this->line('sabre_shop_identifier_keys='.$this->capKeyList(
                array_keys(is_array($rawPayload['sabre_shop_identifiers'] ?? null) ? $rawPayload['sabre_shop_identifiers'] : [])
            ));
            $this->line('sabre_shop_context_keys='.$this->capKeyList(
                array_keys(is_array($rawPayload['sabre_shop_context'] ?? null) ? $rawPayload['sabre_shop_context'] : [])
            ));
        }
        $this->printDigestFlat($digest);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $digest
     */
    protected function printDigestFlat(array $digest): void
    {
        foreach ($digest as $k => $v) {
            if ($v === null) {
                $this->line($k.'=');

                continue;
            }
            if (is_bool($v)) {
                $this->line($k.'='.($v ? 'true' : 'false'));

                continue;
            }
            if (is_scalar($v)) {
                $this->line($k.'='.$this->cap((string) $v));

                continue;
            }
            if (is_array($v)) {
                if ($v === []) {
                    $this->line($k.'=');

                    continue;
                }
                if (array_is_list($v)) {
                    $parts = [];
                    foreach (array_slice($v, 0, 20) as $item) {
                        if (is_scalar($item)) {
                            $parts[] = $this->cap((string) $item);
                        }
                    }
                    $this->line($k.'='.implode(', ', $parts));

                    continue;
                }
                foreach ($v as $nk => $nv) {
                    if (is_scalar($nv)) {
                        $this->line($k.'.'.$nk.'='.$this->cap((string) $nv));
                    }
                }
            }
        }
    }

    protected function cap(string $s): string
    {
        $s = trim($s);
        if (strlen($s) <= 100) {
            return $s;
        }

        return substr($s, 0, 100);
    }

    /**
     * @param  list<string>  $keys
     */
    protected function capKeyList(array $keys): string
    {
        sort($keys);

        return implode(', ', array_slice($keys, 0, 32));
    }
}
