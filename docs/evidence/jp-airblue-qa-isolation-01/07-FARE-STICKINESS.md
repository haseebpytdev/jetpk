# Fare stickiness — pre-AirBook closure re-cert

DATE: 2026-10-31  
ACTOR_ROLE: platform_admin (rotated QA admin)  
ROUTE: LHE→KHI one-way 2026-11-21  
CONNECTION: 23 only

```
FAMILIES=EV,EF,EX
SELECTED=EF
FARE_OPTION_KEY=grouped-offer-* (authoritative)
BOOKING_CLASS=W
FARE_BASIS=EFTAOWW
```

## Four-stage customer total

| Stage | Fare family | Customer total (PKR) |
|---|---|---|
| Results (EF option) | EF | 23750 |
| Selected offer JSON | EF | 23750 |
| Passengers handoff | EF (option key retained) | 23750 |
| Review | EF / total sticky | 23750 |

```
AIRBLUE_FARE_STICKINESS=PASS
AIRBLUE_CUSTOMER_TOTAL_STICKINESS=PASS
NO_EV_FALLBACK=PASS
```

Server EF member `supplier_total_source` sticky at 23750 (authoritative supplier payable field name). Browser fare rows omit supplier payable fields.
