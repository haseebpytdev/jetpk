# Migration review

Migration `2026_09_01_010000_create_ai_conversations_tables.php`:

- Creates `ai_conversations`, `ai_messages`, `ai_handoff_audits`  
- Additive; down drops only those tables  
- AI_MIGRATION_REVIEW=PASS (code review)  

AI_MIGRATION_REQUIRED=VERIFY_ON_PRODUCTION (ancestry includes migration; live schema not probed this phase).
