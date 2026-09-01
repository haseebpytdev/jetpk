# Model decision

```
INFERENCE_HARNESS_CERTIFIED=PASS
LARGER_MODEL_TEST_NEEDED=YES
NEXT_MODEL_RESOURCE_GATE=PASS
NEXT_MODEL quality=FAIL
AI_MODEL_CANDIDATE_APPROVED=NO
AI_MODEL_DECISION=STOP_GENERAL_MODEL_ESCALATION_AND_REDESIGN_LANGUAGE_PIPELINE
```

Do **not** jump to 7B/8B.

Next engineering direction:

1. Deterministic parser + canonicalizer first (already strengthened)
2. Optional dedicated Urdu/translation local layer later
3. Patch-first follow-up without LLM for common deltas
4. Templated responses; LLM only for hard NL
5. Hard wrong-confident gate = 0 before any public activation
