# 01D quality analysis (preserved)

| Gate | Result |
|------|--------|
| Model | Qwen3-1.7B-Q8_0 |
| Resource | PASS |
| Quality | FAIL |
| Approved | NO |

Observed defects (01D raw scoring required model IATA/airline codes):

- ORIGIN 24% / DESTINATION 50% / AIRLINE 0%
- WRONG_CONFIDENT=62 (e.g. Lahore→LHR)
- Invented airline `JetPakistan`
- Follow-up 13.3%
- Latency p50/p95 ~21s/34s

**Do not reinterpret as PASS.** Root-cause work in 01E shows much of this was harness/architecture (prompt + scorer + missing app canonicalization), not only parameter count.
