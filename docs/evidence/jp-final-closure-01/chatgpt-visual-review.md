# ChatGPT visual review notes — JP-FINAL-CLOSURE-01 R3

## Email pack (16 + mobile 6)

- Source: `storage/app/email-previews/jetpk/*_{role}.html` via `jetpk:email-preview`
- Shots: `docs/evidence/jp-final-closure-01/email/preview-shots/`
- Manifest: `preview-shot-manifest.json` → `EMAIL_VISUAL_PACK=PASS`
- Checks: no `hello@example.com`, no `jetpk.test`, no Manage booking on security/verification, no Dear Team on non-ops user templates
- Support phone `+92 300 4455667` present from company/`ota-client` config (classified accepted, not invent)

## Live surfaces

- Responsive 5×5 (home/groups/groups_search/flights/support × 1440/1366/1024/768/390): PASS
- Flights: oneway/details/traveler×10/review/return pair/segmented/nearby preserved/change/bfcache: PASS
- Groups discovery + local bookings JFZZT2DJ / WZBJCK6Z: PASS (prior)
- Admin CMS path corrected to `/admin/dashboard/cms/pages`: PASS
- QA ML inventory deactivated after shots: PASS

## Known visual caveats

- Do not use unsuffixed `booking_confirmed.html` / `payment_rejected_user.html` leftovers from older preview commands for certification
- First Book Now sample includes progressive warm (~28s); steady p50 ~19.6s
