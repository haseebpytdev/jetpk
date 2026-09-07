# JETPAKISTAN-P2-FINAL-VISUAL-EMAIL-UAT

Date: 2026-09-07  
Branch: `phase/jp-master-unfinished-closure-10`  
SHA: `da660bf5cf5fd19395eb069fac62705309532c32`

## Gate 1 — Browser rendering

Engine: Playwright Chromium (headless) via `docs/evidence/jp-email-p2/screenshot-p2-visual-uat.mjs`  
Artifacts: `docs/evidence/jp-email-p2/artifacts/*.html`  
Screenshots: `docs/evidence/jp-email-p2/screenshots/*-w{1280,430,390,360,320}.png` (25 files)

Metrics (`visual-uat-gate1.json`):

- SCREENSHOT_EVIDENCE_CREATED=YES
- DESKTOP_VISUAL_QA=PASS
- MOBILE_430/390/360/320=PASS
- HORIZONTAL_OVERFLOW=0
- CUT_OFF_COMPONENTS=0
- BROKEN_BUTTONS=0
- BROKEN_TABLES=0

## Gate 2 — Long data stress

Artifact: `artifacts/booking-long-data-stress.html`  
Screenshots: `booking-long-data-stress-w1280.png`, `booking-long-data-stress-w320.png`  
Metrics (`visual-uat-gate2-long-data.json`): LONG_DATA_OVERFLOW=0, LONG_DATA_CUT_OFF=0, LONG_DATA_LAYOUT=PASS

Stress fields: long passenger name, long company name (footer copyright), long email, long booking reference, multi-sector route, large PKR amount.

## Gate 3–6 — Production Gmail sample (P0 auth path)

Path: `AuthSecurityEmailNotificationService::notifyLoginSuccess` to naturally resolved platform admin Gmail (`my***@gmail.com`).  
Not the bare `OtaNotificationService` fallback (that hits `ad***@ota.local` with subject `Successful sign-in to Admin Portal`).

```
EVENT=admin_login_success
RECIPIENT_ROLE=PLATFORM_ADMIN
SUBJECT=JetPakistan — Admin sign-in detected
UTC_TIMESTAMP=2026-09-07T16:41:46Z
SENDER=ota@jetpakistan.pk
COMM_LOG_ID=282
GMAIL_MESSAGE_ID=1a07cbf4e82f2b25
```

Gmail HTML confirms: jetpk-container, white header, Visit site, green divider `#63B32E`, security content, Reset password → `https://jetpakistan.pk/forgot-password`, no localhost, no Operational Alert shell.

### MIME finding + narrow fix

Pre-fix RAW MIME for the 16:41Z sample was `Content-Type: text/html` only — `OtaOperationalNotificationMail` accepted `plainBody` but never attached a text part.

Fix: wire `text: emails.themes.jetpakistan.plain-text` (same pattern as `JetpkOperationalEventMail`).  
Requires protected deploy of the engineering SHA after this commit, then a second Gmail proof.

## Gate 7 — Tests

`php artisan test --filter="AuthLoginSecurityEmailCanonicalTest|P2CanonicalShellConsistencyTest|SettingsTestEmailModernLayoutTest|AuthEmailRendererCanonicalShellTest|operational_notification_mail_sends_without_treating_plain_body"`  
→ 14 passed, 210 assertions.

Note: `OtaOperationalNotificationModernLayoutTest::operational_notification_sends_modern_layout_html` still expects exactly 1 send, but `BookingManualReviewRequired` resolves multiple recipient buckets — pre-existing assertion drift, out of this UAT's MIME fix scope.

CODE_FIX_REQUIRED=YES (text/plain MIME)  
DEPLOYED_IF_FIX_REQUIRED=pending protected deploy after commit
