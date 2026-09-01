# Follow-up state

Preferred patch model: `depart_date_delta_days`, `max_stops`, `budget_text` merged into prior structured state.

```
FOLLOWUP_PATCH_MODEL=PASS
```

Application merge is implemented in `TravelIntentCanonicalizer` + extractor heuristics.

Bounded scores still imperfect (model omits deltas / Urdu date noise):

| Model | FOLLOWUP |
|-------|---------:|
| 1.7B post-audit | 50% |
| 4B | 80% |

Target ≥90% not met for either general model.
