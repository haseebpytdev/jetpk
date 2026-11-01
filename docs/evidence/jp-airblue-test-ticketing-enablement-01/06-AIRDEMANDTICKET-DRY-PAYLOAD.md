# AirDemandTicket dry payload (no send)

Built in-memory via `AirBlueOtaXmlBuilder::buildAirDemandTicketRequest($config, $pnr, $instance, $paymentContext)`.

PaymentContext amount sourced from `airblue_context.supplier_payable_amount` (not customer payable).

| Check | Result |
|-------|--------|
| AIRDEMANDTICKET_DRY_PAYLOAD | PASS |
| protocol / Target=Test | present |
| PaymentInfo PKR / Cash | present |
| SEAT_GATE_FOR_ADT_DRY | PASS (27A Confirmed) |
| SUPPLIER_PAYABLE_AUTHORITY | PASS |
| PAYMENT_CURRENCY | PKR |
| PAYMENT_TYPE | Cash |
| AIRDEMANDTICKET_CALLS | 0 |

No raw XML saved.
