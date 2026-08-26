# JP-BO-04G — Final Sabre Sandbox Lifecycle

**Phase:** JP-BO-04G Owner-authorized Sabre sandbox clone + isolation hardening + protected deploy  
**Branch:** `phase/jp-bo-04g-progressive`  
**PREVIOUS_ENGINEERING_SHA:** `fc96b2cf51ccdbbe86f6a86790adfe29055f8dc6`  
**FINAL_SANDBOX_ENGINEERING_SHA:** `2e251067c33b7ed3912d4d387f5ab2903695849b`  
**FINAL_DOCS_SHA:** `83ff2bae45fc9399d003cfc548b82706ea2a7ea7`  
**GIT_0_0:** YES (after docs push)  
**Production base (commerce):** `4ff3af2721b179e5cf5e0a55fde11aa65b451bc9`  
**Public build (unchanged):** `5jcScCO5Ujc-40-4nw1kr`  
**Backup:** `jp-bo-04g-sandbox-admin-cancel-20260826T091042Z`  
**Release staged:** `/home/pkjetp/releases/jetpk-20260826T091342Z`  
**Mode:** Owner-authorized credential clone into NEW sandbox row only; live Sabre mutation forbidden

---

## Outcome classification (B)

CERT rejected cloned live credentials against the CERT/Sandbox host. Safe engineering is deployed. Network certification is deferred. No live PNR create/cancel occurred.

| Gate | Result |
| --- | --- |
| SANDBOX_SABRE_LIFECYCLE | `DEFERRED_EXTERNAL_CERT_AUTH` |
| FLIGHT_COMMERCE_IMPLEMENTATION | COMPLETE |
| FLIGHT_COMMERCE_PRODUCTION | PASS |
| COMMERCE_PRODUCTION_CERTIFICATION | PASS |
| BACK_OFFICE_LIVE_PROOF | PASS |
| OWNER_RETEST_V3 | `PASS_WITH_SANDBOX_NETWORK_CERTIFICATION_DEFERRED` |
| GROUP_TICKETING_READY | YES |
| JETPAKISTAN_FINAL_DEPLOYED_RUNTIME_SHA | `2e251067c33b7ed3912d4d387f5ab2903695849b` |

---

## Objective

Harden sandbox QA to an exact CERT connection pin, support owner-authorized credential clone into a separate `sabre-sandbox-qa` row, deploy regardless of CERT auth, and hand off to Group Ticketing without live Sabre mutations.

## Included scope

- Exact sandbox connection pin (search / PNR create / cancel)
- Owner-authorized `--clone-credentials-from-connection` (read → new row only)
- CERT host-only guards; no live fallback
- Protected deploy of sandbox/cancel engineering
- Live Sabre immutability proof
- Group Ticketing handoff readiness

## Excluded / deferred

- Sandbox network search / PNR / cancel (blocked: CERT auth rejected cloned credentials)
- Any live Sabre PNR create or cancel
- Mutation of live SupplierConnection id=1
- Group Ticket implementation (next dedicated phase)

---

## Live Sabre protection

| Gate | Result |
| --- | --- |
| LIVE_SABRE_CONNECTION_ID | 1 |
| LIVE_SABRE_ALIAS_SAFE | JEtPK Binham Sabre |
| LIVE_SABRE_ENVIRONMENT | live |
| LIVE_SABRE_HOST | api.platform.sabre.com |
| LIVE_SABRE_CONFIG_HASH_BEFORE | `4711feb2c5815804ce877c6823dea03e24d44558e0fd9c37683cc365755cd059` |
| LIVE_SABRE_CONFIG_HASH_AFTER | `4711feb2c5815804ce877c6823dea03e24d44558e0fd9c37683cc365755cd059` |
| LIVE_SABRE_CONNECTION_MUTATED | NO |
| LIVE_CONNECTION_CONFIG_DRIFT | 0 |
| LIVE_QA_PNR_CREATED_COUNT | 0 |
| LIVE_CANCEL_SEND_COUNT | 0 |

---

## Sandbox connection (clone)

| Gate | Result |
| --- | --- |
| SANDBOX_CONNECTION_CREATED | PASS |
| SANDBOX_CONNECTION_ID | 4 |
| SANDBOX_CONNECTION_ID_DIFFERS_FROM_LIVE | YES |
| SANDBOX_ENVIRONMENT | SANDBOX |
| SANDBOX_HOST | api.cert.platform.sabre.com |
| SANDBOX_PUBLIC_ROUTING | NO |
| SANDBOX_QA_ONLY | YES |
| CREDENTIAL_SOURCE | owner_authorized_clone |
| SANDBOX_AUTH | BLOCKED |
| SANDBOX_ENDPOINT | BLOCKED |
| SANDBOX_NETWORK_CERTIFICATION | `DEFERRED_CREDENTIALS_NOT_ACCEPTED_BY_CERT` |
| Post-auth status | inactive / unhealthy (intentional) |

