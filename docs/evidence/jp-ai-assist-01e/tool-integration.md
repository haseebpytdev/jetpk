# Tool integration

```
COMMON_SEARCH_RESPONSE_LLM_REQUIRED=NO
```

Preferred: validated TravelIntent → deep link `/flights/results` / `/groups` + deterministic ranking; no invented fares.

| Metric | Value |
|--------|------:|
| AI_FLIGHT_SEARCH_READ_CALLS | 1 (deep-link construction smoke) |
| AI_GROUP_SEARCH_READ_CALLS | 1 (deep-link construction smoke) |
| AI_INVENTED_CURRENT_FARES | 0 |
| AI_GROUP_BOOKING_AUTH_BYPASS | 0 |
| AI_SUPPLIER_MUTATION_CALLS | 0 |
