# One API Phase 4 — Route and command security (extends Phase 3)

## HTTP routes (revalidated)

| Method | URI | Mutation | CSRF | Auth | Fixture hook via HTTP |
|--------|-----|----------|------|------|------------------------|
| GET | `/booking/one-api/catalog` | No | N/A | `web` + `platform.module:customer_checkout` | **No** — controller passes only `workflow_context_id`; no `fixture_path` |
| POST | `/booking/one-api/final-price` | Yes | Yes | same + throttle | **No** — validation excludes fixture/diagnostic keys |
| POST | `/booking/one-api/selections` | Yes | Yes | alias | **No** |
| GET | `/booking/one-api/extras` | No | N/A | deprecated catalog | **No** |

## Production fixture isolation (Phase 4)

- `OneApiFixtureTransportScope` — fixture file reads only when PHPUnit (toggleable) or `enable()` from `ota:one-api-test-matrix --mode=fixture`.
- `OneApiSoapTransport` + checkout flow call `resolveReadableFixturePath()` — throws `fixture_forbidden` when scope off.
- `OneApiCheckoutController` does not accept `fixture_path`, `fixture_paths`, or transport mode query params.
- SupplierConnection credentials schema has **no** fixture path fields.

## CLI commands

| Command | Live network | Fixture | Gates |
|---------|--------------|---------|-------|
| `ota:one-api-test-matrix` | Only with explicit live flags | `--mode=fixture` enables scope | `--connection` required |
| `ota:one-api-search-probe` | `--live` + confirm | fixture default in non-prod patterns | mutation gate |
| `ota:one-api-price-probe` | confirm flags | explicit fixture arg only when scope enabled | mutation gate |
| `ota:one-api-read-reservation` | confirm | fixture arg scoped | mutation gate |
| `ota:one-api-phase-4-inventory` | No | No | local manifest only |

## Gaps (honest)

- Checkout routes still use opaque `workflow_context_id` rather than booking-row ownership middleware (Phase 3 gap retained).
- No dedicated HTTP feature test asserting unknown keys cannot enable fixtures (covered indirectly: validation whitelist + scope unit tests).

## Phase 4 proof tests

- `OneApiFixtureTransportSecurityTest` — scope off, traversal rejected, SOAP fixture rejected.
- `OneApiCheckoutController` validation — `client_total` / `posted_supplier_amount` prohibited.
