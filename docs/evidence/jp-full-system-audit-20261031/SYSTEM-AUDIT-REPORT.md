# JetPakistan Full System / Business Logic Audit

**Date:** 2026-10-31  
**Host:** https://jetpakistan.pk  
**Evidence root:** `docs/evidence/jp-full-system-audit-20261031/`  
**Start main SHA (audit day):** `2bf821226604869f494c64d11709e741da595dfa`  
**Closure-02 start main:** `d171fb29a01e1af3a9522d2869e31a99f0027ef3`  
**Canonical main after runtime/config-source closure PRs #151–#155:** `c56178a84f4bc3f2124b9d81ed9c890b5382d0f9`  
**Closure-02 posture:** SMTP hygiene + Group inventory stamp + Ask/portal safe smoke + CMS fare freshness + marker split. No booking/ticket/cancel/refund mutation.

## Owner summary

Public flight search is healthy and canonicalized. The retired SMTP supplier row that caused the outage is now **inactive**, while mail remains configured through `MAIL_*` / communication settings. The mobile FAB squeeze remains fixed. Homepage Trending Route CTAs no longer publish past departure dates. Group inventory is current enough to classify **PASS_READ_ONLY** (245 total / 66 future; sync at 2026-10-31 02:00:10). Ask JetPakistan is **PARTIAL** rather than stale: launcher/panel are available and no critical failure was found, but deeper runtime/model prompts remain to be certified. Guest portal gates pass, while authenticated cross-role RBAC remains open. AbhiPay still has **0 gateway rows**. Booking lifecycle is ready to plan, not yet live-certified.

---

## Top-level domain table

| DOMAIN | BUSINESS PURPOSE | CURRENT STATUS | LIVE? | LAST VERIFIED | WHAT WORKS | WHAT DOES NOT | RISK | NEXT ACTION |
|---|---|---|---|---|---|---|---|---|
| Public Website | Marketing + search entry | DONE_CERTIFIED_CURRENT | Y | 2026-10-31 | `/`, search entry, future-dated Trending CTAs | — | Low | Keep freshness regression |
| Authentication | Login/register/reset | PASS_READ_ONLY | Y | 2026-10-31 | Pages load; mail config resolves | Live OTP/reset not mutation-tested | Low | Controlled auth UAT |
| Flight Search | Find fares | DONE_CERTIFIED_CURRENT | Y | 2026-10-31 | Search 200 + results/data 200 + 16 offers after SMTP cleanup | — | Low | Keep retired-provider regression |
| One-Way | OW itineraries | DONE_CERTIFIED_CURRENT | Y | 2026-10-31 | Domestic OW certified | — | Low | Keep regression |
| Return Paired | Round-trip paired | PASS_READ_ONLY | Partial | Source | Code paths exist | Not live-certified today | Med | Controlled return UAT |
| Return Segmented | Split return | PASS_READ_ONLY | Partial | Source | `ReturnSplitComboService` present | Not live-certified today | Med | Controlled UAT |
| Multi-city | Multi-segment | PARTIAL | Partial | Source | Form + inquiry/search paths exist | Live multi-city search not certified | Med | UAT phase |
| Fare Selection | Branded fares | PASS_READ_ONLY | Y* | Source+hist | AirBlue Oct-28 historical certification | Not re-run mutation | Med | Re-cert only if needed |
| Flight Checkout | Pax → pay | NEEDS_CONTROLLED_LIVE_UAT | — | Source | Funnel exists | No live booking this audit | High if assumed done | JP-BOOKING-LIVE-UAT-01 |
| Booking | Persist order | NEEDS_CONTROLLED_LIVE_UAT | — | Source | Models/services present | No mutation | High | Controlled UAT |
| Supplier PNR/Order | Create supplier order | NEEDS_CONTROLLED_LIVE_UAT | — | Source | Adapters present | Gates/credentials vary | High | Per-supplier UAT |
| Ticketing | Issue tickets | NEEDS_CONTROLLED_LIVE_UAT | — | Hist AirBlue | Code + historical evidence | Not current live | High | Approved ticket UAT |
| Cancel | Cancel PNR/order | NEEDS_CONTROLLED_LIVE_UAT | — | Source | Adapter methods exist | Not current live-tested | High | Approved cancel UAT |
| Refund | Refund money/tickets | NEEDS_CONTROLLED_LIVE_UAT | — | Source | Partial per supplier | Not current live-tested | High | Finance UAT |
| Group Search | Search group inventory | PASS_READ_ONLY | Y | 2026-10-31 | `/groups/search` + D3 read-only API split | Booking mutation not tested | Low/Med | Preserve |
| Group Inventory | Synced packages | PASS_READ_ONLY | Y | 2026-10-31 | 245 rows; 66 active/future; last sync 02:00:10 | Supplier contribution detail can be deepened | Med | Optional Ameer contribution stamp |
| Group Booking | Book group package | NEEDS_CONTROLLED_LIVE_UAT | — | Source | Funnel exists; booking gates retained | Live reserve not run | Med | Config + UAT |
| Payments | Manual/online pay | PARTIAL | Y | 2026-10-31 | Manual/proof architecture | Online gateway absent | High commercial | Decide/configure AbhiPay |
| AbhiPay | Online gateway | BLOCKED_CONFIGURATION | N | 2026-10-31 | Code+routes present | **PAYMENT_GATEWAY_ROWS=0** | High | JP-ABHIPAY-CONFIG-01 |
| Admin | Platform ops | PASS_READ_ONLY | Y | Source/live guest gates | Dashboard/routes exist | Full authenticated RBAC matrix not run | Med | JP-PORTAL-RBAC-AUTH-01 |
| Staff | Ops staff | PASS_READ_ONLY | Y | Source | Routes/role middleware exist | Authenticated role smoke pending | Med | Role smoke |
| Agent | B2B agents | PASS_READ_ONLY | Y | Source | Agent routes/modules | Live agent booking not run | Med | Agent UAT |
| Customer | B2C portal | PASS_READ_ONLY | Y | Source | Customer routes | Authenticated account flow depth pending | Med | Customer smoke |
| CMS | Homepage content | PARTIAL | Y | 2026-10-31 | Trending CTA stale-date defect fixed live and in source | Broader CMS mutation/publish matrix not re-certified this loop | Low/Med | Keep freshness regression |
| Email | Notifications | PASS_READ_ONLY | Y | 2026-10-31 | `MAIL_MAILER=smtp`; SMTP supplier row not required | Bulk send not run | Med | Safe mailbox test when needed |
| Ask JetPakistan | AI assistant | PARTIAL | Y UI | 2026-10-31 | Launcher/panel available; no critical failure found | Deeper planner/composer/model prompt certification incomplete | Med | JP-AI-SMOKE-02 |
| AI Lab | Lab gateway | INTENTIONALLY_DISABLED | ? | Source/defaults | Adapter/lab code exists | Intentionally not enabled | Low | Leave off unless explicitly owned |
| Scheduler | Cron jobs | PASS_READ_ONLY | Y | 2026-10-31 | Group sync executed at 02:00:10; scheduler active | Not every job individually certified | Med | Spot-check critical jobs |
| Queue | Async jobs | PASS_READ_ONLY | Y | 2026-10-31 | Database queue architecture present | Full failed-job depth not re-audited here | Med | Ops inventory |
| Reporting | Ops reports | PASS_READ_ONLY | Source | Commands/routes exist | Delivery not fully verified | Low | Spot-check |
| Security | AuthZ/CSRF | PASS_READ_ONLY | Y | Source + guest gate smoke | Guest protections pass | Authenticated cross-role matrix pending | Med | JP-PORTAL-RBAC-AUTH-01 |
| Production Infrastructure | Runtime/deploy provenance | PARTIAL | Y | 2026-10-31 | /up/PM2 healthy; split-marker scripts merged in PR #155 | New markers take effect on next applicable deploy | Med | Verify marker outputs on next FE/app deploy |

