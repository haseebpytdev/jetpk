# JP-GRP-UI-01 — UI polish continuation (owner review)

## Branch
`phase/jp-grp-ui-01`

## SHAs
| Role | SHA |
|---|---|
| FINAL_ENGINEERING_SHA | `4d2f2e7fee82a1fc6b7e7f6fa897cf1b3423c1fd` |
| Prior polish pack | `e7ccb44a17d0387079a82be09c7f3c40561a846c` |
| DEPLOYED_RUNTIME_SHA | `4d2f2e7fee82a1fc6b7e7f6fa897cf1b3423c1fd` |
| PUBLIC_BUILD_ID | `1jOTXlcR6qXjGGiqBnnd-` |
| Backup | `jp-grp-ui-01-20260827T173923Z` |

## Objective
UI hardening: selected group preview, result cards, CMS media category cards, compact checkout + booking summary, modern selects, passport OCR assist, live deploy for owner review.

## Included
- Richer detail hero + booking summary sidebar
- Compact result cards (trip/category/meal/baggage)
- Media category cards on `/groups` (inventory authority + homepage-tile media enrichment)
- Compact passengers form + DocumentReader OCR assist
- Shared `FormControls.Select` (`data-jp-select=modern`) on group search + checkout
- Laravel GET `/groups/{id}/passengers` → Next HTML proxy (OLS-safe)
- CMS media baseline → QA → restore (residue cleared)

## Excluded
- Real Al-Haider booking / payment
- Gate activation (`GROUP_BOOKING` / `GROUP_RESERVATION` remain OFF)
- Admin deep redesign

## Tests
- `GroupInventoryCardPresenterTest` + `GroupSearchFacetsContractTest` — PASS (16)
- `LocalFakeSupplierCheckoutE2ETest` — PASS

## Evidence
`docs/evidence/jp-grp-ui-01/20260827T171800Z/`

## Acceptance (this loop)
GROUP_DETAIL_PREVIEW_POLISHED=PASS  
GROUP_RESULT_CARD_POLISH=PASS  
GROUPS_LANDING_MEDIA_CATEGORY_CARDS=PASS  
GROUPS_CATEGORY_MEDIA_CMS=PASS (homepage tiles; fallback when empty)  
GROUP_CHECKOUT_LAYOUT_POLISHED=PASS  
GROUP_BOOKING_SUMMARY_POLISHED=PASS  
GROUP_PASSENGER_FORM_COMPACT=PASS  
PASSPORT_UPLOAD_TRIGGER=PASS  
PASSPORT_AUTOFILL_WORKFLOW=PASS_OR_DOCUMENTED_LIMITATION (reuse DocumentReader; user confirms before submit)  
MODERN_SELECT_COMPONENT=PASS  
CMS_QA_RESIDUE=0  
LIVE_DEPLOY_DONE=YES  

## Hard stop
STOP for owner review. No commercial booking / payment / gate activation.

## Rollback
Restore backup `jp-grp-ui-01-20260827T173923Z` via progressive rollback package under `/home/pkjetp/releases/`.
