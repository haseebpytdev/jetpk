# Tool safety

Allowed (manifest): search_flights, search_groups, get_offer_details, compare_offers, create_fare_link, create_group_link, search_jetpakistan_knowledge, create_support_handoff.

Forbidden: create_pnr, confirm_booking, issue_ticket, payment/wallet mutate, cancel/refund/void, shell/sql, read_env.

Flights in V1: deep-link + `/f/{code}` short link; **no invented fares** (price null until customer opens View & Book).

Groups: `GroupInventorySearchService` published inventory only + `/g/{code}`.

Prompt injection / secret requests: refused in orchestrator sanitize; no privileged tool escape.

`AI_SUPPLIER_MUTATION_CALLS=0` by architecture.
