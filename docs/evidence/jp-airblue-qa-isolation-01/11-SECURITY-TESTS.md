# Security tests

Commands:

```
php vendor/phpunit/phpunit/phpunit tests/Unit/Support/FlightSearch/AirBlueQaSearchScopeTest.php
php vendor/phpunit/phpunit/phpunit tests/Feature/FlightSearch/QaStaffAirBlueAccessGuardTest.php tests/Feature/FlightSearch/QaStaffAirBlueHttpIsolationTest.php
```

Results (2026-10-31):

```
AirBlueQaSearchScopeTest: 8 passed
QaStaffAirBlueAccessGuardTest + QaStaffAirBlueHttpIsolationTest: 16 passed
TOTAL=24 PASS
```

Coverage includes: pin reject, provider/env/host/protocol reject, module-off reject, anon/customer/agent deny bootstrap, anon deny results/offer, short-ref 410, allowlist excludes airblue, cache channel ≠ public_guest.
