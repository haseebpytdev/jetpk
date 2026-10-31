# Ask JetPakistan smoke

| Check | Result |
|---|---|
| ASK_UI launcher | PASS (visible) |
| Panel lazy-load / open | PASS (panel_open=true) |
| Conversation endpoint deep prompts | PARTIAL — travel text entered; response body not clearly isolated from page chrome in this harness |
| SEMANTIC_PLANNER | enabled in env |
| SEMANTIC_COMPOSER | unset |
| MODEL_RUNTIME / AI Lab | Lab adapter unset → likely non-Lab path |
| TRAVEL_INTENT / GK / CURRENT_DATA / HANDOFF | not fully certified this pass |
| ASK_CURRENT_CLASSIFICATION | **PARTIAL** |
| ASK_CRITICAL_FAILURES | none observed (no 500 on launcher/open) |
