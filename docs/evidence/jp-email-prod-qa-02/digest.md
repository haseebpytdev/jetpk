# JP-EMAIL-PROD-QA-02 / JP-DASHBOARD-PROFILE-BRANDING-01 digest

## Codex review (`2951a5ad`)

| File | Classification |
|---|---|
| `JetpkEmailQaContentAuditor.php` | REIMPLEMENT_ON_CURRENT_HEAD |
| `JetpkEmailPreviewCommand.php` | KEEP current HEAD; Codex extras are QA-run ideas, not a blind cherry-pick |
| `OtaEmailTemplateSmokeCommand.php` | ALREADY_SUPERSEDED / DROP |
| `AdminNewCustomerSignupMail.php` / `ManualBookingCommunicationMail.php` | REIMPLEMENT_ON_CURRENT_HEAD (`textString` is invalid on Laravel 13 Content) |
| `ClientMailBrandingResolver.php` | NEEDS_REVIEW / DROP generic Travel fallback |
| `CompanyEmailProfileResolver.php` | ALREADY_SUPERSEDED on current HEAD |
| `EmailBaseVariables.php` / `EmailPlaceholderFallbacks.php` | REIMPLEMENT_ON_CURRENT_HEAD (booking reference + brand fallback) |
| `JetpkEmailBrandingResolver.php` | ALREADY_SUPERSEDED |
| `docs/evidence/jp-email-qa-01/*` | DROP (older local SMTP / not production) |
| Tests (branded-fare / operational coverage / agency name) | NEEDS_REVIEW — port assertions only where they still apply |

CODEX_WORK_REVIEWED=YES

## Performance carry-forward (no re-open)

SHELL_TO_USABLE_APP_P95_ALL_FLOWS=1025
SHELL_TO_USABLE_APP_P95_AUTHORITY_REUSE=852
SAFE_AUTHORITY_FALLBACK_EXCEPTION=YES

AUTHORITY_PERSISTENCE_PROVEN_DEFECT_COUNT=0
AUTHORITY_PERSISTENCE_UNKNOWN_COUNT=2
FARE_CHANGE_ACCEPTANCE_CAUSE_RECOVERED=NO

R3 raw evidence (`traveler-warm-final02r3-n30.json`) lists `requires_fare_change_acceptance` as a response **key** only. The boolean value for rows `return-fare-final02-19` and `-25` was not stored. Do not fabricate.

EMAIL_SCENARIOS_DISCOVERED=96
ACTIVE_ROLE_COPIES=163
LOCAL_RENDER_SMOKE=2


## Live branding mutation

LIVE_BRANDING_MUTATION_REQUIRES_USER_DECISION=YES

Logo/favicon upload uses existing `AgencyBrandingController` + `AgencyBrandingService::uploadMedia`. Production public logo was not replaced in this phase.

## Gmail

GMAIL_INBOX_VERIFICATION=PENDING_CHATGPT
