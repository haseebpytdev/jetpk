# Side-effect suppression

| Metric | Before | After | Verdict |
|--------|--------|-------|---------|
| Communication rows (booking 57) | 9 | 9 | no new (PASS) |
| Commission rows | n/a/-1 | n/a/-1 | PASS |
| Ledger rows | 0 | 0 | PASS |

QA_COMMUNICATION_SUPPRESSION=PASS (delta 0)  
QA_COMMISSION_SUPPRESSION=PASS  
QA_LEDGER_SUPPRESSION=PASS  

No customer ticket-issued / failure spam attributed to this controlled QA rejection path beyond existing baseline rows.
