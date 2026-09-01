# Field accuracy (raw local LLM Qwen3-1.7B Q8_0)

Source: `/tmp/jp-ai-01d/quality-summary.json`

| Field | Accuracy % |
|-------|------------|
| INTENT | 87.0 |
| ORIGIN | 24.1 |
| DESTINATION | 50.6 |
| DEPART_DATE | 84.5 |
| RETURN_DATE | 75.0 |
| ADULT | 93.5 |
| CHILD | 100.0 |
| INFANT | 75.0 |
| AIRLINE | 0.0 |
| MAX_STOPS | 89.5 |
| BUDGET | 25.0 |
| TIME_PREFERENCE | 44.4 |
| FOLLOWUP | 13.3 |

## Aggregates

| Metric | Value |
|--------|-------|
| AI_17B_INTENT_ACCURACY | 87.0 |
| AI_17B_CRITICAL_FIELD_ACCURACY | 57.4 |
| AI_17B_ENGLISH_SCORE | 15.4 |
| AI_17B_ROMAN_URDU_SCORE | 34.5 |
| AI_17B_URDU_SCORE | 0.0 |
| AI_17B_FOLLOWUP_SCORE | 13.3 |

## Failure modes (observed)

- Lahore → **LHR** (Heathrow) confusion
- City names / invalid IATA (`KHC`, `ISL`, `DWC`, `IATA:IAA`)
- Invented airline **JetPakistan**
- Airline names not IATA (`Emirates` vs `EK`)
- Urdu script routes systematically wrong
- High wrong-confident search rate

```
AI_17B_QUALITY_GATE=FAIL
```
