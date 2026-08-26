# JP-BO-04G — Final Sabre Sandbox Lifecycle

**Phase:** JP-BO-04G Final Sabre Sandbox Lifecycle + Admin One-Action Cancel PNR  
**Branch:** `phase/jp-bo-04g-progressive`  
**Previous branch HEAD:** `93b21d0e4bf23ee402574283f044f399b24024ed`  
**FINAL_CLOSURE_ENGINEERING_SHA:** `fc96b2cf51ccdbbe86f6a86790adfe29055f8dc6`  
**FINAL_COMBINED_COMMERCE_RUNTIME_REFERENCE:** `4ff3af2721b179e5cf5e0a55fde11aa65b451bc9`  
**Mode:** Sandbox-only network lifecycle authorized; live Sabre mutation forbidden  
**Hard stop reason:** dedicated Sabre CERT/sandbox credentials not present in secure config

---

## Objective

Prove one sandbox Sabre search → one sandbox QA PNR → one Admin Cancel PNR → local cancelled, without touching the live Sabre connection or ambiguous live PNR inventory.

## Included scope

- Live Sabre connection read-only capture + config hash
- Environment authority documentation
- Sandbox public-routing exclusion
- QA production-host guard
- Admin one-action Cancel PNR confirmation modal + eligibility gates
- Sandbox connection provisioner (CERT env profiles only)
- Local unit/feature tests for guards and admin-direct-cancel failure contracts
- Ownership drift precise correction
- Final documentation

## Excluded / blocked scope

- Any live Sabre PNR create
- Any live Sabre cancel send
- Mutation of live SupplierConnection id=1
- Use of ambiguous live booking inventory as cancel target
- Sandbox network auth/search/PNR/cancel (**blocked**: credentials missing)

---

## Environment authority (canonical)

| Concern | Authority |
| --- | --- |
| SABRE_ENVIRONMENT_AUTHORITY | `supplier_connections.environment` (`App\Enums\SupplierEnvironment`: demo/sandbox/live) |
| SANDBOX_ENDPOINT_AUTHORITY | `supplier_connections.base_url` via `SabreSupplierConnectionNormalizer::baseUrlForEnvironment()` + `config('suppliers.sabre.cert_stl.base_url')` |
| PUBLIC_ROUTING_AUTHORITY | `SupplierPublicRoutingGuard` + connection `settings.public_customer_routing` / `qa_sandbox_only` / `production_default_routing` |
| CREDENTIALS_AUTHORITY | encrypted `supplier_connections.credentials` (runtime) OR CERT env profiles under `config('suppliers.sabre.cert_stl.profiles.*')` for QA provision only |
| STICKINESS | booking `meta.supplier_connection_id` (no cross-connection fallback on cancel) |

---

## Live Sabre protection proof

| Gate | Result |
| --- | --- |
| LIVE_SABRE_CONNECTION_CAPTURED | YES |
| LIVE_SABRE_CONNECTION_ID | 1 (internal) |
| LIVE_SABRE_ALIAS_SAFE | JEtPK Binham Sabre |
| LIVE_SABRE_ENVIRONMENT | live |
| LIVE_SABRE_HOST | api.platform.sabre.com |
| LIVE_SABRE_CONFIG_HASH | `4711feb2c5815804ce877c6823dea03e24d44558e0fd9c37683cc365755cd059` |
| LIVE_SABRE_CONNECTION_MUTATED | NO |
| LIVE_CONNECTION_CONFIG_DRIFT | 0 (no mutation performed) |
| LIVE_SABRE_NEW_PNR_COUNT | 0 |
| LIVE_SABRE_CANCEL_SEND_COUNT | 0 |
| LIVE_AMBIGUOUS_PNR_EXTERNAL_MUTATION | 0 |
| LIVE_AMBIGUOUS_PNR_STATE | UNCHANGED |

Private hash ledger: `/home/pkjetp/backups/jp-bo-04g-sandbox-private/live-sabre-config-hash-pre.txt`

---

## Sandbox credentials gate

