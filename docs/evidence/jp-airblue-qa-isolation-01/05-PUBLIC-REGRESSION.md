# Normal public regression — post-deploy smoke

DATE: 2026-10-31

```
UP=200
PUBLIC_SEARCH=200
RESULTS_DATA=200
OFFERS=12
PROVIDERS=sabre
AIRBLUE_OFFERS=0
QA_META_LEAK=False
SUPPLIER_TOTAL_LEAK=False
NORMAL_PUBLIC_AIRBLUE_TEST_EXPOSURE=0
QA_ANON_BOOTSTRAP=302 (login redirect)
```

Allowlist unchanged (airblue not added). Config `ota.php` not overwritten on prod (root-owned); QA connection defaults remain 23 in code.
