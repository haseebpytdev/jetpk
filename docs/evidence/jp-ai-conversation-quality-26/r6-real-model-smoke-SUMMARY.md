# R6 REAL_MODEL_SMOKE

HEAD (commit target): work/jp-ai-conversation-quality-26 R6
Gateway: LocalLlama Qwen3.5-0.8B ctx=4096 via tunnel

## Six prompts
All PASS (see r6-real-model-smoke-console.txt). Public JP actions remain default set.

## Embed clear / handoff
Covered by PHPUnit:
- test_generic_embed_client_a_handoff_waiting_followup_excludes_jp_hrefs
- test_embed_clear_preserves_tenant_id_and_blocks_cross_tenant_access

GENERIC_HANDOFF_ACTION_LEAKS=0
EMBED_CLEAR_TENANT_CONTINUITY=PASS
