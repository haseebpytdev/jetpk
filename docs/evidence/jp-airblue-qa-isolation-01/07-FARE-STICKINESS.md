# Fare stickiness

Consolidation proves multi-family inventory on conn23:

```
FARE_FAMILIES_AVAILABLE=EV,EF,EX
PREFERRED_NON_DEFAULT=EF
```

Staff browser corridor (`/staff/qa/airblue-search` → results → select EF → passengers → review) requires authenticated platform_admin/staff session.

```
AIRBLUE_FARE_STICKINESS=PENDING_STAFF_BROWSER_UAT
AIRBLUE_CUSTOMER_TOTAL_STICKINESS=PENDING_STAFF_BROWSER_UAT
```

Hard stop remains: no AirBook during stickiness UAT.
