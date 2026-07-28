# One API protocol flow

1. **REST authenticate** — `POST` login/password → `tokenPair` (cached per connection + environment, not stored in DB).
2. **REST search** — `findOndWiseFlightCombinations` with `isReturn`, inbound dates on second OND, `SKIP_OND_MERGE=false`.
3. **SOAP price** — initial `OTA_AirPriceRQ`, optional final price after bundles/ancillaries.
4. **SOAP ancillaries** — baggage, meal, seat map (vendor SOAPAction names).
5. **SOAP book** — paid `DirectBill` includes fulfillment; on-hold omits fulfillment block.
6. **SOAP read / modify** — retrieve PNR; hold payment via modification flow.

Correlation IDs flow through `OneApiCorrelationContext` and the `one-api` log channel (redacted).
