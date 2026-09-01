# Raw output classification (bounded post-audit, n=50)

After prompt + app canonicalization (not 01D raw IATA scoring):

| Class | Count |
|-------|------:|
| OK | 31 |
| MODEL_WRONG | 19 |
| JSON_EXTRACTION_ERROR | 0 |
| SCHEMA_PARSE_ERROR | 0 |
| NORMALIZATION_ERROR | 0 (app-owned) |
| SCORER_ERROR | 0 (scorer aligned to app output) |
| CONTEXT_ERROR | present inside MODEL_WRONG follow-ups |

```
MODEL_WRONG_COUNT=19
JSON_EXTRACTION_ERROR_COUNT=0
SCHEMA_PARSE_ERROR_COUNT=0
NORMALIZATION_ERROR_COUNT=0
SCORER_ERROR_COUNT=0
CONTEXT_ERROR_COUNT≈5 (follow-up day-delta / Urdu date)
```

01D inflated MODEL_WRONG via asking the LLM for IATA and accepting invented codes in `TravelIntent::normalizeAirport`.
