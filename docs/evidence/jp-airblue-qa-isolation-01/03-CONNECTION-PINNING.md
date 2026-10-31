# Connection pinning

QA channel `qa_staff_airblue` loads **only** `config('ota.qa_staff_airblue.connection_id')` (default 23).

```
QA_CONNECTION_IDS=[23]
QA_PROVIDER_CALLS_AIRBLUE_22=0 (by design — not in collection)
QA_PROVIDER_CALLS_SABRE=0
QA_PROVIDER_CALLS_PIA=0
```

Scope rejects: wrong id, non-airblue, live env, wrong host, non-v3 protocol, inactive, supplier_search module off, unauthorized actor.
