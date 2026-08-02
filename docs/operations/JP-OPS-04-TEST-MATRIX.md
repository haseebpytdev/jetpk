# JP-OPS-04 Test Matrix

## Laravel (gate) — final JP-OPS-04B

| Command | File | Passed | Failed | Skipped | Disposition |
|---------|------|-------:|-------:|--------:|-------------|
| `php artisan test tests/Feature/Agent/AgentPortalOperationalClosureTest.php` | operational closure | 9 | 0 | 0 | PASS |
| `php artisan test tests/Feature/Agent/AgentStaffPermissionTest.php` | staff permissions | 12 | 0 | 0 | PASS |
| `php artisan test tests/Feature/Agent/AgentStaffSelfEscalationTest.php` | self-escalation | 8 | 0 | 0 | PASS |
| `php artisan test tests/Feature/Agent/AgentStaffTest.php` | staff lifecycle | 10 | 0 | 2 | PASS (2 skipped pre-existing) |
| `php artisan test tests/Feature/Agent/AgentLedgerTest.php` | ledger isolation | 8 | 0 | 0 | PASS |
| `php artisan test tests/Feature/Agent/AgentPortalAuditFixTest.php` | audit/security | 18 | 0 | 0 | PASS |
| `php artisan test tests/Feature/AgentWalletDepositTest.php` | wallet/deposits | 11 | 0 | 0 | PASS (JetPK theme contract corrected) |
| `php artisan test tests/Feature/SupportTicketTest.php` | support sidebar + agent | 8 | 0 | 0 | PASS |
| **Batch total** | 8 files above | **74** | **0** | **2** | **JP-OPS-04 gate green** |

### Commission ledger — JP-OPS-06 dependency (deferred, not JP-OPS-04 blockers)

| Method | Classification | Reason |
|--------|----------------|--------|
| `test_ticketing_an_agent_booking_creates_commission_entry` | C — JP-OPS-06 | Requires `admin.bookings.issue-ticket` supplier ticketing; `agent_commission_entries` empty after issue |
| `test_duplicate_ticketing_duplicate_call_does_not_create_duplicate_commission` | C — JP-OPS-06 | Depends on ticketing-created commission row |
| `test_commission_entry_stores_calculation_snapshot` | C — JP-OPS-06 | No entry created without ticketing event |
| `test_changing_rules_later_does_not_alter_old_commission_entry` | C — JP-OPS-06 | No entry created without ticketing event |

JP-OPS-04 commission **read contract** remains green via `test_agent_reports_and_commissions_json_owner_only` and `test_agent_can_view_own_commissions`.

## Frontend regression (permanent)

| Script | Tests | Result |
|--------|------:|--------|
| `npm run test:jp-ops-02-client-security` | 16+ | PASS |
| `npm run test:jp-ops-03-customer-regression` | 23 | PASS |
| `npm run test:jp-ops-04-agent-regression` | **28** | PASS (3+9+8+8) |
| `npm run test:jp-ops-04-agent-operational` | **25** | PASS (0 fail, 0 skip) |
| Consolidated Playwright gate | **68** | PASS (0 fail, 0 skip) |

Also: `npm run typecheck`, `npm run lint`, `npm run build` — all PASS.

## Playwright JP-OPS-04 operational scenarios (25)

| # | Test | Root-cause class (04B) |
|---|------|------------------------|
| 1 | owner dashboard authorized nav | incomplete test mock → fixed payloads + `portal-nav` scope |
| 2 | staff permitted dashboard nav | same |
| 3 | staff direct-route denial | already green |
| 4 | inactive agency denial | already green |
| 5 | booking list loads | incomplete booking list item shape |
| 6 | booking detail cancellation pending | already green |
| 7 | booking-create handoff | already green |
| 8 | staff list renders | route pattern + scoped assertion |
| 9 | staff create validation 422 | form-scoped labels |
| 10 | staff permission mutation denied | **new** |
| 11 | agency page loads | already green |
| 12 | wallet state renders balance | scoped `wallet-metric-card` |
| 13 | deposit submission 409 | deposit create form `summary` mock |
| 14 | deposit CTA hidden | already green |
| 15 | reports overview | `has_live_data` + `summary` shape |
| 16 | commissions owner page | already green |
| 17 | staff commissions denial | already green |
| 18 | payment-proof pending | **new** |
| 19 | support cases list | `status` object on ticket mock |
| 20 | notifications unavailable | already green |
| 21 | session expiry | already green |
| 22 | removed membership | already green |
| 23 | no private API before auth | **new** |
| 24 | staff create one POST | **new** mutation count |
| 25 | deposit submit one POST | **new** mutation count |

## Mutation request-count assertions (Playwright)

| Mutation | Test | Assertion |
|----------|------|-----------|
| Staff create | `staff create mutation sends one POST per submit` | `posts.length === 1` after dblclick |
| Deposit submit | `deposit submit mutation sends one POST per click` | `posts.length === 1` after dblclick |

(419 one-attempt cases covered in `jp-ops-04-csrf-agent-mutations.test.mjs`.)

## Wallet theme correction (Category A)

JetPakistan agent portal does not render legacy `agent-dashboard-wallet-balance` or `account-dropdown-balance` on dashboard. Tests now assert:

- Dashboard: Wallet quick link when `wallet_view` permitted
- Authoritative balance: `agent-wallet-kpis` on `agent.wallet.show` route

## Document count

- Operations: **12** (`JP-OPS-04-*`, implementation register included)
- Phase: **1**
- Total: **13**

## Changed-file count (canonical)

- Tracked diff vs `f8fa178…`: **31**
- Untracked new: **40**
- Unique total: **71**
