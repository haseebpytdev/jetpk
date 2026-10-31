# 04 — AirBook result

## Result

```
AIRBOOK_PROVIDER_CALL_COUNT=1
AIRBOOK_RESULT=SUCCESS
AIRBOOK_SUCCESS=YES
PNR_SAFE=f4b1c9f3d4ae:6
SUPPLIER_REFERENCE_SAFE=f4b1c9f3d4ae:6
BOOK_CREATE_CALLS=1
```

The successful AirBook used the canonical JetPakistan route:

`BookingProviderRouter → AirBlueBookingRouterService → SupplierBookingService → AirBlue adapter/service`

### Mid-phase pre-send defect

Before the successful transmission, AirBook was blocked by a false `protocol_version_mismatch`.

Root cause: `AirBlueBookingService::resolveProviderContext()` read only the legacy booking offer snapshot and ignored the authoritative validated/normalized meta snapshots.

The defect was fixed live and later canonicalized in PR #162:

`6c5fc26943d0283e5be2c5de5909d41ac60e4169`

Live patched file hash reported during UAT:

`e62e7fbdcf6b86778693f94089743944c7d97d931f0c433dfe9383ca3d385c15`

Rollback backup:

`/home/pkjetp/backups/airblue-airbook01-provider-context-20261031T215353Z/AirBlueBookingService.php`
