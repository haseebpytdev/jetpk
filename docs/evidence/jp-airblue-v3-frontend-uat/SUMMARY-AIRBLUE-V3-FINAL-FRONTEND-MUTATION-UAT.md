# SUMMARY — AirBlue V3 Final Frontend Mutation UAT

**STATUS: BLOCKED** (do not rewrite as PASS)

| Field | Value |
|---|---|
| Date | 2026-10-28 |
| Production | https://jetpakistan.pk |
| Runtime SHA (deployed) | `93882cbc722901b637e2ebd655c26293ce0e9ff4` |
| UAT seed | `.recovery/pr135-final-uat/_final-search-seed.php` |
| Search id hash | `cd834678ea2a` |

## Outcome

Stopped before AirBook. Live ephemeral TEST seed produced AirBlue offers with structured baggage arrays; `/flights/results/data` returned HTTP 500.

## Blocker

```
BLOCKER_CONFIRMED=
FlightController buildFilterMeta array baggage coercion

DIAG_MSG=Array to string conversion
DIAG_FILE=/home/pkjetp/jetpk_app/app/Http/Controllers/Frontend/FlightController.php:2135
```

Unsafe pattern:

```php
$bag = strtolower((string) ($offer['baggage'] ?? ''));
```

AirBlue normalized shape:

```php
'baggage' => [
  'checked' => null,
  'cabin' => null,
  'summary' => null,
]
```

## Provider calls in blocked run

| Call | Count |
|---|---|
| AIRLOWFARESEARCH_CALLS_IN_BLOCKED_RUN | 1 |
| AIRBOOK | 0 |
| SEATMAP / TYPE5 | 0 |
| AIRDEMANDTICKET | 0 |
| CANCEL / REFUND | 0 |
| MUTATIONS_IN_BLOCKED_RUN | 0 |

## Closure track

Fix branch (code only, no deploy in that loop):

`fix/flight-results-structured-baggage-filter-safety`

Evidence:

`SUMMARY-AIRBLUE-V3-BAGGAGE-FILTER-BLOCKER-CLOSURE.md`

**NEXT:** review → merge → scoped deploy → resume this mutation UAT from a fresh seed.
