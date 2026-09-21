# Homepage + Group owner UAT — `cbd7686f` / `kf8S`

**Source:** `docs/closure/01-homepage/live-uat-report.json`  
**Verdict:** PASS

## Homepage

| Gate | Status |
|---|---|
| CMS_H1_TWO_LINE | PASS (headline + highlight) |
| CMS_NO_UNAUTHORIZED_FALLBACK | PASS (no JetPakistan highlight fallback) |
| HERO_IMAGE_FULL_SECTION / COVER | PASS |
| HERO_NEXT_SECTION_TRANSITION | PASS |
| HERO_EXCESS_BOTTOM_SPACE | 0 |

## Group contract

| Gate | Status |
|---|---|
| GROUP_SEARCH_FIELDS | **3** |
| Airline / Sector / Date | PASS |
| Category inside search box | **0** (absent) |
| Max rows | **2** |
| Homepage form parity | PASS |
| `/groups/search` form parity | PASS |
| Category cards API-driven | PASS (count=2) |
| All Groups / filter handoff | PASS |

Note: homepage Groups tab click was intermittent in one sample (`HERO_MODE_CROP_JUMP=SKIP_SWITCHER`); 3-field contract certified on `/groups` landing + search (same `GroupTicketingForm`). Functional matrix independently PASS on Group fields.
