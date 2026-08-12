# OWNER UAT W2 — Failed notifications classification

Date (UTC): 2026-08-12  
Method: read-only PHP bootstrap on production; script removed after run.  
OLS: MATCH `612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c`

## Totals

- `FAILED_TOTAL=74` (`communication_logs.status` in `failed`,`error`)
- `QA_LIKE` event-name heuristic = 0
- `BOOKING_LINKED=0`

## By event

- auth_new_device_login=24
- support_ticket_created=22
- staff_login_success=10
- support_ticket_assigned=8
- support_ticket_replied=6
- admin_login_success=3
- support_ticket_status_changed=1

## Error pattern

All sampled error prefixes are SMTP **550 5.1.1** for Owner-UAT QA mailboxes:

- `jp-dash-03-qa-admi…` (26)
- `jp-dash-03-qa-staf…` (23)
- `jp-dash-03-qa-cust…` (15)
- `jp-dash-03-qa-agen…` (10)

## Disposition

- **Class:** QA / Owner-UAT identity mailbox bounces (not customer booking traffic)
- **Action:** Operational review UI only; **no blind retry**; **no audit deletion**
- **KPI:** Truthful count of failed delivery attempts; CTA now routes to `/notifications/failures`
