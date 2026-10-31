# JP-FLIGHT-SEARCH-LIVE-INCIDENT-01 — Root cause

## Classification

```text
FLIGHT_SEARCH_FIRST_BROKEN_BOUNDARY=/laravel/flights/results/search
FLIGHT_ROOT_CAUSE=Active retired SMTP SupplierConnection (id=5, provider string "smtp") enters public search connection set; logPublicSearchDiagnostics called $connection->provider->value on a non-enum string → ErrorException → HTTP 500 before supplier fan-out.
FLIGHT_FIX=Use SupplierConnection::providerKey() / string-safe isFlightSearchProvider() in FlightSearchService diagnostics and provider value reads; skip non-flight providers without fatal.
```

## Same-run repro (pre-fix)

| Field | Value |
|---|---|
| SEARCH_INPUT | LHE→KHI one_way 2026-11-20 1ADT economy |
| SEARCH_CLICK_TIME | 2026-10-31T12:22:26.114Z |
| REQUEST_URL | `/laravel/flights/results/search?...` |
| HTTP_STATUS | 500 |
| Body | `{"message":"Server Error"}` |

## Log (sanitized)

```text
production.ERROR: Attempt to read property "value" on string
at FlightSearchService.php:726
logPublicSearchDiagnostics ← searchWithMeta ← FlightController::runSearch ← resultsSearchData
```

## Live connection inventory (safe)

| id | provider raw | cast | status | is_active | name |
|---|---|---|---|---|---|
| 1 | sabre | enum | active | 1 | JetPK Binham Sabre |
| 2 | pia_ndc | enum | active | 1 | PIA JetPK |
| 4 | sabre | enum | inactive | 0 | Sabre Sandbox QA |
| 5 | smtp | **string** | active | 1 | SMTP JetPakistan LIVE |
| 6 | al_haider | enum | inactive | 1 | JPAK Group |
| 22 | airblue | enum | active | 1 | AirBlue Zapways TEST v2 |
| 23 | airblue | enum | active | 1 | AirBlue Zapways v3 TEST |

Query loads `is_active=true OR status=active`, so SMTP id=5 is included.

## Not the Oct-28 baggage regression

LIVE `FlightController` + `OfferBaggageResolver` hashes **matched** local; baggage sites already use resolver. Incident is diagnostics/provider-cast safety.

## Regression test

`tests/Feature/FlightSearch/FlightSearchRetiredProviderDiagnosticsSafetyTest.php` — 2 tests, 9 assertions, PASS.