| Check | Local | Production |
| --- | --- | --- |
| CERT profile cert_6md8 | incomplete | incomplete |
| CERT profile cert_lu6k | incomplete | incomplete |
| CERT profile cert_test3 | incomplete | incomplete |
| SABRE_SANDBOX_CREDENTIALS_REQUIRED | YES | YES |

**STOP SANDBOX NETWORK EXECUTION** — never fell back to live credentials/endpoint.

Required next owner action (outside chat paste): place dedicated Sabre CERT credentials into secure private env / server secret storage as `SABRE_CERT_*` profile values, then re-run `sabre:ensure-sandbox-qa-connection --test-auth` and the sandbox lifecycle.

---

## Engineering delivered (source)

1. `SupplierPublicRoutingGuard` — excludes sandbox/demo from public/agent production fanout  
2. `FlightSearchService` — channel-aware skip (`sandbox_excluded_from_production_fanout`)  
3. `SabreSandboxQaLifecycleGuard` — refuses sandbox QA when env=live or host=production  
4. `SabreSandboxQaConnectionProvisioner` + `sabre:ensure-sandbox-qa-connection`  
5. Admin Cancel PNR modal (TEST/SANDBOX badge + summary)  
6. `BookingOperationalCapabilitiesPresenter` eligibility gates + `cancel_pnr_context`  
7. `adminDirectCancel` reuses open request + sandbox host guard  

### Agent cancel policy (canonical)

Agents may **request** cancellation only. Approve/process/supplier execution remain admin/staff.  
`AGENT_CANCEL_POLICY=request_only`  
`CUSTOMER_DIRECT_CANCEL_PNR=NO`  
`ADMIN_DIRECT_CANCEL_PNR=PASS` (source/orchestration; sandbox network cancel blocked pending credentials)

---

## Tests executed

| Suite | Result |
| --- | --- |
| `SupplierPublicRoutingAndSandboxQaGuardTest` | PASS |
| `AdminDirectCancelBookingTest` | PASS |
| `BookingOperationalCapabilitiesPresenterTest` | PASS (rerun with guards) |
| CancellationRefundWorkflow subset (agent/customer/gates/pnr/ticketed) | PASS |

---

## Ownership cleanup

| Gate | Result |
| --- | --- |
| OWNERSHIP_DRIFT (before) | 6 |
| Precise chown targets | Frontend dir, FlightSearch dir, utils dir + 3 TS files → `pkjetp:pkjetp` |
| OWNERSHIP_DRIFT (after) | 0 |
| LARAVEL_HEALTH | PASS |
| No recursive chown / no 777 | YES |

---

## Sandbox lifecycle results

| Gate | Result |
| --- | --- |
| SANDBOX_CONNECTION_CREATED | BLOCKED |
| SANDBOX_AUTH | BLOCKED |
| SANDBOX_SEARCH | BLOCKED |
| SANDBOX_QA_PNR_CREATED_COUNT | 0 |
| SANDBOX_CANCEL_SEND_COUNT | 0 |
| SANDBOX_HOST_CANCEL_CONFIRMED | BLOCKED |
| LOCAL_BOOKING_CANCELLED | BLOCKED |
| SANDBOX_SABRE_LIFECYCLE | BLOCKED |

---

## Final status

**COMMERCE_PRODUCTION_CERTIFICATION=PASS** (prior pin retained)  
**BACK_OFFICE_LIVE_PROOF=PASS** (prior pin retained)  
**SANDBOX_SABRE_LIFECYCLE=BLOCKED**  
**OWNER_RETEST_V3=BLOCKED** (sandbox lifecycle incomplete)  
**JP_BO_04G=BLOCKED**  
**FLIGHT_COMMERCE_CLOSURE=BLOCKED**  
**GROUP_TICKETING_READY=NO**

**NEXT:** Owner installs dedicated Sabre CERT sandbox credentials into secure server/private env (`SABRE_CERT_*` profiles). Then resume with a dedicated prompt: ensure `sabre-sandbox-qa` connection → sandbox search → one sandbox PNR → Admin Cancel PNR → idempotency → live config hash re-check. Do not begin Group Ticketing until sandbox lifecycle PASS.
