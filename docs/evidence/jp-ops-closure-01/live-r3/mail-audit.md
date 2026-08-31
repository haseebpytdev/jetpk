# R3 mail recipient audit (sanitized)

## R2 three non-QA targets

Routing used operational NotificationRecipientResolver (assigned/platform/support). Not a product routing defect.

```
INCORRECT_RECIPIENT_ROUTING=NO
QA_RECIPIENT_ISOLATION_GAP=YES
REAL_NON_QA_TARGETS_TOTAL=3
REAL_NON_QA_SENT_COUNT=0
REAL_NON_QA_FAILED_COUNT=ge_1
REAL_NON_QA_SKIPPED_COUNT=unknown_partial
```

Addresses not printed. Roles: internal ops / agency support / legacy support domain.

## R3

```
R3_REAL_NON_QA_EMAILS_TARGETED=4
```

Payment-rejection exercise resolved the same ops list; transport status failed. No further uncontrolled events sent after classification.

## External receipt

```
EMAIL_EXTERNAL_RECEIPT=EXTERNAL_OWNER_RECEIPT_PENDING
EVENT=payment_reminder|booking_request_received (R2 QA customer sent)
MASKED_RECIPIENT=*@example.invalid (QA) + owner inbox pending
```
