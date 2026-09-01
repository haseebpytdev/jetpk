# Kill switch

## Pre-public

Transitions under `jetpk-production-run` (`tmp/jetpk-jp-ai-assist-02b-activate2.sh`):

1. INTERNAL_CANARY → OFF (health `assistant_mode=off`) → AI_MODE_OFF_RESTORE=PASS  
2. OFF → INTERNAL_CANARY → AI_MODE_INTERNAL_RESTORE=PASS  

AI_KILL_SWITCH=**PASS**

Mode application: `.env` rewrite + `artisan optimize:clear` + `config:cache` + OLS restart + `pkill` lsphp (HTTP workers otherwise served stale config).

## Post-public

`tmp/jetpk-jp-ai-assist-02b-post-killswitch.sh`:

PUBLIC → INTERNAL_CANARY → OFF → **PUBLIC restored**

POST_PUBLIC_KILL_SWITCH=**PASS**

Documented safe procedures remain env-mode only (no source deploy).
