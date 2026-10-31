# JP-AIRBLUE-QA-ISOLATION-01 — Preflight

DATE: 2026-10-31

```
ORIGIN_MAIN_SHA=706b8d79adcbf9e8cd4ff2d3b5848ff1be13176e
LOCAL_BRANCH=fix/jp-airblue-qa-isolation-01
LOCAL_SHA=706b8d79adcbf9e8cd4ff2d3b5848ff1be13176e (base; WIP commits follow)
```

Live baseline (prior closed leak certification retained):

```
PUBLIC_SUPPLIER_COST_LEAKAGE=PASS
PUBLIC_SEARCH=200
RESULTS_DATA=200
```

Conn23 safe fields (no credentials):

```
ID=23
PROVIDER=airblue
ENVIRONMENT=sandbox
HOST=otatest4.zapways.com
PROTOCOL=v3 / 3.0
SERVICE_TARGET=Test
PUBLIC_ALLOWLIST_AIRBLUE=NO
```

```
OTA_PUBLIC_FLIGHT_RESULTS_SUPPLIERS=duffel,sabre,iati,pia_ndc
AIRBLUE_IN_NORMAL_PUBLIC_ALLOWLIST=NO
```
