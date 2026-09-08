# Production PHP upload limit adjustment — Closure-04 CMS gate
# Date: 2026-09-08 (UTC)
# SHA unchanged: 20e921661da55e121a9b2353cba535b350613493

## Root cause
CMS 2.25MB JPEG upload returned HTTP 422 `The file failed to upload.` while 2048KB succeeded.
Production PHP `upload_max_filesize` was **2M** (below contract 2250KB and app validation max 5120KB).

## Before
```
upload_max_filesize => 2M
post_max_size => 8M
```

## Change (infrastructure only — not application redeploy)
File: `/usr/local/lsws/lsphp83/etc/php/8.3/litespeed/php.ini`
Backup: `php.ini.bak-closure04-<timestamp>` on server
```
upload_max_filesize = 2M  →  upload_max_filesize = 6M
```
Reload: `/usr/local/lsws/bin/lswsctrl restart`

## After
```
upload_max_filesize => 6M
```

## Rollback
```bash
# restore backup on server, then:
/usr/local/lsws/bin/lswsctrl restart
```
