# AIRBLUE-08 evidence closure (no new supplier calls)

**FINAL_STATUS:** `FULL_PASS_NO_OFFERS`  
**Authoritative connection:** id **22**, `AirBlue Zapways TEST v2`, Agent ID `JetPakistanOTA`, TEST endpoint.

## Certification stages (unchanged)

| Stage | Result |
|---|---|
| CONFIG | PASS |
| TLS | PASS |
| CONNECTIVITY | PASS |
| AUTH | PASS (credential acceptance via successful certified `AirLowFareSearch`; dummy-PNR Read HTTP 500 is **not** primary auth evidence) |
| SEARCH | PASS |
| OFFERS | NO_RESULTS |

Certified search: **ISB → LHE**, depart **2026-10-23**, `OFFER_COUNT=0`.

## AirLowFareSearch call accounting

| Metric | Value | Notes |
|---|---|---|
| `AIRLOWFARESEARCH_INVOCATIONS_TOTAL` | **3** | All application `airblue:test-search` / equivalent search invocations during ZAPWAYS-08 |
| `PRE_CERT_DIAGNOSTIC_ATTEMPTS` | **2** | Before TLS/runtime fix; default connection id **11** |
| `SUPPLIER_REACHED_SEARCH_CALLS` | **2** | Attempt 1 failed at **mTLS** (`supplier_transport_failed`) — **no evidence** request reached Zapways. Attempt 2 (root PHP, invalid date) returned `provider_error` — **likely** reached supplier. Attempt 3 (connection **22**, `nobody`) certified PASS. |
| `CERTIFIED_SEARCH_CALLS` | **1** | Connection **22** only; used for PASS classification |

`AUTH_EVIDENCE=accepted AirLowFareSearch with no auth/transport fault`

## TLS operations fix (no key material)

- `/home/pkjetp/jetpk_secrets/zapways` was `700` root-only; PHP (`nobody`) could not read cert/key.
- Corrected: directory `750` `root:nogroup`, key `640`, certs `644`.
- OpenSSL mTLS to `otatest4.zapways.com` PASS before/after.
- Path is **outside** application release tree; normal deploy does not reset permissions — **persistent** unless manually reverted.

## Mutation guard (unchanged)

`AIRBOOK_CALLS=0`, `AIRDEMANDTICKET_CALLS=0`, `AIRBOOKMODIFY_CALLS=0`, `CANCEL_CALLS=0`, booking/payment/ticket mutations **0**.

`NEW_ZAPWAYS_CALLS_THIS_LOOP=0` (JP-OPS-09)
