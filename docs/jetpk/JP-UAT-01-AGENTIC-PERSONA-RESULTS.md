# JP-UAT-01 — Agentic Persona Results (Playwright CLI)

Supplement to scripted/deterministic JP-UAT-01. Does **not** replace `SCRIPTED_BUSINESS_UAT_SCORE=92`.

## Tooling

| Tool | Result |
|------|--------|
| BROWSER_USE | UNAVAILABLE_CREDENTIAL_BOUNDARY |
| PLAYWRIGHT_MCP | NOT_ACTIVE_SESSION_BOUNDARY |
| PLAYWRIGHT_CLI_SKILLS | ACTIVE |
| AGENTIC_PERSONA_EXECUTION | PASS |

Method: Microsoft Playwright CLI sessions (`snapshot → reason → click/fill → snapshot`). No keyword coaching from UAT scripts during persona decision loops. Auth storage prepared by orchestrator only.

Commercial: live public flight searches this supplement = **1** (anonymous ISB→DXB). Max 2. No PNR/payment/wallet mutation/supplier-write.

---

## A. Anonymous Traveller (`-s=uat-anonymous`)

| Field | Value |
|-------|-------|
| goal_achieved | yes |
| starting_page | https://jetpakistan.pk/ |
| visible_path | Home → Flight search (Return tab backtrack → One Way) → fill Departure → Search Flights → results (~20) → sort Lowest Price → inspect filters/Details CTAs → stop before booking |
| actions | 9 |
| backtracks | 1 (Return → One Way) |
| wrong_choices | 0 |
| dead_ends | 0 |
| confusing_labels | Results stop filter chips show counts like “(2)” without explicit “Direct” wording on filter chips |
| moments_of_uncertainty | 1 (whether Return vs One Way was required) |
| hidden_knowledge_required | no |
| business_outcome_understood | yes |
| confidence | 5 |
| findings | P3 filter wording only; booking not attempted |

---

## B. Customer (`-s=uat-customer`)

| Field | Value |
|-------|-------|
| goal_achieved | yes |
| starting_page | https://jetpakistan.pk/ (signed-in storage) |
| visible_path | Home (Dashboard + Account menu visible) → Support → “My support requests” → ticket SEWJRVS9 thread (status Pending, Reply, Close) → Account menu areas → probe `/admin/dashboard` → `/access-denied` |
| actions | 8 |
| backtracks | 0 |
| wrong_choices | 0 |
| dead_ends | 0 |
| confusing_labels | Header “Dashboard” lands on bookings (known UAT-F003) |
| moments_of_uncertainty | 0 |
| hidden_knowledge_required | no |
| business_outcome_understood | yes |
| confidence | 5 |
| findings | UAT-F001 discoverability holds; UAT-F004 access-denied holds; no admin chrome |

---

## C. Agent (`-s=uat-agent`) — includes blind revalidation after UAT-F005

### First attempt (pre-fix)

| Field | Value |
|-------|-------|
| goal_achieved | partial |
| visible_path | Home → Dashboard → “Something went wrong” → Try again fail → Home → Account → Wallet (heading only) → Support (empty OK) → Bookings → error boundary |
| actions | 12 |
| backtracks | 2 |
| dead_ends | Dashboard, Bookings |
| confidence | 2 |
| findings | **UAT-F005 P1** — `/laravel/agent*` JSON stolen by OLS Next SPA rules when rewritten to `:8088/agent…` |

### Blind rerun (post-fix)

| Field | Value |
|-------|-------|
| goal_achieved | yes |
| starting_page | https://jetpakistan.pk/ |
| visible_path | Home → Dashboard → overview metrics/wallet/quick actions → Wallet (balances) → Support → Profile (misclick) → Support → Bookings (filters + empty list) |
| actions | 10 |
| backtracks | 1 (Profile misclick) |
| wrong_choices | 1 (clicked Profile while aiming Support) |
| dead_ends | 0 |
| confusing_labels | none blocking |
| moments_of_uncertainty | 0 after fix |
| hidden_knowledge_required | no |
| business_outcome_understood | yes |
| confidence | 4 |
| findings | F001 Wallet/Support discoverable; no money moved |

Verifier (post-persona): `/laravel/agent?format=json`, `/bookings`, `/wallet` → `application/json` ok:true.

---

## D. Operations / Support Staff (`-s=uat-staff`)

| Field | Value |
|-------|-------|
| goal_achieved | yes |
| starting_page | https://jetpakistan.pk/ |
| visible_path | Home → Dashboard → Operations dashboard (“Needs attention”) → Support tickets list (open/pending, resolve/reply controls visible) — no Resolve/Clear clicked |
| actions | 4 |
| backtracks | 0 |
| wrong_choices | 0 |
| dead_ends | 0 |
| confusing_labels | “Tickets” vs “Support” both present (non-blocking) |
| moments_of_uncertainty | 0 |
| hidden_knowledge_required | no |
| business_outcome_understood | yes |
| confidence | 5 |
| findings | Assigned/new work discoverable from Dashboard without route coaching |

---

## E. Platform Admin (`-s=uat-admin`)

| Field | Value |
|-------|-------|
| goal_achieved | yes |
| starting_page | https://jetpakistan.pk/ |
| visible_path | Home → Dashboard (KPIs + Needs attention) → Support tickets → Staff/Users directory + Roles → System health |
| actions | 6 |
| backtracks | 0 |
| wrong_choices | 0 |
| dead_ends | 0 |
| confusing_labels | Nav label “Staff” opens Users directory (P3) |
| moments_of_uncertainty | 1 (Staff vs Users naming) |
| hidden_knowledge_required | no |
| business_outcome_understood | yes |
| confidence | 4 |
| findings | Support + staff responsibility + health reachable without route hints |

---

## F. Exploratory charter (`-s=uat-explore`)

| Field | Value |
|-------|-------|
| goal_achieved | yes (evaluation pass) |
| starting_page | https://jetpakistan.pk/ |
| visible_path | Home Support menu → lookup-booking (“Manage your booking”) → groups/search |
| actions | 5 |
| backtracks | 1 (currency click misfire before login) |
| dead_ends | Groups search disabled: “No group sectors are currently available” |
| confusing_labels | none severe |
| hidden_knowledge_required | no |
| confidence | 4 |
| findings | P3 groups empty inventory; lookup booking UX clear; no live search this pass |

---

## Agentic assessment (separate from scripted 92)

| Metric | Value |
|--------|-------|
| AGENTIC_BLACK_BOX_UAT_SCORE | 90 |
| AGENTIC_MANDATORY_PERSONAS | 5/5 complete |
| AGENTIC_EXPLORATORY | complete |
| HIDDEN_ROUTE_KNOWLEDGE_REQUIRED | no (after F005) |
| P0 | 0 |
| P1_OPEN | 0 (F005 fixed + blind rerun) |
| P2_OPEN | 0 |
| P3_DOCUMENTED | filter wording; Staff→Users label; groups empty; F003 |

`AUTHORITATIVE_VERIFIER`: agent JSON APIs post-F005; F004 access-denied; OLS hash unchanged; QA cleanup at closure.
