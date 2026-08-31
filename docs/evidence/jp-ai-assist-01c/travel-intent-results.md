# TravelIntent results

Schema (validated): intent, origin, destination, depart_date, return_date, adults, children, infants, cabin, airline, max_stops, budget, time_preference.

Pipeline:

1. `StructuredTravelIntentParser` (always)
2. Optional `LocalLlamaProvider` JSON (only if healthy) — fills gaps only
3. `TravelIntent::fromArray` rejects unknown tool names / invalid airports
4. `AiChatOrchestrator` chooses tools — never executes model-named functions

Malformed model JSON → structured fallback. No arbitrary function calling.
