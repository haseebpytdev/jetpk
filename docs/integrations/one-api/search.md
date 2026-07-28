# One API search

- Builder: `OneApiSearchRequestBuilder`
- Service: `OneApiFlightSearchService` + `OneApiRestClient`
- Parser filters `NOT_AVAILABLE` rows and empty `cabinPrices`
- Normalizer: `OneApiResponseNormalizer` → `NormalizedFlightOfferData` with HMAC offer tokens

Return trips set `isReturn=true` and duplicate OND with **return date** on the inbound leg.
