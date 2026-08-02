# JP-OPS-02 Implementation Register

Scoped closure items derived from JP-OPS-01 auth findings and live inventory (baseline `d216a71`).

| ID | Finding | Severity | Surface | Closure action |
|----|---------|----------|---------|----------------|
| GAP-015 | Production OTP channel blocked; demo patch preserved | P2 | Laravel + Next | Provider contract + readiness docs; demo flags unchanged |
| GAP-002 / R-01 / T-01 | Dashboard `session ?? mockUser` | P3 | Dashboard | Defer JP-OPS-05 |
| OPS02-R1 | Two session shapes; frontend strips permissions/status | Root | Both | Canonical public session schema + non-lossy Next mapping |
| OPS02-R2 | Session responses lack `Cache-Control: no-store` | Root | Laravel | Private/no-store on session + CSRF JSON |
| OPS02-R3 | `419` maps to `unknown`; no CSRF refresh policy | Root | Next | Typed `csrf_expired`; conservative one-shot refresh |
| OPS02-R4 | Portal guards per-page only | Root | Next | Layout-level Laravel-authoritative guards |
| OPS02-R5 | No global stale-session recovery | Root | Next | 401 → login with `reason=session-expired` |
| OPS02-R6 | Agent Owner vs Staff not distinguished | Root | Laravel | `agency_role` + `portal_type` in session contract |
| OPS02-R7 | Preview/fixture identity bypass if misconfigured | Root | Next | Harden production-path gates |
| OPS02-R8 | Role redirect scattered | Root | Laravel | `AuthPostLoginRedirectResolver` + documented matrix |
| OPS02-DOC | CSRF/stale/disabled/enumeration not gap-itemized | — | Both | Verify + close with tests/docs |

Excluded: Customer business features, Agent staff-management, dashboard mutations, deploy, commit, push, merge, JP-OPS-03.
