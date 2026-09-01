# Benchmark method

1. Temporary `llama-server` on `127.0.0.1:3922`, concurrency=1, threads=2, ctx=2048, nice=10.
2. Production system prompt `ai-assistant/prompts/travel-intent-system.txt`.
3. Bounded Roman-Urdu normalization before LLM (sy/se, chahiye variants, etc.) — not corpus-specific phrase hacks.
4. User payload includes `today=2026-09-01` and optional `prior` structured state.
5. `enable_thinking=false` (Qwen3 otherwise fills reasoning and empties content).
6. Corpus ≥100 turns: EN 25, Roman Urdu 30, Urdu 20, mixed 10, follow-up sequences (15 steps).
7. Field-by-field scoring vs expected TravelIntent; wrong-confident = plausible but wrong route/date while claiming `flight_search`.
8. Structured no-LLM fallback exercised locally via `StructuredTravelIntentParser` (AI PHP not yet on production tree).
9. Load shedding: client timeout + unavailable port → fallback path.
10. Tear down all temp listeners; model file retained outside Git.

**No external AI APIs.** Inference local only.
