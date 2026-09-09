# Root cause — DEPLOY-HYGIENE-001

## Summary

Protected JetPakistan deployment wrappers run over SSH as **root**. Several steps in the
deploy chain create or preserve files under Laravel writable runtime paths without
`pkjetp:pkjetp` ownership. After Closure-05, `storage/framework/cache/data/db/0c` was
`root:root`, causing `file_put_contents(...): Permission denied` and flight-search cache
failures until manual `chown`.

## Required conclusion

| Field | Value |
|---|---|
| ROOT_CAUSE_SCRIPT | `jetpk-deploy.sh` (primary), `jetpk-pre-proxy-gate.sh` (secondary), `jetpk-stage-release.sh` (staging extract) |
| ROOT_CAUSE_COMMAND | Root-run `rsync`/`cp`/`mkdir` in `jetpk-deploy.sh`; root-run `$PHP artisan migrate:status` (full deploy path); root-run `/usr/local/lsws/lsphp83/bin/php … artisan migrate:status` in `jetpk-pre-proxy-gate.sh`; root-run remote `tar xzf` extract in `jetpk-stage-release.sh` |
| WHY_FILES_BECAME_ROOT_OWNED | SSH deploy sessions execute as root. File copies, archive extracts, and artisan invocations without `sudo -u pkjetp` inherit root ownership. `optimize:clear` as `pkjetp` cannot delete root-owned cache entries, so root-owned paths persist and block runtime writes by PHP-FPM/`pkjetp`. |

## Root-cause matrix

| SCRIPT | COMMAND | EXECUTING_USER | WRITES_TO | EXPECTED_OWNER | ACTUAL_OWNER (Closure-05) | ROOT_CAUSE_CANDIDATE |
|---|---|---|---|---|---|---|
| `jetpk-deploy.sh` | `rsync -a "$RELEASE_DIR/…/" "$APP/…/"` | root | app/config/routes/resources/frontend trees | pkjetp:pkjetp | root:root (when created) | YES |
| `jetpk-deploy.sh` | `$PHP artisan migrate:status` (full-tree path) | root | bootstrap/cache, possible cache side-effects | pkjetp:pkjetp | root:root | YES |
| `jetpk-deploy.sh` | `$PHP composer install` (full-tree path) | root | vendor/bootstrap artifacts | pkjetp:pkjetp | root:root | YES |
| `jetpk-deploy.sh` | `sudo -u pkjetp … artisan optimize:clear` | pkjetp | storage/framework/cache, views, bootstrap/cache | pkjetp:pkjetp | mixed if prior root entries remain | NO (correct user; cannot fix pre-existing root files) |
| `jetpk-pre-proxy-gate.sh` | `/usr/local/lsws/lsphp83/bin/php … artisan migrate:status` | root | bootstrap/cache compiled artifacts | pkjetp:pkjetp | root:root | YES |
| `jetpk-stage-release.sh` | remote `tar xzf … -C release_dir` | root | `/home/pkjetp/releases/jetpk-*` staging tree | pkjetp:pkjetp | root:root | YES |
| `jetpk-next-build.sh` | `npm ci` / `npm run build` | pkjetp | frontend/.next, dashboard/.next | pkjetp:pkjetp | pkjetp:pkjetp | NO |

## Closure-05 evidence

- Symptom/log: `docs/evidence/jp-homepage-groups-authority-closure-05/logs/postdeploy-cache-permission-fix.txt`
- Emergency remediation: manual `chown -R pkjetp:pkjetp` on cache/views/bootstrap/cache
- Deploy sequence used server `/tmp/jetpk-deploy.sh` generation `5477c989…` (pre-fix)

## Permanent fix (implemented)

1. Tracked helper `scripts/jetpk/assert-runtime-ownership.sh` — scoped normalize + assert gate
2. `jetpk-deploy.sh` — run remaining artisan/composer as `pkjetp`; normalize + assert at end
3. `jetpk-pre-proxy-gate.sh` — artisan as `pkjetp`; hard assert gate before PASS
4. `jetpk-stage-release.sh` — `chown -R pkjetp:pkjetp` on remote release dir after extract
