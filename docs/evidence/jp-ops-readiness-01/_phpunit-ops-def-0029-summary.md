# OPS-DEF-0029 PHPUnit summary (sanitized)

## Targeted durability + legacy bridge + classifier run

- Suites: `SmtpDeliveryFailureClassifierTest`, `DeliverQueuedOperationalMailTest`, `RecipientDeliverySafetyServiceTest`
- Tests: 29
- Assertions: 98
- Passed: 29
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
12. legacy failed log (no meta, 550/5.1.1, single recipient) suppresses
13. legacy 450/4.7.1 does not suppress
14. legacy bare ambiguous 550 does not suppress
15. legacy permanent + skipped audit remains suppressed; later sent/delivered clears
16. legacy multi-recipient recipient_email never infers permanent reject
17. modern classified meta path remains green

## Production permanent-failure cardinality (read-only)

- PERMANENT_SCANNED=5
- PERMANENT_SINGLE_TO_COUNT=5
- PERMANENT_MULTI_TO_COUNT=0
- PERMANENT_WITH_CC_COUNT=0
- PERMANENT_WITH_BCC_COUNT=0
- HISTORICAL_PERMANENT_LOGS_TOTAL=5
- HISTORICAL_PERMANENT_LOGS_WITH_CLASSIFICATION_META=0
- HISTORICAL_PERMANENT_LOGS_WITHOUT_CLASSIFICATION_META=5
- LEGACY_5_1_1_FALLBACK=YES
- LEGACY_4XX_SUPPRESSION=NO
- LEGACY_AMBIGUOUS_550_SUPPRESSION=NO
- LEGACY_MULTI_RECIPIENT_INFERENCE=NO
- FIRST_TIME_MULTI_RECIPIENT_ATTRIBUTION=NOT_REQUIRED_FOR_CURRENT_0029 / DOCUMENTED_LIMITATION
- KNOWN_BAD_RECIPIENT_FILTERING_IN_MULTI_RECIPIENT_BUNDLE=PASS
- MUTATIONS=0

## Broader suites — BASE vs HEAD identity

BASE SHA: `27961bb210bcd52d0509a0e2c1149ff6fc915714`

Prior loop proved NEW_HEAD_ONLY_FAILURES=0 for the shared Communication/routing/operational failure set (layout/subject/routing/cancellation). This bridge only adds RecipientDeliverySafetyService legacy fallback + classifier `classifyMessage` extract — no notification semantics changes outside suppression evidence reading.

No absolute local filesystem paths in this summary.
