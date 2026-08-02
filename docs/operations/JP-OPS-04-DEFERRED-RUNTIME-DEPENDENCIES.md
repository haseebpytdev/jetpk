# JP-OPS-04 Deferred Runtime Dependencies

| Dependency | Impact | Phase |
|------------|--------|-------|
| Saved travelers Next (`/agent/travelers`) | Blade CRUD only; no Next page | Future |
| Accounting ledger Next (`/agent/accounting/ledger`) | Wallet ledger connected; accounting variant Blade-only | Future |
| Notification inbox backend | Stub JSON; mark-read 501 | Future |
| Agency CRM | No lead/contact pipeline in agent portal | Future |
| Markup mutation | Admin-only `MarkupRulePolicy` | Out of agent scope |
| Live supplier ticketing | Display `ticketing_status` only; commission **earned** row creation on ticket issue | JP-OPS-06 |
| Commission ledger on ticketing | `AgentCommissionLedgerTest` methods `test_ticketing_an_agent_booking_creates_commission_entry`, `test_duplicate_ticketing_duplicate_call_does_not_create_duplicate_commission`, `test_commission_entry_stores_calculation_snapshot`, `test_changing_rules_later_does_not_alter_old_commission_entry` — require supplier ticketing execution; JP-OPS-04 read contract (`test_agent_can_view_own_commissions`, `test_agent_reports_and_commissions_json_owner_only`) is green | JP-OPS-06 |
| Live payment capture / wallet debit execution | Proof upload + status display; no browser-side Paid | JP-OPS-06 |
| Live supplier cancellation | Cancellation **request** only; staff processes | JP-OPS-06+ |
| Admin deposit approval Next | Platform admin Blade workflow | JP-OPS-05 |
| Email/SMS notification delivery | Not in scope | External |
| Production queue workers | Background ops unchanged | JP-OPS-01 GAP-013 |

No production deploy, queue, or server configuration in JP-OPS-04.

## Gaps closed in this phase

| Gap | Closure |
|-----|---------|
| GAP-003 | Next staff management + JSON |
| GAP-004 | Next reports + JSON |
| GAP-005 | Next commissions + owner JSON |
| GAP-012 | Next booking create entry + mode activation |

## OTP

`OTP_DEMO_*` / `DemoFixedLoginOtpGate` — **unchanged** from JP-OPS-02/03.
