# CLOSURE-05 Visual Branding — Evidence Status (NOT VERIFIED PASS)

## Handoff for ChatGPT visual review

EVIDENCE_BRANCH=evidence/jp-final-visual-uat-20260917
MANIFEST_PATH=docs/evidence/jp-final-visual-uat/manifest.md
SCREENSHOT_DIR=docs/evidence/jp-final-visual-uat/groups/

## Engineering (deploy candidate — visual gate still open)

- Group payment page restored to Golden card hierarchy: Complete payment → booking summary (mobile-first) → status/timer → method cards → details → submit CTA
- Client validation via `noValidate` + field errors + FormErrorSummary (no native-required tooltip dependency)
- Public config API emits Company Profile `logo_url` / `favicon_url` / `header_logo_height` / resolver `brand_name`
- Next root + public layouts resolve favicon from PublicConfig; header logo fallback uses existing `logo.svg`
- Email branding prefers `jetpk_company_branding()->logoUrl()`

## Local proof (synthetic / mocked)

- Playwright `group-ticketing.spec.ts`: 5 passed
- PHPUnit branding propagation + public config structure: passed
- Favicon static regression: passed
- Group payment screenshots: 320/360/375/390/412/768/1024/1440 + validation + method-selected states

## Still blocked for VERIFIED PASS

- Live production deploy of engineering SHA
- Live Group payment recapture after deploy
- Live Company Profile logo/favicon upload E2E + project-wide favicon matrix
- Full safe route screenshot sweep (public/auth/flights/checkout/portals)
- ChatGPT visual certification
- Exact SHA parity gates

STATUS=VISUAL_APPROVAL_GATE_OPEN
VERIFIED_PASS=NO
