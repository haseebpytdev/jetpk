# Wrong-confidence gate

```
WRONG_CONFIDENT_SEARCHES=62
```

Expected: 0.

Examples:

- `Lahore to Dubai` → origin `LHR`
- Urdu لاہور→دبئی → `LHR`/`DWC` or non-IATA
- Invented `airline: JetPakistan` with confident `flight_search`

A model that invents executable search criteria is unsafe for Ask JetPakistan V1 without hard server validation + structured ownership (already intended architecture). Raw model quality is far below gate.
