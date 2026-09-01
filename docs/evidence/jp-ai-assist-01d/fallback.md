# Fallback / load shedding / handoff

## No-LLM structured fallback

Exercised via local `StructuredTravelIntentParser` (AI service PHP not deployed on production app tree yet):

| Input (with prior LHE→DXB) | Result |
|----------------------------|--------|
| LHE DXB | route retained |
| 2 adults | adults=2 |
| direct | max_stops=0 |
| under 150k | budget=150000 |
| one day later | depart +1 day |
| human agent | intent=handoff |

```
NO_LLM_FLIGHT_SEARCH_FALLBACK=PASS
AI_LOAD_SHEDDING=PASS
AI_HUMAN_HANDOFF_SMOKE=PASS
AI_HUMAN_HANDOFF_REGRESSION=NO
```

Knowledge intents: payment deadline / cancellation / saved travelers → `knowledge` (guest booking phrasing still weak in parser regex — known 01C limitation).

## LLM bypass rate (simple benchmark heuristic)

```
LLM_BYPASS_RATE_ON_SIMPLE_BENCHMARK=63.0%
```

Supports routing principle: deterministic parser first.
