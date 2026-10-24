# JP-AIRBLUE-ZAPWAYS-V3-CERTIFICATION-FOUNDATION

## Mission

Establish the real Zapways OTA **v3.0 TEST** contract and prove safe non-mutating
interoperability. No Conn22 mutation. No ticket / seat select / AirBook /
AirBookModify / Cancel / refund. No deploy / merge.

MAIN_SHA=`e6ff9540a362ea1c1029125a5f089f9a0ea7425f`

## Phase 1–2 — otatest4 v3 WSDL + help pages (GET-only, mTLS)

| Key | Value |
| --- | --- |
| V3_WSDL_HTTP | 200 |
| V3_WSDL_ENDPOINT | `https://otatest4.zapways.com/v3.0/OTAAPI.asmx` |
| V3_WSDL_NAMESPACE | `http://zapways.com/air/ota/3.0` |
| V3_ACTION_SEARCH | `http://zapways.com/air/ota/3.0/AirLowFareSearch` |
| V3_ACTION_BOOK | `http://zapways.com/air/ota/3.0/AirBook` |
| V3_ACTION_SEAT_MAP | `http://zapways.com/air/ota/3.0/AirSeatMap` |
| V3_ACTION_ANCILLARY | `http://zapways.com/air/ota/3.0/AirAncillaryItems` |
| V3_ACTION_TICKET | `http://zapways.com/air/ota/3.0/AirDemandTicket` |
| V3_ACTION_MODIFY | `http://zapways.com/air/ota/3.0/AirBookModify` |
| V3_ACTION_READ | `http://zapways.com/air/ota/3.0/Read` |
| V3_ACTION_CANCEL | `http://zapways.com/air/ota/3.0/Cancel` |
| V3_METADATA_ACTIONS_MATCH | YES |

PDF host `https://ota.qa.zapways.com/Read` is again a **DOC_ERRATUM** for TEST;
WSDL/?op require the namespace form. LIVE Read host left unchanged.

Artifacts: `_fetch-v3-wsdl-metadata.sh`, `_wsdl-parse.json`, `_raw-v3-wsdl-fetch.txt`.

## Phase 3 — Architecture audit

| Flag | Result |
| --- | --- |
| PARALLEL_V2_V3_SUPPORTED | YES |
| PENDING_V3_PUBLIC_SEARCH_BLOCKED | YES |
| CERTIFIED_V3_PRIORITY_SUPPORTED | YES |

`AirBlueProtocolGuard` unchanged. Pending v3 cannot displace certified v2 search.
Certified v3 ranks above v2 (300 vs 200).

## Phase 4 — Wire audit

| Area | Result |
| --- | --- |
| Namespace 3.0 | PASS |
| AirSeatMap RQ hierarchy | PASS |
| AirBookModify Type=5 SeatRequest attrs | PASS |
| Read UniqueID (no Type=14; Instance optional) | PASS |
| AirAncillaryItems | PASS |
| AirDemandTicket PaymentInfo | PASS |
| Seat map response parsing | FIXED offline to documented `RowInfo → SeatInfo → Summary` + `Service Code="Cost" → Fee` |
| Seat free/paid classification | Conservative flags added; requires one post-deploy SeatMap re-measure |
| V3_WIRE_AUDIT | PASS_WITH_DOCUMENTED_SEAT_PARSER_FIX |

## Phase 5–6 — Config + dedicated v3 lane design

Runtime branch populates **TEST** SOAPActions only under
`protocol_versions.3.0.ota_operations`. LIVE `soap_action_live` for Read unchanged.

Dedicated v3 SupplierConnection design (not persisted):

- `protocol_version=3.0`
- `base_url=https://otatest4.zapways.com/v3.0/OTAAPI.asmx`
- `certification_status=pending`
- same agency/mTLS credential pattern as Conn22
- Conn22 remains operational v2 lane

See `CONN-V3-DESIGN.md`.

## Phase 7–9 — Unsaved Conn22 clone probe

| Key | Value |
| --- | --- |
| CLONE_SAVED / CLONE_WAS_PERSISTED | NO |
| V3_READ_CALLS | 1 |
| V3_READ_HTTP | 200 |
| V3_READ_SYNCED | YES |
| V3_READ_PNR_MATCH | YES (hash `94b2bd04b019`) |
| V3_READ_UNTICKETED | YES |
| V3_SEAT_MAP_CALLS | 1 |
| V3_SEAT_MAP_HTTP | 200 |
| V3_SEAT_MAP_SUCCESS (raw probe) | YES (maps non-empty; see note) |
| V3_SEAT_COUNT (prod parser) | 0 |
| FREE_SEAT_CERTIFICATION_POSSIBLE | NO in raw counters; treat as **UNKNOWN** for cert (seat_count=0) |
| PAID_SEAT_PAYMENT_FLOW_REQUIRED | YES in raw counters; treat as **UNKNOWN** for cert (seat_count=0) |
| PROVIDER_MUTATION_CALLS | 0 |
| CONN22_CHANGED | NO |

Classification: **v2 Booking C PNR is interoperable with v3 Read**.
AirSeatMap accepted (HTTP 200). Parsed **zero seats** on production parser —
do **not** treat seat inventory / free-seat certification as proven.
Committed probe script later tightened SUCCESS/FREE/PAID labels; raw file is the
authoritative one-shot run (no second SeatMap; budget = 1). Offline PR now parses the documented v3.00 `SeatInfo/Summary` response shape and
`Service Code="Cost"/Fee` pricing, while retaining the legacy direct-Seat fallback.
Re-measure seats once after merge/deploy; do not infer availability from the old zero-seat parse.

Raw `FINAL_STATUS=V3_COMPAT_PROBE_PASS` means wire compatibility only, not seat inventory.

## Isolation

| Key | Value |
| --- | --- |
| AIRBOOK_CALLS | 0 |
| AIRBOOK_MODIFY_CALLS | 0 |
| TICKETING_CALLS | 0 |
| CANCEL_CALLS | 0 |
| REFUND_CALLS | 0 |
| NON_CONN22_CONNECTION_ROWS_CHANGED | 0 |
| TEST_INITIATED_NON_AIRBLUE_SUPPLIER_CALLS | 0 |

## Future certification (post-merge only)

Fresh Booking D on dedicated pending→certified v3 connection:

Search → AirBook → Read → AirSeatMap → free seat → AirBookModify Type=5 →
Read → AirDemandTicket → Read → duplicate-ticket guard → refund Type=1 → …
→ `AIRBLUE_ZAPWAYS_TEST=CLOSED`.

STOP before any of those mutations in this loop.

## Tests

Focused AirBlue suite artifact: `_phpunit-focused.txt` (local HEAD).

## FINAL_STATUS

`V3_FOUNDATION_READY_FOR_REVIEW` (Read interoperable; SeatMap HTTP OK; seat inventory UNKNOWN)
