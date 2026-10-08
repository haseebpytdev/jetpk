# JP-DASH-PROD-01 Write Bridge Matrix

Architecture preserved: `GET /api/dashboard/*` read-only; writes via Laravel admin/staff/agent/customer mutation routes.

| UI Surface | HTTP | Laravel Route Class | Classification |
|------------|------|---------------------|----------------|
| Booking note/status | PATCH/POST | `Admin\\Booking*` controllers | SUPPLIER_CERTIFICATION_REQUIRED for supplier actions; QA synthetic safe for notes |
| Payment verify/reject | POST | `Admin\\BookingPayment*` | PASS pattern (not executed this pass) |
| Cancellation approve/reject | POST | `Admin\\BookingCancellation*` | PASS pattern (QA fixture id=4) |
| Refund review | POST | `Admin\\BookingRefund*` | PASS pattern (QA fixture id=1) |
| Users CRUD | POST/PATCH | `Admin\\UserManagementController` | PASS pattern |
| Staff permissions | PATCH | `Admin\\Staff*` | PASS pattern |
| Markups CRUD | POST/PATCH/DELETE | `Admin\\Markup*` | PASS pattern (QA markup id=1) |
| CMS page | POST/PATCH/DELETE | `Admin\\Cms*` + dashboard UI | PASS pattern (prior DOR harness) |
| CMS media | POST/DELETE | agency media endpoints | PASS pattern (prior DOR harness) |
| API Connections | POST/PATCH/DELETE | `Admin\\ApiSettings*` | PASS modal/catalog; create executed in prior harness only |
| Support ticket | POST | portal support controllers | PASS pattern (QA ticket id=23) |
| Reports | GET | `/api/dashboard/reports/*` | READ_ONLY_BY_DESIGN |
| Audit | GET | `/api/dashboard/audit/*` | READ_ONLY_BY_DESIGN |
| SMTP/Google OAuth status | GET | settings integrations read | READ_ONLY_BY_DESIGN |

No `POST/PATCH/DELETE` added to `/api/dashboard/*` during certification.
