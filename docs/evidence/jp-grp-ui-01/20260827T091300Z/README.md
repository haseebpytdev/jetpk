# JP-GRP-UI-01 live evidence

**URL:** https://jetpakistan.pk  
**Timestamp:** 2026-08-27T09:13:00Z (capture window through ~10:45Z)  
**FINAL_ENGINEERING_SHA:** `636584a395cbc93221d7f005fcde7311915f973e`  
**Prior UI engineering SHA:** `717691fb1c8c0661f228024be12bcfbfb9742f28`  
**PUBLIC_BUILD_ID:** `pDR6hMs_p8fGZBjYq4WeK`  
**BACKUP_ID (UI deploy):** `jp-grp-ui-01-20260827T085240Z`  
**BACKUP_ID (route fix):** `jp-grp-ui-01-20260827T102732Z`  
**LIVE_SOURCE_DRIFT:** 0  
**OLS_HASH:** `612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c`

## Browser assertions (live)

| Assertion | Result |
|---|---|
| Homepage tab label Groups | PASS |
| Flights ↔ Groups no full reload | PASS (`noReload=true`) |
| Shared group search component | PASS |
| Dynamic category cards | PASS (live count=2: UAE, KSA ONEWAY) |
| Airline inventory options | PASS (5 airlines + Any) |
| Sector inventory options | PASS (9 sectors + Any) |
| Groups page shared search + results | PASS (sample sector search returned cards) |
| Mobile layouts captured | PASS |

## Filter matrix (live inventory-backed)

- Airlines: AIR ARABIA, AIR SIAL, FLY JINNAH, flyadeal, FLYNAS
- Sectors: ISB-DMM, ISB-DXB, ISB-RUH, ISB-SHJ, LHE-DMM, LHE-DXB, LHE-RUH, LYP-SHJ, PEW-SHJ
- Categories: UAE (23), KSA ONEWAY (8)
- Travel date mode: EXACT_THEN_NEARBY ±3 days (server)

## CMS truth matrix

| Metric | Value |
|---|---|
| CMS_AFFECTED_FIELDS_TESTED | 3 (`group-search.hero.kicker/title/description`) |
| CMS_SAVE_PASS | 3 |
| CMS_API_PASS | 3 |
| CMS_PUBLIC_RENDER_PASS | 3 |
| CMS_REFRESH_PASS | 3 |
| CMS_RESTORE_PASS | 3 |
| CMS_FIELD_MISMATCHES | 0 |
| CMS_QA_TEXT_RESIDUE | 0 |
| CMS_MEDIA_FIELDS_TESTED | 0 (group-search schema has no media assets) |

Route constraint fix required for public API (`routes/web.php` allow `group-search`).

## Al-Haider safety

| Metric | Value |
|---|---|
| Auth mode | managed_token |
| Token generation enabled | false |
| Token generation calls | 0 |
| Booking gate | false |
| Read-only inventory | PASS |
| Group count | 29 |
| Airline count | 11 |

## Screenshots

01–12 UI matrix, 13–15 CMS text before/QA/restored, 16+18 homepage media baseline/restored frames (no media mutation this phase).

## Deployment summary

1. Staged 18 runtime files from `717691fb` → protected activate + PUBLIC_ONLY Next build.  
2. Live CMS API 404 diagnosed → route constraint missing `group-search`.  
3. Engineering fix `636584a3` staged (routes/web.php only) → activate without Next rebuild → CMS HTTP 200.
