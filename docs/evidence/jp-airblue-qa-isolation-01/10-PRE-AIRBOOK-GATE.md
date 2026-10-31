# Pre-AirBook gate

```
AIRBLUE_PREBOOK_GATE=STOP
CONNECTION_ID=23
ENVIRONMENT=sandbox
PROTOCOL=v3
HOST_TEST=YES
SERVICE_TARGET_TEST=YES
QA_SCOPE=YES
AUTHORIZED_ACTOR=YES
EXPECTED_OPERATION=AirBook
EXPECTED_PROVIDER_EFFECT=NONE (blocked)
EXPECTED_FINANCIAL_EFFECT=NONE
EXPECTED_EMAIL_EFFECT=NONE
BOOK_CREATE_CALLS=0
```

CLI note: `--allow-live` = operator acknowledgement only. Future AirBook cert must also pass `AirBlueQaSearchScope` sandbox checks. Optional `--require-sandbox` deferred (not required this phase).
