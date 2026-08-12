# JP-UAT-01 — Business Rubric (FROZEN)

**Frozen at (UTC):** 2026-08-12T05:00:00Z  
**Parent tip:** `a3a93e1ed0c82a14808aa09207ba7f7bd6b86842`  
**Branch:** `phase/jetpk-uat-01-autonomous-business-uat`  
**Machine JSON:** `docs/jetpk/JP-UAT-01-BUSINESS-RUBRIC.json`

This rubric is frozen before exploratory runs. Pass criteria must not be weakened because results are inconvenient.

## Dual-evidence rule

A scenario passes only when:

1. black-box business user succeeds, and
2. deterministic verifier confirms authoritative state, and
3. security/RBAC remains correct (where applicable).

## Severity

| Severity | Meaning |
|----------|---------|
| P0 | Critical launch blocker (exposure, money corruption, supplier write, silent state corruption) |
| P1 | Business launch blocker (mandatory persona cannot complete core task, wrong routing, false success) |
| P2 | Important UX/operational defect with workaround |
| P3 | Polish |
| P4 | Enhancement |

JP-UAT-01 cannot PASS with unresolved P0 or P1.

## Scoring (secondary to objective gates)

| Points | Criterion |
|--------|-----------|
| 25 | Primary business goal completed |
| 15 | Authoritative state correct |
| 10 | Correct recipient/routing |
| 10 | RBAC/privacy correct |
| 5 | Refresh/reconnect persistence |
| 5 | Error/recovery behavior |
| 10 | Task discoverability |
| 10 | Terminology/status comprehension |
| 5 | Workflow efficiency |
| 5 | Visual hierarchy / decision clarity |

Targets: each mandatory persona ≥ 85; overall ≥ 90. Objective gates always override score.

## Commercial boundary (global)

No real supplier booking/PNR/ticket/void/cancel/refund; no wallet/deposit money movement; no supplier credential changes; max 3 live public searches; stop before commercial booking creation. Sabre cancellation gates remain enabled.

## Scenarios

See JSON for full fields (`SCENARIO_ID` … `COMMERCIAL_BOUNDARY`). Summary:

| ID | Persona | Gate |
|----|---------|------|
| UAT-ANON-01 | Anonymous Traveller | `UAT_ANONYMOUS_TRAVELLER` |
| UAT-CUST-01 | Customer | `UAT_CUSTOMER` |
| UAT-AGENT-01 | Agent | `UAT_AGENT` |
| UAT-STAFF-OPS-01 | Operations Staff | `UAT_STAFF_OPERATIONS` |
| UAT-SUPPORT-01 | Support Operator | `UAT_SUPPORT_OPERATOR` |
| UAT-FINANCE-01 | Finance-capable Staff | `UAT_FINANCE_OPERATOR` (PASS or N/A with architecture proof) |
| UAT-ADMIN-01 | Platform Admin | `UAT_PLATFORM_ADMIN` |
| UAT-LOOP-01 | Cross-persona | `UAT_FULL_BUSINESS_LOOP` |
| UAT-DISC-01 | All | `BUSINESS_DISCOVERABILITY` |
| UAT-STATUS-01 | All | `STATUS_COMPREHENSION` |
| UAT-ERR-01 | All | `BUSINESS_ERROR_RECOVERY` |
| UAT-DEAD-01 | All | `BUSINESS_DEAD_ENDS=0` / `LEGACY_OPERATOR_UI_DISCOVERED=0` |
| UAT-TERM-01 | All | `BUSINESS_TERMINOLOGY` |
| UAT-NAV-01 | All | `UAT_NAVIGATION_IA` |
| UAT-CONFIRM-01 | All | `ACTION_CONFIRMATION_INTEGRITY` |
| UAT-RBAC-01 | Customer/Agent/Staff | `BLACK_BOX_ROLE_BOUNDARIES` |
| UAT-RESP-01 | All | `BUSINESS_RESPONSIVE_UAT` |
| UAT-A11Y-01 | All | `BUSINESS_KEYBOARD_UAT` |
| UAT-ADV-01 | Adversarial | `EXPLORATORY_UAT_COMPLETED` |

## Policy hard stop

Cursor may not autonomously change refund/cancellation/legal/prices/markups/commissions/supplier commercial settings/ticketing rules/credit limits/wallet business rules/agency commercial permissions/Hajj-Umrah commercial policy. Such findings = `EXTERNAL_OWNER_DECISION`.
