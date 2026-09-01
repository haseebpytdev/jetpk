# JP-AI-ASSIST-01F — SUMMARY

## Phase name
JP-AI-ASSIST-01F — Hybrid local language pipeline

## Branch
`phase/jp-flight-perf-01`

## Objective
Certify production-quality deterministic/hybrid Ask JetPakistan language core without general LLM escalation.

## Included
Hybrid pipeline services, corpus ≥300, unit/feature tests, evidence pack, orchestrator hybrid wiring.

## Excluded
7B/8B models, external AI APIs, public activation, live booking/PNR/payment, push.

## Root causes addressed (from 01E)
General chat models unsuitable; need model-free domain pipeline for search authority.

## Files changed
See git commit (app/Services/Ai/Hybrid/*, TravelIntent*, StructuredTravelIntentParser, AiChatOrchestrator, config/ota.php, knowledge md, tests, evidence).

## Tests
`phpunit tests/Unit/Ai tests/Feature/Ai/PublicAiAssistantTest.php` — PASS

## Final status
HYBRID_PIPELINE_CANDIDATE_APPROVED=YES  
SAFE_TO_PUSH=NO  
AI_01F_CERTIFICATION=PASS
