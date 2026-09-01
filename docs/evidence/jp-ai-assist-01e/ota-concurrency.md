# OTA concurrency

R7D API authority (PRE): Return p95≈4244, FareValidate p95≈2023, Fare→Traveler p95≈6954.

Canonical host HTML probes (`https://jetpakistan.pk`) while 4B generating:

| | PRE | ACTIVE |
|--|----:|-------:|
| Return results p95 (HTML) | 75 ms | 118 ms |
| PUBLIC_5XX | 0 | 0 |

```
AI_CORE_OTA_REGRESSION=NO
```

Full Playwright Fare Validate / Fare→Traveler matrix under AI was **not** re-executed in 01E (commercial/browser cost); HTML return/home/groups remained healthy. Marked as internal limitation.
