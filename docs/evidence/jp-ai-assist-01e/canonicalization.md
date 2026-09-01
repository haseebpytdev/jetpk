# Canonicalization (app-owned)

```
LOCATION_CANONICALIZATION_APP_OWNED=YES
AIRLINE_CANONICALIZATION_APP_OWNED=YES
DATE_CANONICALIZATION_APP_OWNED=YES
BUDGET_NORMALIZER=PASS
```

Implemented in `TravelIntentCanonicalizer` + hardened `TravelIntent`:

- City/name → IATA via allowlist (reject unknown 3-letter codes like KHC)
- Airline name → code (Emirates→EK); reject invented JetPakistan
- Budget dialects: 150k / 150 hazar / 1.5 lakh / PKR 150,000
- Relative dates: tomorrow/kal/next Friday/aglay jumay / day delta patches

Prompt now asks for `origin_text` / `destination_text` / `airline_name` / `*_date_text`.
