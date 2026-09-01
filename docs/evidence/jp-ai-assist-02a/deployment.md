# Deployment

AI_02A_ENGINEERING_SHA=`896f1e8acf5083ac8292b5287e1fc5bcb051e260`  

Production canary deploy **not executed** in this run:

- NO PUSH (commit exists only on local branch)  
- Protected Contabo deploy wrappers require staged archive + `jetpk-production-run`  
- Public AI must remain OFF on first activate; then set `OTA_AI_ASSISTANT_MODE=internal_canary`

PUBLIC_AI_DURING_INITIAL_DEPLOY=N/A (no deploy)
