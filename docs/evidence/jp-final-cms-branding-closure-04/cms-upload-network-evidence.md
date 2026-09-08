# CMS 2.25MB upload — browser/network evidence

**SHA:** `20e921661da55e121a9b2353cba535b350613493`  
**Captured:** 2026-09-08T13:33:48Z  
**Runner:** `run-closure-04-prod-gates.mjs` (Playwright multipart, no base64)

## Network summary

| Step | Method | URL | HTTP | Result |
|------|--------|-----|------|--------|
| Upload | POST | `/admin/page-settings/home/assets?format=json` | **200** | `ok: true`, `message: Asset uploaded.` |
| Editor record | GET | `/admin/page-settings/home?format=json` | **200** | Asset `id=20`, key `route_closure04_qa_1788874430584`, size **2304000** |
| Media URL | HEAD (curl) + GET (browser) | `/storage/client-assets/.../route_closure04_qa_1788874430584-20260908133352.jpg` | **200** / **200** | |
| Draft preview begin | POST | `/admin/page-settings/home/preview?format=json` | **200** | `preview_token` issued |
| Draft preview API | GET | `/laravel/api/public/content/homepage?jp_preview=1&jp_preview_token=...` | **200** | |
| Preview image fetch | GET (browser on preview page) | media URL above | **200** | |
| Published probe | GET | `/laravel/api/public/content/homepage` (no preview) | **200** | `contains_asset_key: false` |
| Cleanup | DELETE | `/admin/page-settings/home/assets/20?force=1` | **302** | `asset_record_removed: true` |

Full machine JSON: `cms-upload-evidence.json`

## Mandatory CMS gates

| Gate | Status |
|------|--------|
| CMS_2250KB_DRAFT_UPLOAD | **PASS** (UPLOAD_HTTP=200, 2304000 bytes) |
| CMS_ASSET_RECORD | **PASS** |
| CMS_MEDIA_URL_HTTP_200 | **PASS** |
| CMS_DRAFT_PREVIEW | **PASS** |
| TEST_FIXTURE_NOT_PUBLISHED | **PASS** |
| CMS_TEST_FIXTURE_CLEANUP | **PASS** |

## Server logs

Read-only tail of `storage/logs/laravel.log` at probe time showed no ERROR for the upload path (successful asset store is not error-logged). No `production.ERROR` entries tied to `closure04` fixture.

## Screenshots

Playwright screenshot step failed (`caret: omit` unsupported in this Playwright build). **Not treated as PASS-by-omission** — documented in `closure-04-prod-gates.json` `limitations`. Network/HTTP evidence above is authoritative for CMS gate.
