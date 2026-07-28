# One API on-hold and payment

- On-hold book omits fulfillment in SOAP payload
- `OneApiHoldPaymentService` + `OneApiRetrieveService` for read/modify payment
- Live payment modification gated by `ONE_API_LIVE_PAYMENT_MODIFICATION_ENABLED` and confirm flags on probes
