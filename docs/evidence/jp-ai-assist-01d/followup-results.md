# Follow-up results

Structured incremental state test (Lahore→Dubai → one day later → only direct → under 160k) and multilingual follow-ups.

| Metric | Value |
|--------|-------|
| FOLLOWUP_ACCURACY | 13.3% |
| STRUCTURED_FOLLOWUP_STATE | FAIL |

Model did not reliably merge prior structured state; often rewrote origin/destination incorrectly.

**Application must retain structured-state ownership; LLM must not be authoritative for incremental edits.**
