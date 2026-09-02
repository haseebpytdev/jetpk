# Provider registry

Connections loaded once via `SupplierConnection` agency-scoped query (`orderBy id`).

`PROVIDER_REGISTRY_P95_MS` ≈ 40 ms (after).

Non-flight connections (smtp, al_haider) are skipped with `non_flight_provider` — still iterated but not instantiated as flight adapters for search HTTP.

`UNNECESSARY_PROVIDER_INSTANTIATIONS`: adapters resolved only for non-skipped connections.
