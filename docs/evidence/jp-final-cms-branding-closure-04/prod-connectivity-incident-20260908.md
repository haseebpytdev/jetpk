# Production connectivity incident — Closure-04 CMS gate (2026-09-08)

**Engineering SHA (unchanged):** `20e921661da55e121a9b2353cba535b350613493`

## Timeline (UTC)
- ~12:46 — QA admin password synced vault→production (`jetpk:dash-03-qa-identities admin rotate-password`); login `HASH_OK=yes`
- ~12:57 — CMS 2.25MB upload probe: **422** at web; **2048 KB passes**
- ~13:05 — Identified `upload_max_filesize=2M` (CLI); raised global `php.ini` to `6M`, `lswsctrl restart`
- ~13:07 — Uploads >2048 KB still **422** (web still 2M-effective)
- ~13:08 — Attempted `lswsctrl stop; start` — SSH/HTTPS to `185.215.166.176` began **timing out**

## Evidence at last successful probe
- `https://jetpakistan.pk/` — previously **200**
- Admin auth — **200 OK**
- Ask 20-turn / trending / favicon gates — **PASS** in `closure-04-prod-gates.json` (CMS gates **FAIL**)

## Owner recovery (no app redeploy)
1. Restore LiteSpeed / host networking from provider console if SSH remains down:
   ```bash
   /usr/local/lsws/bin/lswsctrl start
   /usr/local/lsws/bin/lswsctrl status
   ```
2. Confirm web PHP limit (target `upload_max_filesize >= 6M`):
   - `/usr/local/lsws/lsphp83/etc/php/8.3/litespeed/php.ini`
   - Optionally add `phpIniOverride` under `extprocessor jetpk_lsphp` in `/usr/local/lsws/conf/vhosts/jetpakistan.pk/vhconf.conf`
3. Reload LiteSpeed gracefully; verify `curl -I https://jetpakistan.pk/`
4. Re-run production gates script

## Rollback PHP ini
```bash
cp /usr/local/lsws/lsphp83/etc/php/8.3/litespeed/php.ini.bak-closure04-* \
   /usr/local/lsws/lsphp83/etc/php/8.3/litespeed/php.ini
/usr/local/lsws/bin/lswsctrl restart
```

## Closure-04 status
**PARTIAL / TEMPORARILY_BLOCKED** — CMS 2.25MB production gate not proven; host connectivity unverified after restart attempt.
