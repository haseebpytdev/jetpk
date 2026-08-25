# JP-BO-04G Progressive Remediation — Checkpoint

**Status:** RESUMED — engineering deployed; live certification still blocked on Review/price refresh  
**Checkpoint UTC:** 2026-08-25T18:35:00Z (approx)  
**Branch:** `phase/jp-bo-04g-progressive`

---

## Authoritative pins

| Pin | Value |
| --- | --- |
| PREVIOUS_LIVE_SHA (rollback) | `b08f4ba088ee1483bf76e6a61277f4946c25c478` |
| DEPLOYED_ENGINEERING_SHA | `e93f90f5b57b44daa3486edf728812e44b60b030` |
| CHECKPOINT_DOCS_SHA (historical) | `44f960aff0ae6a7c4cbf6d93e67784781b46086a` |
| NEW_PUBLIC_BUILD | `F1hRrv3NulCyaAh_s0nmw` |
| BACKUP_ID | `jp-bo-04g-final-commerce-20260825T180029Z` |

---

## Resume completion

| Step | Status |
| --- | --- |
| Push checkpoint `44f960af` | DONE |
| Owner A–D engineering | DONE |
| Local tests + Playwright matrix | DONE |
| Protected deploy (14 files) | DONE |
| Live Book Now / brand hide / Change Flight fresh search | DONE (partial proof) |
| Live OW/Pair/Split Review + price refresh closure | **OPEN** |
| Live-proof doc | `JP-BO-04G-PROGRESSIVE-REMEDIATION-LIVE-PROOF.md` |

---

## Left

1. Clear `order-summary-price-refresh` after successful Sabre revalidation on live.
2. Complete OW / Pair / Split to Booking Review (stop before Tier-3).
3. BFCache back + agent/admin badge screenshots.
4. Then owner-authorized Sabre lifecycle preflight only.

`TIER3_READY=NO`
