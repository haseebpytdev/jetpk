# Security / stickiness tests — pre-AirBook closure

DATE: 2026-10-31
SHA_UNDER_TEST: local tree aligned to `25e2abb7` presenter + PR #160 tests

Command:

```
php vendor/phpunit/phpunit/phpunit tests/Unit/FlightOfferDisplayPresenterTest.php tests/Feature/Phase2PublicFlightSearchSecurityTest.php tests/Feature/FlightSearch/QaStaffAirBlueAccessGuardTest.php tests/Feature/FlightSearch/QaStaffAirBlueHttpIsolationTest.php tests/Feature/AirBlueGroupedFareBookingTotalStickinessTest.php
```

{"tool":"phpunit","result":"passed","tests":82,"passed":82,"assertions":481,"duration_ms":255148}

`SECURITY_TESTS=PASS`
`STICKINESS_TESTS=PASS`
