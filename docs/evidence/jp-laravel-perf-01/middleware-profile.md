# Middleware profile

Route: `web` + `platform.module:public_flight_search` + `throttle:public-flight-results-search`.

No auth middleware on public search.

Measured auth/context inside controller ≪ 10 ms. Full middleware wall is included in client `init_wall_ms` / OLS variance, not separated further in this phase.

`RATE_LIMIT_WAIT_MS_P95`: limiter is allow/reject (30/min) — no sleep observed.
