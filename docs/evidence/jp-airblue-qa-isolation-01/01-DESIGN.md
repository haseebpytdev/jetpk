# Design

Parallel QA context — does **not** weaken normal public search.

```
Platform Admin / Staff
  → /staff/qa/airblue-search (bootstrap)
  → AirBlueQaSearchScope (fail-closed)
  → source_channel=qa_staff_airblue
  → FlightSearchService pins connection_id=23 only
  → FlightSearchResultStore + qa_* metadata
  → existing results/fare/passenger/review presenters
  → QaFlightSearchAccessGuard on every search_id transition
  → processReviewSubmit HARD STOP (no AirBook)
```

Key types:

- `App\Support\FlightSearch\AirBlueQaSearchScope`
- `App\Support\FlightSearch\QaStaffAirBlueContext`
- `App\Support\FlightSearch\QaFlightSearchAccessGuard`
- `App\Http\Controllers\Staff\QaStaffAirBlueSearchController`
- routes: `routes/qa-staff-airblue.php` (account.type:platform_admin,staff)

`--allow-live` remains operator acknowledgement only; not an environment proof.