\*Fare selection is historically certified for AirBlue; today's public search probe was non-mutating.

---

## Status counts — reconciled to the 32 domain rows above

| Status | Count |
|---|---:|
| DONE_CERTIFIED_CURRENT | 3 |
| PASS_READ_ONLY | 15 |
| PARTIAL | 5 |
| BROKEN | 0 |
| BLOCKED_CONFIGURATION | 1 |
| BLOCKED_EXTERNAL | 0 |
| NEEDS_CONTROLLED_LIVE_UAT | 7 |
| INTENTIONALLY_DISABLED | 1 |
| STALE_EVIDENCE_REQUIRES_RECHECK | 0 |
| **TOTAL** | **32** |

---

## Closure-02 operational results

- SMTP row 5: `active/is_active=1` → `inactive/is_active=0`; credentials/settings preserved.
- `SMTP_REQUIRED_FOR_MAIL=NO`
- `MAIL_CONFIG_POST_HYGIENE=PASS`
- `FLIGHT_SEARCH_POST_HYGIENE=PASS` (search 200 / data 200 / 16 offers)
- `RETIRED_ACTIVE_ROWS=0`
- Group inventory: 245 total / 66 future; sync 2026-10-31 02:00:10.
- Ask JetPakistan: PARTIAL, critical failures 0.
- Portal RBAC: guest gates PASS; authenticated cross-role matrix pending.
- CMS stale fare root cause: pinned CTA URL with past depart; fixed and live HTML certified.
- Deploy marker split: merged in PR #155; production effect to be verified on next applicable deploy.

## Canonical merge chain

1. PR #151 — flight-search retired-provider diagnostics — `7cc318122d4f2cf6c7b5571ce176970c3862180f`
2. PR #152 — FAB horizontal-clearance fix — `1f4cdcbf2b18cb577677b8ddd1124017dd46394f`
3. PR #153 — initial full-system audit — `d171fb29a01e1af3a9522d2869e31a99f0027ef3`
4. PR #154 — CMS Trending CTA freshness — `8f33173710f233f6ec84c729eb96ab11a5c53d4c`
5. PR #155 — component deployment-marker split — `c56178a84f4bc3f2124b9d81ed9c890b5382d0f9`

## Readiness

JetPakistan is **not declared fully complete**.

It is ready to **plan** `JP-BOOKING-LIVE-UAT-01`, with payment treated separately because AbhiPay remains unconfigured.
