# Operational Feasibility and Mockup Control Matrix

Phase: **JP-UI-01**

Status codes: **ON** Operational now · **OV** Operational, visually wrong · **BE** Backend exists, frontend missing · **FE** Frontend exists, backend incomplete · **UN** Unsupported · **CO** Conditional · **HI** Must hide · **OPS** Requires JP-OPS

## Mockup control classification

| Control | Mockup page(s) | Status | Notes | Phase |
|---------|----------------|--------|-------|-------|
| Flight search (one-way/return/multi) | Home, Results | **ON/OV** | Works; layout wrong on home | JP-UI-03/04 |
| Group Ticketing tab | Home | **ON/OV** | `/groups/search`; not in compact hero tabs same way | JP-UI-03 |
| Hotels nav | Header | **UN** | Not in product scope | **HI** |
| Offers nav | Header | **UN** | No offers CMS route | **HI** or JP-OPS |
| Travel Services dropdown | Header | **UN** | Not implemented | **HI** |
| Currency selector | Header | **ON/OV** | UI only; settlement Laravel-side | JP-UI-02 |
| Theme toggle | Header | **BE** | No frontend theme system | JP-UI-02 |
| Newsletter subscribe | Footer | **FE** | Form stub only | JP-OPS |
| Social login (Google/Apple/FB) | Login, Signup | **CO** | Only if OAuth providers configured | JP-OPS |
| OTP login | Login | **ON** | Demo flags preserved | JP-OPS |
| Role: Family Manager / Business | Signup | **UN** | Not Laravel account types | **HI** |
| Destination carousel prices | Home | **FE** | Fixture data, not live | JP-UI-03 |
| Featured offer % discounts | Home | **FE** | Fixture | JP-UI-03 |
| Flexible dates on results | Results | **ON/OV** | Search option exists; results bar differs | JP-UI-04 |
| Direct flights filter | Results | **ON** | |
| Branded fare families | Results, Fare | **CO** | `has_branded_fares` supplier-dependent | JP-UI-04 |
| Seat selection | Seats mockup | **UN** | `seat_map_available: false` | **HI** |
| Ancillary meals/lounge | Various | **UN** | | **HI** |
| Pay by card (AbhiPay) | Payment | **ON** | Hosted handoff | JP-UI-04 |
| Manual payment + proof | Payment | **ON** | |
| PNR display on success | Success | **CO** | Only when `pnr_details.available` | — |
| Change flight (lookup) | Manage booking | **UN** | | **HI** |
| Add baggage (lookup) | Manage booking | **UN** | | **HI** |
| Live flight status | Header/lookup | **UN** | FAQ link only | **HI** |
| Support ticket CTA | Support | **ON** | Contact form / support flow | JP-UI-03 |
| Emergency support hotline | Support | **OPS** | Verify CMS content before display | JP-OPS |
| Live chat | Support | **UN** | | **HI** |
| Save/send itinerary WhatsApp | Success | **UN** | | **HI** |
| Flight status link | Footer | **UN** | Points to FAQ placeholder | JP-OPS |
| Refund status widget | Success | **CO** | Laravel `refund.available` | — |
| Turnstile on lookup | Manage booking | **ON** | Laravel config | — |
| Agent register | — | **ON** | Separate from customer signup | — |

## Seat selection feasibility (detailed)

| Check | Result |
|-------|--------|
| Supplier seat-map API | Not wired for standard booking |
| Laravel route | None for seat map |
| Session persistence | N/A |
| `seat_extras_capability.seat_map_available` | `false` |
| Group ticketing | Seat **counts** only, not maps |
| Payment impact | N/A |
| **Classification** | **Future capability — conditional route — must remain hidden** |

## Authentication feasibility

| Item | Visual target | Operational |
|------|---------------|-------------|
| Split layout | Mockup | Single column today |
| Social providers | Mockup shows 3 | Laravel-driven; hide if absent |
| Customer register | Mockup | **ON** at `/register` |
| Agent apply | Not in signup mockup | **ON** at `/agent/register` |
| OTP demo | — | **Preserved** per phase rules |

## Manage booking feasibility

| Control | Rule |
|---------|------|
| PNR + last name lookup | **ON**; Turnstile when enabled |
| Booking enumeration | Prevented server-side |
| Actions after lookup | Only Laravel-allowed actions |
| Fake change/add baggage | **Must not implement** |

## JP-OPS deferred operational closure

- CMS for nav (Hotels/Offers/Services) or confirm permanent hide
- Newsletter backend
- Offers/deals API
- Seat map supplier integration
- OAuth provider enablement
- Emergency support content verification
- Flight status product decision

No unsupported mockup controls were made operational in JP-UI-01.
