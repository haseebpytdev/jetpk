# JetPakistan Full System / Business Logic Audit

**Date:** 2026-10-31  
**Host:** https://jetpakistan.pk  
**Evidence root:** `docs/evidence/jp-full-system-audit-20261031/`  
**Start main SHA:** `2bf821226604869f494c64d11709e741da595dfa`  
**Audit posture:** Read-only except approved hotfixes JP-FLIGHT-SEARCH-LIVE-INCIDENT-01 + JP-UI-FAB-02  

## Owner summary

Public flight search was **down** (HTTP 500 on search init) because an active retired **SMTP** supplier connection crashed diagnostics. That is **fixed and live-certified** (search + results with offers). Mobile FAB horizontal content squeeze is **fixed and deployed**. Online AbhiPay checkout remains **not configured** (0 gateway rows). One API remains **dormant** by design. Scheduler cron **is running**. Full commercial booking/ticketing/cancel/refund paths need **controlled live UAT** (not executed this loop).

---

## Top-level domain table

| DOMAIN | BUSINESS PURPOSE | CURRENT STATUS | LIVE? | LAST VERIFIED | WHAT WORKS | WHAT DOES NOT | RISK | NEXT ACTION |
|---|---|---|---|---|---|---|---|---|
| Public Website | Marketing + search entry | PASS_READ_ONLY | Y | 2026-10-31 | `/` `/login` CMS sections HTTP 200 | Trending dates include past `2026-09-22` links | Medium content freshness | CMS fare refresh / date hygiene phase |
| Authentication | Login/register/reset | PASS_READ_ONLY | Y | 2026-10-31 | Pages load; SMTP mailer configured | Live OTP/reset not mutation-tested | Low | Controlled auth UAT |
| Flight Search | Find fares | DONE_CERTIFIED_CURRENT | Y | 2026-10-31 | Search+data 200; 16 offers LHE→KHI | Was 500 pre-fix | Low post-fix | Deactivate SMTP supplier row; canonical fix merged in PR #151 |
| One-Way | OW itineraries | DONE_CERTIFIED_CURRENT | Y | 2026-10-31 | Domestic OW certified today | — | Low | Keep regression |
| Return Paired | Round-trip paired | PASS_READ_ONLY | Partial | Source | Code paths exist | Not live-certified today | Med | Safe live OW first; then return UAT |
| Return Segmented | Split return | PASS_READ_ONLY | Partial | Source | `ReturnSplitComboService` present | Not live-certified today | Med | Controlled UAT |
| Multi-city | Multi-segment | PARTIAL | Partial | Source | Form + inquiry route | Live multi-city search not certified | Med | UAT phase |
| Fare Selection | Branded fares | PASS_READ_ONLY | Y* | Source+hist | AirBlue Oct-28 hist cert | Not re-run mutation | Med | Optional re-cert |
| Flight Checkout | Pax → pay | NEEDS_CONTROLLED_LIVE_UAT | — | Source | Funnel exists | No live booking this audit | High if assumed done | Booking UAT phase |
| Booking | Persist order | NEEDS_CONTROLLED_LIVE_UAT | — | Source | Models/services present | No mutation | High | Controlled UAT |
| Supplier PNR/Order | Create supplier order | NEEDS_CONTROLLED_LIVE_UAT | — | Source | Adapters present | Gates/credentials vary | High | Per-supplier UAT |
| Ticketing | Issue tickets | NEEDS_CONTROLLED_LIVE_UAT | — | Hist AirBlue | Code + hist evidence | Not current live | High | Approved ticket UAT |
| Cancel | Cancel PNR/order | NEEDS_CONTROLLED_LIVE_UAT | — | Source | Adapter methods exist | Not live-tested | High | Approved cancel UAT |
| Refund | Refund money/tickets | NEEDS_CONTROLLED_LIVE_UAT | — | Source | Partial per supplier | Not live-tested | High | Finance UAT |
| Group Search | Search group inventory | PASS_READ_ONLY | Y | 2026-10-31 | `/groups/search` 200; D3 API split | Inventory depth TBD | Med | Inventory row counts phase |
| Group Inventory | Synced packages | STALE_EVIDENCE_REQUIRES_RECHECK | ? | Cron present | `schedule:run` cron active; sync cmd registered | Row counts / last sync not fully stamped | Med | Read-only DB inventory probe |
| Group Booking | Book group package | NEEDS_CONTROLLED_LIVE_UAT | — | Source | Funnel exists; AlHaider book default off | Live reserve gated | Med | Config + UAT |
| Payments | Manual/online pay | PARTIAL | Y | 2026-10-31 | Manual/proof architecture | Online gateway absent | High commercial | Configure AbhiPay or accept manual-only |
| AbhiPay | Online gateway | BLOCKED_CONFIGURATION | N | 2026-10-31 | Code+routes present | **PAYMENT_GATEWAY_ROWS=0** | High | Create gateway row + secrets |
| Admin | Platform ops | PASS_READ_ONLY | Y | Source+PM2 | Dashboard PM2 online 11d | Full RBAC crawl not exhaustive | Med | Safe admin smoke |
| Staff | Ops staff | PASS_READ_ONLY | Y | Source | Routes/`account.type:staff` | Live staff UAT limited | Med | Role smoke |
| Agent | B2B agents | PASS_READ_ONLY | Y | Source | Agent routes/modules | Live agent booking not run | Med | Agent UAT |
| Customer | B2C portal | PASS_READ_ONLY | Y | Source | Customer routes | Live account flows limited | Med | Customer smoke |
| CMS | Homepage content | PARTIAL | Y | 2026-10-31 | Content renders | Stale trending dates | Med | Editorial refresh |
| Email | Notifications | PASS_READ_ONLY | Y | 2026-10-31 | `MAIL_MAILER=smtp`; templates exist | Bulk send not run | Med | Safe mailbox test |
| Ask JetPakistan | AI assistant | STALE_EVIDENCE_REQUIRES_RECHECK | Y UI | 2026-10-31 | Launcher visible on public pages | Env AI flags probe incomplete this run | Med | Safe chat smoke + env confirm |
| AI Lab | Lab gateway | INTENTIONALLY_DISABLED | ? | Source defaults | Adapter code | Defaults off | Low | Leave off unless owned |
| Scheduler | Cron jobs | PASS_READ_ONLY | Y | 2026-10-31 | `* * * * * schedule:run` present | Individual job success not fully tailed | Med | `schedule:list` + last-run logs |
| Queue | Async jobs | PASS_READ_ONLY | Y | 2026-10-31 | `QUEUE_CONNECTION=database`; cron worker | Failed-job depth incomplete | Med | Inventory failed jobs |
| Reporting | Ops reports | PASS_READ_ONLY | Source | Scheduled report cmds | Delivery not verified | Low | Spot-check |
| Security | AuthZ/CSRF | PASS_READ_ONLY | Y | Source | CSRF, modules, hidden credentials | Deep audit incomplete | Med | Security auditor pass |
| Production Infrastructure | Runtime | PASS_READ_ONLY | Y | 2026-10-31 | `/up` 200; PM2 FE+dashboard online | Marker overwritten by FE deploy | Med | Restore dual markers |

