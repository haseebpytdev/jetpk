# JetPakistan Final Visual UAT — LIVE STATUS (pre–ChatGPT gate)

## Release stamps (production, measured)

```
FINAL_RELEASE_SHA=08cb61c4e78ee6af340d11252c16f79ec7496945
REMOTE_MAIN_SHA=08cb61c4e78ee6af340d11252c16f79ec7496945
PRODUCTION_RUNTIME_SHA=08cb61c4e78ee6af340d11252c16f79ec7496945
PUBLIC_BUILD_SOURCE_SHA=08cb61c4e78ee6af340d11252c16f79ec7496945
DASHBOARD_BUILD_SOURCE_SHA=08cb61c4e78ee6af340d11252c16f79ec7496945
RUNTIME_MARKER=jp-08cb61c4-dash-rebuild4-1789719906
PUBLIC_BUILD_ID=coRh7xvX3Md4v9NYWjOZo
DASHBOARD_BUILD_ID=OoIQDvKbRztM6S1oRONLu
ROLLBACK_SHA=08cb61c4e78ee6af340d11252c16f79ec7496945
```

Public was already at exact `08cb61c4` (no public redeploy required).
Dashboard lagged at `7296741a`; rebuilt via temp-dir `force-dynamic` build (no production source mutation) and PM2 restarted.

## Company Profile E2E (reversible)

```
COMPANY_PROFILE_CANONICAL_UI=/admin/settings/branding
COMPANY_PROFILE_LOGO_E2E=PASS (apply QA temp → public config → restore exact original)
COMPANY_PROFILE_FAVICON_E2E=PASS (same)
PUBLICATION_ARCH=no_rebuild_cache_revalidate_via_resolver
```

QA temp assets deleted; originals restored (`LOGO_RESTORED=YES`, `FAV_RESTORED=YES`).

## Group payment live

Screenshots under `live/groups/group-payment-live-w*.png` from production hold (released after capture).
Visual self-review: H1 "Complete payment" before progress; distinct method cards; booking summary; CTA present; no duplicate price block observed.
Automated testid probe flaky (methods/CTA selectors) — do not treat DOM probe FAIL as visual hierarchy FAIL without screenshot review.
`PAYMENT_EXECUTED=NO`. Holds released.

## Logo / favicon matrix (anonymous live)

See `manifest-logo-favicon-matrix.json`.

Findings (honest):
- Home `/` header logo still `client-assets/jetpk/logo/logo.svg` (not Company Profile storage) → `STALE_LOGO_INSTANCES≥1`, `LOGO_PROJECT_WIDE=FAIL`
- Several public routes still emit `rel=icon` → `/favicon.ico` (200, image/x-icon, 251 bytes) while auth/groups emit Company Profile PNG → `FAVICON_PROJECT_WIDE=PARTIAL`, `DEFAULT_NEXT_FAVICON=6`
- No Parwaaz / master / old-OTA favicon strings observed

## Explicit non-claims

- VISUAL PASS — **not claimed**
- VERIFIED PASS — **not claimed**
- Preliminary local/mock Group Payment set — **not final**

## Artifact workflow

`gh` CLI not authenticated in this environment → `EVIDENCE_WORKFLOW_RUN_ID` / `EVIDENCE_ARTIFACT_ID` pending after evidence push + Actions run from authorized host.

## Source

All captures: `SOURCE=live production`, `RELEASE_SHA=08cb61c4…`, `SANITIZED=YES`.
