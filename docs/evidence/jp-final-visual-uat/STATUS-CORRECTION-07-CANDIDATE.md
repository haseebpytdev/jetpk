# JetPakistan Final Visual UAT — CORRECTION-07 approval candidate

RELEASE_SHA=db901c2c7a28a1eb08151e6e5ab3a29d75b7281f
PUBLIC_BUILD_ID=nme7eiDlifThiSEn_kQ9U
DASHBOARD_BUILD_ID=ztvU2IOk8lbYPPbBjMA7j
CAPTURED_AT=2026-09-18T11:50:00Z
SOURCE=live production
SANITIZED=YES

## Engineering corrections included

- CORRECTION-07: home SSR Company Profile branding; overflow containment; remove `frontend/app/favicon.ico`
- CORRECTION-07b: FAB footer clear
- CORRECTION-07c: Laravel dashboard layout Company Profile favicon emit
- CORRECTION-07d: error layout Company Profile favicon emit
- CORRECTION-07e: SSR Laravel auth fetches use absolute `LARAVEL_URL` (customer/agent portals)

## Active manifests (approval candidate)

| Manifest | SELF_REVIEW fails | Notes |
| --- | --- | --- |
| manifest-live.json | 0 | public/auth/groups visual |
| manifest-group-payment-live.json | 0 | payment methods + CTA scroll semantics |
| manifest-logo-favicon-matrix.json | 0 | LOGO+FAVICON project-wide PASS |
| manifest-portals-flights-live.json | 0 | customer/agent/admin/flights |
| manifest-functional-regression.json | 0 | FUNCTIONAL_REGRESSION=PASS |

Historical failed 08cb evidence remains in Git history on this branch; do not treat older commits as the active approval set.

## Gates (not VISUAL PASS / not VERIFIED PASS)

HOME_SSR_LOGO_SOURCE=Company Profile
HOME_HORIZONTAL_OVERFLOW_320=0
HOME_HORIZONTAL_OVERFLOW_360=0
LOGO_PROJECT_WIDE=PASS
FAVICON_PROJECT_WIDE=PASS
ADMIN_FAVICON=Company Profile
FAB_MEASUREMENT_VALIDATED=YES
FAB_MEANINGFUL_CONTENT_OVERLAP=0
GROUP_PAYMENT_METHOD_CARDS=PASS
GROUP_PAYMENT_CTA=PASS
FUNCTIONAL_REGRESSION=PASS
PAYMENT_EXECUTED=NO
