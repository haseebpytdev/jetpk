# JP-AIRBLUE-QA-ISOLATION-01 — SUMMARY

```
START_MAIN_SHA=706b8d79adcbf9e8cd4ff2d3b5848ff1be13176e
BRANCH=fix/jp-airblue-qa-isolation-01
QA_ISOLATION_IMPLEMENTED=YES
QA_ALLOWED_ROLES=platform_admin,staff
QA_CONNECTION_ID=23
QA_ENVIRONMENT=sandbox
QA_HOST=otatest4.zapways.com
QA_PROTOCOL=3.0
NORMAL_PUBLIC_AIRBLUE_EXPOSURE=0 (by design; live re-verify post-deploy)
SECURITY_TESTS=24 PASS
BOOK_CREATE_CALLS=0
AIRBLUE_PREBOOK_GATE=STOP
```

## SERVICE_FEE P3 (audit only)

`PublicCustomerPricing` rejects `service_fee` from public customer `final_total` (not supplier cost; OTA fee bucket). Top-level public `service_fee` field may still appear in some JSON presenters — **not** P0 supplier-cost leak. Classification:

```
SERVICE_FEE_IS_CUSTOMER_CHARGE=AMBIGUOUS_LEGACY (rejected from public final_total)
SERVICE_FEE_IS_SUPPLIER_COST=NO
SERVICE_FEE_PUBLIC_DISPLAY_REQUIRED=NO
SERVICE_FEE_CLASSIFICATION=P3_RESIDUAL_FIELD_NOT_SUPPLIER_COST
```

## IatiOfferFallbackDetailsTest residuals

Prior post-P0 noise: stale Blade assertion + airlines-table env — classify **STALE_TEST / TEST_ENVIRONMENT_DEFECT**, not customer P0. Deferred to separate test-maintenance commit.

## Hard stop

Do **not** execute AirBook / ticket / cancel / void / refund / payment in this phase.
