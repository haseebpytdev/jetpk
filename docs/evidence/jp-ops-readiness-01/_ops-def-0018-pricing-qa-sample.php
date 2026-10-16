<?php
require __DIR__ . "/vendor/autoload.php";
$app = require __DIR__ . "/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\Booking;
use App\Support\Payments\BookingPayableResolver;
use App\Support\Bookings\BookingPaymentSummaryPresenter;
$rows = Booking::query()
  ->with(["fareBreakdown","payments"])
  ->where(function($q){
    $q->where("meta->qa_fixture", true)
      ->orWhere("meta->disposable", true)
      ->orWhere("supplier", "local_qa_inert")
      ->orWhere("meta->supplier_provider", "local_qa_inert");
  })
  ->orderByDesc("id")
  ->limit(8)
  ->get(["id","booking_reference","currency","amount_paid","balance_due","payment_status","promo_code","promo_discount_amount","payable_before_promo","payable_after_promo","meta","status"]);
$out = [];
foreach ($rows as $b) {
  $b->loadMissing(["fareBreakdown","payments"]);
  $fare = BookingPayableResolver::fareTotal($b);
  $pay = BookingPayableResolver::customerPayableTotal($b);
  $sum = BookingPaymentSummaryPresenter::forBooking($b, false);
  $out[] = [
    "id" => $b->id,
    "ref_masked" => substr((string)$b->booking_reference,0,6)."…".substr((string)$b->booking_reference,-4),
    "currency" => $b->currency,
    "fare" => $fare,
    "payable" => $pay,
    "promo" => (float)($b->promo_discount_amount ?? 0),
    "paid" => (float)($b->amount_paid ?? 0),
    "balance" => BookingPayableResolver::balanceDue($b),
    "payment_status" => $b->payment_status,
    "summary_payable" => $sum["customer_payable"],
    "summary_balance" => $sum["balance_due"],
    "inv9" => abs(BookingPayableResolver::balanceDue($b) - max(0, round($pay - BookingPayableResolver::verifiedPaidAmount($b), 2))) < 0.001,
    "inv10" => !(($b->payment_status === "paid") && (BookingPayableResolver::balanceDue($b) != 0 || abs((float)$b->amount_paid - $pay) > 0.001)),
    "payable_eq_summary" => abs($pay - (float)$sum["customer_payable"]) < 0.001,
  ];
}
echo json_encode(["count"=>count($out),"rows"=>$out,"mutations"=>0], JSON_PRETTY_PRINT);
