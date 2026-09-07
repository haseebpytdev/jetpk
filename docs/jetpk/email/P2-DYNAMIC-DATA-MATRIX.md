# P2 dynamic data matrix

| Field | Source of truth | Customer | Agent/Staff | Platform admin | Null rule |
|---|---|---|---|---|---|
| Brand / logo | JetpkEmailBrandingResolver / CompanyEmailProfile | yes | yes | yes | fallback JetPakistan |
| Customer name | User / booking contact | yes | yes | yes | hide empty |
| Booking reference | Booking.reference_code | yes | yes | yes | hide empty |
| PNR | Booking.pnr via ModernEmailLayout::customerPnrDisplay | yes (masked/safe) | yes | yes | hide when null (never `PNR: null`) |
| Route / dates | booking snapshot / offer display | yes | yes | yes | hide empty |
| Amount / currency | payment display fields | customer-facing only | role-appropriate | may include ops | never show raw zero unless real |
| Ticket number | ticketing fields | yes when issued | yes | yes | hide empty |
| Support contact | brand profile | yes | yes | yes | fallback support email |
| CTA URL | EmailContextualCtaResolver / PublicActionUrl | portal-correct | portal-correct | admin URL | HTTPS only |
| OTP / reset tokens | specialized paths | never log | n/a | n/a | not rendered in HTML beyond OTP block |

Adapters using `shell_notice` strip blank/`null` string detail rows before render.

MISSING_DYNAMIC_FIELDS / INCORRECT_DYNAMIC_FIELDS gates: enforced via render tests + null-row filtering.
