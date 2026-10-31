# QA search — live evidence

DATE: 2026-10-31

Server-side QA channel probe (`source_channel=qa_staff_airblue`):

```
SCOPE_ALLOWED=YES
CONNECTION_ID=23
PROVIDER=airblue
ENVIRONMENT=sandbox
PROTOCOL=3.0
HOST=otatest4.zapways.com
SERVICE_TARGET=Test
ROUTE=LHE→KHI one_way
DEPART=2026-11-21
RAW_OFFERS=6
QA_CONNECTION_IDS={"23":6}
QA_PROVIDERS={"airblue":6}
AFTER_CONSOLIDATION_STORED=2
FARE_FAMILIES=EV,EF,EX
BOOK_CREATE_CALLS=0
```

Display sample (hashed offer ids only): customer totals present; `has_supplier_total=false` on sampled offers.
