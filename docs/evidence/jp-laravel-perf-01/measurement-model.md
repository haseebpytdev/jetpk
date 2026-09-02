# JP-LARAVEL-PERF-01 — Measurement model

## Clocks

| Symbol | Meaning |
|---|---|
| `search_perf_id` | Sanitized UUID per search (no PII/credentials) |
| `T0` … `T13` | Server marks from controller entry → first `adapter->search` |
| `INIT_RESPONSE_MS` | Server time until progressive JSON ready (`T_INIT_RESPONSE_READY`) |
| `TOTAL_PRE_SUPPLIER_MS` | Server `T0` → first eligible provider network start (`T13`) |
| `init_wall_ms` | Client HTTP wall of `GET /laravel/flights/results/search` |

## JP-NEXT-PERF-02D relationship

02D `ONEWAY_LARAVEL_PRE_SUPPLIER_*` was **browser wall of search-init during full results page load**, not server `T0→T13`.

That wall includes:

- browser connection pool / page-load contention
- TLS / proxy RTT
- occasional OLS/PHP queue delay

It does **not** equal application CPU until first supplier socket.

## Multi-supplier

Dispatch mode remains `SEQUENTIAL` (`foreach` connections). Provider start offsets and spread are recorded per eligible provider. `max(elapsed_ms)` must not be treated as parallel wall.

## Token / auth network

Supplier token refresh inside `adapter->search` is attributed to supplier elapsed, not Laravel prep. Separate `PRE_SEARCH_SUPPLIER_AUTH_NETWORK_MS` reserved when adapters report it.
