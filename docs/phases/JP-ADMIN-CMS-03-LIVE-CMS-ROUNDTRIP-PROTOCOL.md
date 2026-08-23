# JP-ADMIN-CMS-03 — Protected Live CMS Round-Trip Protocol

**DO NOT EXECUTE during engineering.** Run only after independent protected deployment review/authorization.

## Preconditions

- Production runtime SHA matches `FINAL_ADMIN_CMS03_ENGINEERING_SHA`
- Owner authorized reversible UAT mutation on jetpakistan.pk
- No real AbhiPay credentials, PNR, booking, ticket, cancel, or payment state

## Steps

1. Snapshot current published Homepage JSON (`/api/public/content/homepage`) + relevant `client_page_assets` for the selected card.
2. Record current public Homepage screenshot + content hash.
3. Admin CMS: upload a harmless synthetic test image (small PNG with explicit UAT watermark).
4. Assign it to ONE selected Homepage card (route / destination / featured deal).
5. Change ONE harmless visible CMS text value with explicit temporary marker `UAT_CMS03_LIVE_<timestamp>`.
6. Save draft.
7. Confirm normal public Homepage unchanged (no marker, prior media).
8. Preview draft (`jp_preview=1`) — verify text + image.
9. Publish.
10. Verify public API exact text/media.
11. Verify live jetpakistan.pk renders exact temporary text/media.
12. Measure propagation time (expect ≤10s with `cache: no-store` homepage fetch).
13. Immediately restore original text/media through CMS.
14. Publish restored baseline.
15. Verify final public page exactly matches baseline snapshot.
16. Verify no orphan/public test marker remains.
17. Verify media upload persisted (or removed) correctly on server.
18. Verify logs contain no CMS/media errors.

## Required production gates (post-deploy only)

- LIVE_CMS_UPLOAD=PASS
- LIVE_CMS_PREVIEW=PASS
- LIVE_CMS_PUBLISH=PASS
- LIVE_CMS_PUBLIC_TEXT_PARITY=PASS
- LIVE_CMS_PUBLIC_MEDIA_PARITY=PASS
- LIVE_CMS_RESTORE=PASS
- LIVE_CMS_FINAL_BASELINE=PASS

## Rollback

If restore fails: re-upload baseline JSON/assets from step 1 snapshot and publish; clear CDN/app caches if any intermediate layer exists beyond Next `no-store`.
