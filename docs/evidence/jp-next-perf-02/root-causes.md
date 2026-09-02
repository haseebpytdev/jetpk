# JP-NEXT-PERF-02 — Root causes (measured before fix)

## ROOT_CAUSE_1 — Groups results blocked on client hydration then browser→proxy API

**Contribution:** ~2.7s before any inventory request starts + ~3.3s browser inventory TTFB  
**Routes:** `/groups/search`, `/groups` → search  
**Evidence:** Playwright resource timing on `?sector=ISB-SHJ`:
- document TTFB ~1100ms
- `/laravel/groups/search/data` starts at **2687ms**, duration **3345ms**
- first logo/card paint ~6076ms
- Server-local Laravel `127.0.0.1:8088` inventory: **826ms** only

**Mechanism:** Thin RSC page rendered a full `"use client"` tree; inventory fetch could not start until JS hydrated. Browser path paid OLS/proxy RTT; SSR can use private Laravel listener.

## ROOT_CAUSE_2 — Artificial 720ms groups landing navigation delay

**Contribution:** +720ms on every landing→search handoff  
**Routes:** `/groups`  
**Evidence:** `GroupsLandingPage.pushSearch` `setTimeout(..., 720)` before `router.push`  
**Class:** forbidden cosmetic delay (phase §0 / §56)

## ROOT_CAUSE_3 — Review / Payment blank full-page loading with no shell

**Contribution:** perceived multi-second dead UI after Traveler authority exists  
**Routes:** `/booking/review`, `/booking/payment/manual`  
**Evidence:** `BookingReviewPage` / `ManualPaymentPage` returned only `BookingLoadingState` until client fetch completed; no SSR payload; no shell.

## ROOT_CAUSE_4 — Flight filter/sort cleared READY cards (skeleton regression)

**Contribution:** READY→full skeleton on sort/filter; extra perceived latency  
**Routes:** `/flights/results`  
**Evidence:** `useFlightResults` filter effect `setData(null); setStatus("loading")` even when view unchanged.

## Separated clocks (Groups ISB-SHJ, pre-fix sample)

| Clock | ms |
|---|---|
| USER_ACTION_TO_ACK | N/A (direct URL) |
| ROUTE_SHELL (document) | ~1100 TTFB / 1577 DCL |
| FRONTEND_TO_API_DELAY | ~2687 (hydration gate) |
| LARAVEL_LOCAL_DATA | 826 |
| NETWORK_BROWSER_DATA | 3345 |
| FIRST_USEFUL_CARD | ~6076 (this sample) / 9755 (02B cold) |
| FILTER_READY (facets) | ~4070 browser / 110 local |

## GROUPS_WATERFALL_ROOT_CAUSE

Not airline→sector→category→inventory sequential masters.  
Facets + CMS + inventory already fire in **parallel** after hydration.  
Dominant waste: **post-navigation client-only authority** + **proxy hop** + **720ms artificial delay** on landing path.  
Filter metadata does **not** gate URL-driven inventory (`filtersValid` allows load before facets) — but form UX previously `disabled={loading}` and landing waited on facets for submit.
