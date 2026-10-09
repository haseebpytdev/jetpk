# Booking management parity matrix (legacy Blade → Next)

Reference: `resources/views/dashboard/admin/bookings/show.blade.php` (+ partials)  
Target: `dashboard/features/bookings/booking-detail-page-content.tsx` + Laravel GET `/api/dashboard/*`

| Feature | Legacy / authoritative | Next full page (JP-OPS-09) | Gap | Target |
|---|---|---|---|---|
| Command header / summary | Rich sticky header, amounts, pills | Page header + status badges + summary column | Fewer quick actions | **TARGET** (core summary present) |
| Lifecycle / status | Pipeline + badges | Status badges + audit section | No visual pipeline | PARTIAL |
| Itinerary / sectors | Full sector table | Itinerary section + drawer summary | Sector-level detail when API provides | **TARGET** (list-level) |
| Passengers | Full traveller records | Passengers section when API returns rows | Deep docs/APIS | **TARGET** when data present |
| Fare / pricing | Full breakdown + rules | Fare breakdown section | Branded fare rules | **TARGET** when data present |
| Payments | Ledger + receipts | Payment summary section | Receipt downloads | PARTIAL |
| Supplier / PNR | Panels + sync actions | PNR/supplier section | Supplier sync buttons | PARTIAL (read-first) |
| Ticketing / readiness | Large ticketing workspace | Ticket readiness section | Issue/void actions | PARTIAL (read-first) |
| Documents | Download routes | Not in Next yet | Document list/actions | MISSING → honest empty |
| Cancellation / refund | Forms + state | State via summary/audit only | Operator forms | MISSING (placeholders removed) |
| Communication / notes | Send + thread | Internal note only (live) | Email send UI | PARTIAL |
| Staff assignment | Assign UI | Removed placeholder button | Real assign form | MISSING |
| Audit / activity | Timeline | Audit metadata section | Full audit stream | PARTIAL |
| Deadlines | Explicit fields | Via itinerary dates | SLA/deadline widgets | PARTIAL |
| RBAC | Laravel routes | Preserved via Laravel GET + mutation routes | — | **TARGET** |

JP-OPS-09 restores **View → full page** navigation and removes live preview banner + placeholder financial mutations.
