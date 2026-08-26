# JP-API-CMS-FINAL-CLOSEOUT — Engineering Heartbeat

## Status

`ENGINEERING_COMMITTED=YES`

## Branch

`phase/jp-bo-04g-progressive`

## Baseline

```text
START_SHA=9d5e1d3fac435ee5c1a0d670be2c33692f2e18f5
REMOTE_AT_START=9d5e1d3fac435ee5c1a0d670be2c33692f2e18f5
GIT_AHEAD_BEHIND=0 0
```

## Engineering SHA

```text
FINAL_CLOSEOUT_ENGINEERING_SHA=7fbb1301e5e96eadb0acdc65e0b5fba149eb0a35
```

## Commit

`fix(admin): close managed Al-Haider auth and CMS truth`

## Included in this heartbeat

- Official Al-Haider Postman docs contract audited (public, no login required)
- Endpoint mismatches corrected: reserve `/api/create/booking`, cancel `PATCH /api/cancel/booking/{id}`
- `managed_token` auth strategy + UI fields
- DB-authoritative token store on `SupplierConnection`
- Strict expiry-only auto-renew + persistent 1/365-day issuance budget
- `AlHaiderClient::isConfigured()` recognizes DB-managed tokens without ENV secrets
- Al-Haider Test Connection = read-only groups probe, zero login
- SMTP active-invalid → ENV fallback validator
- Sidebar: single **API & Modules** entry (Supplier operations subsection inside workspace)
- Mocked renewal certification A–H
- Connected CMS text/media production-truth matrix tests

## Next

1. Protected backup `jp-api-cms-final-closeout-<UTC>`
2. Deploy immutable runtime from `FINAL_CLOSEOUT_ENGINEERING_SHA`
3. Live Al-Haider read-only inventory with existing token only (no `/api/login`)
4. Live CMS evidence screenshots committed to git
5. Final closeout docs + `USER_TESTING_READY` gate

## Hard stops still in force

- No new Al-Haider token generation
- No group reservation/booking
- No payment / ticket / cancel mutation
