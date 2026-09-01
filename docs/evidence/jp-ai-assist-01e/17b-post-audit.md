# 1.7B post-audit (after harness fixes)

Same non-thinking template; shortened prompt; app canonicalization; ~50 controlled turns.

| Metric | Value |
|--------|------:|
| 17B_POST_AUDIT_INTENT | 82.0 |
| 17B_POST_AUDIT_CRITICAL_FIELDS | 91.0 |
| 17B_POST_AUDIT_ENGLISH | 86.7 |
| 17B_POST_AUDIT_ROMAN_URDU | 75.0 |
| 17B_POST_AUDIT_URDU | 60.0 |
| 17B_POST_AUDIT_FOLLOWUP | 50.0 |
| 17B_POST_AUDIT_WRONG_CONFIDENT | 4 |
| VALID_JSON_RATE | 100% |
| RESPONSE_P50/P95 | 22631 / 29577 ms |

Critical fields crossed 90%, but wrong-confident≠0, English/RU/Urdu/follow-up below targets, latency unusable.

```
LARGER_MODEL_TEST_NEEDED=YES
INFERENCE_HARNESS_CERTIFIED=PASS
```
