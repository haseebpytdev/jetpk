<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\PromoCode;
use App\Services\Promos\PromoCodeService;
use App\Support\Payments\BookingPayableResolver;
use Illuminate\Console\Command;

class PromosTestCodeCommand extends Command
{
    protected $signature = 'promos:test-code {code : Promo code to validate} {--booking= : Booking id}';

    protected $description = 'Validate a promo code against a booking payable context';

    public function handle(PromoCodeService $promoCodeService): int
    {
        $code = (string) $this->argument('code');
        $bookingId = $this->option('booking');

        if ($bookingId === null) {
            $promo = PromoCode::query()->whereRaw('UPPER(code) = ?', [strtoupper(trim($code))])->first();
            if ($promo === null) {
                $this->error('Promo code not found.');

                return self::FAILURE;
            }
            $preview = $promoCodeService->calculateDiscount($promo, 10000);
            $this->table(['Field', 'Value'], [
                ['code', $promo->code],
                ['type', $promo->type?->value],
                ['value', (string) $promo->value],
                ['preview_original', number_format($preview['original_payable'], 2)],
                ['preview_discount', number_format($preview['discount_amount'], 2)],
                ['preview_final', number_format($preview['final_payable'], 2)],
            ]);

            return self::SUCCESS;
        }

        $booking = Booking::query()->with('fareBreakdown')->findOrFail((int) $bookingId);
        $result = $promoCodeService->validateForBooking($code, $booking);

        $this->info($result->valid ? 'VALID' : 'INVALID');
        foreach ($result->errors as $error) {
            $this->warn($error);
        }

        $this->table(['Field', 'Value'], [
            ['original_payable', number_format($result->originalPayable, 2)],
            ['discount', number_format($result->discountAmount, 2)],
            ['final_payable', number_format($result->finalPayable, 2)],
            ['current_booking_payable', number_format(BookingPayableResolver::customerPayableTotal($booking), 2)],
        ]);

        return $result->valid ? self::SUCCESS : self::FAILURE;
    }
}
