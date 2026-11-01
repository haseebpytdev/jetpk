# QA side-effect suppression (PR #167 source proof)

Deployed `TicketingService` (SHA matched) contains:

| Behavior | Gate |
|----------|------|
| Success customer ticket email | skipped when `$allowQaAirBlue`; logs `qa_airblue_success_communication_suppressed` |
| Failure customer communication | skipped when `$allowQaAirBlue`; logs `qa_airblue_failure_communication_suppressed` |
| Agent commission | only when `!$allowQaAirBlue` |
| Markup ledger posting | skipped when `$allowQaAirBlue`; logs `qa_airblue_financial_side_effects_suppressed` |

Canonical persistence remains:

- TicketingAttempt create/update
- BookingTicket create
- Booking / SupplierBooking status updates
- BookingStatusLog
- `booking.tickets_issued` audit

QA_SUCCESS_COMMUNICATION_SUPPRESSED=YES  
QA_FAILURE_COMMUNICATION_SUPPRESSED=YES  
QA_AGENT_COMMISSION_SUPPRESSED=YES  
QA_LEDGER_POSTING_SUPPRESSED=YES  
QA_TICKETING_PERSISTENCE_READY=PASS  
QA_FINANCIAL_SIDE_EFFECT_SUPPRESSION=PASS  
QA_COMMUNICATION_SUPPRESSION=PASS  

No live ticket simulated this phase.