\*Fare selection historically certified for AirBlue; public search today returned Sabre-family airlines on LHE–KHI probe.

---

## Approved runtime fixes closed this loop

1. **JP-FLIGHT-SEARCH-LIVE-INCIDENT-01** — DONE_CERTIFIED_CURRENT (live; canonical PR #151 merged as `7cc318122d4f2cf6c7b5571ce176970c3862180f`)
2. **JP-UI-FAB-02** — DONE_CERTIFIED_CURRENT (live FE; canonical PR #152 merged as `1f4cdcbf2b18cb577677b8ddd1124017dd46394f`)

**Canonical main after both merges:** `1f4cdcbf2b18cb577677b8ddd1124017dd46394f`

## Status counts (domains above)

| Status | Count |
|---|---|
| DONE_CERTIFIED_CURRENT | 3 |
| PASS_READ_ONLY | 16 |
| PARTIAL | 4 |
| BROKEN | 0 |
| BLOCKED_CONFIGURATION | 1 |
| BLOCKED_EXTERNAL | 0 |
| NEEDS_CONTROLLED_LIVE_UAT | 8 |
| INTENTIONALLY_DISABLED | 1 |
| STALE_EVIDENCE_REQUIRES_RECHECK | 2 |

---

## Provenance notes

- `PRODUCTION_MARKER != AUTOMATIC_PRODUCTION_FILE_PARITY` confirmed.
- Flight baggage Oct-28 sites were already on LIVE disk (hash match) — **not** today’s outage.
- After FAB deploy, `storage/app/deploy-sha.txt` = frontend selective SHA `d9e08e18…` (finalize script). Laravel selective flight hotfix remains on disk with hash `1aa86ce4…`; canonical source is now represented by merged PR #151.
- Frontend FAB source is now canonical on protected `main` through PR #152.
