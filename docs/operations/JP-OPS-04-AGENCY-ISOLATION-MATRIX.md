# JP-OPS-04 Agency Isolation Matrix

## Tenant scope

| Resource | Scope key | Enforcement |
|----------|-----------|-------------|
| Bookings | `bookings.agent_id = auth agent.id` | `BookingPolicy` + controller `where('agent_id')` |
| Wallet / ledger | `agent_wallets.agent_id` | `AgentWalletPolicy`, scoped queries |
| Deposits | `agent_deposit_requests.agent_id` | Deposit controller scope |
| Payments / invoices | booking.agent_id | `whereHas` via agent bookings |
| Commission entries | `agent_id` on agent record | `agent.admin` + `AgentCommissionPolicy` |
| Staff users | `meta.owner_agent_id` + `current_agency_id` | `AgentStaffPolicy::ownsStaff` |
| Reports | agency bookings for user's agent | `BookingReportService` + `viewAgencyReports` |
| Agency profile | auth user's `agent()` | `Gate::authorize('view', $agent)` |
| Support tickets | agency/agent context | existing support scoping |
| Cancellation / payment proof | `booking.agent_id` match | explicit abort in controllers |

## Cross-agency denial

| Test scenario | Expected |
|---------------|----------|
| Agency B owner reads Agency A booking | 403 |
| Agency B staff lists Agency A wallet | 403 |
| Staff with removed `AgencyUser` row | 403 dashboard JSON |
| Inactive agent business (`is_active=false`) | 403, `code: agency_inactive` |

## Context integrity

| Check | Location |
|-------|----------|
| `current_agency_id` matches agent business | `AgentPortalAccess::evaluate` |
| Staff `owner_agent_id` matches employer agent | `AgentPortalAccess` + `AgentStaffPolicy` |
| `AgencyUser` membership exists for staff | `AgentPortalAccess::evaluate` |
| Agency context bootstrap | `EnsureAgencyContext` middleware |

No `agent_id`, `agency_id`, or `booking_reference` from the client is trusted for authorization — policies and scoped queries resolve ownership server-side.

## Staff data boundaries

Staff members are visible only when:

- `account_type = agent_staff`
- `current_agency_id` = owner agent's `agency_id`
- `meta.owner_agent_id` = owner agent's `id`

Cross-agency staff enumeration returns empty or 403, never foreign agency rows.
