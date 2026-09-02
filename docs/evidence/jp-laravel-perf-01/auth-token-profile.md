# Auth / token profile

Public guest search: no login required.

`AUTH_CONTEXT_P95_MS` ≈ 3.5 ms (default agency resolve).

`PRE_SEARCH_SUPPLIER_AUTH_NETWORK_P95_MS=0` in measured samples (token cache hits / no separate pre-search auth HTTP classified).

Sabre/IATI/OneAPI token work inside `adapter->search` remains supplier elapsed.
