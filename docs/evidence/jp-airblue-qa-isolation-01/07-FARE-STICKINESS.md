# Fare stickiness — staff browser UAT

DATE: 2026-10-31  
ACTOR_ROLE: platform_admin (existing QA marker)  
ROUTE: LHE→KHI one-way 2026-11-21  
CONNECTION: 23 only

```
FAMILIES=EV,EF,EX
SELECTED=EF
FARE_OPTION_KEY=grouped-offer-* (authoritative grouped key)
BOOKING_CLASS=W
FARE_BASIS=EFTAOWW
```

## Four-stage customer total

| Stage | Fare family | Customer total (PKR) |
|---|---|---|
| Results (EF option) | EF | 23750 |
| Selected offer JSON | EF | 23750 |
| Passengers handoff | EF (option key retained) | 23750 |
| Review itinerary/pricing | EF / total | 23750 |

```
AIRBLUE_FARE_STICKINESS=PASS
AIRBLUE_CUSTOMER_TOTAL_STICKINESS=PASS
NO_EV_FALLBACK=PASS
```

Offer details HTTP=200 with `fare_family=EF` when using parent grouped `offer_id` + EF `fare_option_key`.
