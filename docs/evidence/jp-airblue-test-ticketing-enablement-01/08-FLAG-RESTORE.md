# Restore ticketing_enabled OFF

Restored exact original key value `false` (key was originally present).

| Check | Result |
|-------|--------|
| TICKETING_FLAG_RESTORED | YES |
| CREDENTIALS_NON_TICKETING_HASH_AFTER_RESTORE | equals BEFORE |
| CONN23_RESTORE_INTEGRITY | PASS |
| TICKETING_ENABLED_AFTER | NO |

## Post-restore dry-run fail-closed

```
php artisan airblue:qa-test-ticketing 57 --actor=asifhaseeb --dry-run
```

- qa_scope_allowed=false
- qa_scope_reason=ticketing_feature_disabled
- POST_RESTORE_SCOPE_BLOCK=PASS

Emergency note: during an intermediate ADT dry script error, flag was briefly left ON and immediately restored via emergency restore before finishing gates. Final state verified OFF.
