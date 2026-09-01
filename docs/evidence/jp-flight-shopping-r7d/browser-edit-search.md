# Browser Edit Search (exact path)

Primary proof used the live production UI (not deep-link construction).

## Flow

1. Home One Way ISB→DXB, Departure via native date input `2026-09-18`
2. Search Flights → Results (`search_id` A)
3. Edit Search
4. Trip type → **Return** (`data-testid=trip-type-trigger` / menuitem)
5. `date-range-trigger` → `date-range-panel` → click `button[data-date="2026-09-25"]`
6. Search Flights → Results (`search_id` B ≠ A)
7. Paired view when selector shown

## Measured (live)

| Gate | Result |
|---|---|
| EDIT_SEARCH_BROWSER_RETURN_PICKER | PASS (`Return`, label `Fri 18 Sept → Fri 25 Sept`) |
| EDIT_SEARCH_BROWSER_SUBMIT | PASS (`trip_type=round_trip`, `return_date=2026-09-25`) |
| EDIT_SEARCH_BROWSER_SEARCH_ID | PASS (new `search_id`) |
| EDIT_SEARCH_BROWSER_PAIRED_RESULT | PASS (12 Book Now cards) |
| EDIT_SEARCH_BROWSER_INFINITE_LOADING | 0 |

Sample Return URL retained `view=pair` with Copy/WhatsApp share actions present (12/12).
