# Authorization matrix

| Role | Initiate QA search | Read QA results/data | Select QA offer | Expected |
|---|---|---|---|---|
| anonymous | DENY | DENY (404) | DENY (404) | DENY |
| customer | DENY | DENY | DENY | DENY |
| agent | DENY | DENY | DENY | DENY |
| agent_staff | DENY | DENY | DENY | DENY |
| staff | ALLOW (if scope ok) | ALLOW (own QA session) | ALLOW | ALLOW |
| platform_admin | ALLOW (if scope ok) | ALLOW (own QA session) | ALLOW | ALLOW |

Guards:

- Route middleware: `auth` + `account.type:platform_admin,staff`
- `QaFlightSearchAccessGuard::assertCanInitiate`
- `QaFlightSearchAccessGuard::assertCanAccess` on results/data, offer, revalidate, return-split, passengers, short-ref resolve
- Session must remain QA-active (`QaStaffAirBlueContext::isActive`)
