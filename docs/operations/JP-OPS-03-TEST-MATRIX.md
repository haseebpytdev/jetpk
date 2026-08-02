# JP-OPS-03 Test Matrix

## Laravel

| Test file | Coverage |
|-----------|----------|
| `CustomerPortalOperationalClosureTest` (8) | Cancel JSON, duplicate 409, capabilities, travelers JSON + masking, passenger immutability, dashboard scope, support close, document download safety |
| `CustomerPortalJsonContractTest` (5) | Dashboard, bookings, support, notifications stub, profile |
| `CustomerBookingOwnershipTest` (3) | Cross-customer deny |
| `CustomerInvoiceOwnershipTest` (3) | Invoice ownership |
| `CancellationRefundWorkflowTest::test_customer_can_request_cancellation_for_own_booking` | Blade cancellation POST via booking_reference |

Command:

```bash
php artisan test \
  tests/Feature/Customer/CustomerPortalOperationalClosureTest.php \
  tests/Feature/Customer/CustomerPortalJsonContractTest.php \
  tests/Feature/Jetpk/CustomerBookingOwnershipTest.php \
  tests/Feature/Jetpk/CustomerInvoiceOwnershipTest.php \
  tests/Feature/CancellationRefundWorkflowTest.php \
    --filter=test_customer_can_request_cancellation_for_own_booking
```

## Frontend regression (permanent)

| Script | Tests |
|--------|------:|
| `npm run test:jp-ops-02-client-security` | JP-OPS-02 shared client |
| `npm run test:jp-ops-03-customer-regression` | 9 API errors + 2 allowlist + 6 mutation wiring + 6 CSRF one-attempt = **23** |
| `npm run test:jp-ops-03-customer-operational` | Playwright JP-OPS-03 customer operational spec (**8**) |

Also: `customer-dashboard.spec.ts`, `customer-portal-routes.spec.ts`, `auth.spec.ts`, `jp-ops-02-portal-guards.spec.ts`.

## Document count

- Operations: **11** (`JP-OPS-03-*`, implementation register included)
- Phase: **1**
- Total: **12**

## Changed-file count (canonical)

- Tracked diff vs `770a29c…`: **24**
- Untracked new: **25**
- Working-tree delta: **49**
