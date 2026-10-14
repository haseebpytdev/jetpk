# QA identity matrix (JP-DASH-FINAL-11)

Safe labels only — no passwords, tokens, or session material.

## Production QA agency A

| Label | Username marker | User ID | Account type | Agency ID | Agency slug | Notes |
|-------|-----------------|---------|--------------|-----------|-------------|-------|
| PLATFORM_ADMIN | `jp-dash-03-qa-admin` | 9 | `platform_admin` | 4 | `jetpk-production-qa` | Active |
| STAFF | `jp-dash-03-qa-staff` | 8 | `staff` | 4 | `jetpk-production-qa` | Operator + support presets |
| AGENT_OWNER | `jp-dash-03-qa-agent` | 10 | `agent` | 4 | `jetpk-production-qa` | Owner role |
| AGENT_STAFF_SUPPORT | `jp-dash-03-qa-agent-staff` | 12 | `agent_staff` | 4 | `jetpk-production-qa` | Support staff template (current prod) |
| CUSTOMER_A | `jp-dash-03-qa-customer` | 11 | `customer` | 4 | `jetpk-production-qa` | Primary QA customer |

`QA_RUN_ID` for fixtures: `JPQA-20261008` (booking `JPQA-20261008-BOOKING` exists).

## Gaps (required for full FINAL-11 matrix)

| Label | Status | Planned marker |
|-------|--------|----------------|
| CUSTOMER_B | **PROVISION_PENDING** | `jp-final-11-qa-customer-b` |
| AGENT_OWNER_B (agency B) | **PROVISION_PENDING** | `jp-final-11-qa-agent-b` |
| AGENT_STAFF_MANAGER | **PROVISION_PENDING** | `jp-final-11-qa-staff-manager` |
| AGENT_STAFF_ACCOUNTANT | **PROVISION_PENDING** | `jp-final-11-qa-staff-accountant` |
| AGENT_STAFF_SALES | **PROVISION_PENDING** | `jp-final-11-qa-staff-sales` |
| AGENT_STAFF_TICKETING | **PROVISION_PENDING** | `jp-final-11-qa-staff-ticketing` |
| AGENT_STAFF_VIEWER | **PROVISION_PENDING** | `jp-final-11-qa-staff-viewer` |
| LEGACY_AGENCY_ADMIN | **PROVISION_PENDING** | `jp-final-11-qa-legacy-agency-admin` |

Legacy slug `jp-dash-03-qa-agency` (id **3**) exists; no dedicated `agency_admin` QA user on production yet.

## Secondary agency

| Agency slug | ID | Purpose |
|-------------|-----|---------|
| `jetpk-production-qa-b` | — | Cross-agency isolation (pending reconcile) |

## Provisioning path (when gaps closed)

Controlled production reconcile (no deploy): `php artisan jetpk:dashboard-final-11-qa reconcile` via `dashboard/scripts/jp-dash-final-11/setup-production-qa.mjs` after the command is on production runtime and `JP_FINAL_11_QA_*` passwords are in vault/env (never commit passwords).
