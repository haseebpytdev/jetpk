# Phase 4 — SupplierConnection test integrity

## Phase 3 change reviewed

`SupplierConnectionCrudTest::seededAdmin()` was temporarily changed to force **PlatformAdmin**. That change was **reverted** in Phase 4.

## Answers

1. **Necessary for One API production?** No. One API admin coverage belongs in `OneApiSupplierConnectionFeatureTest` and platform RBAC tests.
2. **Changes generic test semantics?** Yes — the suite name implies agency admin; promoting to platform admin masked RBAC regression.
3. **Hides agency-admin denial?** Yes — `LegacyAgencyAdminAuthorizationTest::test_agency_admin_cannot_access_admin_api_settings` documents intended **403** for agency admin.
4. **Correct solution?** Dedicated One API connection tests + use `PlatformAdminAuthorizationTest` for API settings access; do **not** change generic CRUD seed user.

## Current failing generic tests (current tree, agency admin seed)

| Test | Expected | Actual | Cause |
|------|----------|--------|--------|
| `test_agency_admin_can_view_api_settings` | 200 | **403** | `account.type:platform_admin` on admin routes + agency admin user |
| `test_agency_admin_cannot_delete_another_agency_supplier_connection` | 403 | **302** | Redirect/guest path vs forbidden (policy/middleware) |
| Duffel edit HTML assertions (2) | masked token absent | HTML mismatch | Unrelated to One API |

## One API–specific admin tests

- `OneApiSupplierConnectionFeatureTest` — **passes** (normalizer + encryption).

## Recommendation for isolated One API commit

**Exclude** `tests/Feature/SupplierConnectionCrudTest.php` unless/until generic RBAC expectations are updated in a separate RBAC phase.
