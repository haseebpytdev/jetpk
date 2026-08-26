# JP-API-CMS Final Closeout — Live Evidence

**UTC folder:** `20260826T202942Z`  
**Engineering SHA:** `7fbb1301e5e96eadb0acdc65e0b5fba149eb0a35`  
**Host:** https://jetpakistan.pk  
**Capture note:** Admin shots via QA identity after vault sync. Token/password fields blurred where present. No Authorization headers, raw tokens, or PII.

| File | Surface | Proves |
| --- | --- | --- |
| `01-api-modules.png` | `/admin/dashboard/integrations` | Single **API & Modules** sidebar entry; Al-Haider card Connected / last health ok |
| `02-alhaider-config-masked.png` | Al-Haider Configure modal | JPak Group configure surface; no raw token |
| `03-alhaider-managed-renewal-config-masked.png` | Al-Haider Configure modal | Same configure surface (managed_token authority verified via probe, not UI secret reveal) |
| `04-alhaider-test-pass.png` | Al-Haider Configure / health context | Last health **ok** aligned with live Test Connection probe |
| `05-sabre-channel-toggles.png` | Integrations + Sabre context | Sabre connections visible without credential leak |
| `06-smtp-managed.png` | SMTP Configure modal | **Mail: env fallback** visible; invalid-active DB → ENV fallback |
| `07-cms-text-admin-temp.png` | `/admin/dashboard/cms/sections` | Homepage builder connected text controls |
| `08-cms-text-public-temp.png` | `/` public | Public homepage (post-restore; temp QA text not left live) |
| `09-cms-text-restored.png` | `/` public | Public homepage restored / production content |
| `10-cms-media-admin-temp.png` | `/admin/dashboard/cms/assets` | Media library admin |
| `11-cms-media-public-temp.png` | `/` public | Public hero/media present |
| `12-cms-media-restored.png` | `/` public | Public media restored (hero bytes 1,948,204; support CTA intact) |

## Companion probe evidence (tmp, not secret)

- `tmp/jp-api-cms-final-closeout-cms-matrix.out` — 28/28 text + 3/3 media, `CMS_QA_TEXT_RESIDUE=0`
- `tmp/jp-api-cms-final-closeout-alhaider-*.out` — managed_token, test ok, inventory 28 groups / 11 airlines, token gen calls = 0
- `tmp/jp-api-cms-final-closeout-smtp-sidebar.out` — SMTP invalid→ENV fallback PASS; sidebar single API & Modules in groups
