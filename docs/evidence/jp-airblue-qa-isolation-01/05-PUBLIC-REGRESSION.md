# Normal public regression

Unchanged:

```
OTA_PUBLIC_FLIGHT_RESULTS_SUPPLIERS excludes airblue
AirBlueConnectionSearchPolicy public certification still fail-closed for pending v3
QA path bypasses allowlist ONLY when source_channel=qa_staff_airblue
```

Require after deploy:

```
NORMAL_PUBLIC_AIRBLUE_TEST_EXPOSURE=0
PUBLIC_ANON_AIRBLUE_CALLS=0
public search 200 / results data 200
```
