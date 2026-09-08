# CMS upload size probe — production (2026-09-08)

SHA: 20e921661da55e121a9b2353cba535b350613493  
Auth: QA admin login OK after vault→production password sync (`HASH_OK=yes`)

| File size | HTTP | Result |
|-----------|------|--------|
| 2048 KB | 200 | Asset uploaded |
| 2100 KB | 422 | The file failed to upload. |
| 2200 KB | 422 | The file failed to upload. |
| 2250 KB | 422 | The file failed to upload. |
| 2300 KB | 422 | The file failed to upload. |

## Root cause
Web-facing PHP enforces **upload_max_filesize = 2M** (exact ceiling at 2048 KB). Contract requires **~2250 KB** JPEG. Application validation allows 5120 KB (`ClientPageSettingsController::storeAsset`).

CLI `lsphp -i` reported `upload_max_filesize => 6M` after global `php.ini` edit + `lswsctrl restart`, but uploads **>2048 KB still failed**, indicating LSAPI/vhost workers had not picked up the new limit (or require vhost `phpIniOverride`).

## Infrastructure action attempted
- Edited `/usr/local/lsws/lsphp83/etc/php/8.3/litespeed/php.ini`: `2M → 6M`
- Backup: `php.ini.bak-closure04-*` on server
- `lswsctrl restart` then `lswsctrl stop; start` — **after full stop/start, HTTPS and SSH to `185.215.166.176` timed out** (see `prod-connectivity-incident-20260908.md`)

## Required to pass CMS_2250KB_DRAFT_UPLOAD
1. Restore host connectivity (LiteSpeed/SSH).
2. Apply **web-effective** `upload_max_filesize >= 6M` (global ini + vhost LSAPI reload/`phpIniOverride` as needed).
3. Re-run `node docs/evidence/jp-final-cms-branding-closure-04/run-closure-04-prod-gates.mjs`

No application code or engineering SHA change required.
