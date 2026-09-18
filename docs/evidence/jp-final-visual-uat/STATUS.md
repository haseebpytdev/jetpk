# CLOSURE-05 Visual Branding — Evidence Status (CORRECTION-06)

## Engineering tip (main)

FINAL_RELEASE_SHA_CANDIDATE=`08cb61c4e78ee6af340d11252c16f79ec7496945`

## This evidence branch

EVIDENCE_BRANCH=`evidence/jp-final-visual-uat-20260917`
Merged main CORRECTION-06 (workflow + source). Evidence branch retained — **do not delete**.

## What CORRECTION-06 fixed in source

- H1 Complete payment above BookingProgress
- Removed GroupPriceBlock duplicate on payment page
- Stronger `jp-fab-content-clear` (5.5rem) for Ask FAB
- customer/agent/flights layouts pass PublicConfig branding
- Dashboard favicon from Company Profile via public config
- Removed default Next `app/icon.png`

## Still NOT final visual certification

- Existing group payment PNGs remain **local mock** until protected deploy of `08cb61c4` + live recapture from https://jetpakistan.pk
- Full route matrix + logo/favicon live E2E + artifact run IDs still required

STATUS=VISUAL_APPROVAL_GATE_OPEN
SOURCE=local mocked (preliminary) until live deploy
VERIFIED_PASS=NO
VISUAL_PASS=NO
