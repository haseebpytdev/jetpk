# Exactly one live command

```
php artisan airblue:qa-test-ticketing 57 --actor=asifhaseeb --temporary-enable-ticketing --allow-live
```

Sanitized output:

```
pre_enable_scope_allowed=true
pre_enable_scope_reason=ok
temporary_ticketing_enabled=true
qa_scope_allowed=true
qa_scope_reason=ok
seat_gate_satisfied=true
canonical_eligible=false
lifecycle_path=ticketing_service
success=false
status=failed
error_code=provider_error
outcome=DEFINITIVE_PROVIDER_REJECTION
provider_calls=1
ticket_count=0
ticketing_flag_restored=true
COMMAND_EXIT_CODE=1
```

No second command invocation. Mutation allowance consumed.
