# JP-UX-POLISH-02B — Group results timing

| Metric | Value |
|---|---|
| URL | `https://jetpakistan.pk/groups/search?sector=ISB-SHJ` |
| GROUP_FILTER_READY_MS | 8137 |
| GROUP_RESULTS_FIRST_CARD_MS | 9755 |
| GROUP_RESULTS_RENDERED | YES |
| GROUP_RESULT_CARD_COUNT_VISIBLE | 7 |
| GROUPS_RESULT_CARDS_VISUAL_VERIFIED | PASS |
| GROUP_RESULTS_PERFORMANCE_BLOCKER | NO |

Notes:
- Bare `/groups/search` without query stays in empty-prompt state (“Choose an airline…”). Evidence used a real sector query.
- Cards loaded with route, datetime, fare, seats, meal/baggage, View details.
- Inventory for this sector was Fly Jinnah (9P). Separate Air Sial (PF) live card captured via airline filter.
- Recorded timings feed JP-NEXT-PERF-02 (observation only; no perf remediation in 02B).
