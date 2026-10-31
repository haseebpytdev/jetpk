# 13 — Provider context leak closure (PR #160)

DATE: 2026-10-31  
CANONICAL_MAIN: `25e2abb7e5f3d026cd901bb7f87811d1899eacad`  
PR: #160 merged (`fix(public): hide internal provider context from fare options`)

## Source

`FlightOfferDisplayPresenter::mapFareFamilyOptionRow()` no longer copies `provider_context` into customer-facing fare-option rows. Comment retained that authoritative supplier context stays server-side on grouped members.

## Selective LIVE deploy

| Field | Value |
|---|---|
| File | `app/Support/FlightSearch/FlightOfferDisplayPresenter.php` |
| BACKUP_PATH | `/home/pkjetp/backups/airblue-pr160-presenter-pre-20261031T190410Z` |
| PREDEPLOY_FILE_HASH | `0b02b2855dda2c640b393f4fd612d62df4f41e38d1b30bfd2d14b86e02ea2844` |
| CANONICAL_FILE_HASH | `3a8e9f6a1601775406f8d6fd4a45454df6af8d3ef466ce3f6eaddd91164e0b72` |
| POSTDEPLOY_FILE_HASH | `3a8e9f6a1601775406f8d6fd4a45454df6af8d3ef466ce3f6eaddd91164e0b72` |
| RUNTIME_FILE_PARITY | PASS |
| LIVE_MAP_ROW_LEAK | NO |
| Cache | `php artisan optimize:clear` |

## LIVE browser scan (QA results/data fare rows)

```
fare_option_rows_scanned=12
PUBLIC_FARE_OPTION_PROVIDER_CONTEXT=ABSENT
PUBLIC_PRICED_ITINERARIES=ABSENT
PUBLIC_INTERNAL_CONNECTION_ID=ABSENT
PUBLIC_CORRELATION_ID=ABSENT
supplier_total / markup / pricing_components = ABSENT on fare option rows
selected_offer commercial_leak_hits=[]
```

## Server authority (booleans only)

```
STORE_HIT=YES
SERVER_PROVIDER_CONTEXT_PRESENT=YES
SERVER_PRICED_ITINERARY_CONTEXT_PRESENT=YES
SERVER_EF_MEMBER_PROVIDER_CONTEXT=YES
SERVER_AIRBOOK_AUTHORITY_PRESERVED=PASS
```

PR #160 removes browser exposure only; internal EF members retain provider context for AirBook authority.
