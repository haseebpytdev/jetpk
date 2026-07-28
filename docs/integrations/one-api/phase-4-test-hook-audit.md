# Phase 4 — Test hook audit

## Findings

| Location | Hook | Production risk | Resolution |
|----------|------|-----------------|------------|
| `OneApiBookingService` | `one_api_diagnostic.fixture_path` + `runningUnitTests()` | **Removed** | Deleted; tests pass `fixture_path` only via service method argument under PHPUnit |
| `OneApiSoapTransport` | `fixture_path` / `fixture_paths` in diagnostic context | **Gated** | `OneApiFixtureTransportScope::resolveReadableFixturePath()` — allowed only when scope enabled or PHPUnit fixtures allowed |
| `OneApiCheckoutFlowService` | Default `base_path('tests/.../price_base.xml')` | **Removed** | No default test XML in production path; callers must pass fixture only under gate |
| `OneApiTestMatrixRunner` / matrix command | Passes fixture paths | **OK** | Matrix enables scope (`OneApiFixtureTransportScope::enable('matrix_command')`) in fixture mode |
| `OneApiFareRevalidationService` | Optional `$fixturePath` argument | **OK** | Used from tests/matrix only with gate |
| Probe CLI commands | `--fixture` paths | **OK** | Console-only; not web-accessible |
| `OneApiFlightSearchService` | `runningUnitTests()` for live search bypass | **Review** | Existing supplier pattern; not expanded in Phase 4 |

## `OneApiFixtureTransportScope`

- PHPUnit: fixtures allowed by default (`OneApiEnablesFixtureTransport` trait).
- Production HTTP: **cannot** read fixture files (scope disabled).
- Matrix fixture mode: explicit `enable('matrix_command')`.
- Paths must resolve under `tests/Fixtures/Suppliers/OneApi` (traversal blocked).

## Tests

- `OneApiFixtureTransportSecurityTest` — scope disabled + traversal rejection + SOAP rejection.

## Remaining intentional fixture surfaces

- PHPUnit / matrix / fixture probe commands only.
- No SupplierConnection credential field selects fixtures.
- No request parameter activates fixtures on web routes.
