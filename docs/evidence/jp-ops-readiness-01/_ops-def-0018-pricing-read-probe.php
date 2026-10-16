<?php
require __DIR__ . "/vendor/autoload.php";
$app = require __DIR__ . "/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\Booking;
use App\Support\Payments\BookingPayableResolver;
use App\Support\Bookings\BookingPaymentSummaryPresenter;
$b = Booking::with(["fareBreakdown","payments","promoCode"])->find(40);
if (!$b) { echo json_encode(["ok"=>false,"error"=>"not_found"]); exit(1); }
$out = [
  "ok" => true,
  "id" => $b->id,
  "ref_masked" => substr((string)$b->booking_reference,0,6)."…".substr((string)$b->booking_reference,-4),
  "provider" => $b->meta["supplier_provider"] ?? ($b->supplier ?? null),
  "qa_fixture" => (bool)($b->meta["qa_fixture"] ?? false),
  "currency" => $b->currency,
  "fare_total" => BookingPayableResolver::fareTotal($b),
  "payable_before" => BookingPayableResolver::payableBeforePromo($b),
  "promo_discount" => BookingPayableResolver::promoDiscount($b),
  "payable_after" => BookingPayableResolver::customerPayableTotal($b),
  "amount_paid" => (float)($b->amount_paid ?? 0),
  "balance_due_col" => $b->balance_due,
  "balance_due_resolver" => BookingPayableResolver::balanceDue($b),
  "verified_paid" => BookingPayableResolver::verifiedPaidAmount($b),
  "payment_status" => $b->payment_status,
  "booking_status" => $b->status?->value ?? (string)$b->status,
  "fare_breakdown" => [
    "base" => (float)($b->fareBreakdown?->base_fare ?? 0),
    "taxes" => (float)($b->fareBreakdown?->taxes ?? 0),
    "fees" => (float)($b->fareBreakdown?->fees ?? 0),
    "markup" => (float)($b->fareBreakdown?->markup ?? 0),
    "discount" => (float)($b->fareBreakdown?->discount ?? 0),
    "total" => (float)($b->fareBreakdown?->total ?? 0),
    "currency" => $b->fareBreakdown?->currency,
  ],
  "summary" => BookingPaymentSummaryPresenter::forBooking($b, false, "customer"),
  "mutations" => 0,
];
echo json_encode($out, JSON_PRETTY_PRINT);
