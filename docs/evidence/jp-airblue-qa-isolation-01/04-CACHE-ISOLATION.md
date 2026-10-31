# Cache isolation

`FlightSearchService::buildCriteriaCacheContext` includes `source_channel`.

```
public_guest ≠ qa_staff_airblue
```

QA store meta also sets:

- `criteria_cache_context.search_mode=airblue_qa`
- `criteria_cache_context.result_source=qa_staff_airblue`
- `qa_connection_id` / `qa_actor_user_id`

Supplier result-cache fingerprints cannot collide with anonymous public_guest entries for the same OD.
