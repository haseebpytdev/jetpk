# Phase 5 — Workflow context ownership audit

## Binding dimensions

| Dimension | Storage | Enforced by |
|-----------|---------|-------------|
| Owner user ID | `OneApiWorkflowContext.ownerUserId` | `OneApiWorkflowContextGuard::authorizeHttp` / booking mutation |
| Agency ID | `agencyId` + connection `agency_id` | Guard agency scope |
| Supplier connection | `connectionId` | Guard connection match |
| Session fingerprint | `sessionFingerprint` (hash of Laravel session id) | Guard session binding |
| Booking ID | `bookingId` (bound on first booking mutation) | `authorizeBookingMutation` |
| Signed offer | `signedOfferFingerprint` + payload | Set at create; future tamper checks |
| Expiry | `expiresAtIso` | Guard `assertNotExpired` |
| Lifecycle | `lifecycleStatus` (`active`, `completed`, `locked_ambiguous`) | Guard lifecycle checks |

## Entry points

| Entry | Auth | Ownership |
|-------|------|-----------|
| `OneApiCheckoutController@catalog` | `auth` middleware + 401 in controller | Guard HTTP |
| `OneApiCheckoutController@saveSelections` | same | Guard HTTP |
| `OneApiCheckoutFlowService` (internal/matrix) | `internalFixtureRunner=true` or matrix scope | `authorizeInternalFixtureRunner` |
| `OneApiBookingService::createSupplierBooking` | Actor user + booking row | `authorizeBookingMutation` |
| `OneApiFareRevalidationService` | Server-side revalidation | Creates context with agency; owner claimed on first HTTP catalog |

## HTTP hardening (Phase 5)

- One API checkout routes wrapped in `auth` middleware.
- `fixture_path`, `fixture_paths`, `fixture_key` **prohibited** on final-price POST.
- Cross-user / wrong-connection access returns **404** `workflow_not_found` (no enumeration).

## Tests

- `OneApiWorkflowOwnershipFeatureTest` — unauthenticated, guard cross-user, HTTP cross-user catalog, wrong connection, fixture param rejected.

## Remaining gaps (honest)

- Booking-row middleware on catalog routes (context still keyed by opaque UUID; booking bound at supplier book).
- Passenger-profile fingerprint mismatch rejection (field present; dedicated negative tests partial).
- Hold-payment / reservation-read HTTP controllers (CLI-only today) — extend guard when exposed.
