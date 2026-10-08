# Dashboard Home Control Matrix (JP-DASH-PROD-04)

| CONTROL | VISIBLE_IN_LIVE | EXPECTED_BEHAVIOR | CURRENT_BEHAVIOR (pre-04) | TARGET_ROUTE/ACTION | CLASSIFICATION | RESULT (post-04) |
|---------|-----------------|-------------------|---------------------------|---------------------|----------------|------------------|
| Recent bookings View | YES | Open booking detail in Next dashboard | `alert("Preview only")` | `/bookings/{id}` | OPERATIONAL_LINK | FIXED |
| Operational queue CTA | YES (count>0) | Navigate to ops destination | `alert("Preview only — Laravel …")` | `overview-route-map.ts` | OPERATIONAL_LINK | FIXED |
| Quick actions | YES | Navigate to ops destination | `alert("Preview — …")` | `overview-route-map.ts` | OPERATIONAL_LINK | FIXED |
| Refresh | YES | `router.refresh()` live data | Disabled + "coming soon" | client refresh | OPERATIONAL_ACTION | FIXED |
| Export report | YES (was visible) | Real export or hidden | Disabled in live | hidden in live | REMOVED_LIVE | HIDDEN_NOT_IMPLEMENTED |
| Live date chip | NO (live) | N/A | Disabled "Live" label | removed | REMOVED | FIXED |

No visible live control may remain `PREVIEW_ONLY`, `COMING_SOON`, or `DEAD`.
