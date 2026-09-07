# P2 email template matrix

Canonical rules (do not invent a new visual language):

- JetPakistan header + logo + Visit site
- max-width 640px table shell (`jetpk-container`)
- green brand divider / accent from JetpkEmailBrandingResolver
- consistent CTA button partial
- footer support contact
- mobile `@media max-width:480px` padding/stack
- no Operational Alert modern-ops shell on JetPakistan emails

| Family | Shell | Subject source | Heading source | Status |
|---|---|---|---|---|
| Auth login (admin) | JetPK base | P0 contract subject | Auth payload title | FROZEN P0 |
| Auth other login / failed | JetPK base | event registry | Auth payload title | CANONICAL |
| OTP | JetPK OTP block | OTP subject | OTP heading | SPECIALIZED |
| Registration welcome | JetPK | welcome subject | welcome heading | CANONICAL |
| Admin signup alert | JetPK shell_notice | tagged admin subject | New customer signup | CANONICAL |
| Customer booking Mailables | JetPK shell_notice | mailable envelope | family headline | CANONICAL |
| Booking universal Blade | JetPK base extends | payload subject | payload title | CANONICAL |
| Operational events | JetPK event registry | registry / DB template | registry heading | CANONICAL |
| Abandoned / settings / manual | JetPK shell_notice | envelope / renderer | title payload | CANONICAL |

SUBJECT_EVENT_MATCH / VISIBLE_TITLE_EVENT_MATCH: subjects and headings are separate; admin login subject must not be replaced by the visible heading.
