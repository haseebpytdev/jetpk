# SABRE-GDS-SHOPPING-OFFER-LOCAL-CLOCK-SOURCE-PARITY-CORRECTION-9

## Root cause (source-parity defect, not genuine schedule change)
`SabreFlightSearchNormalizer::segmentFromScheduleDesc()` built endpoint raw values via `stringifyScheduleDateTime()`, which **preferred `dateTime` over `time`**, and then applied **elapsed block time** to overwrite arrival when local `arrival.time` was present. For QR 629 LHE–DOH this produced shop ISO **15:05** (dateTime/elapsed) while revalidation used authoritative **`arrival.time` = 13:05**.

## Correction
- `SabreGdsBfmScheduleEndpointLocalClock` — shared BFM endpoint clock rules (time before dateTime, wall-clock normalization, evidence).
- Shopping normalizer uses `composeScheduleEndpointRaw()` + skips elapsed arrival override when local arrival time exists.
- Canonical/revalidation delegates to the same helper.
- Snap/offer segment rows carry `bfm_endpoint_clock_evidence`; probe artifacts expose `selected_endpoint_clock_evidence` / `draft_endpoint_clock_evidence`.

## SFTP
- `app/Support/Sabre/Revalidation/SabreGdsBfmScheduleEndpointLocalClock.php`
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationCanonicalSegmentSignature.php`
- `app/Services/Suppliers/Sabre/Gds/SabreFlightSearchNormalizer.php`
- `app/Support/Sabre/Revalidation/SabreGdsRevalidationCanonicalSignatureRuntimePropagation.php`
- `app/Support/Sabre/Scenario/SabreGdsLiveScenarioRevalidationOutcomeMapper.php`

## Post-deploy
Fresh QR revalidation-only probe after a **new shop** (so draft segments pick up corrected clocks).
