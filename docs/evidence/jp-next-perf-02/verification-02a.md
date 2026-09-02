# JP-NEXT-PERF-02A — Verification closure

## Authority (verified)

| Item | Value |
|------|-------|
| Branch | `phase/jp-flight-perf-01` |
| Local HEAD (start) | `5373089dbaccc13a559861f299e6ce98788ea675` |
| Remote freeze | `1f12edef052da278f02b7ffeaf4e7a881c663ef9` (unchanged; **NO PUSH**) |
| Runtime | `98f92ea9da7017feb99b108267cf174f2b89c896` |
| Public build | `AJ9bvi6_QyxDfAP2TgofV` |
| Engineering fix in 02A | **NO** (verification-only) |

## Gaps closed

| Gap | Status |
|-----|--------|
| One Way N10 | `valid_n=10` → `oneway-n10.json` |
| Return Paired N10 | `valid_n=10` → `return-paired-n10.json` |
| Return Segmented N10 | `valid_n=10` → `return-segmented-n10.json` |
| Groups N20 | cold+warm → `groups-n20.json` |
| Fare→Traveler N10 | `valid_n=10` → `fare-traveler-n10.json` |
| Review N10 | `valid_n=10` → `review-n10.json` |
| Payment shell N10 | `valid_n=10` → `payment-shell-n10.json` |
| Mobile 390 full pack | **PASS** → `02a/mobile-390.json` + screenshots |
| PM2 restart delta | lifetime 103; phase delta 0 → `pm2-restart-delta.md` |
| Rollback retention | **2** → `rollback-retention.md` |
| OLS→Next overhead | `proxy-overhead-precision.md` |
| Cold chunks | `chunk-cold-validation.md` |
| ACK numeric | P50=3 P95=6 → `02a/user-action-ack.json` |

## Key timings (production)

### Groups N20
- Filter ready cold P50/P95: **1413 / 6755** (P95 = single `jpAuditReset` outlier; other cold first-card ≤1725)
- First card warm P50/P95: **1257 / 1931**

### Flights
- One Way total P50/P95: **3215 / 12484** (backend/API proxy P95 8537)
- Return paired total P50/P95: **3687 / 5305**
- Return segmented total P50/P95: **2030 / 2390**
- Local sort/filter P95: **197 / 332**; READY→skeleton: **0**
- View switch: URL `view=` refetch (not local-only); P95 not compared to 500ms local target

### Checkout (read-only stop)
- Fare→Traveler valid 10; total P50/P95 **5744 / 20632**; route shell P95 **6098**; skeleton reset **0**
- Traveler→Review P95 **1172**; blank loading **NO**
- Review→Payment shell P95 **820**; blank loading **NO**

### Mobile 390
`MOBILE_390_FULL_PACK=PASS`, overflow **0**, skeleton reg **0**

## Commercial / health

No booking/PNR/payment/ticket/cancel. MOFA not deployed. Chatwoot not installed. Ask JetPakistan remains public. PM2 public/dashboard online. Rollback packs = 2.
