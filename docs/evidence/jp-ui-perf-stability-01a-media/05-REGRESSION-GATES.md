# Regression gates

```text
FLIGHT_RESULTS_CODE_CHANGED=NO
CHECKOUT_CODE_CHANGED=NO
SUPPLIER_CODE_CHANGED=NO
EMAIL_CODE_CHANGED=NO
AUTH_CODE_CHANGED=NO
AIRBLUE_OR_SUPPLIER_PROVIDER_CALLS=0
GLOBAL_IMAGESLOT_BEHAVIOR_CHANGED=NO
PRODUCTION_DEPLOYED=NO
PRODUCTION_BACKFILL_RUN=NO
```

Protected:

- AirBlue certification / mutation safety untouched
- ImageSlot `/storage/` bypass retained
- Original CMS asset URLs remain published
- Hero Blade LCP path continues via `hero_lcp`
