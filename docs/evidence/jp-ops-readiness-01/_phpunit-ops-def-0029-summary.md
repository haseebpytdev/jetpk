# OPS-DEF-0029 PHPUnit summary (sanitized)

## Targeted durability + classifier run

- Suites: `SmtpDeliveryFailureClassifierTest`, `DeliverQueuedOperationalMailTest`, `RecipientDeliverySafetyServiceTest`
- Tests: 24
- Assertions: 86
- Passed: 24
- Failed: 0

Mandatory assertions covered:

1. permanent reject suppresses first later send
2. suppression audit skipped row does NOT clear permanent status
3. second scheduled/later send remains suppressed
4. later real sent outcome clears suppression
5. later real delivered outcome clears suppression
6. 450 transient remains unsuppressed
7. unknown remains unsuppressed
8. corrected new exact address remains allowed
9. previously-known bad recipient filtered from multi-recipient bundle; valid continues
10. notifications-transactional queue unchanged
11. successful transient retry clears stale failure meta

## Production permanent-failure cardinality (read-only failed_jobs)

- PERMANENT_SCANNED=5
- PERMANENT_SINGLE_TO_COUNT=5
- PERMANENT_MULTI_TO_COUNT=0
- PERMANENT_WITH_CC_COUNT=0
- PERMANENT_WITH_BCC_COUNT=0
- FIRST_TIME_MULTI_RECIPIENT_ATTRIBUTION=NOT_REQUIRED_FOR_CURRENT_0029 / DOCUMENTED_LIMITATION
- KNOWN_BAD_RECIPIENT_FILTERING_IN_MULTI_RECIPIENT_BUNDLE=PASS
- MUTATIONS=0

## Broader suites — BASE vs HEAD identity

BASE SHA: `27961bb210bcd52d0509a0e2c1149ff6fc915714`

### BASE_FAILING_TESTS (identical set)

Communication (8):

- BookingCancellationSyncMailLogTest::test_sync_queue_successful_cancellation_mail_marks_single_log_sent
- BookingCancellationSyncMailLogTest::test_database_queue_keeps_customer_cancellation_log_queued_until_job_execution
- BookingCancellationSyncMailLogTest::test_duplicate_cancellation_call_creates_no_extra_row_or_send
- CustomerFacingMailableModernLayoutTest::booking_request_received_mail_renders_modern_layout_with_branding_and_reference
- I8EmailModernizationTest::admin_new_customer_signup_mail_preserves_admin_recipient_and_safe_content
- OtaOperationalNotificationModernLayoutTest::operational_notification_sends_modern_layout_html
- OtaOperationalNotificationModernLayoutTest::agency_message_template_subject_and_body_are_used_when_present
- OtaOperationalNotificationModernLayoutTest::safe_default_subject_and_body_used_when_template_missing

NotificationRecipientRouting (1):

- test_support_email_fallback_when_no_platform_admin_attached

NotificationOperationalCoverage (4: 2 fail + 2 error):

- test_fare_update_requires_acceptance_logs_operational_notification
- test_payment_verified_routes_customer_and_admin_without_duplicate
- test_manual_review_notification_is_admin_staff_only_payload
- test_communication_log_error_message_does_not_contain_smtp_password

### HEAD_FAILING_TESTS

Same 13 methods as BASE (layout/subject/routing/cancellation; messages match).

### Identity gate

- IDENTICAL_FAILURES=13
- NEW_HEAD_ONLY_FAILURES=0

HEAD Communication suite total tests higher due to OPS-DEF-0029 added passing tests; failure method set unchanged vs BASE.

No absolute local filesystem paths in this summary.
