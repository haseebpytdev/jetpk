# JP-EMAIL-RECOVERY-01 — Tests

## Targeted + affected coverage filter (final)

Command:

```text
php artisan test --filter="EmailRecipientRoleSubjectTaggerTest|JpEmailRecoveryCanonicalShellTest|JetpkEmailPlainTextRendererTest|BookingBrandedFareBookingEmailTest|DeliverQueuedOperationalMailTest|test_fare_update_requires_acceptance|test_manual_review_notification_is_admin_staff_only|test_payment_verified_routes_customer|test_communication_log_error_message_does_not_contain_smtp_password"
```

Exact PHPUnit JSON (authoritative final run):

```text
{"tool":"phpunit","result":"passed","tests":31,"passed":31,"assertions":152,"duration_ms":25974}
```

```
FINAL_TARGETED_TEST_COUNT=31
FINAL_TARGETED_ASSERTIONS=152
FINAL_TARGETED_FAILURES=0
FINAL_TARGETED_SKIPS=0
TARGETED_TEST_COUNT=31
TARGETED_ASSERTIONS=152
TARGETED_FAILURES=0
TARGETED_SKIPS=0
```

Includes:

- role subject tagger (+ retag)
- shared `JetpkEmailPlainTextRenderer` (real JetPK `layouts.base` CSS leak gate)
- canonical JetPK shell (customer + operational queue)
- customer canonical render fail-closed (no `BookingUniversalNotification`)
- sender identity from global `mail.from` config
- same-address multi-role first-resolved-role-wins policy
- multi-role fan-out subjects `[ADMIN]` / `[STAFF]`
- branded-fare JetPK send
- DeliverQueuedOperationalMail suite
- fare-update / manual-review / payment_verified / SMTP redact coverage cases

## Broader regression (final)

Command:

```text
php artisan test --filter="EmailRecipientRoleSubjectTaggerTest|JpEmailRecoveryCanonicalShellTest|JetpkEmailPlainTextRendererTest|BookingBrandedFareBookingEmailTest|DeliverQueuedOperationalMailTest|NotificationOperationalCoverageTest|RecipientDeliverySafetyServiceTest|JetpkUniversalEmailAgencyNameTest|JetpkEmailEventContentRegistryTest|BookingCommunicationService"
```

Exact PHPUnit JSON:

```text
{"tool":"phpunit","result":"passed","tests":74,"passed":74,"assertions":616,"duration_ms":77588}
```

```
BROADER_TEST_COUNT=74
BROADER_ASSERTIONS=616
BROADER_FAILURES=0
BROADER_SKIPS=0
```
