# CMS Confirmation Coverage — Homepage CMS Authority 06

Shared component: `dashboard/features/cms/components/cms-action-notice.tsx`

## Homepage builder (`homepage-settings-panel.tsx`)

| Action | Success path | Failure path | Double-submit |
|--------|--------------|--------------|---------------|
| SAVE_DRAFT | `persist()` → `CmsActionNotice` success | `setError` → `CmsActionNotice` error | `busy` disables sticky buttons |
| PREVIEW | `previewDraft()` → success banner | error banner | `busy` disables actions |
| PUBLISH | `persist(..., true)` → `cms-publish-success` test id | error banner | `busy` disables actions |
| MEDIA_UPLOAD | per-section upload → `setSuccess` | `setError` in catch | upload is per-card |
| MEDIA_REPLACE | same as upload | same | same |
| MEDIA_REMOVE | `setSuccess` on key clear | n/a | n/a |

Duplicate inline success/error paragraphs removed; only `CmsActionNotice` renders at panel top.

## Company profile / branding (`organization-profile-form.tsx`)

| Action | Success | Failure | Double-submit |
|--------|---------|---------|---------------|
| COMPANY_PROFILE_UPDATE | `company-profile-success` | `company-profile-error` | `saving` disables save |
| BRANDING_UPDATE (logo/favicon) | `company-profile-success` | `company-branding-error` | `mediaBusy` disables upload |

## Gaps

None blocking deploy. Hero focal/overlay native selects retain inline validation only (no duplicate banners).

CMS_FEEDBACK_VERIFIER=**READY** (pending Grok)
