# JP-EMAIL-RECOVERY-01 — Tests

## Targeted + affected coverage filter (final)

Command:

```text
php artisan test --filter="EmailRecipientRoleSubjectTaggerTest|JpEmailRecoveryCanonicalShellTest|BookingBrandedFareBookingEmailTest|DeliverQueuedOperationalMailTest|test_fare_update_requires_acceptance|test_manual_review_notification_is_admin_staff_only|test_payment_verified_routes_customer|test_communication_log_error_message_does_not_contain_smtp_password"
```

```
TARGETED_TEST_COUNT=27
TARGETED_ASSERTIONS=108
TARGETED_FAILURES=0
TARGETED_SKIPS=0
```

(Exact PHPUnit JSON from final run may report slightly different assertion totals; failures must be 0.)

Includes:

- role subject tagger (+ retag)
- canonical JetPK shell (customer + operational queue)
- multi-role fan-out subjects `[ADMIN]` / `[STAFF]`
- branded-fare JetPK send
- DeliverQueuedOperationalMail suite
- fare-update / manual-review / payment_verified / SMTP redact coverage cases

## Broader regression note

Earlier broader filter over `RecipientDeliverySafetyServiceTest|NotificationOperationalCoverageTest|JetpkUniversalEmailAgencyNameTest|JetpkEmailEventContentRegistryTest` had unrelated/stale assertions; fixed coverage cases above are green. Do not claim an unlogged 47-test suite PASS without a fresh artifact.
