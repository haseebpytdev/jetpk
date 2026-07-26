# Report KPI Definitions — DASH-08-09 Prompt 02

Reference date: `2026-07-01`. All KPIs derive from operational fixtures unless noted. Default report preset: **This year** (includes Jan–Feb 2026 fixture bookings).

| Key | Label | Definition | Formula | Sources | Currency | Comparison | Trend semantics | Limitation |
|-----|-------|------------|---------|---------|----------|------------|-----------------|------------|
| gross_booking_value | Gross booking value | Sum of booking totals in range | Σ booking.totalAmount | Bookings | PKR | Previous period/year when enabled | Increase positive | Mixed currency unavailable |
| collected_payments | Collected payments / Collected revenue | Paid/partial transaction amounts | Σ transaction.paidAmount | Payments | PKR | Increase positive | Refunds excluded |
| outstanding_balance | Outstanding balance / Outstanding value | Unpaid booking balance | Σ (total − paid) | Bookings | PKR | Decrease positive | — |
| refunded_amount | Refunded amount / Refunded value | Refunded transaction totals | Σ refunded transactions | Payments | PKR | Neutral | Fixture coverage limited |
| booking_count | Booking count | Bookings in date range | COUNT bookings | Bookings | N/A | Neutral | — |
| customer_count | Unique customers | Unique customer emails in range | DISTINCT customerEmail | Bookings | N/A | Neutral | Email proxy only |
| agent_assisted_booking_count | Agent-assisted bookings / sales | Bookings with agent source | COUNT agent channel | Bookings | N/A | Neutral | String heuristic |
| direct_booking_count | Direct bookings / sales | Non-agent bookings | COUNT direct channel | Bookings | N/A | Neutral | — |
| supplier_exposure | Supplier exposure | Supplier-linked booking exposure | Gross value by supplier | Bookings | PKR | Neutral | Not supplier-ledger settlement |
| issued_ticket_count | Issued tickets/documents | Tickets with Issued status | COUNT issueStatus=Issued | Tickets | N/A | Positive | issueDate filter |
| pending_fulfilment_count | Pending fulfilment | PNRs pending fulfilment | COUNT fulfilment Pending/Partial | PNRs | N/A | Decrease positive | — |
| pnr_order_count | PNR / order volume | PNR records in range | COUNT PNRs | PNRs | N/A | Neutral | — |
| gds_share / GDS PNR count | GDS PNR count | Sabre GDS PNR records | COUNT referenceType=GDS PNR | PNRs | N/A | Neutral | Distinct from NDC |
| ndc_share / NDC order count | NDC order count | NDC order records | COUNT referenceType=NDC Order | PNRs | N/A | Neutral | Distinct from GDS |
| collection_rate | Collection rate | Collected / gross booking value | collected ÷ gross × 100 | Bookings + Payments | N/A | Positive | Unavailable when gross=0 |
| review_required_count | Reconciliation required / Ticketing blocked | Context-specific queue counts | COUNT queue states | Payments/PNRs | N/A | Warning | Label varies by module |

**Payments module:** collection rate = `collected_payments ÷ gross_booking_value × 100`, unavailable when denominator is zero.

**Operations module:** GDS, NDC, One API, Manual and Mock channels remain separate lanes. Ticketing and cancellation states are informational only.

Future live-data: server aggregations, RBAC-filtered scopes, multi-currency with explicit FX policy.