---

## Isolation gates (source + tests)

| Gate | Result |
| --- | --- |
| QA_SANDBOX_EXACT_CONNECTION_PIN | PASS |
| QA_SANDBOX_SEARCH_CONNECTION_COUNT | 1 (contract) |
| QA_SANDBOX_LIVE_CONNECTION_ELIGIBLE | NO |
| PUBLIC_SANDBOX_FANOUT | 0 |
| NORMAL_PUBLIC_LIVE_ROUTING_UNCHANGED | PASS |
| QA_SANDBOX_PNR_CREATE_PRODUCTION_GUARD | PASS |
| QA_SANDBOX_CANCEL_EXACT_CONNECTION_PIN | PASS |
| ADMIN_DIRECT_CANCEL_PNR | PASS (deployed orchestration) |

---

## Sandbox lifecycle (network)

| Gate | Result |
| --- | --- |
| SANDBOX_SEARCH | NOT_RUN |
| SANDBOX_QA_PNR_CREATED_COUNT | 0 |
| SANDBOX_CANCEL_SEND_COUNT | 0 |
| SANDBOX_HOST_CANCEL_CONFIRMED | NOT_RUN |
| LOCAL_BOOKING_CANCELLED | NOT_RUN |

Do **not** claim `SANDBOX_SABRE_LIFECYCLE=PASS` — no sandbox PNR/cancel occurred.

---

## Deploy manifest

| Class | Count |
| --- | --- |
| LARAVEL_RUNTIME_FILES | 11 |
| DASHBOARD_RUNTIME_FILES | 2 |
| FRONTEND_RUNTIME_FILES | 0 |
| CONFIG_RUNTIME_FILES | 0 |
| MIGRATIONS | 0 |
| EXACT_DEPLOYABLE_FILE_COUNT | 13 |
| UNEXPECTED_RUNTIME_SUBSYSTEMS | NONE |

## Production health after deploy

| Gate | Result |
| --- | --- |
| LIVE_SOURCE_DRIFT | 0 (13/13 SHA256 match) |
| PUBLIC_PM2 | online (unchanged PID during Laravel/dashboard-only activate) |
| DASHBOARD_PM2 | online (restarted) |
| LARAVEL_HEALTH | PASS (HTTP 200 `/up`) |
| PUBLIC_HOME | HTTP 200 |
| PUBLIC_BUILD_UNCHANGED | PASS |
| OWNERSHIP_DRIFT | 0 |
| PUBLIC_5XX | 0 (smoke) |
| LARAVEL_5XX | 0 (smoke) |
| JS_FATAL_ERRORS | 0 (smoke) |

---

## Tests executed (local)

| Suite | Result |
| --- | --- |
| `SabreSandboxQaExactConnectionPinTest` | PASS |
| `SupplierPublicRoutingAndSandboxQaGuardTest` | PASS |
| `SabreSandboxQaCredentialCloneTest` | PASS |
| `AdminDirectCancelBookingTest` | PASS |
| CancellationRefundWorkflow / AdminDirectCancel filter | PASS (25) |
| `BookingOperationalCapabilitiesPresenterTest` | PASS |

---

## Engineering delivered

1. `SabreSandboxQaConnectionPin` + `SabreSandboxQaSearchService` — exact one-connection sandbox QA search  
2. `FlightSearchService` — `admin_qa_sandbox` refuses fanout without internal pin  
3. `SupplierPublicRoutingGuard` — live skipped on sandbox QA channel  
4. `SabreSandboxQaLifecycleGuard` — PNR create + cancel exact-pin guards  
5. `SabreBookingService` — pre-HTTP sandbox QA production guard  
6. `SabreSandboxQaConnectionProvisioner` — CERT profile **or** owner-authorized clone  
7. `sabre:ensure-sandbox-qa-connection --clone-credentials-from-connection=`  
8. Admin Cancel PNR sandbox exact-pin retention  

---

## Rollback

Restore the 13 staged paths from backup  
`/home/pkjetp/backups/jp-bo-04g-sandbox-admin-cancel-<UTC>/`  
(and/or release rollback package under `/home/pkjetp/releases/jetpk-rollback-*-jp-bo-04g-sandbox`).  
Clear Laravel caches as `pkjetp`. Restart `jetpk-dashboard` if dashboard paths rolled back.  
Do not touch live SupplierConnection credentials.

---

## NEXT

Start dedicated Group Ticket phase.  
Optional later: install dedicated Sabre CERT credentials (or Sabre-accepted cert EPR) and re-run one sandbox search → one sandbox PNR → Admin Cancel PNR.
