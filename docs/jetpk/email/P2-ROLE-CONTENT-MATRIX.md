# P2 role content matrix

| Audience | Allowed content | Forbidden |
|---|---|---|
| Customer | booking refs, itinerary, customer prices, support, CTAs to customer portal | supplier net, commission, admin ops notes, raw payloads |
| Agent / agent staff | agency booking context, customer contact when policy allows | unrelated platform-admin digests |
| Staff | assigned booking ops, support tickets | customer password/OTP |
| Agency admin | agency summaries, wallet/booking activity | other agencies |
| Platform admin | operational metadata, manual review, supplier failure summaries without secrets | bearer tokens, raw GDS payloads |

Booking universal Blade: operational block only when `admin.recipient_type` is admin/agent/staff/finance.

OtaOperationalEmailRenderer: blocks raw supplier payload keys (covered by unit test).
