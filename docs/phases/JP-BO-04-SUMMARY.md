# JP-BO-04 — Phase Summary (Stage A predeploy closure)

## Phase name
**JP-BO-04** — Master OTA Full Back-Office Operational Parity (Stage A)

## Branch name
`phase/jp-bo-04`

## Objective
Close Stage A engineering for JetPakistan Dashboard operational parity against master OTA: mint reviewable engineering SHAs, green focused Laravel + Playwright gates, reconcile PARTIAL classifications, and stop before deploy / Stage B live proof.

## Included scope
- 04A Payment Review / inbox shared authority
- 04B Booking lifecycle capability contract (PNR, mark-paid, issue, cancel, deferred void)
- 04C Multi-connection Integrations + AbhiPay Save/mask UX
- 04D Finance accounting credit/debit/reversal + commissions
- 04E SMTP/comms, users/RBAC wiring, current CMS operational controls
- 04F Playwright operational matrix + coverage harness
- Docs Stage A predeploy closure (this commit)

## Excluded scope
- Production deploy / SSH / SFTP / SCP
- Live Sabre PNR create/retry/cancel/ticket/void/refund
- Real AbhiPay payment or owner credential mutation
- Production SMTP credential change
- OWNER_RETEST_V3 PASS
- Portable CMS platform architecture

## Investigation findings
1. Payment Review badge historically counted unpaid/partial bookings but deep-linked to payment ledger.
2. Booking detail lacked server `operationalCapabilities` for safe high-risk actions.
3. Sabre Void service class exists but live gate is off — UI must stay disabled.
4. Finance remaining gaps are depth/polish or Stage-B settlement — not missing primary operator controls.
5. Playwright required live Dashboard build + fixture/stub hybrid; Laravel PHPUnit remains mutation truth.

## Root causes
1. Semantic mismatch on Payment Review badge vs page source.
2. Missing capability presenter for booking operator actions.
3. Void live call intentionally deferred (`void_live_call_enabled=false`).
4. Preview gate previously suppressed in live builds blocked RBAC denial simulation (fixed in 04E shell).

## Exact files changed
See `tmp/jp-bo-04/runtime-manifest.txt` and commits 04A–04F.
- `EXACT_RUNTIME_FILE_COUNT=80` (JP-BO-04 delta from starting docs SHA)
- `MIGRATIONS=0`
- `UNRELATED_RUNTIME_FILES=0`
- `CMS_PLATFORM_SCOPE_LEAKS=0`

## Routes changed
Operator JSON/`format=json` paths and Next deep links (payment review queues, booking actions, integrations/api-settings, accounting/SMTP). No public commercial route expansion.

## Database changes
None. `MIGRATIONS=0`.

## Backend changes
OperationalInboxAuthority, BookingOperationalCapabilitiesPresenter, booking/finance/SMTP/SupplierConnection JSON contracts, admin cancel + payment override paths.

## Frontend changes
Inbox badge, booking operational actions, integrations multi-connection + AbhiPay config Save, accounting workspace, SMTP workspace, current CMS homepage panel, DataSourcePreviewGate in live builds when query present.

## Tests executed
| Gate | Result |
| --- | --- |
| Laravel focused JP-BO-04 | **35 passed / 223 assertions** |
| Dashboard typecheck | **PASS** |
| JP-BO-04 Playwright matrix | **39 passed** |
| Dashboard production build (live+mock) | **PASS** |
| Public frontend | **NOT_REQUIRED** |

## Screenshots
Sanitized under `tmp/jp-bo-04/playwright/` (01–15 matrix proofs). Untracked evidence only.

## Responsive / accessibility
Sidebar desktop + mobile matrix in Playwright; `:focus-visible` preserved (no global focus suppression).

## Known limitations
- `FINANCE_PARITY=PASS_WITH_NON_CRITICAL_DIFFERENCES` (no owner-unapproved exclusions; see master parity doc)
- `SABRE_VOID_SUPPORT=SUPPORTED_EXISTING_LIVE_GATE_DISABLED` (service exists; live gate remains off)
- Stage B live proof not run
- Last Sabre booking read-only baseline pending Stage B Tier 1

## Risks
Large back-office delta; requires owner/ChatGPT SHA review before protected deploy.

## Rollback
`git revert` 04A–04F engineering commits on `phase/jp-bo-04` (do not deploy until approved).

## Commit SHAs
| Slice | SHA |
| --- | --- |
| 04A | `c93d9a53b66177d8fd8e777fe53667e42c4f56e6` |
| 04B | `125b34adaf1526ce483f2234a47f3ea85e003eff` |
| 04C | `0e51f4a4858b5e4e48eb6068233eb92a8e7c425e` |
| 04D | `3154f7e082985419e36a2ec0b38e2791b79ec3b7` |
| 04E | `f22c87bd0fcb32fa41a204a3cadc625ae7df7ff1` |
| 04F | `f70e56b30705e32613dbe6316c2a5fb97d6f17bd` |
| Classification correction | `ec9f0ba257a4ef96149bd8474627beec2e2d5a4d` |
| **FINAL_ENGINEERING_SHA** | `ec9f0ba257a4ef96149bd8474627beec2e2d5a4d` |

## Final status
**STAGE_A_CLASSIFICATION_CORRECTED — AWAITING OWNER/CHATGPT STAGE-B AUTHORIZATION — DEPLOY=NO**
