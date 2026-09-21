# Reconciliation decision table

| CAPABILITY | MAIN (`cf8fd79d`) | LIVE | PRE_RECOVERY | ACTION |
|------------|-------------------|------|--------------|--------|
| AI runtime providers | YES | YES (MD5 match) | YES | NO_ACTION |
| Deployment guards (jetpk scripts) | YES | PARTIAL (stamp `78dadc7b`) | YES | LIVE_DEPLOY_REQUIRED after merge |
| verify-ai-runtime script | **NO (git)** | YES | YES | RESTORE_TO_MAIN |
| Customer Queries admin | **NO** | YES (SCP/hotfix) | YES | RESTORE_TO_MAIN |
| AskJetPakistanChat lead UI | PARTIAL | UNKNOWN | YES | RESTORE_TO_MAIN |
| QA matrix harness | **NO** | **NO** | YES | RESTORE_TO_MAIN (evidence only) |
| AI audience mode | internal_canary capable | **public** (effective) | internal_canary | **MANUAL_REVIEW** — must set internal_canary before matrix |
| Recovery checkpoint scripts | N/A | Behind main | `78dadc7b` | SUPERSEDED by main |

## Production classification

**MIXED_RUNTIME**

Evidence:
- Recovery stamp: `78dadc7b330ca24a6183241458dd8d1f6aa0d454`
- `AiServiceProvider.php` + `bootstrap/providers.php` MD5 **match main** `cf8fd79d`
- `CustomerQueryController.php` present on live, absent from main until closure restore
- Gateway `127.0.0.1:8765` health **200**; Ollama **200**; Laravel `/up` **200**; public site **200**
