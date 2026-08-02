# JP-OPS-03 Implementation Register

**Phase:** JP-OPS-03 Customer Portal Operational Closure
**Branch:** `phase/jetpk-ops-03-customer-portal-closure`
**Baseline:** `770a29c8514bdadab9275e9786a1cf9790a6db0d`

## Customer page × action matrix

| Page | Visible action | Laravel read | Laravel mutation | Ownership | State | Disposition |
|------|----------------|--------------|------------------|-----------|-------|-------------|
| `/customer/dashboard` | View metrics, quick actions | `GET /customer?format=json` | — | `customer_id` scope | CONNECTED | Verify metrics; hide fake unread |
| `/customer/bookings` | List, filter, paginate | `GET /customer/bookings?format=json` | — | `customer_id` scope | CONNECTED | Add pagination UI |
| `/customer/bookings/{ref}` | Detail, cancel request, docs | `GET /customer/bookings/{ref}?format=json` | `POST /customer/bookings/{id}/cancellations` | Gate + `customer_id` | PARTIALLY_CONNECTED | Wire cancel JSON + capabilities |
| `/customer/payments` | List payments | `GET /customer/payments?format=json` | — | `whereHas booking.customer_id` | CONNECTED | Read-only; no fake retry |
| `/customer/invoices` | List invoices | `GET /customer/invoices?format=json` | — | `whereHas booking.customer_id` | CONNECTED | — |
| `/customer/invoices/{ref}` | Invoice detail | `GET /customer/invoices/{ref}?format=json` | — | Gate + `customer_id` | BACKEND_WITHOUT_NEXT_BINDING | Add Next page |
| `/customer/documents/{id}/download` | Download PDF | — | GET download | `BookingDocumentPolicy` | PARTIALLY_CONNECTED | Direct Laravel GET link |
| `/customer/travelers` | CRUD saved travelers | `GET /customer/travelers?format=json` | POST/PATCH/DELETE | `SavedTravelerPolicy` | FRONTEND_WITHOUT_BACKEND_CONTRACT | Add JSON + Next UI |
| `/customer/profile` | View/update profile | `GET /customer/profile?format=json` | `PATCH /profile` | Auth user only | CONNECTED | — |
| `/customer/security` | Change password | — | `PUT /password` | Auth user only | CONNECTED | — |
| `/customer/support` | List, create | `GET/POST /customer/support/tickets` | POST store | `created_by_user_id` | CONNECTED | — |
| `/customer/support/{ref}` | View, reply, close | `GET .../tickets/{ref}?format=json` | POST reply, PATCH close | `SupportTicketPolicy` | CONNECTED | Wire close UI |
| `/customer/notifications` | Inbox | `GET /customer/notifications?format=json` | mark-read 501 | `user_id` (stub) | INTENTIONALLY_UNAVAILABLE | Preserve honest stub |
| Refund request | — | Read on booking detail | — | — | INTENTIONALLY_UNAVAILABLE | Staff-only; display status only |

## Gaps addressed

| Gap ID | Action |
|--------|--------|
| GAP-006 | Customer cancellation request UI + JSON POST |
| GAP-007 | Saved travelers Next CRUD + JSON |
| T-02 | Customer cancel E2E tests |

## Excluded

- Customer refund request intake (no backend route)
- Live Sabre cancellation
- Production payment/refund execution
- Notification inbox backend
- Agent/Admin portals (JP-OPS-04/05)

## Changed-file count (canonical)

- Tracked diff vs `770a29c…`: **24**
- Untracked new: **25**
- Working-tree delta: **49**

| Group | Count |
|-------|------:|
| Laravel runtime | 5 |
| Laravel tests | 2 |
| Frontend runtime | 20 |
| Frontend tests | 7 |
| Package/test configuration | 1 |
| JP-OPS-01 documents | 2 |
| JP-OPS-03 operations documents | 11 |
| JP-OPS-03 phase document | 1 |
| **Total** | **49** |

Permanent document count unchanged: operations **11** + phase **1** = **12**.
